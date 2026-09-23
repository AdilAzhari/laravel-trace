<?php

declare(strict_types=1);

namespace AdilAzhari\LaravelTrace\Storage;

use AdilAzhari\LaravelTrace\Contracts\SpanRecorder;
use AdilAzhari\LaravelTrace\Span\Span;
use AdilAzhari\LaravelTrace\Tracing\InMemorySpanRecorder;
use AdilAzhari\LaravelTrace\Tracing\Tracer;
use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Contracts\Foundation\Application;
use InvalidArgumentException;

/**
 * Delegates to the {@see SpanRecorder} selected by
 * `laravel-trace.storage.driver`, read live on every {@see self::record()}
 * call rather than once at construction time.
 *
 * This is the object actually bound to the `SpanRecorder` contract: a
 * container singleton, injected into the singleton {@see Tracer} and then
 * reused for the life of the application instance - across every request
 * or job a long-lived worker handles (see {@see Tracer::isEnabled()} for
 * the same constraint on the `enabled` flag). Picking the concrete recorder
 * once, on first resolution, would permanently bake in whichever driver was
 * configured at that moment and miss later test or runtime config overrides
 * to `laravel-trace.storage.driver`; deferring the choice to each call
 * keeps it live.
 */
final readonly class StorageDrivenSpanRecorder implements SpanRecorder
{
    public function __construct(
        private Application $app,
        private ConfigRepository $config,
    ) {}

    public function record(Span $span): void
    {
        $this->driver()->record($span);
    }

    private function driver(): SpanRecorder
    {
        $driver = $this->config->get('laravel-trace.storage.driver', 'memory');

        return match ($driver) {
            'memory' => $this->app->make(InMemorySpanRecorder::class),
            'database' => $this->app->make(DatabaseSpanRecorder::class),
            default => throw new InvalidArgumentException(
                sprintf(
                    'Unknown laravel-trace storage driver [%s]. Expected "memory" or "database".',
                    is_scalar($driver) ? (string) $driver : get_debug_type($driver),
                ),
            ),
        };
    }
}
