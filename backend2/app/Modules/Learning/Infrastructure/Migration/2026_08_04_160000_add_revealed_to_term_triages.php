<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Whether the learner flipped the triage card (revealing the translation/example) before deciding.
 * A `known` verdict reached AFTER peeking is a weaker signal — recognition of a hint, not recall —
 * so it feeds the verification planner as a risk factor. Nullable + additive: old swipes and any
 * client that doesn't send it stay null (neutral).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('term_triages', function (Blueprint $table): void {
            $table->boolean('revealed')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('term_triages', function (Blueprint $table): void {
            $table->dropColumn('revealed');
        });
    }
};
