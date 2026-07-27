<?php

declare(strict_types=1);

namespace App\Modules\Collections\Domain\Entity;

use App\Modules\Shared\Domain\ValueObject\TermId;

/** A term's membership in a collection. Identity within the aggregate is the term. */
final class CollectionItem
{
    public function __construct(
        public readonly TermId $termId,
        public int $position,
        public readonly ?string $note = null,
    ) {}
}
