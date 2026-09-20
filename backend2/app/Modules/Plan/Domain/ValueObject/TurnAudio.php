<?php

declare(strict_types=1);

namespace App\Modules\Plan\Domain\ValueObject;

/**
 * THE SOUND OF ONE AGENT LINE (наряд CONV-1), bought for this turn alone: the file on the day's own
 * disk, how long it plays, whose voice read it — and its bill, in the three units every voice
 * purchase is counted in (`characters` · `credits` · `costUsd`) plus the vendor's id of the call.
 *
 * A day's line is filed under (scene, ref, voice) and lives as long as the scene does; a turn's is
 * written once, for a line nobody will ever say again, and is named by the turn itself.
 */
final readonly class TurnAudio
{
    public function __construct(
        public string $path,
        public string $format,
        public ?int $durationMs,
        public string $voiceKey,
        public int $characters,
        public int $credits,
        public string $costUsd,
        public ?string $requestId,
    ) {}
}
