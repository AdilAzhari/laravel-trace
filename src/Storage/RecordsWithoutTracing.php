<?php

declare(strict_types=1);

namespace AdilAzhari\LaravelTrace\Storage;

/**
 * Marks the database queries a storage recorder issues while persisting a
 * trace or span as "not to be traced". Without this, a database storage
 * driver would record its own writes as `database` spans, which in turn
 * record another write, recursing without end.
 */
final class RecordsWithoutTracing
{
    private static bool $active = false;

    /**
     * @template TReturn
     *
     * @param  callable(): TReturn  $callback
     * @return TReturn
     */
    public static function run(callable $callback): mixed
    {
        $previous = self::$active;
        self::$active = true;

        try {
            return $callback();
        } finally {
            self::$active = $previous;
        }
    }

    public static function active(): bool
    {
        return self::$active;
    }
}
