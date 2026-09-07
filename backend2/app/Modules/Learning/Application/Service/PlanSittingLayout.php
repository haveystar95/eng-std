<?php

declare(strict_types=1);

namespace App\Modules\Learning\Application\Service;

use App\Modules\Learning\Application\Dto\PlanDialogueView;
use App\Modules\Learning\Domain\Service\PlanSessionSections;
use App\Modules\Learning\Domain\Service\PlanSittings;
use App\Modules\Learning\Domain\ValueObject\ExerciseMode;
use App\Modules\Learning\Domain\ValueObject\PlanDayStage;

/**
 * WHAT ONE SITTING WILL DEAL, before a single card is built — {@see PlanSittingPlanner}'s answer.
 *
 * Specs in running order, the conversations they rest on, and the one number the screens print:
 * how many minutes the sitting is, at the configured seconds a card. The session builder assembles
 * cards out of the specs; the day-state census reads the counts and nothing else.
 */
final readonly class PlanSittingLayout
{
    /**
     * @param  list<array<string, mixed>>  $specs  in running order, each stamped with `section_code`
     * @param  list<PlanDialogueView>  $chains
     */
    public function __construct(
        public array $specs,
        public array $chains,
        private int $cardSeconds,
    ) {}

    /** Every card of the sitting — the day part and the прогон together. */
    public function cards(): int
    {
        return count($this->specs);
    }

    /**
     * ТОЛЬКО ОДИН ЭТАП ДНЯ — то, что «Продолжить» действительно раздаёт (наряд DAY-GATE-1, Ч.1.4).
     *
     * План посадки считается ЦЕЛИКОМ (экран дня должен знать про день всё), а раздаётся по этапу:
     * человек садится за «Слова и фразы», возвращается на экран дня и видит, что осталось. До этого
     * наряда сессия несла оба присеста сразу, и человеку негде было увидеть, где он.
     *
     * РАЗОГРЕВ ОСТАЁТСЯ ВСЕГДА. Спасательный набор — ритуал ДНЯ, а не этапа (канон §5), он раз в день
     * и перед всем; выбрасывать его из вечернего присеста значило бы, что порядок «разогрев →
     * остальное» зависит от того, в какой из двух заходов человек попал.
     */
    public function onlyStage(PlanDayStage $stage): self
    {
        return new self(
            array_values(array_filter($this->specs, static function (array $spec) use ($stage): bool {
                $key = PlanSittingPlanner::sectionKeyOfSpec($spec);

                return explode('#', $key, 2)[0] === PlanSessionSections::WARMUP
                    || PlanDayStage::ofSection($key) === $stage;
            })),
            $this->chains,
            $this->cardSeconds,
        );
    }

    /** Есть ли в этой посадке хоть одно УПРАЖНЕНИЕ — карточка, на которую человек отвечает. */
    public function hasExercises(): bool
    {
        foreach ($this->specs as $spec) {
            if (($spec['mode'] ?? null) !== ExerciseMode::Intro) {
                return true;
            }
        }

        return false;
    }

    /** The cards before the прогон — what the day part holds. */
    public function dayCards(): int
    {
        return count(array_filter(
            $this->specs,
            static fn (array $s): bool => ($s['section_code'] ?? null) !== PlanSessionSections::SCENE_RUN,
        ));
    }

    /** «Материал» — the cards the learner meets and exercises (наряд DAY-FIX-3, Ч.4). */
    public function materialCards(): int
    {
        return PlanSittingPlanner::cardsOfKind($this->specs, PlanSittings::MATERIAL);
    }

    /** «Разговор» — the cards the learner says: the dialogue, the seam's lines, the прогон. */
    public function conversationCards(): int
    {
        return PlanSittingPlanner::cardsOfKind($this->specs, PlanSittings::CONVERSATION);
    }

    /** «около N минут» — cards × seconds, rounded UP to a whole minute; zero for an empty sitting. */
    public function minutes(): int
    {
        return self::minutesFor($this->cards(), $this->cardSeconds);
    }

    public function materialMinutes(): int
    {
        return self::minutesFor($this->materialCards(), $this->cardSeconds);
    }

    public function conversationMinutes(): int
    {
        return self::minutesFor($this->conversationCards(), $this->cardSeconds);
    }

    public static function minutesFor(int $cards, int $cardSeconds): int
    {
        if ($cards <= 0) {
            return 0;
        }

        return (int) ceil($cards * max(1, $cardSeconds) / 60);
    }

    /** ПРИСЕСТЫ — «Материал», then «Разговор». */
    /** @return list<int> */
    public function sittings(): array
    {
        return PlanSittingPlanner::sittingsOf($this->specs);
    }
}
