<?php

declare(strict_types=1);

use LaravelTrace\LaravelTrace\Span\Span;
use LaravelTrace\LaravelTrace\Span\SpanStatus;
use LaravelTrace\LaravelTrace\Span\SpanType;
use LaravelTrace\LaravelTrace\Trace\TraceId;

it('starts a span', function () {
    $traceId = TraceId::generate();

    $span = Span::start(
        traceId: $traceId,
        name: 'CreateOrder',
        type: SpanType::Action,
    );

    expect($span->traceId)
        ->toBe($traceId)
        ->and($span->name)
        ->toBe('CreateOrder')
        ->and($span->type)
        ->toBe(SpanType::Action)
        ->and($span->status)
        ->toBe(SpanStatus::Running)
        ->and($span->parentId)
        ->toBeNull();
});

it('can have a parent span', function () {
    $traceId = TraceId::generate();

    $parent = Span::start(
        traceId: $traceId,
        name: 'OrderCreated',
        type: SpanType::Event,
    );

    $child = Span::start(
        traceId: $traceId,
        name: 'SendConfirmation',
        type: SpanType::Job,
        parentId: $parent->id,
    );

    expect($child->traceId)
        ->toBe($traceId)
        ->and($child->parentId)
        ->toBe($parent->id);
});
