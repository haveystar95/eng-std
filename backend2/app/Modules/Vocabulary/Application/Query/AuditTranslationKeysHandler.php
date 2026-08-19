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
 *
 * Both of a card's keys are judged with the SAME rule: the term and every example sentence. The rule
 * takes a source and a translation and does not care which of the two it is looking at — which is
 * why extending the audit to examples cost a reader and no second copy of the judgement.
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
                $gaps = $this->rule->misses($row->termText, $row->translation, $lang);
                if ($gaps === []) {
                    continue;
                }

                $candidates[] = $this->candidate(
                    termId: $row->termId,
                    termText: $row->termText,
                    kind: 'term',
                    sourceText: $row->termText,
                    lang: $lang,
                    translation: $row->translation,
                    gaps: $gaps,
                );
            }

            foreach ($examples as $row) {
                $gaps = $this->rule->misses($row->sentence, $row->translation, $lang);
                if ($gaps === []) {
                    continue;
                }

                $candidates[] = $this->candidate(
                    termId: $row->termId,
                    termText: $row->termText,
                    kind: 'example',
                    sourceText: $row->sentence,
                    lang: $lang,
                    translation: $row->translation,
                    gaps: $gaps,
                );
            }
        }

        return new TranslationKeyAuditView(
            seen: $seen,
            candidates: $candidates,
            groupNames: AddresseeIsomorphism::groupNames(),
            seenTermsByLang: $seenTermsByLang,
            seenExamplesByLang: $seenExamplesByLang,
            skippedExamples: $this->keys->examplesOfUnknownLangCount($query->termLang),
            ruleLanguages: AddresseeIsomorphism::languages(),
        );
    }

    /**
     * @param  'term'|'example'  $kind
     * @param  list<AddresseeMiss>  $gaps
     */
    private function candidate(
        string $termId,
        string $termText,
        string $kind,
        string $sourceText,
        string $lang,
        string $translation,
        array $gaps,
    ): TranslationKeyCandidate {
        return new TranslationKeyCandidate(
            termId: $termId,
            termText: $termText,
            kind: $kind,
            sourceText: $sourceText,
            lang: $lang,
            translation: $translation,
            groups: array_map(static fn (AddresseeMiss $g): string => $g->group, $gaps),
            missingWords: self::words($gaps),
            expectedForms: self::expectedForms($gaps),
        );
    }

    /**
     * Every unanswered word, deduplicated — `Tell us what you told us` says `us` once in a report.
     *
     * @param  list<AddresseeMiss>  $gaps
     * @return list<string>
     */
    private static function words(array $gaps): array
    {
        $words = [];
        foreach ($gaps as $miss) {
            foreach ($miss->words as $word) {
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
     * @param  list<AddresseeMiss>  $gaps
     * @return array<string, list<string>>
     */
    private static function expectedForms(array $gaps): array
    {
        $forms = [];
        foreach ($gaps as $miss) {
            foreach ($miss->words as $word) {
                $forms[mb_strtolower($word)] = $miss->expected;
            }
        }

        return $forms;
    }
}
