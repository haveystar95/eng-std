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
 * THE INPUTS OF A SCENE'S DAY (наряды GEN-3, GEN-4) — one way to put them together, for the skeleton and for the dialogue:
 *
 *  - TOPIC — the scene's native title; TOPIC_DESCRIPTION — its three-line brief, then the plan's goal in the learner's own
 *    words (the facts a frame's slot may take);
 *  - SURVIVAL_SET — the scene's `must_say` and `must_understand`;
 *  - the pair of languages by name for the prompts and by code for the checks; LEVEL; VOCABULARY_COUNT — the level's range;
 *  - LEARNER_GENDER — the profile's, read now;
 *  - LEARNER_ROLE — the plan's, PARTNER_ROLE — the scene's ({@see Plan::lessonRoles()});
 *  - EARLIER_DAYS — the scene days of the plan before this one whose lesson is written ({@see Plan::earlierDaysOf()});
 *  - the scene's id — the seed the options are shuffled by.
 */
final readonly class LessonRequests
{
    public function __construct(
        private PlanConfig $config,
        private LearnerGender $gender,
    ) {}

    public function for(Plan $plan, PlanScene $scene): LessonRequest
    {
        [$min, $max] = $this->config->vocabularyRange($plan->level());

        return new LessonRequest(
            topic: $scene->titleNative(),
            topicDescription: self::topicDescription($scene->topicDescription(), $plan->goalText()),
            survival: $scene->survival(),
            targetLanguage: LanguageName::of($plan->targetLang()->value),
            nativeLanguage: LanguageName::of($plan->nativeLang()->value),
            level: $plan->level(),
            learnerGender: $this->gender->of($plan->userId()),
            vocabularyMin: $min,
            vocabularyMax: $max,
            roles: $plan->lessonRoles($scene),
            earlierDays: $plan->earlierDaysOf($scene->id()),
            targetLangCode: $plan->targetLang()->value,
            nativeLangCode: $plan->nativeLang()->value,
            sceneId: $scene->id()->value,
        );
    }

    /** How TOPIC_DESCRIPTION names the learner's own words — the line the skeleton reads them on. */
    public const LEARNER_WORDS = 'About the learner, in their own words:';

    /** The scene's brief, then the learner's own words — the facts a frame's slot may take. */
    public static function topicDescription(string $brief, string $goal): string
    {
        $goal = trim((string) preg_replace('/\s+/u', ' ', $goal));

        return $goal === '' ? trim($brief) : trim($brief)."\n\n".self::LEARNER_WORDS." {$goal}";
    }

    /**
     * THE LEARNER'S OWN WORDS AS THE SKELETON GOT THEM (наряд GEN-4c) — the line of a TOPIC_DESCRIPTION this class wrote
     * ({@see topicDescription()}), '' when it has none: the only details of the learner a day is written from (the profile
     * gives the skeleton the learner's gender and no words). What `vocab.from_placeholder` tells a learner's detail from a
     * placeholder by.
     */
    public static function learnerWords(string $topicDescription): string
    {
        $at = mb_strrpos($topicDescription, self::LEARNER_WORDS);

        return $at === false ? '' : trim(mb_substr($topicDescription, $at + mb_strlen(self::LEARNER_WORDS)));
    }
}
