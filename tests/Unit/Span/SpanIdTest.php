<?php

declare(strict_types=1);

use LaravelTrace\LaravelTrace\Span\SpanId;

it('generates a unique span id', function (): void {
    $first = SpanId::generate();
    $second = SpanId::generate();

    expect($first)
        ->toBeInstanceOf(SpanId::class)
        ->and($first->value)
        ->not->toBeEmpty()
        ->and($first->value)
        ->not->toBe($second->value);
});

it('can be converted to a string', function (): void {
    $spanId = new SpanId('01K2SPAN123');

    expect((string) $spanId)
        ->toBe('01K2SPAN123');
});
