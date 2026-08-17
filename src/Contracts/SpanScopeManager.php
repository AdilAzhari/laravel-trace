<?php

declare(strict_types=1);

namespace LaravelTrace\LaravelTrace\Contracts;

use LaravelTrace\LaravelTrace\Context\TraceContext;

interface SpanScopeManager
{
    public function setContext(TraceContext $context): void;
}
