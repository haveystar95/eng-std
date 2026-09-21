<?php

declare(strict_types=1);

namespace App\Modules\Plan\Application\Service;

use App\Modules\Plan\Application\Dto\ConversationMaterialView;
use App\Modules\Plan\Application\Dto\ConversationSceneView;
use App\Modules\Plan\Application\Dto\ConversationSummaryView;
use App\Modules\Plan\Application\Dto\ConversationTurnView;
use App\Modules\Plan\Application\Dto\ConversationView;
use App\Modules\Plan\Domain\Entity\Conversation;
use App\Modules\Plan\Domain\Entity\ConversationTurn;
use App\Modules\Plan\Domain\Repository\StagePassageRepository;
use App\Modules\Plan\Domain\Service\ConversationOutcomes;
use App\Modules\Plan\Domain\Service\ConversationRules;
use App\Modules\Plan\Domain\Service\IntentClause;
use App\Modules\Plan\Domain\ValueObject\Stage;

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
            turns: array_map(fn (ConversationTurn $turn): ConversationTurnView => self::turn($turn, $material), $talk->turns()),
            summary: $talk->isEnded() ? $this->summary($talk, $material, $replay) : null,
            talkTitleNative: $material->titleNative,
            targets: self::targets($material, $talk),
            replay: $replay,
        );
    }

    /**
     * «СКАЖИ В РАЗГОВОРЕ» ON THE WIRE — `{scene_id, ref, text_target, text_native, said}` for every target of the day's
     * talk (наряд CONV-2, п. 10), `said` by the talk given: the talk's own document and the talk's row of the day window
     * (`window.stages[].targets`, наряд BACK-TAILS-2 §4) print ONE list — the day's material's, the one `POST
     * …/conversation` starts the talk with — and no second set exists. No talk yet: nothing is said.
     *
     * @return list<array{scene_id: string, ref: string, text_target: string, text_native: string, said: bool}>
     */
    public static function targets(ConversationMaterialView $material, ?Conversation $talk): array
    {
        $heard = $talk === null ? [] : ConversationOutcomes::heard($talk);

        return array_map(static fn ($target): array => [
            'scene_id' => $target->sceneId,
            'ref' => $target->ref,
            'text_target' => $target->textTarget,
            'text_native' => $target->textNative,
            'said' => isset($heard[$target->id()]),
        ], $material->targets);
    }

    /**
     * The summary of a finished talk — the projection of its journal over its targets, never a stored second count.
     * A replay gives nothing back tomorrow: the day's result is the talk that walked its stage (наряд CONV-2, п. 2).
     */
    public function summary(Conversation $talk, ConversationMaterialView $material, bool $replay = false): ConversationSummaryView
    {
        $outcome = ConversationOutcomes::of($talk, $material->targets);
        $used = array_fill_keys($outcome->phrasesUsed, true);

        $phrases = [];
        foreach ($material->targets as $phrase) {
            $phrases[] = [
                'scene_id' => $phrase->sceneId,
                'ref' => $phrase->ref,
                'text_target' => $phrase->textTarget,
                'text_native' => $phrase->textNative,
                'audio_id' => null,
                'used' => isset($used[$phrase->id()]),
            ];
        }

        return new ConversationSummaryView(
            saidCount: $outcome->saidCount,
            phrasesUsed: $outcome->phrasesUsedCount(),
            phrasesTotal: $outcome->phrasesTotal,
            phrases: $phrases,
            understoodAll: $outcome->understoodAll,
            notUnderstood: $outcome->notUnderstood,
            rescues: $outcome->rescues,
            endedReason: $outcome->endedReason?->value,
            minutes: $outcome->minutes,
            returnsTomorrow: $talk->type()->returnsTomorrow() && ! $replay,
        );
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
        $phrases = [];
        foreach ($turn->phrasesUsed as $id) {
            $parts = explode(':', $id, 2);
            if (count($parts) === 2) {
                $phrase = $material->phrase($id);
                $phrases[] = [
                    'scene_id' => $parts[0],
                    'ref' => $parts[1],
                    'text_target' => $phrase?->textTarget,
                    'text_native' => $phrase?->textNative,
                ];
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
