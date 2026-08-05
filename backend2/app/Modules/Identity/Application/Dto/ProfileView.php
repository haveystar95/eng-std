<?php

declare(strict_types=1);

namespace App\Modules\Identity\Application\Dto;

final readonly class ProfileView
{
    public function __construct(
        public string $nativeLanguage,
        public string $targetLanguage,
        public string $cefrLevel,
        public int $dailyGoal,
        public string $tier = 'free',
    ) {}
}
