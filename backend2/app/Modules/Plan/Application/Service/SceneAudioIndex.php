<?php

declare(strict_types=1);

namespace App\Modules\Plan\Application\Service;

use App\Modules\Plan\Application\Dto\LineAudioRow;
use App\Modules\Plan\Domain\Service\SpokenLines;
use App\Modules\Plan\Domain\ValueObject\VoiceCast;
use App\Modules\Shared\Domain\ValueObject\VoiceGender;

/**
 * The spoken files of some scenes, found by what they voice (DAY-UI-3): the partner's line in the
 * partner's voice, everything else in the learner's. A file of the other voice does not count — a
 * line read by the wrong person is not this line.
 */
final readonly class SceneAudioIndex
{
    /**
     * @param  array<string, LineAudioRow>  $rows  keyed `<scene>:<ref>:<voice key>`
     * @param  array<string, VoiceCast>  $casts  scene id → its two voices
     */
    public function __construct(
        private array $rows,
        private array $casts,
        private ?string $femaleKey,
        private ?string $maleKey,
    ) {}

    public static function empty(): self
    {
        return new self([], [], null, null);
    }

    /** The audio id of what `$ref` voices in scene `$sceneId`, or null — not voiced yet (the phone reads it). */
    public function idOf(string $sceneId, string $ref): ?string
    {
        $gender = ($this->casts[$sceneId] ?? VoiceCast::of(null))->genderOf(SpokenLines::speakerOf($ref));
        $key = $gender === VoiceGender::Female ? $this->femaleKey : $this->maleKey;

        return $key === null ? null : ($this->rows[$sceneId.':'.$ref.':'.$key] ?? null)?->id;
    }
}
