<?php

declare(strict_types=1);

namespace AdilAzhari\LaravelTrace\Contracts;

use AdilAzhari\LaravelTrace\Read\TraceQuery;
use AdilAzhari\LaravelTrace\Trace\Trace;
use AdilAzhari\LaravelTrace\Trace\TraceId;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

/**
 * Reads persisted traces back as {@see Trace} domain objects.
 *
 * The read-side counterpart to {@see TraceRecorder}. Which implementation
 * the container resolves is decided by `laravel-trace.storage.driver`, the
 * same switch the recorder honours. Implementations never expose their
 * persistence types (Eloquent models, arrays) across this boundary.
 */
interface TraceReader
{
    public function find(TraceId $id): ?Trace;

    /**
     * @return Collection<int, Trace>
     */
    public function get(TraceQuery $query): Collection;

    /**
     * @return LengthAwarePaginator<int, Trace>
     */
    public function paginate(TraceQuery $query, int $perPage = 25, int $page = 1): LengthAwarePaginator;

    public function count(TraceQuery $query): int;
}
