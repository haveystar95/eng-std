<?php

declare(strict_types=1);

namespace App\Modules\Plan\Infrastructure\Console;

use App\Modules\Generation\Application\Port\SpeechAccountError;
use App\Modules\Generation\Application\Port\TransientSpeechError;
use App\Modules\Identity\Application\Port\UserReader;
use App\Modules\Plan\Application\Command\DropUnreadVoice;
use App\Modules\Plan\Application\Command\DropUnreadVoiceHandler;
use App\Modules\Plan\Application\Command\VoiceScene;
use App\Modules\Plan\Application\Command\VoiceSceneHandler;
use App\Modules\Plan\Application\Dto\VoiceBalance;
use App\Modules\Plan\Application\Exception\VoiceCapReached;
use App\Modules\Plan\Application\Exception\VoiceFuseTripped;
use App\Modules\Plan\Application\Port\LineAudioStore;
use App\Modules\Plan\Application\Port\LineSpeaker;
use App\Modules\Plan\Application\Service\SceneVoiceQueue;
use App\Modules\Plan\Application\Service\VoiceCap;
use App\Modules\Plan\Application\Service\VoiceFuse;
use App\Modules\Plan\Domain\ValueObject\PlanSceneId;
use App\Modules\Plan\Infrastructure\Adapter\VoiceDatabase;
use App\Modules\Shared\Domain\Service\SpeechCost;
use App\Modules\Shared\Domain\ValueObject\Ulid;
use App\Modules\Shared\Domain\ValueObject\UserId;
use DateTimeImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use stdClass;

/**
 * `plan:speak-backfill {--plan=*} {--count}` — everything the lessons of existing scenes still do not say in the
 * server's voice (TTS-2): the partner's lines, the learner's lines, the phrases, the phrases with their other fillers,
 * the words.
 *
 * Scene by scene, each the way a fresh day is voiced ({@see VoiceSceneHandler}): every line on a call of its own, in the
 * voice its speaker has in the scene. `--drop-unread` deletes, scene by scene right before the scene is bought, the lines
 * filed under a voice their speaker no longer has (a voice of the pack was changed — {@see DropUnreadVoiceHandler}), so
 * they are bought anew in the voice they have now and no dead file stays behind. The order: the plans of real learners
 * first, then the plans of QA accounts (the simulator's), newest plan first within each, scenes in their order; `--plan`
 * takes only the plans named, in the order named. On a database voiced only by name (the e2e stand,
 * `generation.speech.named_plans_only_databases`) nothing is bought without `--plan`.
 *
 * The whole run is one run of the credits cap ({@see VoiceCap}): before each scene what the run has bought plus what the
 * scene would cost must fit under `generation.speech.job_credits_cap`, or the run STOPS there — the scene is neither
 * dropped nor bought, and the letter goes to the log. It also waits a transient refusal out a few times, and STOPS on a
 * refusal of the vendor account (no credits, a voice the plan does not include — the vendor's code is printed) and on
 * the fuse (too little of the account left). What did not get bought stays owed, and the next run starts where this one
 * stopped — nothing here remembers a position. Prints what is not voiced yet by kind before and after, and what the run
 * bought: lines, characters, dollars, credits, calls. `--count` only counts, and names the price of what is owed — credits ·
 * characters · $ by the tariff's rate — to be agreed before anything is bought: nothing is bought. `--drop-only` only
 * deletes the lines (rows and files) filed under a voice their speaker no longer has: nothing is bought, what they said
 * stays owed.
 *
 * Writes `plan_line_audios` and files on `plan.audio_disk` — take the database backup first, as for any write to the
 * dev database.
 */
final class PlanSpeakBackfillCommand extends Command
{
    protected $signature = 'plan:speak-backfill {--plan=* : only these plan ids, in this order} {--count : only count what is not voiced yet} {--drop-unread : first delete the lines filed under a voice their speaker no longer has} {--drop-only : only delete the lines filed under a voice their speaker no longer has — nothing is bought}';

    protected $description = 'Voice what plan scenes still lack — every line on its own call — real learners first';

    /** Transient refusals in a row on one scene before the command gives up for now. */
    private const MAX_WAITS = 5;

