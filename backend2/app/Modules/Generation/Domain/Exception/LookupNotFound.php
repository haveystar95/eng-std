<?php

declare(strict_types=1);

namespace App\Modules\Generation\Domain\Exception;

use App\Modules\Shared\Domain\Exception\ProblemDetails;
use DomainException;

/**
 * The save points at a lookup that is not in the cache.
 *
 * Reachable in one honest way: the card was on screen long enough for the row to be gone (a
 * cleanup, a restored backup), so the client's move is to look the word up again rather than to
 * retry the save. 404 says exactly that.
 */
final class LookupNotFound extends DomainException implements ProblemDetails
{
    public static function withId(string $id): self
    {
        return new self("No cached lookup with id {$id}.");
    }

    public static function nothingToAdd(): self
    {
        return new self('A save must name either a lookup or a term.');
    }

    public function problemStatus(): int
    {
        return 404;
    }

    public function problemCode(): string
    {
        return 'lookup_not_found';
    }

    public function problemTitle(): string
    {
        return 'Lookup not found';
    }

    public function problemMeta(): array
    {
        return [];
    }
}
