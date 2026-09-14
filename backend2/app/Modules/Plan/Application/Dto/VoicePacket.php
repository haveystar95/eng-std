<?php

declare(strict_types=1);

namespace App\Modules\Plan\Application\Dto;

/**
 * ONE VENDOR CALL OF THE VOICE BACKFILL (DAY-UI-3): one scene's whole dialogue, or up to twelve phrases or words of
 * several scenes read in one voice.
 */
final readonly class VoicePacket
{
    public const DIALOGUE = 'dialogue';
    public const PHRASES = 'phrases';
    public const WORDS = 'words';

    /** @param list<VoicePacketLine> $lines */
    public function __construct(
        public string $kind,
        public string $lang,
        public array $lines,
    ) {}
}
