<?php

declare(strict_types=1);

namespace App\Modules\Plan\Domain\Lesson;

use App\Modules\Plan\Domain\Check\LessonCodes;
use App\Modules\Plan\Domain\Check\LessonViolation;
use App\Modules\Plan\Domain\Service\FrameText;

/**
 * ONE REPAIRABLE CARD OF A LESSON, BY ITS ADDRESS (P2R, наряды GEN-2a, GEN-2b, GEN-3): a frame (`p3`, or one of its
 * fillers `p3.f2`), a whole exchange (`x3` — both messages and the check), a learner line (`B3`), an exchange's
 * check (`x3.check`), a listening question (`L2`), a word of the day (`v4`, P2R v1.2 — a word the learner learned on
 * an earlier day is replaced by another word of this lesson). Anything else the validator addresses — the lesson as a
 * whole, a partner line on its own — is not a card a repair can take.
 *
 * It reads the card out of a lesson and puts a repaired one back. A repaired frame keeps the dialogue lines that
 * stand on it true to it: each line that said the old frame becomes the new frame with the filler the server found
 * in it, after the glue it had and with its own closing mark where the new frame has none — the server's assembly,
 * not the model's. A repaired exchange may come with the frame its learner line stands on (`frame_update`, P2R
 * v1.1): the two go in together or not at all.
 */
