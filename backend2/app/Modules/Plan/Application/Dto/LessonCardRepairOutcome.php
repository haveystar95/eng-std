<?php

declare(strict_types=1);

namespace App\Modules\Plan\Application\Dto;

use App\Modules\Plan\Domain\Lesson\Dialogue;
use App\Modules\Plan\Domain\Lesson\Skeleton;

/**
 * What a repair of one card came back with (`lesson_card_repair.v1.5`). `status`: `repaired` — the model answered with a card
 * of the right shape and `skeleton` / `dialogue` are the stages with it put in; `not_a_card` — the address names no card of
 * the stages; `off_schema` — the model's card is not the card's shape, a learner line names no frame of the skeleton, or a
 * repaired frame drops a filler the dialogue says (the call is paid for all the same, and nothing is put in); `refused` — a
 * replaced word the server does not take: a word the day already has (paid for, nothing put in, `note` says why).
 */
final readonly class LessonCardRepairOutcome
{
    public const REPAIRED = 'repaired';

    public const NOT_A_CARD = 'not_a_card';

    public const OFF_SCHEMA = 'off_schema';

    public const REFUSED = 'refused';

    /**
     * @param  array<string, mixed>|null  $before  the card as its stage held it
     * @param  array<string, mixed>|null  $after  the card the model returned
     * @param  list<array{code: string, address: string, detail: string}>  $findings  what the card was sent for
     */
    public function __construct(
        public string $status,
        public string $address,
        public ?string $kind,
        public ?array $before,
        public ?array $after,
        public array $findings,
        public ?Skeleton $skeleton,
        public ?Dialogue $dialogue,
        public string $costUsd,
        public int $latencyMs,
        public string $promptVersion,
        public string $note = '',
    ) {}
}
