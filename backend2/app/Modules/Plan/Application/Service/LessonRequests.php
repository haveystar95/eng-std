<?php

declare(strict_types=1);

namespace App\Modules\Plan\Application\Service;

use App\Modules\Plan\Application\Dto\LessonRequest;
use App\Modules\Plan\Application\Dto\PlanConfig;
use App\Modules\Plan\Application\Port\LearnerGender;
use App\Modules\Plan\Domain\Entity\Plan;
use App\Modules\Plan\Domain\Entity\PlanScene;
use App\Modules\Shared\Domain\Service\LanguageName;

/**
 * THE LESSON REQUEST OF A SCENE (`lesson_day.v4.7`; наряды GEN-2a, GEN-3) — one way to put the prompt's inputs together, for
 * the build of a day and for the repair of a stored day's card alike:
 *
 *  - TOPIC — the scene's native title; TOPIC_DESCRIPTION — its brief, then the plan's goal in the learner's own words
 *    (the facts a frame's slot may take);
 *  - the pair of languages by name for the prompt and by code for the validator; LEVEL; the counts of the level;
 *  - LEARNER_GENDER — the profile's, read now;
 *  - LEARNER_ROLE — the plan's, PARTNER_ROLE — the scene's ({@see Plan::lessonRoles()});
 *  - EARLIER_DAYS — the scene days of the plan before this one whose lesson is written ({@see Plan::earlierDaysOf()}).
 */
final readonly class LessonRequests
{
    public function __construct(
        private PlanConfig $config,
        private LearnerGender $gender,
    ) {}

    public function for(Plan $plan, PlanScene $scene): LessonRequest
    {
        $counts = $this->config->countsFor($plan->level());

        return new LessonRequest(
            topic: $scene->titleNative(),
            topicDescription: self::topicDescription($scene->topicDescription(), $plan->goalText()),
            targetLanguage: LanguageName::of($plan->targetLang()->value),
            nativeLanguage: LanguageName::of($plan->nativeLang()->value),
            level: $plan->level(),
            learnerGender: $this->gender->of($plan->userId()),
            vocabularyCount: $counts['vocabulary'],
            dialogueCount: $counts['dialogue'],
            roles: $plan->lessonRoles($scene),
            earlierDays: $plan->earlierDaysOf($scene->id()),
            targetLangCode: $plan->targetLang()->value,
            nativeLangCode: $plan->nativeLang()->value,
        );
    }

    /** The scene's brief, then the learner's own words — the facts a frame's slot may take. */
    public static function topicDescription(string $brief, string $goal): string
    {
        $goal = trim((string) preg_replace('/\s+/u', ' ', $goal));

        return $goal === '' ? trim($brief) : trim($brief)."\n\nAbout the learner, in their own words: {$goal}";
    }
}