final readonly class LessonCard
{
    public const FRAME = 'frame';

    public const EXCHANGE = 'exchange';

    public const LINE = 'line';

    public const CHECK = 'check';

    public const LISTENING = 'listening';

    public const TERM = 'term';

    /**
     * @param  self::FRAME|self::EXCHANGE|self::LINE|self::CHECK|self::LISTENING|self::TERM  $kind
     * @param  string  $frameId  the frame's id for a frame card, the word's id for a term card, '' otherwise
     * @param  int  $number  the exchange's step, or the listening question's number; 0 for a frame
     */
    private function __construct(
        public string $address,
        public string $kind,
        public string $frameId,
        public int $number,
    ) {}

    public static function at(string $address): ?self
    {
        return match (true) {
            preg_match('/^(p\d+)(?:\.f\d+)?$/', $address, $m) === 1 => new self($m[1], self::FRAME, $m[1], 0),
            preg_match('/^x(\d+)$/', $address, $m) === 1 => new self($address, self::EXCHANGE, '', (int) $m[1]),
            preg_match('/^B(\d+)$/', $address, $m) === 1 => new self($address, self::LINE, '', (int) $m[1]),
            preg_match('/^x(\d+)\.check$/', $address, $m) === 1 => new self($address, self::CHECK, '', (int) $m[1]),
            preg_match('/^L(\d+)$/', $address, $m) === 1 && (int) $m[1] > 0 => new self($address, self::LISTENING, '', (int) $m[1]),
            preg_match('/^v\d+$/', $address) === 1 => new self($address, self::TERM, $address, 0),
            default => null,
        };
    }

    /** Is this finding about this card? An exchange card holds its two messages and its check. */
    public function covers(LessonViolation $violation): bool
    {
        if ($this->kind === self::EXCHANGE) {
            return in_array($violation->address, ["x{$this->number}", "A{$this->number}", "B{$this->number}", "x{$this->number}.check"], true);
        }

        return ($other = self::at($violation->address)) !== null && $other->address === $this->address;
    }

    /**
     * Is a finding about this card one its repair is told? A frame is repaired to a different TARGET pattern, and its native
     * rendering is the plain translation of the new frame even when that coincides with another frame's native text (P2R
     * v1.3, доработка GEN-3): the native pattern's identity — `frame.known_native_repeat`, a `frame.twin` whose target pattern
     * is its own — is no finding to repair, and told it the model makes the native text differ by a device («Что с ним? —
     * ___.»). A twin that shares the TARGET pattern with another frame of `$answer` is told. Every other finding is.
     */
    public function cites(LessonViolation $violation, Lesson $answer): bool
    {
        if ($this->kind !== self::FRAME) {
            return true;
        }
        if ($violation->code === LessonCodes::FRAME_KNOWN_NATIVE_REPEAT) {
            return false;
        }
        if ($violation->code !== LessonCodes::FRAME_TWIN) {
            return true;
        }
        $frame = $answer->phrase($this->frameId);
        foreach ($answer->phrases as $other) {
            if ($frame !== null && $other->id !== $frame->id && FrameText::identity($other->frameTarget) === FrameText::identity($frame->frameTarget)) {
                return true;
            }
        }

        return false;
    }

    /**
     * The card as the answer holds it, in the lesson's own shape; null when the answer has no such card.
     *
     * @return array<string, mixed>|null
     */
    public function of(Lesson $answer): ?array
    {
        return match ($this->kind) {
            self::FRAME => $answer->phrase($this->frameId)?->toArray(),
            self::EXCHANGE => $answer->exchange($this->number)?->toArray(),
            self::LINE => $answer->exchange($this->number)?->learner()?->toArray(),
            self::CHECK => $answer->exchange($this->number)?->check->toArray(),
            self::LISTENING => ($answer->listening[$this->number - 1] ?? null)?->toArray(),
            self::TERM => $answer->vocabularyItem($this->frameId)?->toArray(),
        };
    }

    /** The answer with this card replaced; the answer as it was when the card is not there. */
    public function replace(Lesson $answer, Phrase|Exchange|Message|ExchangeCheck|ListeningQuestion|VocabularyItem $card): Lesson
    {
        return match (true) {
            $this->kind === self::FRAME && $card instanceof Phrase => $this->withFrame($answer, $this->frameId, $card),
            $this->kind === self::EXCHANGE && $card instanceof Exchange => $answer->withExchanges(array_map(
                fn (Exchange $e): Exchange => $e->step === $this->number ? $card->withStep($this->number) : $e,
                $answer->exchanges,
            )),
            $this->kind === self::LINE && $card instanceof Message => $answer->withExchanges(array_map(
                fn (Exchange $e): Exchange => $e->step !== $this->number ? $e : $e->withMessages(array_map(
                    static fn (Message $m): Message => $m->isLearner() ? $card : $m,
                    $e->messages,
                )),
                $answer->exchanges,
            )),
            $this->kind === self::CHECK && $card instanceof ExchangeCheck => $answer->withExchanges(array_map(
                fn (Exchange $e): Exchange => $e->step === $this->number ? $e->withCheck($card) : $e,
                $answer->exchanges,
            )),
            $this->kind === self::LISTENING && $card instanceof ListeningQuestion => $answer->withListening(array_map(
                fn (ListeningQuestion $q, int $i): ListeningQuestion => $i === $this->number - 1 ? $card : $q,
                $answer->listening,
                array_keys($answer->listening),
            )),
            // A word keeps its id and its place in the list, whatever id the repair wrote.
            $this->kind === self::TERM && $card instanceof VocabularyItem => $answer->withVocabulary(array_map(
                fn (VocabularyItem $v): VocabularyItem => $v->id === $this->frameId ? $card->withId($this->frameId) : $v,
                $answer->vocabulary,
            )),
            default => $answer,
        };
    }

    /**
     * A repaired exchange with the frame its learner line stands on (P2R v1.1, `frame_update`) — put in TOGETHER:
     * the exchange and the frame, or nothing. Null when they do not fit each other: the frame is no frame of the
     * lesson, or the exchange's learner line does not stand on it. Without a frame it is the exchange alone.
     */
    public function replaceExchange(Lesson $answer, Exchange $exchange, ?Phrase $frameUpdate): ?Lesson
    {
        if ($this->kind !== self::EXCHANGE || $answer->exchange($this->number) === null) {
            return null;
        }
        if ($frameUpdate !== null
            && ($answer->phrase($frameUpdate->id) === null || $exchange->learner()?->phraseId !== $frameUpdate->id)) {
            return null;
        }
        $repaired = $this->replace($answer, $exchange);

        return $frameUpdate === null ? $repaired : $this->withFrame($repaired, $frameUpdate->id, $frameUpdate);
    }

    private function withFrame(Lesson $answer, string $frameId, Phrase $frame): Lesson
    {
        $old = $answer->phrase($frameId);
        $frame = new Phrase($frameId, $frame->kind, $frame->frameTarget, $frame->frameNative, $frame->pronunciationNative, $frame->slot);
        $phrases = array_map(static fn (Phrase $p): Phrase => $p->id === $frame->id ? $frame : $p, $answer->phrases);

        $exchanges = array_map(static function (Exchange $exchange) use ($old, $frame): Exchange {
            return $exchange->withMessages(array_map(static function (Message $message) use ($old, $frame): Message {
                if ($old === null || ! $message->isLearner() || $message->phraseId !== $frame->id) {
                    return $message;
                }
                $was = FrameText::line($old, $message->textTarget);
                $filler = $was['filler']?->target;
                // A line that did not say the old frame, or a new slot with no filler found to say it with, stays as it was.
                if (! $was['matches'] || (FrameText::hasSlot($frame->frameTarget) && $filler === null)) {
                    return $message;
                }
                $core = FrameText::fill($frame->frameTarget, $filler);
                $after = mb_substr($message->textTarget, mb_strlen($was['glue']), 1);
                if ($was['glue'] !== '' && $after !== '' && $after === mb_strtolower($after)) {
                    $core = mb_strtolower(mb_substr($core, 0, 1)).mb_substr($core, 1);
                }

                return $message->withText(FrameText::withEndMarkOf($was['glue'].$core, $message->textTarget));
            }, $exchange->messages));
        }, $answer->exchanges);

        return $answer->withPhrases($phrases)->withExchanges($exchanges);
    }
}
