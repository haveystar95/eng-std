<?php

declare(strict_types=1);

namespace App\Modules\Plan\Application\Service;

use App\Modules\Plan\Application\Port\LineAudioStore;
use App\Modules\Plan\Application\Port\LineSpeaker;
use App\Modules\Plan\Domain\ValueObject\VoiceCast;
use App\Modules\Shared\Domain\ValueObject\VoiceGender;

/**
 * Where a reader finds the voice of a day (DAY-UI-3): one query for every scene the cards touch, both
 * of the pack's voices, and each line matched to the voice its speaker has in that scene.
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
        $female = $this->speaker->voiceKeyFor($lang, VoiceGender::Female);
        $male = $this->speaker->voiceKeyFor($lang, VoiceGender::Male);
        $keys = array_values(array_filter([$female, $male], static fn (?string $k): bool => $k !== null));
        if ($keys === [] || $casts === []) {
            return SceneAudioIndex::empty();
        }

        return new SceneAudioIndex($this->audios->forScenes(array_keys($casts), $keys), $casts, $female, $male);
    }
}
