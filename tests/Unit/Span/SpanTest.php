<?php

declare(strict_types=1);

use AdilAzhari\LaravelTrace\Span\Span;
use AdilAzhari\LaravelTrace\Span\SpanStatus;
use AdilAzhari\LaravelTrace\Span\SpanType;
use AdilAzhari\LaravelTrace\Trace\TraceId;

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

it('completes a span', function (): void {
    $traceId = TraceId::generate();

    $span = Span::start(
        traceId: $traceId,
        name: 'ReserveInventory',
        type: SpanType::Action,
    );

    $finishedAt = new DateTimeImmutable;

    $completed = $span->completeSpan($finishedAt);

    expect($completed->status)
        ->toBe(SpanStatus::Completed)
        ->and($completed->finishedAt)
        ->toBe($finishedAt)
        ->and($span->status)
        ->toBe(SpanStatus::Running);
});

it('fails a span', function (): void {
    $traceId = TraceId::generate();

    $span = Span::start(
        traceId: $traceId,
        name: 'ReserveInventory',
        type: SpanType::Action,
        attributes: [],
    );

    $finishedAt = new DateTimeImmutable;

    $exception = new RuntimeException(
        'Inventory service failed',
    );

    $failed = $span->failSpan($exception, $finishedAt);

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
