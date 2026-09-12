<?php

declare(strict_types=1);

namespace App\Modules\Plan\Infrastructure\Adapter;

use App\Modules\Observability\Application\Support\OutboundCallContext;
use App\Modules\Plan\Application\Port\SceneImageStore;
use App\Modules\Plan\Domain\ValueObject\PlanSceneId;
use App\Modules\Plan\Domain\ValueObject\SceneImageSize;
use Illuminate\Contracts\Filesystem\Factory as Disks;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * The sized copies of a scene photo on the configured disk (`plan.image_disk`), under
 * `plan-images/<scene>/<size>.jpg`.
 *
 * The container has no image library, and it needs none: the photo's CDN crops. The stored source
 * address loses its query and gets the square one instead —
 * `…/pexels-photo-2034335.jpeg?auto=compress&cs=tinysrgb&fit=crop&w=448&h=448`. The call is
 * labelled `images` for the outbound log, like the search that found the photo.
 *
 * `network: false` (the fake image driver — tests, offline dev) fetches nothing: a copy that is
 * not on the disk stays missing, and no test puts a request on the wire by accident.
 */
final readonly class CdnSceneImageStore implements SceneImageStore
{
    public function __construct(
        private Disks $disks,
        private string $disk,
        private OutboundCallContext $context,
        private bool $network,
        private int $timeoutSeconds = 15,
    ) {}

    public function has(PlanSceneId $sceneId, SceneImageSize $size): bool
    {
        return $this->disks->disk($this->disk)->exists(self::path($sceneId, $size));
    }

    public function read(PlanSceneId $sceneId, SceneImageSize $size): ?string
    {
        $disk = $this->disks->disk($this->disk);
        $path = self::path($sceneId, $size);
        $bytes = $disk->exists($path) ? $disk->get($path) : null;

        return is_string($bytes) && $bytes !== '' ? $bytes : null;
    }

    public function fetch(PlanSceneId $sceneId, string $sourceUrl, SceneImageSize $size): ?string
    {
        if (! $this->network) {
            return null;
        }
        $base = strtok($sourceUrl, '?');
        if (! is_string($base) || ! str_starts_with($base, 'https://')) {
            return null;
        }

        try {
            $response = $this->context->run('images', null, fn () => Http::withHeaders(['Accept' => 'image/jpeg'])
                ->timeout($this->timeoutSeconds)
                ->get($base, ['auto' => 'compress', 'cs' => 'tinysrgb', 'fit' => 'crop', 'w' => $size->value, 'h' => $size->value]));
            $bytes = $response->body();
            if (! $response->successful() || $bytes === '' || ! str_starts_with((string) $response->header('Content-Type'), 'image/')) {
                Log::warning('Scene image copy not fetched', ['scene_id' => $sceneId->value, 'size' => $size->value, 'status' => $response->status()]);

                return null;
            }
            $this->disks->disk($this->disk)->put(self::path($sceneId, $size), $bytes);

            return $bytes;
        } catch (Throwable $e) {
            Log::warning('Scene image copy not fetched', ['scene_id' => $sceneId->value, 'size' => $size->value, 'error' => $e->getMessage()]);

            return null;
        }
    }

    private static function path(PlanSceneId $sceneId, SceneImageSize $size): string
    {
        return sprintf('plan-images/%s/%d.jpg', $sceneId->value, $size->value);
    }
}
