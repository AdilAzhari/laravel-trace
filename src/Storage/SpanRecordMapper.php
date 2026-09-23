<?php

declare(strict_types=1);

namespace AdilAzhari\LaravelTrace\Storage;

use AdilAzhari\LaravelTrace\Models\SpanRecord;
use AdilAzhari\LaravelTrace\Span\Span;
use AdilAzhari\LaravelTrace\Span\SpanError;
use AdilAzhari\LaravelTrace\Span\SpanId;
use AdilAzhari\LaravelTrace\Span\SpanStatus;
use AdilAzhari\LaravelTrace\Span\SpanType;
use AdilAzhari\LaravelTrace\Trace\TraceId;

/**
 * Translates between the {@see Span} domain object and its persisted
 * {@see SpanRecord} row.
 *
 * The single place the span column layout is known: the database recorder
 * writes through {@see self::toAttributes()} and the database reader
 * hydrates through {@see self::toDomain()}, so the two directions cannot
 * drift. Keeps {@see Span} itself free of any persistence awareness.
 *
 * @internal Database-storage implementation detail; not part of the
 *           package's public API.
 */
final class SpanRecordMapper
{
    /**
     * Column values for a span row, excluding its `id` (which the recorder
     * uses as the {@see SpanRecord::updateOrCreate()} match key).
     *
     * @return array<string, mixed>
     */
    public function toAttributes(Span $span): array
    {
        return [
            'trace_id' => $span->traceId->value,
            'parent_id' => $span->parentId?->value,
            'name' => $span->name,
            'type' => $span->type->value,
            'status' => $span->status->value,
            'started_at' => $span->startedAt,
            'finished_at' => $span->finishedAt,
            'duration_ms' => $span->durationMs(),
            'error_type' => $span->error?->type,
            'error_message' => $span->error?->message,
            'error_file' => $span->error?->file,
            'error_line' => $span->error?->line,
            'attributes' => $span->attributes,
        ];
    }

    public function toDomain(SpanRecord $record): Span
    {
        return new Span(
            id: new SpanId($record->id),
            traceId: new TraceId($record->trace_id),
            parentId: $record->parent_id !== null ? new SpanId($record->parent_id) : null,
            name: $record->name,
            type: SpanType::from($record->type),
            attributes: $record->attributes ?? [],
            status: SpanStatus::from($record->status),
            startedAt: $record->started_at->toDateTimeImmutable(),
            finishedAt: $record->finished_at?->toDateTimeImmutable(),
            error: $this->toError($record),
        );
    }

    private function toError(SpanRecord $record): ?SpanError
    {
        if ($record->error_type === null) {
            return null;
        }

        return new SpanError(
            type: $record->error_type,
            message: $record->error_message ?? '',
            file: $record->error_file,
            line: $record->error_line,
        );
    }
}
