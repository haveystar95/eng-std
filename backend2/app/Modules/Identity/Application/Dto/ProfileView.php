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
        // IANA timezone; UTC when the client has never sent one (device-batch F19).
        public string $timezone = 'UTC',
        // ISO-8601 instant the user finished onboarding, or null if never — the client's onboarding
        // gate (device-batch F1). Server truth: survives keychain wipe / reinstall / new device.
        public ?string $onboardedAt = null,
        // `female` | `male`, or null when the learner never said (GEN-2a). The lesson prompt's
        // LEARNER_GENDER reads it — the grammar of the learner's own lines in their language.
        public ?string $gender = null,
    ) {}
}
