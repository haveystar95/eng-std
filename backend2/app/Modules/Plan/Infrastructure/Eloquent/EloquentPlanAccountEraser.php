<?php

declare(strict_types=1);

namespace App\Modules\Plan\Infrastructure\Eloquent;

use App\Modules\Plan\Application\Port\PlanAccountEraser;
use App\Modules\Shared\Domain\ValueObject\UserId;
use Illuminate\Contracts\Filesystem\Factory as Disks;
use Illuminate\Support\Facades\DB;

/**
 * Deleting the plans cascades through scenes, days, cards, terms and audio rows by FK — and through the talks, their lines
 * and rejections, the journal of walked stages (`plan_stage_passages`), the journal (`plan_events`) and the delivery log
 * (`plan_notifications`). The last two are also deleted by `user_id` first, explicitly: they are the append-only tables,
 * the eraser is the one path allowed to remove their rows, and it should not depend on a cascade to say so.
 *
 * THE FILES GO TOO (наряд ACC-1 §1): the learner's scenes and talks are read before the rows go, and their folders are
 * deleted whole — every spoken line of a scene lives under `plan-audio/<scene>/` (`EloquentLineAudioStore`), every line
 * of a talk under `plan-audio/conversations/<talk>/` (`DiskConversationAudioStore`), the photo copies under
 * `plan-images/<scene>/` (`CdnSceneImageStore`) — after the account's transaction commits: a deletion that rolls back
 * keeps its files, and a folder already gone is nobody's concern.
 */
final readonly class EloquentPlanAccountEraser implements PlanAccountEraser
{
    public function __construct(
        private Disks $disks,
        private string $audioDisk,
        private string $imageDisk,
    ) {}

    public function eraseFor(UserId $userId): int
    {
        $id = $userId->value;
        $plans = DB::table('plans')->where('user_id', $id)->count();
        $scenes = DB::table('plan_scenes')->where('user_id', $id)->pluck('id')->map(static fn (mixed $v): string => (string) $v)->all();
        $talks = DB::table('conversations')->where('user_id', $id)->pluck('id')->map(static fn (mixed $v): string => (string) $v)->all();

        DB::table('plan_notifications')->where('user_id', $id)->delete();
        DB::table('plan_events')->where('user_id', $id)->delete();
        DB::table('plans')->where('user_id', $id)->delete();

        DB::afterCommit(function () use ($scenes, $talks): void {
            $audio = $this->disks->disk($this->audioDisk);
            $images = $this->disks->disk($this->imageDisk);
            foreach ($scenes as $scene) {
                $audio->deleteDirectory("plan-audio/{$scene}");
                $images->deleteDirectory("plan-images/{$scene}");
            }
            foreach ($talks as $talk) {
                $audio->deleteDirectory("plan-audio/conversations/{$talk}");
            }
        });

        return $plans;
    }
}
