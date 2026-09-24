<?php

declare(strict_types=1);

namespace App\Modules\Plan\Application\Dto\Inspection;

use App\Modules\Plan\Domain\Service\ConversationOutcomes;
use DateTimeImmutable;

/**
 * A talk of the plan and its journals as stored (`conversations`, `conversation_turns`, and since наряд FIX-4
 * `conversation_rejections`), for the admin (наряд ADM-1).
 */
final readonly class InspectedTalk
{
    /**
     * @param  list<string>  $sceneIds
     * @param  list<string>  $checkpointsDone
     * @param  list<InspectedTurn>  $turns
     * @param  list<InspectedRejection>  $rejections
     */
    public function __construct(
        public string $id,
        public string $dayId,
        public int $dayNumber,
        public string $type,
        public string $state,
        public array $sceneIds,
        public array $checkpointsDone,
        public int $turnLimit,
        public bool $hintsEnabled,
        public string $costUsd,
        public ?string $endedReason,
        public DateTimeImmutable $startedAt,
        public ?DateTimeImmutable $endedAt,
        public array $turns,
        public array $rejections = [],
    ) {}

    /** Did a limit end the talk (наряд FIX-4 §4) — the rule of {@see ConversationOutcomes::endedByLimit()}. */
    public function endedByLimit(): bool
    {
        $last = null;
        foreach ($this->turns as $turn) {
            if ($turn->kind === 'agent') {
                $last = $turn;
            }
        }

        return ConversationOutcomes::endedByLimit($this->state === 'ended', $this->endedReason, $last?->sceneEvent, array_diff($this->sceneIds, $this->checkpointsDone) === []);
    }
}
