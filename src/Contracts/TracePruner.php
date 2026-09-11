<?php

declare(strict_types=1);

namespace AdilAzhari\LaravelTrace\Contracts;

use AdilAzhari\LaravelTrace\Retention\PruneCriteria;
use AdilAzhari\LaravelTrace\Retention\PruneResult;

/**
 * Removes terminal traces (and their spans) older than a cutoff.
 *
 * A dedicated destructive lifecycle port, deliberately separate from
 * {@see TraceReader} / {@see SpanReader} (read) and {@see TraceRecorder} /
 * {@see SpanRecorder} (write): those contracts stay untouched by retention.
 * Which implementation the container resolves follows
 * `laravel-trace.storage.driver`, the same switch the recorder/reader
 * contracts honour.
 */
interface TracePruner
{
    public function prune(PruneCriteria $criteria): PruneResult;
}
