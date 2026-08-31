<?php

declare(strict_types=1);

namespace App\Modules\Vocabulary\Application\Port;

use App\Modules\Shared\Domain\ValueObject\TermId;

/**
 * What a plan day knows about a term that nothing else asks: what the expression DOES in a day
 * («line», «word» or «chunk»), the frame it stands in, whose turn it is, and how much machinery
 * it carries.
 *
 * Written for EVERY term of a day, including one the store already had from a collection — such a
 * term has never been asked any of these questions, and `false, null, null, null` on it is absence
 * rather than a considered answer. Overwriting absence with knowledge is the one case where a
 * write-once rule would be wrong.
 *
 * `kind` and `frame` are what the SESSION reads: the stage checklist a card is dealt depends on
 * the first ({@see \App\Modules\Learning\Domain\Service\PlanStageLadder}), and the gap of a
 * cloze card is cut from the second. A term re-imported by a later plan day gets the later day's
 * frame, which is correct — the frame belongs to the day being taught.
 */
interface TermPlanFactsWriter
{
    public function write(
        TermId $termId,
        bool $isLine,
        ?int $difficultyScore,
        ?string $kind = null,
        ?string $frame = null,
        ?string $speaker = null,
    ): void;
}
