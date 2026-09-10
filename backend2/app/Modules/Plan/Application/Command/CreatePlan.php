<?php

declare(strict_types=1);

namespace App\Modules\Plan\Application\Command;

use App\Modules\Plan\Domain\ValueObject\PlanLevel;
use App\Modules\Shared\Domain\ValueObject\LanguageCode;
use App\Modules\Shared\Domain\ValueObject\UserId;
use DateTimeImmutable;

/** The entry: a goal, a language, a level, a number of days and — optionally — the event's date. */
final readonly class CreatePlan
{
    public function __construct(
        public UserId $actorId,
        public string $goalText,
        public LanguageCode $targetLang,
        public PlanLevel $level,
        public int $daysTotal,
        public ?DateTimeImmutable $eventDate,
    ) {}
}
