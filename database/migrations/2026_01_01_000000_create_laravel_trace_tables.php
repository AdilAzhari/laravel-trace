<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('laravel_traces', function (Blueprint $table): void {
            $table->string('id', 26)->primary();
            $table->string('name');
            $this->extracted($table);

            $table->index(['status', 'started_at']);
        });

        Schema::create('laravel_trace_spans', function (Blueprint $table): void {
            $table->string('id', 26)->primary();
            $table->string('trace_id', 26);
            $table->string('parent_id', 26)->nullable();

            $table->string('name');
            $table->string('type');
            $this->extracted($table);

            $table->foreign('trace_id')
                ->references('id')
                ->on('laravel_traces')
                ->cascadeOnDelete();

            $table->index(['trace_id', 'started_at']);
            $table->index('parent_id');
            $table->index(['status', 'started_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('laravel_trace_spans');
        Schema::dropIfExists('laravel_traces');
    }

    public function extracted(Blueprint $table): void
    {
        $table->string('status');

        $table->dateTime('started_at', 6);
        $table->dateTime('finished_at', 6)->nullable();
        $table->double('duration_ms')->nullable();

        $table->string('error_type')->nullable();
        $table->text('error_message')->nullable();
        $table->text('error_file')->nullable();
        $table->unsignedInteger('error_line')->nullable();

        $table->json('attributes')->nullable();
    }
};
