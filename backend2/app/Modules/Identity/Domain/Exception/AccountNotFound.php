<?php

declare(strict_types=1);

namespace App\Modules\Identity\Domain\Exception;

use App\Modules\Shared\Domain\Exception\ProblemDetails;
use DomainException;

/**
 * The account asked to be deleted is gone already (наряд ACC-1 §1: «идемпотентно, повтор — 404»). A repeat with the same
 * token never gets this far — every token is revoked with the account, so it is a 401 at the door; this is the repeat
 * that came through the door before the first deletion committed, waited on the account's row and found it gone.
 */
final class AccountNotFound extends DomainException implements ProblemDetails
{
    public static function withId(string $userId): self
    {
        return new self("The account {$userId} does not exist (deleted already).");
    }

    public function problemStatus(): int
    {
        return 404;
    }

    public function problemCode(): string
    {
        return 'account_not_found';
    }

    public function problemTitle(): string
    {
        return 'The account does not exist';
    }

    public function problemMeta(): array
    {
        return [];
    }
}
