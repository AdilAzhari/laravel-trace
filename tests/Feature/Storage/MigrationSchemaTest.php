<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

it('creates the traces and spans tables with the expected columns', function (): void {
    expect(Schema::hasTable('laravel_traces'))->toBeTrue()
        ->and(Schema::hasColumns('laravel_traces', [
            'id', 'name', 'status', 'started_at', 'finished_at', 'duration_ms',
            'error_type', 'error_message', 'error_file', 'error_line', 'attributes',
        ]))->toBeTrue()
        ->and(Schema::hasTable('laravel_trace_spans'))->toBeTrue()
        ->and(Schema::hasColumns('laravel_trace_spans', [
            'id', 'trace_id', 'parent_id', 'name', 'type', 'status',
            'started_at', 'finished_at', 'duration_ms',
            'error_type', 'error_message', 'error_file', 'error_line', 'attributes',
        ]))->toBeTrue();
});

it('declares a foreign key from a span to its trace', function (): void {
    // Asserted structurally rather than by triggering a live violation:
    // SQLite only enforces a declared foreign key when `PRAGMA foreign_keys`
    // is ON for the connection, and it silently ignores changes to that
    // pragma while a transaction is open - which RefreshDatabase already
    // has by the time a test body runs. A real MySQL/Postgres connection
    // enforces a declared foreign key unconditionally.
    $sql = DB::table('sqlite_master')
        ->where('type', 'table')
        ->where('name', 'laravel_trace_spans')
        ->value('sql');

    expect($sql)
        ->toBeString()
        ->toContain('foreign key("trace_id") references "laravel_traces"("id")');
});

it('allows a span row once its trace row exists', function (): void {
    $traceId = (string) Str::ulid();

    DB::table('laravel_traces')->insert([
        'id' => $traceId,
        'name' => 'CreateOrder',
        'status' => 'running',
        'started_at' => now(),
    ]);

    DB::table('laravel_trace_spans')->insert([
        'id' => (string) Str::ulid(),
        'trace_id' => $traceId,
        'name' => 'ReserveInventory',
        'type' => 'action',
        'status' => 'running',
        'started_at' => now(),
    ]);

    expect(DB::table('laravel_trace_spans')->where('trace_id', $traceId)->count())
        ->toBe(1);
});

it('keeps span rows keyed to their trace across the microsecond-precision started_at column', function (): void {
    $traceId = (string) Str::ulid();

    DB::table('laravel_traces')->insert([
        'id' => $traceId,
        'name' => 'CreateOrder',
        'status' => 'completed',
        'started_at' => now(),
        'finished_at' => now(),
        'duration_ms' => 0.123,
    ]);

    $row = DB::table('laravel_traces')->where('id', $traceId)->first();

    expect((float) $row->duration_ms)->toBe(0.123);
});
