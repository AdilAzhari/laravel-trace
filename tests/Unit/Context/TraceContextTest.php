<?php

declare(strict_types=1);

use LaravelTrace\LaravelTrace\Context\TraceContext;
use LaravelTrace\LaravelTrace\Span\SpanId;
use LaravelTrace\LaravelTrace\Trace\TraceId;

it('creates a context with a trace', function () {
    $traceId = TraceId::generate();

    $context = new TraceContext(
        traceId: $traceId,
    );

    expect($context->traceId)
        ->toBe($traceId)
        ->and($context->spanId)
        ->toBeNull();
});

it('can create a context for a span', function () {
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

it('does not mutate the original context', function () {
    $traceId = TraceId::generate();
    $spanId = SpanId::generate();

    $context = new TraceContext($traceId);
    $child = $context->withSpan($spanId);

    expect($context->spanId)
        ->toBeNull()
        ->and($child->spanId)
        ->toBe($spanId);
});
