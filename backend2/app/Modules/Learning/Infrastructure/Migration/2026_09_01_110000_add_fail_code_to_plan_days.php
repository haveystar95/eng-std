<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * `learning_plan_days.fail_code` — WHY THE DAY BURNED, as a code the client may switch on.
     *
     * The day already stores the verdict twice, for two readers who want different things:
     * `fail_reason` is Russian prose for a person, `generation_violations` is the address list the
     * next prompt is told. Neither is usable by the CLIENT, and the live run is what that costs
     * (Д-19): the screen said «модель вернула материал не на том языке» over a day that had failed
     * on `day.example_is_a_term`, because the client had no code to switch on and one hard-coded
     * sentence to show. The owner could not tell what to fix.
     *
     * A CODE and not the text. The rule from 31.08 stands — `PlanViolation::$detail` is Russian and
     * stays on the server — and it is what makes a third column right rather than «send the prose».
     * The client owns its own wording, in its own l10n, for the codes it knows, and has one honest
     * fallback for the codes it does not.
     *
     * The FIRST fatal violation of the last attempt, not all of them: the screen has room for one
     * sentence, `generation_violations` keeps the full list beside it, and a day that failed on six
     * things failed on the first one too. Cleared with the rest when the day is written.
     *
     * 64 characters — the longest code in either validator is under 32 and this leaves room for a
     * namespace nobody has needed yet. No index: read by primary key with the day.
     */
    public function up(): void
    {
        Schema::table('learning_plan_days', function (Blueprint $table): void {
            $table->string('fail_code', 64)->nullable()->after('fail_reason');
        });
    }

    public function down(): void
    {
        Schema::table('learning_plan_days', function (Blueprint $table): void {
            $table->dropColumn('fail_code');
        });
    }
};
