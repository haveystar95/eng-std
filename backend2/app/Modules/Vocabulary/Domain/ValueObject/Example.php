<?php

declare(strict_types=1);

namespace App\Modules\Vocabulary\Domain\ValueObject;

use App\Modules\Shared\Domain\Service\TextNormalizer;
use App\Modules\Shared\Domain\ValueObject\LanguageCode;
use InvalidArgumentException;

/**
 * A usage example for a term: a target-language sentence with an optional translation.
 *
 * Both halves arrive canonical — one of the three content gates, see {@see TermText}.
 */
final class Example
{
    public readonly string $sentence;

    public readonly ?string $sentenceTranslation;

    /**
     * @param  string|null  $sentenceTranslation  the gloss shown beside the sentence
     * @param  LanguageCode|null  $translationLang  the language that gloss is written in. Required
     *         whenever there IS a gloss, and refused when there is not: an example translation whose
     *         language is implied is exactly the state `example_translations` exists to end — the
     *         old column held whichever language the collection that first pulled the term in
     *         happened to support, and every reader downstream had to guess.
     * @param  Provenance|null  $provenance  which prompt version and model wrote this sentence,
     *                                       when it came from the станок — see {@see Provenance}.
     */
    public function __construct(
        string $sentence,
        ?string $sentenceTranslation = null,
        public readonly ?LanguageCode $translationLang = null,
        public readonly ?Provenance $provenance = null,
    ) {
        $normalizer = new TextNormalizer();

        $trimmed = trim($normalizer->canonical($sentence));
        if ($trimmed === '') {
            throw new InvalidArgumentException('Example sentence cannot be empty.');
        }
        if ($sentenceTranslation !== null && trim($sentenceTranslation) !== '' && $translationLang === null) {
            throw new InvalidArgumentException('An example translation must name the language it is written in.');
        }
        $this->sentence = $trimmed;
        $this->sentenceTranslation = $sentenceTranslation !== null
            ? trim($normalizer->canonical($sentenceTranslation))
            : null;
    }
}
