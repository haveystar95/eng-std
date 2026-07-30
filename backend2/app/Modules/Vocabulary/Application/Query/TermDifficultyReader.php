<?php

declare(strict_types=1);

namespace App\Modules\Vocabulary\Application\Query;

use App\Modules\Shared\Domain\ValueObject\TermId;
use App\Modules\Vocabulary\Application\Dto\TermDifficultyView;

/**
 * Exposes term difficulty signals (CEFR level, word vs phrase) to other modules — Learning
 * uses them to score how risky a "I know this" triage swipe is.
 */
interface TermDifficultyReader
{
    /**
     * @param  list<TermId>  $termIds
     * @return array<string, TermDifficultyView>  keyed by term id
     */
    public function byIds(array $termIds): array;
}
