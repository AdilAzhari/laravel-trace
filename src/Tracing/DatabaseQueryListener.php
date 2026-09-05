<?php

declare(strict_types=1);

namespace AdilAzhari\LaravelTrace\Tracing;

use AdilAzhari\LaravelTrace\Contracts\Tracer;
use AdilAzhari\LaravelTrace\Span\SpanType;
use AdilAzhari\LaravelTrace\Storage\RecordsWithoutTracing;
use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Database\Events\QueryExecuted;

final readonly class DatabaseQueryListener
{
    public function __construct(
        private Tracer $tracer,
        private ConfigRepository $config,
    ) {}

    public function handle(QueryExecuted $event): void
    {
        // A database storage recorder writes traces/spans through queries of
        // its own; tracing those would recurse without end (see
        // RecordsWithoutTracing).
        if (RecordsWithoutTracing::active()) {
            return;
        }

        if (! (bool) $this->config->get(
            'laravel-trace.enabled',
            true,
        )) {
            return;
        }

        if (! (bool) $this->config->get(
            'laravel-trace.database.enabled',
            true,
        )) {
            return;
        }

        if ($this->tracer->context() === null) {
            return;
        }

        $scope = $this->tracer->span(
            name: 'database.query',
            type: SpanType::Database,
            attributes: [
                'db.connection' => $event->connectionName,
                'db.duration_ms' => $event->time,
                'db.sql' => $event->sql,
            ],
        );

        $scope->close();
    }
}
