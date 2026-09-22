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
 * result. Every `where*` / `order*` method returns a new instance; the
 * class is `readonly`, so the original can never be mutated, directly or
 * otherwise.
 */
final readonly class SpanQuery
{
    /** @var list<string> */
    public const array SORTABLE_FIELDS = ['started_at', 'finished_at', 'duration_ms', 'name', 'type'];

    /**
     * @param  list<string>  $ids
     * @param  list<string>  $traceIds
     * @param  list<string>  $parentIds
     * @param  list<SpanType>  $types
     * @param  list<SpanStatus>  $statuses
     * @param  bool|null  $rootsOnly  `null` = any, `true` = only spans with
     *                                no parent (trace roots).
     * @param  bool|null  $hasError  `null` = any, `true` = only spans that
     *                               failed with an error, `false` = only
     *                               spans without an error.
     * @param  array<string, string|int|float|bool|null>  $attributes
     */
    public function __construct(
        public array $ids = [],
        public array $traceIds = [],
        public array $parentIds = [],
        public array $types = [],
        public array $statuses = [],
        public ?bool $rootsOnly = null,
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

    public function whereId(SpanId|string ...$ids): self
    {
        return $this->with(ids: self::stringify(...$ids));
    }

    public function whereTrace(TraceId|string ...$traceIds): self
    {
        return $this->with(traceIds: self::stringify(...$traceIds));
    }

    public function whereParent(SpanId|string ...$parentIds): self
    {
        return $this->with(parentIds: self::stringify(...$parentIds));
    }

    public function whereType(SpanType ...$types): self
    {
        return $this->with(types: array_values($types));
    }

    public function whereStatus(SpanStatus ...$statuses): self
    {
        return $this->with(statuses: array_values($statuses));
    }

    public function onlyRoots(): self
    {
        return $this->with(rootsOnly: true);
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
                'Cannot order spans by [%s]. Allowed: %s.',
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
}
