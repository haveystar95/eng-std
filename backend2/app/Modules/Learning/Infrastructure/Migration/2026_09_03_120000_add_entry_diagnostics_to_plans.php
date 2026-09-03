<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * THE ENTRY'S TWO NEW FACTS — the listening check, and a plan that has no date.
 *
 * ## `listening_diagnostics`
 *
 * What the learner tapped on the optional step between the level and the date (кадры V4·03…03г):
 * the three lines they heard, and «Понял» / «Не совсем» beside each. It is stored on the PLAN and
 * not in a table of its own because it is not a log — it is one answer, given once, that shapes two
 * prompts: `{{diagnostics}}` of P1 and `{{balance}}` of P2. A row per line would be a join for a
 * value that is always read whole and never queried across plans.
 *
 * Nullable and NOT defaulted to an empty object: «шаг пропущен» and «шаг пройден, всё понял» are
 * different facts, and the second one changes what the plan is made of. An empty JSON default would
 * have made them the same value.
 *
 * ## `event_date` becomes nullable
 *
 * «Без даты» (кадры V4·04б and 06б). Until now the whole plan was arithmetic over the date, and the
 * column said so. A plan with no date is not a plan with a far-away one: nothing is compressed, no
 * «срок мал» is computed, the days open one after another, and the rehearsal is «в конце» rather
 * than «накануне». The column is the only place that can hold «даты нет» without a second flag
 * disagreeing with it.
 *
 * Forward-only in effect: every existing row HAS a date, so nothing is rewritten, and `down()` can
 * put the NOT NULL back only if no dateless plan has been created since. It says so out loud rather
 * than inventing a date for one.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('learning_plans', function (Blueprint $table): void {
            $table->jsonb('listening_diagnostics')->nullable()->after('level');
        });

        DB::statement('ALTER TABLE learning_plans ALTER COLUMN event_date DROP NOT NULL');
    }

    public function down(): void
    {
        // A dateless plan cannot be given a date by a migration — the date is the learner's fact,
        // not the schema's. Rolling back with one in the table is refused rather than fabricated.
        $dateless = DB::table('learning_plans')->whereNull('event_date')->count();
        if ($dateless > 0) {
            throw new RuntimeException(
                "Откат невозможен: {$dateless} план(ов) без даты события. Дату за пользователя "
                . 'миграция не придумывает — либо проставьте её руками, либо оставьте колонку nullable.'
            );
        }

        DB::statement('ALTER TABLE learning_plans ALTER COLUMN event_date SET NOT NULL');

        Schema::table('learning_plans', function (Blueprint $table): void {
            $table->dropColumn('listening_diagnostics');
        });
    }
};
