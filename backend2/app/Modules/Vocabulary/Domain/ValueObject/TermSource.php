<?php

declare(strict_types=1);

namespace App\Modules\Vocabulary\Domain\ValueObject;

enum TermSource: string
{
    case Curated = 'curated';
    case Ai = 'ai';
    case User = 'user';
}
