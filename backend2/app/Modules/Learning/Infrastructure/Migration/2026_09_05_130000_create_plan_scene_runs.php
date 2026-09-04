<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * `learning_plan_scene_runs` — ЖУРНАЛ ПРОГОНОВ СЦЕНЫ (наряд SCENE-RUN, Ч.2.6).
     *
     * Одна строка на ЗАВЕРШЁННЫЙ прогон, и это событие, а не счётчик: прогон случился в такой-то
     * день, столько-то ходов человек прошёл сам, столько-то пропустил, столько-то сделал
     * спасателем ({@see \App\Modules\Learning\Domain\ValueObject\PlanSceneRun}). Строка не
     * редактируется никогда — «лучший результат» живёт на паре (план, термин)
     * (`learning_plan_term_stages`), а здесь лежит то, что было.
     *
     * ## Почему не в `plan_conversations`
     *
     * Та таблица заведена под CONV-1 — свободный разговор с ролью: у неё есть `transcript_ref`,
     * `checkpoints_hit` и `hints_used`, и ни одно из трёх не имеет смысла для прогона, который
     * идёт ход за ходом по сценарию сцены. Прогон — не разговор, а последняя ступень лестницы; две
     * разные вещи в одной таблице это `checkpoints_hit`, означающий разное в разных строках.
     *
     * ## Индекс
     *
     * `(plan_id, scene_index)` — единственный запрос, который к этой таблице ходит: «что известно о
     * прогонах этой сцены» (зрелость сцены на экране плана, кадр D·07, и итог дня). По времени её
     * никто не читает, поэтому индекса по `completed_at` нет.
     *
     * Обратимость: таблица новая, `down()` её сносит.
     */
    public function up(): void
    {
        Schema::create('learning_plan_scene_runs', function (Blueprint $table): void {
            $table->char('id', 26)->primary();
            $table->char('plan_id', 26);
            // Сцена — то, что прогоняли; день — когда. В обычный день это одно число, в финальном
            // расходятся: там подряд прогоняются все сцены плана.
            $table->integer('scene_index');
            $table->integer('day_index');
            $table->integer('total');
            $table->integer('said');
            $table->integer('said_fast');
            $table->integer('skipped');
            $table->integer('rescued');
            $table->timestampTz('completed_at');
            $table->timestampsTz();

            $table->foreign('plan_id')->references('id')->on('learning_plans')->cascadeOnDelete();
            $table->index(['plan_id', 'scene_index'], 'learning_plan_scene_runs_scene_idx');
        });

        // Арифметика прогона держится базой, а не дисциплиной вызывающего: `total` это ровно сумма
        // трёх исходов, а «сразу» — подмножество «сам». Строка, в которой это не так, описывает
        // прогон, которого не было.
        DB::statement(
            'ALTER TABLE learning_plan_scene_runs ADD CONSTRAINT learning_plan_scene_runs_sum_check '
            . 'CHECK (total = said + skipped + rescued AND said_fast <= said AND said >= 0 '
            . 'AND skipped >= 0 AND rescued >= 0 AND said_fast >= 0)'
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('learning_plan_scene_runs');
    }
};
