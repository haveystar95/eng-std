<?php

declare(strict_types=1);

namespace App\Modules\Learning\Application\Command;

use App\Modules\Learning\Application\Dto\ReviewInput;
use App\Modules\Shared\Domain\ValueObject\TermId;
use App\Modules\Shared\Domain\ValueObject\UserId;

/** Submit a batch of graded answers (typically an offline session's worth) for one user. */
final readonly class SubmitReviews
{
    /** @param list<ReviewInput> $reviews */
    public function __construct(
        public UserId $actorId,
        public array $reviews,
    ) {}

    /**
     * The distinct term ids referenced by this batch.
     *
     * @return list<TermId>
     */
    public function termIds(): array
    {
        $unique = [];
        foreach ($this->reviews as $review) {
            $unique[$review->termId->value] = $review->termId;
        }

        return array_values($unique);
    }
}
