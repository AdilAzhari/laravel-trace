<?php

declare(strict_types=1);

namespace AdilAzhari\LaravelTrace\Models;

use AdilAzhari\LaravelTrace\Trace\Trace;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * The persisted representation of a {@see Trace}.
 *
 * Internal to the package's database storage driver: not published, and not
 * intended for consumers to extend or query directly.
 *
 * @internal Database-storage implementation detail; not part of the
 *           package's public API.
 *
 * @property string $id
 * @property string $name
 * @property string $status
 * @property Carbon $started_at
 * @property Carbon|null $finished_at
 * @property float|null $duration_ms
 * @property string|null $error_type
 * @property string|null $error_message
 * @property string|null $error_file
 * @property int|null $error_line
 * @property array<string, string|int|float|bool|null>|null $attributes
 */
final class TraceRecord extends Model
{
    protected $table = 'laravel_traces';

    /**
     * Keep sub-second precision when the underlying driver stores datetimes
     * as text (SQLite). The migration declares microsecond columns; without
     * this format Eloquent would write and read them at second precision,
     * so a database-hydrated trace would not match its in-memory twin.
     */
    protected $dateFormat = 'Y-m-d H:i:s.u';

    protected $primaryKey = 'id';

    public $incrementing = false;

    protected $keyType = 'string';

    public $timestamps = false;

    protected $fillable = [
        'id',
        'name',
        'status',
        'started_at',
        'finished_at',
        'duration_ms',
        'error_type',
        'error_message',
        'error_file',
        'error_line',
        'attributes',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'started_at' => 'immutable_datetime',
            'finished_at' => 'immutable_datetime',
            'duration_ms' => 'float',
            'error_line' => 'integer',
            'attributes' => 'array',
        ];
    }
}
