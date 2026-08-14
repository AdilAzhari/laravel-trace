<?php

declare(strict_types=1);

use LaravelTrace\LaravelTrace\Context\InMemoryTraceContextStore;
use LaravelTrace\LaravelTrace\Span\Span;
use LaravelTrace\LaravelTrace\Span\SpanStatus;
use LaravelTrace\LaravelTrace\Span\SpanType;
use LaravelTrace\LaravelTrace\Trace\TraceId;
use LaravelTrace\LaravelTrace\Tracing\Tracer;

it('restores the previous context when closed', function () {
    $store = new InMemoryTraceContextStore;
    $tracer = new Tracer($store);

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

it('fails a span and restores the previous context', function () {
    $store = new InMemoryTraceContextStore();
    $tracer = new Tracer($store);

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

it('does not retain an error when completing a span', function () {
    $traceId = TraceId::generate();

    $span = Span::start(
        traceId: $traceId,
        name: 'ReserveInventory',
        type: SpanType::Action,
    );

    $completed = $span->complete(
        new DateTimeImmutable(),
    );

    expect($completed->status)
        ->toBe(SpanStatus::Completed)
        ->and($completed->error)
        ->toBeNull();
});
