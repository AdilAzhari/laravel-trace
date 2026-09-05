<?php

declare(strict_types=1);

use AdilAzhari\LaravelTrace\Models\SpanRecord;
use AdilAzhari\LaravelTrace\Models\TraceRecord;
use AdilAzhari\LaravelTrace\Span\Span;
use AdilAzhari\LaravelTrace\Span\SpanStatus;
use AdilAzhari\LaravelTrace\Span\SpanType;
use AdilAzhari\LaravelTrace\Storage\DatabaseSpanRecorder;
use AdilAzhari\LaravelTrace\Trace\TraceId;
use AdilAzhari\LaravelTrace\Trace\TraceStatus;

it('persists a completed span belonging to an existing trace', function (): void {
    $traceId = TraceId::generate();

    TraceRecord::query()->create([
        'id' => $traceId->value,
        'name' => 'CreateOrder',
        'status' => TraceStatus::Running->value,
        'started_at' => now(),
    ]);

    $recorder = app(DatabaseSpanRecorder::class);

    $span = Span::start($traceId, 'ReserveInventory', SpanType::Action);
    $completed = $span->completeSpan(
        $span->startedAt->modify('+10 milliseconds'),
    );

    $recorder->record($completed);

    $row = SpanRecord::query()->find($span->id->value);

    expect($row)->not->toBeNull()
        ->and($row->trace_id)->toBe($traceId->value)
        ->and($row->status)->toBe(SpanStatus::Completed->value)
        ->and($row->duration_ms)->toBeFloat();
});

it('persists parent_id for a child span', function (): void {
    $traceId = TraceId::generate();

    TraceRecord::query()->create([
        'id' => $traceId->value,
        'name' => 'CreateOrder',
        'status' => TraceStatus::Running->value,
        'started_at' => now(),
    ]);

    $recorder = app(DatabaseSpanRecorder::class);

    $parent = Span::start($traceId, 'process.order', SpanType::Action);
    $recorder->record($parent);

    $child = Span::start($traceId, 'database.query', SpanType::Database, parentId: $parent->id);
    $recorder->record($child->completeSpan($child->startedAt));

    $row = SpanRecord::query()->find($child->id->value);

    expect($row->parent_id)->toBe($parent->id->value);
});

it('creates a placeholder trace row when the span belongs to a trace with no local record', function (): void {
    // Mirrors an inbound propagated trace, or a queue job restoring context
    // from its payload: no Trace object, and therefore no TraceRecord, was
    // ever created locally.
    $traceId = TraceId::generate();

    $recorder = app(DatabaseSpanRecorder::class);

    $span = Span::start($traceId, 'http.request', SpanType::Http);
    $recorder->record($span->completeSpan($span->startedAt));

    $trace = TraceRecord::query()->find($traceId->value);

    expect($trace)->not->toBeNull()
        ->and($trace->status)->toBe(TraceStatus::Running->value);
});

it('does not overwrite an existing trace row when ensuring one exists', function (): void {
    $traceId = TraceId::generate();

    TraceRecord::query()->create([
        'id' => $traceId->value,
        'name' => 'CreateOrder',
        'status' => TraceStatus::Completed->value,
        'started_at' => now(),
        'finished_at' => now(),
    ]);

    $recorder = app(DatabaseSpanRecorder::class);

    $span = Span::start($traceId, 'database.query', SpanType::Database);
    $recorder->record($span->completeSpan($span->startedAt));

    $trace = TraceRecord::query()->find($traceId->value);

    // The span recorder must not reset an already-completed trace back to
    // "running" just because it ensures a parent row exists.
    expect($trace->status)->toBe(TraceStatus::Completed->value);
});

it('is idempotent by span id: a later record replaces the earlier one', function (): void {
    $traceId = TraceId::generate();

    TraceRecord::query()->create([
        'id' => $traceId->value,
        'name' => 'CreateOrder',
        'status' => TraceStatus::Running->value,
        'started_at' => now(),
    ]);

    $recorder = app(DatabaseSpanRecorder::class);

    $span = Span::start($traceId, 'ReserveInventory', SpanType::Action);
    $recorder->record($span);
    $recorder->record($span->completeSpan($span->startedAt));

    expect(SpanRecord::query()->count())->toBe(1)
        ->and(SpanRecord::query()->first()->status)
        ->toBe(SpanStatus::Completed->value);
});
