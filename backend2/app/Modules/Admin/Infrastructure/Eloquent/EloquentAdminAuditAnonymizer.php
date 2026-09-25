<?php

declare(strict_types=1);

namespace App\Modules\Admin\Infrastructure\Eloquent;

use App\Modules\Admin\Application\Port\AdminAuditAnonymizer;
use App\Modules\Shared\Domain\ValueObject\UserId;
use Illuminate\Support\Facades\DB;

final class EloquentAdminAuditAnonymizer implements AdminAuditAnonymizer
{
    public function anonymizeTarget(UserId $userId): void
    {
        DB::table('admin_audit_log')->where('target_user_id', $userId->value)->update(['target_user_id' => null]);
    }
}
