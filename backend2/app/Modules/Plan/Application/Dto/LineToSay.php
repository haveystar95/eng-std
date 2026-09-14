<?php

declare(strict_types=1);

namespace App\Modules\Plan\Application\Dto;

use App\Modules\Shared\Domain\ValueObject\VoiceGender;

/** One line of a script the voice is asked for: the file it becomes (`x3`, `x3b`, `p2`, `v5`), its text, its voice. */
final readonly class LineToSay
{
    public function __construct(
        public string $ref,
        public string $text,
        public VoiceGender $voice,
    ) {}
}
