<?php

declare(strict_types=1);

namespace App\Modules\Generation\Domain\ValueObject;

/**
 * One thing wrong with a model answer, as a CODE plus enough detail to fix it.
 *
 * A code and not a sentence, because the two readers want different things. A regeneration decides
 * on the code — this class of failure is worth paying to retry, that one is not — and a person
 * reading `fail_reason` at 8am wants the sentence. Both from one row.
 *
 * ## Since v0.3.1 a violation also has an ADDRESS, and the address is what a prompt may read
 *
 * `array` + `index` + `field` — «`phrases[3].translation`». It exists because of what the live run
 * measured: a retry told «`day.slot_outside_frame [Right now, I am a backend developer.]` — the
 * translation carries a `___`» came back with THAT SENTENCE in it, defects and all
 * (`docs/research/plan-v0.3-run.md`, второй заход). A detailed account of a wrong answer, with the
 * wrong answer quoted inside it, works as a TEMPLATE and not as a prohibition.
 *
 * So a violation now carries two prose fields with two different audiences and one hard rule
 * between them:
 *
 *   {@see $detail} — Russian, quotes whatever helps, goes to `fail_reason` and to the log, and
 *   NEVER reaches a model.
 *   {@see $reason} — English, one phrase, about THIS card only. It may name what is wrong with the
 *   field it is addressed at; it may not quote another card, because quoting one is how the last
 *   run taught the model to reproduce it.
 *
 * {@see address()} is the whole of what a prompt is allowed to see, and both readers of it — the
 * whole-day retry and the P2R repair call — build their message out of nothing else.
 *
 * ## Addressed and unaddressed violations mean different things to the pipeline
 *
 * {@see isAddressed()} is not a formality: a violation with a card behind it is one P2R can be
 * asked to fix, and a violation about the ANSWER — «twelve cards where fourteen were asked for»,
 * «checkpoint 2 is closed by nothing» — is not repairable card by card at all, and sends the day
 * down the whole-day path instead.
 */
final readonly class PlanViolation
{
    public function __construct(
        public string $code,
        /** RUSSIAN, for the person reading `fail_reason`. Never sent to a model. */
        public string $detail,
        /** Which card it is about, where that is meaningful. Never sent to a model. */
        public ?string $subject = null,
        /** `phrases` | `words` | `chunks` — null when this is about the answer as a whole. */
        public ?string $array = null,
        /** 0-based position inside that array, as the model wrote it. */
        public ?int $index = null,
        /** The field of the card the violation is about; null when the whole card is. */
        public ?string $field = null,
        /** ENGLISH, one phrase, about this card alone — the only prose a model ever reads. */
        public string $reason = '',
    ) {}

    /**
     * A violation of ONE card, addressed by where that card stands in the answer.
     *
     * The address comes off the card itself rather than being passed in beside it: an index typed
     * at the call site is an index that can be typed wrong, and a repair call that edits the wrong
     * card is worse than one that edits nothing.
     */
    public static function onCard(
        string $code,
        PlanDayItem $card,
        ?string $field,
        string $detail,
        string $reason,
    ): self {
        return new self($code, $detail, $card->text, $card->arrayName(), $card->index, $field, $reason);
    }

    /** A violation of the ANSWER — no card to hand to a repair call, so no address. */
    public static function onAnswer(string $code, string $detail, string $reason = ''): self
    {
        return new self($code, $detail, null, null, null, null, $reason);
    }

    /** Is there a card behind this — i.e. can a repair call be pointed at it? */
    public function isAddressed(): bool
    {
        return $this->array !== null && $this->index !== null;
    }

    /**
     * THE ADDRESS AND THE CODE, and nothing that came out of the model.
     *
     * This is the ONE form a prompt is built from. Everything a previous answer wrote —
     * {@see $subject}, {@see $detail} — stays behind, because the last live run proved that showing
     * it back is an instruction to write it again.
     */
    public function address(): string
    {
        $where = $this->isAddressed()
            ? $this->array . '[' . $this->index . ']' . ($this->field !== null ? '.' . $this->field : '')
            : 'day';

        return $where . ' — ' . $this->code . ($this->reason !== '' ? ': ' . $this->reason : '');
    }

    public function __toString(): string
    {
        return $this->subject === null
            ? "{$this->code}: {$this->detail}"
            : "{$this->code} [{$this->subject}]: {$this->detail}";
    }
}
