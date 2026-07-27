<?php

declare(strict_types=1);

namespace App\Modules\Learning\Application\Command;

use App\Modules\Learning\Domain\Entity\StudySession;
use App\Modules\Learning\Domain\Repository\StudySessionRepository;
use App\Modules\Learning\Domain\ValueObject\StudySessionId;
use App\Modules\Shared\Domain\Service\Clock;

final readonly class StartStudySessionHandler
{
    public function __construct(
        private StudySessionRepository $sessions,
        private Clock $clock,
    ) {}

    public function __invoke(StartStudySession $command): StudySessionId
    {
        $id = $command->sessionId ?? StudySessionId::generate();

        $this->sessions->save(StudySession::start(
            id: $id,
            userId: $command->actorId,
            mode: $command->mode,
            startedAt: $this->clock->now(),
            collectionId: $command->collectionId,
        ));

        return $id;
    }
}
