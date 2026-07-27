<?php

declare(strict_types=1);

namespace App\Modules\Collections\Domain\Exception;

use App\Modules\Shared\Domain\ValueObject\CollectionId;
use RuntimeException;

final class CollectionNotFound extends RuntimeException
{
    public static function withId(CollectionId $id): self
    {
        return new self("Collection not found: {$id->value}");
    }
}
