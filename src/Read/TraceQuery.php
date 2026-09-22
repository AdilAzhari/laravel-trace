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
 * Every `where*` / `order*` method returns a new instance; the class is
 * `readonly`, so the original can never be mutated, directly or otherwise.
 */
final readonly class TraceQuery
{
    /**
     * Columns a trace list may be ordered by. Anything else is rejected so
     * a caller cannot push an arbitrary identifier into an `ORDER BY`.
     *
     * @var list<string>
     */
    public const array SORTABLE_FIELDS = ['started_at', 'finished_at', 'duration_ms', 'name'];

    /**
     * @param  list<string>  $ids
     * @param  list<string>  $names
     * @param  list<TraceStatus>  $statuses
     * @param  bool|null  $hasError  `null` = any, `true` = only traces that
     *                               failed with an error, `false` = only
     *                               traces without an error.
     * @param  array<string, string|int|float|bool|null>  $attributes
     */
    public function __construct(
        public array $ids = [],
        public array $names = [],
        public array $statuses = [],
        public ?DateTimeImmutable $startedAfter = null,
        public ?DateTimeImmutable $startedBefore = null,
        public ?float $minDurationMs = null,
        public ?float $maxDurationMs = null,
        public ?bool $hasError = null,
        public array $attributes = [],
        public OrderBy $orderBy = new OrderBy('started_at', 'desc'),
    ) {}

    public static function new(): self
    {
        return new self;
    }

    public function whereId(string ...$ids): self
    {
        return $this->with(ids: array_values($ids));
    }

    public function whereName(string ...$names): self
    {
        return $this->with(names: array_values($names));
    }

    public function whereStatus(TraceStatus ...$statuses): self
    {
        return $this->with(statuses: array_values($statuses));
    }

    public function startedAfter(DateTimeInterface $at): self
    {
        return $this->with(startedAfter: self::toImmutable($at));
    }

    public function startedBefore(DateTimeInterface $at): self
    {
        return $this->with(startedBefore: self::toImmutable($at));
    }

    public function minDurationMs(float|int $milliseconds): self
    {
        return $this->with(minDurationMs: (float) $milliseconds);
    }

    public function maxDurationMs(float|int $milliseconds): self
    {
        return $this->with(maxDurationMs: (float) $milliseconds);
    }

    public function onlyErrors(): self
    {
        return $this->with(hasError: true);
    }

    public function withoutErrors(): self
    {
        return $this->with(hasError: false);
    }

    public function whereAttribute(string $key, string|int|float|bool|null $value): self
    {
        return $this->with(attributes: [...$this->attributes, $key => $value]);
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

        return $this->with(orderBy: new OrderBy($field, $direction));
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
     * Builds a new instance from the current one, with the given
     * constructor arguments overridden. The only place this class
     * constructs a modified copy of itself - every `where*`/`order*` method
     * above is a thin wrapper around this. Named-argument unpacking matches
     * each key in `$overrides` to the same-named constructor parameter, and
     * `get_object_vars($this)` supplies the rest unchanged, so this stays
     * correct without listing every property at each call site.
     */
    private function with(mixed ...$overrides): self
    {
        return new self(...[...get_object_vars($this), ...$overrides]);
    }

    private static function toImmutable(DateTimeInterface $at): DateTimeImmutable
    {
        return $at instanceof DateTimeImmutable
            ? $at
            : DateTimeImmutable::createFromInterface($at);
    }
}
