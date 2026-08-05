<?php

declare(strict_types=1);

namespace App\Modules\Collections\Domain\Exception;

use App\Modules\Shared\Domain\Exception\ProblemDetails;
use DomainException;

/** A free-tier user tried to subscribe to (or fork) a premium store collection. */
final class SubscriptionRequired extends DomainException implements ProblemDetails
{
    public static function forPremiumCollection(): self
    {
        return new self('A premium subscription is required for this collection.');
    }

    public function problemStatus(): int
    {
        return 403;
    }

    public function problemCode(): string
    {
        return 'subscription_required';
    }

    public function problemTitle(): string
    {
        return 'Subscription required';
    }

    public function problemMeta(): array
    {
        return [];
    }
}
