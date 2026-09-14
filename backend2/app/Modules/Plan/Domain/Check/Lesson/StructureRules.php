<?php

declare(strict_types=1);

namespace App\Modules\Plan\Domain\Check\Lesson;

use App\Modules\Plan\Domain\Check\LessonCodes;
use App\Modules\Plan\Domain\Check\LessonRule;
use App\Modules\Plan\Domain\Check\LessonValidationContext;
use App\Modules\Plan\Domain\Check\LessonViolation;
use App\Modules\Plan\Domain\Lesson\Exchange;
use App\Modules\Plan\Domain\Lesson\Lesson;
use App\Modules\Plan\Domain\Lesson\Message;
use App\Modules\Plan\Domain\Service\NativeScript;

/**
 * THE SHAPE OF THE VISIT: the ordered counts, two messages per exchange opened by its initiator, a
 * closing message that is no question, three options with a real right one, readings in the
 * learner's own script. What the strict schema cannot say (it holds no list lengths, п. 202).
 */
final class StructureRules implements LessonRule
{
    public const OPTIONS = 3;

    public function violations(Lesson $answer, LessonValidationContext $context): array
    {
        $out = [];

        $exchanges = count($answer->exchanges);
        if ($exchanges !== $context->dialogueCount) {
            $out[] = new LessonViolation(LessonCodes::DIALOGUE_COUNT, 'lesson', "{$exchanges} exchanges instead of {$context->dialogueCount}");
        }
        $steps = array_map(static fn (Exchange $e): int => $e->step, $answer->exchanges);
        if ($exchanges > 0 && $steps !== range(1, $exchanges)) {
            $out[] = new LessonViolation(LessonCodes::DIALOGUE_COUNT, 'lesson', 'steps are ['.implode(', ', $steps)."], not 1..{$exchanges}");
        }
        $items = count($answer->vocabulary);
        if ($items !== $context->vocabularyCount) {
            $out[] = new LessonViolation(LessonCodes::VOCAB_COUNT, 'lesson', "{$items} vocabulary items instead of {$context->vocabularyCount}");
        }

        foreach ($answer->exchanges as $exchange) {
            $address = LessonViolation::exchange($exchange->step);
            $messages = count($exchange->messages);
            if ($messages !== 2) {
                $out[] = new LessonViolation(LessonCodes::EXCHANGE_SHAPE, $address, "{$messages} messages instead of 2");
            }
            $first = $exchange->first();
            if ($first !== null && $first->speaker !== $exchange->initiator) {
                $out[] = new LessonViolation(LessonCodes::EXCHANGE_SHAPE, $address, "the first message is by {$first->speaker}, the initiator is {$exchange->initiator}");
            }
            if ($exchange->initiator !== $exchange->kind->initiator()) {
                $out[] = new LessonViolation(LessonCodes::EXCHANGE_SHAPE, $address, "an {$exchange->kind->value} exchange is opened by {$exchange->kind->initiator()}, the initiator is {$exchange->initiator}");
            }
            $second = $exchange->second();
            if ($first !== null && $second !== null && $first->speaker === $second->speaker) {
                $out[] = new LessonViolation(LessonCodes::EXCHANGE_SHAPE, $address, "both messages are by {$first->speaker}");
            }
            if ($second !== null && $second->isQuestion()) {
                $out[] = new LessonViolation(LessonCodes::EXCHANGE_SECOND_QUESTION, self::messageAddress($second, $exchange->step), "the closing message «{$second->textTarget}» ends with a question mark");
            }

            $options = count($exchange->check->options);
            if ($options !== self::OPTIONS) {
                $out[] = new LessonViolation(LessonCodes::CHECK_SHAPE, LessonViolation::check($exchange->step), "{$options} options instead of ".self::OPTIONS);
            }
            if ($exchange->check->correctOption() === null) {
                $out[] = new LessonViolation(LessonCodes::CHECK_SHAPE, LessonViolation::check($exchange->step), "correct_option_index {$exchange->check->correctOptionIndex} points at no option");
            }
        }

        foreach ($answer->listening as $index => $question) {
            $options = count($question->optionsNative);
            if ($options !== self::OPTIONS) {
                $out[] = new LessonViolation(LessonCodes::LISTENING_SHAPE, LessonViolation::listening($index), "{$options} options instead of ".self::OPTIONS);
            }
            if ($question->correctOption() === null) {
                $out[] = new LessonViolation(LessonCodes::LISTENING_SHAPE, LessonViolation::listening($index), "correct_option_index {$question->correctOptionIndex} points at no option");
            }
        }

        return [...$out, ...self::readings($answer, $context->nativeLang)];
    }

    public static function messageAddress(Message $message, int $step): string
    {
        return $message->isLearner() ? LessonViolation::learner($step) : LessonViolation::partner($step);
    }

    /** @return list<LessonViolation> */
    private static function readings(Lesson $answer, string $nativeLang): array
    {
        if (! NativeScript::isChecked($nativeLang)) {
            return [];
        }
        /** @var list<array{0: string, 1: string|null}> $readings */
        $readings = [];
        foreach ($answer->phrases as $phrase) {
            // The slot's underscores are punctuation, not a letter of another script.
            $readings[] = [$phrase->id, $phrase->pronunciationNative];
            foreach ($phrase->fillers() as $index => $filler) {
                $readings[] = [$phrase->id.'.f'.($index + 1), $filler->pronunciationNative];
            }
        }
        foreach ($answer->vocabulary as $item) {
            $readings[] = [$item->id, $item->pronunciationNative];
        }
        foreach ($answer->exchanges as $exchange) {
            $readings[] = [LessonViolation::learner($exchange->step), $exchange->learner()?->pronunciationNative];
        }

        $out = [];
        foreach ($readings as [$address, $reading]) {
            if ($reading !== null && ! NativeScript::isValidReading($nativeLang, $reading)) {
                $out[] = new LessonViolation(LessonCodes::PRONUNCIATION_SCRIPT, $address, "the reading «{$reading}» leaves the native script");
            }
        }

        return $out;
    }
}
