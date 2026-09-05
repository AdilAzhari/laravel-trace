<?php

declare(strict_types=1);

use AdilAzhari\LaravelTrace\Storage\RecordsWithoutTracing;

it('is inactive by default', function (): void {
    expect(RecordsWithoutTracing::active())->toBeFalse();
});

it('is active only for the duration of the callback', function (): void {
    expect(RecordsWithoutTracing::active())->toBeFalse();

    $observed = RecordsWithoutTracing::run(
        fn (): bool => RecordsWithoutTracing::active(),
    );

    expect($observed)->toBeTrue()
        ->and(RecordsWithoutTracing::active())->toBeFalse();
});

it('restores the previous state instead of always clearing it', function (): void {
    RecordsWithoutTracing::run(function (): void {
        expect(RecordsWithoutTracing::active())->toBeTrue();

        RecordsWithoutTracing::run(function (): void {
            expect(RecordsWithoutTracing::active())->toBeTrue();
        });

        // Still active after the nested call returns - it did not clear
        // the outer scope's flag.
        expect(RecordsWithoutTracing::active())->toBeTrue();
    });

    expect(RecordsWithoutTracing::active())->toBeFalse();
});

it('restores the previous state even when the callback throws', function (): void {
    expect(fn () => RecordsWithoutTracing::run(function (): void {
        throw new RuntimeException('boom');
    }))->toThrow(RuntimeException::class);

    expect(RecordsWithoutTracing::active())->toBeFalse();
});

it('returns the callback result', function (): void {
    expect(RecordsWithoutTracing::run(fn (): int => 42))->toBe(42);
});
