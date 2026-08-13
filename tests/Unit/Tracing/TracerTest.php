<?php

declare(strict_types=1);

use LaravelTrace\LaravelTrace\Span\SpanStatus;
use LaravelTrace\LaravelTrace\Span\SpanType;
use LaravelTrace\LaravelTrace\Trace\TraceStatus;
use LaravelTrace\LaravelTrace\Tracing\Tracer;

it('starts a trace', function () {
    $tracer = new Tracer;

    $trace = $tracer->start('CreateOrder');

    expect($trace->name)
        ->toBe('CreateOrder')
        ->and($trace->status)
        ->toBe(TraceStatus::Running);
});

it('starts a span inside a trace', function () {
    $tracer = new Tracer;

    $trace = $tracer->start('CreateOrder');

    $span = $tracer->startSpan(
        trace: $trace,
        name: 'ReserveInventory',
        type: SpanType::Action,
    );

    expect($span->traceId)
        ->toBe($trace->id)
        ->and($span->name)
        ->toBe('ReserveInventory')
        ->and($span->type)
        ->toBe(SpanType::Action)
        ->and($span->status)
        ->toBe(SpanStatus::Running);
});
