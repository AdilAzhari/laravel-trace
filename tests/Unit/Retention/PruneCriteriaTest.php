<?php

declare(strict_types=1);

use AdilAzhari\LaravelTrace\Retention\PruneCriteria;

it('holds the cutoff, chunk size and dry-run flag', function (): void {
    $cutoff = new DateTimeImmutable('2026-01-01 00:00:00.000000');

    $criteria = new PruneCriteria($cutoff, chunkSize: 250, dryRun: true);

    expect($criteria->cutoff)->toBe($cutoff)
        ->and($criteria->chunkSize)->toBe(250)
        ->and($criteria->dryRun)->toBeTrue();
});

it('defaults to a real (non-dry) run with a chunk size of 500', function (): void {
    $criteria = new PruneCriteria(new DateTimeImmutable);

    expect($criteria->chunkSize)->toBe(500)
        ->and($criteria->dryRun)->toBeFalse();
});

it('rejects a zero chunk size', function (): void {
    expect(fn () => new PruneCriteria(new DateTimeImmutable, chunkSize: 0))
        ->toThrow(InvalidArgumentException::class);
});

it('rejects a negative chunk size', function (): void {
    expect(fn () => new PruneCriteria(new DateTimeImmutable, chunkSize: -1))
        ->toThrow(InvalidArgumentException::class);
});
