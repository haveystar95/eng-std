<?php

declare(strict_types=1);

namespace App\Modules\Plan\Application\Dto;

use App\Modules\Plan\Domain\ValueObject\Speaker;
use App\Modules\Shared\Domain\ValueObject\VoiceGender;

/**
 * One line the voice is asked for: its ref — the file it becomes (`x3`, `x3b`, `p2`, `p2.f1`, `v5`) — its text, and
 * whose voice says it: the speaker and the gender that speaker has in this scene (TTS-2: the pack picks a voice by
 * both).
 */
final readonly class LineToSay
{
    public function __construct(
        public string $ref,
        public string $text,
        public Speaker $speaker,
        public VoiceGender $gender,
    ) {}
}
