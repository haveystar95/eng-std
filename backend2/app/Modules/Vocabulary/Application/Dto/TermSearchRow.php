<?php

declare(strict_types=1);

namespace App\Modules\Vocabulary\Application\Dto;

/**
 * One hit of the free, instant search over terms that already exist.
 *
 * Everything a search card renders, and nothing a card does not: no accepted variants, no
 * distractors, no image attribution. The learner is deciding whether this is the word they meant —
 * that decision needs the word, what it means and one sentence using it.
 */
final readonly class TermSearchRow
{
    public function __construct(
        public string $id,
        public string $lang,
        public string $text,
        public string $type,
        public ?string $transcription,
        public ?string $translation,
        public ?string $description,
        public ?string $example,
        public ?string $exampleTranslation,
        public ?string $cefr,
        /** True when the hit matched the TERM itself rather than one of its translations. */
        public bool $matchedTerm = true,
    ) {}
}
