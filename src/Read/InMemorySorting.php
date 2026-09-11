<?php

declare(strict_types=1);

namespace AdilAzhari\LaravelTrace\Read;

use DateTimeInterface;

/**
 * Column comparison for the in-memory readers, kept aligned with how the
 * database sorts: `NULL`s come first ascending (SQLite / MySQL convention),
 * strings compare byte-wise, everything else by value.
 */
final class InMemorySorting
{
    public static function compare(mixed $a, mixed $b): int
    {
        if ($a === null && $b === null) {
            return 0;
        }

        if ($a === null) {
            return -1;
        }

        if ($b === null) {
            return 1;
        }

        if ($a instanceof DateTimeInterface && $b instanceof DateTimeInterface) {
            return $a <=> $b;
        }

        if (is_string($a) && is_string($b)) {
            return strcmp($a, $b);
        }

        return $a <=> $b;
    }
}
