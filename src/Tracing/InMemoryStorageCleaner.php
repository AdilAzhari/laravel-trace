<?php

declare(strict_types=1);

namespace AdilAzhari\LaravelTrace\Tracing;

use AdilAzhari\LaravelTrace\Contracts\TraceContextStore;

/**
 * Explicit lifecycle operation for the `memory` storage driver: clears the
 * shared {@see InMemoryTraceStore} and {@see InMemorySpanStore} together so
 * a caller can never clear one and leave the other stale.
 *
 * Deliberately separate from the active trace context (see
 * {@see TraceContextStore}) - clearing
 * which trace/span is currently open is unrelated to clearing what has
 * already been recorded.
 *
 * The package never calls this on its own: doing so automatically at
 * request/job termination would silently break reading back what the
 * current process just recorded, which the `memory` driver otherwise
 * supports for the life of the application instance. A long-lived worker
 * (`queue:work`, Octane) that wants to bound memory growth must call
 * {@see self::clear()} explicitly at whatever boundary makes sense for it.
 *
 * Has no effect on the `database` driver: it only touches these two
 * in-memory singletons directly, never persisted rows.
 */
final readonly class InMemoryStorageCleaner
{
    public function __construct(
        private InMemoryTraceStore $traceStore,
        private InMemorySpanStore $spanStore,
    ) {}

    public function clear(): void
    {
        $this->traceStore->flush();
        $this->spanStore->flush();
    }
}
