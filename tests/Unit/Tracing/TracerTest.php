<?php

declare(strict_types=1);

use LaravelTrace\LaravelTrace\Context\InMemoryTraceContextStore;
use LaravelTrace\LaravelTrace\Span\SpanStatus;
use LaravelTrace\LaravelTrace\Span\SpanType;
use LaravelTrace\LaravelTrace\Trace\TraceStatus;
use LaravelTrace\LaravelTrace\Tracing\InMemorySpanRecorder;
use LaravelTrace\LaravelTrace\Tracing\Tracer;

it('starts a trace', function (): void {
    $recorder = new InMemorySpanRecorder;

    $tracer = new Tracer(
        new InMemoryTraceContextStore,
        $recorder,
    );

    $trace = $tracer->start('CreateOrder');

    expect($trace->name)
        ->toBe('CreateOrder')
        ->and($trace->status)
        ->toBe(TraceStatus::Running);
});

it('starts a span inside a trace', function (): void {
    $recorder = new InMemorySpanRecorder;
    $tracer = new Tracer(
        new InMemoryTraceContextStore,
        $recorder,
    );

    $trace = $tracer->start('CreateOrder');

    $scope = $tracer->span(
        'ReserveInventory',
        SpanType::Action,
    );

    $span = $scope->span();

    expect($span->traceId)
        ->toBe($trace->id)
        ->and($span->name)
        ->toBe('ReserveInventory')
        ->and($span->type)
        ->toBe(SpanType::Action)
        ->and($span->status)
        ->toBe(SpanStatus::Running);
});

it('records a completed span', function (): void {
    $recorder = new InMemorySpanRecorder;

    $tracer = new Tracer(
        new InMemoryTraceContextStore,
        $recorder,
    );

    $tracer->start('CreateOrder');

    $scope = $tracer->span(
        'ReserveInventory',
        SpanType::Action,
    );

    $completed = $scope->close();

    expect($recorder->all())
        ->toHaveCount(1)
        ->and($recorder->all()[0])
        ->toBe($completed);
});

it('records a failed span', function (): void {
    $recorder = new InMemorySpanRecorder;

    $tracer = new Tracer(
        new InMemoryTraceContextStore,
        $recorder,
    );

    $tracer->start('CreateOrder');

    $scope = $tracer->span(
        'ChargePayment',
        SpanType::Action,
    );

    $exception = new RuntimeException(
        'Payment provider failed',
    );

    $failed = $scope->fail($exception);

    expect($recorder->all())
        ->toHaveCount(1)
        ->and($recorder->all()[0])
        ->toBe($failed)
        ->and($failed->status)
        ->toBe(SpanStatus::Failed);
});
