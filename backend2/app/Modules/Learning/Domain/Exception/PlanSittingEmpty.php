<?php

declare(strict_types=1);

namespace App\Modules\Learning\Domain\Exception;

use App\Modules\Shared\Domain\Exception\ProblemDetails;
use DomainException;

/**
 * СОБИРАТЬ НЕЧЕГО — и поэтому сессия НЕ создаётся (наряд DAY-GATE-1, Ч.1.4).
 *
 * Живой прогон 07.09: после «Дотренировать» человек получил сессию из одной интро-карточки, а затем
 * шесть сессий подряд вообще без карточек — все шесть остались незакрытыми в базе, и на экране это
 * читалось как «здесь пока нечего повторять» над днём, который сам себя считал идущим.
 *
 * Пустая посадка — это не экран, это отказ. На НЕ пройденном этапе её быть не может по построению:
 * этап текущий ровно тогда, когда он что-то должен ({@see \App\Modules\Learning\Domain\Service\PlanDayPassage}).
 * Остаётся один законный случай — спек есть, а карточку сборщик построить не смог, — и о нём надо
 * говорить кодом, а не пустой лентой.
 */
final class PlanSittingEmpty extends DomainException implements ProblemDetails
{
    /** @param array<string, mixed> $meta */
    private function __construct(private readonly array $meta, string $message)
    {
        parent::__construct($message);
    }

    public static function forDay(string $planId, int $dayIndex, ?string $stage): self
    {
        return new self(
            ['plan_id' => $planId, 'day_index' => $dayIndex, 'stage' => $stage],
            "Для дня {$dayIndex} плана {$planId} нечего собрать.",
        );
    }

    public function problemStatus(): int
    {
        return 409;
    }

    public function problemCode(): string
    {
        return 'plan_sitting_empty';
    }

    public function problemTitle(): string
    {
        return 'Nothing to deal';
    }

    /** @return array<string, mixed> */
    public function problemMeta(): array
    {
        return $this->meta;
    }
}
