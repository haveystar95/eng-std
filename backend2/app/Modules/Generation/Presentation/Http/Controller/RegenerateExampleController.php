<?php

declare(strict_types=1);

namespace App\Modules\Generation\Presentation\Http\Controller;

use App\Modules\Generation\Application\Command\RegenerateExample;
use App\Modules\Generation\Application\Command\RegenerateExampleHandler;
use App\Modules\Shared\Domain\ValueObject\TermId;
use App\Modules\Shared\Domain\ValueObject\Ulid;
use App\Modules\Shared\Domain\ValueObject\UserId;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/** POST /terms/{id}/regenerate-example — the "New example" button. Counts against the daily quota. */
final class RegenerateExampleController
{
    public function __construct(private readonly RegenerateExampleHandler $regenerate) {}

    public function __invoke(Request $request, string $id): JsonResponse
    {
        if (! Ulid::isValid($id)) {
            throw new NotFoundHttpException();
        }

        $result = ($this->regenerate)(new RegenerateExample(
            UserId::fromString((string) $request->user()?->getAuthIdentifier()),
            TermId::fromString($id),
        ));

        if ($result === null) {
            throw new NotFoundHttpException();
        }

        return response()->json(['data' => [
            'example' => $result->example,
            'example_translation' => $result->exampleTranslation,
        ]]);
    }
}
