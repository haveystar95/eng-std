<?php

declare(strict_types=1);

namespace App\Modules\Learning\Application\Command;

use App\Modules\Learning\Domain\ValueObject\PlanId;
use App\Modules\Shared\Domain\ValueObject\UserId;

/**
 * ЗАВЕРШЁННЫЙ ПРОГОН СЦЕНЫ, как о нём говорит клиент: ход за ходом, исходом на каждый.
 *
 * Арифметику («сам N из M · сразу K») сервер считает САМ, из этого списка. Клиент числа не
 * присылает: итог прогона — то, что человеку показывают и что потом читает зрелость сцены, и число,
 * посчитанное на телефоне, было бы вторым источником правды о том, чего человек добился.
 */
final readonly class RecordSceneRun
{
    /** @param list<SceneRunTurn> $turns ходы в порядке цепочки */
    public function __construct(
        public PlanId $planId,
        public UserId $actorId,
        public int $sceneIndex,
        public int $dayIndex,
        public array $turns,
    ) {}
}
