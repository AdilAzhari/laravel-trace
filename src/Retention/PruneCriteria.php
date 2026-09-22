<?php

declare(strict_types=1);

namespace AdilAzhari\LaravelTrace\Retention;

use AdilAzhari\LaravelTrace\Contracts\TracePruner;
use AdilAzhari\LaravelTrace\Read\TraceQuery;
use DateTimeImmutable;
use InvalidArgumentException;

/**
 * An immutable description of what a {@see TracePruner} should remove:
 * everything eligible strictly before `cutoff`, walked in batches of
 * `chunkSize`, either for real or as a `dryRun` that reports what would be
 * removed without changing anything.
 *
 * Deliberately narrower than a read query: retention has no name/status/
 * attribute filters, only an age boundary. Not reused with
 * {@see TraceQuery} on purpose.
 */
final readonly class PruneCriteria
{
    /**
     * @var positive-int
     */
    public int $chunkSize;

    /**
     * @throws InvalidArgumentException if $chunkSize is not greater than zero
     */
    public function __construct(
        public DateTimeImmutable $cutoff,
        int $chunkSize = 500,
        public bool $dryRun = false,
    ) {
        if ($chunkSize <= 0) {
            throw new InvalidArgumentException(sprintf(
                'PruneCriteria chunk size must be greater than zero, got [%d].',
                $chunkSize,
            ));
        }

        $this->chunkSize = $chunkSize;
    }
}
