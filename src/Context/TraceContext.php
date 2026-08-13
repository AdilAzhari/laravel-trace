<?php

declare(strict_types=1);

namespace LaravelTrace\LaravelTrace\Context;

use LaravelTrace\LaravelTrace\Span\SpanId;
use LaravelTrace\LaravelTrace\Trace\TraceId;

final readonly class TraceContext
{
    public function __construct(
        public TraceId $traceId,
        public ?SpanId $spanId = null,
    ) {}

    public function withSpan(SpanId $spanId): self
    {
        return new self(
            traceId: $this->traceId,
            spanId: $spanId,
        );
    }
}
