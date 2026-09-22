<?php

declare(strict_types=1);

namespace AdilAzhari\LaravelTrace\Read;

use AdilAzhari\LaravelTrace\Contracts\SpanReader;
use AdilAzhari\LaravelTrace\Span\SpanId;
use AdilAzhari\LaravelTrace\Span\SpanStatus;
use AdilAzhari\LaravelTrace\Span\SpanType;
use AdilAzhari\LaravelTrace\Trace\TraceId;
use DateTimeImmutable;
use DateTimeInterface;
use InvalidArgumentException;

/**
 * An immutable description of which spans to read and in what order.
 *
 * Inert, like {@see TraceQuery}: a {@see SpanReader}
 * interprets it, and the in-memory and database readers must agree on the
 * result. Every `where*` / `order*` method returns a new instance.
 */
final class SpanQuery
{
    /** @var list<string> */
    public const array SORTABLE_FIELDS = ['started_at', 'finished_at', 'duration_ms', 'name', 'type'];

    /** @var list<string> */
    public array $ids = [];

    /** @var list<string> */
    public array $traceIds = [];

    /** @var list<string> */
    public array $parentIds = [];

    /** @var list<SpanType> */
    public array $types = [];

    /** @var list<SpanStatus> */
    public array $statuses = [];

    /**
     * `null` = any, `true` = only spans with no parent (trace roots).
     */
    public ?bool $rootsOnly = null;

    public ?DateTimeImmutable $startedAfter = null;

    public ?DateTimeImmutable $startedBefore = null;

    public ?float $minDurationMs = null;

    public ?float $maxDurationMs = null;

    /**
     * `null` = any, `true` = only spans that failed with an error,
     * `false` = only spans without an error.
     */
    public ?bool $hasError = null;

    /** @var array<string, string|int|float|bool|null> */
    public array $attributes = [];

    public OrderBy $orderBy;

    public function __construct()
    {
        $this->orderBy = new OrderBy('started_at', 'desc');
    }

    public static function new(): self
    {
        return new self;
    }

    public function whereId(SpanId|string ...$ids): self
    {
        $clone = clone $this;
        $clone->ids = self::stringify(...$ids);

        return $clone;
    }

    public function whereTrace(TraceId|string ...$traceIds): self
    {
        $clone = clone $this;
        $clone->traceIds = self::stringify(...$traceIds);

        return $clone;
    }

    public function whereParent(SpanId|string ...$parentIds): self
    {
        $clone = clone $this;
        $clone->parentIds = self::stringify(...$parentIds);

        return $clone;
    }

    public function whereType(SpanType ...$types): self
    {
        $clone = clone $this;
        $clone->types = array_values($types);

        return $clone;
    }

    public function whereStatus(SpanStatus ...$statuses): self
    {
        $clone = clone $this;
        $clone->statuses = array_values($statuses);

        return $clone;
    }

    public function onlyRoots(): self
    {
        $clone = clone $this;
        $clone->rootsOnly = true;

        return $clone;
    }

    public function startedAfter(DateTimeInterface $at): self
    {
        $clone = clone $this;
        $clone->startedAfter = self::toImmutable($at);

        return $clone;
    }

    public function startedBefore(DateTimeInterface $at): self
    {
        $clone = clone $this;
        $clone->startedBefore = self::toImmutable($at);

        return $clone;
    }

    public function minDurationMs(float|int $milliseconds): self
    {
        $clone = clone $this;
        $clone->minDurationMs = (float) $milliseconds;

        return $clone;
    }

    public function maxDurationMs(float|int $milliseconds): self
    {
        $clone = clone $this;
        $clone->maxDurationMs = (float) $milliseconds;

        return $clone;
    }

    public function onlyErrors(): self
    {
        $clone = clone $this;
        $clone->hasError = true;

        return $clone;
    }

    public function withoutErrors(): self
    {
        $clone = clone $this;
        $clone->hasError = false;

        return $clone;
    }

    public function whereAttribute(string $key, string|int|float|bool|null $value): self
    {
        $clone = clone $this;
        $clone->attributes = [...$this->attributes, $key => $value];

        return $clone;
    }

    /**
     * @throws InvalidArgumentException if $field is not one of
     *                                  self::SORTABLE_FIELDS, or if
     *                                  $direction is not "asc"/"desc"
     *                                  (via {@see OrderBy})
     */
    public function orderByField(string $field, string $direction = 'desc'): self
    {
        if (! in_array($field, self::SORTABLE_FIELDS, true)) {
            throw new InvalidArgumentException(sprintf(
                'Cannot order spans by [%s]. Allowed: %s.',
                $field,
                implode(', ', self::SORTABLE_FIELDS),
            ));
        }

        $clone = clone $this;
        $clone->orderBy = new OrderBy($field, $direction);

        return $clone;
    }

    public function latest(): self
    {
        return $this->orderByField('started_at', 'desc');
    }

    public function oldest(): self
    {
        return $this->orderByField('started_at', 'asc');
    }

    /**
     * @return list<string>
     */
    private static function stringify(SpanId|TraceId|string ...$ids): array
    {
        return array_values(array_map(
            static fn (SpanId|TraceId|string $id): string => (string) $id,
            $ids,
        ));
    }

    private static function toImmutable(DateTimeInterface $at): DateTimeImmutable
    {
        return $at instanceof DateTimeImmutable
            ? $at
            : DateTimeImmutable::createFromInterface($at);
    }
}
