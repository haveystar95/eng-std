<?php

declare(strict_types=1);

namespace App\Modules\Plan\Application\Service;

use App\Modules\Plan\Application\Dto\ConversationMaterialView;
use App\Modules\Plan\Domain\Check\Language\LanguagePacks;
use App\Modules\Plan\Domain\Check\Language\SentenceEnds;
use App\Modules\Plan\Domain\Entity\Plan;
use App\Modules\Plan\Domain\Entity\PlanDay;
use App\Modules\Plan\Domain\Entity\PlanScene;
use App\Modules\Plan\Domain\Entity\PlanTerm;
use App\Modules\Plan\Domain\Lesson\Exchange;
use App\Modules\Plan\Domain\Lesson\Filler;
use App\Modules\Plan\Domain\Repository\PlanTermRepository;
use App\Modules\Plan\Domain\Service\FrameText;
use App\Modules\Plan\Domain\Service\NativeStrings;
use App\Modules\Plan\Domain\Service\SpokenLines;
use App\Modules\Plan\Domain\ValueObject\ConversationCheckpoint;
use App\Modules\Plan\Domain\ValueObject\ConversationPhrase;
use App\Modules\Plan\Domain\ValueObject\DayType;
use App\Modules\Plan\Domain\ValueObject\ExchangeKind;
use App\Modules\Plan\Domain\ValueObject\PlanSceneId;
use App\Modules\Plan\Domain\ValueObject\TermKind;

/**
 * WHICH SCENES A TALK WALKS AND WHAT IT LISTENS FOR (наряд CONV-1) — the one place that decides it,
 * the way {@see DayDealer} is the one place that decides which scenes a review or a rehearsal deals.
 *
 * - a scene day: its own scene, three or four turns (кадр 37-5, «Разговор · Приём у врача»);
 * - the rehearsal: every ready scene of the plan, in the plan's order — «Разговор целиком · 3 сцены»;
 * - a review day: the scenes of the two scene days it repeats.
 *
 * The phrases are the plan's own phrases of those scenes as CONSTRUCTIONS (наряд FIX-3 §6): the frame with its window,
 * the value the lesson says it with and the lesson's sentence of it — the learner came to say the frame with a value of
 * their own, and the talk ticks it by {@see \App\Modules\Plan\Domain\Service\FrameJudge} (наряд FIX-4 §2). Four to seven
 * of them are the talk's targets (наряд CONV-2, п. 10), and its entry title is «Поговори с …» the role it opens with (п. 12).
 */
