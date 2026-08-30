<?php

declare(strict_types=1);

namespace AdilAzhari\LaravelTrace\Tests\Fixtures\Listeners;

use AdilAzhari\LaravelTrace\Tests\Fixtures\Events\OrderCreated;

final class SendOrderConfirmation
{
    public function handle(OrderCreated $event): void
    {
        // Intentionally empty.
    }
}
