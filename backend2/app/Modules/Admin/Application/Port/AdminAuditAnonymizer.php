<?php

declare(strict_types=1);

namespace App\Modules\Admin\Application\Port;

use App\Modules\Shared\Domain\ValueObject\UserId;

/**
 * On account deletion (наряд ACC-1 §1), the admin audit trail keeps what was done and by which admin, and loses whom it
 * was done to: `admin_audit_log.target_user_id` of the deleted learner is nulled. The context of a row is the change
 * itself (a tier from → to, a trainer switched), nothing of the person.
 */
interface AdminAuditAnonymizer
{
    public function anonymizeTarget(UserId $userId): void;
}
