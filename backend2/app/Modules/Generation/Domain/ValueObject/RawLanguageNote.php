<?php

declare(strict_types=1);

namespace App\Modules\Generation\Domain\ValueObject;

/**
 * A language problem the model reports, with its class named rather than left to prose.
 *
 * Typed because the two interesting classes are repaired differently — leakage from a close relative
 * means regenerate, a typo means edit — and a free-text note would put both in one pile that has to
 * be re-read by a human to be sorted. `kind` is the raw wire string: an unknown value is mapped to
 * the coarse `language` kind rather than throwing, so a newer prompt can add a class without
 * breaking a run.
 */
final readonly class RawLanguageNote
{
    public function __construct(
        public string $kind,
        public string $detail,
    ) {}
}
