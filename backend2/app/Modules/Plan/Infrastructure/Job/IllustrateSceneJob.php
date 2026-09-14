<?php

declare(strict_types=1);

namespace App\Modules\Plan\Infrastructure\Job;

use App\Modules\Observability\Application\Support\OutboundCallContext;
use App\Modules\Plan\Application\Command\FinishIllustration;
use App\Modules\Plan\Application\Command\FinishIllustrationHandler;
use App\Modules\Plan\Application\Command\IllustrateScene;
use App\Modules\Plan\Application\Command\IllustrateSceneHandler;
use App\Modules\Plan\Domain\ValueObject\PlanSceneId;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * The day's pictures right after its lesson (DAY-UI-3) — retried on a transient search error with what
 * was already written kept. The day waits for this job, so this job cannot leave it waiting: when the
 * last try fails, the day is made ready without the photos it did not get (their slots keep tones).
 */
final class IllustrateSceneJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 180;

    /** @var list<int> */
    public array $backoff = [5, 20];

    public function __construct(private readonly string $sceneId) {}

    public function handle(IllustrateSceneHandler $handler, OutboundCallContext $context): void
    {
        $context->run('images', null, fn () => $handler(new IllustrateScene(PlanSceneId::fromString($this->sceneId))));
    }

    public function failed(Throwable $e): void
    {
        Log::warning('IllustrateSceneJob failed; the day is ready with the photos it has', ['scene_id' => $this->sceneId, 'error' => $e->getMessage()]);
        app(FinishIllustrationHandler::class)(new FinishIllustration(PlanSceneId::fromString($this->sceneId)));
    }
}
