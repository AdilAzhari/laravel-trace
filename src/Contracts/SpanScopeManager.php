<?php

declare(strict_types=1);

namespace AdilAzhari\LaravelTrace\Contracts;

use AdilAzhari\LaravelTrace\Context\TraceContext;

interface SpanScopeManager
{
    public function setContext(TraceContext $context): void;
}
