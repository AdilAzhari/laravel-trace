<?php

declare(strict_types=1);

namespace LaravelTrace\LaravelTrace\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @see \LaravelTrace\LaravelTrace\LaravelTrace
 */
class LaravelTrace extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \LaravelTrace\LaravelTrace\LaravelTrace::class;
    }
}
