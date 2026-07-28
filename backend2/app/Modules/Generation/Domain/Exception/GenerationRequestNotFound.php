<?php

declare(strict_types=1);

namespace App\Modules\Generation\Domain\Exception;

use App\Modules\Shared\Domain\ValueObject\GenerationRequestId;
use RuntimeException;

final class GenerationRequestNotFound extends RuntimeException
{
    public static function withId(GenerationRequestId $id): self
    {
        return new self("Generation request {$id->value} was not found.");
    }
}
