<?php

declare(strict_types=1);

namespace LaravelTrace\LaravelTrace\Tracing;

use LaravelTrace\LaravelTrace\Context\TraceContext;
use LaravelTrace\LaravelTrace\Contracts\TraceContextStore;
use LaravelTrace\LaravelTrace\Contracts\Tracer as TracerContract;
use LaravelTrace\LaravelTrace\Span\Span;
use LaravelTrace\LaravelTrace\Span\SpanType;
use LaravelTrace\LaravelTrace\Trace\Trace;
use LogicException;

final readonly class Tracer implements TracerContract
{
    public function __construct(
        private TraceContextStore $contextStore,
    ) {
    }

    public function start(string $name): Trace
    {
        return Trace::start($name);
    }

    public function startSpan(
        string $name,
        SpanType $type,
    ): Span {
        $context = $this->context();

        if ($context === null) {
            throw new LogicException(
                'Cannot start a span without an active trace.',
            );
        }

        $span = Span::start(
            traceId: $context->traceId,
            name: $name,
            type: $type,
            parentId: $context->spanId,
        );

        $this->setContext(
            $context->withSpan($span->id),
        );

        return $span;
    }

    public function context(): ?TraceContext
    {
        return $this->contextStore->get();
    }

    public function setContext(TraceContext $context): void
    {
        $this->contextStore->set($context);
    }

    public function clearContext(): void
    {
        $this->contextStore->clear();
    }
}
