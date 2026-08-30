<?php

declare(strict_types=1);

use AdilAzhari\LaravelTrace\Contracts\Tracer;
use AdilAzhari\LaravelTrace\Span\Span;
use AdilAzhari\LaravelTrace\Span\SpanStatus;
use AdilAzhari\LaravelTrace\Span\SpanType;
use AdilAzhari\LaravelTrace\Tests\Fixtures\Events\OrderCreated;
use AdilAzhari\LaravelTrace\Tests\Fixtures\Events\OrderShipped;
use AdilAzhari\LaravelTrace\Tests\Fixtures\Listeners\QueuedOrderNotifier;
use AdilAzhari\LaravelTrace\Tests\Fixtures\Listeners\SendOrderConfirmation;
use AdilAzhari\LaravelTrace\Tracing\InMemorySpanRecorder;
use Illuminate\Events\CallQueuedListener;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;

it('records a span when an event listener executes', function (): void {
    Event::listen(
        OrderCreated::class,
        SendOrderConfirmation::class,
    );

    $tracer = app(Tracer::class);

    $tracer->start('test');

    Event::dispatch(
        new OrderCreated(orderId: 123),
    );

    $spans = app(InMemorySpanRecorder::class)->all();

    expect($spans)
        ->toHaveCount(1)
        ->and($spans[0]->type)
        ->toBe(SpanType::Listener)
        ->and($spans[0]->status)
        ->toBe(SpanStatus::Completed);
});

it('fails the listener span when a listener throws', function (): void {
    Event::listen(
        OrderCreated::class,
        function (OrderCreated $event): void {
            throw new RuntimeException('Listener failed.');
        },
    );

    $tracer = app(Tracer::class);

    $tracer->start('test');

    expect(fn () => Event::dispatch(new OrderCreated(orderId: 123)))
        ->toThrow(RuntimeException::class, 'Listener failed.');

    $spans = app(InMemorySpanRecorder::class)->all();

    expect($spans)
        ->toHaveCount(1)
        ->and($spans[0]->type)
        ->toBe(SpanType::Listener)
        ->and($spans[0]->status)
        ->toBe(SpanStatus::Failed);
});

it('does not wrap wildcard listeners in a listener span', function (): void {
    $receivedEvent = null;

    Event::listen(
        '*',
        function (string $eventName, array $payload) use (&$receivedEvent): void {
            $receivedEvent = $eventName;
        },
    );

    $tracer = app(Tracer::class);

    $tracer->start('test');

    Event::dispatch(
        new OrderCreated(orderId: 123),
    );

    expect($receivedEvent)
        ->toBe(OrderCreated::class)
        ->and(app(InMemorySpanRecorder::class)->all())
        ->toBeEmpty();
});

it('executes listeners normally when there is no active trace', function (): void {
    $executed = false;

    Event::listen(
        OrderCreated::class,
        function (OrderCreated $event) use (&$executed): void {
            $executed = true;
        },
    );

    Event::dispatch(
        new OrderCreated(orderId: 123),
    );

    expect($executed)
        ->toBeTrue()
        ->and(app(InMemorySpanRecorder::class)->all())
        ->toBeEmpty();
});

it('does not wrap queued listeners in a listener span', function (): void {
    Queue::fake();

    Event::listen(
        OrderCreated::class,
        QueuedOrderNotifier::class,
    );

    $tracer = app(Tracer::class);

    $tracer->start('test');

    Event::dispatch(
        new OrderCreated(orderId: 123),
    );

    Queue::assertPushed(CallQueuedListener::class);

    expect(app(InMemorySpanRecorder::class)->all())
        ->toBeEmpty();
});

