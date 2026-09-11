<?php

declare(strict_types=1);

namespace AdilAzhari\LaravelTrace\Read;

use AdilAzhari\LaravelTrace\Contracts\SpanReader;
use AdilAzhari\LaravelTrace\Span\Span;
use AdilAzhari\LaravelTrace\Span\SpanId;
use AdilAzhari\LaravelTrace\Span\SpanStatus;
use AdilAzhari\LaravelTrace\Span\SpanType;
use AdilAzhari\LaravelTrace\Trace\TraceId;
use DateTimeInterface;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

/**
 * Fluent sugar over {@see SpanQuery} and {@see SpanReader}. Short-lived,
 * created per `LaravelTrace::spans()` call; holds no persistence knowledge.
 */
final class SpanQueryBuilder
{
    private SpanQuery $query;

    public function __construct(
        private readonly SpanReader $reader,
    ) {
        $this->query = SpanQuery::new();
    }

    public function whereId(SpanId|string ...$ids): self
    {
        $this->query = $this->query->whereId(...$ids);

        return $this;
    }

    public function whereTrace(TraceId|string ...$traceIds): self
    {
        $this->query = $this->query->whereTrace(...$traceIds);

        return $this;
    }

    public function whereParent(SpanId|string ...$parentIds): self
    {
        $this->query = $this->query->whereParent(...$parentIds);

        return $this;
    }

    public function onlyRoots(): self
    {
        $this->query = $this->query->onlyRoots();

        return $this;
    }

    public function whereType(SpanType ...$types): self
    {
        $this->query = $this->query->whereType(...$types);

        return $this;
    }

    public function whereStatus(SpanStatus ...$statuses): self
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

    public function toQuery(): SpanQuery
    {
        return $this->query;
    }

    /**
     * @return Collection<int, Span>
     */
    public function get(): Collection
    {
        return $this->reader->get($this->query);
    }

    public function first(): ?Span
    {
        return $this->reader->get($this->query)->first();
    }

    /**
     * The matching spans as a parent/child tree.
     *
     * @return list<SpanNode>
     */
    public function tree(): array
    {
        return SpanTree::fromSpans($this->reader->get($this->query));
    }

    /**
     * @return LengthAwarePaginator<int, Span>
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
