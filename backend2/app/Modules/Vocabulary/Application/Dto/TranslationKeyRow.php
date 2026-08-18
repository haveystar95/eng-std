<?php

declare(strict_types=1);

namespace App\Modules\Vocabulary\Application\Dto;

/** One (term, primary translation) pair: the question the learner is asked, and its only answer. */
final readonly class TranslationKeyRow
{
    public function __construct(
        public string $termId,
        public string $termText,
        public string $translationId,
        public string $translation,
    ) {}
}