it('nests listener spans and restores each parent context as listeners finish', function (): void {
    $tracer = app(Tracer::class);

    $contextInsideOuter = null;
    $contextInsideInner = null;
    $contextInOuterAfterInner = null;

    Event::listen(
        OrderShipped::class,
        function () use ($tracer, &$contextInsideInner): void {
            $contextInsideInner = $tracer->context();
        },
    );

    Event::listen(
        OrderCreated::class,
        function () use ($tracer, &$contextInsideOuter, &$contextInOuterAfterInner): void {
            $contextInsideOuter = $tracer->context();

            Event::dispatch(new OrderShipped(orderId: 1));

            $contextInOuterAfterInner = $tracer->context();
        },
    );

    $trace = $tracer->start('test');

    Event::dispatch(new OrderCreated(orderId: 1));

    $spans = app(InMemorySpanRecorder::class)->all();

    $outerSpan = collect($spans)
        ->first(fn (Span $span): bool => $span->parentId === null);

    $innerSpan = collect($spans)
        ->first(fn (Span $span): bool => $span->parentId !== null);

    // Trace -> outer listener span -> inner listener span
    expect($spans)
        ->toHaveCount(2)
        ->and($outerSpan->type)
        ->toBe(SpanType::Listener)
        ->and($innerSpan->type)
        ->toBe(SpanType::Listener)
        ->and($outerSpan->traceId->value)
        ->toBe($trace->id->value)
        ->and($innerSpan->traceId->value)
        ->toBe($trace->id->value)
        ->and($innerSpan->parentId->value)
        ->toBe($outerSpan->id->value)
        ->and($outerSpan->status)
        ->toBe(SpanStatus::Completed)
        ->and($innerSpan->status)
        ->toBe(SpanStatus::Completed);

    // The live context each listener saw while running.
    expect($contextInsideOuter?->spanId?->value)
        ->toBe($outerSpan->id->value)
        ->and($contextInsideInner?->spanId?->value)
        ->toBe($innerSpan->id->value);

    // When the inner listener finished, the outer listener's context was restored.
    expect($contextInOuterAfterInner?->spanId?->value)
        ->toBe($outerSpan->id->value);

    // When the outer listener finished, the original trace context was restored.
    expect($tracer->context()?->traceId->value)
        ->toBe($trace->id->value)
        ->and($tracer->context()?->spanId)
        ->toBeNull();
});

it('fails the inner listener span, propagates, and restores the outer listener context', function (): void {
    $tracer = app(Tracer::class);

    $contextInOuterWhenInnerThrew = null;

    Event::listen(
        OrderShipped::class,
        function (): void {
            throw new RuntimeException('Inner listener failed.');
        },
    );

    Event::listen(
        OrderCreated::class,
        function () use ($tracer, &$contextInOuterWhenInnerThrew): void {
            try {
                Event::dispatch(new OrderShipped(orderId: 1));
            } catch (RuntimeException $exception) {
                $contextInOuterWhenInnerThrew = $tracer->context();

                throw $exception;
            }
        },
    );

    $trace = $tracer->start('test');

    expect(fn () => Event::dispatch(new OrderCreated(orderId: 1)))
        ->toThrow(RuntimeException::class, 'Inner listener failed.');

    $spans = app(InMemorySpanRecorder::class)->all();

    $outerSpan = collect($spans)
        ->first(fn (Span $span): bool => $span->parentId === null);

    $innerSpan = collect($spans)
        ->first(fn (Span $span): bool => $span->parentId !== null);

    expect($spans)
        ->toHaveCount(2)
        ->and($innerSpan->parentId->value)
        ->toBe($outerSpan->id->value)
        ->and($innerSpan->status)
        ->toBe(SpanStatus::Failed)
        ->and($innerSpan->error?->type)
        ->toBe(RuntimeException::class)
        ->and($outerSpan->status)
        ->toBe(SpanStatus::Failed)
        ->and($outerSpan->error?->type)
        ->toBe(RuntimeException::class);

    // The inner span's failure restored the outer listener's context before the
    // exception unwound any further.
    expect($contextInOuterWhenInnerThrew?->spanId?->value)
        ->toBe($outerSpan->id->value);

    // The outer span's failure restored the original trace context: nothing leaked.
    expect($tracer->context()?->traceId->value)
        ->toBe($trace->id->value)
        ->and($tracer->context()?->spanId)
        ->toBeNull();
});
