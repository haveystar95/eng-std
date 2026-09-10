<?php

declare(strict_types=1);

namespace App\Modules\Plan\Infrastructure\Job;

use App\Modules\Plan\Application\Command\SpeakSceneLines;
use App\Modules\Plan\Application\Command\SpeakSceneLinesHandler;
use App\Modules\Plan\Domain\ValueObject\PlanSceneId;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * The partner's lines of one scene, spoken by the language pack's voice. Retried on a transient
 * vendor error with what was already bought kept (the store is idempotent per line and voice).
 */
final class SpeakSceneLinesJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 600;

    /** @var list<int> */
    public array $backoff = [15, 60, 120];

    public function __construct(private readonly string $sceneId) {}

    public function handle(SpeakSceneLinesHandler $handler): void
    {
        $handler(new SpeakSceneLines(PlanSceneId::fromString($this->sceneId)));
    }

    public function failed(Throwable $e): void
    {
        Log::warning('SpeakSceneLinesJob failed; lines keep the system voice', ['scene_id' => $this->sceneId, 'error' => $e->getMessage()]);
    }
}
