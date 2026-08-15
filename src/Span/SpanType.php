<?php

declare(strict_types=1);

namespace LaravelTrace\LaravelTrace\Span;

enum SpanType: string
{
    case Http = 'http';
    case Action = 'action';
    case Event = 'event';
    case Listener = 'listener';
    case Job = 'job';
    case Database = 'database';
}
