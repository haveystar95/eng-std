<?php

declare(strict_types=1);

namespace App\Modules\Plan\Infrastructure\Console;

use App\Modules\Generation\Application\Port\SpeechAccountError;
use App\Modules\Generation\Application\Port\TransientSpeechError;
use App\Modules\Plan\Application\Command\DropUnreadVoice;
use App\Modules\Plan\Application\Command\DropUnreadVoiceHandler;
use App\Modules\Plan\Application\Command\VoiceScene;
use App\Modules\Plan\Application\Command\VoiceSceneHandler;
use App\Modules\Plan\Application\Dto\LineToSay;
use App\Modules\Plan\Application\Exception\VoiceCapReached;
use App\Modules\Plan\Application\Exception\VoiceFuseTripped;
use App\Modules\Plan\Application\Port\LineAudioStore;
use App\Modules\Plan\Application\Port\LineSpeaker;
use App\Modules\Plan\Application\Service\SceneVoiceQueue;
use App\Modules\Plan\Domain\Repository\PlanRepository;
use App\Modules\Plan\Domain\ValueObject\PlanId;
use App\Modules\Plan\Domain\ValueObject\Speaker;
use App\Modules\Shared\Domain\Service\SpeechCost;
use App\Modules\Shared\Domain\ValueObject\Ulid;
use DateTimeImmutable;
use Illuminate\Console\Command;

/**
 * `plan:revoice-learner --plan= [--scene=] [--apply]` — THE LEARNER'S VOICE BOUGHT IN THE LEARNER'S OWN GENDER (наряд
 * FIX-3 §1).
 *
 * The learner's lines, phrases and words are said in the gender of the learner's profile, the same on every scene
 * ({@see \App\Modules\Plan\Domain\ValueObject\VoiceCast}); a plan voiced before that — when the learner's voice was
 * «the partner's other gender» — has scenes whose learner files are in the other voice, and nobody reads them: the phone
 * says those lines with its own voice. This names what buying them anew would take — per scene: the learner's lines
 * owed, the characters, the vendor's credits and the dollars by the tariff's rate — and buys NOTHING unless `--apply`
 * is given: a purchase is the owner's decision, made after the price is named.
 *
 * With `--apply`, scene by scene, under one run of the credits cap and the fuse, exactly the way a fresh scene is voiced
 * ({@see VoiceSceneHandler}): what is owed is bought, and then the learner's files of the voice they no longer have are
 * dropped ({@see DropUnreadVoiceHandler}) — never before the new ones are there. A partner line owed as well is bought
 * with them (the scene is voiced whole) and named in the count.
 */
final class PlanRevoiceLearnerCommand extends Command
{
    protected $signature = 'plan:revoice-learner {--plan= : the plan id} {--scene=* : only these scenes of it} {--apply : buy what is owed (without it: only count and price)}';

    protected $description = 'Name — and with --apply buy — the learner lines of a plan owed in the learner\'s own voice';

    public function handle(
        PlanRepository $plans,
        SceneVoiceQueue $queue,
        LineSpeaker $speaker,
        LineAudioStore $store,
        VoiceSceneHandler $voice,
        DropUnreadVoiceHandler $drop,
    ): int {
        $planId = (string) $this->option('plan');
        if (! Ulid::isValid($planId)) {
            $this->error('Name the plan: --plan=<id>.');

            return self::FAILURE;
        }
        $plan = $plans->findById(PlanId::fromString($planId));
        if ($plan === null) {
            $this->error("No plan {$planId}.");

            return self::FAILURE;
        }
        /** @var list<string> $only */
        $only = array_values(array_filter((array) $this->option('scene'), static fn (mixed $id): bool => is_string($id) && $id !== ''));
        $apply = $this->option('apply') === true;
        $rate = (float) config('generation.speech.usd_per_thousand_credits', 0.20);

        $scenes = [];
        foreach ($plan->scenes() as $scene) {
            if ($scene->hasLesson() && ($only === [] || in_array($scene->id()->value, $only, true))) {
                $scenes[] = $scene->id();
            }
        }

        $total = ['learner' => 0, 'partner' => 0, 'characters' => 0, 'credits' => 0];
        foreach ($scenes as $sceneId) {
            $debt = $queue->owed($sceneId);
            if ($debt === null) {
                $this->line("сцена {$sceneId->value} — голоса нет (нет урока или озвучка выключена)");

                continue;
            }
            $learner = array_values(array_filter($debt->lines, static fn (LineToSay $l): bool => $l->speaker === Speaker::Learner));
            $characters = array_sum(array_map(static fn (LineToSay $l): int => SpeechCost::charactersOf($l->text), $debt->lines));
            $credits = $debt->lines === [] ? 0 : $speaker->creditsFor($debt->lang, $debt->lines);
            $total['learner'] += count($learner);
            $total['partner'] += count($debt->lines) - count($learner);
            $total['characters'] += $characters;
            $total['credits'] += $credits;
            $this->line(sprintf(
                'сцена %s · голос ученика %s — строк ученика: %d (реплик %d, фраз %d, наполнений %d, слов %d), собеседника: %d · %d симв. · %d кредитов · $%s',
                $sceneId->value, $debt->cast->learner()->value, count($learner), $debt->learnerLines, $debt->phrases, $debt->fillers, $debt->words,
                $debt->partnerLines, $characters, $credits, number_format($credits / 1000 * $rate, 4, '.', ''),
            ));
        }
        $this->info(sprintf(
            'план %s: строк ученика %d, собеседника %d · %d символов · %d кредитов · $%s%s',
            $planId, $total['learner'], $total['partner'], $total['characters'], $total['credits'],
            number_format($total['credits'] / 1000 * $rate, 4, '.', ''), $apply ? '' : ' — ничего не куплено (покупка — только с --apply)',
        ));
        if (! $apply || $total['learner'] + $total['partner'] === 0) {
            return self::SUCCESS;
        }

        $started = new DateTimeImmutable();
        $dropped = 0;
        try {
            foreach ($scenes as $sceneId) {
                $voice(new VoiceScene($sceneId, $store->creditsSince($started)));
                $owed = $queue->owed($sceneId);
                // The old files go only once the new ones are there: a scene bought in part keeps the voice it had.
                if ($owed !== null && $owed->isSettled()) {
                    $dropped += $drop(new DropUnreadVoice($sceneId));
                }
            }
        } catch (SpeechAccountError $e) {
            $this->error("The voice vendor account refused ({$e->httpStatus} {$e->vendorCode}) — stopped: {$e->getMessage()}");
        } catch (TransientSpeechError $e) {
            $this->warn('The vendor is busy — run the command again later: '.$e->getMessage());
        } catch (VoiceCapReached $e) {
            $this->error('Stopped by the credits cap — '.$e->getMessage());
        } catch (VoiceFuseTripped $e) {
            $this->warn('Stopped by the voice fuse — '.$e->getMessage());
        }
        $this->info(sprintf('куплено кредитов: %d · файлов прежнего голоса удалено: %d', $store->creditsSince($started), $dropped));

        return self::SUCCESS;
    }
}
