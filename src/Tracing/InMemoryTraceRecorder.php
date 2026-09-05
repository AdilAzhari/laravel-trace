<?php

declare(strict_types=1);

namespace AdilAzhari\LaravelTrace\Tracing;

use AdilAzhari\LaravelTrace\Contracts\TraceRecorder;
use AdilAzhari\LaravelTrace\Trace\Trace;

final class InMemoryTraceRecorder implements TraceRecorder
{
    /**
     * @var array<string, Trace>
     */
    private array $traces = [];

    /**
     * Idempotent by trace ID: a later record for the same trace (e.g. its
     * terminal state) replaces the earlier one rather than appending a
     * second entry.
     */
    public function record(Trace $trace): void
    {
        $this->traces[$trace->id->value] = $trace;
    }

    /**
     * @return list<Trace>
     */
    public function all(): array
    {
        return array_values($this->traces);
    }
}
