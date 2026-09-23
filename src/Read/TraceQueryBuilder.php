<?php

declare(strict_types=1);

namespace AdilAzhari\LaravelTrace\Read;

use AdilAzhari\LaravelTrace\Contracts\TraceReader;
use AdilAzhari\LaravelTrace\Trace\Trace;
use AdilAzhari\LaravelTrace\Trace\TraceStatus;
use DateTimeInterface;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

/**
 * Fluent sugar over {@see TraceQuery} and {@see TraceReader}.
 *
 * Short-lived, created per `LaravelTrace::traces()` call. Holds no
 * persistence knowledge — every fluent method just folds another clause
 * into an immutable {@see TraceQuery}; the terminal methods hand that query
 * to the reader.
 */
final class TraceQueryBuilder
{
    private TraceQuery $query;

    public function __construct(
        private readonly TraceReader $reader,
    ) {
        $this->query = TraceQuery::new();
    }

    public function whereId(string ...$ids): self
    {
        $this->query = $this->query->whereId(...$ids);

        return $this;
    }

    public function whereName(string ...$names): self
    {
        $this->query = $this->query->whereName(...$names);

        return $this;
    }

    public function whereStatus(TraceStatus ...$statuses): self
    {
        $this->query = $this->query->whereStatus(...$statuses);

        return $this;
    }

    public function startedAfter(DateTimeInterface $at): self
    {
        $this->query = $this->query->startedAfter($at);

        return $this;
    }

    public function startedBefore(DateTimeInterface $at): self
    {
        $this->query = $this->query->startedBefore($at);

        return $this;
    }

    public function minDurationMs(float|int $milliseconds): self
    {
        $this->query = $this->query->minDurationMs($milliseconds);

        return $this;
    }

    public function maxDurationMs(float|int $milliseconds): self
    {
        $this->query = $this->query->maxDurationMs($milliseconds);

        return $this;
    }

    public function onlyErrors(): self
    {
        $this->query = $this->query->onlyErrors();

        return $this;
    }

    public function withoutErrors(): self
    {
        $this->query = $this->query->withoutErrors();

        return $this;
    }

    public function whereAttribute(string $key, string|int|float|bool|null $value): self
    {
        $this->query = $this->query->whereAttribute($key, $value);

        return $this;
    }

    public function orderBy(string $field, string $direction = 'desc'): self
    {
        $this->query = $this->query->orderByField($field, $direction);

        return $this;
    }

    public function latest(): self
    {
        $this->query = $this->query->latest();

        return $this;
    }

    public function oldest(): self
    {
        $this->query = $this->query->oldest();

        return $this;
    }

    public function toQuery(): TraceQuery
    {
        return $this->query;
    }

    /**
     * @return Collection<int, Trace>
     */
    public function get(): Collection
    {
        return $this->reader->get($this->query);
    }

    /**
     * Materialises the matching set and returns its first trace. Use
     * {@see self::paginate()} when the set may be large.
     */
    public function first(): ?Trace
    {
        return $this->reader->get($this->query)->first();
    }

    /**
     * @return LengthAwarePaginator<int, Trace>
     */
    public function paginate(int $perPage = 25, int $page = 1): LengthAwarePaginator
    {
        return $this->reader->paginate($this->query, $perPage, $page);
    }

    public function count(): int
    {
        return $this->reader->count($this->query);
    }

    public function exists(): bool
    {
        return $this->count() > 0;
    }
}
