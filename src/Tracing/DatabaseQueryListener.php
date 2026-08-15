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
    ) {}

    public function handle(QueryExecuted $event): void
    {
        $context = $this->tracer->context();

        if ($context === null) {
            return;
        }

        $scope = $this->tracer->span(
            name: 'database.query',
            type: SpanType::Database,
        );

        $scope->close();
    }
}
