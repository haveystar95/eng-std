<?php

declare(strict_types=1);

namespace App\Modules\Collections\Domain\ValueObject;

enum CollectionType: string
{
    case System = 'system';
    case Shared = 'shared';
    case Custom = 'custom';
}
