<?php

declare(strict_types=1);

use AdilAzhari\LaravelTrace\Trace\TraceId;
use AdilAzhari\LaravelTrace\Tracing\InMemorySpanStore;
use AdilAzhari\LaravelTrace\Tracing\InMemoryStorageCleaner;
use AdilAzhari\LaravelTrace\Tracing\InMemoryTraceStore;

it('clears traces and spans out of both stores', function (): void {
    $traceStore = new InMemoryTraceStore;
    $spanStore = new InMemorySpanStore;
    $trace = makeTrace();

    $traceStore->put($trace);
    $spanStore->put(makeSpan($trace->id));

    (new InMemoryStorageCleaner($traceStore, $spanStore))->clear();

    expect($traceStore->all())->toBe([])
        ->and($spanStore->all())->toBe([]);
});

it('is safe to call when both stores are already empty', function (): void {
    $traceStore = new InMemoryTraceStore;
    $spanStore = new InMemorySpanStore;

    (new InMemoryStorageCleaner($traceStore, $spanStore))->clear();

    expect($traceStore->all())->toBe([])
        ->and($spanStore->all())->toBe([]);
});

it('does not touch a store instance it was not given', function (): void {
    $clearedTraceStore = new InMemoryTraceStore;
    $clearedSpanStore = new InMemorySpanStore;
    $untouchedSpanStore = new InMemorySpanStore;
    $trace = makeTrace();

    $clearedTraceStore->put($trace);
    $untouchedSpanStore->put(makeSpan(TraceId::generate()));

    (new InMemoryStorageCleaner($clearedTraceStore, $clearedSpanStore))->clear();

    expect($clearedTraceStore->all())->toBe([])
        ->and($untouchedSpanStore->all())->toHaveCount(1);
});
