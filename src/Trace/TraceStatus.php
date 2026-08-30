<?php

declare(strict_types=1);

namespace AdilAzhari\LaravelTrace\Trace;

enum TraceStatus: string
{
    case Running = 'running';
    case Completed = 'completed';
    case Failed = 'failed';
}
