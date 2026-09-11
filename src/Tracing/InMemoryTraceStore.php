<?php

declare(strict_types=1);

namespace AdilAzhari\LaravelTrace\Tracing;

use AdilAzhari\LaravelTrace\Trace\Trace;

/**
 * The process-lifetime store the in-memory storage driver keeps traces in.
 *
 * Registered as a container singleton so the in-memory recorder and the
 * in-memory reader are two views of the same data rather than the reader
 * reaching into the recorder. Keyed by trace ID, so recording the same
 * trace again (its terminal state) replaces the earlier entry.
 */
final class InMemoryTraceStore
{
    /**
     * @var array<string, Trace>
     */
    private array $traces = [];

    public function put(Trace $trace): void
    {
        $this->traces[$trace->id->value] = $trace;
    }

    public function get(string $id): ?Trace
    {
        return $this->traces[$id] ?? null;
    }

    public function forget(string $id): void
    {
        unset($this->traces[$id]);
    }

    /**
     * @return list<Trace>
     */
    public function all(): array
    {
        return array_values($this->traces);
    }

    public function flush(): void
    {
        $this->traces = [];
    }
}
