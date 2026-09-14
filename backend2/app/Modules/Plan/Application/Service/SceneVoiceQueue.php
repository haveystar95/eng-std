<?php

declare(strict_types=1);

namespace App\Modules\Plan\Application\Service;

use App\Modules\Plan\Application\Dto\LineAudioRow;
use App\Modules\Plan\Application\Dto\LineToSay;
use App\Modules\Plan\Application\Dto\SceneVoiceDebt;
use App\Modules\Plan\Application\Dto\VoiceBatch;
use App\Modules\Plan\Application\Port\LineAudioStore;
use App\Modules\Plan\Application\Port\LineSpeaker;
use App\Modules\Plan\Application\Port\SceneLocator;
use App\Modules\Plan\Domain\Entity\PlanScene;
use App\Modules\Plan\Domain\Repository\PlanRepository;
use App\Modules\Plan\Domain\Repository\PlanTermRepository;
use App\Modules\Plan\Domain\Service\SpokenLines;
use App\Modules\Plan\Domain\ValueObject\PlanSceneId;
use App\Modules\Plan\Domain\ValueObject\Speaker;
use App\Modules\Plan\Domain\ValueObject\VoiceCast;
use App\Modules\Shared\Domain\ValueObject\VoiceGender;

/**
 * WHAT THE SERVER'S VOICE STILL OWES A SCENE — all of it (DAY-UI-3).
 *
 * Canon (owner, DAY-UI-3): every line of the dialogue — the partner's and the learner's — every phrase
 * and every word is voiced by the server, in the scene's two voices of different gender. The queue is
 * whatever the store does not have in the voice its speaker has in this scene, as CALLS: the dialogue
 * in one call with both voices (the whole of it, when anything in it is missing), the phrases in one,
 * the words in one — two when a scene has more words than a batch carries. Four calls a day at most:
 * the free vendor counts requests.
 *
 * Null when there is nothing to ask: no plan, no lesson yet, or no voice for the language (speech off).
 */
final readonly class SceneVoiceQueue
{
    /** Lines in one batch of words: a day has 8–16, and two calls stay inside four a day. */
    public const WORDS_PER_CALL = 12;

    public function __construct(
        private SceneLocator $scenes,
        private PlanRepository $plans,
        private PlanTermRepository $terms,
        private LineSpeaker $speaker,
        private LineAudioStore $store,
    ) {}

    public function owed(PlanSceneId $sceneId): ?SceneVoiceDebt
    {
        $planId = $this->scenes->planIdOf($sceneId);
        $plan = $planId === null ? null : $this->plans->findById($planId);
        $scene = $plan?->scene($sceneId);
        $lesson = $scene?->lesson();
        if ($plan === null || $scene === null || $lesson === null || ! $scene->hasLesson()) {
            return null;
        }
        $lang = $plan->targetLang()->value;
        $keys = [
            VoiceGender::Female->value => $this->speaker->voiceKeyFor($lang, VoiceGender::Female),
            VoiceGender::Male->value => $this->speaker->voiceKeyFor($lang, VoiceGender::Male),
        ];
        if (in_array(null, $keys, true)) {
            return null;
        }
        /** @var array<string, string> $keys */
        $have = $this->store->forScenes([$sceneId->value], array_values($keys));

        $cast = VoiceCast::of(SpokenLines::castOf(
            $scene->partnerVoiceGender(),
            $lesson->roleGender,
            self::learnerMaterial($have, $keys),
            PlanScene::DEFAULT_PARTNER_VOICE,
        ));
        $missing = static function (string $ref, Speaker $speaker) use ($have, $keys, $cast, $sceneId): bool {
            return ! isset($have[$sceneId->value.':'.$ref.':'.$keys[$cast->genderOf($speaker)->value]]);
        };

        $batches = [];
        $partner = 0;
        $learner = 0;
        $dialogue = [];
        $owedDialogue = [];
        foreach (SpokenLines::dialogue($lesson) as $line) {
            $dialogue[] = new LineToSay($line['ref'], $line['text'], $cast->genderOf($line['speaker']));
            if (! $missing($line['ref'], $line['speaker'])) {
                continue;
            }
            $owedDialogue[] = $line['ref'];
            if ($line['speaker'] === Speaker::Partner) {
                $partner++;
            } else {
                $learner++;
            }
        }
        if ($owedDialogue !== []) {
            $batches[] = new VoiceBatch(VoiceBatch::DIALOGUE, $dialogue, $owedDialogue);
        }

        $terms = $this->terms->forScene($sceneId);
        $phrases = self::owedTerms(SpokenLines::terms($terms, phrases: true), $cast, $missing);
        if ($phrases !== []) {
            $batches[] = new VoiceBatch(VoiceBatch::PHRASES, $phrases, array_map(static fn (LineToSay $l): string => $l->ref, $phrases));
        }
        $words = self::owedTerms(SpokenLines::terms($terms, phrases: false), $cast, $missing);
        foreach (array_chunk($words, self::WORDS_PER_CALL) as $chunk) {
            $batches[] = new VoiceBatch(VoiceBatch::WORDS, $chunk, array_map(static fn (LineToSay $l): string => $l->ref, $chunk));
        }

        return new SceneVoiceDebt(
            lang: $lang,
            cast: $cast,
            castIsNew: $scene->partnerVoiceGender() === null,
            batches: $batches,
            partnerLines: $partner,
            learnerLines: $learner,
            phrases: count($phrases),
            words: count($words),
        );
    }

    /**
     * @param  list<array{ref: string, text: string}>  $terms
     * @param  callable(string, Speaker): bool  $missing
     * @return list<LineToSay>
     */
    private static function owedTerms(array $terms, VoiceCast $cast, callable $missing): array
    {
        $out = [];
        foreach ($terms as $term) {
            if ($missing($term['ref'], Speaker::Learner)) {
                $out[] = new LineToSay($term['ref'], $term['text'], $cast->learner());
            }
        }

        return $out;
    }

    /**
     * How many of the learner's files — their lines, phrases, words — each of the pack's voices holds.
     *
     * @param  array<string, LineAudioRow>  $have
     * @param  array<string, string>  $keys  gender → voice key
     * @return array<string, int>
     */
    private static function learnerMaterial(array $have, array $keys): array
    {
        $out = array_fill_keys(array_keys($keys), 0);
        foreach ($have as $row) {
            $gender = array_search($row->voiceKey, $keys, true);
            if ($gender !== false && SpokenLines::speakerOf($row->lineRef) === Speaker::Learner) {
                $out[$gender]++;
            }
        }

        return $out;
    }
}
