<?php

declare(strict_types=1);

use AdilAzhari\LaravelTrace\Context\InMemoryTraceContextStore;
use AdilAzhari\LaravelTrace\Tracing\InMemorySpanRecorder;
use AdilAzhari\LaravelTrace\Tracing\InMemoryTraceRecorder;
use AdilAzhari\LaravelTrace\Tracing\Tracer;

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
