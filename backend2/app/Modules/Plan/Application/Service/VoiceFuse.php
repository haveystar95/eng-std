<?php

declare(strict_types=1);

namespace App\Modules\Plan\Application\Service;

use App\Modules\Plan\Application\Dto\VoiceBalance;
use App\Modules\Plan\Application\Exception\VoiceFuseTripped;
use App\Modules\Plan\Application\Port\LineAudioStore;
use App\Modules\Plan\Application\Port\LineSpeaker;
use App\Modules\Shared\Domain\Service\Clock;
use DateTimeZone;

/**
 * THE VOICE FUSE (TTS-2): before a scene is voiced, is there still enough of the vendor account left?
 *
 * The balance is the vendor's own count (`/v1/user/subscription` — credits used against the plan's limit) when it
 * answers; when it does not, this app's own count — the credits debited for every line stored since the start of the
 * month in UTC — against the configured plan size. Less than `floorShare` of the limit left (10 %) — the fuse
 * trips: {@see VoiceFuseTripped}, and nothing is bought. Checked per scene, not per day: a check is one free request,
 * and a whole scene is the most that can go over.
 */
final readonly class VoiceFuse
{
    public function __construct(
        private LineSpeaker $speaker,
        private LineAudioStore $store,
        private Clock $clock,
        private int $monthlyCredits,
        private float $floorShare = 0.10,
    ) {}

    public function balance(): VoiceBalance
    {
        $vendor = $this->speaker->balance();
        if ($vendor !== null) {
            return $vendor;
        }
        $monthStart = $this->clock->now()->setTimezone(new DateTimeZone('UTC'))->modify('first day of this month')->setTime(0, 0);

        return new VoiceBalance($this->store->creditsSince($monthStart), $this->monthlyCredits, VoiceBalance::COUNTER);
    }

    /** @throws VoiceFuseTripped */
    public function assertOpen(): VoiceBalance
    {
        $balance = $this->balance();
        if ($balance->remainingShare() < $this->floorShare) {
            throw VoiceFuseTripped::at($balance, $this->floorShare);
        }

        return $balance;
    }
}
