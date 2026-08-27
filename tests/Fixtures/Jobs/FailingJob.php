<?php

declare(strict_types=1);

namespace LaravelTrace\LaravelTrace\Tests\Fixtures\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use RuntimeException;

final class FailingJob implements ShouldQueue
{
    public function handle(): void
    {
        throw new RuntimeException('Job failed.');
    }
}
