<?php

declare(strict_types=1);

namespace App\Modules\Plan\Domain\ValueObject;

/** A term of the day as the sheet, the program and the collection see it. */
enum TermKind: string
{
    case Word = 'word';
    case Chunk = 'chunk';
    case Phrase = 'phrase';

    /** How the collection files it: a single word, or a multi-word expression. */
    public function vocabularyType(): string
    {
        return $this === self::Word ? 'word' : 'phrase';
    }
}
