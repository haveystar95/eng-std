<?php

declare(strict_types=1);

namespace App\Modules\Generation\Domain\Exception;

use App\Modules\Generation\Domain\ValueObject\GenerationStatus;
use DomainException;

final class InvalidGenerationTransition extends DomainException
{
    public static function from(GenerationStatus $current, GenerationStatus $target): self
    {
        return new self("Cannot move a generation from {$current->value} to {$target->value}.");
    }
}
