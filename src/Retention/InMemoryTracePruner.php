<?php

declare(strict_types=1);

namespace AdilAzhari\LaravelTrace\Retention;

use AdilAzhari\LaravelTrace\Contracts\TracePruner;
use AdilAzhari\LaravelTrace\Span\Span;
use AdilAzhari\LaravelTrace\Trace\Trace;
use AdilAzhari\LaravelTrace\Trace\TraceStatus;
use AdilAzhari\LaravelTrace\Tracing\InMemorySpanStore;
use AdilAzhari\LaravelTrace\Tracing\InMemoryTraceStore;

/**
 * Prunes the in-memory store the in-memory storage driver writes to.
 *
 * Operates directly against the shared {@see InMemoryTraceStore} /
 * {@see InMemorySpanStore} singletons - the same stores the in-memory
 * recorder and reader use - so the recorder and pruner see and mutate the
 * same data rather than a private copy.
 *
 * Eligibility mirrors {@see DatabaseTracePruner} exactly: a terminal trace
 * ({@see TraceStatus::Completed} or {@see TraceStatus::Failed}) started
 * strictly before the cutoff. A {@see TraceStatus::Running} trace is never
 * pruned, however old.
 */
final readonly class InMemoryTracePruner implements TracePruner
{
    public function __construct(
        private InMemoryTraceStore $traceStore,
        private InMemorySpanStore $spanStore,
    ) {}

    public function prune(PruneCriteria $criteria): PruneResult
    {
        $eligible = array_values(array_filter(
            $this->traceStore->all(),
            fn (Trace $trace): bool => $this->isEligible($trace, $criteria),
        ));

        // Deterministic order, mirroring the database pruner's `id`-ordered
        // keyset traversal, so chunking behaves the same way in both.
        usort($eligible, static fn (Trace $a, Trace $b): int => $a->id->value <=> $b->id->value);

        $tracesMatched = 0;
        $spansMatched = 0;
        $chunksProcessed = 0;

        foreach (array_chunk($eligible, $criteria->chunkSize) as $chunk) {
            $chunksProcessed++;

            $traceIds = array_map(
                static fn (Trace $trace): string => $trace->id->value,
                $chunk,
            );

            $spans = array_values(array_filter(
                $this->spanStore->all(),
                static fn (Span $span): bool => in_array($span->traceId->value, $traceIds, true),
            ));

            $tracesMatched += count($chunk);
            $spansMatched += count($spans);

            if ($criteria->dryRun) {
                continue;
            }

            foreach ($spans as $span) {
                $this->spanStore->forget($span->id->value);
            }

            foreach ($chunk as $trace) {
                $this->traceStore->forget($trace->id->value);
            }
        }

        return new PruneResult(
            tracesMatched: $tracesMatched,
            spansMatched: $spansMatched,
            chunksProcessed: $chunksProcessed,
            dryRun: $criteria->dryRun,
        );
    }

    private function isEligible(Trace $trace, PruneCriteria $criteria): bool
    {
        return in_array($trace->status, [TraceStatus::Completed, TraceStatus::Failed], true)
            && $trace->startedAt < $criteria->cutoff;
    }
}
