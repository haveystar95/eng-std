<?php

declare(strict_types=1);

namespace App\Modules\Vocabulary\Application\Dto;

/**
 * One (term, primary translation) pair with enough context to be proof-read: what the learner is
 * asked and where they meet it.
 *
 * The collection titles are plural because a term is deduplicated globally — one bad translation can
 * be the question in several decks, and a reader deciding whether it is worth changing wants to know
 * that before they change it.
 */
final readonly class TranslationKeyRow
{
    /** @param list<string> $collections titles of the live collections this term sits in */
    public function __construct(
        public string $termId,
        public string $termText,
        public string $translationId,
        public string $translation,
        public array $collections,
    ) {}
}
