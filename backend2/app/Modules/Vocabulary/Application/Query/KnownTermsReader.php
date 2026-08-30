<?php

declare(strict_types=1);

namespace App\Modules\Vocabulary\Application\Query;

/**
 * The terms an earlier day of THIS plan already introduced, on this language pair.
 *
 * What the day prompt's KNOWN block is built from. Scoped to the plan rather than to the learner's
 * whole pool on purpose: the block's contract is «met earlier in this plan, give it 1–2 fresh
 * examples and do not re-teach it», and handing the model four hundred words the learner knows
 * from elsewhere would turn a small instruction into a wall of context that costs money on every
 * day of every plan.
 */
interface KnownTermsReader
{
    /** @return array<string, string> term id → text */
    public function metInPlan(string $planId, string $targetLang, string $supportLang): array;
}
