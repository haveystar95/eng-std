<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * `learning_plan_term_stages.retrained_on` — В КАКОЙ ДЕНЬ РЕПЛИКУ УЖЕ БРАЛИ «ПОВТОРИТЬ ОШИБКИ»
     * (наряд DAY-GATE-1, Ч.1.1; решение владельца 07.09, п. 4).
     *
     * «Повторить ошибки» — единственное исключение из правила «один показ ступени в день»: по явному
     * нажатию промахнувшаяся сегодня реплика показывается ЕЩЁ РАЗ, и ровно один раз. Второго показа
     * сегодня нет — иначе исключение перестало бы быть исключением и превратилось бы в способ
     * молотить одну реплику весь вечер.
     *
     * Вывести это из журнала ответов нельзя: очередь посадки на телефоне переспрашивает промах в той
     * же сессии и пишет за него отдельный ответ, поэтому «сколько раз реплика отвечена сегодня» не
     * различает переспрос очереди и повтор по кнопке. Дата — то, чего в журнале нет.
     *
     * ДАТА, А НЕ ФЛАГ: «сегодня» у человека своё (`profiles.timezone`), и завтра строка должна
     * ожить сама, без ночной уборки. Локальный день учащегося, `Y-m-d`, как и всё остальное, что
     * лестница плана меряет днями.
     */
    public function up(): void
    {
        Schema::table('learning_plan_term_stages', function (Blueprint $table): void {
            $table->date('retrained_on')->nullable()->after('said_fast');
        });
    }

    public function down(): void
    {
        Schema::table('learning_plan_term_stages', function (Blueprint $table): void {
            $table->dropColumn('retrained_on');
        });
    }
};
