<?php

declare(strict_types=1);

namespace App\Modules\Collections\Domain\Exception;

use DomainException;

final class NotCollectionOwner extends DomainException
{
    public static function make(): self
    {
        return new self('This collection can only be modified by its owner.');
    }
}
