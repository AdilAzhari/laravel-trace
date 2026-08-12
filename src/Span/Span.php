<?php

declare(strict_types=1);

namespace LaravelTrace\LaravelTrace\Span;

use DateTimeImmutable;
use LaravelTrace\LaravelTrace\Trace\TraceId;

final readonly class Span
{
    public function __construct(
        public SpanId $id,
        public TraceId $traceId,
        public ?SpanId $parentId,
        public string $name,
        public SpanType $type,
        public SpanStatus $status,
        public DateTimeImmutable $startedAt,
        public ?DateTimeImmutable $finishedAt = null,
    ) {}

    public static function start(
        TraceId $traceId,
        string $name,
        SpanType $type,
        ?SpanId $parentId = null,
    ): self {
        return new self(
            id: SpanId::generate(),
            traceId: $traceId,
            parentId: $parentId,
            name: $name,
            type: $type,
            status: SpanStatus::Running,
            startedAt: new DateTimeImmutable,
        );
    }
}
