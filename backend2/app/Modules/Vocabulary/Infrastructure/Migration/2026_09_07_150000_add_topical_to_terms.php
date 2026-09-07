<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * `terms.topical` — A TOPICAL WORD OF THE SITUATION, not a piece of the scene's lines (наряд
     * DAY-FIX-3, Ч.2.1; P2 v0.8).
     *
     * Three days of a fresh plan were walked in ten minutes — the material was thin. The day now
     * carries, beside the words that stand in its lines, 6–10 topical nouns and set phrases of the
     * encounter that the lines do not happen to use («рецепт», «страховка» at the doctor's). The
     * mark is what tells the two apart downstream: the day screen captions a topical word «по
     * теме» so the learner understands why it never sounds in the dialogue, and the sitting planner
     * trims topical words first when the material sitting runs over its ceiling.
     *
     * A boolean with a default and NOT NULL: «not topical» is the honest state of every term that
     * never came from a plan day and of every plan word written before v0.8 (no backfill — the old
     * days are not migrated, правило наряда). No index: it is read with the card, never queried
     * across terms.
     */
    public function up(): void
    {
        Schema::table('terms', function (Blueprint $table): void {
            $table->boolean('topical')->default(false)->after('speaking_keys');
        });
    }

    public function down(): void
    {
        Schema::table('terms', function (Blueprint $table): void {
            $table->dropColumn('topical');
        });
    }
};
