<?php

declare(strict_types=1);

namespace App\Modules\Learning\Application\Dto;

/** A ready-to-play session: its server-fixed id and the self-contained cards. */
final readonly class SessionView
{
    /** @param list<SessionCardView> $cards */
    public function __construct(
        public string $sessionId,
        public array $cards,
    ) {}
}
