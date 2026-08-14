<?php

declare(strict_types=1);

namespace LaravelTrace\LaravelTrace\Tracing;

use LaravelTrace\LaravelTrace\Contracts\SpanRecorder;
use LaravelTrace\LaravelTrace\Span\Span;

final class InMemorySpanRecorder implements SpanRecorder
{
    /**
     * @var list<Span>
     */
    private array $spans = [];

    public function record(Span $span): void
    {
        $this->spans[] = $span;
    }

    /**
     * @return list<Span>
     */
    public function all(): array
    {
        return $this->spans;
    }
}
