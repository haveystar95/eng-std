<?php

declare(strict_types=1);

namespace App\Modules\Plan\Domain\Assembly;

use App\Modules\Plan\Domain\Check\Language\LanguagePack;
use App\Modules\Plan\Domain\Check\Language\LanguageWords;
use App\Modules\Plan\Domain\Lesson\Lesson;
use App\Modules\Plan\Domain\Lesson\ListeningQuestion;
use App\Modules\Plan\Domain\Lesson\Message;

/**
 * THE EXCHANGE A LISTENING QUESTION IS ABOUT — the day's listening cards' reading (наряд SESSION-1a,
 * `listen_question.exchange_step`).
 *
 * The questions are in the learner's language, so the words are read by that language's pack: a question belongs to
 * the exchange whose two lines share the most content words with it and its right option; none shared — none.
 */
final class ListeningExchange
{
    /** The keys of the learner's pack the reading needs. */
    public const KEYS = ['function_words', 'word_forms'];

    public static function of(Lesson $lesson, ListeningQuestion $question, LanguageWords $words): ?int
    {
        $asked = $question->textNative.' '.($question->correctOption() ?? '');
        $best = null;
        $bestScore = 0;
        foreach ($lesson->exchanges as $exchange) {
            $lines = implode(' ', array_map(static fn (Message $m): string => $m->textNative, $exchange->messages));
            $score = $words->shared($asked, $lines);
            if ($score > $bestScore) {
                $best = $exchange->step;
                $bestScore = $score;
            }
        }

        return $best;
    }

    /** The same reading off the learner's pack — null when the pack has not the words it needs (no rule, no guess). */
    public static function inPack(Lesson $lesson, ListeningQuestion $question, LanguagePack $native): ?int
    {
        foreach (self::KEYS as $key) {
            if (! $native->has($key)) {
                return null;
            }
        }

        return self::of($lesson, $question, new LanguageWords($native));
    }
}
