<?php

declare(strict_types=1);

namespace AdilAzhari\LaravelTrace\Contracts;

use AdilAzhari\LaravelTrace\Trace\Trace;

interface TraceRecorder
{
    public function record(Trace $trace): void;
}
