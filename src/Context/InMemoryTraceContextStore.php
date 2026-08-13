<?php

declare(strict_types=1);

namespace LaravelTrace\LaravelTrace\Context;

use LaravelTrace\LaravelTrace\Contracts\TraceContextStore;

final class InMemoryTraceContextStore implements TraceContextStore
{
    private ?TraceContext $context = null;

    public function get(): ?TraceContext
    {
        return $this->context;
    }

    public function set(TraceContext $context): void
    {
        $this->context = $context;
    }

    public function clear(): void
    {
        $this->context = null;
    }
}
