<?php

declare(strict_types=1);

namespace AdilAzhari\LaravelTrace\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @see \AdilAzhari\LaravelTrace\LaravelTrace
 */
class LaravelTrace extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \AdilAzhari\LaravelTrace\LaravelTrace::class;
    }
}
