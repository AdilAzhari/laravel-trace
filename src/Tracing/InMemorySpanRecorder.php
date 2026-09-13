<?php

declare(strict_types=1);

namespace AdilAzhari\LaravelTrace\Tracing;

use AdilAzhari\LaravelTrace\Contracts\SpanRecorder;
use AdilAzhari\LaravelTrace\Span\Span;

final readonly class InMemorySpanRecorder implements SpanRecorder
{
    public function __construct(
        private InMemorySpanStore $store = new InMemorySpanStore,
    ) {}

    /**
     * Idempotent by span ID: a later record for the same span (e.g. its
     * terminal state) replaces the earlier one rather than appending a
     * second entry.
     */
    public function record(Span $span): void
    {
        $this->store->put($span);
    }

    /**
     * @return list<Span>
     */
    public function all(): array
    {
        return $this->store->all();
    }
}
