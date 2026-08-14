<?php

declare(strict_types=1);

namespace LaravelTrace\LaravelTrace\Tracing;

use LaravelTrace\LaravelTrace\Context\TraceContext;
use LaravelTrace\LaravelTrace\Span\Span;

final class SpanScope
{
    private bool $closed = false;

    public function __construct(
        private readonly Span $span,
        private readonly TraceContext $previousContext,
        private readonly Tracer $tracer,
    ) {}

    public function span(): Span
    {
        return $this->span;
    }

    public function close(): void
    {
        if ($this->closed) {
            return;
        }

        $this->closed = true;

        $this->tracer->setContext(
            $this->previousContext,
        );
    }
}
