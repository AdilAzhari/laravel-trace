<?php

declare(strict_types=1);

namespace AdilAzhari\LaravelTrace\Tracing;

use AdilAzhari\LaravelTrace\Config\ConfigBoolean;
use AdilAzhari\LaravelTrace\Contracts\Tracer;
use AdilAzhari\LaravelTrace\Span\SpanType;
use AdilAzhari\LaravelTrace\Storage\RecordsWithoutTracing;
use DateTimeImmutable;
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

        if (! ConfigBoolean::resolve($this->config->get(
            'laravel-trace.enabled',
            true,
        ), true)) {
            return;
        }

        if (! ConfigBoolean::resolve($this->config->get(
            'laravel-trace.instrumentation.database.enabled',
            true,
        ), true)) {
            return;
        }

        if ($this->tracer->context() === null) {
            return;
        }

        // QueryExecuted fires only after the query has already finished, so
        // the span is backdated by the query's own measured time; without
        // this, startedAt and finishedAt would both land at "now" and the
        // span's duration would reflect the near-zero cost of recording it
        // rather than the real query time (which is otherwise only visible
        // via the db.duration_ms attribute below).
        $scope = $this->tracer->span(
            name: 'database.query',
            type: SpanType::Database,
            attributes: [
                'db.connection' => $event->connectionName,
                'db.duration_ms' => $event->time,
                'db.sql' => $event->sql,
            ],
            startedAt: $this->startedAt($event),
        );

        $scope->close();
    }

    private function startedAt(QueryExecuted $event): DateTimeImmutable
    {
        $elapsedMicroseconds = (int) round($event->time * 1000);

        return (new DateTimeImmutable)->modify(
            sprintf('-%d microseconds', $elapsedMicroseconds),
        );
    }
}
