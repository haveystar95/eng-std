<?php

declare(strict_types=1);

namespace App\Modules\Plan\Application\Service;

use App\Modules\Plan\Application\Port\DayBuildLog;
use App\Modules\Plan\Application\Port\NativeDistractorSource;
use App\Modules\Plan\Domain\Assembly\DayAssembler;
use App\Modules\Plan\Domain\Assembly\PhraseSeries;
use App\Modules\Plan\Domain\Assembly\ReturnedUnit;
use App\Modules\Plan\Domain\Assembly\SceneMaterial;
use App\Modules\Plan\Domain\Assembly\SpeakCards;
use App\Modules\Plan\Domain\Check\Language\LanguagePacks;
use App\Modules\Plan\Domain\Entity\DayCard;
use App\Modules\Plan\Domain\Entity\Plan;
use App\Modules\Plan\Domain\Entity\PlanDay;
use App\Modules\Plan\Domain\Entity\PlanScene;
use App\Modules\Plan\Domain\Entity\PlanTerm;
use App\Modules\Plan\Domain\Exception\LessonNotReady;
use App\Modules\Plan\Domain\Exception\SceneNotFound;
use App\Modules\Plan\Domain\Repository\ConversationRepository;
use App\Modules\Plan\Domain\Repository\StagePassageRepository;
use App\Modules\Plan\Domain\Repository\DayCardRepository;
use App\Modules\Plan\Domain\Service\ConversationOutcomes;
use App\Modules\Plan\Domain\Repository\PlanTermRepository;
use App\Modules\Plan\Domain\Service\UnitStates;
use App\Modules\Plan\Domain\ValueObject\CardKind;
use App\Modules\Plan\Domain\ValueObject\DayCardId;
use App\Modules\Plan\Domain\ValueObject\DayType;
use App\Modules\Plan\Domain\ValueObject\LessonStatus;
use App\Modules\Plan\Domain\ValueObject\PlanDayId;
use App\Modules\Plan\Domain\ValueObject\PlanSceneId;
use App\Modules\Plan\Domain\ValueObject\TermKind;
use App\Modules\Plan\Domain\ValueObject\Stage;
use App\Modules\Plan\Domain\ValueObject\UnitKind;

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
        private ConversationRepository $conversations,
        private StagePassageRepository $passages,
        private ConversationMaterial $material,
        private DayBuildLog $log,
    ) {}

    /**
     * The day, dealt for good — and what the dealing had to give up, written to the day's build log (наряд BACK-TAILS-2
     * §1): «Фразы» over their ceiling with every rung of the ladder spent is the stop signal. The day is dealt anyway.
     *
     * @return list<DayCard>
     */
    public function deal(Plan $plan, PlanDay $day): array
    {
        $cards = $this->assemble($plan, $day, withNativeTopUp: true);
        $this->signalPhrases($plan, $day);

        return $cards;
    }

    /** A scene day whose «Фразы» the ladder could not fit under their ceiling says so in the build log. */
    private function signalPhrases(Plan $plan, PlanDay $day): void
    {
        $scene = $day->type() === DayType::Scene ? $plan->sceneOf($day) : null;
        $material = $scene === null ? null : ($this->material($plan, [$scene->id()])[$scene->id()->value] ?? null);
        if ($scene === null || $material === null) {
            return;
        }
        $deal = $this->assembler->phrasesDeal($material, $plan->level());
        if ($deal->overCeiling()) {
            $this->log->phrasesOverCeiling($plan->id(), $day->id(), $day->number(), $scene->id(), $deal);
        }
    }

    /**
     * The day as it WILL be dealt, dealt nowhere: the same assembler over the same material, so
     * the room can show the stages and the programme of a day whose lesson is written and whose
     * turn has not come. Nothing is written and no id is kept — only the shape.
     *
     * A day whose lesson is not ready has no shape yet, and says so with an empty list rather
     * than an exception: the room draws that as five absent stages.
     *
     * The native top-up is not asked for here. It fills the wrong options of a choice card and,
     * on a day of four words or more, changes neither how many cards a day has nor which units
     * they belong to — the two things the room reads off this (a smaller day may outline a word
     * without the check the catalogue would give it).
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

    /**
     * THE ECHO CARD A NEW DEAL GIVES AN EXCHANGE — `SpeakCards::echoLine()` over this dealer's own material of the scene:
     * the payload of «Повтори через паузу» as it is dealt today, the learner's own line and all (наряд BACK-TAILS-2,
     * дополнение по отчёту клиента 1c: the echo cards dealt before CONV-2 are brought to this form). Null when the plan
     * has no such scene, the scene no lesson, the lesson no such exchange, or the exchange no line of the learner's.
     *
     * @return array<string, mixed>|null
     */
    public function echoOf(Plan $plan, PlanSceneId $sceneId, int $step): ?array
    {
        try {
            $plan->scene($sceneId);
        } catch (SceneNotFound) {
            return null;
        }
        $material = $this->material($plan, [$sceneId])[$sceneId->value] ?? null;
        $exchange = $material?->lesson->exchange($step);

        return $material === null || $exchange === null ? null : SpeakCards::echoLine($material, $exchange);
    }

    /**
     * The payload of the copy a card's first lapse deals at the end of its stage (D-06; SESSION-1d): a phrase card is
     * said again with another filler of its frame — the scene it belongs to is read for that, and only for that — any
     * other card is the same card with its options and tiles shuffled again.
     *
     * @param  list<DayCard>  $dealt  the day's cards as they stand
     * @return array<string, mixed>
     */
    public function again(Plan $plan, DayCard $failed, array $dealt): array
    {
        $sceneId = $failed->payload()['scene_id'] ?? null;
        $scene = null;
        $known = array_filter($plan->scenes(), static fn (PlanScene $s): bool => $s->id()->value === $sceneId);
        if ($failed->unitKind() === UnitKind::Phrase && is_string($sceneId) && $known !== []) {
            $scene = $this->material($plan, [PlanSceneId::fromString($sceneId)])[$sceneId] ?? null;
        }

        return $this->assembler->again($failed, $scene, $dealt, $plan->level());
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
            $withNativeTopUp ? $this->nativeTopUp($plan, $today) : [], $ids,
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
            $first === null || ! $withNativeTopUp ? [] : $this->nativeTopUp($plan, $first), $ids,
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
     * A unit two of whose cards failed twice — a frame failed as a recognition and as said aloud (SESSION-1d) — comes
     * back once, as the card it failed as the LAST time: the one answered latest (on one moment, the later place).
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

        /** @var array<string, array{card: DayCard, source: PlanDay}> $last the latest failure of every unit, in the order units first failed */
        $last = [];
        foreach ($sources as $source) {
            foreach ($this->cards->returningFrom($source->id()) as $card) {
                $sceneId = $card->payload()['scene_id'] ?? null;
                if (! is_string($sceneId) || isset($back[self::returnKey($card, $source->id())])) {
                    continue;
                }
                $unit = UnitStates::key($sceneId, $card->unitKind(), $card->unitRef());
                $held = $last[$unit]['card'] ?? null;
                if ($held === null || self::failedLater($card, $held)) {
                    $last[$unit] = ['card' => $card, 'source' => $source];
                }
            }
        }

        $out = [];
        foreach ($last as ['card' => $card, 'source' => $source]) {
            $out[] = new ReturnedUnit(
                PlanSceneId::fromString((string) $card->payload()['scene_id']), $card->unitKind(), $card->unitRef(), $source->id(),
                $card->kind(), PhraseSeries::fillerOf($card->kind(), $card->payload()),
            );
        }

        return [...$out, ...$this->unsaidInTalks($plan, $sources)];
    }

    /**
     * WHAT THE TALK DID NOT HEAR (наряд CONV-1, п. 3): the phrases a day's talk was FOR — its targets (наряд CONV-2,
     * п. 10) — that did not sound come back on the next day, once, as the learner's own line said aloud.
     *
     * The talk is the one that walked the day's sixth stage (наряд CONV-2, п. 2): a replay after it is an exercise on
     * top of a walked day, and what it did or did not hear is not the day's result.
     *
     * They are added AFTER the units that failed on cards, so a phrase that did both comes back as what it failed as —
     * a wrong answer is a stronger fact about a phrase than a talk that took another road. The rehearsal's talk gives
     * nothing back: there is no tomorrow before the event, and its summary says so in words instead (кадр 37-12).
     *
     * @param  array<int, PlanDay>  $sources
     * @return list<ReturnedUnit>
     */
    private function unsaidInTalks(Plan $plan, array $sources): array
    {
        $out = [];
        foreach ($sources as $source) {
            $walkedId = $this->passages->of($source->id(), Stage::Conversation)?->conversationId;
            $talk = $walkedId === null ? null : $this->conversations->findById($walkedId);
            if ($talk === null || ! $talk->isEnded() || ! $talk->type()->returnsTomorrow()) {
                continue;
            }
            $outcome = ConversationOutcomes::of($talk, $this->material->for($plan, $source)->targets);
            foreach ($outcome->notSaid as $id) {
                [$sceneId, $ref] = array_pad(explode(':', $id, 2), 2, '');
                if ($sceneId !== '' && $ref !== '') {
                    $out[] = new ReturnedUnit(
                        PlanSceneId::fromString($sceneId), UnitKind::Phrase, $ref, $source->id(), CardKind::SpeakRetell,
                    );
                }
            }
        }

        return $out;
    }

    /** Was `$card` answered after `$than` — later in time, or at one moment later in its day's order? */
    private static function failedLater(DayCard $card, DayCard $than): bool
    {
        return [$card->answeredAt()?->getTimestamp() ?? 0, $card->stage() === $than->stage() ? $card->position() : 0]
            > [$than->answeredAt()?->getTimestamp() ?? 0, $card->stage() === $than->stage() ? $than->position() : 0];
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
            // The scene's name is the plan's, written into the day from here and from nowhere else (наряд BACK-TAILS-2 §6).
            $out[$key] = new SceneMaterial(
                $id, $lesson, $terms[$key] ?? [], $target, $native, $scene->unreadableFillers(),
                $scene->titleNative(), $scene->titleTarget(),
            );
        }

        return $out;
    }

    /**
     * The native top-up of the choices made among translations — `word_choose` asked `term_to_native` and
     * `word_listen` (D-07; SESSION-1e: at any level): the card wants three wrong translations; a day of
     * eight words has seven, so the catalogue is asked only when the day itself is too small.
     *
     * @return list<string>
     */
    private function nativeTopUp(Plan $plan, SceneMaterial $scene): array
    {
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
