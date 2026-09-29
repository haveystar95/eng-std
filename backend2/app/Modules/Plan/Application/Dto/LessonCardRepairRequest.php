<?php

declare(strict_types=1);

namespace App\Modules\Plan\Application\Dto;

use App\Modules\Plan\Domain\Lesson\EarlierDays;
use App\Modules\Plan\Domain\ValueObject\PlanLevel;
use App\Modules\Shared\Domain\ValueObject\VoiceGender;

/**
 * THE REPAIR OF ONE CARD (`lesson_card_repair.v1.5`, наряд GEN-4, 3.9): the card by its ADDRESS and kind, as its stage holds
 * it; FINDINGS — what the stage's check (or the seam judge) found at it, code and English detail; the SKELETON, whole; the
 * DIALOGUE, whole, for a card of the dialogue (null before the dialogue exists); NEIGHBOURS — for a whole exchange, the
 * exchanges before and after it (null at the edge of the visit; null for any other card); EARLIER_DAYS in its short form;
 * and the pair of languages, the level and the learner's gender.
 */
final readonly class LessonCardRepairRequest
{
    /**
     * @param  'frame'|'term'|'partner_line'|'exchange'|'check'|'listening'  $kind
     * @param  array<string, mixed>  $card
     * @param  list<array{code: string, detail: string}>  $findings
     * @param  array<string, mixed>  $skeleton
     * @param  array<string, mixed>|null  $dialogue
     * @param  array{before: array<string, mixed>|null, after: array<string, mixed>|null}|null  $neighbours
     */
    public function __construct(
        public string $address,
        public string $kind,
        public array $card,
        public array $findings,
        public array $skeleton,
        public ?array $dialogue,
        public ?array $neighbours,
        public EarlierDays $earlierDays,
        public string $targetLanguage,
        public string $nativeLanguage,
        public PlanLevel $level,
        public ?VoiceGender $learnerGender,
    ) {}
}
