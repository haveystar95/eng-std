<?php

declare(strict_types=1);

namespace App\Modules\Plan\Infrastructure\Console;

use App\Modules\Generation\Application\Port\TransientImageSearchError;
use App\Modules\Observability\Application\Support\OutboundCallContext;
use App\Modules\Plan\Application\Command\BackfillSceneImages;
use App\Modules\Plan\Application\Command\BackfillSceneImagesHandler;
use App\Modules\Plan\Application\Command\FillMissingImages;
use App\Modules\Plan\Application\Command\FillMissingImagesHandler;
use App\Modules\Plan\Application\Dto\MissingImageCounts;
use App\Modules\Plan\Application\Dto\SceneImageBackfillReport;
use App\Modules\Plan\Application\Port\SceneLocator;
use App\Modules\Plan\Domain\Repository\PlanTermRepository;
use App\Modules\Plan\Domain\ValueObject\PlanId;
use App\Modules\Shared\Domain\ValueObject\Ulid;
use Illuminate\Console\Command;

/**
 * `plan:images-backfill {--plan=} {--requery}` — the photos existing plans still lack, and what scene
 * photos stored before PLAN-UI-3 lack. Two steps, both idempotent and safe to run again after a rate
 * limit:
 *
 * 1. (DAY-UI-2, the DAY-UI-3 ladder) every scene, word and chunk without a photo is asked the search
 *    ladder (`FillMissingImagesHandler`), and the run prints «было пусто / стало»; with `--requery`
 *    the words whose photo the bare word found, and the words repeating a picture their day already
 *    shows, are asked again and a new answer replaces the photo;
 * 2. (PLAN-UI-3) scene photos get their tone and their two square copies.
 *
 * Every vendor call is labelled `images` in the outbound log. Writes photo columns of
 * `plan_scenes` / `plan_terms` and files on `plan.image_disk` — take the database backup first, as
 * for any write to the dev database.
 */
final class PlanImagesBackfillCommand extends Command
{
    protected $signature = 'plan:images-backfill {--plan= : only this plan id} {--requery : re-ask the words whose photo the bare word found or their day already shows}';

    protected $description = 'Find the photos plans still lack (the search ladder), then fill scene photo tones and pre-fetch their 112/448 square copies';

    public function handle(
        FillMissingImagesHandler $fill,
        BackfillSceneImagesHandler $handler,
        SceneLocator $scenes,
        PlanTermRepository $terms,
        OutboundCallContext $context,
    ): int {
        $option = $this->option('plan');
        if (is_string($option) && $option !== '' && ! Ulid::isValid($option)) {
            $this->error("Not a plan id: {$option}");

            return self::FAILURE;
        }
        $plan = is_string($option) && $option !== '' ? PlanId::fromString($option) : null;
        $requery = $this->option('requery') === true;

        $before = $scenes->missingImageCounts($plan);
        $bare = $requery ? array_sum(array_map('count', $terms->photographedWithoutPrompt($plan))) : 0;
        $repeats = $requery ? array_sum(array_map('count', $terms->repeatingDayPhotos($plan))) : 0;
        try {
            $context->run('images', null, fn () => $fill(new FillMissingImages($plan, $requery)));
        } catch (TransientImageSearchError $e) {
            $this->warn('The photo search stopped on a vendor limit — run the command again later: '.$e->getMessage());
        }
        $after = $scenes->missingImageCounts($plan);
        $this->info('Without a photo — before: '.self::counts($before).' · after: '.self::counts($after));
        if ($requery) {
            $this->info("Asked the new ladder — words and chunks photographed by the bare word: {$bare}, repeating a picture of their day: {$repeats}");
        }

        /** @var SceneImageBackfillReport $report */
        $report = $context->run('images', null, fn (): SceneImageBackfillReport => $handler(new BackfillSceneImages($plan)));

        $this->info(sprintf(
            'Scenes with a photo: %d · tones written: %d, still without a tone: %d · copies fetched: %d, still missing: %d',
            $report->scenes, $report->tonesWritten, $report->tonesMissing, $report->copiesFetched, $report->copiesMissing,
        ));

        return self::SUCCESS;
    }

    private static function counts(MissingImageCounts $counts): string
    {
        return "scenes {$counts->scenes}, words and chunks {$counts->terms}";
    }
}
