<?php

declare(strict_types=1);

namespace App\Modules\Learning\Application\Query;

use App\Modules\Learning\Application\Dto\PlanDayProgressView;
use App\Modules\Learning\Application\Dto\PlanDayTermView;
use App\Modules\Learning\Application\Service\LineAudioIndex;
use App\Modules\Learning\Application\Service\PlanProgress;
use App\Modules\Learning\Domain\Repository\PlanDayRepository;
use App\Modules\Learning\Domain\Repository\PlanRepository;
use App\Modules\Learning\Domain\Repository\PlanTermStageRepository;
use App\Modules\Learning\Domain\ValueObject\ExerciseMode;
use App\Modules\Learning\Domain\ValueObject\PlanId;
use App\Modules\Learning\Domain\ValueObject\PlanStage;
use App\Modules\Learning\Domain\ValueObject\PlanTermStanding;
use App\Modules\Learning\Domain\ValueObject\PlanTurnLevel;

/**
 * THE DAY SCREEN'S REGISTER — «фразы дня» and «слова в этих фразах», each with its stage
 * (макет «Фаза 4», кадр 1c · 02).
 *
 * Two populations, and the screen draws them differently because they mean different things:
 *
 *  * THE DAY'S OWN terms — what this sitting introduces. Phrases first, in serif with a terracotta
 *    rule («то, что ты скажешь»), then the words those phrases are built from.
 *  * CARRIED terms — words met on an EARLIER day that have not lived out their three stages. They
 *    are what makes «B · со дня 1» true on screen, and they are the visible half of the rule that
 *    the plan's days are connected rather than independent lessons.
 *
 * A carried word that is FINISHED is left out entirely. It has nothing left to say on today's
 * screen, and listing it would grow the register by every word of every past day.
 *
 * The whole thing runs on {@see PlanProgress} — the same computation the plan session and the plan
 * screen use. That is deliberate and it is the reason this is a query handler rather than a couple
 * of joins: a register that named a stage the session then disagreed with is exactly the class of
 * bug PLAN-1b spent a наряд making impossible.
 */
