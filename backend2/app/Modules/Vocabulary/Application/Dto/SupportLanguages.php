<?php

declare(strict_types=1);

namespace App\Modules\Vocabulary\Application\Dto;

/**
 * WHICH SUPPORT LANGUAGE each term in one batch is being read in.
 *
 * A term is global and carries translations in several languages at once, so every content read has
 * to name one. It used to name exactly one for the whole batch — the reader's `profiles
 * .native_language` — and that is the hole this class closes: the language is a property of the
 * COLLECTION the term is being shown through (DECISIONS п. 81), and a session, a `/sync` page or a
 * shelf legitimately mixes collections of different pairs (п. 128, 143). One scalar cannot answer
 * for such a batch; a bare `array<string,string>` can, but says nothing about the term it has no
 * entry for, which is how a missing key becomes a blank card nobody can explain.
 *
 * Two constructors, for the two contexts a caller is actually in:
 *
 *  - {@see uniform()} — the caller knows the ONE collection every term is being read through (a
 *    scoped session, a triage queue, a collection screen, an export).
 *  - {@see perTerm()} — the caller has terms and no single collection (a pool session, the delta
 *    sync, the day-plan simulator). The fallback is the language for a term the map cannot place —
 *    a word whose folder was deleted while its progress stayed (DECISIONS п. 102), which has no
 *    pair left to read. That, and only that, is where the profile still speaks (п. 142).
 */
final readonly class SupportLanguages
{
    /** @param array<string, string> $byTerm */
    private function __construct(
        private array $byTerm,
        private string $fallback,
    ) {}

    /** One pair for the whole batch — the caller knows which collection it is reading through. */
    public static function uniform(string $lang): self
    {
        return new self([], $lang);
    }

    /**
     * A language per term, with the language for a term that has none.
     *
     * @param  array<string, string>  $byTerm  term id => support language
     */
    public static function perTerm(array $byTerm, string $fallback): self
    {
        return new self($byTerm, $fallback);
    }

    public function for(string $termId): string
    {
        return $this->byTerm[$termId] ?? $this->fallback;
    }

    /**
     * The batch split into one id list per language, so a reader that filters by language issues
     * one query per DISTINCT language rather than one per term. In practice that is one query —
     * a learner with two pairs makes it two.
     *
     * @param  list<string>  $termIds
     * @return array<string, list<string>>  language => the term ids read in it
     */
    public function group(array $termIds): array
    {
        $out = [];
        foreach ($termIds as $termId) {
            $out[$this->for($termId)][] = $termId;
        }

        return $out;
    }
}
