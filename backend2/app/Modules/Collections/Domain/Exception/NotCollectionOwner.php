<?php

declare(strict_types=1);

namespace App\Modules\Collections\Domain\Exception;

use App\Modules\Shared\Domain\Exception\ProblemDetails;
use DomainException;

final class NotCollectionOwner extends DomainException implements ProblemDetails
{
    public static function make(): self
    {
        return new self('This collection can only be modified by its owner.');
    }

    public function problemStatus(): int
    {
        return 403;
    }

    public function problemCode(): string
    {
        return 'collection_not_editable';
    }

    public function problemTitle(): string
    {
        return 'Collection is not editable';
    }

    public function problemMeta(): array
    {
        return [];
    }
}
