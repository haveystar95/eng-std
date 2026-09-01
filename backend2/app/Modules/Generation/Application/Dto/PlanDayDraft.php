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
        /**
         * P2R calls this run made — 0, or 1 when the answer was nearly right and a repair fixed it.
         *
         * Carried on the SUCCESSFUL draft and not only on the refusal, which is the whole of Д-18:
         * the same two calls used to be charged on the failed path and free on the written one, so
         * the day's counters said different things about identical spending
         * ({@see \App\Modules\Learning\Domain\Entity\PlanDay::markFailed()}).
         */
        public int $repairCalls = 0,
    ) {}
}
