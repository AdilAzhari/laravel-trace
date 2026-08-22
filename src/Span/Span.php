<?php

declare(strict_types=1);

namespace LaravelTrace\LaravelTrace\Span;

use DateTimeImmutable;
use LaravelTrace\LaravelTrace\Trace\TraceId;
use Throwable;

final readonly class Span
{
    /**
     * @param  array<string, string|int|float|bool|null>  $attributes
     */
    public function __construct(
        public SpanId $id,
        public TraceId $traceId,
        public ?SpanId $parentId,
        public string $name,
        public SpanType $type,
        public array $attributes,
        public SpanStatus $status,
        public DateTimeImmutable $startedAt,
        public ?DateTimeImmutable $finishedAt = null,
        public ?SpanError $error = null,
    ) {}

    /**
     * @param  array<string, string|int|float|bool|null>  $attributes
     */
    public static function start(
        TraceId $traceId,
        string $name,
        SpanType $type,
        ?SpanId $parentId = null,
        array $attributes = [],
        ?DateTimeImmutable $startedAt = null,
    ): self {
        return new self(
            id: SpanId::generate(),
            traceId: $traceId,
            parentId: $parentId,
            name: $name,
            type: $type,
            attributes: $attributes,
            status: SpanStatus::Running,
            startedAt: $startedAt ?? new DateTimeImmutable,
            error: null,
        );
    }

    public function completeSpan(DateTimeImmutable $finishedAt): self
    {
        return new self(
            id: $this->id,
            traceId: $this->traceId,
            parentId: $this->parentId,
            name: $this->name,
            type: $this->type,
            attributes: $this->attributes,
            status: SpanStatus::Completed,
            startedAt: $this->startedAt,
            finishedAt: $finishedAt,
            error: null,
        );
    }

    public function failSpan(Throwable $exception, DateTimeImmutable $finishedAt): self
    {
        return new self(
            id: $this->id,
            traceId: $this->traceId,
            parentId: $this->parentId,
            name: $this->name,
            type: $this->type,
            attributes: $this->attributes,
            status: SpanStatus::Failed,
            startedAt: $this->startedAt,
            finishedAt: $finishedAt,
            error: SpanError::fromThrowable($exception),
        );
    }

    /**
     * @param  array<string, string|int|float|bool|null>  $attributes
     */
    public static function completed(
        TraceId $traceId,
        string $name,
        SpanType $type,
        DateTimeImmutable $startedAt,
        DateTimeImmutable $finishedAt,
        ?SpanId $parentId = null,
        array $attributes = [],
    ): self {
        return new self(
            id: SpanId::generate(),
            traceId: $traceId,
            parentId: $parentId,
            name: $name,
            type: $type,
            attributes: $attributes,
            status: SpanStatus::Completed,
            startedAt: $startedAt,
            finishedAt: $finishedAt,
            error: null,
        );
    }

    /**
     * @param  array<string, string|int|float|bool|null>  $attributes
     */
    public function withAttributes(array $attributes): self
    {
        return new self(
            id: $this->id,
            traceId: $this->traceId,
            parentId: $this->parentId,
            name: $this->name,
            type: $this->type,
            attributes: [
                ...$this->attributes,
                ...$attributes,
            ],
            status: $this->status,
            startedAt: $this->startedAt,
            finishedAt: $this->finishedAt,
            error: $this->error,
        );
    }

    public function durationMs(): ?float
    {
        if ($this->finishedAt === null) {
            return null;
        }

        return ($this->finishedAt->format('U.u') - $this->startedAt->format('U.u')) * 1000;
    }
}
