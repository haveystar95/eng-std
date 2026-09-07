<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * `learning_plan_day_stage_passages` — ЖУРНАЛ ПРОЙДЕННЫХ ЭТАПОВ (наряд DAY-GATE-1, доработка).
     *
     * Одна строка на факт «этап X дня N этого плана пройден». Append-only: строка не меняется и не
     * удаляется — пройденное не разучивается.
     *
     * ## Зачем таблица, если «пройден» и так выводится
     *
     * Выводился он из вопроса «что эта карточка ДОЛЖНА СЕГОДНЯ»
     * ({@see \App\Modules\Learning\Domain\Service\PlanStageLadder::owedStepsToday()}), а «сегодня»
     * кончается в полночь. Живой прогон 07–08.09 показал цену: в 23:51 у дня 1 стояло
     * `rehearsal: current`, в 00:07 — снова `material: current`, потому что лестница пересчитала
     * долг на новый календарный день. Человек, начавший день вечером, наутро видел его непройденным.
     *
     * Пересчёт — это МНЕНИЕ о сегодняшнем дне; прохождение — СОБЫТИЕ. События не пересчитываются,
     * поэтому они и лежат в таблице, а вывод остался только тем, что событие ПОРОЖДАЕТ: этап,
     * которому сегодня нечего показать, закрывается — и в тот же миг записывается.
     *
     * Правило «один показ ступени в день» этим не тронуто: оно про ПОКАЗЫ карточек, а не про этапы,
     * и живёт там же, где жило.
     *
     * ## Ключ
     *
     * `(plan_id, day_index, stage)` уникален: событие «этап пройден» случается один раз. Запись
     * идемпотентна — повторная попытка молча ничего не делает, и это то, что позволяет звать её из
     * каждого места, где этап мог закрыться, не сверяясь предварительно.
     *
     * `passed_on` — календарная дата УЧЕНИКА, в которую этап закрылся. У строк, дописанных
     * `plan:reconcile-day` задним числом, это дата сверки: сверка восстанавливает ФАКТ, а не время,
     * и делать вид, что знает время, ей нечем.
     *
     * Обратимость: таблица новая, `down()` её сносит.
     */
    public function up(): void
    {
        Schema::create('learning_plan_day_stage_passages', function (Blueprint $table): void {
            $table->char('id', 26)->primary();
            $table->char('plan_id', 26);
            $table->integer('day_index');
            // `material` | `conversation` | `rehearsal` — значения
            // {@see \App\Modules\Learning\Domain\ValueObject\PlanDayStage}. `retrain` сюда не
            // пишется никогда: он дня не держит и «пройден» от него не зависит.
            $table->string('stage', 16);
            $table->date('passed_on');
            $table->timestampsTz();

            $table->foreign('plan_id')->references('id')->on('learning_plans')->cascadeOnDelete();
            // ОДИН запрос ходит к этой таблице — «что уже пройдено в этом плане», целиком, на
            // отрисовку плана. По дате её не читают, поэтому индекса по `passed_on` нет.
            $table->unique(['plan_id', 'day_index', 'stage'], 'learning_plan_day_stage_passages_uq');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('learning_plan_day_stage_passages');
    }
};
