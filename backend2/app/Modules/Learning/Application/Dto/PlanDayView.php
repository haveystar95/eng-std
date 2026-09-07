<?php

declare(strict_types=1);

namespace App\Modules\Learning\Application\Dto;

/** One day of a plan, as the API and the generator both read it. */
final readonly class PlanDayView
{
    /**
     * @param  list<string>  $outcomes
     * @param  list<string>  $checkpoints
     * @param  list<string>  $topics
     * @param  array<string, mixed>|null  $role
     */
    public function __construct(
        public string $id,
        public int $index,
        public string $kind,
        public string $title,
        public ?string $scheduledOn,
        public ?string $collectionId,
        public string $status,
        public int $generationAttempts,
        public ?string $failReason,
        /**
         * WHY the day burned, as a code the client switches on — `day.example_is_a_term` and the
         * rest. Never the prose: `PlanViolation::$detail` is Russian and stays on the server, so
         * the screen owns its own wording (Д-19). Null unless the day is `failed`.
         */
        public ?string $failCode,
        public int $termBudget,
        public array $outcomes,
        public array $checkpoints,
        public array $topics,
        public ?array $role,
        /**
         * THE ВВОДКА — «кто перед тобой, что сейчас произойдёт, что считается успехом», 2–3
         * sentences in the learner's own language, written once by P1 (канон §2).
         *
         * It is what turns a list of sentences into a situation, and it is why the day screen can
         * say something before the first card. Empty on the final day, which is no scene, and on
         * every day scheduled before v0.4.
         */
        public string $intro = '',
        /**
         * ОДНО СЛОВО О ДНЕ — `not_started` | `in_progress` | `done` — и сколько минут он ещё стоит
         * (наряд DAY-FIX-2, Ч.3). Считает только сервер ({@see \App\Modules\Learning\Application\Service\PlanDayStateCensus});
         * вкладка «План», экран дня и шапка присеста читают это поле и не считают ничего сами.
         */
        public string $dayState = 'not_started',
        public int $minutesLeft = 0,
        /**
         * МИНУТЫ ДВУХ ПРИСЕСТОВ — «Материал» и «Разговор» врозь (наряд DAY-FIX-3, Ч.5.1): экран
         * дня пишет обе, вкладка «План» — ту, что впереди.
         */
        public int $materialMinutes = 0,
        public int $conversationMinutes = 0,
    ) {}
}
