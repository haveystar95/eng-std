<?php

declare(strict_types=1);

namespace Tests\Doubles;

use App\Modules\Learning\Domain\Entity\Review;
use App\Modules\Learning\Domain\Repository\ReviewRepository;

final class InMemoryReviewRepository implements ReviewRepository
{
    /** @var array<string, Review> */
    private array $byId = [];

    public function insertIgnore(Review $review): bool
    {
        if (isset($this->byId[$review->id->value])) {
            return false;
        }

        $this->byId[$review->id->value] = $review;

        return true;
    }

    public function count(): int
    {
        return count($this->byId);
    }

    /** @return list<Review> */
    public function all(): array
    {
        return array_values($this->byId);
    }
}
