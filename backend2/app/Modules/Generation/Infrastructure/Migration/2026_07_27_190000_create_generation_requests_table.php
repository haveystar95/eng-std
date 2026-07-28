<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('generation_requests', function (Blueprint $table) {
            $table->char('id', 26)->primary();
            $table->char('user_id', 26);
            $table->text('prompt');
            $table->text('normalized_prompt');
            $table->string('source_lang', 5);
            $table->string('target_lang', 5);
            $table->jsonb('levels');
            $table->integer('size');
            $table->text('prompt_version');
            $table->string('status', 16);                 // pending | running | succeeded | failed
            $table->text('model')->nullable();
            $table->integer('tokens_in')->nullable();
            $table->integer('tokens_out')->nullable();
            $table->decimal('cost_usd', 10, 6)->nullable();
            $table->char('collection_id', 26)->nullable(); // cross-module ref, intentionally no FK
            $table->text('error')->nullable();
            $table->timestampTz('created_at');
            $table->timestampTz('finished_at')->nullable();

            $table->index(['user_id', 'created_at'], 'generation_requests_user_idx');
        });

        DB::statement("ALTER TABLE generation_requests ADD CONSTRAINT generation_requests_status_check CHECK (status IN ('pending','running','succeeded','failed'))");
        // Future term-set cache lookup by (normalized prompt, langs, prompt version).
        DB::statement('CREATE INDEX generation_requests_cache_idx ON generation_requests (normalized_prompt, source_lang, target_lang, prompt_version)');
    }

    public function down(): void
    {
        Schema::dropIfExists('generation_requests');
    }
};
