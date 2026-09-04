<?php

declare(strict_types=1);

namespace App\Modules\Learning\Application\Service;

use App\Modules\Learning\Domain\Entity\Review;
use App\Modules\Learning\Domain\Repository\PlanRepository;
use App\Modules\Learning\Domain\Repository\PlanTermStageRepository;
use App\Modules\Learning\Domain\ValueObject\PlanTermStage;
use App\Modules\Shared\Domain\ValueObject\UserId;

/**
 * ЧТО ОТВЕТ НА ХОД ДИАЛОГА МЕНЯЕТ В СТРОГОСТИ СЛЕДУЮЩЕГО — наряд SCENE-RUN, Ч.1.
 *
 * Лестница плана этого не считает и не должна: она проекция журнала, а журнал не различает выбор и
 * сборку — тренажёр у них один ({@see \App\Modules\Learning\Domain\Service\PlanDialogueLevel}).
 * Здесь живёт единственный факт, который приходится хранить, и он обновляется ровно там, где
 * появляется, — в той же транзакции, что пишет ответ.
 *
 * ## Почему не спрашиваем, принадлежит ли карточка плану
 *
 * Ситуационные карточки бывают ТОЛЬКО в плане: их строки в матрице заведены со `scope = plan`, и в
 * обычной сессии нет сцены, о которой карточка могла бы спросить (`docs/plan-map.md` §2.2а).
 * Значит `situational_say` / `situational_ask` в непрактическом ответе — это ход диалога идущего
 * плана, и второй запрос, который это подтвердил бы, подтверждал бы уже известное. Если активного
 * плана у человека нет — писать некуда, и мы молча не пишем: ответ уже лежит в журнале, а строка
 * строгости это подсказка о подаче, а не доказательство.
 */
final readonly class PlanTurnProgress
{
    public function __construct(
        private PlanRepository $plans,
        private PlanTermStageRepository $stages,
    ) {}

    /**
     * @param  list<Review>  $accepted  ответы, ПРИНЯТЫЕ в журнал этой партией
     */
    public function record(UserId $user, array $accepted): void
    {
        $turns = array_values(array_filter(
            $accepted,
            static fn (Review $review): bool => ! $review->isPractice
                && $review->exerciseMode?->speaksAfterChoice() === true,
        ));
        if ($turns === []) {
            return;
        }

        $plan = $this->plans->findActiveFor($user);
        if ($plan === null) {
            return;
        }

        $stages = $this->stages->forPlan($plan->id());
        foreach ($turns as $review) {
            $termId = $review->termId->value;
            $stage = $stages[$termId] ?? new PlanTermStage($plan->id()->value, $termId);
            // По порядку внутри партии: догнавшая офлайн-очередь приходит отсортированной по
            // `client_seq`, и два ответа на один ход обязаны примениться в том же порядке, в каком
            // человек их дал, — иначе верный ответ после неверного читался бы наоборот.
            $stage = $stage->afterChoice($review->isCorrect());
            $stages[$termId] = $stage;
            $this->stages->save($stage);
        }
    }
}
