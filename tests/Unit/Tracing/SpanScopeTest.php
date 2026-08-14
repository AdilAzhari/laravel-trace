<?php

declare(strict_types=1);

use LaravelTrace\LaravelTrace\Context\InMemoryTraceContextStore;
use LaravelTrace\LaravelTrace\Span\SpanType;
use LaravelTrace\LaravelTrace\Tracing\SpanScope;
use LaravelTrace\LaravelTrace\Tracing\Tracer;

it('restores the previous context when closed', function () {
    $store = new InMemoryTraceContextStore();
    $tracer = new Tracer($store);

    $tracer->start('CreateOrder');

    $parent = $tracer->span(
        'CreateOrder',
        SpanType::Action,
    );

    $parentContext = $tracer->context();

    $child = $tracer->span(
        'ReserveInventory',
        SpanType::Action,
    );

    expect($tracer->context()?->spanId)
        ->toBe($child->span()->id);

    $child->close();

    expect($tracer->context())
        ->toBe($parentContext);
});
