<?php

declare(strict_types=1);

namespace App\Modules\Plan\Application\Command;

use App\Modules\Plan\Domain\ValueObject\PlanSceneId;

/**
 * The photo job gave up: the day is made ready without the photos it did not find — its slots keep
 * their tones (DAY-UI-3). The photos are best effort; the day is not.
 */
final readonly class FinishIllustration
{
    public function __construct(public PlanSceneId $sceneId) {}
}
