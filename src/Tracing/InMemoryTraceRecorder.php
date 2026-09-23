<?php

declare(strict_types=1);

namespace AdilAzhari\LaravelTrace\Tracing;

use AdilAzhari\LaravelTrace\Contracts\TraceRecorder;
use AdilAzhari\LaravelTrace\Trace\Trace;

final readonly class InMemoryTraceRecorder implements TraceRecorder
{
    public function __construct(
        private InMemoryTraceStore $store = new InMemoryTraceStore,
    ) {}

    /**
     * Idempotent by trace ID: a later record for the same trace (e.g. its
     * terminal state) replaces the earlier one rather than appending a
     * second entry.
     */
    public function record(Trace $trace): void
    {
        $this->store->put($trace);
    }

    /**
     * @return list<Trace>
     */
    public function all(): array
    {
        return $this->store->all();
    }
}
