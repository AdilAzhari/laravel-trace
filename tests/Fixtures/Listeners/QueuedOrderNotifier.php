<?php

declare(strict_types=1);

namespace LaravelTrace\LaravelTrace\Tests\Fixtures\Listeners;

use Illuminate\Contracts\Queue\ShouldQueue;
use LaravelTrace\LaravelTrace\Tests\Fixtures\Events\OrderCreated;

final class QueuedOrderNotifier implements ShouldQueue
{
    public function handle(OrderCreated $event): void
    {
        // Intentionally empty.
    }
}
