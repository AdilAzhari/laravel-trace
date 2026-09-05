<?php

declare(strict_types=1);

namespace AdilAzhari\LaravelTrace\Storage;

use AdilAzhari\LaravelTrace\Contracts\TraceRecorder;
use AdilAzhari\LaravelTrace\Trace\Trace;
use AdilAzhari\LaravelTrace\Tracing\InMemoryTraceRecorder;
use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Contracts\Foundation\Application;
use InvalidArgumentException;

/**
 * Delegates to the {@see TraceRecorder} selected by
 * `laravel-trace.storage.driver`, read live on every {@see self::record()}
 * call rather than once at construction time.
 *
 * See {@see StorageDrivenSpanRecorder} for why this indirection exists: the
 * object bound to the `TraceRecorder` contract is resolved too early, during
 * container bootstrap, for a one-shot driver choice to observe config
 * changes made after boot.
 */
final readonly class StorageDrivenTraceRecorder implements TraceRecorder
{
    public function __construct(
        private Application $app,
        private ConfigRepository $config,
    ) {}

    public function record(Trace $trace): void
    {
        $this->driver()->record($trace);
    }

    private function driver(): TraceRecorder
    {
        $driver = $this->config->get('laravel-trace.storage.driver', 'memory');

        return match ($driver) {
            'memory' => $this->app->make(InMemoryTraceRecorder::class),
            'database' => $this->app->make(DatabaseTraceRecorder::class),
            default => throw new InvalidArgumentException(
                sprintf(
                    'Unknown laravel-trace storage driver [%s]. Expected "memory" or "database".',
                    is_scalar($driver) ? (string) $driver : get_debug_type($driver),
                ),
            ),
        };
    }
}
