<?php

declare(strict_types=1);

namespace AdilAzhari\LaravelTrace\Read;

use AdilAzhari\LaravelTrace\Contracts\SpanReader;
use AdilAzhari\LaravelTrace\Span\Span;
use AdilAzhari\LaravelTrace\Span\SpanId;
use AdilAzhari\LaravelTrace\Trace\TraceId;
use AdilAzhari\LaravelTrace\Tracing\InMemorySpanStore;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

/**
 * Reads spans from the in-memory store the in-memory recorder writes to.
 *
 * Filters and sorts a plain array; must produce the same result as
 * {@see DatabaseSpanReader} for the same {@see SpanQuery}. Only sees what
 * the current process recorded.
 */
final readonly class InMemorySpanReader implements SpanReader
{
    public function __construct(
        private InMemorySpanStore $store,
    ) {}

    public function find(SpanId $id): ?Span
    {
        return $this->store->get($id->value);
    }

    public function get(SpanQuery $query): Collection
    {
        return new Collection($this->sorted($this->filtered($query), $query->orderBy));
    }

    public function forTrace(TraceId $traceId): Collection
    {
        return $this->get(SpanQuery::new()->whereTrace($traceId)->oldest());
    }

    public function children(SpanId $parentId): Collection
    {
        return $this->get(SpanQuery::new()->whereParent($parentId)->oldest());
    }

    public function paginate(SpanQuery $query, int $perPage = 25, int $page = 1): LengthAwarePaginator
    {
        $all = $this->sorted($this->filtered($query), $query->orderBy);

        return new LengthAwarePaginator(
            array_slice($all, ($page - 1) * $perPage, $perPage),
            count($all),
            $perPage,
            $page,
        );
    }

    public function count(SpanQuery $query): int
    {
        return count($this->filtered($query));
    }

    /**
     * @return list<Span>
     */
    private function filtered(SpanQuery $query): array
    {
        return array_values(array_filter(
            $this->store->all(),
            fn (Span $span): bool => $this->matches($span, $query),
        ));
    }

    private function matches(Span $span, SpanQuery $query): bool
    {
        if ($query->ids !== [] && ! in_array($span->id->value, $query->ids, true)) {
            return false;
        }

        if ($query->traceIds !== [] && ! in_array($span->traceId->value, $query->traceIds, true)) {
            return false;
        }

        if ($query->parentIds !== [] && ! in_array($span->parentId?->value, $query->parentIds, true)) {
            return false;
        }

        if ($query->rootsOnly === true && $span->parentId !== null) {
            return false;
        }

        if ($query->types !== [] && ! in_array($span->type, $query->types, true)) {
            return false;
        }

        if ($query->statuses !== [] && ! in_array($span->status, $query->statuses, true)) {
            return false;
        }

        if ($query->startedAfter !== null && $span->startedAt < $query->startedAfter) {
            return false;
        }

        if ($query->startedBefore !== null && $span->startedAt >= $query->startedBefore) {
            return false;
        }

        $duration = $span->durationMs();

        if ($query->minDurationMs !== null && ($duration === null || $duration < $query->minDurationMs)) {
            return false;
        }

        if ($query->maxDurationMs !== null && ($duration === null || $duration > $query->maxDurationMs)) {
            return false;
        }

        if ($query->hasError === true && $span->error === null) {
            return false;
        }

        if ($query->hasError === false && $span->error !== null) {
            return false;
        }

        foreach ($query->attributes as $key => $value) {
            if (($span->attributes[$key] ?? null) !== $value) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  list<Span>  $spans
     * @return list<Span>
     */
    private function sorted(array $spans, OrderBy $orderBy): array
    {
        usort($spans, function (Span $a, Span $b) use ($orderBy): int {
            $primary = match ($orderBy->field) {
                'finished_at' => InMemorySorting::compare($a->finishedAt, $b->finishedAt),
                'duration_ms' => InMemorySorting::compare($a->durationMs(), $b->durationMs()),
                'name' => InMemorySorting::compare($a->name, $b->name),
                'type' => InMemorySorting::compare($a->type->value, $b->type->value),
                default => InMemorySorting::compare($a->startedAt, $b->startedAt),
            };

            $primary = $orderBy->isAscending() ? $primary : -$primary;

            // Deterministic tiebreak: id descending, always.
            return $primary !== 0
                ? $primary
                : strcmp($b->id->value, $a->id->value);
        });

        return $spans;
    }
}
