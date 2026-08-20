<?php

declare(strict_types=1);

namespace App\Modules\Learning\Application\Query;

use App\Modules\Shared\Domain\ValueObject\UserId;

/**
 * The pool for a free-practice session, regardless of `due_at` or state. Practice never schedules,
 * so there is no due/new distinction here; the selection is capped by [sessionSize] and shuffled so
 * repeat runs vary.
 *
 * WHAT it draws from depends on [collectionId], and the handler is where that is written down:
 * without one, the learner's POOL; with one, the WHOLE COLLECTION — the words being studied first,
 * the untriaged rest of the topic after them.
 */
final readonly class GetPracticeTerms
{
    public function __construct(
        public UserId $userId,
        public int $sessionSize = 20,
        public ?string $collectionId = null, // when set, restrict practice to that collection
    ) {}
}
