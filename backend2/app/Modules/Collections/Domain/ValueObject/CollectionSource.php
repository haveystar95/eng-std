<?php

declare(strict_types=1);

namespace App\Modules\Collections\Domain\ValueObject;

enum CollectionSource: string
{
    case Curated = 'curated';
    case Ai = 'ai';
    case User = 'user';
}
