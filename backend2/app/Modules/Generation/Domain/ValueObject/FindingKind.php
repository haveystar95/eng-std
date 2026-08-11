<?php

declare(strict_types=1);

namespace App\Modules\Generation\Domain\ValueObject;

/** What a human has to look at. Mirrors the CHECK on `enrichment_findings.kind`. */
enum FindingKind: string
{
    /** The Russian prompt does not uniquely restore the reference, and no variant covers the gap. */
    case Ambiguity = 'ambiguity';

    /** UA lexis in a Russian field, or non-English in an English field. */
    case Language = 'language';

    /** A proposed correct variant equals a proposed wrong distractor — one of them is a lie. */
    case VariantConflict = 'variant_conflict';
}
