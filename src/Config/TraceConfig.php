<?php

declare(strict_types=1);

namespace LaravelTrace\LaravelTrace\Config;

final readonly class TraceConfig
{
    public function __construct(
        private bool $enabled,
        private bool $databaseEnabled,
    ) {}

    public function enabled(): bool
    {
        return $this->enabled;
    }

    public function databaseEnabled(): bool
    {
        return $this->databaseEnabled;
    }
}
