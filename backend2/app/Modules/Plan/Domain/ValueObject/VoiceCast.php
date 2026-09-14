<?php

declare(strict_types=1);

namespace App\Modules\Plan\Domain\ValueObject;

use App\Modules\Plan\Domain\Entity\PlanScene;
use App\Modules\Shared\Domain\ValueObject\VoiceGender;

/**
 * THE TWO VOICES OF A SCENE (DAY-UI-3): the partner's, and the learner's — always the other gender.
 *
 * The learner's voice says everything that is the learner's: their lines of the dialogue, the day's
 * phrases and its words. «Голос ученика = тот же голос для его реплик и фраз.»
 */
final readonly class VoiceCast
{
    private function __construct(public VoiceGender $partner) {}

    /** The scene's cast — its stored partner gender, the default for a scene that has none. */
    public static function of(?VoiceGender $partner): self
    {
        return new self($partner ?? PlanScene::DEFAULT_PARTNER_VOICE);
    }

    public static function ofScene(PlanScene $scene): self
    {
        return self::of($scene->partnerVoiceGender());
    }

    public function learner(): VoiceGender
    {
        return $this->partner->opposite();
    }

    public function genderOf(Speaker $speaker): VoiceGender
    {
        return $speaker === Speaker::Partner ? $this->partner : $this->learner();
    }
}
