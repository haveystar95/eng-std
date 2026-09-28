<?php

declare(strict_types=1);

namespace App\Modules\Plan\Domain\Lesson;

/**
 * WHO SPEAKS IN A LESSON (`lesson_day`, SPEAKERS AND ROLES; наряд GEN-3): the learner's role — the PLAN's, the same on
 * every day of the story — and the partner's role — the scene's. The roles are given to the model, not inferred by it, and
 * whatever the model writes in `learner_role` and in the roles of its messages, the server writes these over it
 * ({@see Lesson::withRoles()}): the strip of the scene and the bubbles of its dialogue say one name.
 */
final readonly class LessonRoles
{
    public function __construct(
        public string $learnerTarget,
        public string $learnerNative,
        public string $partnerTarget,
        public string $partnerNative,
    ) {}
}
