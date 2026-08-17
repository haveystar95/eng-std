<?php

declare(strict_types=1);

namespace App\Modules\Admin\Application\Port;

/**
 * Appends one row to the admin audit trail. Every admin mutation records here — the write and its
 * audit entry live in the same command, so a mutation can't land without a trace.
 */
interface AdminAuditRecorder
{
    /**
     * @param  array<string, mixed>  $context  structured detail (e.g. before/after values)
     */
    public function record(string $adminId, string $action, ?string $targetUserId, array $context): void;
}
