<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * THE OWNER'S TRAINER SWITCHES, so a fresh database is the product the owner actually runs.
 *
 * ## What this fixes, and what it deliberately does not
 *
 * The наряд that asked for this expected an EMPTY matrix. It is not empty: the migrations write
 * every row. The whole difference is five GLOBAL rows — `intro`, `speaking`, `dictation`,
 * `pick_correct`, `description_match` — which every migration ships DISABLED on purpose. That is
 * the release rule, stated out loud in `2026_08_18_090000_add_speaking_to_mode_settings.php` and in
 * the project guide: a new trainer goes out dark and is switched on себе → бете → всем from the
 * admin panel, never by a migration.
 *
 * The rule is about a trainer nobody has tried yet. These five have been tried: the owner switched
 * them on and has been studying with them. What was missing is that a NEW database did not know
 * that, so a plan on a fresh account came out with no intro card and no speaking card at all, and
 * nothing said so — the live run measured a stage-A checklist of 27 tasks where the design says 56
 * (`docs/research/e2e-sim-1.md`, Д-14, Д-15).
 *
 * So this seeder carries the ROLLOUT and nothing else. It writes `enabled` and `position` and does
 * not touch a single gate: `min_acquisition`, `min_learning_step`, `min_successful_reviews` and
 * `options_policy` are already identical in both databases, and a seeder that restated them would
 * be a second place to change them.
 *
 * ## Idempotent, and it never overrides a person
 *
 * Matched on `(user_id IS NULL, mode)`, which is the shipped row — a learner's
 * OWN override (`user_id` set) is untouched, and running this twice changes nothing the second
 * time. A mode that has no row at all is left alone rather than invented: the migrations own which
 * modes exist, and a seeder that could add one would be able to resurrect a mode a migration
 * dropped.
 */
class LearningModeSettingsSeeder extends Seeder
{
    /**
     * The owner's live rollout — `mode => [enabled, position]`.
     *
     * Read out of `wordtrainer` on 01.09.2026. The order IS the product: `position` is the ordinary
     * session's rotation.
     */
    private const ROLLOUT = [
        'multiple_choice' => [true, 0],
        'word_bank' => [true, 1],
        'typing' => [true, 2],
        'listening' => [true, 3],
        'cloze' => [true, 4],
        'scramble' => [true, 5],
        'dictation' => [true, 6],
        'pick_correct' => [true, 7],
        'description_match' => [true, 8],
        'speaking' => [true, 9],
        'intro' => [true, 10],
    ];

    public function run(): void
    {
        foreach (self::ROLLOUT as $mode => [$enabled, $position]) {
            DB::table('learning_mode_settings')
                ->whereNull('user_id')
                ->where('mode', $mode)
                // Nothing to say about a row that already says it. A no-op that still stamped
                // `updated_at` would make «when did this trainer go out» read as «the last time
                // anybody ran a seeder».
                ->where(fn ($q) => $q->where('enabled', '!=', $enabled)->orWhere('position', '!=', $position))
                ->update([
                    'enabled' => $enabled,
                    'position' => $position,
                    'updated_at' => now(),
                ]);
        }
    }
}
