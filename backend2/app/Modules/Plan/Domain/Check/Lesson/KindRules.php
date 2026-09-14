<?php

declare(strict_types=1);

namespace App\Modules\Plan\Domain\Check\Lesson;

use App\Modules\Plan\Domain\Check\LessonCodes;
use App\Modules\Plan\Domain\Check\LessonRule;
use App\Modules\Plan\Domain\Check\LessonValidationContext;
use App\Modules\Plan\Domain\Check\LessonViolation;
use App\Modules\Plan\Domain\Lesson\Exchange;
use App\Modules\Plan\Domain\Lesson\Lesson;
use App\Modules\Plan\Domain\Service\EnglishWords;
use App\Modules\Plan\Domain\ValueObject\ExchangeKind;

/**
 * THE KINDS OF EXCHANGE (`lesson_day.v4.4`, EXCHANGE KINDS): at least two asks, at most one rescue;
 * a rescue is opened by the learner, follows a partner line, and its partner reply says that line
 * again — shorter or slower, with no new fact.
 *
 * «No new fact» is read by words: the repeat may drop words and change their form, but two content
 * words the previous partner line did not have, or any number it did not have, are a new fact.
 */
final class KindRules implements LessonRule
{
    public const MIN_ASKS = 2;

    public const MAX_RESCUES = 1;

    public function violations(Lesson $answer, LessonValidationContext $context): array
    {
        $out = [];
        $asks = count(array_filter($answer->exchanges, static fn (Exchange $e): bool => $e->kind === ExchangeKind::Ask));
        if ($asks < self::MIN_ASKS) {
            $out[] = new LessonViolation(LessonCodes::KIND_ASK_COUNT, 'lesson', "{$asks} ask exchanges (at least ".self::MIN_ASKS.')');
        }
        $rescues = count(array_filter($answer->exchanges, static fn (Exchange $e): bool => $e->kind === ExchangeKind::Rescue));
        if ($rescues > self::MAX_RESCUES) {
            $out[] = new LessonViolation(LessonCodes::KIND_RESCUE_COUNT, 'lesson', "{$rescues} rescue exchanges (at most ".self::MAX_RESCUES.')');
        }

        foreach ($answer->exchanges as $index => $exchange) {
            if ($exchange->kind !== ExchangeKind::Rescue) {
                continue;
            }
            $address = LessonViolation::exchange($exchange->step);
            $first = $exchange->first();
            if ($first === null || ! $first->isLearner()) {
                $out[] = new LessonViolation(LessonCodes::RESCUE_NOT_FIRST, $address, 'a rescue opens with the learner, not the partner');
            }

            $previous = $answer->exchanges[$index - 1] ?? null;
            $said = $previous?->partner();
            if ($previous === null || $said === null || $previous->kind === ExchangeKind::Rescue) {
                $out[] = new LessonViolation(LessonCodes::RESCUE_NO_PREV, $address, 'there is no previous partner line to repeat');

                continue;
            }
            $repeat = $exchange->partner();
            if ($repeat === null || $context->targetLang !== 'en') {
                continue;
            }
            $new = EnglishWords::notIn($repeat->textTarget, $said->textTarget);
            $numbers = array_values(array_filter($new, static fn (string $w): bool => preg_match('/\d/', $w) === 1));
            if (count($new) >= 2 || $numbers !== []) {
                $out[] = new LessonViolation(
                    LessonCodes::RESCUE_NEW_FACT,
                    LessonViolation::partner($exchange->step),
                    'the repeat says «'.implode('», «', $new)."», which exchange {$previous->step} did not",
                );
            }
        }

        return $out;
    }
}
