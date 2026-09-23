<?php

declare(strict_types=1);

use AdilAzhari\LaravelTrace\Read\OrderBy;

it('defaults to a descending direction', function (): void {
    expect((new OrderBy('started_at'))->direction)->toBe('desc');
});

it('accepts an explicit ascending or descending direction', function (): void {
    expect((new OrderBy('name', 'asc'))->direction)->toBe('asc')
        ->and((new OrderBy('name', 'desc'))->direction)->toBe('desc');
});

it('reports its direction as a boolean', function (): void {
    expect((new OrderBy('name', 'asc'))->isAscending())->toBeTrue()
        ->and((new OrderBy('name', 'desc'))->isAscending())->toBeFalse();
});

it('rejects a direction that is neither asc nor desc', function (): void {
    expect(fn () => new OrderBy('started_at', 'sideways'))
        ->toThrow(InvalidArgumentException::class);
});
