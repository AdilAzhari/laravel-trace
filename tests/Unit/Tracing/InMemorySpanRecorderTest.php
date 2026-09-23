<?php

declare(strict_types=1);

use AdilAzhari\LaravelTrace\Span\Span;
use AdilAzhari\LaravelTrace\Span\SpanStatus;
use AdilAzhari\LaravelTrace\Span\SpanType;
use AdilAzhari\LaravelTrace\Trace\TraceId;
use AdilAzhari\LaravelTrace\Tracing\InMemorySpanRecorder;

it('records spans', function (): void {
    $recorder = new InMemorySpanRecorder;

    $span = Span::start(
        traceId: TraceId::generate(),
        name: 'ReserveInventory',
        type: SpanType::Action,
    );

    $recorder->record($span);

    expect($recorder->all())
        ->toHaveCount(1)
        ->and($recorder->all()[0])
        ->toBe($span);
});

it('records multiple spans', function (): void {
    $recorder = new InMemorySpanRecorder;

    $traceId = TraceId::generate();

    $first = Span::start(
        traceId: $traceId,
        name: 'ReserveInventory',
        type: SpanType::Action,
    );

    $second = Span::start(
        traceId: $traceId,
        name: 'SendConfirmation',
        type: SpanType::Action,
    );

    $recorder->record($first);
    $recorder->record($second);

    expect($recorder->all())
        ->toHaveCount(2)
        ->and($recorder->all())
        ->toEqual([$first, $second]);
});

it('is idempotent by span id: a later record replaces the earlier one', function (): void {
    $recorder = new InMemorySpanRecorder;

    $span = Span::start(
        traceId: TraceId::generate(),
        name: 'ReserveInventory',
        type: SpanType::Action,
    );

    $recorder->record($span);
    $recorder->record($span->completeSpan(new DateTimeImmutable));

    expect($recorder->all())
        ->toHaveCount(1)
        ->and($recorder->all()[0]->status)
        ->toBe(SpanStatus::Completed);
});
