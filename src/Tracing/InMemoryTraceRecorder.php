<?php

declare(strict_types=1);

namespace LaravelTrace\LaravelTrace\Tracing;

use LaravelTrace\LaravelTrace\Contracts\TraceRecorder;
use LaravelTrace\LaravelTrace\Trace\Trace;

final class InMemoryTraceRecorder implements TraceRecorder
{
    /**
     * @var list<Trace>
     */
    private array $traces = [];

    public function record(Trace $trace): void
    {
        $this->traces[] = $trace;
    }

    /**
     * @return list<Trace>
     */
    public function all(): array
    {
        return $this->traces;
    }
}
