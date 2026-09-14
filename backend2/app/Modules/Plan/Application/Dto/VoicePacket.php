<?php

declare(strict_types=1);

namespace App\Modules\Plan\Application\Dto;

use App\Modules\Shared\Domain\ValueObject\VoiceGender;

/**
 * ONE VENDOR CALL OF THE VOICE BACKFILL (DAY-UI-3): one scene's whole dialogue, or up to twelve phrases or words of
 * several scenes read in one voice. [$casts] — the scenes whose two voices are decided now; they are stored before
 * the call, so the lines bought are found under the voice their scene reads them in.
 */
final readonly class VoicePacket
{
    public const DIALOGUE = 'dialogue';
    public const PHRASES = 'phrases';
    public const WORDS = 'words';

    /**
     * @param  list<VoicePacketLine>  $lines
     * @param  array<string, VoiceGender>  $casts  scene id → the partner's voice to store
     */
    public function __construct(
        public string $kind,
        public string $lang,
        public array $lines,
        public array $casts,
    ) {}
}
