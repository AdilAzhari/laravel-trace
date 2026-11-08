<?php

declare(strict_types=1);

namespace AdilAzhari\LaravelTrace\Tests\Fixtures\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use RuntimeException;

final class FailingJob implements ShouldQueue
{
    use Queueable;

    public function handle(): void
    {
        throw new RuntimeException('Job failed.');
    }
}
