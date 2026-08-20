<?php

declare(strict_types=1);

namespace App\Modules\Generation\Domain\ValueObject;

/**
 * The automatic checks a produced item is put through. A closed set, because the bake-off report
 * counts by it and a free-text check name would give two runs two different tables.
 *
 * Each is a KNOWN class of defect with a row behind it, not a style opinion. None of them is a
 * complete test of quality — a set that passes every check can still be dull, and the human read of
 * the side-by-side examples is what the numbers are there to prioritise.
 */
enum CheckId: string
{
    /** Every required field present, non-empty, and of a valid value (type, CEFR). */
    case Fields = 'fields';

    /** The learner-language fields are that language: no Ukrainian in Russian, no wrong script. */
    case LangSource = 'lang_source';

    /** The target-language fields are that language: no Cyrillic in an English term or example. */
    case LangTarget = 'lang_target';

    /** No two items in one answer are the same term. */
    case UniqueText = 'unique_text';

    /** No two items in one answer share a translation — two identical questions, two answers. */
    case UniqueTranslation = 'unique_translation';

    /** An example exists, is non-empty, and is not simply the term repeated. */
    case Example = 'example';

    /** The translation still points at its own term — both waves (nothing lost, nothing added). */
    case Isomorphism = 'isomorphism';

    /** The translation NAMES the term rather than describing what it means. Coarse, flags candidates. */
    case Definition = 'definition';

    /** Exactly 3 wrong options, all distinct, none of them equal to the right answer. */
    case Options = 'options';

    /** (given-terms only) `text` is the term we handed over, character for character. */
    case Verbatim = 'verbatim';

    /** Accepted forms are shaped like the term (no clauses, no duplicates of `text`). */
    case Forms = 'forms';

    /** Batch-level: the answer has as many items as were asked for. */
    case Size = 'size';

    /** How the check reads in a report column. */
    public function label(): string
    {
        return match ($this) {
            self::Fields => 'полнота полей',
            self::LangSource => 'язык перевода',
            self::LangTarget => 'язык термина',
            self::UniqueText => 'дубли терминов',
            self::UniqueTranslation => 'дубли переводов',
            self::Example => 'пример',
            self::Isomorphism => 'изоморфность',
            self::Definition => 'перевод-определение',
            self::Options => 'опции',
            self::Verbatim => 'термин дословно',
            self::Forms => 'формы слова',
            self::Size => 'размер списка',
        };
    }

    /**
     * The per-item checks that apply to a given product shape, in report order.
     *
     * `Size` is not here: it is a fact about the whole answer, not about an item, and counting it
     * per item would make one short answer look like N defects.
     *
     * @return list<self>
     */
    public static function forShape(PromptShape $shape): array
    {
        $checks = [
            self::Fields,
            self::LangSource,
            self::LangTarget,
            self::UniqueText,
            self::UniqueTranslation,
        ];

        // A shape that was forbidden to write a core is not judged on one. Asking whether its
        // example teaches or its key is reversible would score it on an answer it was told not to
        // give — and a zero there would read as a clean bill of health.
        if ($shape->producesCore()) {
            $checks[] = self::Example;
            $checks[] = self::Isomorphism;
            $checks[] = self::Definition;
        }
        if ($shape->hasOptions()) {
            $checks[] = self::Options;
        }
        if ($shape->hasForms()) {
            $checks[] = self::Forms;
        }
        if (! $shape->selectsItems()) {
            $checks[] = self::Verbatim;
        }

        return $checks;
    }
}
