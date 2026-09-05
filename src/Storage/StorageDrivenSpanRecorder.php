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
 * This is the object actually bound to the `SpanRecorder` contract: it is
 * resolved once, early, as part of the container's
 * `'events' -> TracingEventDispatcher -> EventListenerTracer -> Tracer`
 * dependency chain (see {@see Tracer::isEnabled()}
 * for the same constraint on the `enabled` flag), well before test or
 * runtime config overrides to `laravel-trace.storage.driver` would have
 * taken effect. Picking the concrete recorder once at that point would
 * permanently bake in whichever driver was configured at that moment;
 * deferring the choice to each call keeps it live for the lifetime of the
 * request.
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
