<?php

declare(strict_types=1);

namespace App\Modules\Learning\Domain\Exception;

use App\Modules\Shared\Domain\Exception\ProblemDetails;
use DomainException;

/**
 * Deleting the collection a plan day owns.
 *
 * The same rule as {@see PlanHeldTerm}, one level up and with a harsher consequence: a day's
 * collection IS the day's material. Deleting it does not weaken the day, it empties it — the plan
 * would still show day 2 and day 2 would open onto nothing.
 *
 * A 409 with the plan named, so the client can offer «отказаться от плана» instead of failing.
 */
final class PlanHeldCollection extends DomainException implements ProblemDetails
{
    private function __construct(
        private readonly string $collectionId,
        private readonly string $planId,
        string $message,
    ) {
        parent::__construct($message);
    }

    public static function make(string $collectionId, string $planId): self
    {
        return new self(
            $collectionId,
            $planId,
            'Это коллекция дня активного плана — она и есть материал этого дня. Удалить её нельзя: '
            . 'сначала поставьте план на паузу или откажитесь от него.',
        );
    }

    public function problemStatus(): int
    {
        return 409;
    }

    public function problemCode(): string
    {
        return 'plan_held_collection';
    }

    public function problemTitle(): string
    {
        return 'The collection belongs to a running plan day';
    }

    /** @return array<string, mixed> */
    public function problemMeta(): array
    {
        return ['collection_id' => $this->collectionId, 'plan_id' => $this->planId];
    }
}
