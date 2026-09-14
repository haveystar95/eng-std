<?php

declare(strict_types=1);

namespace App\Modules\Plan\Infrastructure\Job;

use App\Modules\Plan\Application\Command\SpeakSceneLines;
use App\Modules\Plan\Application\Command\SpeakSceneLinesHandler;
use App\Modules\Plan\Domain\ValueObject\PlanSceneId;
use DateTimeImmutable;
use DateTimeInterface;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * The partner's lines of one scene, spoken by the language pack's voice (the role's lines only — the
 * learner's phrases and the words are the phone's voice). Retried on a transient vendor error with
 * what was already bought kept (the store is idempotent per line and voice).
 *
 * The vendor's limit is PER MINUTE as well as per day (Gemini TTS: 10 requests a minute and 100 a day
 * per model, seen 14.09): a scene is eight lines, two lessons written at once are sixteen. Three tries
 * gave up with half the lines bought and nothing asked again, so the job retries a minute apart for
 * half an hour instead: every attempt buys only what is missing.
 */
final class SpeakSceneLinesJob implements ShouldQueue
{
    use Queueable;

    public int $timeout = 600;

    /** @var list<int> the last value repeats for every later attempt */
    public array $backoff = [15, 60];

    public function __construct(private readonly string $sceneId) {}

    public function retryUntil(): DateTimeInterface
    {
        return new DateTimeImmutable('+30 minutes');
    }

    public function handle(SpeakSceneLinesHandler $handler): void
    {
        $handler(new SpeakSceneLines(PlanSceneId::fromString($this->sceneId)));
    }

    public function failed(Throwable $e): void
    {
        Log::warning('SpeakSceneLinesJob failed; lines keep the system voice', ['scene_id' => $this->sceneId, 'error' => $e->getMessage()]);
    }
}
