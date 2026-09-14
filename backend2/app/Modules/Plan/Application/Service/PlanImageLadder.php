<?php

declare(strict_types=1);

namespace App\Modules\Plan\Application\Service;

use App\Modules\Plan\Application\Port\CheckCounters;
use App\Modules\Plan\Application\Port\PlanImageFinder;
use App\Modules\Plan\Domain\Entity\Plan;
use App\Modules\Plan\Domain\Entity\PlanScene;
use App\Modules\Plan\Domain\Entity\PlanTerm;
use App\Modules\Plan\Domain\Repository\PlanRepository;
use App\Modules\Plan\Domain\Repository\PlanTermRepository;
use App\Modules\Plan\Domain\Service\ImageQueries;
use App\Modules\Plan\Domain\Service\ImageTones;
use App\Modules\Plan\Domain\ValueObject\CheckAction;
use App\Modules\Plan\Domain\ValueObject\CheckMode;
use App\Modules\Plan\Domain\ValueObject\Finding;
use App\Modules\Plan\Domain\ValueObject\Image;

/**
 * ONE MISSING PHOTO, ASKED THE WHOLE LADDER (DAY-UI-2, находка PHONE-RUN-1 №4) — the one place the
 * photo job and `plan:images-backfill` both go through.
 *
 * The rungs are `ImageQueries`' and the first photo found is written into its own columns with its
 * tone (a conditional UPDATE — the aggregate is never saved from here, see the module README). When
 * every rung comes back empty the miss is counted as `image_missing` beside the checks, under the
 * prompt version that wrote the description, and a word is painted with its scene's tone instead —
 * which also marks it asked, so the job does not search it again after the next lesson.
 *
 * A transient vendor error propagates: the job retries, the backfill stops and says so.
 */
final readonly class PlanImageLadder
{
    public const MISSING = 'image_missing';

    public function __construct(
        private PlanImageFinder $images,
        private PlanRepository $plans,
        private PlanTermRepository $terms,
        private CheckCounters $counters,
    ) {}

    /** The scene's photo after the ladder — found and written now, or null. */
    public function sceneImage(Plan $plan, PlanScene $scene): ?Image
    {
        $found = $this->first(ImageQueries::forScene($scene, $plan->titles()));
        if ($found === null) {
            $this->countMissing($plan->planCall()?->promptVersion, "scene {$scene->order()} «{$scene->titleTarget()}»");

            return null;
        }

        // Another writer that came first owns the photo — and its copies.
        return $this->plans->attachSceneImage($scene->id(), $found) ? $found : null;
    }

    /** A word's or a chunk's photo: written when found, otherwise the card is painted with [$sceneTone]. */
    public function termImage(Plan $plan, PlanScene $scene, PlanTerm $term, ?string $sceneTone): void
    {
        $found = $this->first(ImageQueries::forTerm($term, $scene));
        if ($found !== null) {
            $this->terms->attachImage($term->id(), $found);

            return;
        }
        $this->countMissing($scene->lessonCall()?->promptVersion, "{$term->ref()} «{$term->textTarget()}»");
        $this->terms->markImageMissing($term->id(), ImageTones::first($sceneTone, $plan->coverImage()?->tone));
    }

    /** @param list<string> $queries */
    private function first(array $queries): ?Image
    {
        foreach ($queries as $query) {
            $found = $this->images->find($query);
            if ($found !== null) {
                return $found;
            }
        }

        return null;
    }

    private function countMissing(?string $promptVersion, string $what): void
    {
        $this->counters->record($promptVersion ?? 'unknown', [
            new Finding(self::MISSING, CheckMode::Observe, CheckAction::Counted, $what),
        ]);
    }
}
