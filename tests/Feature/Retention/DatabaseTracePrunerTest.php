<?php

declare(strict_types=1);

use AdilAzhari\LaravelTrace\Contracts\Tracer;
use AdilAzhari\LaravelTrace\Models\SpanRecord;
use AdilAzhari\LaravelTrace\Models\TraceRecord;
use AdilAzhari\LaravelTrace\Retention\DatabaseTracePruner;
use AdilAzhari\LaravelTrace\Retention\PruneCriteria;
use AdilAzhari\LaravelTrace\Trace\TraceId;
use AdilAzhari\LaravelTrace\Trace\TraceStatus;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

beforeEach(function (): void {
    config()->set('laravel-trace.storage.driver', 'database');
});

$cutoff = new DateTimeImmutable('2026-06-01 00:00:00.000000');

// --- A. Boundary ---------------------------------------------------------

it('retains a trace started exactly at the cutoff', function () use ($cutoff): void {
    recordTrace(makeTrace(status: TraceStatus::Completed, startedAt: $cutoff));

    $result = app(DatabaseTracePruner::class)->prune(new PruneCriteria($cutoff));

    expect($result->tracesMatched)->toBe(0)
        ->and(TraceRecord::query()->count())->toBe(1);
});

it('prunes a trace started one microsecond before the cutoff', function () use ($cutoff): void {
    recordTrace(makeTrace(status: TraceStatus::Completed, startedAt: $cutoff->modify('-1 microsecond')));

    $result = app(DatabaseTracePruner::class)->prune(new PruneCriteria($cutoff));

    expect($result->tracesMatched)->toBe(1)
        ->and(TraceRecord::query()->count())->toBe(0);
});

// --- B. Status ---------------------------------------------------------------

it('prunes a completed trace older than the cutoff', function () use ($cutoff): void {
    recordTrace(makeTrace(status: TraceStatus::Completed, startedAt: $cutoff->modify('-1 day')));

    expect(app(DatabaseTracePruner::class)->prune(new PruneCriteria($cutoff))->tracesMatched)->toBe(1);
});

it('prunes a failed trace older than the cutoff', function () use ($cutoff): void {
    recordTrace(makeTrace(status: TraceStatus::Failed, startedAt: $cutoff->modify('-1 day'), withError: true));

    expect(app(DatabaseTracePruner::class)->prune(new PruneCriteria($cutoff))->tracesMatched)->toBe(1);
});

it('never prunes a running trace, no matter how old', function () use ($cutoff): void {
    recordTrace(makeTrace(status: TraceStatus::Running, startedAt: $cutoff->modify('-30 days')));

    $result = app(DatabaseTracePruner::class)->prune(new PruneCriteria($cutoff));

    expect($result->tracesMatched)->toBe(0)
        ->and(TraceRecord::query()->count())->toBe(1);
});

// --- C. New traces -------------------------------------------------------------

it('retains completed and failed traces newer than the cutoff', function () use ($cutoff): void {
    recordTrace(makeTrace(status: TraceStatus::Completed, startedAt: $cutoff->modify('+1 day')));
    recordTrace(makeTrace(status: TraceStatus::Failed, startedAt: $cutoff->modify('+1 day'), withError: true));

    $result = app(DatabaseTracePruner::class)->prune(new PruneCriteria($cutoff));

    expect($result->tracesMatched)->toBe(0)
        ->and(TraceRecord::query()->count())->toBe(2);
});

// --- D. Span cleanup -----------------------------------------------------

it('deletes the spans of a pruned trace and keeps the spans of a retained trace', function () use ($cutoff): void {
    $oldTraceId = TraceId::generate();
    $newTraceId = TraceId::generate();

    recordTrace(makeTrace(id: $oldTraceId, status: TraceStatus::Completed, startedAt: $cutoff->modify('-1 day')));
    recordTrace(makeTrace(id: $newTraceId, status: TraceStatus::Completed, startedAt: $cutoff->modify('+1 day')));

    $oldSpan = recordSpan(makeSpan($oldTraceId, 'old-span'));
    $newSpan = recordSpan(makeSpan($newTraceId, 'new-span'));

    $result = app(DatabaseTracePruner::class)->prune(new PruneCriteria($cutoff));

    expect($result->spansMatched)->toBe(1)
        ->and(SpanRecord::query()->find($oldSpan->id->value))->toBeNull()
        ->and(SpanRecord::query()->find($newSpan->id->value))->not->toBeNull();
});

