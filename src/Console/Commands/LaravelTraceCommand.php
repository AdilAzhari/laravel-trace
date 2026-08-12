<?php

declare(strict_types=1);

namespace LaravelTrace\LaravelTrace\Console\Commands;

use Illuminate\Console\Command;

class LaravelTraceCommand extends Command
{
    /**
     * The command signature.
     */
    protected $signature = 'laravel-trace:placeholder';

    /**
     * The command description.
     */
    protected $description = 'Placeholder Artisan command shipped by the package laravel-trace.';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->line('LaravelTrace placeholder command executed.');

        return self::SUCCESS;
    }
}
