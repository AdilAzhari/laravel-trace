<?php

declare(strict_types=1);

use AdilAzhari\LaravelTrace\Contracts\Tracer;
use AdilAzhari\LaravelTrace\Http\Middleware\TraceRequest;
use AdilAzhari\LaravelTrace\Span\SpanId;
use AdilAzhari\LaravelTrace\Span\SpanStatus;
use AdilAzhari\LaravelTrace\Span\SpanType;
use AdilAzhari\LaravelTrace\Trace\TraceId;
use AdilAzhari\LaravelTrace\Tracing\InMemorySpanRecorder;
use AdilAzhari\LaravelTrace\Tracing\InMemoryTraceRecorder;
use Illuminate\Support\Facades\Http;

it('continues an upstream trace when the propagation header is present', function (): void {
    $traceId = TraceId::generate();
    $spanId = SpanId::generate();

    $this->postJson('/trace-test', [], [
        TraceRequest::DEFAULT_HEADER => $traceId->value.'-'.$spanId->value,
    ])->assertSuccessful();

    $spans = app(InMemorySpanRecorder::class)->all();
    $traces = app(InMemoryTraceRecorder::class)->all();

    expect($spans)
        ->toHaveCount(1)
        ->and($spans[0]->name)
        ->toBe('http.request')
        ->and($spans[0]->traceId->value)
        ->toBe($traceId->value)
        ->and($spans[0]->parentId?->value)
        ->toBe($spanId->value)
        ->and($spans[0]->attributes)
        ->toMatchArray([
            'http.method' => 'POST',
            'http.status_code' => 200,
        ])
        // A propagated request does not own a root trace.
        ->and($traces)
        ->toBeEmpty()
        ->and(app(Tracer::class)->context())
        ->toBeNull();
});

it('continues an upstream trace with no span when only a trace id is propagated', function (): void {
    $traceId = TraceId::generate();

    $this->postJson('/trace-test', [], [
        TraceRequest::DEFAULT_HEADER => $traceId->value,
    ])->assertSuccessful();

    $spans = app(InMemorySpanRecorder::class)->all();

    expect($spans)
        ->toHaveCount(1)
        ->and($spans[0]->traceId->value)
        ->toBe($traceId->value)
        ->and($spans[0]->parentId)
        ->toBeNull();
});

it('fails the propagated span without recording a local trace when the request fails', function (): void {
    $this->withoutExceptionHandling();

    $traceId = TraceId::generate();
    $spanId = SpanId::generate();

    expect(fn () => $this->postJson('/trace-test-failure', [], [
        TraceRequest::DEFAULT_HEADER => $traceId->value.'-'.$spanId->value,
    ]))->toThrow(RuntimeException::class, 'Something failed.');

    $spans = app(InMemorySpanRecorder::class)->all();

    expect($spans)
        ->toHaveCount(1)
        ->and($spans[0]->status)
        ->toBe(SpanStatus::Failed)
        ->and($spans[0]->traceId->value)
        ->toBe($traceId->value)
        ->and(app(InMemoryTraceRecorder::class)->all())
        ->toBeEmpty()
        ->and(app(Tracer::class)->context())
        ->toBeNull();
});

it('starts a fresh local trace when no propagation header is supplied', function (): void {
    $this->postJson('/trace-test')->assertSuccessful();

    $traces = app(InMemoryTraceRecorder::class)->all();
    $spans = app(InMemorySpanRecorder::class)->all();

    expect($traces)
        ->toHaveCount(1)
        ->and($spans)
        ->toHaveCount(1)
        ->and($spans[0]->traceId->value)
        ->toBe($traces[0]->id->value)
        ->and($spans[0]->parentId)
        ->toBeNull();
});

it('falls back to a fresh trace when the propagation header is malformed', function (): void {
    $this->postJson('/trace-test', [], [
        TraceRequest::DEFAULT_HEADER => 'totally-bogus-value',
    ])->assertSuccessful();

    $traces = app(InMemoryTraceRecorder::class)->all();

    expect($traces)->toHaveCount(1);
});

it('honors a custom propagation header name from config', function (): void {
    config()->set('laravel-trace.http.header', 'X-Custom-Trace');

    $traceId = TraceId::generate();

    $this->postJson('/trace-test', [], [
        'X-Custom-Trace' => $traceId->value,
    ])->assertSuccessful();

    $spans = app(InMemorySpanRecorder::class)->all();

    expect($spans[0]->traceId->value)->toBe($traceId->value)
        ->and(app(InMemoryTraceRecorder::class)->all())->toBeEmpty();
});

it('ignores the propagation header when tracing is disabled', function (): void {
    config()->set('laravel-trace.enabled', false);

    $traceId = TraceId::generate();

    $this->postJson('/trace-test', [], [
        TraceRequest::DEFAULT_HEADER => $traceId->value,
    ])->assertSuccessful();

    expect(app(InMemorySpanRecorder::class)->all())->toBeEmpty();
});

it('attaches the active trace context to outbound requests when enabled', function (): void {
    config()->set('laravel-trace.http.propagate_outbound', true);

    Http::fake();

    $tracer = app(Tracer::class);
    $tracer->start('outbound');
    $scope = $tracer->span('call.api', SpanType::Action);

    Http::get('https://example.test/resource');

    $expected = $tracer->context()?->toHeader();

    Http::assertSent(function ($request) use ($expected): bool {
        return $request->hasHeader(TraceRequest::DEFAULT_HEADER, $expected);
    });

    $scope->close();
});

it('does not attach trace context to outbound requests by default', function (): void {
    Http::fake();

    $tracer = app(Tracer::class);
    $tracer->start('outbound');

    Http::get('https://example.test/resource');

    Http::assertSent(function ($request): bool {
        return ! $request->hasHeader(TraceRequest::DEFAULT_HEADER);
    });
});

it('does not overwrite an explicit propagation header on an outbound request', function (): void {
    config()->set('laravel-trace.http.propagate_outbound', true);

    Http::fake();

    $tracer = app(Tracer::class);
    $tracer->start('outbound');

    Http::withHeaders([
        TraceRequest::DEFAULT_HEADER => 'explicit-value',
    ])->get('https://example.test/resource');

    Http::assertSent(function ($request): bool {
        return $request->hasHeader(TraceRequest::DEFAULT_HEADER, 'explicit-value');
    });
});

it('does not attach trace context to outbound requests without an active trace', function (): void {
    config()->set('laravel-trace.http.propagate_outbound', true);

    Http::fake();

    Http::get('https://example.test/resource');

    Http::assertSent(function ($request): bool {
        return ! $request->hasHeader(TraceRequest::DEFAULT_HEADER);
    });
});
