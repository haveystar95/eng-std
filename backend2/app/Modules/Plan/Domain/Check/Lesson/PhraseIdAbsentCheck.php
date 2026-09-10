<?php

declare(strict_types=1);

namespace App\Modules\Plan\Domain\Check\Lesson;

use App\Modules\Plan\Domain\Check\LessonCheck;
use App\Modules\Plan\Domain\Check\LessonContext;
use App\Modules\Plan\Domain\Lesson\Exchange;
use App\Modules\Plan\Domain\Lesson\Lesson;
use App\Modules\Plan\Domain\Lesson\Message;
use App\Modules\Plan\Domain\Service\PhraseInMessage;

/** A `phrase_id` on a message names a phrase found in it by the glue-and-pronoun rule. */
final class PhraseIdAbsentCheck implements LessonCheck
{
    public function name(): string
    {
        return 'phrase_id_absent';
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
                foreach ($message->phraseIds as $id) {
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
                static fn (Message $m): Message => $m->withPhraseIds(array_values(array_filter(
                    $m->phraseIds,
                    static fn (string $id): bool => self::present($lesson, $id, $m),
                ))),
                $e->messages,
            )),
            $lesson->exchanges,
        ));
    }

    private static function present(Lesson $lesson, string $id, Message $message): bool
    {
        $phrase = $lesson->phrase($id);

        return $phrase !== null && $message->isLearner() && PhraseInMessage::matches($phrase->textTarget, $message->textTarget);
    }
}
