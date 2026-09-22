<?php

declare(strict_types=1);

namespace App\Modules\Plan\Application\Service;

use App\Modules\Plan\Application\Dto\LineAudioRow;
use App\Modules\Plan\Application\Dto\LineToSay;
use App\Modules\Plan\Application\Dto\SceneVoiceDebt;
use App\Modules\Plan\Application\Port\LineAudioStore;
use App\Modules\Plan\Application\Port\LineSpeaker;
use App\Modules\Plan\Application\Port\SceneLocator;
use App\Modules\Plan\Domain\Entity\PlanTerm;
use App\Modules\Plan\Domain\Repository\PlanRepository;
use App\Modules\Plan\Domain\Repository\PlanTermRepository;
use App\Modules\Plan\Domain\Service\SpokenLines;
use App\Modules\Plan\Domain\ValueObject\PlanSceneId;
use App\Modules\Plan\Domain\ValueObject\Speaker;
use App\Modules\Plan\Domain\ValueObject\TermKind;

/**
 * WHAT THE SERVER'S VOICE STILL OWES A SCENE — all of it (DAY-UI-3, TTS-2).
 *
 * Canon: every line of the dialogue — the partner's and the learner's — every phrase, every phrase with each of its
 * other fillers and every word is voiced by the server, each line on a call of its own in the voice its speaker has in
 * the scene. What is owed is whatever the store does not have in that voice; a filler its phrase already says is not
 * owed at all.
 *
 * Null when there is nothing to ask: no plan, no lesson yet, or no voice for the language (speech off).
 */
final readonly class SceneVoiceQueue
{
    public function __construct(
        private SceneLocator $scenes,
        private PlanRepository $plans,
        private PlanTermRepository $terms,
        private LineSpeaker $speaker,
        private LineAudioStore $store,
        private VoiceCasts $casts,
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
        $cast = $this->casts->ofScene($scene, $plan->userId());
        $keys = [];
        foreach ([Speaker::Partner, Speaker::Learner] as $speaker) {
            $key = $this->speaker->voiceKeyFor($lang, $speaker, $cast->genderOf($speaker));
            if ($key === null) {
                return null;
            }
            $keys[$speaker->value] = $key;
        }
        $have = $this->store->forScenes([$sceneId->value], array_values(array_unique($keys)));
        $missing = static fn (string $ref, Speaker $speaker): bool => ! isset($have[$sceneId->value.':'.$ref.':'.$keys[$speaker->value]]);

        $lines = [];
        $count = ['partner' => 0, 'learner' => 0, 'phrases' => 0, 'fillers' => 0, 'words' => 0];
        foreach (SpokenLines::dialogue($lesson) as $line) {
            if ($missing($line['ref'], $line['speaker'])) {
                $lines[] = new LineToSay($line['ref'], $line['text'], $line['speaker'], $cast->genderOf($line['speaker']));
                $count[$line['speaker'] === Speaker::Partner ? 'partner' : 'learner']++;
            }
        }

        $learnerLine = static fn (string $ref, string $text): LineToSay => new LineToSay($ref, $text, Speaker::Learner, $cast->learner());
        $terms = $this->terms->forScene($sceneId);
        foreach (SpokenLines::terms($terms, phrases: true) as $phrase) {
            if ($missing($phrase['ref'], Speaker::Learner)) {
                $lines[] = $learnerLine($phrase['ref'], $phrase['text']);
                $count['phrases']++;
            }
        }
        foreach ($terms as $term) {
            foreach (self::fillersOf($term) as $filler) {
                if ($filler['voicedAs'] === $filler['ref'] && $missing($filler['ref'], Speaker::Learner)) {
                    $lines[] = $learnerLine($filler['ref'], $filler['text']);
                    $count['fillers']++;
                }
            }
        }
        foreach (SpokenLines::terms($terms, phrases: false) as $word) {
            if ($missing($word['ref'], Speaker::Learner)) {
                $lines[] = $learnerLine($word['ref'], $word['text']);
                $count['words']++;
            }
        }

        return new SceneVoiceDebt(
            lang: $lang,
            cast: $cast,
            lines: $lines,
            partnerLines: $count['partner'],
            learnerLines: $count['learner'],
            phrases: $count['phrases'],
            fillers: $count['fillers'],
            words: $count['words'],
        );
    }

    /**
     * The lines of a scene filed under a voice their speaker no longer has in it — a voice of the pack changed (TTS-2;
     * the voice is a key of the file, DECISIONS п. 248), or the learner's profile says another gender than the one the
     * learner's lines were bought in (наряд FIX-3 §1) — so no reader finds them and the speaker's lines are owed anew.
     * Nothing is unread when the scene cannot say which voice is right: no lesson, speech off, a voice missing from the
     * pack — a switched-off voice must never read as «every file is stale».
     *
     * @return list<LineAudioRow>
     */
    public function unread(PlanSceneId $sceneId): array
    {
        $planId = $this->scenes->planIdOf($sceneId);
        $plan = $planId === null ? null : $this->plans->findById($planId);
        $scene = $plan?->scene($sceneId);
        if ($plan === null || $scene === null || ! $scene->hasLesson()) {
            return [];
        }
        $cast = $this->casts->ofScene($scene, $plan->userId());
        $keys = [];
        foreach ([Speaker::Partner, Speaker::Learner] as $speaker) {
            $key = $this->speaker->voiceKeyFor($plan->targetLang()->value, $speaker, $cast->genderOf($speaker));
            if ($key === null) {
                return [];
            }
            $keys[$speaker->value] = $key;
        }

        return array_values(array_filter(
            $this->store->ofScene($sceneId),
            static fn (LineAudioRow $row): bool => $row->voiceKey !== $keys[SpokenLines::speakerOf($row->lineRef)->value],
        ));
    }

    /** @return list<array{index: int, ref: string, text: string, voicedAs: string}> */
    private static function fillersOf(PlanTerm $term): array
    {
        return $term->kind() === TermKind::Phrase ? SpokenLines::fillers($term) : [];
    }
}
