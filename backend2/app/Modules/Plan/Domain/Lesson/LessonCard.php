<?php

declare(strict_types=1);

namespace App\Modules\Plan\Domain\Lesson;

use App\Modules\Plan\Domain\Check\LessonViolation;
use App\Modules\Plan\Domain\Service\FrameText;

/**
 * ONE REPAIRABLE CARD OF A DAY, BY ITS ADDRESS (`lesson_card_repair.v1.5`, наряд GEN-4) — of the SKELETON a frame (`p3`, or
 * one of its fillers `p3.f2`), a partner line (`a4`) or a word (`v4`); of the DIALOGUE a whole exchange (`x3`, or one of its
 * two messages `A3` / `B3`), its check (`x3.check`) or a listening question (`L2`). What a check addresses to a stage as a
 * whole (`skeleton`, `dialogue`) is no card a repair can take.
 *
 * It reads the card out of its stage and puts a repaired one back. The card keeps its place and its job — the server holds
 * what the repair may not change, whatever the model wrote: a frame its id, kind and `must_say`; a partner line its id, item,
 * kind and `pairs_with`; a word its id; an exchange its step, kind, initiator, partner line and item. Once the dialogue exists,
 * a repaired partner line is said anew in the exchange that carries it ({@see Dialogue::withPartnerLine()}), and a repaired
 * frame keeps the learner lines that stand on it true to it: each becomes the new frame with the filler it said, after the
 * glue it had.
 */
