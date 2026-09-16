<?php

declare(strict_types=1);

namespace App\Modules\Plan\Application\Service;

use App\Modules\Plan\Application\Port\NativeDistractorSource;
use App\Modules\Plan\Domain\Assembly\DayAssembler;
use App\Modules\Plan\Domain\Assembly\ReturnedUnit;
use App\Modules\Plan\Domain\Assembly\SceneMaterial;
use App\Modules\Plan\Domain\Check\Language\LanguagePacks;
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
use App\Modules\Plan\Domain\ValueObject\PlanDayId;
use App\Modules\Plan\Domain\ValueObject\PlanLevel;
use App\Modules\Plan\Domain\ValueObject\PlanSceneId;
use App\Modules\Plan\Domain\ValueObject\TermKind;

/**
 * Gathers what a day is dealt from — the scene's served lesson and terms, the packs of the plan's two
 * languages, the scenes the returned units belong to, the catalogue top-up — and hands it to the
 * assembler. The one place that decides which scenes a review or a rehearsal covers.
 */
final readonly class DayDealer
{
    public const CHOICE_TOP_UP = 3;

    public function __construct(
        private DayAssembler $assembler,
        private PlanTermRepository $terms,
        private DayCardRepository $cards,
        private NativeDistractorSource $distractors,
        private LanguagePacks $packs,
    ) {}

    /** @return list<DayCard> */
    public function deal(Plan $plan, PlanDay $day): array
    {
        return $this->assemble($plan, $day, withNativeTopUp: true);
    }

    /**
     * The day as it WILL be dealt, dealt nowhere: the same assembler over the same material, so
     * the room can show the stages and the programme of a day whose lesson is written and whose
     * turn has not come. Nothing is written and no id is kept — only the shape.
     *
     * A day whose lesson is not ready has no shape yet, and says so with an empty list rather
     * than an exception: the room draws that as five absent stages.
     *
     * The Beginner top-up is not asked for here. It fills the wrong options of a choice card and
     * changes neither how many cards a day has nor which units they belong to — the two things
     * the room reads off this.
     *
     * @return list<DayCard>
     */
    public function outline(Plan $plan, PlanDay $day): array
    {
        try {
            return $this->assemble($plan, $day, withNativeTopUp: false);
        } catch (LessonNotReady) {
            return [];
        }
    }

    /** @return list<DayCard> */
    private function assemble(Plan $plan, PlanDay $day, bool $withNativeTopUp): array
    {
        $ids = static fn (): DayCardId => DayCardId::generate();

        return match ($day->type()) {
            DayType::Scene => $this->sceneDay($plan, $day, $ids, $withNativeTopUp),
            DayType::Review => $this->reviewDay($plan, $day, $ids, $withNativeTopUp),
            DayType::Rehearsal => $this->rehearsalDay($plan, $day, $ids),
        };
    }

    /**
     * @param  callable(): DayCardId  $ids
     * @return list<DayCard>
     */
    private function sceneDay(Plan $plan, PlanDay $day, callable $ids, bool $withNativeTopUp): array
    {
        $scene = $plan->sceneOf($day);
        if ($scene === null || ! $scene->isReady()) {
            throw LessonNotReady::scene(
                $scene?->id() ?? PlanSceneId::generate(),
                $scene?->lessonStatus() ?? LessonStatus::Pending,
                $scene?->failReason(),
            );
        }

        $returned = $this->returnedUnits($plan, $day);
        $material = $this->material($plan, [$scene->id(), ...array_map(static fn (ReturnedUnit $u): PlanSceneId => $u->sceneId, $returned)]);
        $today = $material[$scene->id()->value];

        return $this->assembler->sceneDay(
            $day->id(), $today, $material, $plan->level(), $returned,
            $withNativeTopUp ? $this->nativeTopUp($plan, $today, $plan->level()) : [], $ids,
        );
    }

    /**
     * @param  callable(): DayCardId  $ids
     * @return list<DayCard>
     */
    private function reviewDay(Plan $plan, PlanDay $day, callable $ids, bool $withNativeTopUp): array
    {
        $previous = $plan->sceneDaysBefore($day->number(), 2);
        $returned = $this->returnedUnits($plan, $day);
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
            $first === null || ! $withNativeTopUp ? [] : $this->nativeTopUp($plan, $first, $plan->level()), $ids,
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
        // The rehearsal is a day like any other for what failed yesterday: it takes it back (SESSION-1a, хвост).
        $returned = array_values(array_filter(
            $this->returnedUnits($plan, $day),
            static fn (ReturnedUnit $u): bool => isset($material[$u->sceneId->value]),
        ));

        return $this->assembler->rehearsalDay($day->id(), $scenes, $material, $plan->level(), $returned, $ids);
    }

    /**
     * THE UNITS THAT COME BACK TODAY — each ONCE, on the nearest following day of whatever type (SESSION-1a, хвост).
     *
     * A unit that failed twice comes back on the very next day: yesterday's failures, whether yesterday was a scene, a
     * review or the rehearsal. A review day also looks at the two scene days before it, for units that have come back
     * nowhere yet; a unit already dealt back on an earlier day — found by the returns that name its day as their
     * source — is not dealt again, so the scene after a review takes nothing of the scenes the review has covered. A
     * card that was itself a return never marks its unit again ({@see DayCard::answer()}).
     *
     * @return list<ReturnedUnit>
     */
    private function returnedUnits(Plan $plan, PlanDay $day): array
    {
        $sources = [];
        if ($day->number() > 1) {
            $yesterday = $plan->day($day->number() - 1);
            $sources[$yesterday->number()] = $yesterday;
        }
        if ($day->type() === DayType::Review) {
            foreach ($plan->sceneDaysBefore($day->number(), 2) as $sceneDay) {
                $sources[$sceneDay->number()] = $sceneDay;
            }
        }
        ksort($sources);

        $back = [];
        foreach ($this->cards->returnedFrom(array_values(array_map(static fn (PlanDay $d): PlanDayId => $d->id(), $sources))) as $card) {
            $back[self::returnKey($card, $card->sourceDayId())] = true;
        }

        $out = [];
        foreach ($sources as $source) {
            foreach ($this->cards->returningFrom($source->id()) as $card) {
                $sceneId = $card->payload()['scene_id'] ?? null;
                if (! is_string($sceneId) || isset($back[self::returnKey($card, $source->id())])) {
                    continue;
                }
                $out[] = new ReturnedUnit(PlanSceneId::fromString($sceneId), $card->unitKind(), $card->unitRef(), $source->id());
            }
        }

        return $out;
    }

    /** One unit of one scene, failed on one day: what «already came back» is matched by. */
    private static function returnKey(DayCard $card, ?PlanDayId $sourceDay): string
    {
        $sceneId = $card->payload()['scene_id'] ?? '';

        return ($sourceDay->value ?? '').':'.(is_string($sceneId) ? $sceneId : '').':'.$card->unitKind()->value.':'.$card->unitRef();
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

        $target = $this->packs->for($plan->targetLang()->value);
        $native = $this->packs->for($plan->nativeLang()->value);

        $out = [];
        foreach ($unique as $key => $id) {
            $scene = $plan->scene($id);
            $lesson = $scene->lesson();
            if ($lesson === null) {
                continue;
            }
            $out[$key] = new SceneMaterial($id, $lesson, $terms[$key] ?? [], $target, $native);
        }

        return $out;
    }

    /**
     * The native top-up of the Beginner `word_choose` (`term_to_native`, D-07): the card wants three
     * wrong translations; a day of eight words has seven, so the catalogue is asked only when the day
     * itself is too small. Intermediate chooses among target terms and never asks.
     *
     * @return list<string>
     */
    private function nativeTopUp(Plan $plan, SceneMaterial $scene, PlanLevel $level): array
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
