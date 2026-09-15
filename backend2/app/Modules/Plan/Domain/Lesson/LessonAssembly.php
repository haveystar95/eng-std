<?php

declare(strict_types=1);

namespace App\Modules\Plan\Domain\Lesson;

use App\Modules\Plan\Domain\Check\Language\LanguagePack;
use App\Modules\Plan\Domain\Service\FrameText;
use App\Modules\Plan\Domain\Service\Shuffle;
use App\Modules\Plan\Domain\Service\SpeakingKey;

/**
 * THE SERVED LESSON — what every reader deals the day from, put together from the model's answer (`docs/plan-v2.md`
 * §3а; наряды GEN-2a, GEN-2b и его доработка).
 *
 * What differs from the answer as written is the server's to decide:
 *
 *  - WHICH FILLER A LEARNER LINE SAYS IS FOUND IN ITS TEXT ({@see FrameText::line}): the frame's fillers are tried in
 *    their order, and the first the line is said with is the line's filler. The model's `filler` field is read by
 *    nobody — not here, not by the validator, not by a repair, not by the day. The text itself is served as the model
 *    wrote it: a line that is its frame with a filler keeps its glue and its closing mark; a line that is not is
 *    `line.ne_frame`, and such a day is not dealt before a repair.
 *  - EVERY FRAME'S `in_dialogue` MARKS EXACTLY THE FILLERS ITS LINES ARE FOUND SAYING. The model's marks are read only by
 *    `filler.one_in_dialogue`, the finding that they differ.
 *  - THE SPEAKING KEY OF A LEARNER LINE IS TAKEN FROM ITS FRAME ({@see SpeakingKey}) — the model's key only for a line
 *    with no frame, and only when it stands in the line. The key needs the target's pack: which words are content.
 *  - THE RIGHT ANSWER OF EVERY CHECK AND EVERY LISTENING QUESTION STANDS AT A SHUFFLED PLACE: a model that puts it first
 *    nine times out of ten does not teach the learner to tap the first option. The shuffle is seeded by the scene and
 *    the question's address, so the same answer always serves the same lesson.
 */
final class LessonAssembly
{
    public static function serve(Lesson $answer, string $seed, LanguagePack $target): Lesson
    {
        $said = self::said($answer, $target);

        $exchanges = array_map(
            static fn (Exchange $exchange): Exchange => $exchange->withCheck(self::shuffledCheck($exchange->check, "{$seed}:x{$exchange->step}:check")),
            $said->exchanges,
        );
        $listening = [];
        foreach ($said->listening as $index => $question) {
            [$options, $correct] = self::shuffled($question->optionsNative, $question->correctOptionIndex, "{$seed}:listening:{$index}");
            $listening[] = $question->withOptions($options, $correct);
        }

        return $said->withExchanges($exchanges)->withListening($listening);
    }

    /**
     * The answer as the server reads it, nothing shuffled: every learner line with the filler found in its text and
     * the key of its frame, every frame with the marks of what its lines say. What a repair is shown of a lesson.
     */
    public static function said(Lesson $answer, LanguagePack $target): Lesson
    {
        /** @var array<string, list<int>> $said the places of the fillers each frame's lines say */
        $said = [];
        $exchanges = [];
        foreach ($answer->exchanges as $exchange) {
            $messages = [];
            foreach ($exchange->messages as $message) {
                if (! $message->isLearner()) {
                    $messages[] = $message;

                    continue;
                }
                $phrase = $message->phraseId === null ? null : $answer->phrase($message->phraseId);
                if ($phrase === null) {
                    $messages[] = $message->withServerReading(null, SpeakingKey::ofLine($message->textTarget, $message->speakingKey));

                    continue;
                }
                $filler = FrameText::line($phrase, $message->textTarget)['filler'];
                $place = $filler === null ? false : array_search($filler, $phrase->fillers(), true);
                if (is_int($place)) {
                    $said[$phrase->id][] = $place;
                }
                $messages[] = $message->withServerReading($filler?->target, SpeakingKey::ofFrame($phrase->frameTarget, $target));
            }
            $exchanges[] = $exchange->withMessages($messages);
        }

        return $answer
            ->withExchanges($exchanges)
            ->withPhrases(array_map(static fn (Phrase $p): Phrase => $p->withFillersSaid($said[$p->id] ?? []), $answer->phrases));
    }

    /**
     * The filler a learner line says, as the server finds it in its text; null for a line on no frame of the lesson,
     * on a frame without a slot, or on none of its frame's fillers.
     */
    public static function fillerOf(Lesson $answer, Message $message): ?Filler
    {
        $phrase = ! $message->isLearner() || $message->phraseId === null ? null : $answer->phrase($message->phraseId);

        return $phrase === null ? null : FrameText::line($phrase, $message->textTarget)['filler'];
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
