<?php

declare(strict_types=1);

namespace AdilAzhari\LaravelTrace\Tracing;

use AdilAzhari\LaravelTrace\Span\Span;

/**
 * The process-lifetime store the in-memory storage driver keeps spans in.
 *
 * Registered as a container singleton so the in-memory recorder and the
 * in-memory reader are two views of the same data rather than the reader
 * reaching into the recorder. Keyed by span ID, so recording the same span
 * again (its terminal state) replaces the earlier entry.
 */
final class InMemorySpanStore
{
    /**
     * @var array<string, Span>
     */
    private array $spans = [];

    public function put(Span $span): void
    {
        $this->spans[$span->id->value] = $span;
    }

    public function get(string $id): ?Span
    {
        return $this->spans[$id] ?? null;
    }

    public function forget(string $id): void
    {
        unset($this->spans[$id]);
    }

    /**
     * @return list<Span>
     */
    public function all(): array
    {
        return array_values($this->spans);
    }

    public function flush(): void
    {
        $this->spans = [];
    }
}
