<?php

declare(strict_types=1);

namespace App\Modules\Plan\Domain\Assembly;

use App\Modules\Plan\Domain\Entity\DayCard;
use App\Modules\Plan\Domain\Service\DayPace;
use App\Modules\Plan\Domain\Service\SpokenLines;
use App\Modules\Plan\Domain\Service\UnitStates;
use App\Modules\Plan\Domain\ValueObject\CardKind;
use App\Modules\Plan\Domain\ValueObject\DayCardId;
use App\Modules\Plan\Domain\ValueObject\DayType;
use App\Modules\Plan\Domain\ValueObject\ExchangeKind;
use App\Modules\Plan\Domain\ValueObject\PlanDayId;
use App\Modules\Plan\Domain\ValueObject\PlanLevel;
use App\Modules\Plan\Domain\ValueObject\Stage;
use App\Modules\Plan\Domain\ValueObject\UnitKind;

/**
 * THE DAY, DEALT — deterministically, from stored material (`docs/plan-v2.md` §6; наряд SESSION-1a, разд. 2).
 *
 * A scene day is the five CARD stages of the registry over one lesson, plus the units that failed twice on the
 * previous content day, each at the end of its own stage; a review day is the returns of the two previous scene days
 * and `speak_answer` over their exchanges; the rehearsal is «Вспомнить» ({@see RecallStage}, наряд CONV-1). The sixth
 * stage of a day — the talk with the agent — is dealt by nobody: it has no cards. Every shuffle and
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
        private readonly RecallStage $recall = new RecallStage,
    ) {}

    /**
     * The same assembler reckoning «Фразы» against their ceiling by a plan's own price list (наряд FIX-3 §2) — the
     * only stage whose dealing depends on what a card costs.
     */
    public function pacedBy(DayPace $pace): self
    {
        return new self($this->words, $this->phrases->pacedBy($pace), $this->dialogue, $this->listen, $this->speak, $this->recall);
    }

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
            ...$this->returns($material, $returned, $nativeTopUp, $level),
        ];

        return $this->deal($dayId, $drafts, $ids, DayType::Scene);
    }

    /**
     * «Фразы» of a scene day as {@see sceneDay()} deals them, with what the ladder did to fit them under their ceiling
     * (наряд BACK-TAILS-2 §1) — what the day's build log reads when the ladder runs out of rungs. Deterministic: the
     * same scene and level are the same stage the day was dealt with.
     */
    public function phrasesDeal(SceneMaterial $scene, PlanLevel $level): PhrasesDeal
    {
        return $this->phrases->deal($scene, $level);
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
    public function reviewDay(PlanDayId $dayId, array $scenes, array $material, PlanLevel $level, array $returned, array $nativeTopUp, callable $ids): array
    {
        $seed = 'review:'.implode(':', array_map(static fn (SceneMaterial $s): string => $s->sceneId->value, $scenes));

        $drafts = [
            ...$this->speak->review($scenes, self::returnedExchanges($returned), $seed),
            ...$this->returns($material, $returned, $nativeTopUp, $level),
        ];

        return $this->deal($dayId, $drafts, $ids, DayType::Review);
    }

    /**
     * THE REHEARSAL (наряд CONV-1): «Вспомнить» — the plan's own lines read through and five or six of
     * them said aloud ({@see RecallStage}) — and then the talk with the agent, which is no card at
     * all. Its twelve `speak_answer` over every scene are gone: the day before the event is for
     * remembering and for speaking to a person, not for another round of the trainer.
     *
     * What failed on the day before still comes back at the end of its own stage — the rehearsal is
     * the nearest following day like any other (SESSION-1a, хвост).
     *
     * @param  list<SceneMaterial>  $scenes  every ready scene of the plan, in order
     * @param  array<string, SceneMaterial>  $material
     * @param  list<ReturnedUnit>  $returned
     * @param  callable(): DayCardId  $ids
     * @return list<DayCard>
     */
    public function rehearsalDay(PlanDayId $dayId, array $scenes, array $material, PlanLevel $level, array $returned, callable $ids): array
    {
        $drafts = [
            ...$this->recall->build($scenes),
            ...$this->returns($material, $returned, [], $level),
        ];

        return $this->deal($dayId, $drafts, $ids, DayType::Rehearsal);
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
    public function again(DayCard $failed, ?SceneMaterial $scene, array $dealt, PlanLevel $level): array
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
            $draft = $this->phrases->again($scene, $phrase, $failed->kind(), PhraseSeries::fillerOf($failed->kind(), $payload), $used, $level);
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
    private function returns(array $material, array $returned, array $nativeTopUp, PlanLevel $level): array
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
            $draft = match (true) {
                // A phrase that did not SOUND in the talk comes back as its own line said aloud (наряд CONV-1,
                // п. 3) — not as a recognition: nothing about it was answered wrong, it simply was not said.
                $unit->kind === UnitKind::Phrase && $unit->failedAs === CardKind::SpeakRetell => self::unsaidPhrase($scene, $unit->ref),
                $unit->kind === UnitKind::Word => ($term = $scene->term($unit->ref)) === null ? null : $this->words->returned($scene, $term, $nativeTopUp),
                $unit->kind === UnitKind::Phrase => ($term = $scene->phraseTerm($unit->ref)) === null || $term->frame() === null
                    ? null
                    : $this->phrases->returned($scene, $term, $unit->failedAs, $unit->failedFiller, $level),
                $unit->kind === UnitKind::Exchange => (($step = SpokenLines::stepOfRef($unit->ref)) === null || ($exchange = $scene->exchange($step)) === null)
                    ? null
                    : $this->speak->speakAnswer($scene, $exchange),
                default => null,
            };
            if ($draft !== null) {
                $out[] = $draft->returned($unit->sourceDayId);
            }
        }

        return $out;
    }

    /**
     * A CONSTRUCTION THE TALK DID NOT HEAR, coming back (наряд CONV-1, п. 3; наряд FIX-3 §6): the frame said with the
     * lesson's own value, said aloud — `speak_retell`, кадр 35-4 — as a unit of the PHRASE (the talk asked for the
     * construction, not for a line of the visit), with the exchange the visit first says it in for its place. A scene
     * with no such phrase term gives nothing back.
     */
    private static function unsaidPhrase(SceneMaterial $scene, string $phraseRef): ?CardDraft
    {
        $phrase = $scene->phraseTerm($phraseRef);
        if ($phrase === null) {
            return null;
        }
        $first = null;
        foreach ($scene->lesson->exchanges as $exchange) {
            if ($exchange->kind !== ExchangeKind::Rescue && $exchange->learner()?->phraseId === $phraseRef) {
                $first = $exchange;
                break;
            }
        }

        return new CardDraft(CardKind::SpeakRetell, UnitKind::Phrase, $phraseRef, SpeakCards::retellFrame($scene, $phrase, $first));
    }

    /**
     * Positions run per stage, in the order the drafts arrived; returned cards land at the end of
     * their stage, after today's.
     *
     * @param  list<CardDraft>  $drafts
     * @param  callable(): DayCardId  $ids
     * @return list<DayCard>
     */
    private function deal(PlanDayId $dayId, array $drafts, callable $ids, DayType $type): array
    {
        $byStage = [];
        foreach (Stage::ofCards() as $stage) {
            $byStage[$stage->value] = [];
        }
        // A kind reads its stage off the day as well as off itself: «Повтори свою реплику» stands in
        // «Говорю сам» on a scene day and in «Вспомнить» on the rehearsal (наряд CONV-1).
        foreach ($drafts as $draft) {
            $byStage[$draft->kind->stage($type)->value][] = $draft;
        }

        $cards = [];
        foreach (Stage::ofCards() as $stage) {
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
