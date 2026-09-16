<?php

declare(strict_types=1);

namespace App\Modules\Plan\Domain\Assembly;

use App\Modules\Plan\Domain\Entity\DayCard;
use App\Modules\Plan\Domain\Service\SpokenLines;
use App\Modules\Plan\Domain\Service\UnitStates;
use App\Modules\Plan\Domain\ValueObject\DayCardId;
use App\Modules\Plan\Domain\ValueObject\PlanDayId;
use App\Modules\Plan\Domain\ValueObject\PlanLevel;
use App\Modules\Plan\Domain\ValueObject\Stage;
use App\Modules\Plan\Domain\ValueObject\UnitKind;

/**
 * THE DAY, DEALT — deterministically, from stored material (`docs/plan-v2.md` §6; наряд SESSION-1a, разд. 2).
 *
 * A scene day is the five stages of the registry over one lesson, plus the units that failed twice on the previous
 * content day, each at the end of its own stage; a review day is the returns of the two previous scene days and
 * `speak_answer` over their exchanges; the rehearsal is `speak_answer` over every scene of the plan. Every shuffle and
 * rotation inside is seeded by the card's own address, so a day dealt twice is the same day. The assembler tolerates
 * whatever the checks left in the lesson: a card whose material is missing is simply not dealt, and nothing about a
 * broken mark drops the day.
 */
final class DayAssembler
{
    public function __construct(
        private readonly WordsStage $words = new WordsStage,
        private readonly PhrasesStage $phrases = new PhrasesStage,
        private readonly DialogueStage $dialogue = new DialogueStage,
        private readonly ListenStage $listen = new ListenStage,
        private readonly SpeakStage $speak = new SpeakStage,
    ) {}

    /**
     * @param  array<string, SceneMaterial>  $material  by scene id — today's scene and the scenes the returns come from
     * @param  list<ReturnedUnit>  $returned
     * @param  list<string>  $nativeTopUp  catalogue translations for the Beginner choice card
     * @param  callable(): DayCardId  $ids
     * @return list<DayCard>
     */
    public function sceneDay(PlanDayId $dayId, SceneMaterial $scene, array $material, PlanLevel $level, array $returned, array $nativeTopUp, callable $ids): array
    {
        $drafts = [
            ...$this->words->build($scene, $nativeTopUp),
            ...$this->phrases->build($scene, $level),
            ...$this->dialogue->build($scene),
            ...$this->listen->build($scene),
            ...$this->speak->build($scene),
            ...$this->returns($material, $returned, $nativeTopUp),
        ];

        return $this->deal($dayId, $drafts, $ids);
    }

    /**
     * The exchanges of the two previous scene days said aloud, and their returns at the end of their stages — an
     * exchange already returned is not dealt twice.
     *
     * @param  list<SceneMaterial>  $scenes  the two previous scene days' scenes
     * @param  array<string, SceneMaterial>  $material
     * @param  list<ReturnedUnit>  $returned
     * @param  list<string>  $nativeTopUp
     * @param  callable(): DayCardId  $ids
     * @return list<DayCard>
     */
    public function reviewDay(PlanDayId $dayId, array $scenes, array $material, array $returned, array $nativeTopUp, callable $ids): array
    {
        $seed = 'review:'.implode(':', array_map(static fn (SceneMaterial $s): string => $s->sceneId->value, $scenes));

        $drafts = [
            ...$this->speak->review($scenes, self::returnedExchanges($returned), $seed),
            ...$this->returns($material, $returned, $nativeTopUp),
        ];

        return $this->deal($dayId, $drafts, $ids);
    }

    /**
     * The exchanges of every scene said aloud, and what failed on the day before at the end of its stage — the
     * rehearsal is the nearest following day for yesterday's units like any other day (SESSION-1a, хвост). An exchange
     * already returned is not dealt twice.
     *
     * @param  list<SceneMaterial>  $scenes  every ready scene of the plan, in order
     * @param  array<string, SceneMaterial>  $material
     * @param  list<ReturnedUnit>  $returned
     * @param  callable(): DayCardId  $ids
     * @return list<DayCard>
     */
    public function rehearsalDay(PlanDayId $dayId, array $scenes, array $material, array $returned, callable $ids): array
    {
        $drafts = [
            ...$this->speak->rehearsal($scenes, self::returnedExchanges($returned)),
            ...$this->returns($material, $returned, []),
        ];

        return $this->deal($dayId, $drafts, $ids);
    }

