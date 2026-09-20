<?php

declare(strict_types=1);

namespace App\Modules\Plan\Application\Dto;

/**
 * THE TALK, AS THE CLIENT DRAWS IT (наряд CONV-1, кадры 37-5…37-12) — everything counted here, the
 * client words nothing and counts nothing.
 *
 * The whole ribbon travels every time: a talk is a dozen short lines, and a client that lost the
 * connection mid-turn (кадр 37-10) gets the same document from `GET …/conversation/{id}` as from
 * the move that dropped — there is no second, thinner shape to keep in step with this one.
 */
final readonly class ConversationView
{
    /**
     * @param  list<ConversationSceneView>  $scenes
     * @param  list<ConversationTurnView>  $turns
     */
    public function __construct(
        public string $id,
        public string $planId,
        public int $day,
        public string $type,
        public string $state,
        public string $partnerRoleNative,
        public string $partnerRoleTarget,
        public string $sceneTitleNative,
        public string $sceneTitleTarget,
        public array $scenes,
        public int $minutesEstimate,
        public int $turnsLeft,
        public bool $hintsEnabled,
        public int $hintDelayMs,
        public ?string $hintNative,
        public array $turns,
        public ?ConversationSummaryView $summary,
    ) {}
}
