<?php

declare(strict_types=1);

namespace App\Modules\Plan\Domain\Check\Lesson;

use App\Modules\Plan\Domain\Check\LessonCheck;
use App\Modules\Plan\Domain\Check\LessonContext;
use App\Modules\Plan\Domain\Lesson\Lesson;

/** Exactly two messages per exchange, the first one spoken by the initiator, and no step number twice. */
final class ExchangeShapeCheck implements LessonCheck
{
    public function name(): string
    {
        return 'exchange_shape';
    }

    public function switchable(): bool
    {
        return true;
    }

    public function violations(Lesson $lesson, LessonContext $context): array
    {
        $out = [];
        $steps = [];
        foreach ($lesson->exchanges as $exchange) {
            if (isset($steps[$exchange->step])) {
                $out[] = "exchange {$exchange->step}: step number repeated";
            }
            $steps[$exchange->step] = true;
            $count = count($exchange->messages);
            if ($count !== 2) {
                $out[] = "exchange {$exchange->step}: {$count} messages instead of 2";
            }
            $first = $exchange->first();
            if ($first !== null && $first->speaker !== $exchange->initiator) {
                $out[] = "exchange {$exchange->step}: first message by {$first->speaker}, initiator is {$exchange->initiator}";
            }
        }

        return $out;
    }

    public function drop(Lesson $lesson, LessonContext $context): Lesson
    {
        return $lesson;
    }
}
