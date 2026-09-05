<?php

declare(strict_types=1);

use AdilAzhari\LaravelTrace\Contracts\SpanRecorder;
use AdilAzhari\LaravelTrace\Contracts\TraceRecorder;
use AdilAzhari\LaravelTrace\Models\TraceRecord;
use AdilAzhari\LaravelTrace\Span\Span;
use AdilAzhari\LaravelTrace\Span\SpanType;
use AdilAzhari\LaravelTrace\Trace\Trace;
use AdilAzhari\LaravelTrace\Trace\TraceId;
use AdilAzhari\LaravelTrace\Tracing\InMemorySpanRecorder;
use AdilAzhari\LaravelTrace\Tracing\InMemoryTraceRecorder;

// `laravel-trace.storage.driver` is read live on every record() call (see
// StorageDrivenTraceRecorder / StorageDrivenSpanRecorder) rather than once
// when the SpanRecorder/TraceRecorder contract is first resolved, because
// that first resolution happens too early - during container bootstrap,
// via the 'events' -> TracingEventDispatcher -> EventListenerTracer ->
// Tracer dependency chain - for a one-shot choice to observe a config
// change made in a test body. These assertions are therefore behavioural
// (where did the record end up?) rather than about the resolved instance's
// type, which is always the same switching wrapper either way.

it('routes recorded traces and spans to the memory driver by default', function (): void {
    $trace = Trace::start('CreateOrder');
    app(TraceRecorder::class)->record($trace);

    $span = Span::start($trace->id, 'ReserveInventory', SpanType::Action);
    app(SpanRecorder::class)->record($span);

    expect(app(InMemoryTraceRecorder::class)->all())->not->toBeEmpty()
        ->and(app(InMemorySpanRecorder::class)->all())->not->toBeEmpty();
});

it('routes recorded traces and spans to the database driver when configured', function (): void {
    config()->set('laravel-trace.storage.driver', 'database');

    $trace = Trace::start('CreateOrder');
    app(TraceRecorder::class)->record($trace);

    $span = Span::start($trace->id, 'ReserveInventory', SpanType::Action);
    app(SpanRecorder::class)->record($span);

    expect(TraceRecord::query()->find($trace->id->value))->not->toBeNull()
        ->and(app(InMemoryTraceRecorder::class)->all())->toBeEmpty()
        ->and(app(InMemorySpanRecorder::class)->all())->toBeEmpty();
});

it('throws for an unknown storage driver when a trace is recorded', function (): void {
    config()->set('laravel-trace.storage.driver', 'redis');

    expect(fn () => app(TraceRecorder::class)->record(Trace::start('CreateOrder')))
        ->toThrow(InvalidArgumentException::class);
});

it('throws for an unknown storage driver when a span is recorded', function (): void {
    config()->set('laravel-trace.storage.driver', 'redis');

    expect(fn () => app(SpanRecorder::class)->record(
        Span::start(TraceId::generate(), 'ReserveInventory', SpanType::Action),
    ))->toThrow(InvalidArgumentException::class);
});
