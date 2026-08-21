<?php

declare(strict_types=1);

namespace App\Modules\Collections\Presentation\Http\Controller;

use App\Modules\Collections\Application\Command\AddTermToCollection;
use App\Modules\Collections\Application\Command\AddTermToCollectionHandler;
use App\Modules\Collections\Application\Command\AddWordToCollection;
use App\Modules\Collections\Application\Command\AddWordToCollectionHandler;
use App\Modules\Collections\Application\Command\CreateCustomCollection;
use App\Modules\Collections\Application\Command\CreateCustomCollectionHandler;
use App\Modules\Collections\Application\Command\DeleteCollection;
use App\Modules\Collections\Application\Command\DeleteCollectionHandler;
use App\Modules\Collections\Application\Command\EnsureDefaultCollection;
use App\Modules\Collections\Application\Command\EnsureDefaultCollectionHandler;
use App\Modules\Collections\Application\Command\MoveTermBetweenCollections;
use App\Modules\Collections\Application\Command\MoveTermBetweenCollectionsHandler;
use App\Modules\Collections\Application\Command\RemoveTermFromCollection;
use App\Modules\Collections\Application\Command\RemoveTermFromCollectionHandler;
use App\Modules\Collections\Application\Command\UpdateCollection;
use App\Modules\Collections\Application\Command\UpdateCollectionHandler;
use App\Modules\Collections\Application\Query\GetCollection;
use App\Modules\Collections\Application\Query\GetCollectionHandler;
use App\Modules\Collections\Application\Query\ListUserCollections;
use App\Modules\Collections\Application\Query\ListUserCollectionsHandler;
use App\Modules\Collections\Presentation\Http\Request\AddWordRequest;
use App\Modules\Collections\Presentation\Http\Request\CreateCollectionRequest;
use App\Modules\Collections\Presentation\Http\Request\MoveItemRequest;
use App\Modules\Collections\Presentation\Http\Request\UpdateCollectionRequest;
use App\Modules\Collections\Presentation\Http\Resource\CollectionResource;
use App\Modules\Collections\Presentation\Http\Resource\CollectionSummaryResource;
use App\Modules\Shared\Domain\ValueObject\CollectionId;
use App\Modules\Shared\Domain\ValueObject\LanguageCode;
use App\Modules\Shared\Domain\ValueObject\TermId;
use App\Modules\Shared\Domain\ValueObject\Ulid;
use App\Modules\Shared\Domain\ValueObject\UserId;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/** The user's collections. Reads are owner-scoped; writes go through the domain's owner rule. */
final class CollectionController
{
    public function __construct(
        private readonly ListUserCollectionsHandler $list,
        private readonly CreateCustomCollectionHandler $create,
        private readonly GetCollectionHandler $get,
        private readonly UpdateCollectionHandler $update,
        private readonly DeleteCollectionHandler $delete,
        private readonly AddWordToCollectionHandler $addWord,
        private readonly AddTermToCollectionHandler $addTerm,
        private readonly RemoveTermFromCollectionHandler $removeTerm,
        private readonly MoveTermBetweenCollectionsHandler $moveTerm,
        private readonly EnsureDefaultCollectionHandler $ensureDefault,
    ) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $cursor = $request->string('cursor')->toString();

        $page = ($this->list)(new ListUserCollections(
            userId: $this->actorId($request),
            cursor: $cursor !== '' ? $cursor : null,
            limit: $request->integer('limit', 30),
        ));

