<?php

declare(strict_types=1);

namespace App\Modules\Vocabulary\Domain\ValueObject;

enum TermType: string
{
    case Word = 'word';
    case Phrase = 'phrase';
    case Idiom = 'idiom';
    case PhrasalVerb = 'phrasal_verb';

    /**
     * Everything that isn't a single word is graded and rendered phrase-like: idioms and phrasal
     * verbs are multi-token expressions, so they follow the recognition path, not word spelling.
     * The one place that decides "word vs phrase behaviour" — never compare against `Phrase` alone.
     */
    public function isPhraseLike(): bool
    {
        return $this !== self::Word;
    }
}
