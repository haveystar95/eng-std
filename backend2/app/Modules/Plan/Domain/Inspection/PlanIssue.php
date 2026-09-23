<?php

declare(strict_types=1);

namespace App\Modules\Plan\Domain\Inspection;

/**
 * ONE THING WRONG WITH A PLAN, AND WHERE (наряд ADM-1, «Что не так»): the check that found it, the day, the place — a line
 * ref, a card, a talk's turn, a model call — and a sentence for the operator. Findings are shown, never repaired here.
 */
final readonly class PlanIssue
{
    public const ERROR = 'error';

    public const WARNING = 'warning';

    /** @param array<string, scalar|null> $detail */
    public function __construct(
        public string $check,
        public string $severity,
        public ?int $day,
        public string $placeKind,
        public string $place,
        public string $message,
        public array $detail = [],
    ) {}
}
