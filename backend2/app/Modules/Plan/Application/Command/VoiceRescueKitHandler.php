<?php

declare(strict_types=1);

namespace App\Modules\Plan\Application\Command;

use App\Modules\Plan\Application\Dto\SpokenAudio;
use App\Modules\Plan\Application\Exception\VoiceCapReached;
use App\Modules\Plan\Application\Exception\VoiceFuseTripped;
use App\Modules\Plan\Application\Port\LineSpeaker;
use App\Modules\Plan\Application\Service\RescueKits;
use App\Modules\Plan\Application\Service\VoiceCap;
use App\Modules\Plan\Application\Service\VoiceCasts;
use App\Modules\Plan\Application\Service\VoiceFuse;
use App\Modules\Plan\Domain\Repository\PlanRepository;

/**
 * Buys what a plan's rescue kit still owes in its learner's voice (наряд LANG-1b §2): the lines of the target's kit whose
 * file for (target, gender, voice, line) is not there ({@see RescueKits::owed()}) — every line on a call of its own, kept as
 * soon as it is bought. Nothing owed — nothing asked: a kit bought once for a target and a gender serves every later plan
 * of them.
 *
 * The same two checks as a scene's voice come first ({@see VoiceSceneHandler}): the credits cap of the run, then the fuse.
 * Queued when a lesson of the plan is accepted, beside the scene's voice — the kit is handed out with the plan, and its
 * sound follows the first day; never on a database voiced only by name (the e2e stand).
 */
final readonly class VoiceRescueKitHandler
{
    public function __construct(
        private PlanRepository $plans,
        private VoiceCasts $casts,
        private RescueKits $kits,
        private LineSpeaker $speaker,
        private VoiceCap $cap,
        private VoiceFuse $fuse,
    ) {}

    /** @throws VoiceCapReached|VoiceFuseTripped */
    public function __invoke(VoiceRescueKit $command): void
    {
        $plan = $this->plans->findById($command->planId);
        if ($plan === null) {
            return;
        }
        $target = $plan->targetLang()->value;
        $learner = $this->casts->learnerOf($plan->userId());
        $owed = $this->kits->owed($target, $learner);
        if ($owed === []) {
            return;
        }
        $this->cap->assertRoomFor($target, $owed, 0);
        $this->fuse->assertOpen();

        $byRef = [];
        foreach ($owed as $line) {
            $byRef[$line->ref] = $line;
        }
        $this->speaker->sayEach($target, $owed, function (string $ref, SpokenAudio $audio) use ($byRef, $target, $learner): void {
            if (isset($byRef[$ref])) {
                $this->kits->keep($target, $learner, $byRef[$ref], $audio);
            }
        });
    }
}
