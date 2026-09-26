<?php

declare(strict_types=1);

namespace App\Modules\Plan\Presentation\Http\Controller;

use App\Modules\Plan\Application\Port\RescueAudioStore;
use Illuminate\Http\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * A line of the rescue kit as a file (наряд LANG-1b §2), under the same token as the rest of the API. The key is the one
 * `rescue_kit[].audio_url` names — the kit's sha1 of (target, gender, voice, line): the same file for every learner of
 * that target and voice, so there is no owner to check.
 */
final class PlanRescueAudioController
{
    public function __construct(private readonly RescueAudioStore $store) {}

    public function show(string $key): Response
    {
        $audio = $this->store->read($key);
        if ($audio === null) {
            throw new NotFoundHttpException;
        }

        return new Response($audio->bytes, Response::HTTP_OK, [
            'Content-Type' => $audio->format === 'wav' ? 'audio/wav' : 'audio/mpeg',
            'Content-Length' => (string) strlen($audio->bytes),
            'Cache-Control' => 'private, max-age=31536000, immutable',
        ]);
    }
}
