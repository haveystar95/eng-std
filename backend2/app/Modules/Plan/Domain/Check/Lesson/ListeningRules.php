<?php

declare(strict_types=1);

namespace App\Modules\Plan\Domain\Check\Lesson;

use App\Modules\Plan\Domain\Check\LessonCodes;
use App\Modules\Plan\Domain\Check\LessonRule;
use App\Modules\Plan\Domain\Check\LessonValidationContext;
use App\Modules\Plan\Domain\Check\LessonViolation;
use App\Modules\Plan\Domain\Lesson\Filler;
use App\Modules\Plan\Domain\Lesson\Lesson;
use App\Modules\Plan\Domain\Lesson\ListeningQuestion;
use App\Modules\Plan\Domain\Lesson\Message;
use App\Modules\Plan\Domain\Lesson\Phrase;
use App\Modules\Plan\Domain\Service\NativeWords;
use App\Modules\Plan\Domain\Service\Words;

/**
 * LISTENING — THE WHOLE VISIT BY EAR (`lesson_day.v4.4`, LISTENING): three to five questions, no two
 * about the same exchange, at least one about a value the learner gave (a filler said in the
 * dialogue), and where a question asks a frame's slot, its wrong options are that frame's other
 * fillers.
 *
 * The questions are in the learner's language, so the reading is by the learner's-language words
 * ({@see NativeWords}): a question belongs to the exchange whose two lines share the most content words
 * with it and its right option. Native languages the word rules do not read count only the number.
 */
final class ListeningRules implements LessonRule
{
    public const MIN_QUESTIONS = 3;

    public const MAX_QUESTIONS = 5;

    public function violations(Lesson $answer, LessonValidationContext $context): array
    {
        $out = [];
        $count = count($answer->listening);
        if ($count < self::MIN_QUESTIONS || $count > self::MAX_QUESTIONS) {
            $out[] = new LessonViolation(LessonCodes::LISTENING_COUNT, 'lesson', "{$count} questions (".self::MIN_QUESTIONS.'–'.self::MAX_QUESTIONS.')');
        }
        if ($count === 0 || ! NativeWords::isChecked($context->nativeLang)) {
            return $out;
        }

        $taken = [];
        foreach ($answer->listening as $index => $question) {
            $step = self::exchangeOf($answer, $question);
            if ($step === null) {
                continue;
            }
            if (isset($taken[$step])) {
                $out[] = new LessonViolation(
                    LessonCodes::LISTENING_SAME_EXCHANGE,
                    LessonViolation::listening($index),
                    LessonViolation::listening($taken[$step])." and this question are both about exchange {$step}",
                );

                continue;
            }
            $taken[$step] = $index;
        }

        $dialogueFillers = self::dialogueFillers($answer);
        $learnerValue = false;
        foreach ($answer->listening as $index => $question) {
            $right = $question->correctOption();
            if ($right === null) {
                continue;
            }
            foreach ($dialogueFillers as [$phrase, $filler]) {
                if (! self::same($right, $filler->native)) {
                    continue;
                }
                $learnerValue = true;
                $others = array_values(array_filter($phrase->fillers(), static fn (Filler $f): bool => $f !== $filler));
                $wrong = array_values(array_filter(
                    $question->optionsNative,
                    static fn (string $o, int $i): bool => $i !== $question->correctOptionIndex,
                    ARRAY_FILTER_USE_BOTH,
                ));
                $fromFrame = count(array_filter($wrong, static function (string $option) use ($others): bool {
                    foreach ($others as $other) {
                        if (self::same($option, $other->native)) {
                            return true;
                        }
                    }

                    return false;
                }));
                $needed = min(2, count($others));
                if ($fromFrame < $needed) {
                    $out[] = new LessonViolation(
                        LessonCodes::LISTENING_DISTRACTOR_NOT_FILLER,
                        LessonViolation::listening($index),
                        "the question asks {$phrase->id}'s slot («{$filler->native}»), but {$fromFrame} of its wrong options are {$phrase->id}'s other fillers (expected {$needed})",
                    );
                }
                break;
            }
        }
        if (! $learnerValue) {
            $out[] = new LessonViolation(LessonCodes::LISTENING_NO_LEARNER_VALUE, 'lesson', 'no question asks for a value the learner gave (a filler said in the dialogue)');
        }

        return $out;
    }

    /** The exchange a question is about: the most content words shared with its two lines; none shared — none. */
    private static function exchangeOf(Lesson $answer, ListeningQuestion $question): ?int
    {
        $asked = $question->textNative.' '.($question->correctOption() ?? '');
        $best = null;
        $bestScore = 0;
        foreach ($answer->exchanges as $exchange) {
            $lines = implode(' ', array_map(static fn (Message $m): string => $m->textNative, $exchange->messages));
            $score = NativeWords::shared($asked, $lines);
            if ($score > $bestScore) {
                $best = $exchange->step;
                $bestScore = $score;
            }
        }

        return $best;
    }

    /**
     * Every filler the dialogue says, with its frame.
     *
     * @return list<array{0: Phrase, 1: Filler}>
     */
    private static function dialogueFillers(Lesson $answer): array
    {
        $out = [];
        foreach ($answer->phrases as $phrase) {
            foreach ($answer->linesOf($phrase->id) as $use) {
                $filler = $phrase->filler($use['message']->filler);
                if ($filler !== null) {
                    $out[] = [$phrase, $filler];
                }
            }
        }

        return $out;
    }

    /** The same value: equal words, or a content word of the same root. */
    private static function same(string $a, string $b): bool
    {
        return Words::tokens($a) === Words::tokens($b) || NativeWords::shared($a, $b) > 0;
    }
}
