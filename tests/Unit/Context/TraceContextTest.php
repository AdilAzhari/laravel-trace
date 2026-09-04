<?php

declare(strict_types=1);

use AdilAzhari\LaravelTrace\Context\InMemoryTraceContextStore;
use AdilAzhari\LaravelTrace\Context\TraceContext;
use AdilAzhari\LaravelTrace\Span\SpanId;
use AdilAzhari\LaravelTrace\Trace\TraceId;
use AdilAzhari\LaravelTrace\Tracing\InMemorySpanRecorder;
use AdilAzhari\LaravelTrace\Tracing\InMemoryTraceRecorder;
use AdilAzhari\LaravelTrace\Tracing\Tracer;

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

it('serializes a trace-only context to a header value', function (): void {
    $traceId = TraceId::generate();

    $context = new TraceContext(traceId: $traceId);

    expect($context->toHeader())
        ->toBe($traceId->value);
});

it('serializes a context with a span to a header value', function (): void {
    $traceId = TraceId::generate();
    $spanId = SpanId::generate();

    $context = new TraceContext(traceId: $traceId, spanId: $spanId);

    expect($context->toHeader())
        ->toBe($traceId->value.'-'.$spanId->value);
});

it('round-trips a context through the header representation', function (): void {
    $context = (new TraceContext(traceId: TraceId::generate()))
        ->withSpan(SpanId::generate());

    $restored = TraceContext::fromHeader($context->toHeader());

    expect($restored)
        ->not->toBeNull()
        ->and($restored->traceId->value)
        ->toBe($context->traceId->value)
        ->and($restored->spanId?->value)
        ->toBe($context->spanId?->value);
});

it('parses a trace-only header value', function (): void {
    $traceId = TraceId::generate();

    $restored = TraceContext::fromHeader(' '.$traceId->value.' ');

    expect($restored)
        ->not->toBeNull()
        ->and($restored->traceId->value)
        ->toBe($traceId->value)
        ->and($restored->spanId)
        ->toBeNull();
});

it('rejects malformed header values', function (string $header): void {
    expect(TraceContext::fromHeader($header))->toBeNull();
})->with([
    'empty' => '',
    'whitespace' => '   ',
    'not an id' => 'not-a-valid-context',
    'single garbage segment' => 'abc123',
    'too many segments' => TraceId::generate()->value.'-'.SpanId::generate()->value.'-'.SpanId::generate()->value,
    'invalid span segment' => TraceId::generate()->value.'-nope',
]);
