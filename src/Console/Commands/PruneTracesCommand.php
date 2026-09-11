<?php

declare(strict_types=1);

namespace AdilAzhari\LaravelTrace\Console\Commands;

use AdilAzhari\LaravelTrace\Contracts\TracePruner;
use AdilAzhari\LaravelTrace\Retention\PruneCriteria;
use AdilAzhari\LaravelTrace\Retention\PruneResult;
use DateTimeImmutable;
use Exception;
use Illuminate\Console\Command;
use Illuminate\Contracts\Config\Repository as ConfigRepository;

/**
 * Deletes completed/failed traces (and their spans) older than a cutoff.
 *
 * Thin: all eligibility, chunking and deletion logic lives in the
 * {@see TracePruner} the container resolves for the active storage driver.
 * This command only turns options/config into a {@see PruneCriteria},
 * confirms, and reports the {@see PruneResult}.
 */
class PruneTracesCommand extends Command
{
    /**
     * The command signature.
     */
    protected $signature = 'laravel-trace:prune
        {--days= : Prune traces started more than this many days ago, overriding the configured retention.days}
        {--before= : Prune traces started before this date/time, overriding --days}
        {--chunk= : Traces deleted per batch, overriding the configured retention.chunk_size}
        {--dry-run : Report what would be pruned without deleting anything}
        {--force : Skip the confirmation prompt}';

    /**
     * The command description.
     */
    protected $description = 'Delete completed/failed traces (and their spans) older than the retention cutoff.';

    /**
     * Execute the console command.
     */
    public function handle(ConfigRepository $config, TracePruner $pruner): int
    {
        if ($this->option('days') !== null && $this->option('before') !== null) {
            $this->components->error('Use either --days or --before, not both.');

            return self::INVALID;
        }

        $explicitOverride = $this->option('days') !== null || $this->option('before') !== null;
        $enabled = (bool) $config->get('laravel-trace.storage.retention.enabled', false);

        if (! $enabled && ! $explicitOverride) {
            $this->components->info(
                'laravel-trace.storage.retention.enabled is false and no --days/--before override '.
                'was given. Nothing to prune.',
            );

            return self::SUCCESS;
        }

        $cutoff = $this->resolveCutoff($config);

        if ($cutoff === null) {
            return self::INVALID;
        }

        $chunkSize = $this->resolveChunkSize($config);

        if ($chunkSize === null) {
            return self::INVALID;
        }

        $dryRun = (bool) $this->option('dry-run');
        $driver = (string) $config->get('laravel-trace.storage.driver', 'memory');

        if (! $dryRun && ! $this->option('force') && ! $this->confirmDeletion($driver, $cutoff)) {
            $this->components->warn('Aborted; nothing was pruned.');

            return self::FAILURE;
        }

        $result = $pruner->prune(new PruneCriteria(
            cutoff: $cutoff,
            chunkSize: $chunkSize,
            dryRun: $dryRun,
        ));

        $this->reportResult($driver, $cutoff, $result);

        if ($driver === 'memory') {
            $this->components->warn(
                'The memory driver only holds data recorded by the process that recorded it - it does '.
                'not persist between artisan invocations. The counts above reflect that this process '.
                'has nothing of its own to prune, not a database query that happened to match zero rows.',
            );
        }

        return self::SUCCESS;
    }

    private function resolveCutoff(ConfigRepository $config): ?DateTimeImmutable
    {
        $before = $this->option('before');

        if (is_string($before)) {
            return $this->resolveExplicitCutoff($before);
        }

        return $this->resolveCutoffFromDays($config);
    }

    private function resolveExplicitCutoff(string $before): ?DateTimeImmutable
    {
        try {
            $cutoff = new DateTimeImmutable($before);
        } catch (Exception) {
            $this->components->error(sprintf(
                'Invalid --before value [%s]: could not be parsed as a date/time.',
                $before,
            ));

            return null;
        }

        if ($cutoff > new DateTimeImmutable) {
            $this->components->error('--before must not be in the future.');

            return null;
        }

        return $cutoff;
    }

    private function resolveCutoffFromDays(ConfigRepository $config): ?DateTimeImmutable
    {
        /** @var mixed $days */
        $days = $this->option('days') ?? $config->get('laravel-trace.storage.retention.days', 7);

        if (! $this->isPositiveInteger($days)) {
            $this->components->error(sprintf(
                'Invalid retention days [%s]: must be a positive integer.',
                is_scalar($days) ? (string) $days : get_debug_type($days),
            ));

            return null;
        }

        return (new DateTimeImmutable)->modify(sprintf('-%d days', (int) $days));
    }

    private function resolveChunkSize(ConfigRepository $config): ?int
    {
        /** @var mixed $chunk */
        $chunk = $this->option('chunk') ?? $config->get('laravel-trace.storage.retention.chunk_size', 500);

        if (! $this->isPositiveInteger($chunk)) {
            $this->components->error(sprintf(
                'Invalid chunk size [%s]: must be a positive integer.',
                is_scalar($chunk) ? (string) $chunk : get_debug_type($chunk),
            ));

            return null;
        }

        return (int) $chunk;
    }

    private function isPositiveInteger(mixed $value): bool
    {
        if (! is_numeric($value)) {
            return false;
        }

        return (float) $value === floor((float) $value) && (int) $value > 0;
    }

    private function confirmDeletion(string $driver, DateTimeImmutable $cutoff): bool
    {
        return $this->confirm(sprintf(
            'This will permanently delete completed/failed traces (and their spans) started before '.
            '%s, using the "%s" storage driver. Continue?',
            $cutoff->format('Y-m-d H:i:s'),
            $driver,
        ));
    }

    private function reportResult(string $driver, DateTimeImmutable $cutoff, PruneResult $result): void
    {
        $verb = $result->dryRun ? 'Would delete' : 'Deleted';

        $this->newLine();
        $this->components->twoColumnDetail('Driver', $driver);
        $this->components->twoColumnDetail('Mode', $result->dryRun ? 'Dry run' : 'Delete');
        $this->components->twoColumnDetail('Cutoff', $cutoff->format('Y-m-d H:i:s.u'));
        $this->components->twoColumnDetail('Chunks processed', (string) $result->chunksProcessed);
        $this->components->twoColumnDetail(sprintf('%s traces', $verb), (string) $result->tracesMatched);
        $this->components->twoColumnDetail(sprintf('%s spans', $verb), (string) $result->spansMatched);
        $this->newLine();
    }
}
