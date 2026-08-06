<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // One row per "New example" the user asks for. Counts toward the daily generation quota
        // (alongside generation_requests) and records the spend.
        Schema::create('example_regenerations', function (Blueprint $table): void {
            $table->char('id', 26)->primary();
            $table->char('user_id', 26);
            $table->char('term_id', 26);
            $table->text('model');
            $table->integer('tokens_in')->nullable();
            $table->integer('tokens_out')->nullable();
            $table->decimal('cost_usd', 10, 6)->nullable();
            $table->timestampTz('created_at');

            // The quota's access path: a user's regenerations in a day.
            $table->index(['user_id', 'created_at'], 'example_regenerations_user_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('example_regenerations');
    }
};
