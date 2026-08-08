<?php

declare(strict_types=1);

namespace App\Modules\Learning\Application\Query;

use App\Modules\Shared\Domain\ValueObject\UserId;

/**
 * The pool for a free-practice session: EVERY term in scope (a collection, or all the user's
 * collections), regardless of `due_at` or state — including just-added and never-triaged terms.
 * Practice never schedules, so there is no due/new distinction here; the pool is capped by
 * [sessionSize] and shuffled so repeat runs vary.
 */
final readonly class GetPracticeTerms
{
    public function __construct(
        public UserId $userId,
        public int $sessionSize = 20,
        public ?string $collectionId = null, // when set, restrict practice to that collection
    ) {}
}
