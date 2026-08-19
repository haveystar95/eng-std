<?php

declare(strict_types=1);

namespace App\Modules\Learning\Application\Query;

use App\Modules\Shared\Domain\ValueObject\UserId;

/**
 * The pool for a free-practice session: every pair the learner has ENROLLED in scope (one
 * collection, or the whole pool), regardless of `due_at` or state. Practice never schedules, so
 * there is no due/new distinction here; the pool is capped by [sessionSize] and shuffled so repeat
 * runs vary. A term the learner has never taken into study is not drilled here — it is in the
 * catalogue, not in training.
 */
final readonly class GetPracticeTerms
{
    public function __construct(
        public UserId $userId,
        public int $sessionSize = 20,
        public ?string $collectionId = null, // when set, restrict practice to that collection
    ) {}
}
