<?php

declare(strict_types=1);

use AdilAzhari\LaravelTrace\Context\InMemoryTraceContextStore;
use AdilAzhari\LaravelTrace\Span\SpanStatus;
use AdilAzhari\LaravelTrace\Span\SpanType;
use AdilAzhari\LaravelTrace\Trace\TraceStatus;
use AdilAzhari\LaravelTrace\Tracing\InMemorySpanRecorder;
use AdilAzhari\LaravelTrace\Tracing\InMemoryTraceRecorder;
use AdilAzhari\LaravelTrace\Tracing\Tracer;

it('starts a trace', function (): void {
    $recorder = new InMemorySpanRecorder;

    $tracer = new Tracer(
        new InMemoryTraceContextStore,
        $recorder,
        new InMemoryTraceRecorder,
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
        new InMemoryTraceRecorder,
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
        new InMemoryTraceRecorder,
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
        new InMemoryTraceRecorder,
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

it('starts a span with attributes', function (): void {
    $tracer = app(Tracer::class);

    $tracer->start('OrderCreated');

    $scope = $tracer->span(
        'database.query',
        SpanType::Database,
        [
            'db.connection' => 'mysql',
            'db.duration_ms' => 4.2,
        ],
    );

    $span = $scope->close();

    expect($span->attributes)
        ->toBe([
            'db.connection' => 'mysql',
            'db.duration_ms' => 4.2,
        ]);
});

it('records a completed trace', function (): void {
    $tracer = app(Tracer::class);

    $trace = $tracer->start('CreateOrder');

    $completed = $tracer->completeTrace($trace);

    expect($completed->status)
        ->toBe(TraceStatus::Completed)
        ->and(app(InMemoryTraceRecorder::class)->all())
        ->toHaveCount(1)
        ->and(app(InMemoryTraceRecorder::class)->all()[0]->id)
        ->toBe($trace->id);
});

it('records a failed trace', function (): void {
    $tracer = app(Tracer::class);

    $trace = $tracer->start('CreateOrder');

    $exception = new RuntimeException('Something failed.');

    $failed = $tracer->failTrace(
        $trace,
        $exception,
    );

    expect($failed->status)
        ->toBe(TraceStatus::Failed)
        ->and(app(InMemoryTraceRecorder::class)->all())
        ->toHaveCount(1)
        ->and(app(InMemoryTraceRecorder::class)->all()[0]->status)
        ->toBe(TraceStatus::Failed);
});
