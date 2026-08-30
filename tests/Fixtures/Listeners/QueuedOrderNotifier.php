<?php

declare(strict_types=1);

namespace AdilAzhari\LaravelTrace\Tests\Fixtures\Listeners;

use AdilAzhari\LaravelTrace\Tests\Fixtures\Events\OrderCreated;
use Illuminate\Contracts\Queue\ShouldQueue;

final class QueuedOrderNotifier implements ShouldQueue
{
    public function handle(OrderCreated $event): void
    {
        // Intentionally empty.
    }
}
