<?php

declare(strict_types=1);

namespace App\Modules\Plan\Application\Command;

use App\Modules\Plan\Application\Port\SceneLocator;
use App\Modules\Plan\Application\Service\SceneReadiness;

final readonly class FinishIllustrationHandler
{
    public function __construct(
        private SceneLocator $scenes,
        private SceneReadiness $readiness,
    ) {}

    public function __invoke(FinishIllustration $command): void
    {
        $planId = $this->scenes->planIdOf($command->sceneId);
        if ($planId !== null) {
            $this->readiness->markReady($planId, $command->sceneId);
        }
    }
}
