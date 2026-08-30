<?php

declare(strict_types=1);

namespace AdilAzhari\LaravelTrace\Trace;

use Illuminate\Support\Str;
use Stringable;

final readonly class TraceId implements Stringable
{
    public function __construct(
        public string $value,
    ) {}

    public static function generate(): self
    {
        return new self(
            value: (string) Str::ulid(),
        );
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
