<?php

declare(strict_types=1);

namespace App\Modules\Plan\Application\Exception;

use App\Modules\Plan\Application\Dto\VoiceBalance;
use RuntimeException;

/**
 * THE VOICE FUSE TRIPPED (TTS-2): less than the configured share of the vendor account's characters is left, so the
 * queue buys nothing more. Not an HTTP problem — nobody asked for this call: the job that meets it writes the letter to
 * the log and ends, the backfill command stops and says so. The day reads its lines with the phone's voice; after a
 * top-up `plan:speak-backfill` buys what is owed.
 */
final class VoiceFuseTripped extends RuntimeException
{
    private function __construct(string $message, public readonly VoiceBalance $balance) {
        parent::__construct($message);
    }

    public static function at(VoiceBalance $balance, float $floorShare): self
    {
        return new self(sprintf(
            'voice fuse: %d of %d credits left (%.1f%% < %d%%, by the %s)',
            $balance->remaining(),
            $balance->limit,
            $balance->remainingShare() * 100,
            (int) round($floorShare * 100),
            $balance->source === VoiceBalance::VENDOR ? "vendor's account" : "app's own count this month",
        ), $balance);
    }
}
