<?php

declare(strict_types=1);

namespace LaravelTrace\LaravelTrace\Tracing;

use Illuminate\Database\Events\QueryExecuted;
use LaravelTrace\LaravelTrace\Contracts\Tracer;
use LaravelTrace\LaravelTrace\Span\SpanType;

final readonly class DatabaseQueryListener
{
    public function __construct(
        private Tracer $tracer,
        private bool $enabled,
    ) {}

    public function handle(QueryExecuted $event): void
    {
        if (! $this->enabled) {
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
