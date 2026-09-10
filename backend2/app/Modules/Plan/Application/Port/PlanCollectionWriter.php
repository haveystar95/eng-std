<?php

declare(strict_types=1);

namespace App\Modules\Plan\Application\Port;

use App\Modules\Plan\Domain\Entity\Plan;
use App\Modules\Plan\Domain\Entity\PlanTerm;
use App\Modules\Shared\Domain\ValueObject\CollectionId;

/**
 * The plan's collection — the ordinary collection the plan's words and phrases go into when a
 * day is closed, through Vocabulary's dedup and Collections' own commands. One per plan, created
 * on the first close.
 */
interface PlanCollectionWriter
{
    public function ensureCollection(Plan $plan): CollectionId;

    /** @param list<PlanTerm> $terms */
    public function addTerms(Plan $plan, CollectionId $collectionId, array $terms, string $promptVersion): void;
}
