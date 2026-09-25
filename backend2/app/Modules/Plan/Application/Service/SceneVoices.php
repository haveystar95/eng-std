<?php

declare(strict_types=1);

namespace App\Modules\Plan\Application\Service;

use App\Modules\Plan\Application\Port\LineAudioStore;
use App\Modules\Plan\Application\Port\LineSpeaker;
use App\Modules\Plan\Domain\ValueObject\Speaker;
use App\Modules\Plan\Domain\ValueObject\VoiceCast;

/**
 * Where a reader finds the voice of a day (DAY-UI-3, TTS-2): one query for every scene the cards touch, in the voices
 * those scenes speak in, and each line matched to the voice its speaker has in that scene — the partner's the voice fixed
 * for the scene (наряд FIX-4c §1), the learner's their own by gender.
 */
final readonly class SceneVoices
{
    public function __construct(
        private LineAudioStore $audios,
        private LineSpeaker $speaker,
    ) {}

    /** @param array<string, VoiceCast> $casts scene id → its cast */
    public function index(string $lang, array $casts): SceneAudioIndex
    {
        $keys = [];
        foreach ([...$casts, SceneAudioIndex::DEFAULT_CAST => VoiceCast::of(null, null)] as $sceneId => $cast) {
            foreach ([Speaker::Partner, Speaker::Learner] as $speaker) {
                $keys[(string) $sceneId][$speaker->value] = $this->speaker->voiceKeyFor($lang, $speaker, $cast->genderOf($speaker), $cast->voiceOf($speaker));
            }
        }
        $all = [];
        foreach ($keys as $sceneKeys) {
            foreach ($sceneKeys as $key) {
                if ($key !== null && ! in_array($key, $all, true)) {
                    $all[] = $key;
                }
            }
        }
        if ($all === [] || $casts === []) {
            return SceneAudioIndex::empty();
        }

        return new SceneAudioIndex($this->audios->forScenes(array_keys($casts), $all), $keys);
    }
}
