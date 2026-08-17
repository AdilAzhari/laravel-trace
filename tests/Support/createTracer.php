<?php

declare(strict_types=1);

use LaravelTrace\LaravelTrace\Context\InMemoryTraceContextStore;
use LaravelTrace\LaravelTrace\Tracing\InMemorySpanRecorder;
use LaravelTrace\LaravelTrace\Tracing\InMemoryTraceRecorder;
use LaravelTrace\LaravelTrace\Tracing\Tracer;

function createTracer(
    ?InMemoryTraceContextStore $store = null,
    ?InMemorySpanRecorder $recorder = null,
    ?InMemoryTraceRecorder $traceRecorder = null,
): Tracer {
    return new Tracer(
        $store ?? new InMemoryTraceContextStore,
        $recorder ?? new InMemorySpanRecorder,
        $traceRecorder ?? new InMemoryTraceRecorder,
    );
}