        return CollectionSummaryResource::collection($page->items)
            ->additional(['meta' => ['next_cursor' => $page->nextCursor, 'has_more' => $page->hasMore]]);
    }

    public function store(CreateCollectionRequest $request): JsonResponse
    {
        $data = $request->validated();
        $actor = $this->actorId($request);

        $id = ($this->create)(new CreateCustomCollection(
            ownerId: $actor,
            title: (string) $data['title'],
            sourceLang: new LanguageCode(isset($data['source_lang']) ? (string) $data['source_lang'] : 'ru'),
            targetLang: new LanguageCode(isset($data['target_lang']) ? (string) $data['target_lang'] : 'en'),
            description: isset($data['description']) ? (string) $data['description'] : null,
            id: isset($data['id']) ? CollectionId::fromString((string) $data['id']) : null,
        ));

        $view = ($this->get)(new GetCollection($id, $actor));

        return CollectionResource::make($view)->response()->setStatusCode(Response::HTTP_CREATED);
    }

    public function show(Request $request, string $id): CollectionResource
    {
        $view = ($this->get)(new GetCollection($this->collectionId($id), $this->actorId($request)));
        if ($view === null) {
            throw new NotFoundHttpException();
        }

        return CollectionResource::make($view);
    }

    public function update(UpdateCollectionRequest $request, string $id): CollectionResource
    {
        $data = $request->validated();
        $actor = $this->actorId($request);
        $collectionId = $this->collectionId($id);

        ($this->update)(new UpdateCollection(
            collectionId: $collectionId,
            actorId: $actor,
            title: array_key_exists('title', $data) ? (string) $data['title'] : null,
            description: array_key_exists('description', $data) ? (string) ($data['description'] ?? '') : null,
        ));

        return CollectionResource::make(($this->get)(new GetCollection($collectionId, $actor)));
    }

    public function destroy(Request $request, string $id): Response
    {
        ($this->delete)(new DeleteCollection($this->collectionId($id), $this->actorId($request)));

        return response()->noContent();
    }

    /**
     * Put a word in this folder — either an EXISTING term by id, or a new one from typed text.
     * The request object guarantees exactly one of the two arrived.
     */
    public function addItem(AddWordRequest $request, string $id): JsonResponse
    {
        $data = $request->validated();
        $actor = $this->actorId($request);
        $collectionId = $this->collectionId($id);

        if (isset($data['term_id'])) {
            ($this->addTerm)(new AddTermToCollection(
                collectionId: $collectionId,
                termId: TermId::fromString((string) $data['term_id']),
                actorId: $actor,
            ));
        } else {
            ($this->addWord)(new AddWordToCollection(
                collectionId: $collectionId,
                actorId: $actor,
                text: (string) $data['text'],
                translation: isset($data['translation']) ? (string) $data['translation'] : null,
                type: isset($data['type']) ? (string) $data['type'] : 'word',
            ));
        }

        return CollectionResource::make(($this->get)(new GetCollection($collectionId, $actor)))
            ->response()->setStatusCode(Response::HTTP_CREATED);
    }

    /**
     * «Сохранённые» — created on first ask. A GET that may write, deliberately: the folder's whole
     * point is to exist by the time something is put in it, and making the client create it would
     * put the "one per owner" rule on the phone.
     */
    public function defaultCollection(Request $request): CollectionResource
    {
        $actor = $this->actorId($request);
        $id = ($this->ensureDefault)(new EnsureDefaultCollection($actor));

        return CollectionResource::make(($this->get)(new GetCollection($id, $actor)));
    }

    /** Move one term between two of the actor's OWN folders. Both ends are ownership-checked. */
    public function moveItem(MoveItemRequest $request, string $id, string $termId): Response
    {
        if (! Ulid::isValid($termId)) {
            throw new NotFoundHttpException();
        }

        ($this->moveTerm)(new MoveTermBetweenCollections(
            fromCollectionId: $this->collectionId($id),
            toCollectionId: $this->collectionId((string) $request->validated()['to_collection_id']),
            termId: TermId::fromString($termId),
            actorId: $this->actorId($request),
        ));

        return response()->noContent();
    }

    public function removeItem(Request $request, string $id, string $termId): Response
    {
        if (! Ulid::isValid($termId)) {
            throw new NotFoundHttpException();
        }

        ($this->removeTerm)(new RemoveTermFromCollection(
            collectionId: $this->collectionId($id),
            actorId: $this->actorId($request),
            termId: TermId::fromString($termId),
        ));

        return response()->noContent();
    }

    private function collectionId(string $id): CollectionId
    {
        if (! Ulid::isValid($id)) {
            throw new NotFoundHttpException();
        }

        return CollectionId::fromString($id);
    }

    private function actorId(Request $request): UserId
    {
        return UserId::fromString((string) $request->user()?->getAuthIdentifier());
    }
}
