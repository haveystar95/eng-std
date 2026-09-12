<?php

declare(strict_types=1);

namespace App\Modules\Identity\Application\Command;

use App\Modules\Identity\Application\Port\VisitLog;
use App\Modules\Identity\Domain\Service\VisitThrottle;
use App\Modules\Shared\Domain\Service\Clock;
use App\Modules\Shared\Domain\Service\TransactionManager;

/** `POST /devices/visit`: a row, unless the last recorded visit is less than half an hour old ({@see VisitThrottle}). */
final readonly class RecordVisitHandler
{
    public function __construct(
        private VisitLog $visits,
        private Clock $clock,
        private TransactionManager $tx,
    ) {}

    /** @return bool whether a row was written */
    public function __invoke(RecordVisit $command): bool
    {
        $now = $this->clock->now();

        return $this->tx->run(function () use ($command, $now): bool {
            $last = $this->visits->lastVisitAtForUpdate($command->userId);
            if (! VisitThrottle::shouldRecord($last, $now)) {
                return false;
            }
            $this->visits->append($command->userId, $now);

            return true;
        });
    }
}
