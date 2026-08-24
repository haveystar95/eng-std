<?php

declare(strict_types=1);

namespace App\Modules\Vocabulary\Domain\ValueObject;

use App\Modules\Shared\Domain\Service\TextNormalizer;
use App\Modules\Shared\Domain\ValueObject\LanguageCode;
use InvalidArgumentException;

/**
 * A near-synonym of a term, on the STUDIED side: `purpose` → `goal`.
 *
 * The word is canonicalised on the way in, like every other content string this module stores
 * ({@see TermText}, {@see Translation}, {@see Example}) — DECISIONS п. 87, and the reason is the
 * same here as everywhere: this value is COMPARED, both against a learner's typing and against the
 * text of other terms, and a comparison between two spellings of one word answers the wrong
 * question.
 *
 * A synonym is not a variant. See the `term_synonyms` migration for the whole of that distinction;
 * the part that matters to this class is that it carries the TERM's language, because everything
 * that reads it is asking a same-language question.
 */
final readonly class Synonym
{
    public string $text;

    public function __construct(
        public LanguageCode $lang,
        string $text,
        public SynonymSource $source = SynonymSource::Auto,
    ) {
        $trimmed = trim((new TextNormalizer())->canonical($text));
        if ($trimmed === '') {
            throw new InvalidArgumentException('A synonym cannot be empty.');
        }
        $this->text = $trimmed;
    }
}
