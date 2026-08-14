<?php

declare(strict_types=1);

namespace App\Modules\Learning\Application\Command;

use App\Modules\Learning\Application\Dto\ExposureInput;
use App\Modules\Learning\Application\Dto\ReviewInput;
use App\Modules\Shared\Domain\ValueObject\TermId;
use App\Modules\Shared\Domain\ValueObject\UserId;

/**
 * Submit one session's worth of events (typically an offline session's) for one user.
 *
 * Two kinds, in one command because they are one transaction and one session-adoption pass:
 * ANSWERS, which are graded and folded into progress, and EXPOSURES — intro cards, which were
 * shown and asked nothing. They are kept as separate lists rather than one list with nullable
 * fields precisely so that nothing downstream can mistake an intro for a retrieval.
 */
final readonly class SubmitReviews
{
    /**
     * @param  list<ReviewInput>  $reviews
     * @param  list<ExposureInput>  $exposures
     */
    public function __construct(
        public UserId $actorId,
        public array $reviews,
        public array $exposures = [],
    ) {}

    /**
     * The distinct term ids referenced by this batch, answers and exposures alike — both are
     * checked against Vocabulary before anything is written.
     *
     * @return list<TermId>
     */
    public function termIds(): array
    {
        $unique = [];
        foreach ($this->reviews as $review) {
            $unique[$review->termId->value] = $review->termId;
        }
        foreach ($this->exposures as $exposure) {
            $unique[$exposure->termId->value] = $exposure->termId;
        }

        return array_values($unique);
    }

    /**
     * The distinct session ids referenced by this batch (an event may omit a session).
     *
     * @return list<string>
     */
    public function sessionIds(): array
    {
        $unique = [];
        foreach ($this->reviews as $review) {
            if ($review->sessionId !== null) {
                $unique[$review->sessionId->value] = true;
            }
        }
        foreach ($this->exposures as $exposure) {
            if ($exposure->sessionId !== null) {
                $unique[$exposure->sessionId->value] = true;
            }
        }

        return array_keys($unique);
    }
}
