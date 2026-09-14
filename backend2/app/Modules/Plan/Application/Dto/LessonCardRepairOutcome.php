<?php

declare(strict_types=1);

namespace App\Modules\Plan\Application\Dto;

use App\Modules\Plan\Domain\Lesson\Lesson;

/**
 * What a repair of one card came back with (P2R). `status`: `repaired` — the model answered with a card of
 * the right shape and `answer` is the lesson with it put in; `nothing_to_repair` — the validator finds
 * nothing at the address (and no code was named); `not_a_card` — the address names no repairable card;
 * `off_schema` — the model's card is not the card's shape (the call is paid for all the same).
 */
final readonly class LessonCardRepairOutcome
{
    public const REPAIRED = 'repaired';

    public const NOTHING_TO_REPAIR = 'nothing_to_repair';

    public const NOT_A_CARD = 'not_a_card';

    public const OFF_SCHEMA = 'off_schema';

    /**
     * @param  array<string, mixed>|null  $before  the card as the answer held it
     * @param  array<string, mixed>|null  $after  the card the model returned
     * @param  list<array{code: string, address: string, detail: string}>  $findingsBefore  at this card, before
     * @param  list<array{code: string, address: string, detail: string}>  $findingsAfter  at this card, after
     * @param  list<array{code: string, address: string, detail: string}>  $lessonFindings  over the whole repaired lesson
     */
    public function __construct(
        public string $status,
        public string $address,
        public ?string $kind,
        public ?array $before,
        public ?array $after,
        public array $findingsBefore,
        public array $findingsAfter,
        public ?Lesson $answer,
        public array $lessonFindings,
        public int $lessonFindingsBefore,
        public string $costUsd,
        public int $latencyMs,
        public string $promptVersion,
        public string $note = '',
    ) {}
}
