<?php

declare(strict_types=1);

namespace App\Modules\Learning\Domain\Exception;

use App\Modules\Shared\Domain\Exception\ProblemDetails;
use DomainException;

/**
 * The event has already happened.
 *
 * A refusal rather than a clamp to «today», and the difference matters: clamping would silently
 * build a one-day plan for a date the learner mistyped, generate a day for it and charge for the
 * call. The whole plan is arithmetic over this date; getting it wrong has to stop the arithmetic,
 * not be absorbed by it.
 */
final class EventDateInPast extends DomainException implements ProblemDetails
{
    private function __construct(
        private readonly string $eventDate,
        private readonly string $today,
        string $message,
    ) {
        parent::__construct($message);
    }

    public static function make(string $eventDate, string $today): self
    {
        return new self(
            $eventDate,
            $today,
            "Дата события {$eventDate} уже прошла (сегодня {$today}) — планировать нечего.",
        );
    }

    public function problemStatus(): int
    {
        return 422;
    }

    public function problemCode(): string
    {
        return 'event_date_in_past';
    }

    public function problemTitle(): string
    {
        return 'The event date has already passed';
    }

    /** @return array<string, mixed> */
    public function problemMeta(): array
    {
        return ['event_date' => $this->eventDate, 'today' => $this->today];
    }
}
