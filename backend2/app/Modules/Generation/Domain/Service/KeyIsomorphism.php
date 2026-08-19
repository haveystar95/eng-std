<?php

declare(strict_types=1);

namespace App\Modules\Generation\Domain\Service;

/**
 * The collaborator {@see ContentChecks} needs and refuses to own: whether a {{source_lang}} line
 * still points at the {{target_lang}} line it is supposed to be the key for.
 *
 * Declared here and implemented in this module's Application (over Vocabulary's rule) because the
 * definition of a broken key belongs to Vocabulary — it owns terms and translations, and the store
 * sweep judges by it. Generation states what it needs; it does not restate the rule.
 */
interface KeyIsomorphism
{
    /**
     * What is wrong with this pair, in both directions, as readable phrases. Empty = nothing found.
     *
     * @return list<string>
     */
    public function gaps(string $source, string $translation, string $lang): array;

    /** Does the rule cover this learner language? A language it does not know is not a clean one. */
    public function knows(string $lang): bool;
}
