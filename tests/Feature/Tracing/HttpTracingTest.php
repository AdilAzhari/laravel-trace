<?php

declare(strict_types=1);

use AdilAzhari\LaravelTrace\Contracts\Tracer;
use AdilAzhari\LaravelTrace\Http\Middleware\TraceRequest;
use AdilAzhari\LaravelTrace\Span\SpanStatus;
use AdilAzhari\LaravelTrace\Span\SpanType;
use AdilAzhari\LaravelTrace\Trace\TraceStatus;
use AdilAzhari\LaravelTrace\Tracing\InMemorySpanRecorder;
use AdilAzhari\LaravelTrace\Tracing\InMemoryTraceRecorder;
use Illuminate\Support\Facades\Route;
use Orchestra\Testbench\Concerns\WithWorkbench;

// The /trace-test* routes are Workbench demo routes (workbench/routes/web.php),
// loaded through Testbench's Workbench route discovery - never by the package.
uses(WithWorkbench::class);

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
        ->and($traces[0]->error)
        ->toBeNull()
        ->and($spans[0]->status)
        ->toBe(SpanStatus::Completed)
        ->and($spans[0]->error)
        ->toBeNull()
        ->and($tracer->context())
        ->toBeNull()
        ->and($spans[0]->traceId)
        ->toBe($traces[0]->id)
        ->and($spans[0]->parentId)
        ->toBeNull();
});

it('completes the trace and span for a redirect response', function (): void {
    Route::middleware(TraceRequest::class)
        ->get('/trace-test-redirect', fn () => redirect('/elsewhere'));

    $this->get('/trace-test-redirect')
        ->assertRedirect('/elsewhere');

    $traces = app(InMemoryTraceRecorder::class)->all();
    $spans = app(InMemorySpanRecorder::class)->all();

    expect($traces)
        ->toHaveCount(1)
        ->and($traces[0]->status)
        ->toBe(TraceStatus::Completed)
        ->and($traces[0]->error)
        ->toBeNull()
        ->and($spans)
        ->toHaveCount(1)
        ->and($spans[0]->status)
        ->toBe(SpanStatus::Completed)
        ->and($spans[0]->error)
        ->toBeNull()
        ->and($spans[0]->attributes)
        ->toMatchArray(['http.status_code' => 302])
        ->and(app(Tracer::class)->context())
        ->toBeNull();
});

it('fails the span and trace and rethrows when the exception escapes the middleware', function (): void {
    $tracer = app(Tracer::class);

    $this->withoutExceptionHandling();

    expect(fn () => $this->postJson('/trace-test-failure'))
        ->toThrow(RuntimeException::class, 'Something failed.');

    $traces = app(InMemoryTraceRecorder::class)->all();
    $spans = app(InMemorySpanRecorder::class)->all();

    expect($traces)
        ->toHaveCount(1)
        ->and($traces[0]->status)
        ->toBe(TraceStatus::Failed)
        ->and($traces[0]->error?->type)
        ->toBe(RuntimeException::class)
        ->and($traces[0]->error?->message)
        ->toBe('Something failed.')
        ->and($spans)
        ->toHaveCount(1)
        ->and($spans[0]->status)
        ->toBe(SpanStatus::Failed)
        ->and($spans[0]->error?->type)
        ->toBe(RuntimeException::class)
        ->and($spans[0]->error?->message)
        ->toBe('Something failed.')
        // No response was produced, so no status code is recorded.
        ->and($spans[0]->attributes)
        ->not->toHaveKey('http.status_code')
        ->and($tracer->context())
        ->toBeNull();
});

// Regression for H1: with Laravel's exception handler active (the default,
// and every real application), the routing pipeline renders the exception
// into a 500 response before TraceRequest sees it, so the middleware's
// catch block never runs. Only inspecting the rendered response's
// exception lets the trace fail.
it('fails the span and trace when laravel renders the exception as a server error', function (): void {
    $this->postJson('/trace-test-failure')
        ->assertStatus(500);

    $traces = app(InMemoryTraceRecorder::class)->all();
    $spans = app(InMemorySpanRecorder::class)->all();

    $trace = end($traces);

    expect($trace->name)
        ->toBe('http.request')
        ->and($trace->status)
        ->toBe(TraceStatus::Failed)
        ->and($trace->finishedAt)
        ->not->toBeNull()
        ->and($trace->error?->type)
        ->toBe(RuntimeException::class)
        ->and($trace->error?->message)
        ->toBe('Something failed.')
        ->and($spans)
        ->toHaveCount(1)
        ->and($spans[0]->traceId)
        ->toBe($trace->id)
        ->and($spans[0]->status)
        ->toBe(SpanStatus::Failed)
        ->and($spans[0]->finishedAt)
        ->not->toBeNull()
        ->and($spans[0]->error?->type)
        ->toBe(RuntimeException::class)
        ->and($spans[0]->error?->message)
        ->toBe('Something failed.')
        ->and($spans[0]->attributes)
        ->toMatchArray(['http.status_code' => 500])
        ->and(app(Tracer::class)->context())
        ->toBeNull();
});

// Documents current behavior, deliberately unchanged by H1: a 500 that
// carries no exception gives the middleware nothing to fail the trace
// with, so it completes and only the status code records the error.
it('completes the trace for a server error response that carries no exception', function (): void {
    Route::middleware(TraceRequest::class)
        ->get('/trace-test-plain-500', fn () => response('', 500));

    $this->get('/trace-test-plain-500')
        ->assertStatus(500);

    $traces = app(InMemoryTraceRecorder::class)->all();
    $spans = app(InMemorySpanRecorder::class)->all();

    expect($traces)
        ->toHaveCount(1)
        ->and($traces[0]->status)
        ->toBe(TraceStatus::Completed)
        ->and($traces[0]->error)
        ->toBeNull()
        ->and($spans)
        ->toHaveCount(1)
        ->and($spans[0]->status)
        ->toBe(SpanStatus::Completed)
        ->and($spans[0]->error)
        ->toBeNull()
        ->and($spans[0]->attributes)
        ->toMatchArray(['http.status_code' => 500])
        ->and(app(Tracer::class)->context())
        ->toBeNull();
});

it('completes the trace when the exception handler renders a client error', function (): void {
    Route::middleware(TraceRequest::class)
        ->get('/trace-test-not-found', fn () => abort(404));

    $this->getJson('/trace-test-not-found')
        ->assertNotFound();

    $traces = app(InMemoryTraceRecorder::class)->all();
    $spans = app(InMemorySpanRecorder::class)->all();

    expect(end($traces)->status)
        ->toBe(TraceStatus::Completed)
        ->and(end($spans)->status)
        ->toBe(SpanStatus::Completed)
        ->and(end($spans)->attributes)
        ->toMatchArray(['http.status_code' => 404]);
});

it('records no trace for an http request when tracing is disabled', function (): void {
    config()->set('laravel-trace.enabled', false);

    $this->postJson('/trace-test')
        ->assertSuccessful();

    expect(app(InMemoryTraceRecorder::class)->all())
        ->toBeEmpty()
        ->and(app(InMemorySpanRecorder::class)->all())
        ->toBeEmpty();
});
