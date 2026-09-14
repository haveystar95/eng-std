<?php

declare(strict_types=1);

namespace App\Modules\Plan\Domain\Lesson;

use App\Modules\Plan\Domain\Service\FrameText;
use App\Modules\Plan\Domain\Service\Shuffle;

/**
 * THE SERVED LESSON — what every reader deals the day from, put together from the model's answer.
 *
 * Two things differ from the answer as written, and both are the server's to decide:
 *
 *  - A LEARNER LINE THAT STANDS ON A FRAME IS THE SERVER'S ASSEMBLY of the frame and its filler
 *    ({@see FrameText::line}): the model's glue is kept when the rest is the frame word for word;
 *    otherwise the frame with the filler is what the learner reads, hears and says. A rescue line and
 *    a line with no frame stay as written.
 *  - THE RIGHT ANSWER OF EVERY CHECK AND EVERY LISTENING QUESTION STANDS AT A SHUFFLED PLACE: a model
 *    that puts it first nine times out of ten does not teach the learner to tap the first option. The
 *    shuffle is seeded by the scene and the question's address, so the same answer always serves the
 *    same lesson.
 */
final class LessonAssembly
{
    public static function serve(Lesson $answer, string $seed): Lesson
    {
        $exchanges = [];
        foreach ($answer->exchanges as $exchange) {
            $messages = [];
            foreach ($exchange->messages as $message) {
                $phrase = $message->isLearner() && $exchange->kind->takesFrame() && $message->phraseId !== null
                    ? $answer->phrase($message->phraseId)
                    : null;
                $messages[] = $phrase === null
                    ? $message
                    : $message->withText(FrameText::line($phrase, $message->filler, $message->textTarget)['text']);
            }
            $exchanges[] = $exchange
                ->withMessages($messages)
                ->withCheck(self::shuffledCheck($exchange->check, "{$seed}:x{$exchange->step}:check"));
        }

        $listening = [];
        foreach ($answer->listening as $index => $question) {
            [$options, $correct] = self::shuffled($question->optionsNative, $question->correctOptionIndex, "{$seed}:listening:{$index}");
            $listening[] = $question->withOptions($options, $correct);
        }

        return $answer->withExchanges($exchanges)->withListening($listening);
    }

    private static function shuffledCheck(ExchangeCheck $check, string $seed): ExchangeCheck
    {
        [$options, $correct] = self::shuffled($check->options, $check->correctOptionIndex, $seed);

        return $check->withOptions($options, $correct);
    }

    /**
     * The options in a seeded order and where the right one went. An index outside the options is
     * kept as it was — there is no right option to follow, and the validator counts it.
     *
     * @template T
     *
     * @param  list<T>  $options
     * @return array{0: list<T>, 1: int}
     */
    private static function shuffled(array $options, int $correct, string $seed): array
    {
        if (! array_key_exists($correct, $options)) {
            return [$options, $correct];
        }
        $order = Shuffle::seeded($seed, array_keys($options));
        $out = [];
        $moved = $correct;
        foreach ($order as $position => $original) {
            $out[] = $options[$original];
            if ($original === $correct) {
                $moved = $position;
            }
        }

        return [$out, $moved];
    }
}
