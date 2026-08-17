<?php

declare(strict_types=1);

use LaravelTrace\LaravelTrace\Context\InMemoryTraceContextStore;
use LaravelTrace\LaravelTrace\Context\TraceContext;
use LaravelTrace\LaravelTrace\Span\SpanId;
use LaravelTrace\LaravelTrace\Trace\TraceId;
use LaravelTrace\LaravelTrace\Tracing\InMemorySpanRecorder;
use LaravelTrace\LaravelTrace\Tracing\InMemoryTraceRecorder;
use LaravelTrace\LaravelTrace\Tracing\Tracer;

it('creates a context with a trace', function (): void {
    $traceId = TraceId::generate();

    $context = new TraceContext(
        traceId: $traceId,
    );

    expect($context->traceId)
        ->toBe($traceId)
        ->and($context->spanId)
        ->toBeNull();
});

it('can create a context for a span', function (): void {
    $traceId = TraceId::generate();
    $spanId = SpanId::generate();

    $context = new TraceContext(
        traceId: $traceId,
    );

    $child = $context->withSpan($spanId);

    expect($child->traceId)
        ->toBe($traceId)
        ->and($child->spanId)
        ->toBe($spanId);
});

it('does not mutate the original context', function (): void {
    $traceId = TraceId::generate();
    $spanId = SpanId::generate();

    $context = new TraceContext($traceId);
    $child = $context->withSpan($spanId);

    expect($context->spanId)
        ->toBeNull()
        ->and($child->spanId)
        ->toBe($spanId);
});

it('sets the trace context when starting a trace', function (): void {
    $store = new InMemoryTraceContextStore;
    $record = new InMemorySpanRecorder;
    $tracer = new Tracer($store, $record, new InMemoryTraceRecorder);

    $trace = $tracer->start('CreateOrder');

    expect($tracer->context())
        ->not->toBeNull()
        ->and($tracer->context()?->traceId)
        ->toBe($trace->id)
        ->and($tracer->context()?->spanId)
        ->toBeNull();
});
