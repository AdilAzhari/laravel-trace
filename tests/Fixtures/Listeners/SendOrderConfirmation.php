<?php

declare(strict_types=1);

namespace LaravelTrace\LaravelTrace\Tests\Fixtures\Listeners;

use LaravelTrace\LaravelTrace\Tests\Fixtures\Events\OrderCreated;

final class SendOrderConfirmation
{
    public function handle(OrderCreated $event): void
    {
        // Intentionally empty.
    }
}
