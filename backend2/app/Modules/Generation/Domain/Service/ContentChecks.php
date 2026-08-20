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

    /**
     * Words that turn a translation into an EXPLANATION rather than a name. A closed, deliberately
     * small list of subordinators and connectives: they are what a definition needs in order to
     * describe a situation, and what a key almost never needs in order to name a thing.
     *
     * They are not forbidden words. «Заполнить, если нужно» is fine; the marker only counts when the
     * translation is ALSO much longer than the term it stands for — see {@see looksLikeDefinition}.
     */
    private const EXPLANATORY = [
        'когда', 'который', 'которая', 'которое', 'которые', 'которым', 'котором',
        'то есть', 'из-за', 'чтобы', 'если', 'например', 'а также', 'в случае',
        'процесс', 'действие', 'состояние', 'ситуация', 'значит',
    ];

    /** A translation this many words long or more is long enough to be describing something. */
    private const DEFINITION_MIN_WORDS = 4;

    /** …and this many times longer than its own term. */
    private const DEFINITION_MARKED_RATIO = 2.0;

    /** A translation this many times longer than its term is describing even with no marker in it. */
    private const DEFINITION_BARE_RATIO = 3.0;

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
        // `text` is required of every shape; everything else below is part of a CORE, and a shape
        // that was told not to write one is not short of it.
        if (trim($item->text) === '') {
            $add(CheckId::Fields, 'пусто: text');
        }

        $core = $shape->producesCore();

        if ($core) {
            foreach (['translation' => $item->translation, 'transcription' => $item->transcription] as $name => $value) {
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
        if (! $core) {
            // Nothing below judges a core. Fall through to the machinery checks.
            $example = '';
        } elseif ($example === '') {
            // Not a cosmetic gap: with no example a term has no card on the third rung and drops
            // out of the course entirely.
            $add(CheckId::Example, 'нет примера');
        } elseif ($this->key($example) === $this->key($item->text)) {
            $add(CheckId::Example, 'пример дословно повторяет термин');
        }

        // --- the key ------------------------------------------------------------------------
        if ($core && $this->isomorphism->knows($sourceLang)) {
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

        // --- a key, not a definition ---------------------------------------------------------
        if ($core && $item->translation !== null && trim($item->translation) !== '') {
            $verdict = $this->looksLikeDefinition($item->text, $item->translation);
            if ($verdict !== null) {
                $add(CheckId::Definition, $verdict);
            }
        }

        // --- options ------------------------------------------------------------------------
        if ($shape->hasOptions()) {
            $this->checkOptions($item, $add);
        }

        // --- forms --------------------------------------------------------------------------
        if ($shape->hasForms()) {
            $this->checkForms($item, $add);
        }

        // --- verbatim -----------------------------------------------------------------------
        if (! $shape->selectsItems() && $item->givenTerm !== null && trim($item->text) !== trim($item->givenTerm)) {
            $add(CheckId::Verbatim, 'выдано «' . trim($item->text) . '» вместо «' . trim($item->givenTerm) . '»');
        }

        return new CandidateVerdict($item, $failed, $notes);
    }

    /**
     * Does this translation DESCRIBE the term instead of naming it? Returns the evidence, or null.
     *
     * The defect: «back up» → «подниматься обратно из-за засора». True about the world, useless as a
     * card — the learner reads it and cannot write `back up` back, because the line explains a
     * blocked drain instead of naming the verb.
     *
     * ## The metric, and why this one
     *
     * A definition differs from a key in two measurable ways at once, and only their CONJUNCTION is
     * a usable signal:
     *
     *  - **length relative to the term.** A key is a name, so it tracks the term's length: ru↔en
     *    keys sit around 0.5–2× word for word. A definition has to introduce the thing before it can
     *    describe it, so it runs long. Length alone is a bad rule — «Расскажите нам о вашем опыте»
     *    is five words for a five-word term and perfectly correct — which is why the ratio is taken
     *    against the TERM rather than against an absolute cap.
     *  - **explanatory connectives.** «из-за», «когда», «который» are the joints a description needs
     *    and a name does not. On their own they are innocent: a phrase term legitimately translates
     *    to a phrase containing «если».
     *
     * So: a marker counts only in a translation that is also {@see DEFINITION_MARKED_RATIO}× its
     * term and at least {@see DEFINITION_MIN_WORDS} words; and a translation {@see
     * DEFINITION_BARE_RATIO}× its term is flagged with no marker at all, because at that length it
     * is describing whatever words it used. Worked against the row that named the defect: «back up»
     * (2 words) → «подниматься обратно из-за засора» (4 words) — ratio 2.0, marker «из-за» → flagged.
     *
     * **It flags CANDIDATES and never a verdict**, exactly like the isomorphism rule beside it:
     * morphology-free heuristics over two languages cannot do better, and a check that quietly
     * rewrote content it merely suspected would be worse than the defect.
     */
    private function looksLikeDefinition(string $term, string $translation): ?string
    {
        $termWords = $this->wordCount($term);
        $translationWords = $this->wordCount($translation);

        if ($termWords === 0 || $translationWords < self::DEFINITION_MIN_WORDS) {
            return null;
        }

        $ratio = $translationWords / $termWords;

        if ($ratio >= self::DEFINITION_BARE_RATIO) {
            return sprintf('перевод в %.1f раза длиннее термина (%d слов против %d)', $ratio, $translationWords, $termWords);
        }

        if ($ratio < self::DEFINITION_MARKED_RATIO) {
            return null;
        }

        $lower = mb_strtolower($translation);
        foreach (self::EXPLANATORY as $marker) {
            if (preg_match('/(?<![\p{L}\p{N}])' . preg_quote($marker, '/') . '(?![\p{L}\p{N}])/u', $lower) === 1) {
                return sprintf('похоже на определение: «%s» при переводе вдвое длиннее термина', $marker);
            }
        }

        return null;
    }

    /** Words, counted the same way on both sides — whitespace-separated runs. */
    private function wordCount(string $value): int
    {
        $trimmed = trim(preg_replace('/\s+/u', ' ', $value) ?? $value);

        return $trimmed === '' ? 0 : count(explode(' ', $trimmed));
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
     * The accepted-forms list. An EMPTY list is correct and common — most terms have no alternative
     * spelling — so nothing here penalises emptiness; what is checked is that a form the model DID
     * return is shaped like a replacement for the term.
     *
     * Whether a form is semantically the same term is not decidable here and is left to the human
     * read, like an option's meaning. What is decidable is shape, and shape is where this goes
     * wrong: a form that is a clause, or a copy of `text`, is a stored answer key that either
     * accepts a sentence for a one-word card or says nothing at all.
     *
     * @param  callable(CheckId, string): void  $add
     */
    private function checkForms(CandidateItem $item, callable $add): void
    {
        $termWords = $this->wordCount($item->text);
        $seen = [$this->key($item->text) => true];

        foreach ($item->forms as $form) {
            $clean = trim($form);
            if ($clean === '') {
                $add(CheckId::Forms, 'пустая форма');

                continue;
            }

            $key = $this->key($clean);
            if (isset($seen[$key])) {
                // Either the term itself back again, or the same form twice: both are a row in the
                // answer key that accepts nothing the key did not already accept.
                $add(CheckId::Forms, 'форма повторяет термин или другую форму: «' . $clean . '»');

                continue;
            }
            $seen[$key] = true;

            // A replacement for the term is about the term's length. Twice its words (and at least
            // three) is not a spelling variant any more, it is a paraphrase or a whole clause.
            $words = $this->wordCount($clean);
            if ($words >= 3 && $termWords > 0 && $words >= 2 * $termWords) {
                $add(CheckId::Forms, 'форма длиннее термина вдвое: «' . $clean . '»');
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
