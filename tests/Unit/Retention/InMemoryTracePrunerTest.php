<?php

declare(strict_types=1);

use AdilAzhari\LaravelTrace\Retention\InMemoryTracePruner;
use AdilAzhari\LaravelTrace\Retention\PruneCriteria;
use AdilAzhari\LaravelTrace\Trace\TraceId;
use AdilAzhari\LaravelTrace\Trace\TraceStatus;
use AdilAzhari\LaravelTrace\Tracing\InMemorySpanStore;
use AdilAzhari\LaravelTrace\Tracing\InMemoryTraceStore;

function inMemoryPruner(): array
{
    $traceStore = new InMemoryTraceStore;
    $spanStore = new InMemorySpanStore;

    return [new InMemoryTracePruner($traceStore, $spanStore), $traceStore, $spanStore];
}

$cutoff = new DateTimeImmutable('2026-06-01 00:00:00.000000');

// --- A. Boundary ---------------------------------------------------------

it('retains a trace started exactly at the cutoff', function () use ($cutoff): void {
    [$pruner, $traceStore] = inMemoryPruner();

    $traceStore->put(makeTrace(status: TraceStatus::Completed, startedAt: $cutoff));

    $result = $pruner->prune(new PruneCriteria($cutoff));

    expect($result->tracesMatched)->toBe(0)
        ->and($traceStore->all())->toHaveCount(1);
});

it('prunes a trace started one microsecond before the cutoff', function () use ($cutoff): void {
    [$pruner, $traceStore] = inMemoryPruner();

    $traceStore->put(makeTrace(
        status: TraceStatus::Completed,
        startedAt: $cutoff->modify('-1 microsecond'),
    ));

    $result = $pruner->prune(new PruneCriteria($cutoff));

    expect($result->tracesMatched)->toBe(1)
        ->and($traceStore->all())->toHaveCount(0);
});

// --- B. Status -------------------------------------------------------------

it('prunes a completed trace older than the cutoff', function () use ($cutoff): void {
    [$pruner, $traceStore] = inMemoryPruner();

    $traceStore->put(makeTrace(status: TraceStatus::Completed, startedAt: $cutoff->modify('-1 day')));

    expect($pruner->prune(new PruneCriteria($cutoff))->tracesMatched)->toBe(1);
});

it('prunes a failed trace older than the cutoff', function () use ($cutoff): void {
    [$pruner, $traceStore] = inMemoryPruner();

    $traceStore->put(makeTrace(status: TraceStatus::Failed, startedAt: $cutoff->modify('-1 day'), withError: true));

    expect($pruner->prune(new PruneCriteria($cutoff))->tracesMatched)->toBe(1);
});

it('never prunes a running trace, no matter how old', function () use ($cutoff): void {
    [$pruner, $traceStore] = inMemoryPruner();

    $traceStore->put(makeTrace(status: TraceStatus::Running, startedAt: $cutoff->modify('-30 days')));

    $result = $pruner->prune(new PruneCriteria($cutoff));

    expect($result->tracesMatched)->toBe(0)
        ->and($traceStore->all())->toHaveCount(1);
});

// --- C. New traces -----------------------------------------------------------

it('retains completed and failed traces newer than the cutoff', function () use ($cutoff): void {
    [$pruner, $traceStore] = inMemoryPruner();

    $traceStore->put(makeTrace(status: TraceStatus::Completed, startedAt: $cutoff->modify('+1 day')));
    $traceStore->put(makeTrace(status: TraceStatus::Failed, startedAt: $cutoff->modify('+1 day'), withError: true));

    $result = $pruner->prune(new PruneCriteria($cutoff));

    expect($result->tracesMatched)->toBe(0)
        ->and($traceStore->all())->toHaveCount(2);
});

// --- D. Span cleanup -----------------------------------------------------

it('removes the spans of a pruned trace and keeps the spans of a retained trace', function () use ($cutoff): void {
    [$pruner, $traceStore, $spanStore] = inMemoryPruner();

    $oldTraceId = TraceId::generate();
    $newTraceId = TraceId::generate();

    $traceStore->put(makeTrace(id: $oldTraceId, status: TraceStatus::Completed, startedAt: $cutoff->modify('-1 day')));
    $traceStore->put(makeTrace(id: $newTraceId, status: TraceStatus::Completed, startedAt: $cutoff->modify('+1 day')));

    $oldSpan = makeSpan($oldTraceId, 'old-span');
    $newSpan = makeSpan($newTraceId, 'new-span');
    $spanStore->put($oldSpan);
    $spanStore->put($newSpan);

    $result = $pruner->prune(new PruneCriteria($cutoff));

    expect($result->spansMatched)->toBe(1)
        ->and($spanStore->get($oldSpan->id->value))->toBeNull()
        ->and($spanStore->get($newSpan->id->value))->not->toBeNull();
});

// --- E. Dry-run ------------------------------------------------------------

it('reports exact matching counts for a dry run without mutating either store', function () use ($cutoff): void {
    [$pruner, $traceStore, $spanStore] = inMemoryPruner();

    $traceId = TraceId::generate();
    $traceStore->put(makeTrace(id: $traceId, status: TraceStatus::Completed, startedAt: $cutoff->modify('-1 day')));
    $spanStore->put(makeSpan($traceId, 'span'));

    $result = $pruner->prune(new PruneCriteria($cutoff, dryRun: true));

    expect($result->dryRun)->toBeTrue()
        ->and($result->tracesMatched)->toBe(1)
        ->and($result->spansMatched)->toBe(1)
        ->and($traceStore->all())->toHaveCount(1)
        ->and($spanStore->all())->toHaveCount(1);
});

// --- F. Chunking -----------------------------------------------------------

it('exhausts every eligible trace across multiple chunks in a single call', function () use ($cutoff): void {
    [$pruner, $traceStore] = inMemoryPruner();

    foreach (range(1, 12) as $i) {
        $traceStore->put(makeTrace(status: TraceStatus::Completed, startedAt: $cutoff->modify('-1 day')));
    }

    $result = $pruner->prune(new PruneCriteria($cutoff, chunkSize: 5));

    expect($result->tracesMatched)->toBe(12)
        ->and($result->chunksProcessed)->toBe(3)
        ->and($traceStore->all())->toHaveCount(0);
});

// --- G. Idempotency ----------------------------------------------------------

it('matches nothing on a second run once the first exhausted eligible traces', function () use ($cutoff): void {
    [$pruner, $traceStore] = inMemoryPruner();

    $traceStore->put(makeTrace(status: TraceStatus::Completed, startedAt: $cutoff->modify('-1 day')));

    $pruner->prune(new PruneCriteria($cutoff));
    $second = $pruner->prune(new PruneCriteria($cutoff));

    expect($second->tracesMatched)->toBe(0)
        ->and($second->spansMatched)->toBe(0);
});