final readonly class GetPlanDayTermsHandler
{
    public function __construct(
        private PlanRepository $plans,
        private PlanDayRepository $days,
        private PlanProgress $progress,
        /** Готовая озвучка реплик — экран дня играет ТОТ ЖЕ файл, что и разговор (наряд TTS-1). */
        private LineAudioIndex $lineAudio,
        /** Что пары доказали голосом — отметка «сказал сам» у строки (наряд DAY-FIX-2, Ч.4.3). */
        private PlanTermStageRepository $termStages,
    ) {}

    /** @return list<PlanDayTermView>|null  null when the plan is not this learner's, or has no such day */
    public function __invoke(GetPlanDayTerms $query): ?array
    {
        $plan = $this->plans->findById(PlanId::fromString($query->planId));
        if ($plan === null || ! $plan->userId()->equals($query->actorId)) {
            return null;
        }

        $days = $this->days->listForPlan($plan->id());
        $progress = $this->progress->forPlan($plan, $days);
        $said = [];
        foreach ($this->termStages->forPlan($plan->id()) as $termId => $stage) {
            if ($stage->saidInRun) {
                $said[$termId] = true;
            }
        }

        $out = [];
        // The day's own words first, in the order the collection holds them, then everything still
        // in flight from the days before it — oldest day first, so the register reads as a history.
        foreach ($this->termsOf($progress->days[$query->dayIndex] ?? null, $query->dayIndex, $query->dayIndex, $said) as $term) {
            $out[] = $term;
        }
        foreach ($progress->days as $index => $day) {
            if ($index >= $query->dayIndex) {
                continue;
            }
            foreach ($this->termsOf($day, $index, $query->dayIndex, $said) as $term) {
                if (! $term->finished) {
                    $out[] = $term;
                }
            }
        }

        return $this->withAudio($out, $plan->targetLang()->value);
    }

    /**
     * WHAT THE ROW WILL BE ASKED NEXT — the code the day screen turns into «выберешь ответ».
     *
     * Read off the standing's next owed trainer and the turn level the planner would deal it at,
     * so the caption and the card cannot disagree ({@see PlanTurnLevel::forTurn()}).
     */
    private static function nextStepOf(PlanTermStanding $standing, ?string $shelf, bool $inSeam): ?string
    {
        $mode = $standing->nextMode;
        if ($mode === null) {
            return null;
        }

        $ordinal = 1;
        foreach ($standing->checklist as $step) {
            if (! $step['done']) {
                $ordinal = (int) $step['ordinal'];

                break;
            }
        }

        return match ($mode) {
            ExerciseMode::Intro => PlanDayTermView::STEP_MEET,
            ExerciseMode::MultipleChoice, ExerciseMode::DescriptionMatch, ExerciseMode::PickCorrect => PlanDayTermView::STEP_RECOGNIZE,
            ExerciseMode::SituationalHear => PlanDayTermView::STEP_HEAR,
            ExerciseMode::SituationalSay, ExerciseMode::SituationalAsk => PlanTurnLevel::forTurn($shelf, $ordinal, $inSeam) === PlanTurnLevel::Choose
                ? PlanDayTermView::STEP_CHOOSE
                : PlanDayTermView::STEP_ASSEMBLE,
            ExerciseMode::WordBank, ExerciseMode::Scramble => PlanDayTermView::STEP_ASSEMBLE,
            ExerciseMode::Speaking => PlanDayTermView::STEP_SAY,
            // A typed trainer is never dealt by a plan (DAY-FIX-2, Ч.2.6); a row that owes one is a
            // row that owes nothing the screen can name.
            ExerciseMode::Typing, ExerciseMode::Listening, ExerciseMode::Cloze, ExerciseMode::Dictation => null,
        };
    }

    /** «пройдено» / «сказал сам» / nothing — the row's mark once the day has been walked. */
    private static function markOf(PlanTermStanding $standing, bool $saidSelf): ?string
    {
        if ($saidSelf) {
            return PlanDayTermView::MARK_SAID_SELF;
        }

        return $standing->stage !== PlanStage::A || $standing->stageComplete
            ? PlanDayTermView::MARK_PASSED
            : null;
    }

    /**
     * Тот же список, с адресами озвучки. Одним запросом на весь регистр, а не по карточке.
     *
     * @param  list<PlanDayTermView>  $terms
     * @return list<PlanDayTermView>
     */
    private function withAudio(array $terms, string $targetLang): array
    {
        if ($terms === []) {
            return [];
        }

        $audio = $this->lineAudio->forTerms(
            array_map(static fn (PlanDayTermView $t): string => $t->termId, $terms),
            $targetLang,
        );
        if ($audio === []) {
            return $terms;
        }

        return array_map(static fn (PlanDayTermView $t): PlanDayTermView => new PlanDayTermView(
            termId: $t->termId,
            text: $t->text,
            translation: $t->translation,
            type: $t->type,
            kind: $t->kind,
            speaker: $t->speaker,
            stage: $t->stage,
            stageComplete: $t->stageComplete,
            finished: $t->finished,
            fromDayIndex: $t->fromDayIndex,
            shelf: $t->shelf,
            tier: $t->tier,
            audioId: $audio[$t->termId] ?? null,
            nextStep: $t->nextStep,
            mark: $t->mark,
        ), $terms);
    }

    /**
     * @param  array<string, true>  $said  term ids that have been said by the learner's own voice
     * @return list<PlanDayTermView>
     */
    private function termsOf(?PlanDayProgressView $day, int $index, int $dayBeingRead, array $said): array
    {
        if ($day === null) {
            return [];
        }

        $out = [];
        foreach ($day->termIds as $termId) {
            $standing = $day->standings[$termId] ?? null;
            $content = $day->content[$termId] ?? null;
            if ($standing === null || $content === null) {
                // A term whose content never arrived has no card and therefore no standing. The
                // session drops it for the same reason; showing it here would name a word the
                // trainer cannot deal.
                continue;
            }

            $out[] = new PlanDayTermView(
                termId: $termId,
                text: $content->text,
                translation: $content->translation,
                type: $content->type,
                kind: $content->kind,
                speaker: $content->speaker,
                stage: $standing->stage->value,
                stageComplete: $standing->stageComplete,
                finished: $standing->finished,
                fromDayIndex: $index,
                shelf: $content->shelf,
                tier: $content->tier,
                nextStep: self::nextStepOf($standing, $content->shelf, $index < $dayBeingRead),
                mark: self::markOf($standing, isset($said[$termId])),
            );
        }

        return $out;
    }
}
