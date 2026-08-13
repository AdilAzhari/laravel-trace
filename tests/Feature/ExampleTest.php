<?php

declare(strict_types=1);

use LaravelTrace\LaravelTrace\LaravelTrace;

it('resolves the singleton', function (): void {
    expect(app(LaravelTrace::class))->toBeInstanceOf(LaravelTrace::class);
});

it('returns the same instance from the container', function (): void {
    expect(app(LaravelTrace::class))->toBe(app(LaravelTrace::class));
});

it('merges the package config', function (): void {
    expect(config('laravel-trace.placeholder'))->toBe('default');
});

it('loads the package translations', function (): void {
    expect(trans('laravel-trace::messages.placeholder'))->toBe('LaravelTrace placeholder translation.');
});

it('loads the package views', function (): void {
    expect(view()->exists('laravel-trace::placeholder'))->toBeTrue();
});

it('registers the artisan command', function (): void {
    $this->artisan('laravel-trace:placeholder')
        ->expectsOutputToContain('LaravelTrace placeholder command executed.')
        ->assertSuccessful();
});
