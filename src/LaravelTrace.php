<?php

declare(strict_types=1);

namespace AdilAzhari\LaravelTrace;

use AdilAzhari\LaravelTrace\Contracts\SpanReader;
use AdilAzhari\LaravelTrace\Contracts\TraceReader;
use AdilAzhari\LaravelTrace\Read\SpanNode;
use AdilAzhari\LaravelTrace\Read\SpanQueryBuilder;
use AdilAzhari\LaravelTrace\Read\SpanTree;
use AdilAzhari\LaravelTrace\Read\TraceQueryBuilder;
use AdilAzhari\LaravelTrace\Span\Span;
use AdilAzhari\LaravelTrace\Span\SpanId;
use AdilAzhari\LaravelTrace\Trace\Trace;
use AdilAzhari\LaravelTrace\Trace\TraceId;
use Illuminate\Support\Collection;

/**
 * The read entry point behind the `LaravelTrace` facade.
 *
 * A thin front for the {@see TraceReader} / {@see SpanReader} contracts:
 * look a trace or span up by id, start a fluent query, or pull a trace's
 * spans as a flat list or a tree. It never touches the write path or any
 * persistence type.
 */
class LaravelTrace
{
    public function __construct(
        private readonly TraceReader $traceReader,
        private readonly SpanReader $spanReader,
    ) {}

    public function trace(TraceId|string $id): ?Trace
    {
        return $this->traceReader->find($this->traceId($id));
    }

    public function traces(): TraceQueryBuilder
    {
        return new TraceQueryBuilder($this->traceReader);
    }

    public function span(SpanId|string $id): ?Span
    {
        return $this->spanReader->find($id instanceof SpanId ? $id : new SpanId($id));
    }

    public function spans(): SpanQueryBuilder
    {
        return new SpanQueryBuilder($this->spanReader);
    }

    /**
     * Every span recorded for a trace, ordered oldest first.
     *
     * @return Collection<int, Span>
     */
    public function spansForTrace(TraceId|string $traceId): Collection
    {
        return $this->spanReader->forTrace($this->traceId($traceId));
    }

    /**
     * A trace's spans arranged as a parent/child tree.
     *
     * @return list<SpanNode>
     */
    public function spanTree(TraceId|string $traceId): array
    {
        return SpanTree::fromSpans($this->spansForTrace($traceId));
    }

    private function traceId(TraceId|string $id): TraceId
    {
        return $id instanceof TraceId ? $id : new TraceId($id);
    }
}
