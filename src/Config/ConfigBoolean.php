<?php

declare(strict_types=1);

namespace AdilAzhari\LaravelTrace\Config;

/**
 * Safely coerces a config value that may have come from an environment
 * variable into a real boolean.
 *
 * A naive `(bool) $value` cast is unsafe here: `(bool) 'false'` is `true`,
 * since any non-empty string is truthy in PHP. Every operational toggle
 * this package reads from config is a candidate for `env()` wiring, which
 * hands back a string for values that are not one of Laravel's recognised
 * boolean keywords (`true`/`false`/`(true)`/`(false)`), so call sites must
 * not assume the config repository already holds a native bool.
 */
final class ConfigBoolean
{
    public static function resolve(mixed $value, bool $default): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if ($value === null || $value === '' || ! is_scalar($value)) {
            return $default;
        }

        $parsed = filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);

        return $parsed ?? $default;
    }
}
