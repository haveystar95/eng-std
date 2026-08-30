<?php

declare(strict_types=1);

namespace App\Modules\Generation\Application\Dto;

use App\Modules\Generation\Domain\ValueObject\PlanDayItem;
use App\Modules\Shared\Domain\ValueObject\UserId;

/** A day's material after the model answered and the validator agreed. */
final readonly class PlanDayDraft
{
    /**
     * @param  list<PlanDayItem>  $items
     * @param  list<array{term_id: string, example: string, example_translation: string}>  $knownExamples
     *         fresh sentences for terms the learner already met — no translation, no description,
     *         no transliteration. They are not re-taught, only re-met somewhere new.
     */
    public function __construct(
        public UserId $ownerId,
        public array $items,
        public array $knownExamples,
        public ?string $dayDescription,
        public string $model,
        public string $promptVersion,
        public ?string $costUsd,
    ) {}
}
