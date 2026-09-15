<?php

declare(strict_types=1);

namespace App\Modules\Plan\Domain\Check\Lesson;

use App\Modules\Plan\Domain\Check\Language\LanguageSide;
use App\Modules\Plan\Domain\Check\LessonCodes;
use App\Modules\Plan\Domain\Check\LessonRule;
use App\Modules\Plan\Domain\Check\LessonValidationContext;
use App\Modules\Plan\Domain\Check\LessonViolation;
use App\Modules\Plan\Domain\Lesson\Exchange;
use App\Modules\Plan\Domain\Lesson\Lesson;

/**
 * THE SHAPE OF THE VISIT: the ordered counts, two messages per exchange opened by its initiator, a
 * closing message that is no question, three options with a real right one, readings in the
 * learner's own script. What the strict schema cannot say (it holds no list lengths, п. 202).
 *
 * «A question» is the target's question mark and a reading's script is the learner's language's — both read off
 * their packs; the counts and shapes need no language.
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

        $questions = $context->reads(LessonCodes::EXCHANGE_SECOND_QUESTION, LanguageSide::Target, 'sentence_ends');
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
            // The exchange is the card: a learner's closing question belongs to an ask of its own, a partner's is
            // a second question of the exchange — either way the repair takes the whole exchange.
            if ($questions && $second !== null && $context->targetWords()->isQuestion($second->textTarget)) {
                $out[] = new LessonViolation(LessonCodes::EXCHANGE_SECOND_QUESTION, $address, "the closing message of {$second->speaker} «{$second->textTarget}» ends with a question mark");
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

        return [...$out, ...self::readings($answer, $context)];
    }

    /** @return list<LessonViolation> */
    private static function readings(Lesson $answer, LessonValidationContext $context): array
    {
        if (! $context->reads(LessonCodes::PRONUNCIATION_SCRIPT, LanguageSide::Native, 'script')) {
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

        $words = $context->nativeWords();
        $out = [];
        foreach ($readings as [$address, $reading]) {
            if ($reading !== null && ! $words->readsInScript($reading)) {
                $out[] = new LessonViolation(LessonCodes::PRONUNCIATION_SCRIPT, $address, "the reading «{$reading}» leaves the native script");
            }
        }

        return $out;
    }
}
