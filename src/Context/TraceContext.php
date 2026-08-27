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

    /**
     * @return array{trace_id: string, span_id: string|null}
     */
    public function toArray(): array
    {
        return [
            'trace_id' => (string) $this->traceId,
            'span_id' => $this->spanId !== null
                ? (string) $this->spanId
                : null,
        ];
    }

    /**
     * @param  array{trace_id: string, span_id: string|null}  $context
     */
    public static function fromArray(array $context): self
    {
        return new self(
            traceId: new TraceId($context['trace_id']),
            spanId: isset($context['span_id'])
                ? new SpanId($context['span_id'])
                : null,
        );
    }
}