final readonly class ConversationMaterial
{
    public function __construct(private PlanTermRepository $terms, private LanguagePacks $packs) {}

    public function for(Plan $plan, PlanDay $day): ConversationMaterialView
    {
        $scenes = $this->scenesOf($plan, $day);
        if ($scenes === []) {
            return new ConversationMaterialView([], []);
        }

        $terms = $this->terms->forScenes(array_map(static fn (PlanScene $s): PlanSceneId => $s->id(), $scenes));
        $ends = $this->packs->for($plan->targetLang()->value)->sentenceEnds();

        $checkpoints = [];
        $phrases = [];
        foreach ($scenes as $scene) {
            $checkpoints[] = new ConversationCheckpoint(
                sceneId: $scene->id()->value,
                titleNative: $scene->titleNative(),
                titleTarget: $scene->titleTarget(),
                aboutNative: $scene->teachesNative(),
                roleTarget: $scene->partnerRoleTarget(),
                roleNative: $scene->partnerRoleNative(),
                partnerGender: $scene->partnerVoiceGender() ?? PlanScene::DEFAULT_PARTNER_VOICE,
                keyLines: self::keyLines($scene),
            );
            foreach ($terms[$scene->id()->value] ?? [] as $term) {
                if ($term->kind() !== TermKind::Phrase) {
                    continue;
                }
                $frame = $term->frame();
                $example = self::example($term, $ends);
                $phrases[] = new ConversationPhrase(
                    sceneId: $scene->id()->value,
                    ref: $term->ref(),
                    frameTarget: $frame->frameTarget ?? $term->textTarget(),
                    frameNative: $frame->frameNative ?? $term->textNative(),
                    exampleTarget: $example?->target,
                    exampleNative: $example?->native,
                    kind: $frame->kind ?? ExchangeKind::Answer,
                    // The lesson's own sentence of the construction — what the hint offers whole (наряд FIX-4 §5).
                    lineTarget: $term->textTarget(),
                    lineNative: $term->textNative(),
                );
            }
        }

        return new ConversationMaterialView(
            $checkpoints,
            $phrases,
            (new NativeStrings($plan->nativeLang()->value))->talkTitle($checkpoints[0]->roleNative),
            $plan->targetLang()->value,
        );
    }

    /**
     * The scenes the talk covers, in the order it walks them — ready ones only: a scene whose lesson
     * is not written has no lines to prepare and nothing for the role to lead with.
     *
     * @return list<PlanScene>
     */
    private function scenesOf(Plan $plan, PlanDay $day): array
    {
        if ($day->type() === DayType::Scene) {
            $scene = $plan->sceneOf($day);

            return $scene !== null && $scene->isReady() ? [$scene] : [];
        }

        if ($day->type() === DayType::Rehearsal) {
            $ready = array_values(array_filter($plan->scenes(), static fn (PlanScene $s): bool => $s->isReady()));
            usort($ready, static fn (PlanScene $a, PlanScene $b): int => $a->order() <=> $b->order());

            return $ready;
        }

        $out = [];
        foreach ($plan->sceneDaysBefore($day->number(), 2) as $sceneDay) {
            $id = $sceneDay->sceneId();
            $scene = $id === null ? null : $plan->scene($id);
            if ($scene !== null && $scene->isReady()) {
                $out[] = $scene;
            }
        }
        usort($out, static fn (PlanScene $a, PlanScene $b): int => $a->order() <=> $b->order());

        return $out;
    }

    /**
     * The learner's own lines of a scene, as the server assembles them — what the role is told the learner has come to
     * say — each with the exchange it stands in: who opens it (`ask` — the learner asks and the role answers; `answer` —
     * the role speaks and the learner answers) and the role's own line there, as the lesson wrote it (наряд CONV-2, п. 1).
     * A header that only called them «the lines the learner is preparing» left the model to guess the sides, and on the
     * owner's talks of 21.09 it guessed wrong from the first line. A rescue line is «попроси повторить», not a line to
     * lead towards.
     *
     * @return list<array{target: string, native: string, phrase_ref: string|null, kind: string, partner: string}>
     */
    private static function keyLines(PlanScene $scene): array
    {
        $lesson = $scene->lesson();
        if ($lesson === null) {
            return [];
        }
        $out = [];
        $seen = [];
        foreach ($lesson->exchanges as $exchange) {
            if (! self::says($exchange) || isset($seen[$exchange->step])) {
                continue;
            }
            $seen[$exchange->step] = true;
            $learner = $exchange->learner();
            if ($learner !== null) {
                $out[] = [
                    'target' => $learner->textTarget,
                    'native' => $learner->textNative,
                    'phrase_ref' => $learner->phraseId,
                    'kind' => $exchange->kind === ExchangeKind::Ask ? 'ask' : 'answer',
                    'partner' => $exchange->partner()->textTarget ?? '',
                ];
            }
        }

        return $out;
    }

    private static function says(Exchange $exchange): bool
    {
        return $exchange->kind !== ExchangeKind::Rescue && $exchange->learner() !== null && $exchange->partner() !== null;
    }

    /**
     * THE LESSON'S VALUE OF A CONSTRUCTION — the filler the phrase itself is said with (its own file, `voicedAs`), grey in
     * the window on the screen; a frame without a window has none.
     */
    private static function example(PlanTerm $term, ?SentenceEnds $ends): ?Filler
    {
        $frame = $term->frame();
        $fillers = $frame?->fillers() ?? [];
        if ($frame === null || $fillers === [] || ! FrameText::hasSlot($frame->frameTarget)) {
            return null;
        }
        foreach (SpokenLines::fillers($term, $ends) as $filler) {
            if ($filler['voicedAs'] === $term->ref() && isset($fillers[$filler['index']])) {
                return $fillers[$filler['index']];
            }
        }
        foreach ($fillers as $filler) {
            if ($filler->inDialogue) {
                return $filler;
            }
        }

        return $fillers[array_key_first($fillers)];
    }
}
