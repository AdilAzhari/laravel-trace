<?php

declare(strict_types=1);

use AdilAzhari\LaravelTrace\Context\InMemoryTraceContextStore;
use AdilAzhari\LaravelTrace\Span\SpanStatus;
use AdilAzhari\LaravelTrace\Span\SpanType;
use AdilAzhari\LaravelTrace\Tracing\EventListenerTracer;
use AdilAzhari\LaravelTrace\Tracing\InMemorySpanRecorder;
use AdilAzhari\LaravelTrace\Tracing\InMemoryTraceRecorder;
use AdilAzhari\LaravelTrace\Tracing\Tracer;

it('records a listener span', function (): void {
    $spanRecorder = new InMemorySpanRecorder;

    $tracer = new Tracer(
        new InMemoryTraceContextStore,
        $spanRecorder,
        new InMemoryTraceRecorder,
    );

    $listenerTracer = new EventListenerTracer($tracer);

    $tracer->start('test');

    $listenerTracer->trace(
        listener: fn (): string => 'handled',
        name: 'listener.SendOrderConfirmation',
    );

    $spans = $spanRecorder->all();

    expect($spans)
        ->toHaveCount(1)
        ->and($spans[0]->name)
        ->toBe('listener.SendOrderConfirmation')
        ->and($spans[0]->type)
        ->toBe(SpanType::Listener)
        ->and($spans[0]->status)
        ->toBe(SpanStatus::Completed);
});

it('fails the listener span when the listener throws', function (): void {
    $spanRecorder = new InMemorySpanRecorder;

    $tracer = new Tracer(
        new InMemoryTraceContextStore,
        $spanRecorder,
        new InMemoryTraceRecorder,
    );

    $listenerTracer = new EventListenerTracer($tracer);

    $tracer->start('test');

    expect(fn () => $listenerTracer->trace(
        listener: function (): void {
            throw new RuntimeException('Listener failed.');
        },
        name: 'listener.SendOrderConfirmation',
    ))->toThrow(RuntimeException::class, 'Listener failed.');

    $spans = $spanRecorder->all();

    expect($spans)
        ->toHaveCount(1)
        ->and($spans[0]->status)
        ->toBe(SpanStatus::Failed);
});
