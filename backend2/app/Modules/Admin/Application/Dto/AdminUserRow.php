<?php

declare(strict_types=1);

namespace App\Modules\Admin\Application\Dto;

/** One row of the admin users list. */
final readonly class AdminUserRow
{
    public function __construct(
        public string $id,
        public string $name,
        public ?string $email,
        public string $tier,
        public ?string $cefr,
        public ?string $createdAt,
        public int $collectionsCount,
        public int $progressCount,
    ) {}
}
