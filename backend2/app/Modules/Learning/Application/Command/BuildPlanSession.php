<?php

declare(strict_types=1);

namespace App\Modules\Learning\Application\Command;

use App\Modules\Learning\Domain\ValueObject\PlanDayStage;
use App\Modules\Learning\Domain\ValueObject\StudySessionId;
use App\Modules\Shared\Domain\ValueObject\UserId;

/**
 * Build the session for one day of a plan.
 *
 * `dayIndex` null means «the day I am on» — the plan's own focus, which the server computes. A
 * client that names a day gets that day, and whether the session is STRICT (stages, checklists,
 * the focus moving) depends on whether the named day IS the focus: a day opened ahead of it, or
 * gone back to, is an ordinary soft run over its collection.
 */
final readonly class BuildPlanSession
{
    public function __construct(
        public UserId $actorId,
        public string $planId,
        public ?int $dayIndex = null,
        public ?StudySessionId $sessionId = null,
        /**
         * КАКОЙ ЭТАП ДНЯ СОБРАТЬ — null значит «текущий», и так приходит «Продолжить»
         * (наряд DAY-GATE-1, Ч.1.4).
         *
         * Клиент называет этап ровно в одном случае — «Повторить ошибки»
         * ({@see \App\Modules\Learning\Domain\ValueObject\PlanDayStage::Retrain}), потому что это
         * единственная дверь, которую человек открывает не по порядку. Назвать ЗАПЕРТЫЙ этап нельзя:
         * сервер отвечает отказом, а не собирает то, до чего очередь не дошла.
         */
        public ?PlanDayStage $stage = null,
    ) {}
}
