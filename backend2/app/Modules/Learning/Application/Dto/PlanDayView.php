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
        public int $termBudget,
        public array $outcomes,
        public array $checkpoints,
        public array $topics,
        public ?array $role,
    ) {}
}
