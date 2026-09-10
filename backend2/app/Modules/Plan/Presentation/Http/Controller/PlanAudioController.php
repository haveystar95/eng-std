<?php

declare(strict_types=1);

namespace App\Modules\Plan\Presentation\Http\Controller;

use App\Modules\Plan\Application\Port\LineAudioStore;
use App\Modules\Shared\Domain\ValueObject\Ulid;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * The partner's line as a file, under the same token as the rest of the API. The id is the one a
 * card's `audio_id` names; a ULID is unguessable enough that ownership is not re-checked per byte.
 */
final class PlanAudioController
{
    public function __construct(private readonly LineAudioStore $store) {}

    public function show(Request $request, string $id): Response
    {
        if (! Ulid::isValid($id)) {
            throw new NotFoundHttpException;
        }
        $row = $this->store->find($id);
        $bytes = $row === null ? null : $this->store->read($row);
        if ($row === null || $bytes === null) {
            throw new NotFoundHttpException;
        }

        return new Response($bytes, Response::HTTP_OK, [
            'Content-Type' => $row->format === 'wav' ? 'audio/wav' : 'audio/mpeg',
            'Content-Length' => (string) strlen($bytes),
            'Cache-Control' => 'private, max-age=31536000, immutable',
        ]);
    }
}