// --- E. Dry-run ----------------------------------------------------------------

it('reports exact matching counts for a dry run without modifying storage', function () use ($cutoff): void {
    $traceId = TraceId::generate();
    recordTrace(makeTrace(id: $traceId, status: TraceStatus::Completed, startedAt: $cutoff->modify('-1 day')));
    recordSpan(makeSpan($traceId, 'span'));

    $result = app(DatabaseTracePruner::class)->prune(new PruneCriteria($cutoff, dryRun: true));

    expect($result->dryRun)->toBeTrue()
        ->and($result->tracesMatched)->toBe(1)
        ->and($result->spansMatched)->toBe(1)
        ->and(TraceRecord::query()->count())->toBe(1)
        ->and(SpanRecord::query()->count())->toBe(1);
});

// --- F. Chunking -----------------------------------------------------------------

it('exhausts every eligible trace across multiple chunks in a single call', function () use ($cutoff): void {
    foreach (range(1, 12) as $i) {
        recordTrace(makeTrace(status: TraceStatus::Completed, startedAt: $cutoff->modify('-1 day')));
    }

    $result = app(DatabaseTracePruner::class)->prune(new PruneCriteria($cutoff, chunkSize: 5));

    expect($result->tracesMatched)->toBe(12)
        ->and($result->chunksProcessed)->toBe(3)
        ->and(TraceRecord::query()->count())->toBe(0);
});

// --- G. Idempotency ------------------------------------------------------------

it('matches nothing on a second run once the first exhausted eligible traces', function () use ($cutoff): void {
    recordTrace(makeTrace(status: TraceStatus::Completed, startedAt: $cutoff->modify('-1 day')));

    app(DatabaseTracePruner::class)->prune(new PruneCriteria($cutoff));
    $second = app(DatabaseTracePruner::class)->prune(new PruneCriteria($cutoff));

    expect($second->tracesMatched)->toBe(0)
        ->and($second->spansMatched)->toBe(0);
});

it('is safe to re-run after a partial failure between the span and trace delete', function () use ($cutoff): void {
    // Simulates the process dying after spans were deleted but before the
    // trace row was: the next run must still finish deleting the trace.
    $traceId = TraceId::generate();
    recordTrace(makeTrace(id: $traceId, status: TraceStatus::Completed, startedAt: $cutoff->modify('-1 day')));

    SpanRecord::query()->where('trace_id', $traceId->value)->delete();

    $result = app(DatabaseTracePruner::class)->prune(new PruneCriteria($cutoff));

    expect($result->tracesMatched)->toBe(1)
        ->and(TraceRecord::query()->count())->toBe(0);
});

// --- H. Failure visibility ------------------------------------------------------

it('lets a database failure propagate instead of swallowing it', function () use ($cutoff): void {
    Schema::dropIfExists('laravel_trace_spans');
    Schema::dropIfExists('laravel_traces');

    expect(fn () => app(DatabaseTracePruner::class)->prune(new PruneCriteria($cutoff)))
        ->toThrow(QueryException::class);
});

// --- I. Non-instrumentation ------------------------------------------------------

it('does not generate database.query spans for its own queries', function () use ($cutoff): void {
    recordTrace(makeTrace(status: TraceStatus::Completed, startedAt: $cutoff->modify('-1 day')));

    DB::enableQueryLog();

    app(Tracer::class)->start('outer');
    app(DatabaseTracePruner::class)->prune(new PruneCriteria($cutoff));

    // The pruning queries themselves must not have been recorded as spans:
    // no trace/span rows should exist for a "database.query" span pointing
    // at the pruner's own SQL.
    expect(SpanRecord::query()->where('type', 'database')->count())->toBe(0);
});
