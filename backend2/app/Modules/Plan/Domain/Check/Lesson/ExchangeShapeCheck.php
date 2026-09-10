<?php

declare(strict_types=1);

namespace App\Modules\Plan\Domain\Check\Lesson;

use App\Modules\Plan\Domain\Check\LessonCheck;
use App\Modules\Plan\Domain\Check\LessonContext;
use App\Modules\Plan\Domain\Lesson\Lesson;

/** Exactly two messages per exchange, and the first one is spoken by the initiator. */
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
        foreach ($lesson->exchanges as $exchange) {
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
