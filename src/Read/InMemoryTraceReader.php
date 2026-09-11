<?php

declare(strict_types=1);

namespace AdilAzhari\LaravelTrace\Read;

use AdilAzhari\LaravelTrace\Contracts\TraceReader;
use AdilAzhari\LaravelTrace\Trace\Trace;
use AdilAzhari\LaravelTrace\Trace\TraceId;
use AdilAzhari\LaravelTrace\Tracing\InMemoryTraceStore;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

/**
 * Reads traces from the in-memory store the in-memory recorder writes to.
 *
 * Filters and sorts a plain array; must produce the same result as
 * {@see DatabaseTraceReader} for the same {@see TraceQuery}. Only sees what
 * the current process recorded — the store does not outlive the request.
 */
final readonly class InMemoryTraceReader implements TraceReader
{
    public function __construct(
        private InMemoryTraceStore $store,
    ) {}

    public function find(TraceId $id): ?Trace
    {
        return $this->store->get($id->value);
    }

    public function get(TraceQuery $query): Collection
    {
        return new Collection($this->sorted($this->filtered($query), $query->orderBy));
    }

    public function paginate(TraceQuery $query, int $perPage = 25, int $page = 1): LengthAwarePaginator
    {
        $all = $this->sorted($this->filtered($query), $query->orderBy);

        return new LengthAwarePaginator(
            array_slice($all, ($page - 1) * $perPage, $perPage),
            count($all),
            $perPage,
            $page,
        );
    }

    public function count(TraceQuery $query): int
    {
        return count($this->filtered($query));
    }

    /**
     * @return list<Trace>
     */
    private function filtered(TraceQuery $query): array
    {
        return array_values(array_filter(
            $this->store->all(),
            fn (Trace $trace): bool => $this->matches($trace, $query),
        ));
    }

    private function matches(Trace $trace, TraceQuery $query): bool
    {
        if ($query->ids !== [] && ! in_array($trace->id->value, $query->ids, true)) {
            return false;
        }

        if ($query->names !== [] && ! in_array($trace->name, $query->names, true)) {
            return false;
        }

        if ($query->statuses !== [] && ! in_array($trace->status, $query->statuses, true)) {
            return false;
        }

        if ($query->startedAfter !== null && $trace->startedAt < $query->startedAfter) {
            return false;
        }

        if ($query->startedBefore !== null && $trace->startedAt >= $query->startedBefore) {
            return false;
        }

        $duration = $trace->durationMs();

        if ($query->minDurationMs !== null && ($duration === null || $duration < $query->minDurationMs)) {
            return false;
        }

        if ($query->maxDurationMs !== null && ($duration === null || $duration > $query->maxDurationMs)) {
            return false;
        }

        if ($query->hasError === true && $trace->error === null) {
            return false;
        }

        if ($query->hasError === false && $trace->error !== null) {
            return false;
        }

        foreach ($query->attributes as $key => $value) {
            if (($trace->attributes[$key] ?? null) !== $value) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  list<Trace>  $traces
     * @return list<Trace>
     */
    private function sorted(array $traces, OrderBy $orderBy): array
    {
        usort($traces, function (Trace $a, Trace $b) use ($orderBy): int {
            $primary = match ($orderBy->field) {
                'finished_at' => InMemorySorting::compare($a->finishedAt, $b->finishedAt),
                'duration_ms' => InMemorySorting::compare($a->durationMs(), $b->durationMs()),
                'name' => InMemorySorting::compare($a->name, $b->name),
                default => InMemorySorting::compare($a->startedAt, $b->startedAt),
            };

            $primary = $orderBy->isAscending() ? $primary : -$primary;

            // Deterministic tiebreak: id descending, always.
            return $primary !== 0
                ? $primary
                : strcmp($b->id->value, $a->id->value);
        });

        return $traces;
    }
}
