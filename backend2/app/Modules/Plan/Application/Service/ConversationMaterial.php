<?php

declare(strict_types=1);

namespace App\Modules\Plan\Application\Service;

use App\Modules\Plan\Application\Dto\ConversationMaterialView;
use App\Modules\Plan\Domain\Entity\Plan;
use App\Modules\Plan\Domain\Entity\PlanDay;
use App\Modules\Plan\Domain\Entity\PlanScene;
use App\Modules\Plan\Domain\Entity\PlanTerm;
use App\Modules\Plan\Domain\Lesson\Exchange;
use App\Modules\Plan\Domain\Repository\PlanTermRepository;
use App\Modules\Plan\Domain\Service\FrameParts;
use App\Modules\Plan\Domain\Service\NativeStrings;
use App\Modules\Plan\Domain\ValueObject\ConversationCheckpoint;
use App\Modules\Plan\Domain\ValueObject\ConversationPhrase;
use App\Modules\Plan\Domain\ValueObject\DayType;
use App\Modules\Plan\Domain\ValueObject\ExchangeKind;
use App\Modules\Plan\Domain\ValueObject\PlanSceneId;
use App\Modules\Plan\Domain\ValueObject\TermKind;
use App\Modules\Plan\Domain\ValueObject\VoiceCast;

/**
 * WHICH SCENES A TALK WALKS AND WHAT IT LISTENS FOR (наряд CONV-1) — the one place that decides it,
 * the way {@see DayDealer} is the one place that decides which scenes a review or a rehearsal deals.
 *
 * - a scene day: its own scene, three or four turns (кадр 37-5, «Разговор · Приём у врача»);
 * - the rehearsal: every ready scene of the plan, in the plan's order — «Разговор целиком · 3 сцены»;
 * - a review day: the scenes of the two scene days it repeats.
 *
 * The phrases are the plan's own phrases of those scenes, each with the KEY the server listens for:
 * the frame's words outside its window ({@see FrameParts::part()}) — the same key «Говорю сам» is
 * judged by, so «фраза дня прозвучала» means one thing across the product. Four to seven of them are
 * the talk's targets (наряд CONV-2, п. 10), and its entry title is «Поговори с …» the role it opens
 * with (п. 12).
 */
final readonly class ConversationMaterial
{
    public function __construct(private PlanTermRepository $terms) {}

    public function for(Plan $plan, PlanDay $day): ConversationMaterialView
    {
        $scenes = $this->scenesOf($plan, $day);
        if ($scenes === []) {
            return new ConversationMaterialView([], []);
        }

        $terms = $this->terms->forScenes(array_map(static fn (PlanScene $s): PlanSceneId => $s->id(), $scenes));

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
                partnerGender: VoiceCast::ofScene($scene)->partner,
                keyLines: self::keyLines($scene),
            );
            foreach ($terms[$scene->id()->value] ?? [] as $term) {
                if ($term->kind() !== TermKind::Phrase) {
                    continue;
                }
                $phrases[] = new ConversationPhrase(
                    sceneId: $scene->id()->value,
                    ref: $term->ref(),
                    key: self::keyOf($term),
                    textTarget: $term->textTarget(),
                    textNative: $term->textNative(),
                    audioRef: $term->ref(),
                );
            }
        }

        return new ConversationMaterialView(
            $checkpoints,
            $phrases,
            (new NativeStrings($plan->nativeLang()->value))->talkTitle($checkpoints[0]->roleNative),
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

    /** The key the phrase is listened for: the frame's words outside its window, or the phrase itself. */
    private static function keyOf(PlanTerm $term): string
    {
        $frame = $term->frame();
        $key = $frame === null ? '' : FrameParts::part($frame->frameTarget);

        return trim($key) === '' ? $term->textTarget() : $key;
    }
}
