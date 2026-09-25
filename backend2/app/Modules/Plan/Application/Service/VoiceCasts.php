<?php

declare(strict_types=1);

namespace App\Modules\Plan\Application\Service;

use App\Modules\Plan\Application\Port\LearnerGender;
use App\Modules\Plan\Application\Port\SceneLocator;
use App\Modules\Plan\Domain\Entity\PlanScene;
use App\Modules\Plan\Domain\ValueObject\VoiceCast;
use App\Modules\Shared\Domain\ValueObject\UserId;
use App\Modules\Shared\Domain\ValueObject\VoiceGender;

/**
 * WHOSE VOICES A SCENE IS SAID IN (наряд FIX-3 §1) — the one place that puts the scene's partner and the learner's own
 * gender together: the partner by the role the lesson imagines, the learner by their profile, read at the moment it is
 * asked. Every reader of the day's voice and the buyer of it ask here, so a line is looked for and bought in one voice.
 */
final readonly class VoiceCasts
{
    public function __construct(
        private SceneLocator $scenes,
        private LearnerGender $learners,
    ) {}

    /** The learner's voice — the gender their profile says, male while it says none ({@see VoiceCast::DEFAULT_LEARNER}). */
    public function learnerOf(UserId $user): VoiceGender
    {
        return $this->learners->of($user) ?? VoiceCast::DEFAULT_LEARNER;
    }

    public function ofScene(PlanScene $scene, UserId $learner): VoiceCast
    {
        return VoiceCast::ofScene($scene, $this->learnerOf($learner));
    }

    /**
     * The casts of some scenes by id, for a reader that holds cards and no aggregate — one query for the scenes and one
     * read of each learner's profile.
     *
     * @param  list<string>  $sceneIds
     * @return array<string, VoiceCast>
     */
    public function ofScenes(array $sceneIds): array
    {
        $learners = [];
        $out = [];
        foreach ($this->scenes->voicesOf($sceneIds) as $sceneId => $voices) {
            $learner = $learners[$voices['learner']->value] ??= $this->learnerOf($voices['learner']);
            $out[$sceneId] = VoiceCast::of($voices['partner'], $learner, $voices['voice']);
        }

        return $out;
    }
}
