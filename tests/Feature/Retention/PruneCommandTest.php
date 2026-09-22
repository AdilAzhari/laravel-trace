<?php

declare(strict_types=1);

use AdilAzhari\LaravelTrace\Models\TraceRecord;
use AdilAzhari\LaravelTrace\Trace\TraceStatus;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;

beforeEach(function (): void {
    config()->set('laravel-trace.storage.driver', 'database');
    config()->set('laravel-trace.storage.retention.enabled', false);
});

it('no-ops when retention is disabled and no override is given', function (): void {
    recordTrace(makeTrace(status: TraceStatus::Completed, startedAt: new DateTimeImmutable('-30 days')));

    $this->artisan('laravel-trace:prune')
        ->assertExitCode(Command::SUCCESS);

    expect(TraceRecord::query()->count())->toBe(1);
});

it('treats a string "false" the same as boolean false for retention.enabled', function (): void {
    // `(bool) 'false'` is `true` in plain PHP - a value that could come
    // from `.env` via `LARAVEL_TRACE_RETENTION_ENABLED` must still leave
    // retention disabled when read as the literal string "false".
    recordTrace(makeTrace(status: TraceStatus::Completed, startedAt: new DateTimeImmutable('-30 days')));

    config()->set('laravel-trace.storage.retention.enabled', 'false');

    $this->artisan('laravel-trace:prune')
        ->assertExitCode(Command::SUCCESS);

    expect(TraceRecord::query()->count())->toBe(1);
});

it('overrides disabled retention with an explicit --days', function (): void {
    recordTrace(makeTrace(status: TraceStatus::Completed, startedAt: new DateTimeImmutable('-30 days')));

    $this->artisan('laravel-trace:prune', ['--days' => 7, '--force' => true])
        ->assertExitCode(Command::SUCCESS);

    expect(TraceRecord::query()->count())->toBe(0);
});

it('overrides disabled retention with an explicit --before', function (): void {
    recordTrace(makeTrace(status: TraceStatus::Completed, startedAt: new DateTimeImmutable('-30 days')));

    $this->artisan('laravel-trace:prune', [
        '--before' => (new DateTimeImmutable('-1 day'))->format('Y-m-d H:i:s'),
        '--force' => true,
    ])->assertExitCode(Command::SUCCESS);

    expect(TraceRecord::query()->count())->toBe(0);
});

it('rejects --days combined with --before', function (): void {
    $this->artisan('laravel-trace:prune', [
        '--days' => 7,
        '--before' => '2026-01-01',
        '--force' => true,
    ])->assertExitCode(Command::INVALID);
});

it('rejects a non-positive --days', function (): void {
    $this->artisan('laravel-trace:prune', ['--days' => 0, '--force' => true])
        ->assertExitCode(Command::INVALID);

    $this->artisan('laravel-trace:prune', ['--days' => -3, '--force' => true])
        ->assertExitCode(Command::INVALID);

    $this->artisan('laravel-trace:prune', ['--days' => 'nope', '--force' => true])
        ->assertExitCode(Command::INVALID);
});

it('rejects a non-positive --chunk', function (): void {
    $this->artisan('laravel-trace:prune', ['--days' => 7, '--chunk' => 0, '--force' => true])
        ->assertExitCode(Command::INVALID);
});

it('rejects a --before value in the future', function (): void {
    $this->artisan('laravel-trace:prune', [
        '--before' => (new DateTimeImmutable('+1 day'))->format('Y-m-d H:i:s'),
        '--force' => true,
    ])->assertExitCode(Command::INVALID);
});

it('rejects an unparseable --before value', function (): void {
    $this->artisan('laravel-trace:prune', ['--before' => 'not-a-date', '--force' => true])
        ->assertExitCode(Command::INVALID);
});

it('performs no deletion and shows no confirmation prompt in dry-run mode', function (): void {
    recordTrace(makeTrace(status: TraceStatus::Completed, startedAt: new DateTimeImmutable('-30 days')));

    $this->artisan('laravel-trace:prune', ['--days' => 7, '--dry-run' => true])
        ->assertExitCode(Command::SUCCESS);

    expect(TraceRecord::query()->count())->toBe(1);
});

it('skips the confirmation prompt with --force', function (): void {
    recordTrace(makeTrace(status: TraceStatus::Completed, startedAt: new DateTimeImmutable('-30 days')));

    // No expectsConfirmation() set up: if the command tried to prompt in a
    // non-interactive test run without --force, this would fail/hang.
    $this->artisan('laravel-trace:prune', ['--days' => 7, '--force' => true])
        ->assertExitCode(Command::SUCCESS);

    expect(TraceRecord::query()->count())->toBe(0);
});

it('aborts without deleting when the confirmation is declined', function (): void {
    // --before gives a fixed, deterministic cutoff so the confirmation
    // text (which embeds it) can be matched exactly without any risk of a
    // clock tick between building the expectation and the command running.
    recordTrace(makeTrace(status: TraceStatus::Completed, startedAt: new DateTimeImmutable('2020-01-01 00:00:00')));

    $this->artisan('laravel-trace:prune', ['--before' => '2025-01-01 00:00:00'])
        ->expectsConfirmation(
            'This will permanently delete completed/failed traces (and their spans) started before '.
            '2025-01-01 00:00:00, using the "database" storage driver. Continue?',
            'no',
        )
        ->assertExitCode(Command::FAILURE);

    expect(TraceRecord::query()->count())->toBe(1);
});

it('explains itself and succeeds when the memory driver has nothing to prune', function (): void {
    config()->set('laravel-trace.storage.driver', 'memory');

    $this->artisan('laravel-trace:prune', ['--days' => 7, '--force' => true])
        ->expectsOutputToContain('memory driver')
        ->assertExitCode(Command::SUCCESS);
});

// `expectsOutputToContain()` intercepts writeln()/write() calls on a mocked
// OutputStyle; the "Illuminate\Console\View\Components" factory that
// twoColumnDetail() belongs to renders through a different path that mock
// does not capture. Asserting on that summary content therefore goes
// through Artisan::call() + Artisan::output(), which reflects exactly what
// a real invocation prints.

it('prints a summary with the driver, cutoff, mode and matched counts', function (): void {
    recordTrace(makeTrace(status: TraceStatus::Completed, startedAt: new DateTimeImmutable('-30 days')));

    $exitCode = Artisan::call('laravel-trace:prune', ['--days' => 7, '--dry-run' => true]);
    $output = Artisan::output();

    expect($exitCode)->toBe(Command::SUCCESS)
        ->and($output)->toContain('Driver')
        ->and($output)->toContain('database')
        ->and($output)->toContain('Mode')
        ->and($output)->toContain('Dry run')
        ->and($output)->toContain('Cutoff')
        ->and($output)->toContain('Would delete traces')
        ->and($output)->toContain('Would delete spans');
});

it('phrases a real run as deleted rather than would-delete', function (): void {
    recordTrace(makeTrace(status: TraceStatus::Completed, startedAt: new DateTimeImmutable('-30 days')));

    $exitCode = Artisan::call('laravel-trace:prune', ['--days' => 7, '--force' => true]);
    $output = Artisan::output();

    expect($exitCode)->toBe(Command::SUCCESS)
        ->and($output)->toContain('Deleted traces')
        ->and($output)->toContain('Deleted spans');
});
