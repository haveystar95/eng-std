<?php

declare(strict_types=1);

namespace App\Modules\Vocabulary\Application\Port;

use App\Modules\Shared\Domain\ValueObject\TermId;

/**
 * The two facts a plan day learns about a term that nothing else asks: whether the expression is a
 * spoken TURN, and how much machinery it carries.
 *
 * Both are facts about the LANGUAGE, not about one learner's plan, which is why they live on
 * `terms` and are written for every term of a day — including one the store already had from a
 * collection, which has never been asked either question. `false, null` on such a term is absence,
 * not a considered answer, and overwriting absence with knowledge is the one case where a
 * write-once rule would be wrong.
 */
interface TermPlanFactsWriter
{
    public function write(TermId $termId, bool $isLine, ?int $difficultyScore): void;
}
