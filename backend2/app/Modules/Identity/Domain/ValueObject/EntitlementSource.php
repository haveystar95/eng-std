<?php

declare(strict_types=1);

namespace App\Modules\Identity\Domain\ValueObject;

/**
 * WHERE A RIGHT TO THE PAID PLAN CAME FROM (наряд ACC-1 §2): given by hand — `admin` (`php artisan access:grant`, the
 * owner's grants to the learners who were there before the paywall), a promotion — `promo`, or bought in a store —
 * `apple`, `google`. The stores' rows are written by the purchase sync of PAY-1 (RevenueCat), not by this order.
 */
enum EntitlementSource: string
{
    case Admin = 'admin';
    case Promo = 'promo';
    case Apple = 'apple';
    case Google = 'google';
}
