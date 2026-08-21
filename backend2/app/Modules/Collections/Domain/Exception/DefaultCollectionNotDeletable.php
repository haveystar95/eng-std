<?php

declare(strict_types=1);

namespace App\Modules\Collections\Domain\Exception;

use App\Modules\Shared\Domain\Exception\ProblemDetails;
use DomainException;

/**
 * «Сохранённые» cannot be deleted — it is the folder a one-tap save lands in.
 *
 * 409 rather than 403: the actor is the owner and is allowed to touch this collection (they may
 * rename it), so the refusal is about the STATE of the thing, not about who is asking. The client
 * switches on the code to grey the delete action out rather than to show an access error.
 */
final class DefaultCollectionNotDeletable extends DomainException implements ProblemDetails
{
    public static function make(): self
    {
        return new self('The default folder cannot be deleted.');
    }

    public function problemStatus(): int
    {
        return 409;
    }

    public function problemCode(): string
    {
        return 'collection_not_deletable';
    }

    public function problemTitle(): string
    {
        return 'Default collection cannot be deleted';
    }

    public function problemMeta(): array
    {
        return [];
    }
}
