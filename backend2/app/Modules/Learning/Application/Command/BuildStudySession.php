<?php

declare(strict_types=1);

namespace App\Modules\Learning\Application\Command;

use App\Modules\Learning\Domain\ValueObject\StudySessionId;
use App\Modules\Shared\Domain\ValueObject\UserId;

/**
 * Build a study session — scoped to one collection or global. Practice sessions never introduce
 * new terms or spend the daily quota. The client may supply the id for offline idempotency.
 */
final readonly class BuildStudySession
{
    public function __construct(
        public UserId $actorId,
        public bool $isPractice = false,
        public ?string $collectionId = null,
        public int $sessionSize = 20,
        public ?StudySessionId $sessionId = null,
    ) {}
}
