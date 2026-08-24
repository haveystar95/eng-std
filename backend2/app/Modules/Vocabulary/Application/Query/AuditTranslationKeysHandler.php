<?php

declare(strict_types=1);

namespace App\Modules\Vocabulary\Application\Query;

use App\Modules\Vocabulary\Application\Dto\TranslationKeyAuditView;
use App\Modules\Vocabulary\Application\Dto\TranslationKeyCandidate;
use App\Modules\Vocabulary\Domain\Service\AddresseeIsomorphism;
use App\Modules\Vocabulary\Domain\ValueObject\AddresseeGap;

/**
 * The audit, judged where the rule lives. Vocabulary owns terms, translations and the definition of
 * a defective key; a caller gets candidates, not rows to re-judge with a copy of the rule.
 *
 * With no language named it sweeps every language the store HAS, asked of the same reader that
 * returns the pairs — not a list configured somewhere. A language that shows up in the content
 * tomorrow is audited tomorrow, without anyone remembering to add it here.
 *
 * Both of a card's keys are judged with the SAME rule: the term and every example sentence. The rule
 * takes a source and a translation and does not care which of the two it is looking at — which is
 * why extending the audit to examples cost a reader and no second copy of the judgement.
 *
 * A pair that breaks in both directions produces TWO candidates, one per direction. They are
 * different defects with opposite fixes (put a word back / take one out), and a reader who found
 * them merged in one row would have to split them by hand before acting on either.
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
        $seenTermsByLang = [];
        $seenExamplesByLang = [];
        $candidates = [];

        foreach ($langs as $lang) {
            $terms = $this->keys->primaryKeys($query->termLang, $lang);
            $examples = $this->keys->primaryExampleKeys($query->termLang, $lang);
            $seen += count($terms) + count($examples);
            $seenTermsByLang[$lang] = count($terms);
            $seenExamplesByLang[$lang] = count($examples);

            foreach ($terms as $row) {
                foreach ($this->judge($row->termId, $row->termText, 'term', $row->termText, $lang, $row->translation) as $candidate) {
                    $candidates[] = $candidate;
                }
            }

            foreach ($examples as $row) {
                foreach ($this->judge($row->termId, $row->termText, 'example', $row->sentence, $lang, $row->translation) as $candidate) {
                    $candidates[] = $candidate;
                }
            }
        }

        return new TranslationKeyAuditView(
            seen: $seen,
            candidates: $candidates,
            groupNames: AddresseeIsomorphism::groupNames(),
            seenTermsByLang: $seenTermsByLang,
            seenExamplesByLang: $seenExamplesByLang,
            ruleLanguages: AddresseeIsomorphism::languages(),
        );
    }

    /**
     * One pair, both directions — at most one candidate each.
     *
     * @param  'term'|'example'  $kind
     * @return list<TranslationKeyCandidate>
     */
    private function judge(
        string $termId,
        string $termText,
        string $kind,
        string $sourceText,
        string $lang,
        string $translation,
    ): array {
        $out = [];
        foreach ([$this->rule->misses($sourceText, $translation, $lang), $this->rule->extras($sourceText, $translation, $lang)] as $gaps) {
            if ($gaps === []) {
                continue;
            }

            $out[] = new TranslationKeyCandidate(
                termId: $termId,
                termText: $termText,
                kind: $kind,
                sourceText: $sourceText,
                lang: $lang,
                translation: $translation,
                direction: $gaps[0]->direction->value,
                groups: array_map(static fn (AddresseeGap $g): string => $g->group, $gaps),
                words: self::words($gaps),
                expectedForms: self::expectedForms($gaps),
            );
        }

        return $out;
    }

    /**
     * Every word the gaps name, deduplicated — `Tell us what you told us` says `us` once in a report.
     *
     * @param  list<AddresseeGap>  $gaps
     * @return list<string>
     */
    private static function words(array $gaps): array
    {
        $words = [];
        foreach ($gaps as $gap) {
            foreach ($gap->words as $word) {
                $words[mb_strtolower($word)] = true;
            }
        }

        return array_keys($words);
    }

    /**
     * What the rule expected for each word, kept per WORD rather than merged into one list: a row
     * that trips two groups otherwise offers the reader «нам/нас/мне/меня/я/вы/вас/…» as if any one
     * of them fixed anything.
     *
     * @param  list<AddresseeGap>  $gaps
     * @return array<string, list<string>>
     */
    private static function expectedForms(array $gaps): array
    {
        $forms = [];
        foreach ($gaps as $gap) {
            foreach ($gap->words as $word) {
                $forms[mb_strtolower($word)] = $gap->expected;
            }
        }

        return $forms;
    }
}
