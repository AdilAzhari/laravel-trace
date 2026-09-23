<?php

declare(strict_types=1);

use AdilAzhari\LaravelTrace\Retention\PruneResult;

it('holds matched counts, chunks processed and the dry-run flag', function (): void {
    $result = new PruneResult(
        tracesMatched: 12,
        spansMatched: 40,
        chunksProcessed: 3,
        dryRun: true,
    );

    expect($result->tracesMatched)->toBe(12)
        ->and($result->spansMatched)->toBe(40)
        ->and($result->chunksProcessed)->toBe(3)
        ->and($result->dryRun)->toBeTrue();
});

it('defaults to a real (non-dry) run', function (): void {
    $result = new PruneResult(tracesMatched: 0, spansMatched: 0, chunksProcessed: 0);

    expect($result->dryRun)->toBeFalse();
});
