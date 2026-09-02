<?php

declare(strict_types=1);

namespace App\Modules\Generation\Domain\ValueObject;

/**
 * THE SHELVES OF A DAY-SCENE — and the one place a shelf answers the three questions asked of it.
 *
 * A day of a plan used to be three arrays with three exact counts: `phrases`, `words`, `chunks`.
 * v0.4 makes it a SCENE — one real encounter — and a scene has shelves rather than arrays
 * (`docs/plan-model.md` §2):
 *
 *   hear     «Тебе скажут»  — what the other person says. Understood, never produced.
 *   say      «Ты ответишь»  — the learner's own short replies.
 *   ask      «Ты спросишь»  — the questions that buy time and detail.
 *   words    the pieces those lines are built from.
 *   chunks   the connectors — «make an appointment».
 *   numbers  a price, a date, a house number, heard in a line of the scene.
 *   rescue   the five universal phrases the SERVER adds (§5). Never a model shelf — it is here
 *            because a rescue card is stored like any other and has to say which shelf it is on.
 *
 * ## The shelf decides the TIER, and that is why the tier is not a field the model writes
 *
 * «Ярус задаётся полкой, структурно. Модель ярус не выбирает» (канон §3). `understand` is two
 * touches and stops at stage B; `speak` walks the full A → B → C ladder. Letting the model name the
 * tier would make a mis-labelled card change which trainers a learner is dealt — and the model has
 * already put an interlocutor's line among the learner's own cards on a live run (Д-8, Д-33).
 * Derived, once, here.
 *
 * ## And it decides the SHAPE of the item
 *
 * A line and a number are ASSEMBLED — `frame` with `___` plus the `filler` that stands in it — and
 * a word or a connector IS the card and writes its own `text`. Same split v0.3 introduced for
 * `phrases`; what changed is that four shelves are now on the assembled side of it.
 */
enum PlanShelf: string
{
    case Hear = 'hear';
    case Say = 'say';
    case Ask = 'ask';
    case Words = 'words';
    case Chunks = 'chunks';
    case Numbers = 'numbers';

    /** The five phrases of the language pack, written into day 1 and into every warm-up. */
    case Rescue = 'rescue';

    /** The two tiers of the canon (§3): what the learner PRODUCES, and what they only take in. */
    public const TIER_SPEAK = 'speak';

    public const TIER_UNDERSTAND = 'understand';

    /**
     * The shelves the MODEL answers in, in the order the day contract lists them.
     *
     * `rescue` is deliberately absent: it is the server's, and a day that returned one would be
     * duplicating the kit rather than filling a shelf ({@see \App\Modules\Generation\Domain\Service\PlanDayValidator::CLONE}).
     *
     * @return list<self>
     */
    public static function model(): array
    {
        return [self::Hear, self::Say, self::Ask, self::Words, self::Chunks, self::Numbers];
    }

    public static function tryFromName(?string $shelf): ?self
    {
        return $shelf === null ? null : self::tryFrom(mb_strtolower(trim($shelf)));
    }

    /** `line` | `word` | `chunk` | `number` — what the card IS, in the words `terms.kind` uses. */
    public function kind(): string
    {
        return match ($this) {
            self::Hear, self::Say, self::Ask, self::Rescue => PlanDayItem::KIND_LINE,
            self::Words => PlanDayItem::KIND_WORD,
            self::Chunks => PlanDayItem::KIND_CHUNK,
            self::Numbers => PlanDayItem::KIND_NUMBER,
        };
    }

    /** `speak` or `understand` — the ladder this card climbs. Never a model field. */
    public function tier(): string
    {
        return match ($this) {
            self::Hear, self::Numbers => self::TIER_UNDERSTAND,
            default => self::TIER_SPEAK,
        };
    }

    /** Is the card's text ASSEMBLED from `frame` + `filler`, rather than written outright? */
    public function isAssembled(): bool
    {
        return $this->kind() === PlanDayItem::KIND_LINE || $this->kind() === PlanDayItem::KIND_NUMBER;
    }

    /** The interlocutor's shelf — the one whose cards are quoted rather than answered. */
    public function isRole(): bool
    {
        return $this === self::Hear;
    }

    /** Only a WORD is illustrated; a line, a connector and a number carry no picture (канон §7). */
    public function wantsImage(): bool
    {
        return $this === self::Words;
    }

    /** Words and connectors carry their own example sentence; the assembled shelves do not. */
    public function wantsExample(): bool
    {
        return $this === self::Words || $this === self::Chunks;
    }
}
