<?php

declare(strict_types=1);

namespace App\Modules\Plan\Presentation\Http\Controller;

use App\Modules\Plan\Application\Query\GetSceneImage;
use App\Modules\Plan\Application\Query\GetSceneImageHandler;
use App\Modules\Plan\Domain\ValueObject\PlanSceneId;
use App\Modules\Plan\Domain\ValueObject\SceneImageSize;
use App\Modules\Shared\Domain\ValueObject\Ulid;
use App\Modules\Shared\Domain\ValueObject\UserId;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * A square copy of a scene photo as a file (`GET /plans/images/{sceneId}/{size}`). The address the
 * plan hands out carries the photo's version, so the bytes behind it never change: a year of
 * `immutable`, and an ETag for the client that asks anyway. Translation only — HTTP caching is
 * the one thing decided here.
 */
final class PlanImageController
{
    private const CACHE_CONTROL = 'public, max-age=31536000, immutable';

    public function __construct(private readonly GetSceneImageHandler $images) {}

    public function show(Request $request, string $sceneId, string $size): Response
    {
        $pixels = SceneImageSize::tryFrom((int) $size);
        if (! Ulid::isValid($sceneId) || $pixels === null || (string) $pixels->value !== $size) {
            throw new NotFoundHttpException;
        }

        $file = ($this->images)(new GetSceneImage(
            PlanSceneId::fromString($sceneId),
            $pixels,
            UserId::fromString((string) $request->user()?->getAuthIdentifier()),
        ));

        $response = new Response($file->bytes, Response::HTTP_OK, ['Content-Type' => 'image/jpeg']);
        $response->setEtag($file->digest);
        $response->headers->set('Cache-Control', self::CACHE_CONTROL);
        if ($response->isNotModified($request)) {
            // Symfony drops the body and the content headers; the cache headers stay.
            $response->headers->set('Cache-Control', self::CACHE_CONTROL);

            return $response;
        }
        $response->headers->set('Content-Length', (string) strlen($file->bytes));

        return $response;
    }
}
