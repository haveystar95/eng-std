<?php

declare(strict_types=1);

namespace Tests\Doubles;

use App\Modules\Learning\Domain\Entity\TermExposure;
use App\Modules\Learning\Domain\Repository\TermExposureRepository;

/**
 * Mirrors the table's own guarantee: the PAIR is the key, so a second exposure of the same
 * (user, term) is one row — and the row carries the LAST showing, moved forward only, exactly as
 * {@see \App\Modules\Learning\Infrastructure\Eloquent\EloquentTermExposureRepository} does.
 */
final class InMemoryTermExposureRepository implements TermExposureRepository
{
    /** @var array<string, TermExposure> */
    public array $byPair = [];

    public function record(TermExposure $exposure): bool
    {
        $key = $exposure->userId->value . '|' . $exposure->termId->value;
        $existing = $this->byPair[$key] ?? null;
        if ($existing !== null) {
            if ($existing->shownAt < $exposure->shownAt) {
                $this->byPair[$key] = $exposure;
            }

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
