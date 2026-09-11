<?php

declare(strict_types=1);

namespace AdilAzhari\LaravelTrace\Storage;

use AdilAzhari\LaravelTrace\Models\TraceRecord;
use AdilAzhari\LaravelTrace\Trace\Trace;
use AdilAzhari\LaravelTrace\Trace\TraceError;
use AdilAzhari\LaravelTrace\Trace\TraceId;
use AdilAzhari\LaravelTrace\Trace\TraceStatus;

/**
 * Translates between the {@see Trace} domain object and its persisted
 * {@see TraceRecord} row.
 *
 * The single place the trace column layout is known: the database recorder
 * writes through {@see self::toAttributes()} and the database reader
 * hydrates through {@see self::toDomain()}, so the two directions cannot
 * drift. Keeps {@see Trace} itself free of any persistence awareness.
 */
final class TraceRecordMapper
{
    /**
     * Column values for a trace row, excluding its `id` (which the recorder
     * uses as the {@see TraceRecord::updateOrCreate()} match key).
     *
     * @return array<string, mixed>
     */
    public function toAttributes(Trace $trace): array
    {
        return [
            'name' => $trace->name,
            'status' => $trace->status->value,
            'started_at' => $trace->startedAt,
            'finished_at' => $trace->finishedAt,
            'duration_ms' => $trace->durationMs(),
            'error_type' => $trace->error?->type,
            'error_message' => $trace->error?->message,
            'error_file' => $trace->error?->file,
            'error_line' => $trace->error?->line,
            'attributes' => $trace->attributes,
        ];
    }

    public function toDomain(TraceRecord $record): Trace
    {
        return new Trace(
            id: new TraceId($record->id),
            name: $record->name,
            status: TraceStatus::from($record->status),
            startedAt: $record->started_at->toDateTimeImmutable(),
            finishedAt: $record->finished_at?->toDateTimeImmutable(),
            error: $this->toError($record),
            attributes: $record->attributes ?? [],
        );
    }

    private function toError(TraceRecord $record): ?TraceError
    {
        if ($record->error_type === null) {
            return null;
        }

        return new TraceError(
            type: $record->error_type,
            message: $record->error_message ?? '',
            file: $record->error_file,
            line: $record->error_line,
        );
    }
}
