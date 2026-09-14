<?php

declare(strict_types=1);

namespace App\Modules\Plan\Infrastructure\Adapter;

use App\Modules\Identity\Application\Port\UserReader;
use App\Modules\Plan\Application\Port\LearnerGender;
use App\Modules\Shared\Domain\ValueObject\UserId;
use App\Modules\Shared\Domain\ValueObject\VoiceGender;

/** The learner's gender, read through Identity's Application when a lesson is written — never cached across lessons. */
final readonly class IdentityLearnerGender implements LearnerGender
{
    public function __construct(private UserReader $users) {}

    public function of(UserId $user): ?VoiceGender
    {
        return VoiceGender::tryFromAny($this->users->byId($user)?->profile?->gender);
    }
}
