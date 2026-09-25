<?php

declare(strict_types=1);

namespace App\Modules\Plan\Application\Service;

use App\Modules\Plan\Application\Dto\ConversationHintView;
use App\Modules\Plan\Application\Dto\ConversationMaterialView;
use App\Modules\Plan\Application\Dto\ConversationSceneView;
use App\Modules\Plan\Application\Dto\ConversationSummaryView;
use App\Modules\Plan\Application\Dto\ConversationTurnView;
use App\Modules\Plan\Application\Dto\ConversationView;
use App\Modules\Plan\Domain\Check\Language\LanguagePack;
use App\Modules\Plan\Domain\Check\Language\LanguagePacks;
use App\Modules\Plan\Domain\Entity\Conversation;
use App\Modules\Plan\Domain\Entity\ConversationTurn;
use App\Modules\Plan\Domain\Repository\StagePassageRepository;
use App\Modules\Plan\Domain\Service\ConversationLead;
use App\Modules\Plan\Domain\Service\ConversationOutcomes;
use App\Modules\Plan\Domain\Service\ConversationRules;
use App\Modules\Plan\Domain\Service\FrameJudge;
use App\Modules\Plan\Domain\ValueObject\ConversationPhrase;
use App\Modules\Plan\Domain\ValueObject\Stage;
use App\Modules\Plan\Domain\ValueObject\TurnKind;

/**
 * THE TALK AS ONE DOCUMENT. Every number the frames print is counted here — turns left, minutes,
 * how many phrases of the plan sounded — because the client «складывает слова и не выводит ни одного
 * числа», the rule the day window was written under.
 */
