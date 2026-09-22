<?php

declare(strict_types=1);

use AdilAzhari\LaravelTrace\Contracts\Tracer;

it('renamed the database query instrumentation key to instrumentation.database.enabled', function (): void {
    expect(config('laravel-trace.instrumentation.database.enabled'))->toBeTrue()
        ->and(config('laravel-trace.database'))->toBeNull();
});

it('defaults every config key to its documented value when no env var is set', function (): void {
    expect(config('laravel-trace.enabled'))->toBeTrue()
        ->and(config('laravel-trace.instrumentation.database.enabled'))->toBeTrue()
        ->and(config('laravel-trace.queue.enabled'))->toBeTrue()
        ->and(config('laravel-trace.http.propagate_outbound'))->toBeFalse()
        ->and(config('laravel-trace.storage.driver'))->toBe('memory')
        ->and(config('laravel-trace.storage.database.swallow_exceptions'))->toBeTrue()
        ->and(config('laravel-trace.storage.retention.enabled'))->toBeFalse()
        ->and(config('laravel-trace.storage.retention.days'))->toBe(7)
        ->and(config('laravel-trace.storage.retention.chunk_size'))->toBe(500);
});

it('reads LARAVEL_TRACE_ENABLED from the environment', function (): void {
    withEnv(['LARAVEL_TRACE_ENABLED' => 'false'], function (): void {
        expect(config('laravel-trace.enabled'))->toBeFalse();
    });
});

it('reads LARAVEL_TRACE_DATABASE_QUERY_ENABLED from the environment', function (): void {
    withEnv(['LARAVEL_TRACE_DATABASE_QUERY_ENABLED' => 'false'], function (): void {
        expect(config('laravel-trace.instrumentation.database.enabled'))->toBeFalse();
    });
});

it('reads LARAVEL_TRACE_QUEUE_ENABLED from the environment', function (): void {
    withEnv(['LARAVEL_TRACE_QUEUE_ENABLED' => 'false'], function (): void {
        expect(config('laravel-trace.queue.enabled'))->toBeFalse();
    });
});

it('reads LARAVEL_TRACE_HTTP_PROPAGATE_OUTBOUND from the environment', function (): void {
    withEnv(['LARAVEL_TRACE_HTTP_PROPAGATE_OUTBOUND' => 'true'], function (): void {
        expect(config('laravel-trace.http.propagate_outbound'))->toBeTrue();
    });
});

it('reads LARAVEL_TRACE_STORAGE_DRIVER from the environment', function (): void {
    withEnv(['LARAVEL_TRACE_STORAGE_DRIVER' => 'database'], function (): void {
        expect(config('laravel-trace.storage.driver'))->toBe('database');
    });
});

it('reads LARAVEL_TRACE_STORAGE_DATABASE_SWALLOW_EXCEPTIONS from the environment', function (): void {
    withEnv(['LARAVEL_TRACE_STORAGE_DATABASE_SWALLOW_EXCEPTIONS' => 'false'], function (): void {
        expect(config('laravel-trace.storage.database.swallow_exceptions'))->toBeFalse();
    });
});

it('reads LARAVEL_TRACE_RETENTION_ENABLED from the environment', function (): void {
    withEnv(['LARAVEL_TRACE_RETENTION_ENABLED' => 'true'], function (): void {
        expect(config('laravel-trace.storage.retention.enabled'))->toBeTrue();
    });
});

it('reads LARAVEL_TRACE_RETENTION_DAYS from the environment', function (): void {
    withEnv(['LARAVEL_TRACE_RETENTION_DAYS' => '14'], function (): void {
        expect(config('laravel-trace.storage.retention.days'))->toBe('14');
    });
});

it('reads LARAVEL_TRACE_RETENTION_CHUNK_SIZE from the environment', function (): void {
    withEnv(['LARAVEL_TRACE_RETENTION_CHUNK_SIZE' => '250'], function (): void {
        expect(config('laravel-trace.storage.retention.chunk_size'))->toBe('250');
    });
});

it('parses a non-keyword falsy env string via ConfigBoolean, not PHP\'s raw (bool) cast', function (): void {
    // Laravel's env() helper already special-cases the literal strings
    // "true"/"false" (and "(true)"/"(false)") into real booleans before
    // config/laravel-trace.php's env() call even returns - so an env value
    // of "false" never actually reaches ConfigBoolean::resolve() as a
    // string, and would read correctly even with the old, unsafe `(bool)`
    // cast this milestone replaced. "off" is not one of env()'s
    // special-cased keywords, so it *is* handed back as the raw string
    // "off": `(bool) 'off'` is `true` (the bug), while
    // ConfigBoolean::resolve() correctly parses it as `false`. This is the
    // env-var scenario that actually exercises the fix.
    withEnv(['LARAVEL_TRACE_ENABLED' => 'off'], function (): void {
        expect(config('laravel-trace.enabled'))->toBe('off');

        $tracer = app(Tracer::class);
        $tracer->start('EnvDisabledTest');

        expect($tracer->context())->toBeNull();
    });
});
