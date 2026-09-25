<?php

declare(strict_types=1);

namespace App\Modules\Observability\Application\Port;

use App\Modules\Shared\Domain\ValueObject\UserId;

/**
 * On account deletion, unlink the user from the request audit trail: the rows stay — what was called, when, how long,
 * with what status and how many bytes (operational value) — but `api_request_logs.user_id` is nulled so nothing points
 * back, and the user's own rows lose what they carried of the person (наряд ACC-1 §1): the request and response bodies
 * and the headers. A body is the learner's speech (`heard`), their goal (`goal_text`), their name and email in
 * `GET /auth/me` — a log «with no personal data» only once they are gone.
 */
interface RequestLogAnonymizer
{
    public function anonymizeUser(UserId $userId): void;
}
