<?php

declare(strict_types=1);

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use LaravelTrace\LaravelTrace\Context\InMemoryTraceContextStore;
use LaravelTrace\LaravelTrace\Http\Middleware\TraceRequest;
use LaravelTrace\LaravelTrace\Tracing\InMemorySpanRecorder;
use LaravelTrace\LaravelTrace\Tracing\InMemoryTraceRecorder;
use LaravelTrace\LaravelTrace\Tracing\Tracer;

/**
 * @throws Throwable
 */
it('starts a trace for an http request', function (): void {
    $store = new InMemoryTraceContextStore;

    $tracer = new Tracer(
        $store,
        new InMemorySpanRecorder,
        new InMemoryTraceRecorder,
    );

    $middleware = new TraceRequest($tracer);

    $request = Request::create(
        '/api/orders',
        'POST',
    );

    $response = $middleware->handle(
        $request,
        function () use ($tracer) {
            expect($tracer->context())
                ->not->toBeNull()
                ->and($tracer->context()?->spanId)
                ->toBeNull();

            return new Response('OK');
        },
    );

    expect($response->getStatusCode())
        ->toBe(200)
        ->and($tracer->context())
        ->toBeNull();
});

it('clears the trace context when the request fails', function (): void {
    $store = new InMemoryTraceContextStore;

    $tracer = new Tracer(
        $store,
        new InMemorySpanRecorder,
        new InMemoryTraceRecorder,
    );

    $middleware = new TraceRequest($tracer);

    $request = Request::create(
        '/api/orders',
        'POST',
    );

    expect(/**
     * @throws Throwable
     */ fn () => $middleware->handle(
        $request,
        function () use ($tracer): void {
            expect($tracer->context())
                ->not->toBeNull();

            throw new RuntimeException('Something went wrong.');
        },
    ))->toThrow(RuntimeException::class)
        ->and($tracer->context())
        ->toBeNull();
});
