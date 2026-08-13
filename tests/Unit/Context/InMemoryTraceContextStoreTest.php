<?php

declare(strict_types=1);

use LaravelTrace\LaravelTrace\Context\InMemoryTraceContextStore;
use LaravelTrace\LaravelTrace\Context\TraceContext;
use LaravelTrace\LaravelTrace\Trace\TraceId;

it('returns null when no context exists', function () {
    $store = new InMemoryTraceContextStore;

    expect($store->get())->toBeNull();
});

it('stores and retrieves a context', function () {
    $store = new InMemoryTraceContextStore;

    $context = new TraceContext(
        traceId: TraceId::generate(),
    );

    $store->set($context);

    expect($store->get())->toBe($context);
});

it('can clear the current context', function () {
    $store = new InMemoryTraceContextStore;

    $context = new TraceContext(
        traceId: TraceId::generate(),
    );

    $store->set($context);
    $store->clear();

    expect($store->get())->toBeNull();
});
