<?php

declare(strict_types=1);

namespace App\Modules\Learning\Presentation\Http\Controller;

use App\Modules\Learning\Application\Command\EnrollTerm;
use App\Modules\Learning\Application\Command\EnrollTermHandler;
use App\Modules\Learning\Application\Command\UnenrollTerm;
use App\Modules\Learning\Application\Command\UnenrollTermHandler;
use App\Modules\Shared\Domain\ValueObject\TermId;
use App\Modules\Shared\Domain\ValueObject\UserId;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use InvalidArgumentException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * THE POOL — the learner's own list of words being studied, as opposed to the catalogue of topics
 * their collections are. Two verbs, both idempotent, both about one pair:
 *
 *   PUT    /pool/terms/{termId}   «Учить это слово»
 *   DELETE /pool/terms/{termId}   «Убрать из изучения» — a pause, nothing is erased
 *
 * There is deliberately no GET here. The device reads its pool from its OWN database, which `/sync`
 * mirrors (`enrolled_at` rides on every progress row) — every screen in this app reads from the
 * local store so it works in airplane mode, and a pool endpoint would be a second, slower answer to
 * a question already answered offline.
 *
 * `changed` in the response says whether THIS call was the one that moved anything: a replayed
 * request from an offline queue answers 200 with `changed: false` rather than an error, for the
 * same reason session completion does.
 */
final class PoolController
{
    public function __construct(
        private readonly EnrollTermHandler $enroll,
        private readonly UnenrollTermHandler $unenroll,
    ) {}

    public function enroll(Request $request, string $termId): JsonResponse
    {
        $id = $this->termId($termId);

        $changed = ($this->enroll)(new EnrollTerm($this->actorId($request), $id));

        return $this->body($id, enrolled: true, changed: $changed);
    }

    public function unenroll(Request $request, string $termId): JsonResponse
    {
        $id = $this->termId($termId);

        $changed = ($this->unenroll)(new UnenrollTerm($this->actorId($request), $id));

        return $this->body($id, enrolled: false, changed: $changed);
    }

    private function body(TermId $termId, bool $enrolled, bool $changed): JsonResponse
    {
        return new JsonResponse(['data' => [
            'term_id' => $termId->value,
            'enrolled' => $enrolled,
            'changed' => $changed,
        ]], Response::HTTP_OK);
    }

    private function termId(string $termId): TermId
    {
        try {
            return TermId::fromString($termId);
        } catch (InvalidArgumentException $e) {
            throw new NotFoundHttpException(previous: $e);
        }
    }

    private function actorId(Request $request): UserId
    {
        return UserId::fromString((string) $request->user()?->getAuthIdentifier());
    }
}
