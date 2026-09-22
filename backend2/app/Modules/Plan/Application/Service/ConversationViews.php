<?php

declare(strict_types=1);

namespace App\Modules\Plan\Application\Service;

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
use App\Modules\Plan\Domain\Service\ConversationOutcomes;
use App\Modules\Plan\Domain\Service\ConversationRules;
use App\Modules\Plan\Domain\Service\IntentClause;
use App\Modules\Plan\Domain\Service\PhraseUse;
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
        private PhraseUse $phrases = new PhraseUse,
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

        $hint = $talk->hintNative();

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
            // The part after «Скажи, что …» (наряд CONV-2, п. 11) — read as a clause here, so the lines stored before
            // the rule came out the same way as the new ones.
            hintNative: $hint === null ? null : IntentClause::of($hint),
            turns: array_map(static fn (ConversationTurn $turn): ConversationTurnView => self::turn($turn), $talk->turns()),
            summary: $talk->isEnded() ? $this->summary($talk, $material, $replay) : null,
            talkTitleNative: $material->titleNative,
            targets: $this->targets($material, $talk),
            replay: $replay,
        );
    }

    /**
     * «СКАЖИ В РАЗГОВОРЕ» ON THE WIRE — every target of the day's talk as a CONSTRUCTION (наряд FIX-3 §6): `{scene_id,
     * ref, frame_target, frame_native, example_target, example_native, said, value_target}` — the frame with its window,
     * the lesson's value (grey in the window on the screen), whether it has been said, and what the learner put in its
     * window when they said it (null until then; for a frame without a window — null too). `said` and the value by the
     * talk given: the talk's own document, its summary and the talk's row of the day window (`window.stages[].targets`)
     * print ONE list — the day's material's, the one `POST …/conversation` starts the talk with. No talk yet: nothing is
     * said.
     *
     * @return list<array{scene_id: string, ref: string, frame_target: string, frame_native: string, example_target: string|null, example_native: string|null, said: bool, value_target: string|null}>
     */
    public function targets(ConversationMaterialView $material, ?Conversation $talk): array
    {
        $heard = $talk === null ? [] : ConversationOutcomes::heard($talk);
        $pack = $this->packs->for($material->targetLang);

        return array_map(fn (ConversationPhrase $target): array => [
            'scene_id' => $target->sceneId,
            'ref' => $target->ref,
            'frame_target' => $target->frameTarget,
            'frame_native' => $target->frameNative,
            'example_target' => $target->exampleTarget,
            'example_native' => $target->exampleNative,
            'said' => isset($heard[$target->id()]),
            'value_target' => $talk === null || ! isset($heard[$target->id()]) ? null : $this->valueIn($talk, $target, $pack),
        ], $material->targets);
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
        );
    }

    /**
     * What the learner put in the window of a target they said — read again off the move that said it, by the rule that
     * heard it ({@see PhraseUse::valueOf()}); the journal keeps the words, not a second copy of them.
     */
    private function valueIn(Conversation $talk, ConversationPhrase $target, LanguagePack $pack): ?string
    {
        foreach ($talk->turns() as $turn) {
            if ($turn->kind === TurnKind::Said && in_array($target->id(), $turn->phrasesUsed, true)) {
                return $this->phrases->valueOf((string) $turn->textTarget, $target, $pack);
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

    private static function turn(ConversationTurn $turn): ConversationTurnView
    {
        $phrases = [];
        foreach ($turn->phrasesUsed as $id) {
            $parts = explode(':', $id, 2);
            if (count($parts) === 2) {
                $phrases[] = ['scene_id' => $parts[0], 'ref' => $parts[1]];
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
            phrasesUsed: $phrases,
            offTopic: $turn->offTopic,
            createdAt: $turn->createdAt->format(DATE_ATOM),
        );
    }
}
