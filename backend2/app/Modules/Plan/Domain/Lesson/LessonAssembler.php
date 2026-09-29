<?php

declare(strict_types=1);

namespace App\Modules\Plan\Domain\Lesson;

use App\Modules\Plan\Domain\Service\FrameText;

/**
 * THE LESSON OF A DAY PUT TOGETHER FROM ITS TWO STAGES (наряд GEN-4, 3.8) — in the lesson's own shape, the one every reader
 * of a scene deals from, field for field as it was when one call wrote it: the topic, the learner's role and the partner's
 * gender of the skeleton; the dialogue's exchanges, each with its check; the skeleton's frames; the listening; the
 * vocabulary. What the two stages keep for themselves — `must_say`, `must_understand`, `partner_line`, `pairs_with` — stays
 * out of it.
 *
 * Three things are the code's here, and only three:
 *
 *  - A LINE CLOSES WITH ITS MARK. The skeleton writes its frames and the learner's lines without a full stop, and a text a
 *    card shows as a sentence is closed with «.» when it ends with no mark — a frame in both languages, every message in both
 *    languages and the learner's simplified variants. A question keeps its «?»; readings, fillers, checks and the listening
 *    are left as written.
 *  - `in_dialogue` MARKS WHAT THE DIALOGUE SAYS: every filler a learner line is found saying ({@see FrameText::line()}) is
 *    true, every other false — whatever the skeleton marked.
 *  - `used_in` NAMES THE LESSON'S PLACES: a frame stays `p3`; a partner line of the skeleton (`a4`) becomes the partner's
 *    message of the exchange that carries it (`A5`); a line no exchange carries is left out.
 */
final class LessonAssembler
{
    public static function assemble(Skeleton $skeleton, Dialogue $dialogue): Lesson
    {
        $exchanges = [];
        $steps = [];
        foreach ($dialogue->exchanges as $exchange) {
            $exchanges[] = $exchange->exchange->withMessages(array_map(self::closedMessage(...), $exchange->exchange->messages));
            if ($exchange->partnerLine !== null) {
                $steps[$exchange->partnerLine] ??= $exchange->step();
            }
        }

        $phrases = array_map(static fn (Phrase $p): Phrase => self::closedFrame($p), $skeleton->phrases());
        $said = [];
        foreach ($exchanges as $exchange) {
            $learner = $exchange->learner();
            $phrase = $learner?->phraseId === null ? null : self::find($phrases, $learner->phraseId);
            if ($learner === null || $phrase === null) {
                continue;
            }
            $filler = FrameText::line($phrase, $learner->textTarget)['filler'];
            $place = $filler === null ? false : array_search($filler, $phrase->fillers(), true);
            if (is_int($place)) {
                $said[$phrase->id][] = $place;
            }
        }
        $phrases = array_map(static fn (Phrase $p): Phrase => $p->withFillersSaid($said[$p->id] ?? []), $phrases);

        $vocabulary = array_map(static fn (VocabularyItem $item): VocabularyItem => $item->withUsedIn(self::usedIn($item->usedIn, $steps)), $skeleton->vocabulary);

        return new Lesson(
            titleTarget: $skeleton->titleTarget,
            titleNative: $skeleton->titleNative,
            descriptionTarget: $skeleton->descriptionTarget,
            descriptionNative: $skeleton->descriptionNative,
            learnerRoleTarget: $skeleton->learnerRoleTarget,
            learnerRoleNative: $skeleton->learnerRoleNative,
            roleGender: $skeleton->roleGender,
            exchanges: $exchanges,
            phrases: $phrases,
            listening: $dialogue->listening,
            vocabulary: $vocabulary,
        );
    }

    /** A text closed with «.» when it ends with no mark of its own; an empty text stays empty. */
    public static function closed(string $text): string
    {
        $text = trim($text);

        return $text === '' || FrameText::endMark($text) !== '' ? $text : $text.'.';
    }

    private static function closedFrame(Phrase $phrase): Phrase
    {
        return new Phrase($phrase->id, $phrase->kind, self::closed($phrase->frameTarget), self::closed($phrase->frameNative), $phrase->pronunciationNative, $phrase->slot);
    }

    private static function closedMessage(Message $message): Message
    {
        return $message->withLineTexts(
            self::closed($message->textTarget),
            self::closed($message->textNative),
            array_map(self::closed(...), $message->simplifiedVariants),
        );
    }

    /** @param list<Phrase> $phrases */
    private static function find(array $phrases, string $id): ?Phrase
    {
        foreach ($phrases as $phrase) {
            if ($phrase->id === $id) {
                return $phrase;
            }
        }

        return null;
    }

    /**
     * @param  list<string>  $usedIn
     * @param  array<string, int>  $steps  a partner line's id → the step of the first exchange that carries it
     * @return list<string>
     */
    private static function usedIn(array $usedIn, array $steps): array
    {
        $out = [];
        foreach ($usedIn as $ref) {
            if (preg_match('/^a\d+$/', $ref) === 1) {
                if (isset($steps[$ref])) {
                    $out[] = 'A'.$steps[$ref];
                }
            } else {
                $out[] = $ref;
            }
        }

        return array_values(array_unique($out));
    }
}
