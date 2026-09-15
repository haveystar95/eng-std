<?php

declare(strict_types=1);

namespace App\Modules\Plan\Application\Dto;

use App\Modules\Plan\Domain\ValueObject\VoiceCast;

/**
 * What a scene's voice still owes (DAY-UI-3, TTS-2): every line not voiced yet, in the order they are bought — the
 * dialogue's lines, the phrases, the fillers (a phrase said with another of its fillers), the words — and how many of
 * each kind.
 */
final readonly class SceneVoiceDebt
{
    /** @param list<LineToSay> $lines each said on its own call */
    public function __construct(
        public string $lang,
        public VoiceCast $cast,
        public array $lines,
        public int $partnerLines,
        public int $learnerLines,
        public int $phrases,
        public int $fillers,
        public int $words,
    ) {}

    public function isSettled(): bool
    {
        return $this->lines === [];
    }
}
