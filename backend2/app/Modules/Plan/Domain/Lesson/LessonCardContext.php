<?php

declare(strict_types=1);

namespace App\Modules\Plan\Domain\Lesson;

/**
 * WHAT A REPAIR OF ONE CARD IS SHOWN OF ITS LESSON (P2R, наряды GEN-2b, GEN-3) — only what the card needs to fit the visit,
 * never the whole answer: the day's frames with their fillers (what a line may stand on, which values are said
 * already) and its words, and the lines around the card —
 *
 *  - a frame: every exchange whose learner line stands on it;
 *  - a whole exchange: every exchange's two lines but its own and its two neighbours' (what the visit has asked and
 *    answered already, which fillers it has said) — the neighbours themselves go to the model on their own, as
 *    NEIGHBOURS ({@see neighbours()}, P2R v1.2);
 *  - a learner line: its partner's line, and the exchanges before and after it;
 *  - a check: its exchange's two lines;
 *  - a listening question: the whole visit in the learner's language, and the other questions;
 *  - a word: every partner line (a word of the day stands in a frame, a filler or a partner line — `used_in` names it).
 *
 * What the earlier days of the plan taught goes to every repair beside this, as EARLIER_DAYS (P2R v1.2).
 *
 * Checks, readings, keys, variants, definitions and image prompts stay out: no card is repaired against them.
 *
 * It is read off the lesson AS THE SERVER READS IT ({@see LessonAssembly::said()}): a line's filler is the one found in
 * its text and a frame's `in_dialogue` marks what its lines say — never the model's own `filler` field or marks.
 */
final class LessonCardContext
{
    /**
     * @param  Lesson  $answer  the answer as the server reads it — {@see LessonAssembly::said()}
     * @return array<string, mixed>
     */
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
                    static fn (Exchange $e): array => self::exchange($e),
                    array_filter($answer->exchanges, static fn (Exchange $e): bool => abs($e->step - $card->number) > 1),
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
            LessonCard::TERM => ['partner_lines' => array_values(array_filter(array_map(
                static fn (Exchange $e): ?array => ($partner = $e->partner()) === null ? null : [
                    'ref' => 'A'.$e->step, 'text_target' => $partner->textTarget, 'text_native' => $partner->textNative,
                ],
                $answer->exchanges,
            )))],
        };
    }

    /**
     * NEIGHBOURS of a whole exchange (P2R v1.2, наряд GEN-3): the exchange before it and the exchange after it, as they lie
     * in the lesson — kind, both lines and the check's question — or null at the edge of the visit. The repaired exchange
     * answers its own line and moves no fact out of them.
     *
     * @param  Lesson  $answer  the answer as the server reads it — {@see LessonAssembly::said()}
     * @return array{before: array<string, mixed>|null, after: array<string, mixed>|null}
     */
    public static function neighbours(Lesson $answer, LessonCard $card): array
    {
        $before = $answer->exchange($card->number - 1);
        $after = $answer->exchange($card->number + 1);

        return [
            'before' => $before === null ? null : self::exchange($before, withCheck: true),
            'after' => $after === null ? null : self::exchange($after, withCheck: true),
        ];
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
