<?php

declare(strict_types=1);

namespace App\Modules\Learning\Application\Dto;

/**
 * A whole plan, structure and all.
 *
 * The naряд's answer to «что отдаёт GET /plans/active»: everything, in one read. The screens for
 * this do not exist yet (1c), and a partial payload designed against imagined screens is how an API
 * ends up needing a second version the week the screens arrive.
 */
final readonly class PlanView
{
    /**
     * @param  list<PlanDayView>  $days
     * @param  array<string, mixed>|null  $computed  the server's arithmetic, verbatim
     * @param  list<string>  $constraints
     * @param  list<string>  $goalTerms
     * @param  list<array{name: string, gender: string, number: string, note: string}>  $entities
     */
    public function __construct(
        public string $id,
        public string $status,
        public string $title,
        public string $goalText,
        public ?string $goalRestated,
        public string $supportLang,
        public string $targetLang,
        public string $level,
        public string $eventDate,
        public int $minutesPerDay,
        public ?string $startedAt,
        public ?string $completedAt,
        public array $days,
        public ?array $computed,
        public array $entities,
        public array $constraints,
        public array $goalTerms,
        /**
         * How ready the learner is, 0…1.
         *
         * v1a formula, and it is written here because it is going to change: `0.4 × доля терминов
         * на ступени C`. The ladder rungs land in 1b and the checkpoint half of the number needs
         * the conversation, which is CONV-1 — so today the checkpoint contribution is a literal
         * zero and the term contribution is capped at 0.4. A number that pretended to be complete
         * would read as «40% готов» on a plan whose conversations have never run.
         */
        public float $readiness,
    ) {}
}
