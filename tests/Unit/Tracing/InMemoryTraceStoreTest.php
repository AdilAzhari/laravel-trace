<?php

declare(strict_types=1);

use AdilAzhari\LaravelTrace\Trace\Trace;
use AdilAzhari\LaravelTrace\Tracing\InMemoryTraceStore;

it('stores and retrieves a trace by id', function (): void {
    $store = new InMemoryTraceStore;
    $trace = Trace::start('CreateOrder');

    $store->put($trace);

    expect($store->get($trace->id->value))->toBe($trace)
        ->and($store->get('missing'))->toBeNull();
});

it('keys traces by id so a later put replaces the earlier one', function (): void {
    $store = new InMemoryTraceStore;
    $trace = Trace::start('CreateOrder');

    $store->put($trace);
    $store->put($trace->complete(new DateTimeImmutable));

    expect($store->all())->toHaveCount(1)
        ->and($store->all()[0]->finishedAt)->not->toBeNull();
});

it('flushes all traces', function (): void {
    $store = new InMemoryTraceStore;
    $store->put(Trace::start('CreateOrder'));

    $store->flush();

    expect($store->all())->toBe([]);
});
