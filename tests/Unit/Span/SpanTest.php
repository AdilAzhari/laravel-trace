<?php

declare(strict_types=1);

use LaravelTrace\LaravelTrace\Span\Span;
use LaravelTrace\LaravelTrace\Span\SpanStatus;
use LaravelTrace\LaravelTrace\Span\SpanType;
use LaravelTrace\LaravelTrace\Trace\TraceId;

it('starts a span', function (): void {
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

it('can have a parent span', function (): void {
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

it('completes a span', function () {
    $traceId = TraceId::generate();

    $span = Span::start(
        traceId: $traceId,
        name: 'ReserveInventory',
        type: SpanType::Action,
    );

    $finishedAt = new DateTimeImmutable;

    $completed = $span->complete($finishedAt);

    expect($completed->status)
        ->toBe(SpanStatus::Completed)
        ->and($completed->finishedAt)
        ->toBe($finishedAt)
        ->and($span->status)
        ->toBe(SpanStatus::Running);
});

it('fails a span', function () {
    $traceId = TraceId::generate();

    $span = Span::start(
        traceId: $traceId,
        name: 'ReserveInventory',
        type: SpanType::Action,
    );

    $finishedAt = new DateTimeImmutable;

    $exception = new RuntimeException(
        'Inventory service failed',
    );

    $failed = $span->fail($exception,$finishedAt);

    expect($failed->status)
        ->toBe(SpanStatus::Failed)
        ->and($failed->finishedAt)
        ->toBe($finishedAt)
        ->and($failed->error)
        ->not->toBeNull()
        ->and($failed->error?->type)
        ->toBe(RuntimeException::class)
        ->and($failed->error?->message)
        ->toBe('Inventory service failed');
});
