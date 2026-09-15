<?php

declare(strict_types=1);

namespace App\Modules\Plan\Application\Service;

use App\Modules\Plan\Application\Dto\LineAudioRow;
use App\Modules\Plan\Domain\Service\SpokenLines;
use App\Modules\Plan\Domain\ValueObject\VoiceCast;

/**
 * The spoken files of some scenes, found by what they voice (DAY-UI-3, TTS-2): the partner's line in the voice the
 * partner has in that scene, everything else in the learner's. A file of another voice does not count — a line read
 * by the wrong person is not this line.
 */
final readonly class SceneAudioIndex
{
    /**
     * @param  array<string, LineAudioRow>  $rows  keyed `<scene>:<ref>:<voice key>`
     * @param  array<string, VoiceCast>  $casts  scene id → its two voices
     * @param  array<string, array<string, string|null>>  $keys  speaker → gender → the voice key the pack files it under
     */
    public function __construct(
        private array $rows,
        private array $casts,
        private array $keys,
    ) {}

    public static function empty(): self
    {
        return new self([], [], []);
    }

    /** The audio id of what `$ref` voices in scene `$sceneId`, or null — not voiced yet (the phone reads it). */
    public function idOf(string $sceneId, string $ref): ?string
    {
        $speaker = SpokenLines::speakerOf($ref);
        $gender = ($this->casts[$sceneId] ?? VoiceCast::of(null))->genderOf($speaker);
        $key = $this->keys[$speaker->value][$gender->value] ?? null;

        return $key === null ? null : ($this->rows[$sceneId.':'.$ref.':'.$key] ?? null)?->id;
    }
}
