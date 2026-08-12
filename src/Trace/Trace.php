<?php

declare(strict_types=1);

namespace LaravelTrace\LaravelTrace\Trace;

use DateTimeImmutable;

final readonly class Trace
{
    public function __construct(
        public TraceId $id,
        public string $name,
        public TraceStatus $status,
        public DateTimeImmutable $startedAt,
        public ?DateTimeImmutable $finishedAt = null,
    ) {}

    public static function start(string $name): self
    {
        return new self(
            id: TraceId::generate(),
            name: $name,
            status: TraceStatus::Running,
            startedAt: new DateTimeImmutable,
        );
    }
}
