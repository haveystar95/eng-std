<?php

declare(strict_types=1);

use App\Modules\Learning\Domain\ValueObject\Acquisition;
use App\Modules\Learning\Domain\ValueObject\ExerciseMode;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Correctness backfill for QA-20's «честная лестница» наряд: speaking's constructive
     * minimum ({@see \App\Modules\Learning\Domain\Service\ModePassport::floorFor()}) is
     * `graduated` — before then there is nothing to recall — but the admin screen let a row be
     * saved below it (the write-side guard this наряд adds did not exist yet), and at least the
     * owner's own global row was found sitting at `learning`. That is the UX defect this наряд
     * was opened for: the screen accepted the setting and it silently did nothing useful.
     *
     * Every `learning_mode_settings` row for `speaking` reading below its floor, in every scope,
     * is corrected here to the shipped threshold (`graduated`, no learning step, no reviews
     * count) — the same values {@see \App\Modules\Learning\Domain\ValueObject\ModeAdmission::shipped()}
     * already uses for it.
     *
     * REVERSIBLE without guessing: every row this migration touches has its PRIOR three columns
     * captured into a companion table before the update, so `down()` restores the exact values
     * rather than re-introducing a hardcoded guess at what they were.
     */
    public function up(): void
    {
        Schema::create('learning_mode_settings_speaking_floor_backup', function (Blueprint $table): void {
            $table->char('setting_id', 26)->primary();
            $table->string('min_acquisition', 16);
            $table->smallInteger('min_learning_step')->nullable();
            $table->integer('min_successful_reviews')->nullable();
        });

        $mode = ExerciseMode::Speaking->value;
        $floor = Acquisition::Graduated->value;

        $below = DB::table('learning_mode_settings')
            ->where('mode', $mode)
            ->where('min_acquisition', '!=', $floor)
            ->get(['id', 'min_acquisition', 'min_learning_step', 'min_successful_reviews']);

        foreach ($below as $row) {
            DB::table('learning_mode_settings_speaking_floor_backup')->insert([
                'setting_id' => $row->id,
                'min_acquisition' => $row->min_acquisition,
                'min_learning_step' => $row->min_learning_step,
                'min_successful_reviews' => $row->min_successful_reviews,
            ]);
        }

        DB::table('learning_mode_settings')
            ->where('mode', $mode)
            ->where('min_acquisition', '!=', $floor)
            ->update([
                'min_acquisition' => $floor,
                'min_learning_step' => null,
                'min_successful_reviews' => null,
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        foreach (DB::table('learning_mode_settings_speaking_floor_backup')->get() as $row) {
            DB::table('learning_mode_settings')->where('id', $row->setting_id)->update([
                'min_acquisition' => $row->min_acquisition,
                'min_learning_step' => $row->min_learning_step,
                'min_successful_reviews' => $row->min_successful_reviews,
                'updated_at' => now(),
            ]);
        }

        Schema::dropIfExists('learning_mode_settings_speaking_floor_backup');
    }
};
