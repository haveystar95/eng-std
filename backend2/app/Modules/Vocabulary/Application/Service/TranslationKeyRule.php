<?php

declare(strict_types=1);

namespace App\Modules\Vocabulary\Application\Service;

use App\Modules\Vocabulary\Domain\Service\AddresseeIsomorphism;
use App\Modules\Vocabulary\Domain\ValueObject\AddresseeGap;

/**
 * The translation-key rule, offered to other modules over primitives.
 *
 * {@see AuditTranslationKeysHandler} asks the same question of rows that are already IN the store.
 * Generation needs it asked of a pair that is not stored and may never be — a candidate a model has
 * just produced — and it must be the SAME rule, not a copy: a bake-off judged by a second
 * implementation would rank providers against a standard the store does not use.
 *
 * Primitives in, primitives out, so Generation never touches Vocabulary's Domain — the same shape
 * as the `ImportTerm` shim, for the same reason.
 */
final readonly class TranslationKeyRule
{
    public function __construct(private AddresseeIsomorphism $rule) {}

    /**
     * Everything wrong with this pair, in both directions, as readable phrases.
     *
     * Empty means clean — or means the rule has no counterpart list for `$lang` and stays silent;
     * {@see knows()} is how a caller tells the two apart. A report that showed a language the rule
     * has never been taught as "0 defects" would be claiming a result it does not have.
     *
     * @param  string  $source  the side the learner must reproduce — a term or an example sentence
     * @return list<string>  e.g. ["потеряно: us (us/me)", "лишнее: хорошо (well/хорошо)"]
     */
    public function gaps(string $source, string $translation, string $lang): array
    {
        return array_map(
            static fn (AddresseeGap $gap): string => sprintf(
                '%s: %s (%s)',
                $gap->direction->value === 'lost' ? 'потеряно' : 'лишнее',
                implode(', ', $gap->words),
                $gap->group,
            ),
            $this->rule->gaps($source, $translation, $lang),
        );
    }

    /** Does the rule have counterpart lists for this learner language at all? */
    public function knows(string $lang): bool
    {
        return AddresseeIsomorphism::knowsLanguage($lang);
    }
}
