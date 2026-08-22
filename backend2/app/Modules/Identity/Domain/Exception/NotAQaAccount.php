<?php

declare(strict_types=1);

namespace App\Modules\Identity\Domain\Exception;

use App\Modules\Shared\Domain\Exception\ProblemDetails;
use DomainException;

/**
 * The address belongs to an account that is not marked `is_qa`.
 *
 * This is the lock that makes the dev door harmless even while it is open: the door can CREATE a QA
 * account and it can enter one, and that is the whole of what it can do. An address that already
 * belongs to a real, Google-signed-in account is refused — so «the flag was left on in a build
 * somebody else runs» costs a throwaway account, not the owner's.
 */
final class NotAQaAccount extends DomainException implements ProblemDetails
{
    public static function forEmail(string $email): self
    {
        return new self("The account {$email} exists and is not a QA account.");
    }

    public function problemStatus(): int
    {
        return 403;
    }

    public function problemCode(): string
    {
        return 'not_a_qa_account';
    }

    public function problemTitle(): string
    {
        return 'Not a QA account';
    }

    public function problemMeta(): array
    {
        return [];
    }
}
