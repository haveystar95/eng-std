<?php

declare(strict_types=1);

use App\Modules\Plan\Infrastructure\Eloquent\PartnerVoiceBackfill;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * THE PARTNER'S VOICE OF A SCENE, FIXED (наряд FIX-4c §1): the vendor's id of the voice the scene's partner speaks in —
     * one of the two voices of the role's gender, cast once, when the scene's lesson is accepted, so that two neighbouring
     * scenes of one gender are two people. Nullable, no default, no index: read with its scene by the scene's key.
     *
     * The scenes that are there are given theirs now ({@see PartnerVoiceBackfill}): voice 1 of the gender where the scene's
     * partner lines are voiced (what they sound like already — nothing is voiced again), the rule of new scenes where none is
     * voiced yet, nothing where no lesson is written. Down drops only this column.
     */
    public function up(): void
    {
        DB::statement('ALTER TABLE plan_scenes ADD COLUMN partner_voice_id varchar(64) NULL');
        app(PartnerVoiceBackfill::class)->run();
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE plan_scenes DROP COLUMN IF EXISTS partner_voice_id');
    }
};
