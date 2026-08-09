<?php

declare(strict_types=1);

namespace App\Modules\Admin\Application\Dto;

/** The stored profile facts for one user, as the admin panel shows them. */
final readonly class AdminUserProfileRow
{
    public function __construct(
        public string $id,
        public string $name,
        public ?string $email,
        public ?string $avatar,
        public string $tier,
        public ?string $cefr,
        public int $dailyGoal,
        public string $timezone,
        public ?string $onboardedAt,
        public ?string $createdAt,
    ) {}
}
