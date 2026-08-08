<?php

declare(strict_types=1);

namespace App\Modules\Identity\Application\Dto;

/** Partial profile update — only non-null fields are applied. */
final readonly class ProfileInput
{
    public function __construct(
        public ?string $nativeLanguage = null,
        public ?string $targetLanguage = null,
        public ?string $cefrLevel = null,
        public ?int $dailyGoal = null,
        // The onboarding-finish call sends this true; the updater then stamps `onboarded_at` once
        // (never overwrites). Regular profile edits leave it null. (device-batch F1)
        public ?bool $onboarded = null,
    ) {}
}
