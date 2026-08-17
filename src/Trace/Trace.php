<?php

declare(strict_types=1);

namespace LaravelTrace\LaravelTrace\Trace;

use DateTimeImmutable;
use Throwable;

final readonly class Trace
{
    public function __construct(
        public TraceId $id,
        public string $name,
        public TraceStatus $status,
        public DateTimeImmutable $startedAt,
        public ?DateTimeImmutable $finishedAt = null,
        public ?TraceError $error = null,
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

    public function complete(DateTimeImmutable $finishedAt): self
    {
        return new self(
            id: $this->id,
            name: $this->name,
            status: TraceStatus::Completed,
            startedAt: $this->startedAt,
            finishedAt: $finishedAt,
            error: null,
        );
    }

    public function fail(
        Throwable $exception,
        DateTimeImmutable $finishedAt,
    ): self {
        return new self(
            id: $this->id,
            name: $this->name,
            status: TraceStatus::Failed,
            startedAt: $this->startedAt,
            finishedAt: $finishedAt,
            error: TraceError::fromThrowable($exception),
        );
    }
}
