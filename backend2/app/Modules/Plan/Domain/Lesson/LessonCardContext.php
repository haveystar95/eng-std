<?php

declare(strict_types=1);

namespace App\Modules\Plan\Domain\Lesson;

/**
 * WHAT A REPAIR OF ONE CARD IS SHOWN OF ITS LESSON (P2R, наряд GEN-2b) — only what the card needs to fit the visit,
 * never the whole answer: the day's frames with their fillers (what a line may stand on, which values are said
 * already) and its words, and the lines around the card —
 *
 *  - a frame: every exchange whose learner line stands on it;
 *  - a whole exchange: the exchanges before and after it in full, and every other exchange's two lines (what the
 *    visit has asked and answered already, which fillers it has said);
 *  - a learner line: its partner's line, and the exchanges before and after it;
 *  - a check: its exchange's two lines;
 *  - a listening question: the whole visit in the learner's language, and the other questions.
 *
 * Checks, readings, keys, variants, definitions and image prompts stay out: no card is repaired against them.
 */
final class LessonCardContext
{
    /** @return array<string, mixed> */
    public static function of(Lesson $answer, LessonCard $card): array
    {
        $context = [
            'frames' => array_values(array_map(
                static fn (Phrase $p): array => self::frame($p),
                array_filter($answer->phrases, static fn (Phrase $p): bool => ! ($card->kind === LessonCard::FRAME && $p->id === $card->frameId)),
            )),
            'words' => array_map(static fn (VocabularyItem $v): array => [
                'id' => $v->id, 'term_target' => $v->termTarget, 'translation_native' => $v->translationNative,
            ], $answer->vocabulary),
        ];

        return $context + match ($card->kind) {
            LessonCard::FRAME => ['exchanges' => array_map(
                static fn (array $use): array => self::exchange($use['exchange']),
                $answer->linesOf($card->frameId),
            )],
            LessonCard::EXCHANGE => [
                'exchanges' => array_values(array_map(
                    static fn (Exchange $e): array => abs($e->step - $card->number) === 1 ? self::exchange($e, withCheck: true) : self::exchange($e),
                    array_filter($answer->exchanges, static fn (Exchange $e): bool => $e->step !== $card->number),
                )),
            ],
            LessonCard::LINE => ['exchanges' => array_values(array_map(
                static fn (Exchange $e): array => $e->step === $card->number ? self::exchange($e, learner: false) : self::exchange($e),
                array_filter($answer->exchanges, static fn (Exchange $e): bool => abs($e->step - $card->number) <= 1),
            ))],
            LessonCard::CHECK => ['exchanges' => array_values(array_map(
                static fn (Exchange $e): array => self::exchange($e),
                array_filter($answer->exchanges, static fn (Exchange $e): bool => $e->step === $card->number),
            ))],
            LessonCard::LISTENING => [
                'exchanges' => array_map(static fn (Exchange $e): array => self::exchange($e), $answer->exchanges),
                'listening' => array_values(array_map(
                    static fn (ListeningQuestion $q): array => ['text_native' => $q->textNative, 'options_native' => $q->optionsNative],
                    array_filter($answer->listening, static fn (ListeningQuestion $q, int $i): bool => $i !== $card->number - 1, ARRAY_FILTER_USE_BOTH),
                )),
            ],
        };
    }

    /** @return array<string, mixed> */
    private static function frame(Phrase $phrase): array
    {
        return [
            'id' => $phrase->id,
            'kind' => $phrase->kind->value,
            'frame_target' => $phrase->frameTarget,
            'frame_native' => $phrase->frameNative,
            'fillers' => $phrase->slot === null ? null : array_map(
                static fn (Filler $f): array => ['target' => $f->target, 'native' => $f->native, 'in_dialogue' => $f->inDialogue],
                $phrase->slot->fillers,
            ),
        ];
    }

    /** @return array<string, mixed> */
    private static function exchange(Exchange $exchange, bool $learner = true, bool $withCheck = false): array
    {
        $messages = [];
        foreach ($exchange->messages as $message) {
            if ($message->isLearner() && ! $learner) {
                continue;
            }
            $row = ['speaker' => $message->speaker, 'text_target' => $message->textTarget, 'text_native' => $message->textNative];
            if ($message->isLearner()) {
                $row['phrase_id'] = $message->phraseId;
                $row['filler'] = $message->filler;
            }
            $messages[] = $row;
        }
        $out = ['step' => $exchange->step, 'kind' => $exchange->kind->value, 'messages' => $messages];
        if ($withCheck) {
            $out['check'] = $exchange->check->textTarget;
        }

        return $out;
    }
}
