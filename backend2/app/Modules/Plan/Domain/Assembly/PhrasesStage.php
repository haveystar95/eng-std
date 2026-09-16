<?php

declare(strict_types=1);

namespace App\Modules\Plan\Domain\Assembly;

use App\Modules\Plan\Domain\Entity\PlanTerm;
use App\Modules\Plan\Domain\ValueObject\CardKind;
use App\Modules\Plan\Domain\ValueObject\PlanLevel;

/**
 * «ФРАЗЫ» (наряд SESSION-1a, разд. 1–2; SPEC §4): three cards per frame of the day — meet it, recognise it, say it —
 * spaced so that no frame's three stand side by side ({@see Spacing}), and after them ONE `phrase_combine`.
 *
 * Recognition walks a seeded cycle over the frames that have a window — `phrase_slot` → `phrase_slot_listen` →
 * `phrase_choose_back` → `phrase_assemble` — so the day tries every way of telling a frame by its window; a frame
 * without a window is recognised back (`phrase_choose_back`), the only card it has material for. Production is
 * `phrase_repeat` for a beginner; an intermediate learner walks `phrase_other_slot` → `phrase_own_slot` over the frames
 * with two fillers or more — a frame with a single value has no «other» window to ask for, so it is repeated. A card
 * whose material is missing (no said filler, no voiced filler) gives its place to the card every frame can have —
 * `phrase_choose_back` for recognition, `phrase_repeat` for production — so a frame never loses one of its three,
 * but for one case: a choice with nothing to choose between is no check, so the only frame of a day without another
 * filler to offer (a lone frame without a window) is met and said, and goes without recognition; the spacing of the
 * other frames does not move.
 */
final class PhrasesStage
{
    private const RECOGNISE = [CardKind::PhraseSlot, CardKind::PhraseSlotListen, CardKind::PhraseChooseBack, CardKind::PhraseAssemble];

    private const PRODUCE = [CardKind::PhraseOtherSlot, CardKind::PhraseOwnSlot];

    private const MIN_FILLERS_TO_VARY = 2;

    public function __construct(private readonly PhraseCards $cards = new PhraseCards) {}

    /** @return list<CardDraft> */
    public function build(SceneMaterial $scene, PlanLevel $level): array
    {
        $intro = [];
        $recognise = [];
        $produce = [];
        $windowed = 0;
        $varied = 0;
        foreach ($scene->phrases() as $phrase) {
            $intro[] = $this->cards->intro($scene, $phrase);
            $recognise[] = $this->recognise($scene, $phrase, PhraseCards::hasSlot($phrase) ? $windowed++ : null);
            $produce[] = $this->produce($scene, $phrase, $level === PlanLevel::Intermediate && self::varies($phrase) ? $varied++ : null);
        }

        $drafts = Spacing::interleave($intro, $recognise, $produce);
        $combine = $this->cards->combine($scene);
        if ($combine !== null) {
            $drafts[] = $combine;
        }

        return $drafts;
    }

    /**
     * The card a frame that failed twice comes back as: `phrase_slot` (`phrase_choose_back` for a frame without a slot);
     * none when its scene has nothing to choose between.
     */
    public function returned(SceneMaterial $scene, PlanTerm $phrase): ?CardDraft
    {
        return $this->cards->slot($scene, $phrase) ?? $this->cards->chooseBack($scene, $phrase);
    }

    /** @param int|null $windowed the frame's place among the day's frames with a window; null for a frame without one */
    private function recognise(SceneMaterial $scene, PlanTerm $phrase, ?int $windowed): ?CardDraft
    {
        $draft = match ($windowed === null ? CardKind::PhraseChooseBack : Rotation::pick($scene->seed('phrases:recognize'), $windowed, self::RECOGNISE)) {
            CardKind::PhraseSlot => $this->cards->slot($scene, $phrase),
            CardKind::PhraseSlotListen => $this->cards->slotListen($scene, $phrase),
            CardKind::PhraseAssemble => $this->cards->assemble($scene, $phrase),
            default => null,
        };

        return $draft ?? $this->cards->chooseBack($scene, $phrase);
    }

    /** @param int|null $varied the frame's place among the day's frames said with another value; null — the frame is repeated */
    private function produce(SceneMaterial $scene, PlanTerm $phrase, ?int $varied): CardDraft
    {
        $draft = match ($varied === null ? CardKind::PhraseRepeat : Rotation::pick($scene->seed('phrases:produce'), $varied, self::PRODUCE)) {
            CardKind::PhraseOtherSlot => $this->cards->otherSlot($scene, $phrase),
            CardKind::PhraseOwnSlot => $this->cards->ownSlot($scene, $phrase),
            default => null,
        };

        return $draft ?? $this->cards->repeat($scene, $phrase);
    }

    /** A frame said with other values than its own: a window and at least two fillers for it. */
    private static function varies(PlanTerm $phrase): bool
    {
        return PhraseCards::hasSlot($phrase) && count($phrase->frame()?->fillers() ?? []) >= self::MIN_FILLERS_TO_VARY;
    }
}
