<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

return new class extends Migration
{
    /**
     * EVERY PLAN BUILT FOR THE LESSON BEFORE FRAMES IS GONE (наряд GEN-2a; owner: «старый каркас и
     * структуру вместе с контентом сносить в 0»).
     *
     * The lesson is now `lesson_day.v4.4` — frames, fillers, exchange kinds, a check per exchange, the
     * listening — and nothing of the lesson it replaced can be read any more: the stored JSON of those
     * lessons, the days dealt from them, their cards, their terms and their voice have no reader in the
     * code. This migration ships with that code, so every plan that exists when it runs was built for the
     * old lesson, whatever state it is in (a plan still waiting for a lesson would be dealt from a plan
     * that was never reviewed against frames). A fresh database has none, and it is a no-op there.
     *
     * What goes, and how:
     *
     *  - `plans` — deleted; `plan_scenes`, `plan_days`, `day_cards`, `plan_terms`, `plan_line_audios`,
     *    `plan_events` and `plan_notifications` go with them by their cascading foreign keys.
     *  - `plan_check_counters` — every row counted under a prompt version that no longer exists: all but
     *    the plan builder's (`plan-builder-*`) and the frames lesson's (`lesson_day.*`) — their names are
     *    checks that no longer exist.
     *  - `collections` — a plan's collection is SOFT-deleted (tombstones for the phone's mirror, the way
     *    PLAN-GEN retired the first plan's collections); the global terms stay.
     *  - files — each dropped scene's voice (`plan-audio/<scene>/` on `plan.audio_disk`) and the square
     *    copies of its photo (`plan-images/<scene>/` on `plan.image_disk`).
     *
     * No `down()`: the rows are content of a lesson that no longer exists; the way back is the database
     * backup taken before `migrate` (`scripts/db-backup.sh`), and the owner allowed this one-way step.
     */
    public function up(): void
    {
        $plans = DB::table('plans')->pluck('id')->all();
        $scenes = $plans === [] ? [] : DB::table('plan_scenes')->whereIn('plan_id', $plans)->pluck('id')->all();
        $collections = $plans === [] ? [] : DB::table('plans')->whereIn('id', $plans)->whereNotNull('collection_id')->pluck('collection_id')->all();

        $counts = [
            'plans' => count($plans),
            'plan_scenes' => count($scenes),
            'plan_days' => $plans === [] ? 0 : DB::table('plan_days')->whereIn('plan_id', $plans)->count(),
            'day_cards' => $plans === [] ? 0 : DB::table('day_cards')->whereIn('day_id', DB::table('plan_days')->whereIn('plan_id', $plans)->select('id'))->count(),
            'plan_terms' => $scenes === [] ? 0 : DB::table('plan_terms')->whereIn('scene_id', $scenes)->count(),
            'plan_line_audios' => $scenes === [] ? 0 : DB::table('plan_line_audios')->whereIn('scene_id', $scenes)->count(),
            'plan_events' => $plans === [] ? 0 : DB::table('plan_events')->whereIn('plan_id', $plans)->count(),
            'plan_notifications' => $plans === [] ? 0 : DB::table('plan_notifications')->whereIn('plan_id', $plans)->count(),
            'plan_check_counters' => self::retiredCounters()->count(),
            'collections_tombstoned' => $collections === [] ? 0 : DB::table('collections')->whereIn('id', $collections)->whereNull('deleted_at')->count(),
        ];

        DB::transaction(function () use ($plans, $collections): void {
            if ($collections !== []) {
                DB::table('collections')->whereIn('id', $collections)->whereNull('deleted_at')
                    ->update(['deleted_at' => now(), 'updated_at' => now()]);
            }
            foreach (array_chunk($plans, 500) as $chunk) {
                DB::table('plans')->whereIn('id', $chunk)->delete();
            }
            self::retiredCounters()->delete();
        });

        $files = 0;
        $audio = Storage::disk((string) config('plan.audio_disk', 'local'));
        $images = Storage::disk((string) config('plan.image_disk', 'local'));
        foreach ($scenes as $scene) {
            foreach ([[$audio, "plan-audio/{$scene}"], [$images, "plan-images/{$scene}"]] as [$disk, $directory]) {
                if ($disk->directoryExists($directory)) {
                    $files += count($disk->allFiles($directory));
                    $disk->deleteDirectory($directory);
                }
            }
        }
        $counts['files'] = $files;

        Log::info('plan: plans built before frames dropped', $counts);
    }

    public function down(): void
    {
        // One-way by design — see the class docblock.
    }

    /** The counters of prompt versions that no longer exist: neither the plan builder's nor the frames lesson's. */
    private static function retiredCounters(): Builder
    {
        return DB::table('plan_check_counters')
            ->where('prompt_version', 'not like', 'plan-builder-%')
            ->where('prompt_version', 'not like', 'lesson\_day.%');
    }
};
