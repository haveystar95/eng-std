<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * "A human looked at this finding and decided." A separate table rather than a column on
 * `enrichment_findings`, because that table is append-only on purpose: it records what a RUN saw, and
 * a review is a different fact by a different actor at a different time. Stamping the original row
 * would erase the distinction between "the run never flagged this" and "the run flagged it and we
 * disagreed" — and that distinction is the whole value of keeping the log.
 *
 * The export reads findings LEFT JOIN acks and shows only the unacknowledged ones, so acknowledging
 * empties the worklist without losing the history.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('enrichment_finding_acks', function (Blueprint $table): void {
            $table->char('finding_id', 26)->primary();
            $table->text('note')->nullable();     // why it was kept, when that is worth saying
            $table->timestampTz('created_at');

            $table->foreign('finding_id')->references('id')->on('enrichment_findings')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('enrichment_finding_acks');
    }
};
