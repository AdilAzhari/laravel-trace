<?php

declare(strict_types=1);

namespace AdilAzhari\LaravelTrace\Read;

use AdilAzhari\LaravelTrace\Contracts\SpanReader;
use AdilAzhari\LaravelTrace\Models\SpanRecord;
use AdilAzhari\LaravelTrace\Span\Span;
use AdilAzhari\LaravelTrace\Span\SpanId;
use AdilAzhari\LaravelTrace\Span\SpanStatus;
use AdilAzhari\LaravelTrace\Span\SpanType;
use AdilAzhari\LaravelTrace\Storage\RecordsWithoutTracing;
use AdilAzhari\LaravelTrace\Storage\SpanRecordMapper;
use AdilAzhari\LaravelTrace\Trace\TraceId;
use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

/**
 * Reads spans from the `laravel_trace_spans` table.
 *
 * Builds one Eloquent query per {@see SpanQuery} and hydrates rows into
 * {@see Span} objects through {@see SpanRecordMapper}; Eloquent models never
 * leave this class. Must produce the same result as
 * {@see InMemorySpanReader} for the same query. Every query runs inside
 * {@see RecordsWithoutTracing}.
 */
final readonly class DatabaseSpanReader implements SpanReader
{
    public function __construct(
        private ConfigRepository $config,
        private SpanRecordMapper $mapper,
    ) {}

    public function find(SpanId $id): ?Span
    {
        $record = RecordsWithoutTracing::run(
            fn (): ?SpanRecord => SpanRecord::on($this->connection())->find($id->value),
        );

        return $record instanceof SpanRecord
            ? $this->mapper->toDomain($record)
            : null;
    }

    public function get(SpanQuery $query): Collection
    {
        return RecordsWithoutTracing::run(fn (): Collection => $this->query($query)
            ->get()
            ->map(fn (SpanRecord $record): Span => $this->mapper->toDomain($record)));
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
        return RecordsWithoutTracing::run(fn (): LengthAwarePaginator => $this->query($query)
            ->paginate(perPage: $perPage, page: $page)
            ->through(fn (SpanRecord $record): Span => $this->mapper->toDomain($record)));
    }

    public function count(SpanQuery $query): int
    {
        return RecordsWithoutTracing::run(fn (): int => $this->query($query)->count());
    }

    /**
     * @return Builder<SpanRecord>
     */
    private function query(SpanQuery $query): Builder
    {
        $builder = SpanRecord::on($this->connection());

        if ($query->ids !== []) {
            $builder->whereIn('id', $query->ids);
        }

        if ($query->traceIds !== []) {
            $builder->whereIn('trace_id', $query->traceIds);
        }

        if ($query->parentIds !== []) {
            $builder->whereIn('parent_id', $query->parentIds);
        }

        if ($query->rootsOnly === true) {
            $builder->whereNull('parent_id');
        }

        if ($query->types !== []) {
            $builder->whereIn('type', array_map(
                static fn (SpanType $type): string => $type->value,
                $query->types,
            ));
        }

        if ($query->statuses !== []) {
            $builder->whereIn('status', array_map(
                static fn (SpanStatus $status): string => $status->value,
                $query->statuses,
            ));
        }

        if ($query->startedAfter !== null) {
            $builder->where('started_at', '>=', $query->startedAfter);
        }

        if ($query->startedBefore !== null) {
            $builder->where('started_at', '<', $query->startedBefore);
        }

        if ($query->minDurationMs !== null) {
            $builder->where('duration_ms', '>=', $query->minDurationMs);
        }

        if ($query->maxDurationMs !== null) {
            $builder->where('duration_ms', '<=', $query->maxDurationMs);
        }

        if ($query->hasError === true) {
            $builder->whereNotNull('error_type');
        }

        if ($query->hasError === false) {
            $builder->whereNull('error_type');
        }

        foreach ($query->attributes as $key => $value) {
            AttributeFilter::apply($builder, $key, $value);
        }

        return $builder
            ->orderBy($query->orderBy->field, $query->orderBy->direction)
            ->orderBy('id', 'desc');
    }

    private function connection(): ?string
    {
        /** @var string|null $connection */
        $connection = $this->config->get('laravel-trace.storage.database.connection');

        return $connection;
    }
}
