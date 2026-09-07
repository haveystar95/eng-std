<?php

declare(strict_types=1);

namespace App\Modules\Learning\Domain\Exception;

use App\Modules\Shared\Domain\Exception\ProblemDetails;
use DomainException;

/**
 * ДЕНЬ ЕЩЁ ЗАПЕРТ — предыдущий не пройден (наряд DAY-GATE-1, Ч.1.2).
 *
 * Замок стоит на СЕРВЕРЕ, а не только на экране, и это не перестраховка: живой прогон 07.09 показал
 * вкладку, где день 2 открывался поверх незакрытого дня 1, потому что «открывается ли строка» решал
 * клиент по наличию материала. Замок, о котором знает один клиент, — это не замок.
 *
 * ТЕКСТА ОТСЮДА НЕ ЕДЕТ. Код и номер дня, который держит, — экран говорит об этом своими словами и
 * на своём языке («сначала закончи день 1»), как со всеми кодами плана (Д-19).
 */
final class PlanDayLocked extends DomainException implements ProblemDetails
{
    /** @param array<string, mixed> $meta */
    private function __construct(private readonly array $meta, string $message)
    {
        parent::__construct($message);
    }

    /** День N+1 закрыт, пока не пройден день `$blockedBy`. */
    public static function behind(string $planId, int $dayIndex, int $blockedBy): self
    {
        return new self(
            ['plan_id' => $planId, 'day_index' => $dayIndex, 'blocked_by_day' => $blockedBy],
            "День {$dayIndex} плана {$planId} закрыт: день {$blockedBy} ещё не пройден.",
        );
    }

    /** Внутри дня: назван этап, до которого очередь не дошла. */
    public static function stage(string $planId, int $dayIndex, string $stage): self
    {
        return new self(
            ['plan_id' => $planId, 'day_index' => $dayIndex, 'stage' => $stage],
            "Этап «{$stage}» дня {$dayIndex} плана {$planId} ещё закрыт.",
        );
    }

    public function problemStatus(): int
    {
        return 409;
    }

    public function problemCode(): string
    {
        return 'plan_day_locked';
    }

    public function problemTitle(): string
    {
        return 'Plan day locked';
    }

    /** @return array<string, mixed> */
    public function problemMeta(): array
    {
        return $this->meta;
    }
}
