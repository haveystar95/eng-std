<?php

declare(strict_types=1);

namespace App\Modules\Plan\Application\Dto\Inspection;

use DateTimeImmutable;

/** A talk of the plan and its journal as stored (`conversations`, `conversation_turns`), for the admin (наряд ADM-1). */
final readonly class InspectedTalk
{
    /**
     * @param  list<string>  $sceneIds
     * @param  list<string>  $checkpointsDone
     * @param  list<InspectedTurn>  $turns
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
    ) {}
}
