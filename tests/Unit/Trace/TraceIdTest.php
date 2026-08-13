<?php

declare(strict_types=1);

use LaravelTrace\LaravelTrace\Trace\TraceId;

it('generates a unique trace id', function (): void {
    $first = TraceId::generate();
    $second = TraceId::generate();

    expect($first)
        ->toBeInstanceOf(TraceId::class)
        ->and($first->value)
        ->not->toBeEmpty()
        ->and($first->value)
        ->not->toBe($second->value);
});

it('can be converted to a string', function (): void {
    $traceId = new TraceId('01K2ABC123');

    expect((string) $traceId)
        ->toBe('01K2ABC123');
});
