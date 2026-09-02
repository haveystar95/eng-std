<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * `plan_skills.skill_ref` — THE NAME A CARD OF THE DAY CALLS THIS ABILITY BY.
     *
     * The row already has an `id`, and it is a ULID: it answers «which row is this», it is generated
     * fresh, and it CHANGES when a plan is rescheduled — every ability is rewritten as a set
     * ({@see \App\Modules\Learning\Domain\Repository\PlanSkillRepository::replaceAll()}). A card of a
     * day already written points at «the promise», and a promise that was renumbered by a
     * reschedule would leave every card of every earlier day pointing at nothing.
     *
     * So the promise gets a name of its own — «s1.2», the scene and the position inside it — which
     * P1 v0.4 is asked for, the server fills in when the answer omits it, and the day's cards carry
     * in `skill_ref` ({@see \App\Modules\Generation\Domain\Service\PlanDayValidator::SKILL_REF_INVALID}).
     *
     * Backfilled from the position the rows already hold, which is exactly what the generated form
     * means: an id is an address, and these rows have always had the address, just not written down.
     *
     * No index — read together with the plan's skills, never queried by on its own.
     */
    public function up(): void
    {
        Schema::table('plan_skills', function (Blueprint $table): void {
            $table->string('skill_ref', 32)->nullable()->after('id');
        });

        DB::statement("UPDATE plan_skills SET skill_ref = 's' || scene_index || '.' || (skill_index + 1)");
    }

    public function down(): void
    {
        Schema::table('plan_skills', function (Blueprint $table): void {
            $table->dropColumn('skill_ref');
        });
    }
};
