<?php

declare(strict_types=1);

namespace App\Modules\Admin\Infrastructure\Eloquent;

use App\Modules\Admin\Application\Port\AdminAuditRecorder;
use App\Modules\Shared\Domain\Service\Clock;
use App\Modules\Shared\Domain\ValueObject\Ulid;
use Illuminate\Support\Facades\DB;

final readonly class EloquentAdminAuditRecorder implements AdminAuditRecorder
{
    public function __construct(private Clock $clock) {}

    public function record(string $adminId, string $action, ?string $targetUserId, array $context): void
    {
        DB::table('admin_audit_log')->insert([
            'id' => Ulid::generate(),
            'admin_id' => $adminId,
            'action' => $action,
            'target_user_id' => $targetUserId,
            'context' => json_encode($context, JSON_THROW_ON_ERROR),
            'created_at' => $this->clock->now(),
        ]);
    }
}
