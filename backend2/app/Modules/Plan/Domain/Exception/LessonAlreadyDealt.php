<?php

declare(strict_types=1);

namespace App\Modules\Plan\Domain\Exception;

use App\Modules\Plan\Domain\ValueObject\PlanSceneId;
use DomainException;

/**
 * A repaired card is not written into a lesson whose day is already dealt: the cards the learner holds
 * were cut from the lesson as it was, and a repair would change the lesson under them.
 */
final class LessonAlreadyDealt extends DomainException
{
    public static function scene(PlanSceneId $sceneId, int $dayNumber): self
    {
        return new self("The lesson of scene {$sceneId->value} is already dealt into day {$dayNumber}; a repair is not written into it.");
    }
}