    public function handle(VoiceSceneHandler $voice, DropUnreadVoiceHandler $drop, SceneVoiceQueue $queue, VoiceCap $cap, VoiceFuse $fuse, LineAudioStore $store, LineSpeaker $speaker, UserReader $users): int
    {
        /** @var list<string> $named */
        $named = array_values(array_filter((array) $this->option('plan'), static fn (mixed $id): bool => is_string($id) && $id !== ''));
        foreach ($named as $id) {
            if (! Ulid::isValid($id)) {
                $this->error("Not a plan id: {$id}");

                return self::FAILURE;
            }
        }
        if ($named === [] && $this->option('count') !== true && VoiceDatabase::namedPlansOnly()) {
            $this->error('This database is voiced only by --plan: nothing bought. Name the plans — plan:speak-backfill --plan=<id>.');

            return self::FAILURE;
        }

        $scenes = $this->scenes($named, $users);
        if ($this->option('drop-only') === true) {
            // Dead files of a voice the pack no longer has, gone without a purchase (TTS-2: the slowed learner's files).
            $dropped = 0;
            foreach ($scenes as $sceneId) {
                $dropped += $drop(new DropUnreadVoice(PlanSceneId::fromString($sceneId)));
            }
            $this->info(sprintf('Lines filed under a voice their speaker no longer has — dropped: %d in %d scenes; nothing bought', $dropped, count($scenes)));

            return self::SUCCESS;
        }
        $before = $this->owed($scenes, $queue);
        if ($this->option('count') === true) {
            $this->info(sprintf('Not voiced yet, %d scenes — %s', count($scenes), self::kinds($before)));
            $price = $this->price($scenes, $queue, $speaker);
            $this->info(sprintf(
                'Would cost about %d credits · %d characters · $%s — the price to name before buying',
                $price['credits'],
                $price['characters'],
                number_format($price['credits'] / 1000 * (float) config('generation.speech.usd_per_thousand_credits', 0.20), 4, '.', ''),
            ));

            return self::SUCCESS;
        }

        $started = new DateTimeImmutable();
        $balance = $fuse->balance();
        $this->line(self::balanceLine('Account before', $balance));
        $voiced = 0;
        $dropped = 0;
        try {
            foreach ($scenes as $sceneId) {
                $scene = PlanSceneId::fromString($sceneId);
                if ($this->option('drop-unread') === true) {
                    // The cap is asked before the old files go: a scene the run may not buy keeps the voice it has.
                    $debt = $queue->owed($scene);
                    if ($debt !== null && ! $debt->isSettled()) {
                        $cap->assertRoom($debt, $store->creditsSince($started));
                    }
                    $dropped += $drop(new DropUnreadVoice($scene));
                }
                for ($waits = 0; ; $waits++) {
                    try {
                        $voice(new VoiceScene($scene, $store->creditsSince($started)));
                        $voiced++;
                        break;
                    } catch (TransientSpeechError $e) {
                        if ($waits >= self::MAX_WAITS) {
                            $this->warn('The vendor kept refusing — run the command again later: '.$e->getMessage());
                            break 2;
                        }
                        $wait = max(5, min(120, $e->retryAfterSeconds ?? 30));
                        $this->line("Vendor limit — waiting {$wait} s ({$e->getMessage()})");
                        sleep($wait);
                    }
                }
            }
        } catch (SpeechAccountError $e) {
            $this->error("The voice vendor account refused ({$e->httpStatus} {$e->vendorCode}) — stopped; nothing more is bought until the account is fixed: {$e->getMessage()}");
        } catch (VoiceCapReached $e) {
            $this->error('Stopped by the credits cap — '.$e->getMessage());
            Log::error('plan:speak-backfill stopped by the credits cap; the rest stays owed', ['scene_credits' => $e->sceneCredits, 'spent' => $e->spent, 'cap' => $e->cap]);
        } catch (VoiceFuseTripped $e) {
            $this->warn('Stopped by the voice fuse — '.$e->getMessage());
        }
        if ($this->option('drop-unread') === true) {
            $this->line("Lines filed under a voice their speaker no longer has — dropped: {$dropped}");
        }

        $bought = $this->bought($scenes, $started);
        $this->info(sprintf(
            'Scenes gone through: %d of %d · bought: %d lines, %d characters, $%s, %d credits, %d calls',
            $voiced, count($scenes), $bought['lines'], $bought['characters'], $bought['usd'], $bought['credits'], $bought['calls'],
        ));
        $this->info(sprintf('Not voiced yet, %d scenes — before: %s · after: %s', count($scenes), self::kinds($before), self::kinds($this->owed($scenes, $queue))));
        $this->line(self::balanceLine('Account after', $fuse->balance()));

        return self::SUCCESS;
    }

