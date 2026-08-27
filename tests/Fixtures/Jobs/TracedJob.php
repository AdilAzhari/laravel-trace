<?php

declare(strict_types=1);

namespace LaravelTrace\LaravelTrace\Tests\Fixtures\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;

final class TracedJob implements ShouldQueue
{
    public function handle(): void
    {
        // Intentionally empty.
    }
}
