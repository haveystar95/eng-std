<?php

declare(strict_types=1);

namespace App\Modules\Vocabulary\Domain\ValueObject;

use App\Modules\Shared\Domain\Service\TextNormalizer;
use App\Modules\Shared\Domain\ValueObject\LanguageCode;
use InvalidArgumentException;

/**
 * How the term READS, written in the letters of the support language: «cómo estás» → «комо эстас».
 *
 * `lang` is the SUPPORT language, not the term's — that is the whole point of the value, and it is
 * why this is keyed per pair rather than per term. A Ukrainian-speaking learner and a Russian-speaking
 * one get different strings for the same word, and neither is a property of the word.
 *
 * Canonicalised on the way in like every other content string this module stores (DECISIONS п. 87).
 * Whether the letters are actually the support language's alphabet is decided BEFORE this — by
 * {@see \App\Modules\Generation\Domain\Service\EnrichmentValidator::transliterationFor()}, which is
 * where a model's output is judged; this class stores what survived that.
 */
final readonly class Transliteration
{
    public string $text;

    public function __construct(
        public LanguageCode $lang,
        string $text,
        public SynonymSource $source = SynonymSource::Auto,
    ) {
        $trimmed = trim((new TextNormalizer())->canonical($text));
        if ($trimmed === '') {
            throw new InvalidArgumentException('A transliteration cannot be empty.');
        }
        $this->text = $trimmed;
    }
}
