<?php

declare(strict_types=1);

namespace App\Modules\Plan\Application\Dto;

use App\Modules\Plan\Domain\Lesson\EarlierDays;
use App\Modules\Plan\Domain\ValueObject\PlanLevel;
use App\Modules\Shared\Domain\ValueObject\VoiceGender;

/**
 * THE REPAIR OF ONE CARD (P2R, `lesson_card_repair.v1.4`): the card by its address and kind, as the answer holds it; what
 * the validator found broken in it (code and English detail — never another card's text); the part of the lesson the
 * card needs to fit the visit ({@see \App\Modules\Plan\Domain\Lesson\LessonCardContext}, never the whole answer); for a
 * whole exchange its NEIGHBOURS — the exchanges before and after it, null at the edge of the visit; the story so far,
 * which the prompt reads in its short form (the frames in both languages and the words of every earlier day); and the
 * inputs the lesson was written with — the counts among them, which bound the ids a repaired card may name.
 */
final readonly class LessonCardRepairRequest
{
    /**
     * @param  'frame'|'exchange'|'line'|'check'|'listening'|'term'  $kind
     * @param  array<string, mixed>  $card
     * @param  array<string, mixed>  $context
     * @param  list<array{code: string, detail: string}>  $findings
     * @param  array{before: array<string, mixed>|null, after: array<string, mixed>|null}|null  $neighbours  a whole exchange's; null for any other card
     */
    public function __construct(
        public string $address,
        public string $kind,
        public array $card,
        public array $context,
        public array $findings,
        public ?array $neighbours,
        public EarlierDays $earlierDays,
        public string $targetLanguage,
        public string $nativeLanguage,
        public PlanLevel $level,
        public ?VoiceGender $learnerGender,
        public int $dialogueCount,
        public int $vocabularyCount,
    ) {}
}
