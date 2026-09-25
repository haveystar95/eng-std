<?php

declare(strict_types=1);

namespace App\Modules\Plan\Application\Service;

use App\Modules\Plan\Application\Dto\LineAudioRow;
use App\Modules\Plan\Domain\Service\SpokenLines;

/**
 * The spoken files of some scenes, found by what they voice (DAY-UI-3, TTS-2): the partner's line in the voice the
 * partner has in that scene — the voice fixed for it (наряд FIX-4c §1) —, everything else in the learner's. A file of
 * another voice does not count — a line read by the wrong person is not this line.
 */
final readonly class SceneAudioIndex
{
    /** The keys of a scene the index was not built for: the default cast, the pack's first voices. */
    public const DEFAULT_CAST = '';

    /**
     * @param  array<string, LineAudioRow>  $rows  keyed `<scene>:<ref>:<voice key>`
     * @param  array<string, array<string, string|null>>  $keys  scene id → speaker → the voice key its lines are filed
     *   under there; {@see DEFAULT_CAST} — for a scene not among them
     */
    public function __construct(
        private array $rows,
        private array $keys,
    ) {}

    public static function empty(): self
    {
        return new self([], []);
    }

    /** The audio id of what `$ref` voices in scene `$sceneId`, or null — not voiced yet (the phone reads it). */
    public function idOf(string $sceneId, string $ref): ?string
    {
        return $this->rowOf($sceneId, $ref)?->id;
    }

    /**
     * The stored file of what `$ref` voices in scene `$sceneId`, in its speaker's voice there — its id and its length
     * (наряд SESSION-1a, разд. 5: every sound of a card says how long it plays); null while it is not voiced.
     */
    public function rowOf(string $sceneId, string $ref): ?LineAudioRow
    {
        $speaker = SpokenLines::speakerOf($ref);
        $key = ($this->keys[$sceneId] ?? $this->keys[self::DEFAULT_CAST] ?? [])[$speaker->value] ?? null;

        return $key === null ? null : ($this->rows[$sceneId.':'.$ref.':'.$key] ?? null);
    }
}
