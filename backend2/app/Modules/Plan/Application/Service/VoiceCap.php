<?php

declare(strict_types=1);

namespace App\Modules\Plan\Application\Service;

use App\Modules\Plan\Application\Dto\SceneVoiceDebt;
use App\Modules\Plan\Application\Exception\VoiceCapReached;
use App\Modules\Plan\Application\Port\LineSpeaker;

/**
 * THE CREDITS CAP OF ONE RUN (TTS-2, the architect's decision): no run of purchases — one voice job, one
 * `plan:speak-backfill` — buys more than `creditsCap` of the vendor's credits.
 *
 * Checked before a scene is bought, never after it: what the run has bought so far plus what the scene would cost (the
 * vendor's rate for its lines' characters, {@see LineSpeaker::creditsFor()}) must fit under the cap, or nothing of the
 * scene is bought — {@see VoiceCapReached}. A scene is bought whole or not at all.
 */
final readonly class VoiceCap
{
    public function __construct(
        private LineSpeaker $speaker,
        private int $creditsCap,
    ) {}

    /** @throws VoiceCapReached */
    public function assertRoom(SceneVoiceDebt $debt, int $spentThisRun): void
    {
        $scene = $this->speaker->creditsFor($debt->lang, $debt->lines);
        if ($spentThisRun + $scene > $this->creditsCap) {
            throw VoiceCapReached::before($scene, $spentThisRun, $this->creditsCap);
        }
    }
}
