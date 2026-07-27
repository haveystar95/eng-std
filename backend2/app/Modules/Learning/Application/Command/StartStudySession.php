<?php

declare(strict_types=1);

namespace App\Modules\Learning\Application\Command;

use App\Modules\Learning\Domain\ValueObject\StudyMode;
use App\Modules\Learning\Domain\ValueObject\StudySessionId;
use App\Modules\Shared\Domain\ValueObject\CollectionId;
use App\Modules\Shared\Domain\ValueObject\UserId;

/** Open a study session. The client may supply the id for offline idempotency. */
final readonly class StartStudySession
{
    public function __construct(
        public UserId $actorId,
        public StudyMode $mode,
        public ?CollectionId $collectionId = null,
        public ?StudySessionId $sessionId = null,
    ) {}
}
