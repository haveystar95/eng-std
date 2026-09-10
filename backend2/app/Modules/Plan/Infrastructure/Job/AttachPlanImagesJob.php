<?php

declare(strict_types=1);

namespace App\Modules\Plan\Infrastructure\Job;

use App\Modules\Observability\Application\Support\OutboundCallContext;
use App\Modules\Plan\Application\Command\AttachPlanImages;
use App\Modules\Plan\Application\Command\AttachPlanImagesHandler;
use App\Modules\Plan\Domain\ValueObject\PlanId;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Photos for the plan — the same job the collections' photos run as: best effort, retried on a
 * transient search error, and never a reason for a day not to open.
 */
final class AttachPlanImagesJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 180;

    /** @var list<int> */
    public array $backoff = [10, 30, 60];

    public function __construct(private readonly string $planId) {}

    public function handle(AttachPlanImagesHandler $handler, OutboundCallContext $context): void
    {
        $context->run('images', null, fn () => $handler(new AttachPlanImages(PlanId::fromString($this->planId))));
    }

    public function failed(Throwable $e): void
    {
        Log::warning('AttachPlanImagesJob failed; plan keeps null images', ['plan_id' => $this->planId, 'error' => $e->getMessage()]);
    }
}