    /**
     * The scenes with a lesson, in the order they are voiced.
     *
     * @param  list<string>  $named
     * @return list<string>
     */
    private function scenes(array $named, UserReader $users): array
    {
        /** @var list<stdClass> $rows */
        $rows = DB::table('plan_scenes')
            ->join('plans', 'plans.id', '=', 'plan_scenes.plan_id')
            ->where('plans.status', '<>', 'deleted')
            ->whereIn('plan_scenes.lesson_status', ['ready', 'illustrating'])
            ->when($named !== [], static fn ($q) => $q->whereIn('plans.id', $named))
            ->orderBy('plans.created_at', 'desc')
            ->orderBy('plan_scenes.order')
            ->get(['plan_scenes.id as scene_id', 'plans.id as plan_id', 'plans.user_id as user_id'])
            ->all();

        $qa = [];
        $rank = static function (stdClass $row) use ($named, $users, &$qa): int {
            if ($named !== []) {
                return (int) array_search((string) $row->plan_id, $named, true);
            }
            $user = (string) $row->user_id;
            $qa[$user] ??= $users->byId(new UserId($user))?->qaTools === true;

            return $qa[$user] ? 1 : 0;
        };
        // A stable sort: within one rank the newest plan stays first and its scenes stay in order.
        $ranked = array_map(static fn (stdClass $row, int $i): array => [$rank($row), $i, (string) $row->scene_id], $rows, array_keys($rows));
        usort($ranked, static fn (array $a, array $b): int => [$a[0], $a[1]] <=> [$b[0], $b[1]]);

        return array_map(static fn (array $r): string => $r[2], $ranked);
    }

    /**
     * @param  list<string>  $scenes
     * @return array{partner: int, learner: int, phrases: int, fillers: int, words: int}
     */
    private function owed(array $scenes, SceneVoiceQueue $queue): array
    {
        $out = ['partner' => 0, 'learner' => 0, 'phrases' => 0, 'fillers' => 0, 'words' => 0];
        foreach ($scenes as $sceneId) {
            $debt = $queue->owed(PlanSceneId::fromString($sceneId));
            if ($debt === null) {
                continue;
            }
            $out['partner'] += $debt->partnerLines;
            $out['learner'] += $debt->learnerLines;
            $out['phrases'] += $debt->phrases;
            $out['fillers'] += $debt->fillers;
            $out['words'] += $debt->words;
        }

        return $out;
    }

    /**
     * What buying everything owed would cost: the vendor's credits by the tariff's rate, and the characters they are for.
     *
     * @param  list<string>  $scenes
     * @return array{credits: int, characters: int}
     */
    private function price(array $scenes, SceneVoiceQueue $queue, LineSpeaker $speaker): array
    {
        $out = ['credits' => 0, 'characters' => 0];
        foreach ($scenes as $sceneId) {
            $debt = $queue->owed(PlanSceneId::fromString($sceneId));
            if ($debt === null || $debt->isSettled()) {
                continue;
            }
            $out['credits'] += $speaker->creditsFor($debt->lang, $debt->lines);
            foreach ($debt->lines as $line) {
                $out['characters'] += SpeechCost::charactersOf($line->text);
            }
        }

        return $out;
    }

    /**
     * @param  list<string>  $scenes
     * @return array{lines: int, characters: int, usd: string, credits: int, calls: int}
     */
    private function bought(array $scenes, DateTimeImmutable $since): array
    {
        $row = $scenes === [] ? null : DB::table('plan_line_audios')
            ->whereIn('scene_id', $scenes)
            ->where('created_at', '>=', $since)
            ->selectRaw('count(*) as lines, coalesce(sum(characters), 0) as characters, coalesce(sum(cost_usd), 0) as usd, coalesce(sum(credits), 0) as credits, count(distinct coalesce(request_id, id)) as calls')
            ->first();

        return [
            'lines' => (int) ($row->lines ?? 0),
            'characters' => (int) ($row->characters ?? 0),
            'usd' => number_format((float) ($row->usd ?? 0), 4, '.', ''),
            'credits' => (int) ($row->credits ?? 0),
            'calls' => (int) ($row->calls ?? 0),
        ];
    }

    /** @param array{partner: int, learner: int, phrases: int, fillers: int, words: int} $owed */
    private static function kinds(array $owed): string
    {
        return "partner lines {$owed['partner']}, learner lines {$owed['learner']}, phrases {$owed['phrases']}, fillers {$owed['fillers']}, words {$owed['words']}";
    }

    private static function balanceLine(string $label, VoiceBalance $balance): string
    {
        return sprintf(
            '%s: %d of %d credits left (%s)%s',
            $label,
            $balance->remaining(),
            $balance->limit,
            $balance->source === VoiceBalance::VENDOR ? 'the vendor\'s count' : 'this app\'s count this month',
            $balance->resetsAt === null ? '' : ', resets '.$balance->resetsAt->format('Y-m-d H:i').' UTC',
        );
    }
}
