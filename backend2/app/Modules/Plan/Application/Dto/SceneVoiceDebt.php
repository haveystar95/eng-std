<?php

declare(strict_types=1);

namespace App\Modules\Plan\Application\Dto;

use App\Modules\Plan\Domain\ValueObject\VoiceCast;

/**
 * What a scene's voice still owes (DAY-UI-3): its cast (and whether the cast was decided just now and
 * must be stored), the calls that buy the rest, and how many lines of each kind are not voiced yet.
 */
final readonly class SceneVoiceDebt
{
    /** @param list<VoiceBatch> $batches in the order they are bought: the dialogue, the phrases, the words */
    public function __construct(
        public string $lang,
        public VoiceCast $cast,
        public bool $castIsNew,
        public array $batches,
        public int $partnerLines,
        public int $learnerLines,
        public int $phrases,
        public int $words,
    ) {}

    public function isSettled(): bool
    {
        return $this->batches === [];
    }
}
