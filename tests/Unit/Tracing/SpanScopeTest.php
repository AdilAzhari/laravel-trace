<?php

declare(strict_types=1);

use AdilAzhari\LaravelTrace\Context\InMemoryTraceContextStore;
use AdilAzhari\LaravelTrace\Span\Span;
use AdilAzhari\LaravelTrace\Span\SpanStatus;
use AdilAzhari\LaravelTrace\Span\SpanType;
use AdilAzhari\LaravelTrace\Trace\TraceId;
use AdilAzhari\LaravelTrace\Tracing\InMemorySpanRecorder;
use AdilAzhari\LaravelTrace\Tracing\InMemoryTraceRecorder;
use AdilAzhari\LaravelTrace\Tracing\SpanScope;
use AdilAzhari\LaravelTrace\Tracing\Tracer;

it('restores the previous context when closed', function (): void {
    $store = new InMemoryTraceContextStore;
    $record = new InMemorySpanRecorder;
    $tracer = new Tracer($store, $record, new InMemoryTraceRecorder);

    $tracer->start('CreateOrder');

    $parent = $tracer->span(
        'CreateOrder',
        SpanType::Action,
    );

    $parentContext = $tracer->context();

    $child = $tracer->span(
        'ReserveInventory',
        SpanType::Action,
    );

    expect($tracer->context()?->spanId)
        ->toBe($child->span()->id);

    $child->close();

    expect($tracer->context())
        ->toBe($parentContext);
});

it('fails a span and restores the previous context', function (): void {
    $store = new InMemoryTraceContextStore;
    $record = new InMemorySpanRecorder;
    $tracer = new Tracer($store, $record, new InMemoryTraceRecorder);

    $tracer->start('CreateOrder');

    $parent = $tracer->span(
        'CreateOrder',
        SpanType::Action,
    );

    $parentContext = $tracer->context();

    $child = $tracer->span(
        'ReserveInventory',
        SpanType::Action,
    );

    $exception = new RuntimeException(
        'Inventory service failed',
    );

    $failed = $child->fail($exception);

    expect($failed->status)
        ->toBe(SpanStatus::Failed)
        ->and($failed->error?->type)
        ->toBe(RuntimeException::class)
        ->and($failed->error?->message)
        ->toBe('Inventory service failed')
        ->and($tracer->context())
        ->toBe($parentContext);
});

it('does not retain an error when completing a span', function (): void {
    $traceId = TraceId::generate();

    $span = Span::start(
        traceId: $traceId,
        name: 'ReserveInventory',
        type: SpanType::Action,
    );

    $completed = $span->completeSpan(
        new DateTimeImmutable,
    );

    expect($completed->status)
        ->toBe(SpanStatus::Completed)
        ->and($completed->error)
        ->toBeNull();
});

it('adds attributes to a span before closing', function (): void {
    $contextStore = new InMemoryTraceContextStore;
    $spanRecorder = new InMemorySpanRecorder;
    $traceRecorder = new InMemoryTraceRecorder;

    $tracer = new Tracer(
        contextStore: $contextStore,
        spanRecorder: $spanRecorder,
        traceRecorder: $traceRecorder,
    );

    $tracer->start('test');

    $scope = $tracer->span(
        name: 'http.request',
        type: SpanType::Http,
        attributes: [
            'http.method' => 'GET',
        ],
    );

    $scope->attributes([
        'http.status_code' => 200,
    ]);

    $scope->close();

    $span = $spanRecorder->all()[0];

    expect($span->attributes)
        ->toMatchArray([
            'http.method' => 'GET',
            'http.status_code' => 200,
        ]);
});

it('depends on the span completer contract', function (): void {
    $contextStore = new InMemoryTraceContextStore;
    $spanRecorder = new InMemorySpanRecorder;
    $traceRecorder = new InMemoryTraceRecorder;

    $tracer = new Tracer(
        contextStore: $contextStore,
        spanRecorder: $spanRecorder,
        traceRecorder: $traceRecorder,
    );

    $tracer->start('test');

    $scope = $tracer->span(
        name: 'test.span',
        type: SpanType::Action,
    );

    expect($scope)->toBeInstanceOf(SpanScope::class);

    $scope->close();

    expect($spanRecorder->all())->toHaveCount(1);
});
