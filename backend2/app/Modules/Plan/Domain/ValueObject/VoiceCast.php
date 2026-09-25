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
 *
 * The partner has TWO voices of each gender (наряд FIX-4c §1), and which of them a scene speaks in is fixed for it
 * ({@see PlanScene::partnerVoiceId()}): the cast carries that voice, so every reader and buyer of the scene's partner
 * lines asks for the same one. None fixed — the pack's first voice of the gender.
 */
final readonly class VoiceCast
{
    /** The learner's voice while their profile says no gender — male, until the phone has asked (наряд FIX-3 §1). */
    public const DEFAULT_LEARNER = VoiceGender::Male;

    private function __construct(public VoiceGender $partner, private VoiceGender $learner, public ?string $partnerVoice = null) {}

    /**
     * The cast of a scene — its stored partner gender and the learner's own, a default for either that is not known —
     * and the partner's voice fixed for the scene, if one is.
     */
    public static function of(?VoiceGender $partner, ?VoiceGender $learner, ?string $partnerVoice = null): self
    {
        $voice = trim((string) $partnerVoice);

        return new self($partner ?? PlanScene::DEFAULT_PARTNER_VOICE, $learner ?? self::DEFAULT_LEARNER, $voice === '' ? null : $voice);
    }

    public static function ofScene(PlanScene $scene, ?VoiceGender $learner): self
    {
        return self::of($scene->partnerVoiceGender(), $learner, $scene->partnerVoiceId());
    }

    public function learner(): VoiceGender
    {
        return $this->learner;
    }

    public function genderOf(Speaker $speaker): VoiceGender
    {
        return $speaker === Speaker::Partner ? $this->partner : $this->learner;
    }

    /** The voice fixed for the speaker in this scene — the partner's, if one is; the learner's is theirs by gender. */
    public function voiceOf(Speaker $speaker): ?string
    {
        return $speaker === Speaker::Partner ? $this->partnerVoice : null;
    }
}
