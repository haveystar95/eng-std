<?php

declare(strict_types=1);

namespace App\Modules\Generation\Presentation\Http\Controller;

use App\Modules\Generation\Application\Command\RequestCollectionGeneration;
use App\Modules\Generation\Application\Command\RequestCollectionGenerationHandler;
use App\Modules\Generation\Application\Port\DispatchesGeneration;
use App\Modules\Generation\Application\Query\GetGenerationRequest;
use App\Modules\Generation\Application\Query\GetGenerationRequestHandler;
use App\Modules\Generation\Presentation\Http\Request\RequestGenerationRequest;
use App\Modules\Generation\Presentation\Http\Resource\GenerationRequestResource;
use App\Modules\Shared\Domain\ValueObject\GenerationRequestId;
use App\Modules\Shared\Domain\ValueObject\LanguageCode;
use App\Modules\Shared\Domain\ValueObject\Ulid;
use App\Modules\Shared\Domain\ValueObject\UserId;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Async generation: POST returns 202 with a pending request; the client polls GET until it
 * flips to succeeded (with a collection_id) or failed.
 */
final class GenerationController
{
    private const DEFAULT_LEVELS = ['A2', 'B1'];
    private const DEFAULT_SIZE = 12;

    public function __construct(
        private readonly RequestCollectionGenerationHandler $request,
        private readonly DispatchesGeneration $dispatcher,
        private readonly GetGenerationRequestHandler $get,
    ) {}

    public function store(RequestGenerationRequest $request): JsonResponse
    {
        $data = $request->validated();
        $actor = $this->actorId($request);

        $outcome = ($this->request)(new RequestCollectionGeneration(
            userId: $actor,
            prompt: (string) $data['prompt'],
            sourceLang: new LanguageCode(isset($data['source_lang']) ? (string) $data['source_lang'] : 'ru'),
            // Omitted → the handler falls back to the user's default learning language.
            targetLang: isset($data['target_lang']) ? new LanguageCode((string) $data['target_lang']) : null,
            levels: $this->levels($data),
            size: isset($data['size']) ? (int) $data['size'] : self::DEFAULT_SIZE,
            id: isset($data['id']) ? GenerationRequestId::fromString((string) $data['id']) : null,
        ));

        // New request → dispatch the job + 202. A client-id repeat → the existing request + 200.
        if ($outcome->created) {
            $this->dispatcher->dispatch($outcome->id);
        }

        $view = ($this->get)(new GetGenerationRequest($outcome->id, $actor));
        $status = $outcome->created ? Response::HTTP_ACCEPTED : Response::HTTP_OK;

        return GenerationRequestResource::make($view)->response()->setStatusCode($status);
    }

    public function show(Request $request, string $id): GenerationRequestResource
    {
        if (! Ulid::isValid($id)) {
            throw new NotFoundHttpException();
        }

        $view = ($this->get)(new GetGenerationRequest(GenerationRequestId::fromString($id), $this->actorId($request)));
        if ($view === null) {
            throw new NotFoundHttpException();
        }

        return GenerationRequestResource::make($view);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return list<string>
     */
    private function levels(array $data): array
    {
        if (! isset($data['levels']) || ! is_array($data['levels']) || $data['levels'] === []) {
            return self::DEFAULT_LEVELS;
        }

        return array_values(array_map('strval', $data['levels']));
    }

    private function actorId(Request $request): UserId
    {
        return UserId::fromString((string) $request->user()?->getAuthIdentifier());
    }
}
