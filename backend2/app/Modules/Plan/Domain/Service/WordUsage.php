<?php

declare(strict_types=1);

namespace App\Modules\Plan\Domain\Service;

use App\Modules\Plan\Domain\Lesson\Lesson;
use App\Modules\Plan\Domain\Lesson\Message;
use App\Modules\Plan\Domain\ValueObject\Speaker;

/**
 * «В РАЗГОВОРЕ» — THE LINE OF THE DAY A WORD IS SAID IN (кадр 23-0e, DAY-UI-3).
 *
 * Read off the SERVED lesson by the word's `used_in`, in its order: a frame id names the learner lines
 * that stand on that frame, a partner reference (`A3`) the partner's line of that exchange — the first
 * of them that really contains the word is its line. A word `used_in` places nowhere in the dialogue
 * (a filler the dialogue does not say) falls back to the first line of the visit that contains it.
 * With the line: whose it is (the voice it is heard in) and where the word stands in it, in
 * characters, for the brass highlight. A word no line says has no usage — the card shows no «В
 * разговоре» at all, rather than a line without the word in it.
 */
final class WordUsage
{
    /** @return array{step: int, speaker: Speaker, text: string, translation: string, offset: int, length: int}|null */
    public static function of(Lesson $lesson, string $vocabularyId, string $term): ?array
    {
        foreach ($lesson->vocabularyItem($vocabularyId)->usedIn ?? [] as $ref) {
            if (preg_match('/^A(\d+)$/', $ref, $m) === 1) {
                $partner = $lesson->exchange((int) $m[1])?->partner();
                $usage = $partner === null ? null : self::usage((int) $m[1], $partner, $term);
            } else {
                $usage = null;
                foreach ($lesson->linesOf($ref) as $use) {
                    $usage = self::usage($use['exchange']->step, $use['message'], $term);
                    if ($usage !== null) {
                        break;
                    }
                }
            }
            if ($usage !== null) {
                return $usage;
            }
        }

        foreach ($lesson->exchanges as $exchange) {
            foreach ($exchange->messages as $message) {
                $usage = self::usage($exchange->step, $message, $term);
                if ($usage !== null) {
                    return $usage;
                }
            }
        }

        return null;
    }

    /** @return array{step: int, speaker: Speaker, text: string, translation: string, offset: int, length: int}|null */
    private static function usage(int $step, Message $message, string $term): ?array
    {
        $span = Words::spanOfTerm($term, $message->textTarget);
        if ($span === null) {
            return null;
        }

        return [
            'step' => $step,
            'speaker' => $message->isLearner() ? Speaker::Learner : Speaker::Partner,
            'text' => $message->textTarget,
            'translation' => $message->textNative,
            'offset' => $span[0],
            'length' => $span[1],
        ];
    }
}
