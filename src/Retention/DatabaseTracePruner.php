<?php

declare(strict_types=1);

namespace AdilAzhari\LaravelTrace\Retention;

use AdilAzhari\LaravelTrace\Contracts\TracePruner;
use AdilAzhari\LaravelTrace\Models\SpanRecord;
use AdilAzhari\LaravelTrace\Models\TraceRecord;
use AdilAzhari\LaravelTrace\Storage\RecordsWithoutTracing;
use AdilAzhari\LaravelTrace\Trace\TraceStatus;
use Illuminate\Contracts\Config\Repository as ConfigRepository;

/**
 * Prunes terminal traces (and their spans) from `laravel_traces` /
 * `laravel_trace_spans`.
 *
 * Walks a deterministic `id`-ordered keyset cursor rather than repeatedly
 * re-selecting the first page - required for dry-run, which must advance
 * through the whole matching set without ever deleting anything, and kept
 * as the one traversal strategy for both paths rather than special-casing
 * dry-run. The cutoff is captured once in {@see PruneCriteria} and never
 * recomputed mid-run.
 *
 * Deletes spans for a chunk's trace IDs before deleting the traces
 * themselves - explicitly, not by relying on the `trace_id` foreign key's
 * `cascadeOnDelete()`. That cascade only fires when the connection
 * enforces foreign keys, which for SQLite depends on the consuming
 * application's own connection configuration; this pruner's correctness
 * does not depend on it. The two deletes are not wrapped in a shared
 * transaction: if the process dies between them, the next run finds the
 * same trace IDs with their spans already gone and simply finishes
 * deleting the traces - safe to re-run by construction.
 *
 * Every query runs inside {@see RecordsWithoutTracing} so pruning cannot
 * generate `database.query` spans for its own SQL. Failures are never
 * swallowed: unlike a trace write, a failed maintenance run must be
 * visible to the operator.
 */
final readonly class DatabaseTracePruner implements TracePruner
{
    /**
     * @var list<TraceStatus>
     */
    private const array ELIGIBLE_STATUSES = [TraceStatus::Completed, TraceStatus::Failed];

    public function __construct(
        private ConfigRepository $config,
    ) {}

    public function prune(PruneCriteria $criteria): PruneResult
    {
        return RecordsWithoutTracing::run(fn (): PruneResult => $this->pruneWithoutTracing($criteria));
    }

    private function pruneWithoutTracing(PruneCriteria $criteria): PruneResult
    {
        $tracesMatched = 0;
        $spansMatched = 0;
        $chunksProcessed = 0;
        $lastId = null;

        while (true) {
            $ids = $this->nextChunk($criteria, $lastId);

            if ($ids === []) {
                break;
            }

            $chunksProcessed++;
            $lastId = end($ids);

            $spanCount = SpanRecord::on($this->connection())
                ->whereIn('trace_id', $ids)
                ->count();

            $tracesMatched += count($ids);
            $spansMatched += $spanCount;

            if ($criteria->dryRun) {
                continue;
            }

            SpanRecord::on($this->connection())->whereIn('trace_id', $ids)->delete();
            TraceRecord::on($this->connection())->whereIn('id', $ids)->delete();
        }

        return new PruneResult(
            tracesMatched: $tracesMatched,
            spansMatched: $spansMatched,
            chunksProcessed: $chunksProcessed,
            dryRun: $criteria->dryRun,
        );
    }

    /**
     * @return list<string>
     */
    private function nextChunk(PruneCriteria $criteria, ?string $afterId): array
    {
        $query = TraceRecord::on($this->connection())
            ->whereIn('status', array_map(
                static fn (TraceStatus $status): string => $status->value,
                self::ELIGIBLE_STATUSES,
            ))
            // Bound explicitly rather than relying on the query grammar's
            // default datetime format, which drops microseconds; the
            // cutoff must be compared at the same precision it was stored.
            ->where('started_at', '<', $criteria->cutoff->format('Y-m-d H:i:s.u'));

        if ($afterId !== null) {
            $query->where('id', '>', $afterId);
        }

        /** @var list<string> $ids */
        $ids = $query->orderBy('id')
            ->limit($criteria->chunkSize)
            ->pluck('id')
            ->all();

        return $ids;
    }

    private function connection(): ?string
    {
        /** @var string|null $connection */
        $connection = $this->config->get('laravel-trace.storage.database.connection');

        return $connection;
    }
}
