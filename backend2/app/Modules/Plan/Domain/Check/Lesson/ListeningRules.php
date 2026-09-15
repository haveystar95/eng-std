<?php

declare(strict_types=1);

namespace App\Modules\Plan\Domain\Check\Lesson;

use App\Modules\Plan\Domain\Check\Language\LanguageSide;
use App\Modules\Plan\Domain\Check\Language\LanguageWords;
use App\Modules\Plan\Domain\Check\LessonCodes;
use App\Modules\Plan\Domain\Check\LessonRule;
use App\Modules\Plan\Domain\Check\LessonValidationContext;
use App\Modules\Plan\Domain\Check\LessonViolation;
use App\Modules\Plan\Domain\Lesson\Filler;
use App\Modules\Plan\Domain\Lesson\Lesson;
use App\Modules\Plan\Domain\Lesson\LessonAssembly;
use App\Modules\Plan\Domain\Lesson\ListeningQuestion;
use App\Modules\Plan\Domain\Lesson\Message;
use App\Modules\Plan\Domain\Lesson\Phrase;
use App\Modules\Plan\Domain\Service\Words;

/**
 * LISTENING — THE WHOLE VISIT BY EAR (`lesson_day.v4.5`, LISTENING): three to five questions, no two
 * about the same exchange, at least one about a value the learner gave (a filler said in the
 * dialogue), and where a question asks a frame's slot, its wrong options are values of the slot's kind.
 *
 * The prompt asks for that frame's other fillers; the architect counts only a wrong option of ANOTHER kind
 * (после GEN-2a: «Один день» beside «Три дня» is a fair distractor even when the frame's list has «со
 * вчера»). The kind a code can tell without meaning is read off the right option — the slot's value as the
 * question asks it — by {@see LanguageWords::valueKind()}: a wrong option is of another kind when one of the two
 * is nothing but a number or a time and the other has neither («Три дня» beside «Кашель»); a count of a thing
 * («две воды», «14A у окна») fits beside both.
 *
 * The questions are in the learner's language, so every word is read by that language's pack: a question belongs
 * to the exchange whose two lines share the most content words with it and its right option. A language with no
 * pack counts only the number of questions.
 */
final class ListeningRules implements LessonRule
{
    public const MIN_QUESTIONS = 3;

    public const MAX_QUESTIONS = 5;

    public function violations(Lesson $answer, LessonValidationContext $context): array
    {
        $sameExchange = $context->reads(LessonCodes::LISTENING_SAME_EXCHANGE, LanguageSide::Native, 'function_words', 'word_forms');
        $learnerValue = $context->reads(LessonCodes::LISTENING_NO_LEARNER_VALUE, LanguageSide::Native, 'function_words', 'word_forms');
        $distractors = $context->reads(LessonCodes::LISTENING_DISTRACTOR_NOT_FILLER, LanguageSide::Native, 'function_words', 'word_forms', 'number_pattern', 'time_pattern');

        $out = [];
        $count = count($answer->listening);
        if ($count < self::MIN_QUESTIONS || $count > self::MAX_QUESTIONS) {
            $out[] = new LessonViolation(LessonCodes::LISTENING_COUNT, 'lesson', "{$count} questions (".self::MIN_QUESTIONS.'–'.self::MAX_QUESTIONS.')');
        }
        if ($count === 0 || (! $sameExchange && ! $learnerValue)) {
            return $out;
        }
        $words = $context->nativeWords();

        if ($sameExchange) {
            $taken = [];
            foreach ($answer->listening as $index => $question) {
                $step = self::exchangeOf($answer, $question, $words);
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
        }

        $dialogueFillers = self::dialogueFillers($answer);
        $valueAsked = false;
        foreach ($answer->listening as $index => $question) {
            $right = $question->correctOption();
            if ($right === null) {
                continue;
            }
            foreach ($dialogueFillers as [$phrase, $filler]) {
                if (! self::same($right, $filler->native, $words)) {
                    continue;
                }
                $valueAsked = true;
                if (! $distractors) {
                    break;
                }
                $kind = $words->valueKind($right);
                foreach ($question->optionsNative as $i => $option) {
                    if ($i !== $question->correctOptionIndex && self::otherKind($kind, $words->valueKind($option))) {
                        $out[] = new LessonViolation(
                            LessonCodes::LISTENING_DISTRACTOR_NOT_FILLER,
                            LessonViolation::listening($index),
                            "the question asks {$phrase->id}'s slot («{$filler->native}»): the right option «{$right}» is ".self::kindName($kind).", the wrong option «{$option}» is ".self::kindName($words->valueKind($option)),
                        );
                        break;
                    }
                }
                break;
            }
        }
        if ($learnerValue && ! $valueAsked) {
            $out[] = new LessonViolation(LessonCodes::LISTENING_NO_LEARNER_VALUE, 'lesson', 'no question asks for a value the learner gave (a filler said in the dialogue)');
        }

        return $out;
    }

    /** The exchange a question is about: the most content words shared with its two lines; none shared — none. */
    private static function exchangeOf(Lesson $answer, ListeningQuestion $question, LanguageWords $words): ?int
    {
        $asked = $question->textNative.' '.($question->correctOption() ?? '');
        $best = null;
        $bestScore = 0;
        foreach ($answer->exchanges as $exchange) {
            $lines = implode(' ', array_map(static fn (Message $m): string => $m->textNative, $exchange->messages));
            $score = $words->shared($asked, $lines);
            if ($score > $bestScore) {
                $best = $exchange->step;
                $bestScore = $score;
            }
        }

        return $best;
    }

    /**
     * Every filler the dialogue says, with its frame — as the server finds it in the lines.
     *
     * @return list<array{0: Phrase, 1: Filler}>
     */
    private static function dialogueFillers(Lesson $answer): array
    {
        $out = [];
        foreach ($answer->phrases as $phrase) {
            foreach ($answer->linesOf($phrase->id) as $use) {
                $filler = LessonAssembly::fillerOf($answer, $use['message']);
                if ($filler !== null) {
                    $out[] = [$phrase, $filler];
                }
            }
        }

        return $out;
    }

    /** Of another kind: nothing but a number or a time beside no number and no time at all — a count of a thing fits either. */
    private static function otherKind(string $a, string $b): bool
    {
        return [$a, $b] === [LanguageWords::VALUE_NUMBER_OR_TIME, LanguageWords::VALUE_OTHER]
            || [$a, $b] === [LanguageWords::VALUE_OTHER, LanguageWords::VALUE_NUMBER_OR_TIME];
    }

    private static function kindName(string $kind): string
    {
        return match ($kind) {
            LanguageWords::VALUE_NUMBER_OR_TIME => 'a number or a time',
            LanguageWords::VALUE_OTHER => 'neither a number nor a time',
            default => 'a count or a time of a thing',
        };
    }

    /** The same value: equal words, or a content word of the same root. */
    private static function same(string $a, string $b, LanguageWords $words): bool
    {
        return Words::tokens($a) === Words::tokens($b) || $words->shared($a, $b) > 0;
    }
}
