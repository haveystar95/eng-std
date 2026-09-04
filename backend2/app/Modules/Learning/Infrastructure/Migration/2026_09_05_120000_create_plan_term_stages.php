<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * `learning_plan_term_stages` — ТО, ЧЕГО ЖУРНАЛ ОТВЕТОВ НЕ ЗНАЕТ (наряд SCENE-RUN, Ч.1 и Ч.2).
     *
     * Лестница плана и дальше считается по append-only журналу и не хранится
     * ({@see \App\Modules\Learning\Domain\Service\PlanStageLadder}). Эта таблица её не дублирует и
     * не может: в ней три факта, которые из журнала не выводятся вовсе
     * ({@see \App\Modules\Learning\Domain\ValueObject\PlanTermStage}).
     *
     *   `choice_streak`  безошибочных ВЫБОРОВ подряд на ступени B. Выбор и сборка — один тренажёр
     *                    (уровень строгости это подача, а не режим), поэтому в журнале они
     *                    неотличимы, и неверная сборка сбрасывала бы счётчик, отбирая у человека
     *                    сборку за то, что он не сдал её с первого раза. Наряд запрещает такой
     *                    откат прямо.
     *   `said_in_run`    реплика прозвучала голосом человека в прогоне сцены — ступень C.
     *   `said_fast`      …и прозвучала СРАЗУ. Латентность карточки (`reviews.latency_ms`) на этот
     *                    вопрос не отвечает: карточка живёт до пятнадцати секунд сторожа, а «сразу»
     *                    меряется от начала прослушивания до ключа.
     *
     * ## Ключ — пара (план, термин)
     *
     * Не (пользователь, термин): термины дедуплицированы глобально, и «сказал сам» в прошлом плане
     * не доказательство про этот. `user_term_progress` этой таблицей не двигается вовсе — правило
     * «прогресс живёт на паре (пользователь, термин)» цело, потому что здесь не прогресс пула, а
     * состояние плановой лестницы, которая всегда была своей для пары (план, термин).
     *
     * Составной первичный ключ и есть индекс доступа: единственный читатель спрашивает план целиком
     * (`WHERE plan_id = …`), единственный писатель — одну пару.
     *
     * Обратимость: таблица новая, `down()` её сносит; ни одна существующая колонка не тронута.
     */
    public function up(): void
    {
        Schema::create('learning_plan_term_stages', function (Blueprint $table): void {
            $table->char('plan_id', 26);
            $table->char('term_id', 26);
            $table->integer('choice_streak')->default(0);
            $table->boolean('said_in_run')->default(false);
            $table->boolean('said_fast')->default(false);
            $table->timestampsTz();

            $table->primary(['plan_id', 'term_id']);
            $table->foreign('plan_id')->references('id')->on('learning_plans')->cascadeOnDelete();
            // RESTRICT, как у коллекции дня: термин, на который ссылается идущий план, не является
            // расходным материалом. Жёсткое удаление такого термина должно падать громко.
            $table->foreign('term_id')->references('id')->on('terms')->restrictOnDelete();
        });

        DB::statement(
            'ALTER TABLE learning_plan_term_stages ADD CONSTRAINT learning_plan_term_stages_streak_check '
            . 'CHECK (choice_streak >= 0)'
        );
        // «Сразу» — это разновидность «сказал», а не отдельное событие: строка `said_fast` без
        // `said_in_run` означала бы реплику, которая прозвучала быстро и не прозвучала.
        DB::statement(
            'ALTER TABLE learning_plan_term_stages ADD CONSTRAINT learning_plan_term_stages_fast_check '
            . 'CHECK (NOT said_fast OR said_in_run)'
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('learning_plan_term_stages');
    }
};
