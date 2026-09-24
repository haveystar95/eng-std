<?php

declare(strict_types=1);

namespace App\Modules\Plan\Application\Dto\Inspection;

use DateTimeImmutable;

/**
 * One line of a talk as stored. On a learner's `said` line `textTarget` is what the phone's recognition heard and sent —
 * the only text of recognition the server ever receives. Since наряд FIX-4: the scene the line was said in, the greeting
 * or goodbye of a scene, and the constructions a move said almost.
 */
final readonly class InspectedTurn
{
    /**
     * @param  list<string>  $phrasesUsed  the constructions the line said, scene-qualified `<scene>:<ref>`
     * @param  list<string>  $phrasesAlmost  the ones it said almost
     */
    public function __construct(
        public string $id,
        public int $index,
        public string $kind,
        public string $speaker,
        public ?string $textTarget,
        public ?string $textNative,
        public bool $hasAudio,
        public ?int $audioDurationMs,
        public ?string $audioVoiceKey,
        public ?int $audioCharacters,
        public ?int $audioCredits,
        public ?string $audioCostUsd,
        public ?bool $understood,
        public array $phrasesUsed,
        public ?bool $offTopic,
        public ?string $checkpointDone,
        public ?string $hintNative,
        public ?string $model,
        public ?string $promptVersion,
        public ?int $tokensIn,
        public ?int $tokensOut,
        public string $modelCostUsd,
        public string $speechCostUsd,
        public string $costUsd,
        public ?int $modelLatencyMs,
        public ?int $speechLatencyMs,
        public ?int $latencyMs,
        public ?string $opensTarget,
        public DateTimeImmutable $createdAt,
        public ?string $sceneId = null,
        public ?string $sceneEvent = null,
        public array $phrasesAlmost = [],
    ) {}
}
