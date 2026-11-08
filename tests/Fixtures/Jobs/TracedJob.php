<?php

declare(strict_types=1);

namespace AdilAzhari\LaravelTrace\Tests\Fixtures\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;

final class TracedJob implements ShouldQueue
{
    use Queueable;

    public function handle(): void
    {
        // Intentionally empty.
    }
}
