<?php

declare(strict_types=1);

namespace App\Modules\Plan\Domain\Check\Dialogue\Rule;

use App\Modules\Plan\Domain\Check\Dialogue\DialogueContext;
use App\Modules\Plan\Domain\Check\Dialogue\DialogueRule;
use App\Modules\Plan\Domain\Check\LessonViolation;
use App\Modules\Plan\Domain\Lesson\Dialogue;
use App\Modules\Plan\Domain\Lesson\DialogueExchange;
use App\Modules\Plan\Domain\ValueObject\ExchangeKind;

/** `rescue.count` — FATAL. The dialogue has exactly one rescue exchange. */
final class RescueCount implements DialogueRule
{
    public const CODE = 'rescue.count';

    public function code(): string
    {
        return self::CODE;
    }

    public function fatal(): bool
    {
        return true;
    }

    public function findings(Dialogue $dialogue, DialogueContext $context): array
    {
        $rescues = count(array_filter($dialogue->exchanges, static fn (DialogueExchange $e): bool => $e->exchange->kind === ExchangeKind::Rescue));

        return $rescues === 1 ? [] : [new LessonViolation(self::CODE, 'dialogue', "{$rescues} rescue exchanges (exactly 1)")];
    }
}
