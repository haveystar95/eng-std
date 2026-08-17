<?php

declare(strict_types=1);

namespace App\Modules\Generation\Application\Command;

/** Re-ask the validator's questions of already-stored content. `$apply` false = a dry run. */
final readonly class AuditDistractors
{
    public function __construct(public bool $apply = false) {}
}
