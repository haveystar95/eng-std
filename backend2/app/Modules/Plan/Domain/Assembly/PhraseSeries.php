<?php

declare(strict_types=1);

namespace App\Modules\Plan\Domain\Assembly;

use App\Modules\Plan\Domain\Entity\PlanTerm;
use App\Modules\Plan\Domain\ValueObject\CardKind;

/**
 * THE SERIES OF ONE FRAME (наряд SESSION-1d — фраза через разные окна): which filler each card of a frame is said with
 * and which kind each of its recognitions is — the one builder behind the day's recognitions, its production, the copy
 * of a failed phrase card and a frame that comes back on another day.
 *
 * - FILLERS: the recognitions take the frame's fillers in order — the said one first (what the dialogue says it
 *   with), the rest by index — each its own, none twice within the frame;
 * - KINDS: the recognitions walk the cycle `phrase_slot` → `phrase_choose_back` → `phrase_slot_listen` →
 *   `phrase_assemble` from a start the frame's own seed picks among the three CHOICES: the assembly never opens a frame
 *   (решение архитектора 16.09), it can only be a second or a third recognition. A frame with one recognition takes the
 *   start of its cycle; a frame without a window is recognised back (`phrase_choose_back`, said as itself);
 * - a recognition whose material is missing gives its place to `phrase_choose_back` with the same filler — the card
 *   every frame can have;
 * - PRODUCTION: `phrase_repeat` is said with a filler the learner has not said yet — round the slot after the said
 *   one, a filler the dialogue does not say first; a frame of one filler (or none) repeats the phrase itself.
 *   `phrase_other_slot` takes a filler no recognition has taken and not the said one (seeded among such); none free —
 *   any but the said one;
 * - AGAIN (the copy of a failed card, a frame that comes back): the same kind with the next filler round the slot after
 *   the failed one that no card of the frame has taken; none free — the next one other than the failed one; a frame
 *   with nothing else — the failed one again;
 * - every filler here is one a card may show ({@see CardObjects::fillers()}, SESSION-1e): a filler the seam judge said
 *   does not read and the dialogue does not say is neither recognised, nor repeated, nor asked for, nor given as an
 *   option — a frame whose other fillers are all such is recognised once, with the said one.
 *
 * Every kind is built by its own {@see PhraseCards} method of one signature — (scene, frame, filler) → card or null —
 * and {@see card()} is the only place that picks the method by kind.
 */