final readonly class ConversationViews
{
    public function __construct(
        private ConversationRules $rules,
        private StagePassageRepository $passages,
        private LanguagePacks $packs,
        private FrameJudge $judge = new FrameJudge,
    ) {}

    public function of(Conversation $talk, ConversationMaterialView $material): ConversationView
    {
        $current = $material->checkpoint($talk->currentCheckpoint());
        $done = $talk->checkpointsDone();
        $replay = $this->isReplay($talk);

        $scenes = [];
        foreach ($material->checkpoints as $checkpoint) {
            $scenes[] = new ConversationSceneView(
                sceneId: $checkpoint->sceneId,
                titleNative: $checkpoint->titleNative,
                titleTarget: $checkpoint->titleTarget,
                roleNative: $checkpoint->roleNative,
                roleTarget: $checkpoint->roleTarget,
                lines: count($checkpoint->keyLines),
                state: match (true) {
                    in_array($checkpoint->sceneId, $done, true) => ConversationSceneView::DONE,
                    $checkpoint->sceneId === $current?->sceneId => ConversationSceneView::CURRENT,
                    default => ConversationSceneView::LOCKED,
                },
            );
        }

        return new ConversationView(
            id: $talk->id()->value,
            planId: $talk->planId()->value,
            day: $talk->dayNumber(),
            type: $talk->type()->value,
            state: $talk->state()->value,
            partnerRoleNative: $current->roleNative ?? '',
            partnerRoleTarget: $current->roleTarget ?? '',
            sceneTitleNative: $current->titleNative ?? '',
            sceneTitleTarget: $current->titleTarget ?? '',
            scenes: $scenes,
            minutesEstimate: $this->rules->minutesFor($talk->type()),
            turnsLeft: $talk->turnsLeft(),
            hintsEnabled: $talk->hintsEnabled(),
            hintDelayMs: $this->rules->hintDelayMs,
            hint: $this->hint($talk, $material),
            turns: array_map(static fn (ConversationTurn $turn): ConversationTurnView => self::turn($turn, $material), $talk->turns()),
            summary: $talk->isEnded() ? $this->summary($talk, $material, $replay) : null,
            talkTitleNative: $material->titleNative,
            targets: $this->targets($material, $talk),
            replay: $replay,
            extraSaid: $this->extraSaid($material, $talk),
        );
    }

    /**
     * «СКАЖИ В РАЗГОВОРЕ» ON THE WIRE — every target of the day's talk as a CONSTRUCTION (наряд FIX-3 §6): `{scene_id,
     * ref, frame_target, frame_native, example_target, example_native, said, value_target, state}` — the frame with its
     * window, the lesson's value (grey in the window on the screen), whether it has been said, what the learner put in its
     * window when they said it (null until then; for a frame without a window — null too), and where it stands: `none`,
     * `almost` — said with one word off, not closed —, `said` (наряд FIX-4 §2). `said` and the value by the talk given:
     * the talk's own document, its summary and the talk's row of the day window (`window.stages[].targets`) print ONE list
     * — the day's material's, the one `POST …/conversation` starts the talk with. No talk yet: nothing is said.
     *
     * @return list<array{scene_id: string, ref: string, frame_target: string, frame_native: string, example_target: string|null, example_native: string|null, said: bool, value_target: string|null, state: string}>
     */
    public function targets(ConversationMaterialView $material, ?Conversation $talk): array
    {
        $pack = $this->packs->for($material->targetLang);

        return array_map(fn (ConversationPhrase $target): array => $this->construction($target, $talk, $material, $pack) + [
            'state' => $talk === null ? 'none' : ConversationOutcomes::stateOf($talk, $target->id())->value,
        ], $material->targets);
    }

    /**
     * «ЕЩЁ ВСПОМНИЛ» (наряд FIX-4 §2): the constructions of the talk's scenes said that are no target — in the order they
     * were said, each with what went into its window. In the summary; never a target.
     *
     * @return list<array{scene_id: string, ref: string, frame_target: string, frame_native: string, example_target: string|null, example_native: string|null, said: bool, value_target: string|null}>
     */
    public function extraSaid(ConversationMaterialView $material, Conversation $talk): array
    {
        $pack = $this->packs->for($material->targetLang);
        $out = [];
        foreach (ConversationOutcomes::of($talk, $material->targets)->extraSaid as $id) {
            $phrase = $material->phrase($id);
            if ($phrase !== null) {
                $out[] = $this->construction($phrase, $talk, $material, $pack);
            }
        }

        return $out;
    }

    /**
     * The summary of a finished talk — the projection of its journal over its targets, never a stored second count.
     * A replay gives nothing back tomorrow: the day's result is the talk that walked its stage (наряд CONV-2, п. 2).
     */
    public function summary(Conversation $talk, ConversationMaterialView $material, bool $replay = false): ConversationSummaryView
    {
        $outcome = ConversationOutcomes::of($talk, $material->targets);

        return new ConversationSummaryView(
            saidCount: $outcome->saidCount,
            phrasesUsed: $outcome->phrasesUsedCount(),
            phrasesTotal: $outcome->phrasesTotal,
            phrases: $this->targets($material, $talk),
            understoodAll: $outcome->understoodAll,
            notUnderstood: $outcome->notUnderstood,
            rescues: $outcome->rescues,
            endedReason: $outcome->endedReason?->value,
            minutes: $outcome->minutes,
            returnsTomorrow: $talk->type()->returnsTomorrow() && ! $replay,
            extraSaid: $this->extraSaid($material, $talk),
            endedByLimit: $talk->endedByLimit(),
        );
    }

    /**
     * THE HINT OF THE LEARNER'S NEXT MOVE (наряд FIX-4 §5), read off the journal by the rule the line was written with
     * ({@see ConversationLead::hint()}): one target of the scene the role's last line is said in — the one the move before
     * it said almost, then with its exact line; else the one the line opened; else the first not said. `sentence` is its
     * sentence in the learner's language as the lesson has it («У меня есть боль в плече.», наряд FIX-4b §2); the clause
     * of «Скажи, что …» the build (20) read (`native`) is gone (наряд ACC-1 §5). None when it is not the learner's move,
     * when the scene has nothing left to say, when the talk is over.
     *
     * «БЕЗ ПОДСКАЗОК» TOO (наряд FIX-4c §2): the document carries the hint whatever the mode — `hints.enabled` says the
     * mode, and the phone hides the plate itself until «Подсказать». A phone that had to pick the target on its own in
     * that mode did not know which one the role's line led to (`opens` is not on the wire).
     */
    private function hint(Conversation $talk, ConversationMaterialView $material): ?ConversationHintView
    {
        $line = $talk->lastAgentTurn();
        if (! $talk->awaitsLearner() || $line === null) {
            return null;
        }
        $scene = $line->sceneId ?? $material->checkpoint($talk->currentCheckpoint())?->sceneId;
        if ($scene === null) {
            return null;
        }
        $hint = ConversationLead::hint(
            $material->targetsOf($scene),
            ConversationOutcomes::heard($talk),
            ConversationLead::moveBefore($talk, $line)->phrasesAlmost ?? [],
            $line->opensTarget,
        );
        if ($hint === null || trim($hint['target']->lineNative) === '') {
            return null;
        }
        $target = $hint['target'];

        return new ConversationHintView(
            sentence: trim($target->lineNative),
            target: $hint['exact'] ? $target->lineTarget : null,
            sceneId: $target->sceneId,
            ref: $target->ref,
        );
    }

    /**
     * One construction on the wire, whether it has been said and what the learner put in its window.
     *
     * @return array{scene_id: string, ref: string, frame_target: string, frame_native: string, example_target: string|null, example_native: string|null, said: bool, value_target: string|null}
     */
    private function construction(ConversationPhrase $phrase, ?Conversation $talk, ConversationMaterialView $material, LanguagePack $pack): array
    {
        $said = $talk !== null && isset(ConversationOutcomes::heard($talk)[$phrase->id()]);

        return [
            'scene_id' => $phrase->sceneId,
            'ref' => $phrase->ref,
            'frame_target' => $phrase->frameTarget,
            'frame_native' => $phrase->frameNative,
            'example_target' => $phrase->exampleTarget,
            'example_native' => $phrase->exampleNative,
            'said' => $said,
            'value_target' => $said ? $this->valueIn($talk, $phrase, $material, $pack) : null,
        ];
    }

    /**
     * What the learner put in the window of a construction they said — read again off the move that said it, by the judge
     * that heard it ({@see FrameJudge}); the journal keeps the words, not a second copy of them. The move is read with
     * every construction it said, as it was judged: a window ends where the next construction glued to it begins (наряд
     * FIX-4b §1 — «This is ___» of «this is my first visit and I have about a year of experience» is «my first visit»).
     */
    private function valueIn(Conversation $talk, ConversationPhrase $phrase, ConversationMaterialView $material, LanguagePack $pack): ?string
    {
        foreach ($talk->turns() as $turn) {
            if ($turn->kind === TurnKind::Said && in_array($phrase->id(), $turn->phrasesUsed, true)) {
                $said = array_values(array_filter(array_map($material->phrase(...), $turn->phrasesUsed)));

                return $this->judge->move((string) $turn->textTarget, $said, $pack)->values[$phrase->id()] ?? null;
            }
        }

        return null;
    }

    /**
     * IS THIS TALK «ЕЩЁ РАЗ» ON A WALKED DAY — the day's sixth stage was walked by ANOTHER talk, one that had ended by
     * the time this one began (наряд CONV-2, п. 2). A talk begun before the stage was walked is not a replay: it may
     * yet be the one that walks it, or it was cut by «Ещё раз» itself.
     */
    private function isReplay(Conversation $talk): bool
    {
        $passage = $this->passages->of($talk->dayId(), Stage::Conversation);

        return $passage !== null
            && ! $passage->conversationId?->equals($talk->id())
            && $passage->passedAt <= $talk->startedAt();
    }

    private static function turn(ConversationTurn $turn, ConversationMaterialView $material): ConversationTurnView
    {
        $targets = [];
        $extra = [];
        foreach ($turn->phrasesUsed as $id) {
            $parts = explode(':', $id, 2);
            if (count($parts) === 2) {
                $pair = ['scene_id' => $parts[0], 'ref' => $parts[1]];
                if ($material->isTarget($id)) {
                    $targets[] = $pair;
                } else {
                    $extra[] = $pair;
                }
            }
        }

        return new ConversationTurnView(
            index: $turn->index,
            speaker: $turn->speaker()->value,
            kind: $turn->kind->value,
            textTarget: $turn->textTarget,
            textNative: $turn->textNative,
            audioId: $turn->audio === null ? null : $turn->id->value,
            audioDurationMs: $turn->audio?->durationMs,
            understood: $turn->understood,
            phrasesUsed: $targets,
            offTopic: $turn->offTopic,
            createdAt: $turn->createdAt->format(DATE_ATOM),
            sceneId: $turn->sceneId,
            sceneEvent: $turn->sceneEvent?->value,
            extraSaid: $extra,
        );
    }
}
