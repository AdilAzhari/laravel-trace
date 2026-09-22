<?php

declare(strict_types=1);

namespace AdilAzhari\LaravelTrace\Read;

use AdilAzhari\LaravelTrace\Contracts\TraceReader;
use AdilAzhari\LaravelTrace\Trace\TraceStatus;
use DateTimeImmutable;
use DateTimeInterface;
use InvalidArgumentException;

/**
 * An immutable description of which traces to read and in what order.
 *
 * Inert: it holds no database or storage awareness. A {@see TraceReader}
 * implementation interprets it — the in-memory reader by filtering an array,
 * the database reader by building an Eloquent query — and both must produce
 * the same result for the same query.
 *
 * Every `where*` / `order*` method returns a new instance; the original is
 * never mutated.
 */
final class TraceQuery
{
    /**
     * Columns a trace list may be ordered by. Anything else is rejected so
     * a caller cannot push an arbitrary identifier into an `ORDER BY`.
     *
     * @var list<string>
     */
    public const array SORTABLE_FIELDS = ['started_at', 'finished_at', 'duration_ms', 'name'];

    /** @var list<string> */
    public array $ids = [];

    /** @var list<string> */
    public array $names = [];

    /** @var list<TraceStatus> */
    public array $statuses = [];

    public ?DateTimeImmutable $startedAfter = null;

    public ?DateTimeImmutable $startedBefore = null;

    public ?float $minDurationMs = null;

    public ?float $maxDurationMs = null;

    /**
     * `null` = any, `true` = only traces that failed with an error,
     * `false` = only traces without an error.
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

    public function whereId(string ...$ids): self
    {
        $clone = clone $this;
        $clone->ids = array_values($ids);

        return $clone;
    }

    public function whereName(string ...$names): self
    {
        $clone = clone $this;
        $clone->names = array_values($names);

        return $clone;
    }

    public function whereStatus(TraceStatus ...$statuses): self
    {
        $clone = clone $this;
        $clone->statuses = array_values($statuses);

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
                'Cannot order traces by [%s]. Allowed: %s.',
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

    private static function toImmutable(DateTimeInterface $at): DateTimeImmutable
    {
        return $at instanceof DateTimeImmutable
            ? $at
            : DateTimeImmutable::createFromInterface($at);
    }
}
