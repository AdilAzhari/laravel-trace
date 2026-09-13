<?php

declare(strict_types=1);

namespace AdilAzhari\LaravelTrace\Retention;

use AdilAzhari\LaravelTrace\Contracts\TracePruner;

/**
 * The outcome of a {@see TracePruner::prune()} run.
 *
 * Deliberately describes records *matched* by the criteria, not records
 * physically deleted: a dry run matches exactly the same traces and spans a
 * real run would, it just never issues the delete. `tracesMatched` /
 * `spansMatched` therefore mean the same thing whether `dryRun` is true or
 * false - only the caller's phrasing of the result differs ("deleted" vs.
 * "would delete").
 */
final readonly class PruneResult
{
    public function __construct(
        public int $tracesMatched,
        public int $spansMatched,
        public int $chunksProcessed,
        public bool $dryRun = false,
    ) {}
}
