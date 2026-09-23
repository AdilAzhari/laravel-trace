<?php

declare(strict_types=1);

namespace AdilAzhari\LaravelTrace\Read;

use AdilAzhari\LaravelTrace\Contracts\TraceReader;
use AdilAzhari\LaravelTrace\Storage\StorageDrivenTraceRecorder;
use AdilAzhari\LaravelTrace\Trace\Trace;
use AdilAzhari\LaravelTrace\Trace\TraceId;
use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use InvalidArgumentException;

/**
 * Delegates to the {@see TraceReader} selected by
 * `laravel-trace.storage.driver`, read live on every call.
 *
 * The read-side mirror of
 * {@see StorageDrivenTraceRecorder}: the
 * object bound to the `TraceReader` contract is resolved once, and the
 * driver may still change afterwards (a test override, a runtime config
 * change), so the concrete reader is chosen per call rather than baked in.
 */
final readonly class StorageDrivenTraceReader implements TraceReader
{
    public function __construct(
        private Application $app,
        private ConfigRepository $config,
    ) {}

    public function find(TraceId $id): ?Trace
    {
        return $this->driver()->find($id);
    }

    public function get(TraceQuery $query): Collection
    {
        return $this->driver()->get($query);
    }

    public function paginate(TraceQuery $query, int $perPage = 25, int $page = 1): LengthAwarePaginator
    {
        return $this->driver()->paginate($query, $perPage, $page);
    }

    public function count(TraceQuery $query): int
    {
        return $this->driver()->count($query);
    }

    private function driver(): TraceReader
    {
        $driver = $this->config->get('laravel-trace.storage.driver', 'memory');

        return match ($driver) {
            'memory' => $this->app->make(InMemoryTraceReader::class),
            'database' => $this->app->make(DatabaseTraceReader::class),
            default => throw new InvalidArgumentException(sprintf(
                'Unknown laravel-trace storage driver [%s]. Expected "memory" or "database".',
                is_scalar($driver) ? (string) $driver : get_debug_type($driver),
            )),
        };
    }
}
