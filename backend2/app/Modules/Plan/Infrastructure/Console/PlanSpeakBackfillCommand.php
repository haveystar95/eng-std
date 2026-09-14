<?php

declare(strict_types=1);

namespace App\Modules\Plan\Infrastructure\Console;

use App\Modules\Generation\Application\Port\TransientSpeechError;
use App\Modules\Plan\Application\Command\VoiceScene;
use App\Modules\Plan\Application\Command\VoiceSceneHandler;
use App\Modules\Plan\Application\Service\SceneVoiceQueue;
use App\Modules\Plan\Domain\ValueObject\PlanSceneId;
use App\Modules\Shared\Domain\ValueObject\Ulid;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * `plan:speak-backfill {--plan=} {--count}` — everything existing scenes still do not say in the
 * server's voice (DAY-UI-3): the partner's lines, the learner's lines, the phrases, the words. A scene
 * written before voices had genders gets its cast first ({@see SceneVoiceQueue}) — a scene whose
 * phrases were already bought keeps them as the learner's voice.
 *
 * Runs the queue's own idempotent handler scene by scene — at most four vendor calls a scene — waits
 * the vendor's per-minute limit out, and STOPS on the daily one: the next window is the vendor's
 * midnight, and the command says so instead of sleeping half a day. Prints what is not voiced yet by
 * kind, before and after; `--count` only counts: nothing is bought.
 *
 * Writes `plan_line_audios`, `plan_scenes.partner_voice_gender` and files on `plan.audio_disk` — take
 * the database backup first, as for any write to the dev database.
 */
final class PlanSpeakBackfillCommand extends Command
{
    protected $signature = 'plan:speak-backfill {--plan= : only this plan id} {--count : only count what is not voiced yet}';

    protected $description = 'Voice what plan scenes still lack — both speakers\' lines, phrases and words — waiting out the per-minute vendor limit';

    /** Per-minute refusals in a row on one scene before the command gives up for now. */
    private const MAX_WAITS = 10;

    public function handle(VoiceSceneHandler $handler, SceneVoiceQueue $queue): int
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
            ->whereIn('plan_scenes.lesson_status', ['ready', 'illustrating'])
            ->when(is_string($option) && $option !== '', static fn ($q) => $q->where('plans.id', $option))
            ->orderBy('plans.created_at', 'desc')
            ->orderBy('plan_scenes.order')
            ->pluck('plan_scenes.id')
            ->all();

        $before = $this->owed($scenes, $queue);
        if ($this->option('count') === true) {
            $this->info(sprintf('Not voiced yet, %d scenes — %s', count($scenes), self::words($before)));

            return self::SUCCESS;
        }

        foreach ($scenes as $sceneId) {
            $waits = 0;
            while (true) {
                try {
                    $handler(new VoiceScene(PlanSceneId::fromString($sceneId)));
                    break;
                } catch (TransientSpeechError $e) {
                    if ($e->perDay) {
                        $this->warn(sprintf(
                            'The vendor\'s daily limit is spent — the next window opens in %s; run the command again then.',
                            self::hours($e->retryAfterSeconds),
                        ));
                        break 2;
                    }
                    if (++$waits > self::MAX_WAITS) {
                        $this->warn('The vendor kept refusing — run the command again later: '.$e->getMessage());
                        break 2;
                    }
                    $wait = max(1, min(120, $e->retryAfterSeconds ?? 60));
                    $this->line("Vendor limit — waiting {$wait} s");
                    sleep($wait);
                }
            }
        }

        $this->info(sprintf(
            'Not voiced yet, %d scenes — before: %s · after: %s',
            count($scenes), self::words($before), self::words($this->owed($scenes, $queue)),
        ));

        return self::SUCCESS;
    }

    /**
     * @param  list<string>  $scenes
     * @return array{partner: int, learner: int, phrases: int, words: int}
     */
    private function owed(array $scenes, SceneVoiceQueue $queue): array
    {
        $out = ['partner' => 0, 'learner' => 0, 'phrases' => 0, 'words' => 0];
        foreach ($scenes as $sceneId) {
            $debt = $queue->owed(PlanSceneId::fromString($sceneId));
            if ($debt === null) {
                continue;
            }
            $out['partner'] += $debt->partnerLines;
            $out['learner'] += $debt->learnerLines;
            $out['phrases'] += $debt->phrases;
            $out['words'] += $debt->words;
        }

        return $out;
    }

    /** @param array{partner: int, learner: int, phrases: int, words: int} $owed */
    private static function words(array $owed): string
    {
        return "partner lines {$owed['partner']}, learner lines {$owed['learner']}, phrases {$owed['phrases']}, words {$owed['words']}";
    }

    private static function hours(?int $seconds): string
    {
        return $seconds === null ? 'the vendor\'s next day' : sprintf('%dh %02dm', intdiv($seconds, 3600), intdiv($seconds % 3600, 60));
    }
}
