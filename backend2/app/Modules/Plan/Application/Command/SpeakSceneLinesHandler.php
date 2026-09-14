<?php

declare(strict_types=1);

namespace App\Modules\Plan\Application\Command;

use App\Modules\Plan\Application\Port\LineAudioStore;
use App\Modules\Plan\Application\Port\LineSpeaker;
use App\Modules\Plan\Application\Port\SceneLocator;
use App\Modules\Plan\Domain\Assembly\CardPayloads;
use App\Modules\Plan\Domain\Repository\PlanRepository;

/**
 * Buys the scene's spoken lines once: the partner's line of every exchange and, for the day
 * window's «прослушать» (DAY-UI-2), every phrase — each under the reference the cards name its unit
 * by (`x3`, `p2`). The store is asked what this voice already has, and only the missing lines are
 * spoken. A vendor refusal skips the line; a transient error propagates so the job retries with
 * what was already stored kept.
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

        /** @var array<string, string> $lines line reference => text */
        $lines = [];
        foreach ($lesson->exchanges as $exchange) {
            // A repeated step (the model's slip, counted by `exchange_shape`) is one line in the
            // store; buying the second would be paying for a file that cannot be kept.
            $partner = $exchange->partner();
            $ref = CardPayloads::exchangeRef($exchange->step);
            if ($partner !== null && ! isset($lines[$ref])) {
                $lines[$ref] = $partner->textTarget;
            }
        }
        foreach ($lesson->phrases as $phrase) {
            $lines[$phrase->id] ??= $phrase->textTarget;
        }

        $have = $this->store->forScenes([$scene->id()->value], $voiceKey);
        foreach ($lines as $ref => $text) {
            if (isset($have[$scene->id()->value.':'.$ref]) || trim($text) === '') {
                continue;
            }
            $audio = $this->speaker->speak($text, $lang);
            if ($audio === null) {
                continue;
            }
            $this->store->put($scene->id(), $ref, $audio->voiceKey, $audio->format, $audio->bytes, $audio->durationMs, $audio->costUsd);
        }
    }
}
