<?php

declare(strict_types=1);

namespace AdilAzhari\LaravelTrace\Context;

use AdilAzhari\LaravelTrace\Span\SpanId;
use AdilAzhari\LaravelTrace\Trace\TraceId;
use Illuminate\Support\Str;

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

    /**
     * Serialize the context into a single HTTP header value.
     *
     * Format: "<trace-id>" or "<trace-id>-<span-id>".
     */
    public function toHeader(): string
    {
        if ($this->spanId === null) {
            return (string) $this->traceId;
        }

        return (string) $this->traceId.'-'.$this->spanId;
    }

    /**
     * Parse a context from an inbound HTTP header value.
     *
     * Returns null when the value is absent or malformed so the caller can
     * fall back to starting a fresh local trace.
     */
    public static function fromHeader(string $header): ?self
    {
        $segments = explode('-', trim($header));

        if (count($segments) > 2) {
            return null;
        }

        foreach ($segments as $segment) {
            if (! Str::isUlid($segment)) {
                return null;
            }
        }

        return new self(
            traceId: new TraceId($segments[0]),
            spanId: isset($segments[1]) ? new SpanId($segments[1]) : null,
        );
    }
}
