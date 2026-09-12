<?php

declare(strict_types=1);

namespace App\Modules\Plan\Infrastructure\Console;

use App\Modules\Observability\Application\Support\OutboundCallContext;
use App\Modules\Plan\Application\Command\BackfillSceneImages;
use App\Modules\Plan\Application\Command\BackfillSceneImagesHandler;
use App\Modules\Plan\Application\Dto\SceneImageBackfillReport;
use App\Modules\Plan\Domain\ValueObject\PlanId;
use App\Modules\Shared\Domain\ValueObject\Ulid;
use Illuminate\Console\Command;

/**
 * `plan:images-backfill {--plan=}` — the tone and the two square copies for scene photos stored
 * before PLAN-UI-3. Idempotent; safe to run again after a rate limit. Every vendor call is
 * labelled `images` in the outbound log. Writes one column (`plan_scenes.image_tone`) and files on
 * `plan.image_disk` — take the database backup first, as for any write to the dev database.
 */
final class PlanImagesBackfillCommand extends Command
{
    protected $signature = 'plan:images-backfill {--plan= : only this plan id}';

    protected $description = 'Fill scene photo tones (Pexels avg_color) and pre-fetch the 112/448 square copies';

    public function handle(BackfillSceneImagesHandler $handler, OutboundCallContext $context): int
    {
        $plan = $this->option('plan');
        if (is_string($plan) && $plan !== '' && ! Ulid::isValid($plan)) {
            $this->error("Not a plan id: {$plan}");

            return self::FAILURE;
        }

        /** @var SceneImageBackfillReport $report */
        $report = $context->run('images', null, fn (): SceneImageBackfillReport => $handler(new BackfillSceneImages(
            is_string($plan) && $plan !== '' ? PlanId::fromString($plan) : null,
        )));

        $this->info(sprintf(
            'Scenes with a photo: %d · tones written: %d, still without a tone: %d · copies fetched: %d, still missing: %d',
            $report->scenes, $report->tonesWritten, $report->tonesMissing, $report->copiesFetched, $report->copiesMissing,
        ));

        return self::SUCCESS;
    }
}
