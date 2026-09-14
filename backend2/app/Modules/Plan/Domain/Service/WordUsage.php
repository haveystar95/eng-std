<?php

declare(strict_types=1);

namespace App\Modules\Plan\Domain\Service;

use App\Modules\Plan\Domain\Lesson\Lesson;
use App\Modules\Plan\Domain\ValueObject\Speaker;

/**
 * «В РАЗГОВОРЕ» — THE LINE OF THE DAY A WORD IS SAID IN (кадр 23-0e, DAY-UI-3).
 *
 * The same line the word's example is taken from ({@see \App\Modules\Plan\Domain\Entity\PlanTerm::fromLesson}):
 * the first message that names the word by id and really contains it, else the first that contains
 * it. With it: whose line it is (the voice it is heard in) and where the word stands in it, in
 * characters, for the brass highlight. A word the dialogue never says has no usage — the card shows
 * no «В разговоре» at all, rather than a line without the word in it.
 */
final class WordUsage
{
    /** @return array{step: int, speaker: Speaker, text: string, translation: string, offset: int, length: int}|null */
    public static function of(Lesson $lesson, string $vocabularyId, string $term): ?array
    {
        foreach ([true, false] as $byId) {
            foreach ($lesson->exchanges as $exchange) {
                foreach ($exchange->messages as $message) {
                    if ($byId && ! in_array($vocabularyId, $message->vocabularyIds, true)) {
                        continue;
                    }
                    $span = Words::spanOfTerm($term, $message->textTarget);
                    if ($span === null) {
                        continue;
                    }

                    return [
                        'step' => $exchange->step,
                        'speaker' => $message->isLearner() ? Speaker::Learner : Speaker::Partner,
                        'text' => $message->textTarget,
                        'translation' => $message->textNative,
                        'offset' => $span[0],
                        'length' => $span[1],
                    ];
                }
            }
        }

        return null;
    }
}
