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
use App\Modules\Plan\Domain\ValueObject\ImageQuery;
use App\Modules\Plan\Domain\ValueObject\PlanSceneId;

/**
 * MISSING PHOTOS, EVERY LADDER CLIMBED AT ONCE (DAY-UI-2, parallel since DAY-UI-3) — the one place
 * the photo jobs and `plan:images-backfill` go through.
 *
 * The rungs are `ImageQueries`'. A rung is asked for every photo still missing in ONE batch of the
 * finder — six requests on the wire at a time — and only what that rung did not find climbs to the
 * next: a day of eight words is two or three batches, not twenty-four searches in a row. The first
 * photo found is written into its own columns with its tone (a conditional UPDATE — the aggregate
 * is never saved from here, see the module README). When every rung comes back empty the miss is
 * counted as `image_missing` beside the checks, under the prompt version that wrote the description,
 * and a word is painted with its scene's tone instead — which also marks it asked.
 *
 * A transient vendor error propagates: the job retries, the backfill stops and says so.
 */
final readonly class PlanImageLadder
{
    public const MISSING = 'image_missing';

    /** Re-asks, one batch each, of the words whose photo repeats one the day already shows. */
    private const REPEAT_ROUNDS = 2;

    public function __construct(
        private PlanImageFinder $images,
        private PlanRepository $plans,
        private PlanTermRepository $terms,
        private CheckCounters $counters,
    ) {}

    /**
     * The photos of the scenes that lack one, found and written now.
     *
     * @param  list<PlanScene>  $scenes
     * @return array<string, Image|null> scene id → its photo now: found, already there, or null
     */
    public function scenePhotos(Plan $plan, array $scenes): array
    {
        $out = [];
        $ladders = [];
        foreach ($scenes as $scene) {
            $out[$scene->id()->value] = $scene->image();
            if ($scene->image() === null) {
                $ladders[$scene->id()->value] = ImageQueries::forScene($scene, $plan->titles());
            }
        }
        foreach ($this->climb($ladders) as $sceneId => $found) {
            $scene = $plan->scene(PlanSceneId::fromString($sceneId));
            if ($found === null) {
                $this->countMissing($plan->planCall()?->promptVersion, "scene {$scene->order()} «{$scene->titleTarget()}»");

                continue;
            }
            // Another writer that came first owns the photo — and its copies.
            $out[$sceneId] = $this->plans->attachSceneImage($scene->id(), $found) ? $found : $out[$sceneId];
        }

        return $out;
    }

    /**
     * The photos of these words and chunks: written when found, otherwise the card is painted with
     * [$sceneTone]. [$replace] writes over a photo the term already has — the backfill's re-ask of
     * photos found by the bare word before DAY-UI-3.
     *
     * @param  list<PlanTerm>  $terms
     * @return int how many photos were found
     */
    public function termPhotos(Plan $plan, PlanScene $scene, array $terms, ?string $sceneTone, bool $replace = false): int
    {
        $ladders = [];
        $byId = [];
        foreach ($terms as $term) {
            $ladders[$term->id()->value] = ImageQueries::forTerm($term, $scene);
            $byId[$term->id()->value] = $term;
        }

        // The pictures the day already shows: its plate and the words that are not asked now.
        $taken = $scene->image() === null ? [] : [$scene->image()->url => true];
        foreach ($this->terms->forScene($scene->id()) as $sibling) {
            if (! isset($byId[$sibling->id()->value]) && $sibling->image() !== null) {
                $taken[$sibling->image()->url] = true;
            }
        }

        $found = 0;
        foreach ($this->unrepeated($this->climbWithQueries($ladders), $taken) as $termId => $image) {
            $term = $byId[$termId];
            if ($image !== null) {
                if ($replace) {
                    $this->terms->replaceImage($term->id(), $image);
                } else {
                    $this->terms->attachImage($term->id(), $image);
                }
                $found++;

                continue;
            }
            if ($replace && $term->image() !== null) {
                continue;
            }
            $this->countMissing($scene->lessonCall()?->promptVersion, "{$term->ref()} «{$term->textTarget()}»");
            $this->terms->markImageMissing($term->id(), ImageTones::first($sceneTone, $plan->coverImage()?->tone));
        }

        return $found;
    }

    /**
     * Every ladder, rung by rung: one batch per rung for all that is still missing.
     *
     * @param  array<string, list<ImageQuery>>  $ladders
     * @return array<string, array{image: Image|null, query: ImageQuery|null}>
     */
    private function climbWithQueries(array $ladders): array
    {
        $found = array_fill_keys(array_map('strval', array_keys($ladders)), ['image' => null, 'query' => null]);
        $missing = $ladders;
        for ($rung = 0; $missing !== []; $rung++) {
            $asked = [];
            foreach ($missing as $key => $queries) {
                if (isset($queries[$rung])) {
                    $asked[(string) $key] = $queries[$rung];
                } else {
                    unset($missing[$key]);
                }
            }
            if ($asked === []) {
                break;
            }
            $answers = $this->images->findMany(array_values($asked));
            foreach (array_keys($asked) as $i => $key) {
                if (($answers[$i] ?? null) !== null) {
                    $found[$key] = ['image' => $answers[$i], 'query' => $asked[$key]];
                    unset($missing[$key]);
                }
            }
        }

        return $found;
    }

    /**
     * @param  array<string, list<ImageQuery>>  $ladders
     * @return array<string, Image|null>
     */
    private function climb(array $ladders): array
    {
        return array_map(static fn (array $f): ?Image => $f['image'], $this->climbWithQueries($ladders));
    }

    /**
     * A DAY DOES NOT SHOW ONE PICTURE TWICE (DAY-UI-3, live check 14.09). The vendor answers «appointment,
     * doctor's office» and «dizzy, doctor's office» with the same first photo, and the day's plate is
     * often a theme's first photo too: a word whose photo repeats the plate or an earlier word asks its
     * own query again for the next page — twice at most, one batch each — and keeps the repeat only when
     * nothing new comes.
     *
     * @param  array<string, array{image: Image|null, query: ImageQuery|null}>  $climbed
     * @param  array<string, true>  $taken  photo urls the day already shows
     * @return array<string, Image|null>
     */
    private function unrepeated(array $climbed, array $taken): array
    {
        $images = array_map(static fn (array $f): ?Image => $f['image'], $climbed);
        for ($round = 1; $round <= self::REPEAT_ROUNDS; $round++) {
            $seen = $taken;
            $again = [];
            foreach ($images as $key => $image) {
                if ($image === null) {
                    continue;
                }
                $query = $climbed[$key]['query'];
                if (isset($seen[$image->url]) && $query !== null) {
                    $again[(string) $key] = new ImageQuery($query->text, $query->page + $round);

                    continue;
                }
                $seen[$image->url] = true;
            }
            if ($again === []) {
                break;
            }
            $answers = $this->images->findMany(array_values($again));
            foreach (array_keys($again) as $i => $key) {
                $answer = $answers[$i] ?? null;
                if ($answer !== null && ! isset($seen[$answer->url])) {
                    $images[$key] = $answer;
                    $seen[$answer->url] = true;
                }
            }
        }

        return $images;
    }

    private function countMissing(?string $promptVersion, string $what): void
    {
        $this->counters->record($promptVersion ?? 'unknown', [
            new Finding(self::MISSING, CheckMode::Observe, CheckAction::Counted, $what),
        ]);
    }
}
