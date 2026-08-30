<?php

declare(strict_types=1);

use AdilAzhari\LaravelTrace\Context\InMemoryTraceContextStore;
use AdilAzhari\LaravelTrace\Context\TraceContext;
use AdilAzhari\LaravelTrace\Trace\TraceId;

it('returns null when no context exists', function (): void {
    $store = new InMemoryTraceContextStore;

    expect($store->get())->toBeNull();
});

it('stores and retrieves a context', function (): void {
    $store = new InMemoryTraceContextStore;

    $context = new TraceContext(
        traceId: TraceId::generate(),
    );

    $store->set($context);

    expect($store->get())->toBe($context);
});

it('can clear the current context', function (): void {
    $store = new InMemoryTraceContextStore;

    $context = new TraceContext(
        traceId: TraceId::generate(),
    );

    $store->set($context);
    $store->clear();

    expect($store->get())->toBeNull();
});
