<?php

declare(strict_types=1);

use AdilAzhari\LaravelTrace\Context\InMemoryTraceContextStore;
use AdilAzhari\LaravelTrace\Http\Middleware\TraceRequest;
use AdilAzhari\LaravelTrace\Tracing\InMemorySpanRecorder;
use AdilAzhari\LaravelTrace\Tracing\InMemoryTraceRecorder;
use AdilAzhari\LaravelTrace\Tracing\Tracer;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

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
                ->not->toBeNull();

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
