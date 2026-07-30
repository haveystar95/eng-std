<?php

declare(strict_types=1);

namespace App\Modules\Learning\Application\Command;

use App\Modules\Learning\Application\Dto\TriageInput;
use App\Modules\Shared\Domain\ValueObject\TermId;
use App\Modules\Shared\Domain\ValueObject\UserId;

/** Submit a batch of triage swipes (a first-pass sweep of a collection) for one user. */
final readonly class TriageTerms
{
    /** @param list<TriageInput> $triages */
    public function __construct(
        public UserId $actorId,
        public array $triages,
    ) {}

    /**
     * The distinct term ids referenced by this batch.
     *
     * @return list<TermId>
     */
    public function termIds(): array
    {
        $unique = [];
        foreach ($this->triages as $triage) {
            $unique[$triage->termId->value] = $triage->termId;
        }

        return array_values($unique);
    }
}
