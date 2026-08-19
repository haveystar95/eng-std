<?php

declare(strict_types=1);

namespace App\Modules\Generation\Domain\Service;

use App\Modules\Generation\Domain\ValueObject\CandidateItem;
use App\Modules\Generation\Domain\ValueObject\CandidateVerdict;
use App\Modules\Generation\Domain\ValueObject\CheckedBatch;
use App\Modules\Generation\Domain\ValueObject\CheckId;
use App\Modules\Generation\Domain\ValueObject\PromptShape;
use App\Modules\Shared\Domain\Service\LanguagePurity;

/**
 * The automatic half of the bake-off: every produced item, put through every check that applies to
 * its shape, with the evidence kept beside the verdict.
 *
 * What it is NOT: a quality score. Each check is a known defect class with a real card behind it —
 * a Ukrainian word in a Russian field, an example that repeats its term, a translation that stopped
 * pointing at its term. A provider that passes all of them can still be dull, literal or repetitive,
 * and the side-by-side examples in the report are what the counts exist to make readable. The
 * numbers rank; a person decides.
 *
 * Every judgement here reuses the detector the rest of the app already judges by — {@see
 * LanguagePurity} for script/lexis, {@see KeyIsomorphism} for the key. Nothing is re-implemented:
 * a bake-off scored against a private copy of a rule would rank providers by a standard the store
 * does not use.
 */
