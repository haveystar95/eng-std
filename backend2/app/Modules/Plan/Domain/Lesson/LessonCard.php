<?php

declare(strict_types=1);

namespace App\Modules\Plan\Domain\Lesson;

use App\Modules\Plan\Domain\Check\LessonViolation;
use App\Modules\Plan\Domain\Service\FrameText;

/**
 * ONE REPAIRABLE CARD OF A LESSON, BY ITS ADDRESS (P2R, наряды GEN-2a, GEN-2b): a frame (`p3`, or one of its
 * fillers `p3.f2`), a whole exchange (`x3` — both messages and the check), a learner line (`B3`), an exchange's
 * check (`x3.check`), a listening question (`L2`). Anything else the validator addresses — the lesson as a
 * whole, a partner line on its own, a vocabulary item — is not a card a repair can take.
 *
 * It reads the card out of the model's answer and puts a repaired one back. A repaired frame keeps the
 * dialogue lines that stand on it true to it: each such line becomes the new frame with its own filler,
 * after the glue it had — the server's assembly, not the model's. A repaired exchange may come with the frame
 * its learner line stands on (`frame_update`, P2R v1.1): the two go in together or not at all.
 */
final readonly class LessonCard
{
    public const FRAME = 'frame';

    public const EXCHANGE = 'exchange';

    public const LINE = 'line';

    public const CHECK = 'check';

    public const LISTENING = 'listening';

    /**
     * @param  self::FRAME|self::EXCHANGE|self::LINE|self::CHECK|self::LISTENING  $kind
     * @param  string  $frameId  the frame's id for a frame card, '' otherwise
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
        };
    }

    /** The answer with this card replaced; the answer as it was when the card is not there. */
    public function replace(Lesson $answer, Phrase|Exchange|Message|ExchangeCheck|ListeningQuestion $card): Lesson
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
                $was = FrameText::line($old, $message->filler, $message->textTarget);
                if (! $was['matches']) {
                    return $message;
                }
                $core = FrameText::fill($frame->frameTarget, $message->filler);
                $after = mb_substr($message->textTarget, mb_strlen($was['glue']), 1);
                if ($was['glue'] !== '' && $after !== '' && $after === mb_strtolower($after)) {
                    $core = mb_strtolower(mb_substr($core, 0, 1)).mb_substr($core, 1);
                }

                return $message->withText($was['glue'].$core);
            }, $exchange->messages));
        }, $answer->exchanges);

        return $answer->withPhrases($phrases)->withExchanges($exchanges);
    }
}
