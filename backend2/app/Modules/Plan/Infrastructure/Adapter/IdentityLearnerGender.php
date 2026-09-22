<?php

declare(strict_types=1);

namespace App\Modules\Plan\Infrastructure\Adapter;

use App\Modules\Identity\Application\Port\UserReader;
use App\Modules\Plan\Application\Port\LearnerGender;
use App\Modules\Shared\Domain\ValueObject\UserId;
use App\Modules\Shared\Domain\ValueObject\VoiceGender;

/**
 * The learner's gender by their profile, read through Identity's Application (the user by primary key, the profile by its
 * unique `user_id`) — when a lesson is written, and since наряд FIX-3 §1 for the learner's voice of every scene and the
 * learner's gendered lines of the server. Never cached: a profile changed is read at the next request.
 */
final readonly class IdentityLearnerGender implements LearnerGender
{
    public function __construct(private UserReader $users) {}

    public function of(UserId $user): ?VoiceGender
    {
        return VoiceGender::tryFromAny($this->users->byId($user)?->profile?->gender);
    }
}
