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
        /** @var array<string, string|int|float|bool|null> */
        public array $attributes = [],
    ) {}

    /**
     * @param  array<string, string|int|float|bool|null>  $attributes
     */
    public static function start(string $name, array $attributes = []): self
    {
        return new self(
            id: TraceId::generate(),
            name: $name,
            status: TraceStatus::Running,
            startedAt: new DateTimeImmutable,
            attributes: $attributes,
        );
    }

    /**
     * @param  array<string, string|int|float|bool|null>  $attributes
     */
    public function complete(DateTimeImmutable $finishedAt, array $attributes = []): self
    {
        return new self(
            id: $this->id,
            name: $this->name,
            status: TraceStatus::Completed,
            startedAt: $this->startedAt,
            finishedAt: $finishedAt,
            error: null,
            attributes: $attributes,
        );
    }

    /**
     * @param  array<string, string|int|float|bool|null>  $attributes
     */
    public function fail(
        Throwable $exception,
        DateTimeImmutable $finishedAt,
        array $attributes = [],
    ): self {
        return new self(
            id: $this->id,
            name: $this->name,
            status: TraceStatus::Failed,
            startedAt: $this->startedAt,
            finishedAt: $finishedAt,
            error: TraceError::fromThrowable($exception),
            attributes: $attributes,
        );
    }

    /**
     * @param  array<string, string|int|float|bool|null>  $attributes
     */
    public function withAttributes(array $attributes): self
    {
        return new self(
            id: $this->id,
            name: $this->name,
            status: $this->status,
            startedAt: $this->startedAt,
            finishedAt: $this->finishedAt,
            error: $this->error,
            attributes: [
                ...$this->attributes,
                ...$attributes,
            ],
        );
    }
}
