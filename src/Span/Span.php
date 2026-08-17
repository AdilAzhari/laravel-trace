<?php

declare(strict_types=1);

namespace LaravelTrace\LaravelTrace\Span;

use DateTimeImmutable;
use LaravelTrace\LaravelTrace\Trace\TraceId;
use Throwable;

final readonly class Span
{
    public function __construct(
        public SpanId $id,
        public TraceId $traceId,
        public ?SpanId $parentId,
        public string $name,
        public SpanType $type,

        /** @var array<string, scalar|null> */
        public array $attributes,

        public SpanStatus $status,
        public DateTimeImmutable $startedAt,
        public ?DateTimeImmutable $finishedAt = null,
        public ?SpanError $error = null,
    ) {}

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
}
