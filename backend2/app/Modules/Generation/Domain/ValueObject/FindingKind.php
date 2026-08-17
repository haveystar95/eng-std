<?php

declare(strict_types=1);

namespace App\Modules\Generation\Domain\ValueObject;

/**
 * What a human has to look at. Mirrors the CHECK on `enrichment_findings.kind`.
 *
 * The three language kinds are deliberately NOT one bucket: they are fixed in different ways, so
 * merging them would produce a worklist nobody can triage.
 *   - leakage from a close relative → the item is regenerated (the model was in the wrong language);
 *   - a typo or invented word → the translation is edited in place (the item is fine);
 *   - the whole field in the wrong language → a bug upstream, not a wording problem.
 */
enum FindingKind: string
{
    /** The prompt side does not uniquely restore the reference, and no variant covers the gap. */
    case Ambiguity = 'ambiguity';

    /** A whole field is in the wrong language (e.g. non-Latin letters in an English field). */
    case Language = 'language';

    /**
     * A word from a language closely related to the learner's, sitting in their field — Ukrainian
     * lexis or суржик in a Russian translation. Written in letters both languages share, so no
     * spell-checker sees it. Fix = regenerate.
     */
    case UaLeakage = 'ua_leakage';

    /**
     * Not a real word of the learner's language: a typo, an invented form, a transliteration. Caught
     * live on «колледка» for `colleague` — a nonword no charset check could see. Fix = edit.
     */
    case MisspelledOrNonword = 'misspelled_or_nonword';

    /** A proposed correct variant equals a proposed wrong distractor — one of them is a lie. */
    case VariantConflict = 'variant_conflict';

    /** Does this kind mean "a person must re-read the wording"? Used by the run's language metric. */
    public function isLanguageIssue(): bool
    {
        return match ($this) {
            self::Language, self::UaLeakage, self::MisspelledOrNonword => true,
            self::Ambiguity, self::VariantConflict => false,
        };
    }
}
