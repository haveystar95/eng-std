<?php

declare(strict_types=1);

namespace App\Modules\Plan\Infrastructure\Console;

use App\Modules\Generation\Application\Port\TransientSpeechError;
use App\Modules\Plan\Application\Command\SpeakSceneLines;
use App\Modules\Plan\Application\Command\SpeakSceneLinesHandler;
use App\Modules\Plan\Domain\ValueObject\PlanSceneId;
use App\Modules\Shared\Domain\ValueObject\Ulid;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * `plan:speak-backfill {--plan=}` — the voiced lines existing scenes still lack (DAY-UI-2): the phrases
 * the day window's «прослушать» plays were never spoken before this order, and a line lost to a
 * vendor limit stayed missing once its job gave up. Runs the queue's own idempotent handler (only
 * missing lines are bought), scene by scene, and waits the vendor's per-minute limit out instead of
 * failing. Prints «было / стало» — the lines stored before and after.
 *
 * Writes `plan_line_audios` and files on `plan.audio_disk` — take the database backup first, as for
 * any write to the dev database.
 */
final class PlanSpeakBackfillCommand extends Command
{
    protected $signature = 'plan:speak-backfill {--plan= : only this plan id}';

    protected $description = 'Speak the lines plan scenes still lack (phrases, partner lines), waiting out the vendor rate limit';

    /**
     * Waits in a row on one scene, a minute each. A scene finished resets the count: the vendor lets
     * a few lines through a minute and a slow run is still a run; ten refusals in a row without one
     * scene finished is the vendor refusing the day.
     */
    private const MAX_WAITS = 10;

    public function handle(SpeakSceneLinesHandler $handler): int
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
        $stored = static fn (): int => $scenes === [] ? 0 : DB::table('plan_line_audios')->whereIn('scene_id', $scenes)->count();
        $before = $stored();

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

        $this->info(sprintf('Voiced lines of %d scenes — before: %d · after: %d', count($scenes), $before, $stored()));

        return self::SUCCESS;
    }
}
