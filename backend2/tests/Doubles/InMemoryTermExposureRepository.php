<?php

declare(strict_types=1);

namespace Tests\Doubles;

use App\Modules\Learning\Domain\Entity\TermExposure;
use App\Modules\Learning\Domain\Repository\TermExposureRepository;

/**
 * Mirrors the table's own guarantee: the PAIR is the key, so a second exposure of the same
 * (user, term) is an ignored insert and the FIRST `shown_at` survives.
 */
final class InMemoryTermExposureRepository implements TermExposureRepository
{
    /** @var array<string, TermExposure> */
    public array $byPair = [];

    public function insertIgnore(TermExposure $exposure): bool
    {
        $key = $exposure->userId->value . '|' . $exposure->termId->value;
        if (isset($this->byPair[$key])) {
            return false;
        }
        $this->byPair[$key] = $exposure;

        return true;
    }

    public function count(): int
    {
        return count($this->byPair);
    }
}
