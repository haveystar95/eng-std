<?php

declare(strict_types=1);

namespace App\Modules\Admin\Application\Command;

use App\Modules\Admin\Application\Port\AdminAuditRecorder;
use App\Modules\Identity\Application\Port\UserTierReader;
use App\Modules\Identity\Application\Port\UserTierWriter;

/**
 * Sets a user's tier through Identity (the tier's owner — the same writer the dev grant-premium
 * command uses) and records the change in the admin audit trail. The write and its audit entry are
 * one operation: a tier flip never lands without a trace. Returns false when the user has no profile
 * to carry a tier (nothing changed, nothing audited).
 */
final readonly class ChangeUserTierHandler
{
    public function __construct(
        private UserTierReader $tiers,
        private UserTierWriter $writer,
        private AdminAuditRecorder $audit,
    ) {}

    public function __invoke(ChangeUserTier $command): bool
    {
        $before = $this->tiers->tierOf($command->userId);

        if (! $this->writer->setTierById($command->userId->value, $command->tier)) {
            return false;
        }

        $this->audit->record(
            $command->adminId,
            'user.tier.change',
            $command->userId->value,
            ['from' => $before->value, 'to' => $command->tier->value],
        );

        return true;
    }
}
