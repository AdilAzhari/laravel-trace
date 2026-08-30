<?php

declare(strict_types=1);

namespace AdilAzhari\LaravelTrace\Tests\Fixtures\Events;

final readonly class OrderShipped
{
    public function __construct(
        public int $orderId,
    ) {}
}
