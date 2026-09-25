<?php

declare(strict_types=1);

namespace App\Modules\Plan\Application\Service;

use App\Modules\Plan\Domain\Entity\PlanScene;
use App\Modules\Plan\Domain\Repository\PlanRepository;
use App\Modules\Plan\Domain\Service\PartnerVoiceRota;
use App\Modules\Shared\Domain\Service\VoiceCatalog;
use App\Modules\Shared\Domain\ValueObject\VoiceGender;

/**
 * THE PARTNER'S VOICE OF A SCENE, CAST ONCE (наряд FIX-4c §1): when its lesson is accepted — the moment the role's gender
 * is first known — by the rota of the plan's scenes ({@see PartnerVoiceRota}) over the pack's two voices of the gender.
 *
 * The voice is a fact of the plan, not a purchase: it is cast from the language pack whether speech is switched on or
 * not, so a plan built while the voice is off still alternates when it is switched on. A language with no voices casts
 * none — its lines are read by the phone.
 */
final readonly class PartnerVoices
{
    public function __construct(private VoiceCatalog $voices) {}

    /**
     * @param  list<array{id: string, order: int, gender: VoiceGender|null, voice: string|null}>  $scenes  the plan's scenes
     *   as {@see PlanRepository::sceneVoicesForUpdate()} locked them
     */
    public function cast(PlanScene $scene, string $targetLang, array $scenes): void
    {
        $gender = $scene->partnerVoiceGender();
        if ($gender === null || $scene->partnerVoiceId() !== null) {
            return;
        }
        $others = array_values(array_filter($scenes, static fn (array $s): bool => $s['id'] !== $scene->id()->value));
        $voice = PartnerVoiceRota::pick($scene->order(), $gender, $others, $this->voices->partnerVoices($targetLang, $gender));
        if ($voice !== null) {
            $scene->castPartnerVoice($voice);
        }
    }
}
