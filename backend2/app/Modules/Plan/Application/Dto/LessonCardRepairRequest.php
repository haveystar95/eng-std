<?php

declare(strict_types=1);

namespace App\Modules\Plan\Application\Dto;

use App\Modules\Plan\Domain\ValueObject\PlanLevel;
use App\Modules\Shared\Domain\ValueObject\VoiceGender;

/**
 * THE REPAIR OF ONE CARD (P2R, `lesson_card_repair.v1.1`): the card by its address and kind, as the answer holds
 * it; what the validator found broken in it (code and English detail — never another card's text); the part of the
 * lesson the card needs to fit the visit ({@see \App\Modules\Plan\Domain\Lesson\LessonCardContext}, never the whole
 * answer); and the inputs the lesson was written with.
 */
final readonly class LessonCardRepairRequest
{
    /**
     * @param  'frame'|'exchange'|'line'|'check'|'listening'  $kind
     * @param  array<string, mixed>  $card
     * @param  array<string, mixed>  $context
     * @param  list<array{code: string, detail: string}>  $findings
     * @param  list<string>  $frameIds  the frames of the day — what a repaired learner line may stand on
     */
    public function __construct(
        public string $address,
        public string $kind,
        public array $card,
        public array $context,
        public array $findings,
        public array $frameIds,
        public string $targetLanguage,
        public string $nativeLanguage,
        public PlanLevel $level,
        public ?VoiceGender $learnerGender,
    ) {}
}
