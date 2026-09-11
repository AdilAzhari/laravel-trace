<?php

declare(strict_types=1);

use AdilAzhari\LaravelTrace\Span\Span;
use AdilAzhari\LaravelTrace\Span\SpanType;
use AdilAzhari\LaravelTrace\Trace\TraceId;
use AdilAzhari\LaravelTrace\Tracing\InMemorySpanStore;

it('stores and retrieves a span by id', function (): void {
    $store = new InMemorySpanStore;
    $span = Span::start(TraceId::generate(), 'ReserveInventory', SpanType::Action);

    $store->put($span);

    expect($store->get($span->id->value))->toBe($span)
        ->and($store->get('missing'))->toBeNull();
});

it('keys spans by id so a later put replaces the earlier one', function (): void {
    $store = new InMemorySpanStore;
    $span = Span::start(TraceId::generate(), 'ReserveInventory', SpanType::Action);

    $store->put($span);
    $store->put($span->completeSpan(new DateTimeImmutable));

    expect($store->all())->toHaveCount(1)
        ->and($store->all()[0]->finishedAt)->not->toBeNull();
});

it('preserves insertion order across distinct spans', function (): void {
    $store = new InMemorySpanStore;
    $traceId = TraceId::generate();
    $first = Span::start($traceId, 'first', SpanType::Action);
    $second = Span::start($traceId, 'second', SpanType::Action);

    $store->put($first);
    $store->put($second);

    expect($store->all())->toEqual([$first, $second]);
});
