<?php

declare(strict_types=1);

namespace App\Modules\Admin\Application\Dto;

/** Whether the paywall of the plan is switched on (`access.paywall_enabled`, наряд ACC-1 §2) — shown on the access page. */
final readonly class PaywallSwitch
{
    public function __construct(public bool $enabled) {}
}
