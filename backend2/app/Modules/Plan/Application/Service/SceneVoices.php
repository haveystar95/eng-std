<?php

declare(strict_types=1);

namespace App\Modules\Plan\Application\Service;

use App\Modules\Plan\Application\Port\LineAudioStore;
use App\Modules\Plan\Application\Port\LineSpeaker;
use App\Modules\Plan\Domain\ValueObject\Speaker;
use App\Modules\Plan\Domain\ValueObject\VoiceCast;
use App\Modules\Shared\Domain\ValueObject\VoiceGender;

/**
 * Where a reader finds the voice of a day (DAY-UI-3, TTS-2): one query for every scene the cards touch, every voice of
 * the pack, and each line matched to the voice its speaker has in that scene.
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
        foreach ([Speaker::Partner, Speaker::Learner] as $speaker) {
            foreach ([VoiceGender::Female, VoiceGender::Male] as $gender) {
                $keys[$speaker->value][$gender->value] = $this->speaker->voiceKeyFor($lang, $speaker, $gender);
            }
        }
        $all = array_values(array_unique(array_filter(
            [...array_values($keys[Speaker::Partner->value]), ...array_values($keys[Speaker::Learner->value])],
            static fn (?string $k): bool => $k !== null,
        )));
        if ($all === [] || $casts === []) {
            return SceneAudioIndex::empty();
        }

        return new SceneAudioIndex($this->audios->forScenes(array_keys($casts), $all), $casts, $keys);
    }
}
