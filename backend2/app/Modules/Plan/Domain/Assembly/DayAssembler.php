<?php

declare(strict_types=1);

namespace App\Modules\Plan\Domain\Assembly;

use App\Modules\Plan\Domain\Entity\DayCard;
use App\Modules\Plan\Domain\ValueObject\DayCardId;
use App\Modules\Plan\Domain\ValueObject\PlanDayId;
use App\Modules\Plan\Domain\ValueObject\PlanLevel;
use App\Modules\Plan\Domain\ValueObject\Stage;
use App\Modules\Plan\Domain\ValueObject\UnitKind;

/**
 * THE DAY, DEALT — deterministically, from stored material (`docs/plan-v2.md` §6).
 *
 * A scene day is five stages over one lesson plus the units that failed twice on the previous
 * content day; a review day is the returns of the two previous scene days and every exchange of
 * their scenes, said aloud; the rehearsal is every exchange of the plan, said aloud. The assembler
 * tolerates whatever the checks left in the lesson: a card whose material is missing is simply
 * not dealt, and nothing about a broken mark drops the day.
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
     * @param  list<string>  $extraTranslations  catalogue top-up for the Beginner choice card
     * @param  callable(): DayCardId  $ids
     * @return list<DayCard>
     */
    public function sceneDay(PlanDayId $dayId, SceneMaterial $scene, array $material, PlanLevel $level, array $returned, array $extraTranslations, callable $ids): array
    {
        $drafts = [
            ...$this->words->build($scene, $level, $extraTranslations),
            ...$this->phrases->build($scene),
            ...$this->dialogue->build($scene, $level),
            ...$this->listen->build($scene, $level),
            ...$this->speak->build($scene),
            ...$this->returns($material, $level, $returned, $extraTranslations),
        ];

        return $this->deal($dayId, $drafts, $ids);
    }

    /**
     * @param  list<SceneMaterial>  $scenes  the two previous scene days' scenes
     * @param  array<string, SceneMaterial>  $material
     * @param  list<ReturnedUnit>  $returned
     * @param  list<string>  $extraTranslations
     * @param  callable(): DayCardId  $ids
     * @return list<DayCard>
     */
    public function reviewDay(PlanDayId $dayId, array $scenes, array $material, PlanLevel $level, array $returned, array $extraTranslations, callable $ids): array
    {
        $drafts = $this->returns($material, $level, $returned, $extraTranslations);
        foreach ($scenes as $scene) {
            $drafts = [...$drafts, ...$this->speak->build($scene)];
        }

        return $this->deal($dayId, $drafts, $ids);
    }

    /**
     * @param  list<SceneMaterial>  $scenes  every ready scene of the plan, in order
     * @param  callable(): DayCardId  $ids
     * @return list<DayCard>
     */
    public function rehearsalDay(PlanDayId $dayId, array $scenes, callable $ids): array
    {
        $drafts = [];
        foreach ($scenes as $scene) {
            $drafts = [...$drafts, ...$this->speak->build($scene)];
        }

        return $this->deal($dayId, $drafts, $ids);
    }

    /**
     * One card per returned unit — choose for a word, assemble for a phrase, speak for an exchange.
     *
     * @param  array<string, SceneMaterial>  $material
     * @param  list<ReturnedUnit>  $returned
     * @param  list<string>  $extraTranslations
     * @return list<CardDraft>
     */
    private function returns(array $material, PlanLevel $level, array $returned, array $extraTranslations): array
    {
        $out = [];
        $seen = [];
        foreach ($returned as $unit) {
            $key = $unit->sceneId->value.':'.$unit->kind->value.':'.$unit->ref;
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $scene = $material[$unit->sceneId->value] ?? null;
            if ($scene === null) {
                continue;
            }
            $draft = match ($unit->kind) {
                UnitKind::Word => ($term = $scene->term($unit->ref)) === null ? null : $this->words->returned($scene, $term, $level, $extraTranslations),
                UnitKind::Phrase => ($term = $scene->term($unit->ref)) === null ? null : $this->phrases->returned($scene, $term),
                UnitKind::Exchange => (($step = CardPayloads::stepOfRef($unit->ref)) === null || ($exchange = $scene->exchange($step)) === null)
                    ? null
                    : $this->speak->speak($scene, $exchange),
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
