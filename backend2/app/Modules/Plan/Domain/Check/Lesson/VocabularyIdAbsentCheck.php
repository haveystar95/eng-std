<?php

declare(strict_types=1);

namespace App\Modules\Plan\Domain\Check\Lesson;

use App\Modules\Plan\Domain\Check\LessonCheck;
use App\Modules\Plan\Domain\Check\LessonContext;
use App\Modules\Plan\Domain\Lesson\Exchange;
use App\Modules\Plan\Domain\Lesson\Lesson;
use App\Modules\Plan\Domain\Lesson\Message;
use App\Modules\Plan\Domain\Service\Words;

/** A `vocabulary_id` on a message names a term that stands in that message's text (or a form of it). */
final class VocabularyIdAbsentCheck implements LessonCheck
{
    public function name(): string
    {
        return 'vocabulary_id_absent';
    }

    public function switchable(): bool
    {
        return true;
    }

    public function violations(Lesson $lesson, LessonContext $context): array
    {
        $out = [];
        foreach ($lesson->exchanges as $exchange) {
            foreach ($exchange->messages as $message) {
                foreach ($message->vocabularyIds as $id) {
                    if (! self::present($lesson, $id, $message)) {
                        $out[] = "exchange {$exchange->step}: {$id} is not in «{$message->textTarget}»";
                    }
                }
            }
        }

        return $out;
    }

    public function drop(Lesson $lesson, LessonContext $context): Lesson
    {
        return $lesson->withExchanges(array_map(
            static fn (Exchange $e): Exchange => $e->withMessages(array_map(
                static fn (Message $m): Message => $m->withVocabularyIds(array_values(array_filter(
                    $m->vocabularyIds,
                    static fn (string $id): bool => self::present($lesson, $id, $m),
                ))),
                $e->messages,
            )),
            $lesson->exchanges,
        ));
    }

    private static function present(Lesson $lesson, string $id, Message $message): bool
    {
        $item = $lesson->vocabularyItem($id);

        return $item !== null && Words::containsTerm($item->termTarget, $message->textTarget);
    }
}
