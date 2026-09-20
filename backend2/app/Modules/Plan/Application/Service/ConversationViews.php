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
use App\Modules\Plan\Domain\Service\ConversationOutcomes;
use App\Modules\Plan\Domain\Service\ConversationRules;

/**
 * THE TALK AS ONE DOCUMENT. Every number the frames print is counted here — turns left, minutes,
 * how many phrases of the plan sounded — because the client «складывает слова и не выводит ни одного
 * числа», the rule the day window was written under.
 */
final readonly class ConversationViews
{
    public function __construct(private ConversationRules $rules) {}

    public function of(Conversation $talk, ConversationMaterialView $material): ConversationView
    {
        $current = $material->checkpoint($talk->currentCheckpoint());
        $done = $talk->checkpointsDone();

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
            hintNative: $talk->hintNative(),
            turns: array_map(self::turn(...), $talk->turns()),
            summary: $talk->isEnded() ? $this->summary($talk, $material) : null,
        );
    }

    /** The summary of a finished talk — the projection of its journal, never a stored second count. */
    public function summary(Conversation $talk, ConversationMaterialView $material): ConversationSummaryView
    {
        $outcome = ConversationOutcomes::of($talk, $material->phrases);
        $used = array_fill_keys($outcome->phrasesUsed, true);

        $phrases = [];
        foreach ($material->phrases as $phrase) {
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
            returnsTomorrow: $talk->type()->returnsTomorrow(),
        );
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
