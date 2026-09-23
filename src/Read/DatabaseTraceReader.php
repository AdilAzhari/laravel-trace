<?php

declare(strict_types=1);

namespace AdilAzhari\LaravelTrace\Read;

use AdilAzhari\LaravelTrace\Contracts\TraceReader;
use AdilAzhari\LaravelTrace\Models\TraceRecord;
use AdilAzhari\LaravelTrace\Storage\RecordsWithoutTracing;
use AdilAzhari\LaravelTrace\Storage\TraceRecordMapper;
use AdilAzhari\LaravelTrace\Trace\Trace;
use AdilAzhari\LaravelTrace\Trace\TraceId;
use AdilAzhari\LaravelTrace\Trace\TraceStatus;
use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

/**
 * Reads traces from the `laravel_traces` table.
 *
 * Builds one Eloquent query per {@see TraceQuery} and hydrates rows into
 * {@see Trace} objects through {@see TraceRecordMapper}; Eloquent models
 * never leave this class. Must produce the same result as
 * {@see InMemoryTraceReader} for the same query.
 *
 * Every query runs inside {@see RecordsWithoutTracing} so that reading the
 * trace store from within a traced request or job does not emit
 * `database` spans for the read itself.
 */
final readonly class DatabaseTraceReader implements TraceReader
{
    public function __construct(
        private ConfigRepository $config,
        private TraceRecordMapper $mapper,
    ) {}

    public function find(TraceId $id): ?Trace
    {
        $record = RecordsWithoutTracing::run(
            fn (): ?TraceRecord => TraceRecord::on($this->connection())->find($id->value),
        );

        return $record instanceof TraceRecord
            ? $this->mapper->toDomain($record)
            : null;
    }

    public function get(TraceQuery $query): Collection
    {
        return RecordsWithoutTracing::run(fn (): Collection => $this->query($query)
            ->get()
            ->map(fn (TraceRecord $record): Trace => $this->mapper->toDomain($record)));
    }

    public function paginate(TraceQuery $query, int $perPage = 25, int $page = 1): LengthAwarePaginator
    {
        return RecordsWithoutTracing::run(fn (): LengthAwarePaginator => $this->query($query)
            ->paginate(perPage: $perPage, page: $page)
            ->through(fn (TraceRecord $record): Trace => $this->mapper->toDomain($record)));
    }

    public function count(TraceQuery $query): int
    {
        return RecordsWithoutTracing::run(fn (): int => $this->query($query)->count());
    }

    /**
     * @return Builder<TraceRecord>
     */
    private function query(TraceQuery $query): Builder
    {
        $builder = TraceRecord::on($this->connection());

        if ($query->ids !== []) {
            $builder->whereIn('id', $query->ids);
        }

        if ($query->names !== []) {
            $builder->whereIn('name', $query->names);
        }

        if ($query->statuses !== []) {
            $builder->whereIn('status', array_map(
                static fn (TraceStatus $status): string => $status->value,
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
