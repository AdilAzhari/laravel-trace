<?php

declare(strict_types=1);

use LaravelTrace\LaravelTrace\Contracts\Tracer;
use LaravelTrace\LaravelTrace\Span\SpanStatus;
use LaravelTrace\LaravelTrace\Span\SpanType;
use LaravelTrace\LaravelTrace\Trace\TraceStatus;
use LaravelTrace\LaravelTrace\Tracing\InMemoryTraceRecorder;

it('starts a trace and establishes trace context', function (): void {
    $tracer = app(Tracer::class);

    $trace = $tracer->start('OrderProcessing');

    expect($trace->status)
        ->toBe(TraceStatus::Running)
        ->and($tracer->context())
        ->not->toBeNull()
        ->and($tracer->context()->traceId->value)
        ->toBe($trace->id->value)
        ->and($tracer->context()->spanId)
        ->toBeNull();
});

it('creates a root span under the active trace', function (): void {
    $tracer = app(Tracer::class);

    $trace = $tracer->start('OrderProcessing');

    $scope = $tracer->span(
        name: 'process.order',
        type: SpanType::Action,
    );

    expect($scope->span()->traceId->value)
        ->toBe($trace->id->value)
        ->and($scope->span()->parentId)
        ->toBeNull()
        ->and($scope->span()->status)
        ->toBe(SpanStatus::Running);

    $scope->close();
});

it('creates nested spans with the correct parent', function (): void {
    $tracer = app(Tracer::class);

    $tracer->start('OrderProcessing');

    $parent = $tracer->span(
        name: 'process.order',
        type: SpanType::Action,
    );

    $child = $tracer->span(
        name: 'database.query',
        type: SpanType::Database,
    );

    expect($child->span()->parentId?->value)
        ->toBe($parent->span()->id->value);

    $child->close();
    $parent->close();
});

it('restores the parent context when a child span closes', function (): void {
    $tracer = app(Tracer::class);

    $tracer->start('OrderProcessing');

    $parent = $tracer->span(
        name: 'process.order',
        type: SpanType::Action,
    );

    $child = $tracer->span(
        name: 'database.query',
        type: SpanType::Database,
    );

    $child->close();

    expect($tracer->context()?->spanId?->value)
        ->toBe($parent->span()->id->value);

    $parent->close();

    expect($tracer->context()?->spanId)
        ->toBeNull();
});

it('restores the parent context when a child span fails', function (): void {
    $tracer = app(Tracer::class);

    $tracer->start('OrderProcessing');

    $parent = $tracer->span(
        name: 'process.order',
        type: SpanType::Action,
    );

    $child = $tracer->span(
        name: 'database.query',
        type: SpanType::Database,
    );

    $child->fail(new RuntimeException('Database failed.'));

    expect($tracer->context()?->spanId?->value)
        ->toBe($parent->span()->id->value);

    $parent->close();

    expect($tracer->context()?->spanId)
        ->toBeNull();
});

it('records a completed trace', function (): void {
    $tracer = app(Tracer::class);

    $trace = $tracer->start('OrderProcessing');

    $completed = $tracer->completeTrace($trace);

    expect($completed->status)
        ->toBe(TraceStatus::Completed)
        ->and($completed->finishedAt)
        ->not->toBeNull()
        ->and(app(InMemoryTraceRecorder::class)->all())
        ->toHaveCount(1);
});

it('records a failed trace', function (): void {
    $tracer = app(Tracer::class);

    $trace = $tracer->start('OrderProcessing');

    $failed = $tracer->failTrace(
        $trace,
        new RuntimeException('Order processing failed.'),
    );

    expect($failed->status)
        ->toBe(TraceStatus::Failed)
        ->and($failed->error?->type)
        ->toBe(RuntimeException::class)
        ->and($failed->finishedAt)
        ->not->toBeNull()
        ->and(app(InMemoryTraceRecorder::class)->all())
        ->toHaveCount(1);
});
