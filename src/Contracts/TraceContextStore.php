<?php

declare(strict_types=1);

namespace LaravelTrace\LaravelTrace\Contracts;

use LaravelTrace\LaravelTrace\Context\TraceContext;

interface TraceContextStore
{
    public function get(): ?TraceContext;

    public function set(TraceContext $context): void;

    public function clear(): void;
}
