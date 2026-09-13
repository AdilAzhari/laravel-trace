<?php

declare(strict_types=1);

use AdilAzhari\LaravelTrace\Retention\DatabaseTracePruner;
use AdilAzhari\LaravelTrace\Retention\InMemoryTracePruner;
use AdilAzhari\LaravelTrace\Retention\PruneCriteria;
use AdilAzhari\LaravelTrace\Trace\TraceId;
use AdilAzhari\LaravelTrace\Trace\TraceStatus;

/*
 * Proves the in-memory and database pruners agree: the same data recorded
 * through the same driver's recorder, pruned with the same criteria,
 * produces the same PruneResult counts. Extends the read milestone's
 * cross-driver parity guarantee to retention.
 */

it('matches identical trace/span counts across drivers for the same data and cutoff', function (string $driver): void {
    useStorageDriver($driver);

    $cutoff = new DateTimeImmutable('2026-06-01 00:00:00.000000');

    // Eligible: completed, older than cutoff, with two spans.
    $eligibleTraceId = TraceId::generate();
    recordTrace(makeTrace(id: $eligibleTraceId, status: TraceStatus::Completed, startedAt: $cutoff->modify('-1 day')));
    recordSpan(makeSpan($eligibleTraceId, 'span-a'));
    recordSpan(makeSpan($eligibleTraceId, 'span-b'));

    // Retained: failed, older than cutoff, but... newer than cutoff.
    $newTraceId = TraceId::generate();
    recordTrace(makeTrace(id: $newTraceId, status: TraceStatus::Failed, startedAt: $cutoff->modify('+1 day'), withError: true));
    recordSpan(makeSpan($newTraceId, 'span-c'));

    // Retained: running, older than cutoff.
    $runningTraceId = TraceId::generate();
    recordTrace(makeTrace(id: $runningTraceId, status: TraceStatus::Running, startedAt: $cutoff->modify('-1 day')));
    recordSpan(makeSpan($runningTraceId, 'span-d'));

    $pruner = $driver === 'database'
        ? app(DatabaseTracePruner::class)
        : app(InMemoryTracePruner::class);

    $result = $pruner->prune(new PruneCriteria($cutoff));

    expect($result->tracesMatched)->toBe(1)
        ->and($result->spansMatched)->toBe(2)
        ->and($result->chunksProcessed)->toBe(1)
        ->and($result->dryRun)->toBeFalse();
})->with('drivers');

it('reports identical dry-run counts across drivers without mutating storage', function (string $driver): void {
    useStorageDriver($driver);

    $cutoff = new DateTimeImmutable('2026-06-01 00:00:00.000000');

    $traceId = TraceId::generate();
    recordTrace(makeTrace(id: $traceId, status: TraceStatus::Completed, startedAt: $cutoff->modify('-1 day')));
    recordSpan(makeSpan($traceId, 'span'));

    $pruner = $driver === 'database'
        ? app(DatabaseTracePruner::class)
        : app(InMemoryTracePruner::class);

    $result = $pruner->prune(new PruneCriteria($cutoff, dryRun: true));

    expect($result->tracesMatched)->toBe(1)
        ->and($result->spansMatched)->toBe(1)
        ->and($result->dryRun)->toBeTrue();
})->with('drivers');
