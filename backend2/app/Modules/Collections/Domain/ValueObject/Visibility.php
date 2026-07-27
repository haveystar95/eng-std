<?php

declare(strict_types=1);

namespace App\Modules\Collections\Domain\ValueObject;

enum Visibility: string
{
    case Private = 'private';
    case Link = 'link';
    case Public = 'public';
}
