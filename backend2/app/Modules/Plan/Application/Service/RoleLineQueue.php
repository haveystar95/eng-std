<?php

declare(strict_types=1);

namespace App\Modules\Plan\Application\Service;

use App\Modules\Plan\Application\Dto\RoleLines;
use App\Modules\Plan\Application\Port\LineAudioStore;
use App\Modules\Plan\Application\Port\LineSpeaker;
use App\Modules\Plan\Application\Port\SceneLocator;
use App\Modules\Plan\Domain\Assembly\CardPayloads;
use App\Modules\Plan\Domain\Repository\PlanRepository;
use App\Modules\Plan\Domain\ValueObject\PlanSceneId;

/**
 * WHAT THE SERVER'S VOICE STILL OWES A SCENE — the partner's line of every exchange, and nothing else.
 *
 * Canon (owner, closing DAY-UI-2): the premium voice is the ROLE's lines; the learner's phrases and
 * the words are the phone's voice. So the queue is the partner's lines the store does not have for
 * this voice yet — the job buys them, `plan:speak-backfill` buys and counts them. Null when there is
 * nothing to ask: no plan, no lesson, or no voice for the language (speech off).
 */
final readonly class RoleLineQueue
{
    public function __construct(
        private SceneLocator $scenes,
        private PlanRepository $plans,
        private LineSpeaker $speaker,
        private LineAudioStore $store,
    ) {}

    public function owed(PlanSceneId $sceneId): ?RoleLines
    {
        $planId = $this->scenes->planIdOf($sceneId);
        $plan = $planId === null ? null : $this->plans->findById($planId);
        $lesson = $plan?->scene($sceneId)->lesson();
        if ($plan === null || $lesson === null) {
            return null;
        }
        $lang = $plan->targetLang()->value;
        $voiceKey = $this->speaker->voiceKeyFor($lang);
        if ($voiceKey === null) {
            return null;
        }

        $have = $this->store->forScenes([$sceneId->value], $voiceKey);
        $lines = [];
        foreach ($lesson->exchanges as $exchange) {
            $partner = $exchange->partner();
            $ref = CardPayloads::exchangeRef($exchange->step);
            // A repeated step (the model's slip, counted by `exchange_shape`) is one line in the
            // store; buying the second would be paying for a file that cannot be kept.
            if ($partner === null || isset($lines[$ref]) || isset($have[$sceneId->value.':'.$ref]) || trim($partner->textTarget) === '') {
                continue;
            }
            $lines[$ref] = $partner->textTarget;
        }

        return new RoleLines($lang, $lines);
    }
}
