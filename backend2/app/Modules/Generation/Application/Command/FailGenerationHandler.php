<?php

declare(strict_types=1);

namespace App\Modules\Generation\Application\Command;

use App\Modules\Generation\Domain\Repository\GenerationRequestRepository;
use App\Modules\Shared\Domain\Service\Clock;

final readonly class FailGenerationHandler
{
    public function __construct(
        private GenerationRequestRepository $requests,
        private Clock $clock,
    ) {}

    public function __invoke(FailGeneration $command): void
    {
        $request = $this->requests->findById($command->id);
        if ($request === null || $request->status()->isTerminal()) {
            return;
        }

        $request->markFailed($command->reason, $this->clock->now());
        $this->requests->save($request);
    }
}
