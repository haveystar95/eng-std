<?php

declare(strict_types=1);

namespace App\Modules\Generation\Domain\ValueObject;

/**
 * One card of a plan day as the model wrote it — before anything has decided whether it is any
 * good.
 *
 * ## Three fields answer three different questions, and the entries where they disagree are why
 *
 * `type` — what the expression IS, lexically: word, phrase, idiom, phrasal_verb.
 * `kind` — what it DOES in this day: a line the learner says, a word that goes in a slot, or a
 *          connector that also goes in a slot. This is what the stage checklist reads.
 * `isLine` — the older, coarser form of `kind`, kept because it is written for every term the plan
 *          touches and is what `terms.is_line` has meant since PLAN-1a.
 *
 * «payment module» is `type: phrase`, `kind: word`, `isLine: false` — multi-word by grammar, a
 * substitution by function. «deal with» is `type: phrasal_verb`, `kind: chunk`. Collapsing any two
 * of the three forces a lie about one of them, which is what v0 did with a single `kind` field.
 *
 * ## `text` on a LINE is assembled, not written
 *
 * Since v0.3 the model does not write a line's `text`. It writes `frame` («I worked on ___») and
 * `filler` («the payment module»), and {@see \App\Modules\Generation\Application\Service\PlanDayComposer}
 * pastes the second into the first before anything judges the card. The class of defect that killed
 * four live answers in a row — `___` left standing in `text` — cannot be written any more, because
 * there is no field for the model to leave it in. Everything downstream still reads `text` and is
 * unchanged: the assembly happens BEFORE the validator, so the clone, key, reading and description
 * rules all judge the sentence the learner will actually see.
 */
final readonly class PlanDayItem
{
    public const KIND_LINE = 'line';
    public const KIND_WORD = 'word';
    public const KIND_CHUNK = 'chunk';

    /**
     * A NUMBER OF THE SCENE — a price, a date, a house number, heard inside a line (канон §6).
     *
     * The fourth kind, and the one nothing deals yet: numbers are written with the day and stored
     * beside it, and the trainer that plays them («звучит реплика → введи цифрами») is NUM-1. A
     * card no session can deal must not be OWED either, which is why the plan's progress reads
     * them out of the day rather than dropping them from it.
     */
    public const KIND_NUMBER = 'number';

    public const SPEAKER_LEARNER = 'learner';
    public const SPEAKER_ROLE = 'role';

    /**
     * The hole in a frame — the one string the server pastes a filler into.
     *
     * Lives on the card rather than in the validator because three layers need the same answer to
     * «is this a frame or a sentence»: the composer pastes on it, the gate counts it, and the
     * writer decides whether there is a frame to store at all.
     */
    public const SLOT = '___';

    public function __construct(
        public string $text,
        /** word | phrase | idiom | phrasal_verb — what it IS, lexically. */
        public string $type,
        /** line | word | chunk — what it DOES in this day. */
        public string $kind,
        /** true for a spoken turn in this day's conversation. `kind === 'line'`, said the old way. */
        public bool $isLine,
        public string $translation,
        public ?string $transliteration,
        public string $description,
        public string $example,
        public string $exampleTranslation,
        /**
         * The line with its slot written as `___`, or '' for a formula the learner says whole.
         * Empty on everything that is not a line.
         */
        public string $frame = '',
        /**
         * What stands in the frame's slot — the `text` of a word or connector of THIS day, copied
         * character for character. `''` on a formula, on a quoted interlocutor line, and on
         * everything that is not a line.
         */
        public string $filler = '',
        /** learner | role on a line, null on a substitution. */
        public ?string $speaker = null,
        /** One English sentence describing the picture. Never empty on a v0.2 day. */
        public string $imageApiPrompt = '',
        /** 1-based checkpoint this line closes; always null on a substitution. */
        public ?int $coversCheckpoint = null,
        /**
         * WHERE THIS CARD STANDS in the array the model wrote it in — 0-based, as written.
         *
         * The other half of an address ({@see arrayName()}), and the reason it is on the card and
         * not computed by whoever needs it: a violation names a card by `(array, index)` and P2R
         * puts a fixed card back at that same `(array, index)`. Two places deriving the same
         * position independently is how a repair call edits the card next to the broken one.
         */
        public int $index = 0,
        /**
         * WHICH SHELF OF THE SCENE this card stands on — `hear` | `say` | `ask` | `words` |
         * `chunks` | `numbers`, and `rescue` for a card the server added itself.
         *
         * The v0.4 contract's load-bearing field, and the reason {@see $kind} is no longer enough:
         * «Ты ответишь» and «Ты спросишь» are both `line`s the learner says, and they are two
         * shelves with two captions; «Тебе скажут» is a `line` too and belongs to the other TIER
         * entirely. The shelf answers all three questions and the model only ever writes the array
         * it put the card in ({@see PlanShelf}).
         *
         * Empty on a card built before shelves existed — the backfill gives every stored term one,
         * and this default is what keeps a hand-built test item constructible.
         */
        public string $shelf = '',
        /**
         * The id of the ONE skill of the scene this card serves — «почему я это учу», mechanically
         * (канон §8). Null only on a card that named none, which is
         * {@see \App\Modules\Generation\Domain\Service\PlanDayValidator::SKILL_REF_INVALID}.
         */
        public ?string $skillRef = null,
        /**
         * NUMBERS ONLY: the same number as digits, or an ISO date — «20», «2026-09-05».
         *
         * The card is heard, not read, so what it is graded against is the digits the learner types
         * and not the words the line spells them with. Null everywhere else.
         */
        public ?string $value = null,
        /**
         * WHAT ELSE COUNTS WHEN THE LINE IS SPOKEN — 1–2 shorter or simpler forms of the same reply
         * (P2 v0.7, наряд GEN-1, канон Y4). Only a `say`/`ask` line carries them; empty everywhere
         * else, and empty on a `you` line is a carded defect
         * ({@see \App\Modules\Generation\Domain\Service\PlanDayValidator::SPEAKING_KEYS_MISSING}).
         *
         * @var list<string>
         */
        public array $speakingKeys = [],
    ) {}

    /**
     * WHICH SHELF this card came out of — the first half of its address, and what the repair call
     * puts a fixed card back into.
     *
     * The name is historical: a violation says «`say[3]`», so the «array» is the shelf. It falls
     * back to the kind for a card built without a shelf (a fixture from before v0.4, a hand-made
     * test item), because an address that cannot be built is a card a repair call cannot be pointed
     * at.
     */
    public function arrayName(): string
    {
        if ($this->shelf !== '') {
            return $this->shelf;
        }

        return match ($this->kind) {
            self::KIND_LINE => PlanShelf::Say->value,
            self::KIND_WORD => PlanShelf::Words->value,
            self::KIND_NUMBER => PlanShelf::Numbers->value,
            default => PlanShelf::Chunks->value,
        };
    }

    /**
     * `speak` or `understand` — the LADDER this card climbs, derived from its shelf and never read
     * off the model's answer (канон §3; {@see PlanShelf::tier()}).
     */
    public function tier(): string
    {
        return (PlanShelf::tryFromName($this->arrayName()) ?? PlanShelf::Say)->tier();
    }

    /**
     * Is there a hole in this card's frame?
     *
     * A FORMULA answers no, and that is the distinction the stored `terms.frame` turns on: since
     * v0.3 a formula's `frame` is the whole line rather than `''`, and a cloze gap cut from it
     * would blank nothing. «No hole» and «no frame» are one state downstream, and this is where
     * they are made one.
     */
    public function hasSlot(): bool
    {
        return str_contains($this->frame, self::SLOT);
    }
}
