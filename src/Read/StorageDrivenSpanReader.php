<?php

declare(strict_types=1);

namespace AdilAzhari\LaravelTrace\Read;

use AdilAzhari\LaravelTrace\Contracts\SpanReader;
use AdilAzhari\LaravelTrace\Span\Span;
use AdilAzhari\LaravelTrace\Span\SpanId;
use AdilAzhari\LaravelTrace\Storage\StorageDrivenSpanRecorder;
use AdilAzhari\LaravelTrace\Trace\TraceId;
use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use InvalidArgumentException;

/**
 * Delegates to the {@see SpanReader} selected by
 * `laravel-trace.storage.driver`, read live on every call. The read-side
 * mirror of
 * {@see StorageDrivenSpanRecorder}.
 */
final readonly class StorageDrivenSpanReader implements SpanReader
{
    public function __construct(
        private Application $app,
        private ConfigRepository $config,
    ) {}

    public function find(SpanId $id): ?Span
    {
        return $this->driver()->find($id);
    }

    public function get(SpanQuery $query): Collection
    {
        return $this->driver()->get($query);
    }

    public function forTrace(TraceId $traceId): Collection
    {
        return $this->driver()->forTrace($traceId);
    }

    public function children(SpanId $parentId): Collection
    {
        return $this->driver()->children($parentId);
    }

    public function paginate(SpanQuery $query, int $perPage = 25, int $page = 1): LengthAwarePaginator
    {
        return $this->driver()->paginate($query, $perPage, $page);
    }

    public function count(SpanQuery $query): int
    {
        return $this->driver()->count($query);
    }

    private function driver(): SpanReader
    {
        $driver = $this->config->get('laravel-trace.storage.driver', 'memory');

        return match ($driver) {
            'memory' => $this->app->make(InMemorySpanReader::class),
            'database' => $this->app->make(DatabaseSpanReader::class),
            default => throw new InvalidArgumentException(sprintf(
                'Unknown laravel-trace storage driver [%s]. Expected "memory" or "database".',
                is_scalar($driver) ? (string) $driver : get_debug_type($driver),
            )),
        };
    }
}
