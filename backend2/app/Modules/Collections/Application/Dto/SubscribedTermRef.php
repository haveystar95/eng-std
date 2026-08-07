<?php

declare(strict_types=1);

namespace App\Modules\Collections\Application\Dto;

use DateTimeImmutable;

/**
 * A term that enters a user's delta because a collection was (re)subscribed in-window — not
 * because the term itself changed. `updatedAt` is the subscription's added_at (the moment the term
 * came into the user's scope), so the client's cursor advances past it. The delta assembler hydrates
 * its content like any other term ref.
 */
final readonly class SubscribedTermRef
{
    public function __construct(
        public string $id,
        public DateTimeImmutable $updatedAt,
    ) {}
}
