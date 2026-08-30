<?php

declare(strict_types=1);

namespace AdilAzhari\LaravelTrace\Trace;

use Throwable;

final readonly class TraceError
{
    public function __construct(
        public string $type,
        public string $message,
        public ?string $file,
        public ?int $line,
    ) {}

    public static function fromThrowable(Throwable $exception): self
    {
        return new self(
            type: $exception::class,
            message: $exception->getMessage(),
            file: $exception->getFile(),
            line: $exception->getLine(),
        );
    }
}
