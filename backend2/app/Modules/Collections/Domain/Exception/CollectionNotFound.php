<?php

declare(strict_types=1);

namespace App\Modules\Collections\Domain\Exception;

use App\Modules\Shared\Domain\Exception\ProblemDetails;
use App\Modules\Shared\Domain\ValueObject\CollectionId;
use RuntimeException;

final class CollectionNotFound extends RuntimeException implements ProblemDetails
{
    private string $collectionId = '';

    public static function withId(CollectionId $id): self
    {
        $exception = new self("Collection not found: {$id->value}");
        $exception->collectionId = $id->value;

        return $exception;
    }

    public function problemStatus(): int
    {
        return 404;
    }

    public function problemCode(): string
    {
        return 'collection_not_found';
    }

    public function problemTitle(): string
    {
        return 'Collection not found';
    }

    public function problemMeta(): array
    {
        return ['collection_id' => $this->collectionId];
    }
}
