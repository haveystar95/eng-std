<?php

declare(strict_types=1);

namespace App\Modules\Plan\Infrastructure\Console;

use App\Modules\Generation\Application\Port\TransientSpeechError;
use App\Modules\Plan\Application\Command\SpeakSceneLines;
use App\Modules\Plan\Application\Command\SpeakSceneLinesHandler;
use App\Modules\Plan\Application\Service\RoleLineQueue;
use App\Modules\Plan\Domain\ValueObject\PlanSceneId;
use App\Modules\Shared\Domain\ValueObject\Ulid;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * `plan:speak-backfill {--plan=} {--count}` — the partner's lines existing scenes still lack: a line
 * lost to a vendor limit stays missing once its job gives up. Only the ROLE's lines — the premium
 * voice is theirs; the learner's phrases and the words are the phone's voice (owner, closing
 * DAY-UI-2). Runs the queue's own idempotent handler scene by scene, waits the vendor's per-minute
 * limit out instead of failing, and prints how many role lines are still not voiced, before and
 * after. `--count` only counts: nothing is bought.
 *
 * Writes `plan_line_audios` and files on `plan.audio_disk` — take the database backup first, as for
 * any write to the dev database.
 */
final class PlanSpeakBackfillCommand extends Command
{
    protected $signature = 'plan:speak-backfill {--plan= : only this plan id} {--count : only count the role lines not voiced yet}';

    protected $description = 'Voice the partner lines plan scenes still lack (the role\'s lines only), waiting out the vendor rate limit';

    /**
     * Waits in a row on one scene, a minute each. A scene finished resets the count: the vendor lets
     * a few lines through a minute and a slow run is still a run; ten refusals in a row without one
     * scene finished is the vendor refusing the day.
     */
    private const MAX_WAITS = 10;

    public function handle(SpeakSceneLinesHandler $handler, RoleLineQueue $queue): int
    {
        $option = $this->option('plan');
        if (is_string($option) && $option !== '' && ! Ulid::isValid($option)) {
            $this->error("Not a plan id: {$option}");

            return self::FAILURE;
        }

        /** @var list<string> $scenes */
        $scenes = DB::table('plan_scenes')
            ->join('plans', 'plans.id', '=', 'plan_scenes.plan_id')
            ->where('plans.status', '<>', 'deleted')
            ->where('plan_scenes.lesson_status', 'ready')
            ->when(is_string($option) && $option !== '', static fn ($q) => $q->where('plans.id', $option))
            ->orderBy('plan_scenes.id')
            ->pluck('plan_scenes.id')
            ->all();
        $owed = static function () use ($scenes, $queue): int {
            $count = 0;
            foreach ($scenes as $sceneId) {
                $count += count($queue->owed(PlanSceneId::fromString($sceneId))->lines ?? []);
            }

            return $count;
        };

        $before = $owed();
        if ($this->option('count') === true) {
            $this->info(sprintf('Role lines not voiced yet, %d scenes: %d', count($scenes), $before));

            return self::SUCCESS;
        }

        foreach ($scenes as $sceneId) {
            $waits = 0;
            while (true) {
                try {
                    $handler(new SpeakSceneLines(PlanSceneId::fromString($sceneId)));
                    break;
                } catch (TransientSpeechError $e) {
                    if (++$waits > self::MAX_WAITS) {
                        $this->warn('The vendor kept refusing — run the command again later: '.$e->getMessage());
                        break 2;
                    }
                    $this->line('Vendor limit — waiting '.($e->retryAfterSeconds ?? 60).' s');
                    sleep(max(1, $e->retryAfterSeconds ?? 60));
                }
            }
        }

        $this->info(sprintf('Role lines not voiced yet, %d scenes — before: %d · after: %d', count($scenes), $before, $owed()));

        return self::SUCCESS;
    }
}