final readonly class ContentChecks
{
    /** Options are exactly three wrong answers beside one right one — a four-option card. */
    private const OPTION_COUNT = 3;

    private const TYPES = ['word', 'phrase', 'idiom', 'phrasal_verb'];

    private const LEVELS = ['A1', 'A2', 'B1', 'B2', 'C1', 'C2'];

    public function __construct(
        private KeyIsomorphism $isomorphism,
        private LanguagePurity $purity = new LanguagePurity(),
    ) {}

    /**
     * @param  list<CandidateItem>  $items
     * @param  int|null  $expectedSize  how many items were asked for; null when the count is not
     *                                  something this shape can get wrong
     */
    public function judge(
        array $items,
        PromptShape $shape,
        string $sourceLang,
        string $targetLang,
        ?int $expectedSize = null,
    ): CheckedBatch {
        $duplicateTexts = $this->duplicates(array_map(
            static fn (CandidateItem $i): string => $i->text,
            $items,
        ));
        $duplicateTranslations = $this->duplicates(array_map(
            static fn (CandidateItem $i): ?string => $i->translation,
            $items,
        ));

        $verdicts = [];
        foreach ($items as $item) {
            $verdicts[] = $this->judgeOne($item, $shape, $sourceLang, $targetLang, $duplicateTexts, $duplicateTranslations);
        }

        $batchFailures = [];
        $sizeNote = null;
        if ($expectedSize !== null && count($items) !== $expectedSize) {
            $batchFailures[] = CheckId::Size;
            $sizeNote = 'запрошено ' . $expectedSize . ', получено ' . count($items);
        }

        return new CheckedBatch($verdicts, $batchFailures, $sizeNote);
    }

    /**
     * @param  array<string, true>  $duplicateTexts  normalised values that occur more than once
     * @param  array<string, true>  $duplicateTranslations
     */
    private function judgeOne(
        CandidateItem $item,
        PromptShape $shape,
        string $sourceLang,
        string $targetLang,
        array $duplicateTexts,
        array $duplicateTranslations,
    ): CandidateVerdict {
        $failed = [];
        $notes = [];

        $add = function (CheckId $check, string $note) use (&$failed, &$notes): void {
            if (! in_array($check, $failed, true)) {
                $failed[] = $check;
            }
            $notes[$check->value][] = $note;
        };

        // --- fields -------------------------------------------------------------------------
        foreach (['text' => $item->text, 'translation' => $item->translation, 'transcription' => $item->transcription] as $name => $value) {
            if ($value === null || trim($value) === '') {
                $add(CheckId::Fields, "пусто: {$name}");
            }
        }
        if ($item->type === null || ! in_array($item->type, self::TYPES, true)) {
            $add(CheckId::Fields, 'тип: ' . ($item->type ?? '—'));
        }
        if ($item->cefr === null || ! in_array(strtoupper($item->cefr), self::LEVELS, true)) {
            $add(CheckId::Fields, 'уровень: ' . ($item->cefr ?? '—'));
        }
        if ($item->example !== null && trim($item->example) !== ''
            && ($item->exampleTranslation === null || trim($item->exampleTranslation) === '')) {
            // An example with no translation is half a card: the learner-side line is the question.
            $add(CheckId::Fields, 'пусто: example_translation');
        }

        // --- language -----------------------------------------------------------------------
        foreach (['translation' => $item->translation, 'example_translation' => $item->exampleTranslation] as $name => $value) {
            if ($value !== null && trim($value) !== '' && ! $this->purity->isClean($sourceLang, $value)) {
                $letters = $this->purity->foreignLetters($sourceLang, $value);
                $add(CheckId::LangSource, $name . ': ' . ($letters === [] ? 'не тот язык' : 'чужие буквы ' . implode(' ', $letters)));
            }
        }
        foreach (['text' => $item->text, 'example' => $item->example] as $name => $value) {
            if ($value !== null && trim($value) !== '' && ! $this->purity->isClean($targetLang, $value)) {
                $letters = $this->purity->foreignLetters($targetLang, $value);
                $add(CheckId::LangTarget, $name . ': ' . ($letters === [] ? 'не тот язык' : 'чужие буквы ' . implode(' ', $letters)));
            }
        }

        // --- duplicates ---------------------------------------------------------------------
        if (isset($duplicateTexts[$this->key($item->text)])) {
            $add(CheckId::UniqueText, 'термин повторяется в ответе');
        }
        if ($item->translation !== null && isset($duplicateTranslations[$this->key($item->translation)])) {
            $add(CheckId::UniqueTranslation, 'перевод «' . trim($item->translation) . '» повторяется в ответе');
        }

        // --- example ------------------------------------------------------------------------
        $example = $item->example !== null ? trim($item->example) : '';
        if ($example === '') {
            // Not a cosmetic gap: with no example a term has no card on the third rung and drops
            // out of the course entirely.
            $add(CheckId::Example, 'нет примера');
        } elseif ($this->key($example) === $this->key($item->text)) {
            $add(CheckId::Example, 'пример дословно повторяет термин');
        }

        // --- the key ------------------------------------------------------------------------
        if ($this->isomorphism->knows($sourceLang)) {
            if ($item->translation !== null && trim($item->translation) !== '') {
                foreach ($this->isomorphism->gaps($item->text, $item->translation, $sourceLang) as $gap) {
                    $add(CheckId::Isomorphism, 'термин — ' . $gap);
                }
            }
            if ($example !== '' && $item->exampleTranslation !== null && trim($item->exampleTranslation) !== '') {
                foreach ($this->isomorphism->gaps($example, $item->exampleTranslation, $sourceLang) as $gap) {
                    $add(CheckId::Isomorphism, 'пример — ' . $gap);
                }
            }
        }

        // --- options ------------------------------------------------------------------------
        if ($shape->hasOptions()) {
            $this->checkOptions($item, $add);
        }

        // --- verbatim -----------------------------------------------------------------------
        if (! $shape->selectsItems() && $item->givenTerm !== null && trim($item->text) !== trim($item->givenTerm)) {
            $add(CheckId::Verbatim, 'выдано «' . trim($item->text) . '» вместо «' . trim($item->givenTerm) . '»');
        }

        return new CandidateVerdict($item, $failed, $notes);
    }

    /**
     * The option set. Two options that read the same are ONE option, and an option that repeats the
     * right answer turns a four-way card into a coin flip with two correct sides — the same rule the
     * card assembler applies when it deals a multiple-choice question.
     *
     * Whether an option is SEMANTICALLY also a correct answer (a synonym of the translation) is not
     * decidable here and is deliberately left to the human read of the examples: a heuristic that
     * guessed would report a defect rate nobody could verify.
     *
     * @param  callable(CheckId, string): void  $add
     */
    private function checkOptions(CandidateItem $item, callable $add): void
    {
        $options = array_values(array_filter(
            array_map(static fn (string $o): string => trim($o), $item->options),
            static fn (string $o): bool => $o !== '',
        ));

        if (count($options) !== self::OPTION_COUNT) {
            $add(CheckId::Options, 'опций ' . count($options) . ', ожидалось ' . self::OPTION_COUNT);
        }

        $seen = [];
        foreach ($options as $option) {
            $key = $this->key($option);
            if (isset($seen[$key])) {
                $add(CheckId::Options, 'опции повторяются: «' . $option . '»');
            }
            $seen[$key] = true;

            if ($item->translation !== null && $key === $this->key($item->translation)) {
                $add(CheckId::Options, 'опция совпадает с верным ответом: «' . $option . '»');
            }
        }
    }

    /**
     * Values that occur more than once, keyed by their normalised form.
     *
     * @param  list<string|null>  $values
     * @return array<string, true>
     */
    private function duplicates(array $values): array
    {
        $counts = [];
        foreach ($values as $value) {
            if ($value === null || trim($value) === '') {
                continue;
            }
            $key = $this->key($value);
            $counts[$key] = ($counts[$key] ?? 0) + 1;
        }

        return array_map(static fn (): bool => true, array_filter($counts, static fn (int $n): bool => $n > 1));
    }

    /** Case- and whitespace-insensitive, because "Withdraw cash" and "withdraw cash " are one item. */
    private function key(string $value): string
    {
        return mb_strtolower(trim(preg_replace('/\s+/u', ' ', $value) ?? $value));
    }
}
