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
        /**
         * THE ORDER THE SCENE IS SPOKEN IN — refs into the shelves above, alternating (P2 v0.5).
         *
         * Still ADDRESSES here and not term ids: the terms do not exist until the day is written,
         * and resolving a ref to a card is only possible while the answer's own indexes are still
         * the truth. {@see \App\Modules\Generation\Application\Command\GeneratePlanDayHandler}
         * turns them into term ids in the same pass that imports the cards, which is the one moment
         * both halves are in hand.
         *
         * Empty on a day the model answered without a chain — impossible on v0.5, where the gate
         * refuses it, and ordinary for anything replayed from an older fixture.
         *
         * @var list<\App\Modules\Generation\Domain\ValueObject\PlanDialogueTurn>
         */
        public array $dialogue = [],
    ) {}
}
