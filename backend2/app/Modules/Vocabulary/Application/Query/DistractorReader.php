<?php

declare(strict_types=1);

namespace App\Modules\Vocabulary\Application\Query;

use App\Modules\Shared\Domain\ValueObject\TermId;

/**
 * Plausible-but-unambiguously-wrong multiple-choice distractors for a target term: the target
 * TEXT of OTHER terms (never a translation — mixing languages in the options would give the
 * answer away). A candidate is excluded when its translations overlap the target's, so the
 * prompt can't read as correct for both (e.g. "withdraw money" against "withdraw cash"). Prefers
 * the session's own pool (its collection), then tops up from same-language terms of a similar
 * level.
 */
interface DistractorReader
{
    /**
     * @param  list<string>  $poolTermIds
     * @return list<string>  up to $count distractor texts
     */
    public function forTarget(TermId $targetId, array $poolTermIds, int $count): array;
}
