<?php

declare(strict_types=1);

use Illuminate\Events\CallQueuedListener;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use LaravelTrace\LaravelTrace\Contracts\Tracer;
use LaravelTrace\LaravelTrace\Span\SpanStatus;
use LaravelTrace\LaravelTrace\Span\SpanType;
use LaravelTrace\LaravelTrace\Tests\Fixtures\Events\OrderCreated;
use LaravelTrace\LaravelTrace\Tests\Fixtures\Listeners\QueuedOrderNotifier;
use LaravelTrace\LaravelTrace\Tests\Fixtures\Listeners\SendOrderConfirmation;
use LaravelTrace\LaravelTrace\Tracing\InMemorySpanRecorder;

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
