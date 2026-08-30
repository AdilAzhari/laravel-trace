<?php

declare(strict_types=1);

use AdilAzhari\LaravelTrace\Contracts\Tracer;
use AdilAzhari\LaravelTrace\Span\SpanType;
use AdilAzhari\LaravelTrace\Trace\TraceStatus;
use AdilAzhari\LaravelTrace\Tracing\InMemorySpanRecorder;
use AdilAzhari\LaravelTrace\Tracing\InMemoryTraceRecorder;

it('starts a trace for an http request', function (): void {
    $tracer = app(Tracer::class);

    $response = $this->postJson('/trace-test');

    $response->assertSuccessful();

    expect($tracer->context())
        ->toBeNull();
});

it('clears tracing context when the request fails', function (): void {
    $tracer = app(Tracer::class);

    $this->postJson('/trace-test-failure')
        ->assertServerError();

    expect($tracer->context())
        ->toBeNull();
});

it('starts and completes a trace for an http request', function (): void {
    $tracer = app(Tracer::class);

    $response = $this->postJson('/trace-test');

    $response->assertSuccessful();

    $traces = app(InMemoryTraceRecorder::class)->all();

    $spans = app(InMemorySpanRecorder::class)->all();

    expect($spans)
        ->toHaveCount(1)
        ->and($spans[0]->name)
        ->toBe('http.request')
        ->and($spans[0]->type)
        ->toBe(SpanType::Http)
        ->and($spans[0]->attributes)
        ->toMatchArray([
            'http.status_code' => 200,
        ])
        ->and($traces)
        ->toHaveCount(1)
        ->and($traces[0]->name)
        ->toBe('http.request')
        ->and($traces[0]->status)
        ->toBe(TraceStatus::Completed)
        ->and($tracer->context())
        ->toBeNull()
        ->and($spans[0]->traceId)
        ->toBe($traces[0]->id)
        ->and($spans[0]->parentId)
        ->toBeNull();
});

it('fails the trace when the request fails', function (): void {
    $tracer = app(Tracer::class);

    $this->withoutExceptionHandling();

    expect(fn () => $this->postJson('/trace-test-failure'))
        ->toThrow(RuntimeException::class, 'Something failed.');

    $traces = app(InMemoryTraceRecorder::class)->all();

    expect($traces)
        ->toHaveCount(1)
        ->and($traces[0]->status)
        ->toBe(TraceStatus::Failed)
        ->and($tracer->context())
        ->toBeNull();
});
