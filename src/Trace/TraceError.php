<?php

declare(strict_types=1);

namespace LaravelTrace\LaravelTrace\Trace;

use Throwable;

final readonly class TraceError
{
    public function __construct(
        public string $class,
        public string $message,
    ) {}

    public static function fromThrowable(Throwable $exception): self
    {
        return new self(
            class: $exception::class,
            message: $exception->getMessage(),
        );
    }
}
