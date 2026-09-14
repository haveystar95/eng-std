<?php

declare(strict_types=1);

namespace App\Modules\Plan\Infrastructure\Adapter;

use App\Modules\Generation\Application\Dto\ImageResult;
use App\Modules\Generation\Application\Dto\ImageSearchQuery;
use App\Modules\Generation\Application\Port\ImageSearchPort;
use App\Modules\Generation\Application\Port\TransientImageSearchError;
use App\Modules\Plan\Application\Port\PlanImageFinder;
use App\Modules\Plan\Domain\ValueObject\Image;
use App\Modules\Plan\Domain\ValueObject\ImageQuery;
use Illuminate\Support\Facades\Log;

/**
 * The plan's photos come from the same seam the collections' photos do. A transient search error
 * propagates so the job retries; an empty result is null and stays null.
 *
 * The tone lookup reads the vendor's photo id out of the stored address
 * (`https://images.pexels.com/photos/2034335/pexels-photo-2034335.jpeg?…`) and asks for that photo.
 * It is a backfill's question, so a transient failure is a null — the backfill runs again — and
 * only a hard vendor error (a bad key) is loud.
 */
final readonly class PexelsPlanImageFinder implements PlanImageFinder
{
    /** «Параллельно (пул 6)» — six requests on the wire at once, the rest wait their turn. */
    public const PARALLEL = 6;

    public function __construct(private ImageSearchPort $images) {}

    public function find(string $prompt): ?Image
    {
        $found = $this->images->search($prompt);

        return $found === null ? null : new Image($found->url, $found->author, $found->authorUrl, $found->avgColor);
    }

    public function findMany(array $queries): array
    {
        $found = $this->images->searchMany(
            array_map(static fn (ImageQuery $q): ImageSearchQuery => new ImageSearchQuery($q->text, $q->page), $queries),
            self::PARALLEL,
        );

        return array_map(
            static fn (?ImageResult $r): ?Image => $r === null ? null : new Image($r->url, $r->author, $r->authorUrl, $r->avgColor),
            $found,
        );
    }

    public function tone(string $imageUrl): ?string
    {
        if (preg_match('~^https://images\.pexels\.com/photos/(\d+)/~', $imageUrl, $m) !== 1) {
            return null;
        }

        try {
            return Image::normalTone($this->images->photo($m[1])?->avgColor);
        } catch (TransientImageSearchError $e) {
            Log::warning('Plan image tone lookup failed; the backfill leaves the tone empty', ['url' => $imageUrl, 'error' => $e->getMessage()]);

            return null;
        }
    }
}
