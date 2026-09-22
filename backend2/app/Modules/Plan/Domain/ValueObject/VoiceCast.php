<?php

declare(strict_types=1);

namespace App\Modules\Plan\Domain\ValueObject;

use App\Modules\Plan\Domain\Entity\PlanScene;
use App\Modules\Shared\Domain\ValueObject\VoiceGender;

/**
 * THE TWO VOICES OF A SCENE (DAY-UI-3; наряд FIX-3 §1): the partner's, and the learner's.
 *
 * The partner's gender is the role's — `plan_scenes.partner_voice_gender`, the gender the lesson imagines the person to
 * be. The LEARNER'S is the learner's own, from their profile, and the same on every scene and every day of every plan:
 * the voice that says «your» lines is you, not whoever the scene's partner happens not to be. It depends on the partner
 * nowhere (канон FIX-3, заменяет «два голоса разного пола на сцену» 14.09 в части ученика). A profile that has not said
 * is voiced male for now ({@see DEFAULT_LEARNER}, DECISIONS) — the phone asks once.
 *
 * Two people of one gender in a scene still sound like two people: the pack has a voice for each ROLE and each gender —
 * the partner's female and male, the learner's male and female — so a male partner and a male learner are two
 * different male voices ({@see \App\Modules\Plan\Application\Port\LineSpeaker::voiceKeyFor()}).
 *
 * The learner's voice says everything that is the learner's: their lines of the dialogue, the day's phrases and its words.
 */
final readonly class VoiceCast
{
    /** The learner's voice while their profile says no gender — male, until the phone has asked (наряд FIX-3 §1). */
    public const DEFAULT_LEARNER = VoiceGender::Male;

    private function __construct(public VoiceGender $partner, private VoiceGender $learner) {}

    /** The cast of a scene — its stored partner gender and the learner's own; a default for either that is not known. */
    public static function of(?VoiceGender $partner, ?VoiceGender $learner): self
    {
        return new self($partner ?? PlanScene::DEFAULT_PARTNER_VOICE, $learner ?? self::DEFAULT_LEARNER);
    }

    public static function ofScene(PlanScene $scene, ?VoiceGender $learner): self
    {
        return self::of($scene->partnerVoiceGender(), $learner);
    }

    public function learner(): VoiceGender
    {
        return $this->learner;
    }

    public function genderOf(Speaker $speaker): VoiceGender
    {
        return $speaker === Speaker::Partner ? $this->partner : $this->learner;
    }
}
