<?php

declare(strict_types=1);

use AdilAzhari\LaravelTrace\Http\Middleware\TraceRequest;
use AdilAzhari\LaravelTrace\Models\SpanRecord;
use AdilAzhari\LaravelTrace\Models\TraceRecord;
use AdilAzhari\LaravelTrace\Span\SpanId;
use AdilAzhari\LaravelTrace\Trace\TraceId;
use AdilAzhari\LaravelTrace\Trace\TraceStatus;
use AdilAzhari\LaravelTrace\Tracing\InMemoryTraceRecorder;

it('persists a placeholder running trace row for a propagated request when using the database driver', function (): void {
    config()->set('laravel-trace.storage.driver', 'database');

    $traceId = TraceId::generate();
    $spanId = SpanId::generate();

    $this->postJson('/trace-test', [], [
        TraceRequest::DEFAULT_HEADER => $traceId->value.'-'.$spanId->value,
    ])->assertSuccessful();

    $span = SpanRecord::query()->where('trace_id', $traceId->value)->first();
    $trace = TraceRecord::query()->find($traceId->value);

    expect($span)->not->toBeNull()
        ->and($span->parent_id)->toBe($spanId->value)
        ->and($trace)
        ->not->toBeNull()
        ->and($trace->status)
        // No local Trace object ever owns this trace ID - the upstream
        // service is the one that will complete it - so it stays running
        // here rather than being invented as completed/failed.
        ->toBe(TraceStatus::Running->value);
});

it('still records no local trace for the same propagated request when using the memory driver', function (): void {
    // Regression guard: the FK-driven placeholder-row behaviour is specific
    // to the database driver and must not leak into the memory driver's
    // documented "propagated requests have no local trace" behaviour.
    $traceId = TraceId::generate();
    $spanId = SpanId::generate();

    $this->postJson('/trace-test', [], [
        TraceRequest::DEFAULT_HEADER => $traceId->value.'-'.$spanId->value,
    ])->assertSuccessful();

    expect(app(InMemoryTraceRecorder::class)->all())->toBeEmpty();
});
