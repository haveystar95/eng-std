<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // One row per LLM enrichment of a user term — the spend record ("cost is written").
        Schema::create('term_enrichments', function (Blueprint $table): void {
            $table->char('id', 26)->primary();
            $table->char('term_id', 26);
            $table->text('model');
            $table->integer('tokens_in')->nullable();
            $table->integer('tokens_out')->nullable();
            $table->decimal('cost_usd', 10, 6)->nullable();
            $table->timestampTz('created_at');

            $table->index('term_id', 'term_enrichments_term_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('term_enrichments');
    }
};
