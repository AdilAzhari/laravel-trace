<?php

declare(strict_types=1);

use LaravelTrace\LaravelTrace\Span\Span;
use LaravelTrace\LaravelTrace\Span\SpanType;
use LaravelTrace\LaravelTrace\Trace\TraceId;
use LaravelTrace\LaravelTrace\Tracing\InMemorySpanRecorder;

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
