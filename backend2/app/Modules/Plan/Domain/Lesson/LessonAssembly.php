<?php

declare(strict_types=1);

namespace App\Modules\Plan\Domain\Lesson;

use App\Modules\Plan\Domain\Check\Language\LanguagePack;
use App\Modules\Plan\Domain\Service\FrameText;
use App\Modules\Plan\Domain\Service\SpeakingKey;

/**
 * THE SERVED LESSON — what every reader deals the day from, put together from the stored lesson (`docs/plan-v2.md` §3а;
 * наряды GEN-2a, GEN-2b и его доработка).
 *
 * What differs from the lesson as stored is the server's to decide:
 *
 *  - WHICH FILLER A LEARNER LINE SAYS IS FOUND IN ITS TEXT ({@see FrameText::line}): the frame's fillers are tried in
 *    their order, and the first the line is said with is the line's filler. The model's `filler` field is read by
 *    nobody here. The text itself is served as stored.
 *  - EVERY FRAME'S `in_dialogue` MARKS EXACTLY THE FILLERS ITS LINES ARE FOUND SAYING.
 *  - THE SPEAKING KEY OF A LEARNER LINE IS TAKEN FROM ITS FRAME ({@see SpeakingKey}) — the model's key only for a line
 *    with no frame, and only when it stands in the line. The key needs the target's pack: which words are content.
 *
 * Where the right answer of a check or a listening question stands is no longer decided here: the server shuffles the
 * options once, when the day is built ({@see OptionShuffle}, наряд GEN-4), and the stored lesson is served in that order.
 */
final class LessonAssembly
{
    public static function serve(Lesson $answer, LanguagePack $target): Lesson
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
}
