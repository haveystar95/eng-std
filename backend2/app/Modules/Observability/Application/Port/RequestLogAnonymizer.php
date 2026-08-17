<?php

declare(strict_types=1);

namespace App\Modules\Observability\Application\Port;

use App\Modules\Shared\Domain\ValueObject\UserId;

/**
 * On account deletion, unlink the user from the request audit trail: the logs stay (operational
 * value, already secret-redacted) but `api_request_logs.user_id` is nulled so no PII points back.
 */
interface RequestLogAnonymizer
{
    public function anonymizeUser(UserId $userId): void;
}