final readonly class LessonCard
{
    public const FRAME = 'frame';

    public const TERM = 'term';

    public const PARTNER_LINE = 'partner_line';

    public const EXCHANGE = 'exchange';

    public const CHECK = 'check';

    public const LISTENING = 'listening';

    /** The kinds of each stage, in the order a stage's cards are repaired: what the others are built on goes first. */
    public const SKELETON_KINDS = [self::FRAME, self::PARTNER_LINE, self::TERM];

    public const DIALOGUE_KINDS = [self::EXCHANGE, self::CHECK, self::LISTENING];

    /**
     * @param  self::FRAME|self::TERM|self::PARTNER_LINE|self::EXCHANGE|self::CHECK|self::LISTENING  $kind
     * @param  string  $id  the frame's, the line's or the word's id; '' for a card of the dialogue
     * @param  int  $number  the exchange's step, or the listening question's number; 0 for a card of the skeleton
     */
    private function __construct(
        public string $address,
        public string $kind,
        public string $id,
        public int $number,
    ) {}

    public static function at(string $address): ?self
    {
        return match (true) {
            preg_match('/^(p\d+)(?:\.f\d+)?$/', $address, $m) === 1 => new self($m[1], self::FRAME, $m[1], 0),
            preg_match('/^a\d+$/', $address) === 1 => new self($address, self::PARTNER_LINE, $address, 0),
            preg_match('/^v\d+$/', $address) === 1 => new self($address, self::TERM, $address, 0),
            preg_match('/^(?:x|A|B)(\d+)$/', $address, $m) === 1 => new self('x'.$m[1], self::EXCHANGE, '', (int) $m[1]),
            preg_match('/^x(\d+)\.check$/', $address, $m) === 1 => new self($address, self::CHECK, '', (int) $m[1]),
            preg_match('/^L(\d+)$/', $address, $m) === 1 && (int) $m[1] > 0 => new self($address, self::LISTENING, '', (int) $m[1]),
            default => null,
        };
    }

    public function ofSkeleton(): bool
    {
        return in_array($this->kind, self::SKELETON_KINDS, true);
    }

    /** Is this finding about this card? A frame holds its fillers; an exchange holds its two messages (not its check). */
    public function covers(LessonViolation $violation): bool
    {
        return ($other = self::at($violation->address)) !== null && $other->address === $this->address;
    }

    /**
     * The card as its stage holds it, in that stage's own shape; null when there is no such card.
     *
     * @return array<string, mixed>|null
     */
    public function of(Skeleton $skeleton, ?Dialogue $dialogue): ?array
    {
        return match ($this->kind) {
            self::FRAME => $skeleton->frame($this->id)?->toArray(),
            self::PARTNER_LINE => $skeleton->partnerLine($this->id)?->toArray(),
            self::TERM => $skeleton->vocabularyItem($this->id)?->toArray(),
            self::EXCHANGE => $dialogue?->exchange($this->number)?->toArray(),
            self::CHECK => $dialogue?->exchange($this->number)?->exchange->check->toArray(),
            self::LISTENING => ($dialogue?->listening[$this->number - 1] ?? null)?->toArray(),
        };
    }

    /**
     * The stages with this card replaced — the skeleton and the dialogue as they come out; each as it was when the card is
     * not there or the repaired card is not of its kind.
     *
     * @return array{0: Skeleton, 1: Dialogue|null}
     */
    public function replace(Skeleton $skeleton, ?Dialogue $dialogue, SkeletonFrame|VocabularyItem|PartnerLine|DialogueExchange|ExchangeCheck|ListeningQuestion $card): array
    {
        return match (true) {
            $this->kind === self::FRAME && $card instanceof SkeletonFrame => $this->withFrame($skeleton, $dialogue, $card),
            $this->kind === self::PARTNER_LINE && $card instanceof PartnerLine => $this->withPartnerLine($skeleton, $dialogue, $card),
            $this->kind === self::TERM && $card instanceof VocabularyItem => [$skeleton->withVocabulary(array_map(
                fn (VocabularyItem $v): VocabularyItem => $v->id === $this->id ? $card->withId($this->id) : $v,
                $skeleton->vocabulary,
            )), $dialogue],
            $this->kind === self::EXCHANGE && $card instanceof DialogueExchange && $dialogue !== null => [$skeleton, $dialogue->withExchanges(array_map(
                fn (DialogueExchange $e): DialogueExchange => $e->step() !== $this->number ? $e : new DialogueExchange(
                    new Exchange($this->number, $e->exchange->kind, $e->exchange->initiator, $card->exchange->messages, $card->exchange->check),
                    $e->mustUnderstand,
                    $e->partnerLine,
                ),
                $dialogue->exchanges,
            ))],
            $this->kind === self::CHECK && $card instanceof ExchangeCheck && $dialogue !== null => [$skeleton, $dialogue->withExchanges(array_map(
                fn (DialogueExchange $e): DialogueExchange => $e->step() === $this->number ? $e->withExchange($e->exchange->withCheck($card)) : $e,
                $dialogue->exchanges,
            ))],
            $this->kind === self::LISTENING && $card instanceof ListeningQuestion && $dialogue !== null => [$skeleton, $dialogue->withListening(array_map(
                fn (ListeningQuestion $q, int $i): ListeningQuestion => $i === $this->number - 1 ? $card : $q,
                $dialogue->listening,
                array_keys($dialogue->listening),
            ))],
            default => [$skeleton, $dialogue],
        };
    }

    /** @return array{0: Skeleton, 1: Dialogue|null} */
    private function withPartnerLine(Skeleton $skeleton, ?Dialogue $dialogue, PartnerLine $card): array
    {
        $old = $skeleton->partnerLine($this->id);
        if ($old === null) {
            return [$skeleton, $dialogue];
        }
        $line = $old->withTexts($card->textTarget, $card->textNative);
        $skeleton = $skeleton->withPartnerLines(array_map(static fn (PartnerLine $l): PartnerLine => $l->id === $line->id ? $line : $l, $skeleton->partnerLines));

        return [$skeleton, $dialogue?->withPartnerLine($line)];
    }

    /** @return array{0: Skeleton, 1: Dialogue|null} */
    private function withFrame(Skeleton $skeleton, ?Dialogue $dialogue, SkeletonFrame $card): array
    {
        $old = $skeleton->frame($this->id);
        if ($old === null) {
            return [$skeleton, $dialogue];
        }
        $phrase = new Phrase($this->id, $old->phrase->kind, $card->phrase->frameTarget, $card->phrase->frameNative, $card->phrase->pronunciationNative, $card->phrase->slot);
        $frame = new SkeletonFrame($phrase, $old->mustSay);
        $skeleton = $skeleton->withFrames(array_map(static fn (SkeletonFrame $f): SkeletonFrame => $f->id() === $frame->id() ? $frame : $f, $skeleton->frames));
        if ($dialogue === null) {
            return [$skeleton, null];
        }

        return [$skeleton, $dialogue->withExchanges(array_map(static function (DialogueExchange $e) use ($old, $phrase): DialogueExchange {
            return $e->withExchange($e->exchange->withMessages(array_map(static function (Message $message) use ($old, $phrase): Message {
                if (! $message->isLearner() || $message->phraseId !== $phrase->id) {
                    return $message;
                }
                $was = FrameText::line($old->phrase, $message->textTarget);
                $filler = $was['filler'];
                $now = $filler === null ? null : $phrase->filler($filler->target);
                // A line that did not say the old frame, or a filler the new frame has not kept, stays as it was.
                if (! $was['matches'] || (FrameText::hasSlot($phrase->frameTarget) && $now === null)) {
                    return $message;
                }
                // A frame written without its closing mark borrows the line's: the line keeps the mark it had.
                $core = FrameText::withEndMarkOf(FrameText::fill($phrase->frameTarget, $now?->target), $message->textTarget);
                if ($was['glue'] !== '') {
                    $core = mb_strtolower(mb_substr($core, 0, 1)).mb_substr($core, 1);
                }
                $glueNative = FrameText::leadingGlue($message->textNative);
                $native = FrameText::withEndMarkOf(FrameText::fill($phrase->frameNative, $now?->native), $message->textNative);
                if ($glueNative !== '') {
                    $native = mb_strtolower(mb_substr($native, 0, 1)).mb_substr($native, 1);
                }

                return $message->withTexts($was['glue'].$core, $glueNative.$native);
            }, $e->exchange->messages)));
        }, $dialogue->exchanges))];
    }
}
