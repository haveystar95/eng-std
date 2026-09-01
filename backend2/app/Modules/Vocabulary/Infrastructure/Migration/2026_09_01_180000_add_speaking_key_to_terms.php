<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * `terms.speaking_key` — WHAT A SPOKEN LINE IS ACTUALLY JUDGED ON.
     *
     * A plan line was graded by coverage of the WHOLE sentence: say 70 % of «Yes, I'm looking for a
     * place to rent for long-term living in a new country» or it is wrong. On the owner's screen
     * (01.09) that produced «Не то» with seven of fifteen words underlined, three sittings in a row,
     * under a caption promising the card was checking the WORD and not the pronunciation. The card
     * teaches one thing — the piece that stands in the frame's hole — and it graded fourteen.
     *
     * So the key is the piece. Three answers, in order, and the column stores the winner because
     * only the code that writes the DAY can see all three:
     *
     *   1. the frame's filler — «long-term living in a new country». The card's whole subject.
     *   2. no frame (a formula): the day's own word or connector that stands inside the line, if one
     *      does. «Sorry, could you repeat that?» has none; a formula built around a day card has.
     *   3. neither: NULL, and the trainer asks for the whole line, saying so out loud.
     *
     * Nullable, and null is the honest state for every term that never came from a plan day.
     *
     * The backfill takes rule 1 only. Rule 2 needs the day's other cards, which is a join through
     * collections this migration has no business making, and the days already written are two.
     */
    public function up(): void
    {
        Schema::table('terms', function (Blueprint $table): void {
            $table->text('speaking_key')->nullable()->after('filler');
        });

        DB::statement("UPDATE terms SET speaking_key = filler WHERE filler IS NOT NULL AND filler <> ''");
    }

    public function down(): void
    {
        Schema::table('terms', function (Blueprint $table): void {
            $table->dropColumn('speaking_key');
        });
    }
};
