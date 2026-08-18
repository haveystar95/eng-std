<?php

declare(strict_types=1);

namespace App\Modules\Vocabulary\Application\Dto;

/** One translation that has stopped pointing at its own term, and which groups it dropped. */
final readonly class TranslationKeyCandidate
{
    /** @param list<string> $groups the addressee groups the pair tripped */
    public function __construct(
        public string $termId,
        public string $termText,
        public string $translation,
        public array $groups,
    ) {}
}
