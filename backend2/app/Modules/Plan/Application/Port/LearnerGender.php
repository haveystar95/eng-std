<?php

declare(strict_types=1);

namespace App\Modules\Plan\Application\Port;

use App\Modules\Shared\Domain\ValueObject\UserId;
use App\Modules\Shared\Domain\ValueObject\VoiceGender;

/**
 * The learner's gender as their profile says it at the moment a lesson is written — what the lesson
 * prompt's LEARNER_GENDER reads (the grammar of the learner's own lines in their language). Null when
 * the profile says nothing: the prompt then hears «unknown».
 */
interface LearnerGender
{
    public function of(UserId $user): ?VoiceGender;
}