    /**
     * The unit keys of the exchanges among the returns — what the speaking selections of a review and a rehearsal leave
     * out, so an exchange coming back is not said twice in one day.
     *
     * @param  list<ReturnedUnit>  $returned
     * @return list<string>
     */
    private static function returnedExchanges(array $returned): array
    {
        $keys = [];
        foreach ($returned as $unit) {
            if ($unit->kind === UnitKind::Exchange) {
                $keys[] = UnitStates::key($unit->sceneId->value, UnitKind::Exchange, $unit->ref);
            }
        }

        return array_values(array_unique($keys));
    }

    /**
     * The payload of a card dealt again at the end of its stage after its first lapse (D-06; SESSION-1d): a phrase card
     * said with a filler comes back as the same kind said with another one — the next no card of its frame has taken
     * today ({@see PhrasesStage::again()}) — and any other card as it was; the options and the tiles of either are
     * shuffled again by the failed card's own `<id>:retry`.
     *
     * @param  SceneMaterial|null  $scene  the scene of the failed card; null when it is not needed (not a phrase)
     * @param  list<DayCard>  $dealt  the day's cards, the failed one and its earlier copies among them
     * @return array<string, mixed>
     */
    public function again(DayCard $failed, ?SceneMaterial $scene, array $dealt): array
    {
        $payload = $failed->payload();
        $phrase = $scene === null || $failed->unitKind() !== UnitKind::Phrase ? null : $scene->phraseTerm($failed->unitRef());
        if ($scene !== null && $phrase !== null) {
            $used = [];
            foreach ($dealt as $card) {
                $index = PhraseSeries::fillerOf($card->kind(), $card->payload());
                if ($index !== null && $card->unitKind() === UnitKind::Phrase && $card->unitRef() === $failed->unitRef()
                    && UnitStates::sceneOf($card) === $scene->sceneId->value && ! in_array($index, $used, true)) {
                    $used[] = $index;
                }
            }
            $draft = $this->phrases->again($scene, $phrase, $failed->kind(), PhraseSeries::fillerOf($failed->kind(), $payload), $used);
            $payload = $draft === null ? $payload : $draft->payload;
        }

        return Retry::payload($payload, $failed->id()->value.':retry');
    }

    /**
     * One card per returned unit — `word_choose` for a word, `speak_answer` for an exchange, and a frame as the kind it
     * failed as the last time said with another filler (SESSION-1d, {@see PhrasesStage::returned()}). The day's
     * listening never returns; a unit returned twice (two earlier days) is dealt once.
     *
     * @param  array<string, SceneMaterial>  $material
     * @param  list<ReturnedUnit>  $returned
     * @param  list<string>  $nativeTopUp
     * @return list<CardDraft>
     */
    private function returns(array $material, array $returned, array $nativeTopUp): array
    {
        $out = [];
        $seen = [];
        foreach ($returned as $unit) {
            if (! $unit->kind->returns()) {
                continue;
            }
            $key = UnitStates::key($unit->sceneId->value, $unit->kind, $unit->ref);
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $scene = $material[$unit->sceneId->value] ?? null;
            if ($scene === null) {
                continue;
            }
            $draft = match ($unit->kind) {
                UnitKind::Word => ($term = $scene->term($unit->ref)) === null ? null : $this->words->returned($scene, $term, $nativeTopUp),
                UnitKind::Phrase => ($term = $scene->phraseTerm($unit->ref)) === null || $term->frame() === null
                    ? null
                    : $this->phrases->returned($scene, $term, $unit->failedAs, $unit->failedFiller),
                UnitKind::Exchange => (($step = SpokenLines::stepOfRef($unit->ref)) === null || ($exchange = $scene->exchange($step)) === null)
                    ? null
                    : $this->speak->speakAnswer($scene, $exchange),
                UnitKind::Day => null,
            };
            if ($draft !== null) {
                $out[] = $draft->returned($unit->sourceDayId);
            }
        }

        return $out;
    }

    /**
     * Positions run per stage, in the order the drafts arrived; returned cards land at the end of
     * their stage, after today's.
     *
     * @param  list<CardDraft>  $drafts
     * @param  callable(): DayCardId  $ids
     * @return list<DayCard>
     */
    private function deal(PlanDayId $dayId, array $drafts, callable $ids): array
    {
        $byStage = [];
        foreach (Stage::ordered() as $stage) {
            $byStage[$stage->value] = [];
        }
        foreach ($drafts as $draft) {
            $byStage[$draft->kind->stage()->value][] = $draft;
        }

        $cards = [];
        foreach (Stage::ordered() as $stage) {
            $position = 0;
            foreach ($byStage[$stage->value] ?? [] as $draft) {
                $cards[] = DayCard::dealt(
                    $ids(), $dayId, $stage, ++$position, $draft->kind, $draft->payload,
                    $draft->source, $draft->sourceDayId, $draft->unitKind, $draft->unitRef,
                );
            }
        }

        return $cards;
    }
}