final readonly class PhraseSeries
{
    /** @var list<CardKind> the recognitions of a frame, round */
    public const CYCLE = [CardKind::PhraseSlot, CardKind::PhraseChooseBack, CardKind::PhraseSlotListen, CardKind::PhraseAssemble];

    /** @var list<CardKind> what the recognitions of a frame may open with — a choice, never the assembly */
    public const OPENERS = [CardKind::PhraseSlot, CardKind::PhraseChooseBack, CardKind::PhraseSlotListen];

    public function __construct(private PhraseCards $cards = new PhraseCards) {}

    /**
     * The fillers in the order the recognitions take them: the said one, then the rest by index. A frame without a
     * window has none.
     *
     * @return list<int>
     */
    public static function fillers(SceneMaterial $scene, PlanTerm $phrase): array
    {
        if (! PhraseCards::hasSlot($phrase)) {
            return [];
        }
        $indexes = array_column(CardObjects::fillers($scene, $phrase), 'index');
        $said = $scene->saidIndex($phrase);
        if (! in_array($said, $indexes, true)) {
            return $indexes;
        }

        return [$said, ...array_values(array_filter($indexes, static fn (int $i): bool => $i !== $said))];
    }

    /** The most recognitions a frame can have: one per filler, each its own — one for a frame without a window. */
    public static function most(SceneMaterial $scene, PlanTerm $phrase): int
    {
        return PhraseCards::hasSlot($phrase) ? count(self::fillers($scene, $phrase)) : 1;
    }

    /** The kind of the frame's recognition number `$i` (from 0): round the cycle from the frame's seeded opener. */
    public static function kind(SceneMaterial $scene, PlanTerm $phrase, int $i): CardKind
    {
        if (! PhraseCards::hasSlot($phrase)) {
            return CardKind::PhraseChooseBack;
        }
        $opener = Rotation::pick($scene->seed("{$phrase->ref()}:recognize"), 0, self::OPENERS);
        $start = (int) array_search($opener, self::CYCLE, true);

        return self::CYCLE[($start + max(0, $i)) % count(self::CYCLE)];
    }

    /**
     * The frame's recognition number `$i`: its kind, said with its filler — or `phrase_choose_back` with that filler
     * when the kind has no material. Null past the frame's fillers, and when not even that choice can be made.
     */
    public function recognition(SceneMaterial $scene, PlanTerm $phrase, int $i): ?CardDraft
    {
        if ($i >= self::most($scene, $phrase)) {
            return null;
        }
        $filler = self::fillers($scene, $phrase)[$i] ?? null;

        return $this->card(self::kind($scene, $phrase, $i), $scene, $phrase, $filler)
            ?? $this->cards->chooseBack($scene, $phrase, $filler);
    }

    /**
     * THE ONE PLACE A PHRASE CARD SAID WITH A FILLER IS PICKED BY ITS KIND — for the day, a copy and a return alike.
     * Null for a kind that is said with no filler of its own, and when the card has no material.
     */
    public function card(CardKind $kind, SceneMaterial $scene, PlanTerm $phrase, ?int $filler): ?CardDraft
    {
        return match ($kind) {
            CardKind::PhraseSlot => $this->cards->slot($scene, $phrase, $filler),
            CardKind::PhraseChooseBack => $this->cards->chooseBack($scene, $phrase, $filler),
            CardKind::PhraseSlotListen => $this->cards->slotListen($scene, $phrase, $filler),
            CardKind::PhraseAssemble => $this->cards->assemble($scene, $phrase, $filler),
            CardKind::PhraseRepeat => $this->cards->repeat($scene, $phrase, $filler),
            CardKind::PhraseOtherSlot => $this->cards->otherSlot($scene, $phrase, $filler),
            default => null,
        };
    }

    /**
     * The same kind said with another filler — the copy of a card failed the first time, a frame that comes back.
     * Null for a kind said with no filler of its own (`phrase_combine`, `phrase_own_slot`, a walkthrough) or when no
     * filler makes the card.
     *
     * @param  list<int>  $used  the fillers the frame's cards have already taken
     */
    public function again(CardKind $kind, SceneMaterial $scene, PlanTerm $phrase, ?int $failed, array $used): ?CardDraft
    {
        foreach (self::againFillers($scene, $phrase, $kind, $failed, $used) as $filler) {
            $draft = $this->card($kind, $scene, $phrase, $filler);
            if ($draft !== null) {
                return $draft;
            }
        }

        return null;
    }

    /**
     * The fillers a card said again tries, in order: round the slot after the failed one — the ones no card has taken
     * first, the taken ones after, the failed one last; `phrase_other_slot` never the said one. A frame without a
     * window is said as itself. None for a kind said with no filler of its own.
     *
     * @param  list<int>  $used
     * @return list<int|null>
     */
    public static function againFillers(SceneMaterial $scene, PlanTerm $phrase, CardKind $kind, ?int $failed, array $used): array
    {
        if (! in_array($kind, [...self::CYCLE, CardKind::PhraseRepeat, CardKind::PhraseOtherSlot], true)) {
            return [];
        }
        if (! PhraseCards::hasSlot($phrase)) {
            return [null];
        }
        $said = $kind === CardKind::PhraseOtherSlot ? $scene->saidIndex($phrase) : null;
        $free = [];
        $taken = [];
        $last = [];
        foreach (self::round(array_column(CardObjects::fillers($scene, $phrase), 'index'), $failed) as $index) {
            if ($index === $said) {
                continue;
            }
            if ($index === $failed) {
                $last[] = $index;
            } elseif (in_array($index, $used, true)) {
                $taken[] = $index;
            } else {
                $free[] = $index;
            }
        }

        return [...$free, ...$taken, ...$last];
    }

    /**
     * The fillers `phrase_repeat` tries, in order: round the slot after the said one — the fillers the dialogue does not
     * say first, then the other said ones. None for a frame of one filler or without a window: it repeats the phrase
     * itself.
     *
     * @return list<int>
     */
    public static function repeatFillers(SceneMaterial $scene, PlanTerm $phrase): array
    {
        if (! PhraseCards::hasSlot($phrase)) {
            return [];
        }
        $said = $scene->saidIndex($phrase);
        $inDialogue = array_column(CardObjects::fillers($scene, $phrase), 'in_dialogue', 'index');
        $unsaid = [];
        $saidToo = [];
        foreach (self::round(array_keys($inDialogue), $said) as $index) {
            if ($index === $said) {
                continue;
            }
            if ($inDialogue[$index]) {
                $saidToo[] = $index;
            } else {
                $unsaid[] = $index;
            }
        }

        return [...$unsaid, ...$saidToo];
    }

    /**
     * The filler `phrase_other_slot` asks for: one no recognition of the frame has taken and not the said one, seeded
     * among such; none free — any but the said one. Null for a frame with no other filler than the said one.
     *
     * @param  list<int>  $taken  the fillers the frame's recognitions are said with
     */
    public static function otherFiller(SceneMaterial $scene, PlanTerm $phrase, array $taken): ?int
    {
        $said = $scene->saidIndex($phrase);
        $rest = array_values(array_filter(self::fillers($scene, $phrase), static fn (int $i): bool => $i !== $said));
        if ($rest === []) {
            return null;
        }
        $free = array_values(array_filter($rest, static fn (int $i): bool => ! in_array($i, $taken, true)));

        return Rotation::pick($scene->seed("{$phrase->ref()}:other"), 0, $free === [] ? $rest : $free);
    }

    /**
     * Which filler a dealt phrase card is said with, read off its own payload: the intro's said one, the prompt's, the
     * window's, the assembly's answer, the combine's exchange; `phrase_slot` names none, so its right option is found
     * among the frame's fillers. Null for a card said with no filler of its own.
     *
     * @param  array<string, mixed>  $payload
     */
    public static function fillerOf(CardKind $kind, array $payload): ?int
    {
        $index = match ($kind) {
            CardKind::PhraseIntro => self::at($payload, 'said', 'filler_index'),
            CardKind::PhraseChooseBack => self::at($payload, 'prompt', 'filler_index'),
            CardKind::PhraseSlotListen, CardKind::PhraseRepeat, CardKind::PhraseOtherSlot => self::at($payload, 'filler_index'),
            CardKind::PhraseAssemble => self::at($payload, 'expected', 'filler_index'),
            CardKind::PhraseCombine => self::at($payload, 'correct_filler'),
            CardKind::PhraseSlot => self::slotFiller($payload),
            default => null,
        };

        return is_int($index) ? $index : null;
    }

    /**
     * The indexes round the slot after `$from`, `$from` itself last; from the first when `$from` is none of them.
     *
     * @param  list<int>  $indexes
     * @return list<int>
     */
    private static function round(array $indexes, ?int $from): array
    {
        $at = $from === null ? false : array_search($from, $indexes, true);
        if ($at === false) {
            return $indexes;
        }

        return [...array_slice($indexes, $at + 1), ...array_slice($indexes, 0, $at + 1)];
    }

    /**
     * The filler whose text is `phrase_slot`'s right option, among the fillers of the frame the card shows.
     *
     * @param  array<string, mixed>  $payload
     */
    private static function slotFiller(array $payload): ?int
    {
        $correct = $payload['correct'] ?? null;
        $options = $payload['options'] ?? null;
        $fillers = self::at($payload, 'frame', 'slot', 'fillers');
        if (! is_string($correct) || ! is_array($options) || ! is_array($fillers)) {
            return null;
        }
        $text = null;
        foreach ($options as $option) {
            if (is_array($option) && ($option['id'] ?? null) === $correct && is_string($option['text'] ?? null)) {
                $text = mb_strtolower(trim($option['text']));
            }
        }
        foreach ($fillers as $filler) {
            if ($text !== null && is_array($filler) && is_string($filler['target'] ?? null) && is_int($filler['index'] ?? null)
                && mb_strtolower(trim($filler['target'])) === $text) {
                return $filler['index'];
            }
        }

        return null;
    }

    /** @param array<array-key, mixed> $payload */
    private static function at(array $payload, string ...$keys): mixed
    {
        $value = $payload;
        foreach ($keys as $key) {
            if (! is_array($value) || ! array_key_exists($key, $value)) {
                return null;
            }
            $value = $value[$key];
        }

        return $value;
    }
}
