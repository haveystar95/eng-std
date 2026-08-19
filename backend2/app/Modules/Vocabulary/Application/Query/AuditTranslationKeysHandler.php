<?php

declare(strict_types=1);

namespace App\Modules\Vocabulary\Application\Query;

use App\Modules\Vocabulary\Application\Dto\TranslationKeyAuditView;
use App\Modules\Vocabulary\Application\Dto\TranslationKeyCandidate;
use App\Modules\Vocabulary\Domain\Service\AddresseeIsomorphism;
use App\Modules\Vocabulary\Domain\ValueObject\AddresseeMiss;

/**
 * The audit, judged where the rule lives. Vocabulary owns terms, translations and the definition of
 * a defective key; a caller gets candidates, not rows to re-judge with a copy of the rule.
 *
 * With no language named it sweeps every language the store HAS, asked of the same reader that
 * returns the pairs — not a list configured somewhere. A language that shows up in the content
 * tomorrow is audited tomorrow, without anyone remembering to add it here.
 */
final readonly class AuditTranslationKeysHandler
{
    public function __construct(
        private TranslationKeyReader $keys,
        private AddresseeIsomorphism $rule,
    ) {}

    public function __invoke(AuditTranslationKeys $query): TranslationKeyAuditView
    {
        $langs = $query->sourceLang !== null
            ? [$query->sourceLang]
            : $this->keys->translationLangs($query->termLang);

        $seen = 0;
        $seenByLang = [];
        $candidates = [];

        foreach ($langs as $lang) {
            $rows = $this->keys->primaryKeys($query->termLang, $lang);
            $seen += count($rows);
            $seenByLang[$lang] = count($rows);

            foreach ($rows as $row) {
                $misses = $this->rule->misses($row->termText, $row->translation, $lang);
                if ($misses === []) {
                    continue;
                }

                $candidates[] = new TranslationKeyCandidate(
                    termId: $row->termId,
                    termText: $row->termText,
                    lang: $lang,
                    translation: $row->translation,
                    groups: array_map(static fn (AddresseeMiss $m): string => $m->group, $misses),
                    missingWords: self::missingWords($misses),
                    expectedForms: self::expectedForms($misses),
                );
            }
        }

        return new TranslationKeyAuditView(
            seen: $seen,
            candidates: $candidates,
            groupNames: AddresseeIsomorphism::groupNames(),
            seenByLang: $seenByLang,
            ruleLanguages: AddresseeIsomorphism::languages(),
        );
    }

    /**
     * Every unanswered word, deduplicated — `Tell us what you told us` says `us` once in a report.
     *
     * @param  list<AddresseeMiss>  $misses
     * @return list<string>
     */
    private static function missingWords(array $misses): array
    {
        $words = [];
        foreach ($misses as $miss) {
            foreach ($miss->termWords as $word) {
                $words[mb_strtolower($word)] = true;
            }
        }

        return array_keys($words);
    }

    /**
     * Which forms would have cleared each missing word, kept per WORD rather than merged into one
     * list: a row that trips two groups otherwise offers the reader «нам/нас/мне/меня/я/вы/вас/…»
     * as if any one of them fixed anything.
     *
     * @param  list<AddresseeMiss>  $misses
     * @return array<string, list<string>>
     */
    private static function expectedForms(array $misses): array
    {
        $forms = [];
        foreach ($misses as $miss) {
            foreach ($miss->termWords as $word) {
                $forms[mb_strtolower($word)] = $miss->expected;
            }
        }

        return $forms;
    }
}
