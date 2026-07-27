<?php

declare(strict_types=1);

namespace App\Modules\Vocabulary\Domain\ValueObject;

enum TermType: string
{
    case Word = 'word';
    case Phrase = 'phrase';
}
