<?php

declare(strict_types=1);

namespace App\Modules\Plan\Application\Service;

use App\Modules\Plan\Application\Port\NativeDistractorSource;
use App\Modules\Plan\Domain\Assembly\DayAssembler;
use App\Modules\Plan\Domain\Assembly\ReturnedUnit;
use App\Modules\Plan\Domain\Assembly\SceneMaterial;
use App\Modules\Plan\Domain\Entity\DayCard;
use App\Modules\Plan\Domain\Entity\Plan;
use App\Modules\Plan\Domain\Entity\PlanDay;
use App\Modules\Plan\Domain\Entity\PlanScene;
use App\Modules\Plan\Domain\Entity\PlanTerm;
use App\Modules\Plan\Domain\Exception\LessonNotReady;
use App\Modules\Plan\Domain\Repository\DayCardRepository;
use App\Modules\Plan\Domain\Repository\PlanTermRepository;
use App\Modules\Plan\Domain\ValueObject\DayCardId;
use App\Modules\Plan\Domain\ValueObject\DayType;
use App\Modules\Plan\Domain\ValueObject\LessonStatus;
use App\Modules\Plan\Domain\ValueObject\PlanLevel;
use App\Modules\Plan\Domain\ValueObject\PlanSceneId;
use App\Modules\Plan\Domain\ValueObject\TermKind;

/**
 * Gathers what a day is dealt from — the scene's lesson and terms, the scenes the returned units
 * belong to, the catalogue top-up — and hands it to the assembler. The one place that decides
 * which scenes a review or a rehearsal covers.
 */
final readonly class DayDealer
{
    public const CHOICE_TOP_UP = 3;

    public function __construct(
        private DayAssembler $assembler,
        private PlanTermRepository $terms,
        private DayCardRepository $cards,
        private NativeDistractorSource $distractors,
    ) {}

    /** @return list<DayCard> */
    public function deal(Plan $plan, PlanDay $day): array
    {
        $ids = static fn (): DayCardId => DayCardId::generate();

        return match ($day->type()) {
            DayType::Scene => $this->sceneDay($plan, $day, $ids),
            DayType::Review => $this->reviewDay($plan, $day, $ids),
            DayType::Rehearsal => $this->rehearsalDay($plan, $day, $ids),
        };
    }

    /**
     * @param  callable(): DayCardId  $ids
     * @return list<DayCard>
     */
    private function sceneDay(Plan $plan, PlanDay $day, callable $ids): array
    {
        $scene = $plan->sceneOf($day);
        if ($scene === null || ! $scene->isReady()) {
            throw LessonNotReady::scene(
                $scene?->id() ?? PlanSceneId::generate(),
                $scene?->lessonStatus() ?? LessonStatus::Pending,
                $scene?->failReason(),
            );
        }

        $returned = $this->returnedUnits($plan, $day, 1);
        $material = $this->material($plan, [$scene->id(), ...array_map(static fn (ReturnedUnit $u): PlanSceneId => $u->sceneId, $returned)]);
        $today = $material[$scene->id()->value];

        return $this->assembler->sceneDay(
            $day->id(), $today, $material, $plan->level(), $returned,
            $this->topUp($plan, $today, $plan->level()), $ids,
        );
    }

    /**
     * @param  callable(): DayCardId  $ids
     * @return list<DayCard>
     */
    private function reviewDay(Plan $plan, PlanDay $day, callable $ids): array
    {
        $previous = $plan->sceneDaysBefore($day->number(), 2);
        $returned = $this->returnedUnits($plan, $day, 2);
        $sceneIds = [];
        foreach ($previous as $sceneDay) {
            $sceneIds[] = $sceneDay->sceneId();
        }
        foreach ($returned as $unit) {
            $sceneIds[] = $unit->sceneId;
        }
        $material = $this->material($plan, array_values(array_filter($sceneIds)));

        $scenes = [];
        foreach ($previous as $sceneDay) {
            $id = $sceneDay->sceneId();
            if ($id !== null && isset($material[$id->value])) {
                $scenes[] = $material[$id->value];
            }
        }
        $first = $scenes[0] ?? null;

        return $this->assembler->reviewDay(
            $day->id(), $scenes, $material, $plan->level(), $returned,
            $first === null ? [] : $this->topUp($plan, $first, $plan->level()), $ids,
        );
    }

    /**
     * @param  callable(): DayCardId  $ids
     * @return list<DayCard>
     */
    private function rehearsalDay(Plan $plan, PlanDay $day, callable $ids): array
    {
        $ready = array_values(array_filter($plan->scenes(), static fn (PlanScene $s): bool => $s->isReady()));
        usort($ready, static fn (PlanScene $a, PlanScene $b): int => $a->order() <=> $b->order());
        $material = $this->material($plan, array_map(static fn (PlanScene $s): PlanSceneId => $s->id(), $ready));

        $scenes = [];
        foreach ($ready as $scene) {
            $scenes[] = $material[$scene->id()->value];
        }

        return $this->assembler->rehearsalDay($day->id(), $scenes, $ids);
    }

    /**
     * The units that failed twice on the previous `$howMany` scene days.
     *
     * @return list<ReturnedUnit>
     */
    private function returnedUnits(Plan $plan, PlanDay $day, int $howMany): array
    {
        $out = [];
        foreach ($plan->sceneDaysBefore($day->number(), $howMany) as $previous) {
            foreach ($this->cards->returningFrom($previous->id()) as $card) {
                $sceneId = $card->payload()['scene_id'] ?? null;
                if (! is_string($sceneId)) {
                    continue;
                }
                $out[] = new ReturnedUnit(PlanSceneId::fromString($sceneId), $card->unitKind(), $card->unitRef(), $previous->id());
            }
        }

        return $out;
    }

    /**
     * @param  list<PlanSceneId>  $sceneIds
     * @return array<string, SceneMaterial>
     */
    private function material(Plan $plan, array $sceneIds): array
    {
        $unique = [];
        foreach ($sceneIds as $id) {
            $unique[$id->value] = $id;
        }
        $terms = $this->terms->forScenes(array_values($unique));

        $out = [];
        foreach ($unique as $key => $id) {
            $scene = $plan->scene($id);
            $lesson = $scene->lesson();
            if ($lesson === null) {
                continue;
            }
            $out[$key] = new SceneMaterial($id, $lesson, $terms[$key] ?? []);
        }

        return $out;
    }

    /**
     * The Beginner choice card wants three wrong translations; a day of eight words has seven, so
     * the catalogue is asked only when the day itself is too small.
     *
     * @return list<string>
     */
    private function topUp(Plan $plan, SceneMaterial $scene, PlanLevel $level): array
    {
        if ($level !== PlanLevel::Beginner) {
            return [];
        }
        $words = $scene->vocabulary();
        if (count($words) - 1 >= self::CHOICE_TOP_UP) {
            return [];
        }
        $exclude = array_map(static fn (PlanTerm $t): string => $t->textNative(), $words);
        $like = $words[0] ?? null;

        return $this->distractors->translations(
            $plan->targetLang(), $plan->nativeLang(),
            $like !== null && $like->kind() === TermKind::Word ? $like->textNative() : '',
            $exclude, self::CHOICE_TOP_UP,
        );
    }
}
