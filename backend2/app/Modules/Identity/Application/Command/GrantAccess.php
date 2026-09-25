<?php

declare(strict_types=1);

namespace App\Modules\Identity\Application\Command;

use App\Modules\Identity\Domain\ValueObject\EntitlementProduct;
use App\Modules\Identity\Domain\ValueObject\EntitlementSource;
use App\Modules\Shared\Domain\ValueObject\UserId;
use DateTimeImmutable;

/**
 * GIVE THE LEARNER THE PAID PLAN (наряд ACC-1 §2, `php artisan access:grant {user} {product} {--until=}`): from now, for
 * the product's term — a month, a year, for good — or until the date given. The learner's right of this source is
 * rewritten; a store's right beside it stays as it is.
 */
final readonly class GrantAccess
{
    public function __construct(
        public UserId $userId,
        public EntitlementProduct $product,
        public ?DateTimeImmutable $until = null,
        public EntitlementSource $source = EntitlementSource::Admin,
    ) {}
}
