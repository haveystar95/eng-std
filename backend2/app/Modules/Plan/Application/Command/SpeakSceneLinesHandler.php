<?php

declare(strict_types=1);

namespace App\Modules\Plan\Application\Command;

use App\Modules\Plan\Application\Port\LineAudioStore;
use App\Modules\Plan\Application\Port\LineSpeaker;
use App\Modules\Plan\Application\Port\SceneLocator;
use App\Modules\Plan\Domain\Repository\PlanRepository;

/**
 * Buys the partner's lines once: the store is asked what this voice already has, and only the
 * missing steps are spoken. A vendor refusal skips the line; a transient error propagates so the
 * job retries with what was already stored kept.
 */
final readonly class SpeakSceneLinesHandler
{
    public function __construct(
        private SceneLocator $scenes,
        private PlanRepository $plans,
        private LineSpeaker $speaker,
        private LineAudioStore $store,
    ) {}

    public function __invoke(SpeakSceneLines $command): void
    {
        $planId = $this->scenes->planIdOf($command->sceneId);
        $plan = $planId === null ? null : $this->plans->findById($planId);
        if ($plan === null) {
            return;
        }
        $scene = $plan->scene($command->sceneId);
        $lesson = $scene->lesson();
        if ($lesson === null) {
            return;
        }

        $lang = $plan->targetLang()->value;
        $voiceKey = $this->speaker->voiceKeyFor($lang);
        if ($voiceKey === null) {
            return;
        }
        $have = $this->store->forScenes([$scene->id()->value], $voiceKey);

        foreach ($lesson->exchanges as $exchange) {
            $partner = $exchange->partner();
            $key = $scene->id()->value.':'.$exchange->step;
            // A repeated step (the model's slip, counted by `exchange_shape`) is one line in the
            // store; buying the second would be paying for a file that cannot be kept.
            if ($partner === null || isset($have[$key])) {
                continue;
            }
            $have[$key] = true;
            $audio = $this->speaker->speak($partner->textTarget, $lang);
            if ($audio === null) {
                continue;
            }
            $this->store->put($scene->id(), $exchange->step, $audio->voiceKey, $audio->format, $audio->bytes, $audio->durationMs, $audio->costUsd);
        }
    }
}
