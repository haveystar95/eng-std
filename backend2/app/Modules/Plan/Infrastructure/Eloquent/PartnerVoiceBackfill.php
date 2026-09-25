<?php

declare(strict_types=1);

namespace App\Modules\Plan\Infrastructure\Eloquent;

use App\Modules\Plan\Domain\Entity\PlanScene;
use App\Modules\Plan\Domain\Service\PartnerVoiceRota;
use App\Modules\Shared\Domain\Service\VoiceCatalog;
use App\Modules\Shared\Domain\ValueObject\VoiceGender;
use Illuminate\Support\Facades\DB;

/**
 * THE VOICES OF THE SCENES THAT WERE THERE BEFORE (наряд FIX-4c §1): `plan_scenes.partner_voice_id` for every scene that
 * has a partner's gender, plan by plan, in the plan's order.
 *
 * - A scene whose partner lines are voiced keeps the voice they were bought in — voice 1 of its gender, the only partner
 *   voice there was: «то, чем они уже озвучены» — nothing is voiced again.
 * - A scene with its lesson and no partner line voiced yet (a stand outside the auto-voice, a plan whose voice waits for
 *   the vendor's window) is cast by the rule every new scene is cast by ({@see PartnerVoiceRota}): so the registrar and the
 *   doctor of the e2e rehearsal — two women, never voiced — are two voices from their next talk on, which is what the
 *   наряд's live rehearsal checks. The two readings of the наряд meet here: voice 1 where there is sound, the rule where
 *   there is none (отчёт FIX-4c §1).
 * - A scene with no lesson yet has no gender and no voice; its lesson casts it.
 * - A language without voices casts none.
 *
 * Idempotent: a scene that has a voice keeps it and only counts for its neighbours.
 */
final readonly class PartnerVoiceBackfill
{
    public function __construct(private VoiceCatalog $voices) {}

    /** @return array{voiced: int, cast: int, left: int} scenes given voice 1 for their sound, scenes cast by the rule, scenes left without */
    public function run(): array
    {
        $rows = DB::table('plan_scenes as s')
            ->join('plans as p', 'p.id', '=', 's.plan_id')
            ->orderBy('s.plan_id')->orderBy('s.order')->orderBy('s.id')
            ->get(['s.id', 's.plan_id', 's.order', 's.partner_voice_gender', 's.partner_voice_id', 's.lesson_status', 'p.target_lang']);

        $count = ['voiced' => 0, 'cast' => 0, 'left' => 0];
        $plans = [];
        foreach ($rows as $row) {
            $plans[(string) $row->plan_id][] = $row;
        }
        foreach ($plans as $scenes) {
            /** @var list<array{order: int, gender: VoiceGender|null, voice: string|null}> $cast */
            $cast = [];
            foreach ($scenes as $row) {
                $gender = self::genderOf($row);
                $voice = $row->partner_voice_id === null ? null : (string) $row->partner_voice_id;
                $voices = $voice === null && $gender !== null ? $this->voices->partnerVoices((string) $row->target_lang, $gender) : [];
                if ($voices !== []) {
                    $voiced = $this->voicedIn((string) $row->id, $voices[0]);
                    $voice = $voiced ? $voices[0] : PartnerVoiceRota::pick((int) $row->order, $gender, $cast, $voices);
                    if ($voice !== null) {
                        DB::table('plan_scenes')->where('id', $row->id)->whereNull('partner_voice_id')->update(['partner_voice_id' => $voice]);
                        $count[$voiced ? 'voiced' : 'cast']++;
                    }
                }
                if ($voice === null) {
                    $count['left']++;
                }
                $cast[] = ['order' => (int) $row->order, 'gender' => $gender, 'voice' => $voice];
            }
        }

        return $count;
    }

    /**
     * The partner's gender of a scene as it speaks: the one its lesson gave the role, the default cast's for a lesson
     * written before voices had genders; none — no lesson yet.
     */
    private static function genderOf(object $row): ?VoiceGender
    {
        $gender = VoiceGender::tryFromAny($row->partner_voice_gender ?? null);
        if ($gender !== null) {
            return $gender;
        }

        return in_array($row->lesson_status ?? null, ['ready', 'illustrating'], true) ? PlanScene::DEFAULT_PARTNER_VOICE : null;
    }

    /** Has the scene a partner line voiced in this voice — a stored file under a key of it. */
    private function voicedIn(string $sceneId, string $voice): bool
    {
        return DB::table('plan_line_audios')->where('scene_id', $sceneId)->where('voice_key', 'like', '%:'.$voice.':%')->exists();
    }
}
