<?php

declare(strict_types=1);

/**
 * Runs `$callback` with the given environment variables set, having rebuilt
 * the application first so `config/laravel-trace.php`'s `env()` calls
 * observe them, then restores the previous values and rebuilds again.
 *
 * Env-var-driven config is read once, when the config file is merged during
 * the container's boot - to observe a different `.env` value for a single
 * assertion, the env var must be set *before* the app is rebuilt. Always
 * restored in `finally` since the suite runs in random order.
 *
 * @param  array<string, string>  $vars
 */
function withEnv(array $vars, Closure $callback): void
{
    $previous = [];

    foreach ($vars as $name => $value) {
        $previous[$name] = getenv($name);

        putenv(sprintf('%s=%s', $name, $value));
        $_ENV[$name] = $value;
        $_SERVER[$name] = $value;
    }

    try {
        test()->refreshApplication();
        $callback();
    } finally {
        foreach ($previous as $name => $previousValue) {
            if ($previousValue === false) {
                putenv($name);
                unset($_ENV[$name], $_SERVER[$name]);

                continue;
            }

            putenv(sprintf('%s=%s', $name, $previousValue));
            $_ENV[$name] = $previousValue;
            $_SERVER[$name] = $previousValue;
        }

        test()->refreshApplication();
    }
}
