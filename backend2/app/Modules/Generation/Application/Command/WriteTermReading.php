<?php

declare(strict_types=1);

namespace App\Modules\Generation\Application\Command;

use App\Modules\Shared\Domain\ValueObject\TermId;

/**
 * Give this term its reading hint in this SUPPORT language, if it has none.
 *
 * `supportLang` comes from the PAIR the card is being built in — the folder's own source language,
 * or the lookup's — never from the learner's profile, because one learner may keep folders in two
 * pairs and the hint is a fact about a pair.
 */
final readonly class WriteTermReading
{
    public function __construct(
        public TermId $termId,
        public string $supportLang,
    ) {}
}
