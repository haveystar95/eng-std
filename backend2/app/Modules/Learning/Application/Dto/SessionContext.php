<?php

declare(strict_types=1);

namespace App\Modules\Learning\Application\Dto;

/**
 * What an answer batch needs to know about a study session it names: who owns it, whether it was
 * free practice, and the fixed set of terms it was built with.
 *
 * Composition alone used to be enough, because every session was built by the server. Offline
 * practice changes that — the device mints the session id — so the batch has to decide between
 * "a session I built for this user" and "an id I have never seen", and it must never accept an
 * answer against someone else's session.
 */
final readonly class SessionContext
{
    /** @param array<string, true> $composition term id → true, the set the session was built with */
    public function __construct(
        public string $userId,
        public bool $isPractice,
        public array $composition,
    ) {}

    public function has(string $termId): bool
    {
        return isset($this->composition[$termId]);
    }
}
