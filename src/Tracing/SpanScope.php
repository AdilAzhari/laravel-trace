<?php

declare(strict_types=1);

namespace LaravelTrace\LaravelTrace\Tracing;

use LaravelTrace\LaravelTrace\Context\TraceContext;
use LaravelTrace\LaravelTrace\Span\Span;
use Throwable;

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

    public function close(): Span
    {
        if ($this->closed) {
            return $this->span;
        }

        $this->closed = true;

        $completed = $this->tracer->complete(
            $this->span,
        );

        $this->tracer->setContext(
            $this->previousContext,
        );

        return $completed;
    }

    public function fail(Throwable $exception): Span
    {
        if ($this->closed) {
            return $this->span;
        }

        $this->closed = true;

        $failed = $this->tracer->fail(
            $this->span,
            $exception,
        );

        $this->tracer->setContext(
            $this->previousContext,
        );

        return $failed;
    }
}
