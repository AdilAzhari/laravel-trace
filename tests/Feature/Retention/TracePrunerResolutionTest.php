<?php

declare(strict_types=1);

use AdilAzhari\LaravelTrace\Contracts\TracePruner;
use AdilAzhari\LaravelTrace\Retention\DatabaseTracePruner;
use AdilAzhari\LaravelTrace\Retention\InMemoryTracePruner;

it('resolves the in-memory pruner by default', function (): void {
    expect(app(TracePruner::class))->toBeInstanceOf(InMemoryTracePruner::class);
});

it('resolves the database pruner when configured', function (): void {
    config()->set('laravel-trace.storage.driver', 'database');

    expect(app(TracePruner::class))->toBeInstanceOf(DatabaseTracePruner::class);
});

it('throws for an unknown storage driver', function (): void {
    config()->set('laravel-trace.storage.driver', 'redis');

    expect(fn () => app(TracePruner::class))->toThrow(InvalidArgumentException::class);
});
