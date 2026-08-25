<?php

declare(strict_types=1);

namespace App\Modules\Generation\Application\Dto;

/**
 * One term, and the two languages that decide what its reading hint must look like.
 *
 * `supportLang` is whose ALPHABET the answer is written in — the side the learner reads — and it is
 * the reason this value is per pair rather than per term: «cómo estás» is «комо эстас» for a Russian
 * learner and something else again for a Ukrainian one.
 */
final readonly class TermReadingBrief
{
    public function __construct(
        public string $text,
        public string $termLang,
        public string $supportLang,
    ) {}
}
