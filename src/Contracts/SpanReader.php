<?php

declare(strict_types=1);

namespace AdilAzhari\LaravelTrace\Contracts;

use AdilAzhari\LaravelTrace\Read\SpanQuery;
use AdilAzhari\LaravelTrace\Span\Span;
use AdilAzhari\LaravelTrace\Span\SpanId;
use AdilAzhari\LaravelTrace\Trace\TraceId;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

/**
 * Reads persisted spans back as {@see Span} domain objects.
 *
 * The read-side counterpart to {@see SpanRecorder}. Which implementation the
 * container resolves is decided by `laravel-trace.storage.driver`, the same
 * switch the recorder honours. Implementations never expose their
 * persistence types across this boundary.
 */
interface SpanReader
{
    public function find(SpanId $id): ?Span;

    /**
     * @return Collection<int, Span>
     */
    public function get(SpanQuery $query): Collection;

    /**
     * All spans belonging to a trace, ordered oldest first (waterfall order).
     *
     * @return Collection<int, Span>
     */
    public function forTrace(TraceId $traceId): Collection;

    /**
     * The direct children of a span, ordered oldest first.
     *
     * @return Collection<int, Span>
     */
    public function children(SpanId $parentId): Collection;

    /**
     * @return LengthAwarePaginator<int, Span>
     */
    public function paginate(SpanQuery $query, int $perPage = 25, int $page = 1): LengthAwarePaginator;

    public function count(SpanQuery $query): int;
}
