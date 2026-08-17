<?php

declare(strict_types=1);

namespace App\Modules\Collections\Presentation\Http\Controller;

use App\Modules\Collections\Application\Command\SubscribeToCollection;
use App\Modules\Collections\Application\Command\SubscribeToCollectionHandler;
use App\Modules\Collections\Application\Command\UnsubscribeFromCollection;
use App\Modules\Collections\Application\Command\UnsubscribeFromCollectionHandler;
use App\Modules\Collections\Application\Query\GetStoreCollectionPreview;
use App\Modules\Collections\Application\Query\GetStoreCollectionPreviewHandler;
use App\Modules\Collections\Application\Query\GetStoreCollections;
use App\Modules\Collections\Application\Query\GetStoreCollectionsHandler;
use App\Modules\Collections\Presentation\Http\Resource\StoreCollectionPreviewResource;
use App\Modules\Collections\Presentation\Http\Resource\StoreCollectionResource;
use App\Modules\Shared\Domain\ValueObject\CollectionId;
use App\Modules\Shared\Domain\ValueObject\LanguageCode;
use App\Modules\Shared\Domain\ValueObject\Ulid;
use App\Modules\Shared\Domain\ValueObject\UserId;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/** The public store: browse subscribable collections and add/remove them from the library. */
final class StoreController
{
    public function __construct(
        private readonly GetStoreCollectionsHandler $list,
        private readonly GetStoreCollectionPreviewHandler $previewHandler,
        private readonly SubscribeToCollectionHandler $subscribe,
        private readonly UnsubscribeFromCollectionHandler $unsubscribe,
    ) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $cursor = $request->string('cursor')->toString();

        $page = ($this->list)(new GetStoreCollections(
            viewer: $this->actorId($request),
            sourceLang: new LanguageCode($request->string('source_lang', 'ru')->toString()),
            targetLang: new LanguageCode($request->string('target_lang', 'en')->toString()),
            cursor: $cursor !== '' ? $cursor : null,
            limit: $request->integer('limit', 30),
        ));

        return StoreCollectionResource::collection($page->items)
            ->additional(['meta' => ['next_cursor' => $page->nextCursor, 'has_more' => $page->hasMore]]);
    }

    /** A free taster: the first few terms + total. No tier gate — previewing a premium deck is fine. */
    public function preview(Request $request, string $id): JsonResponse
    {
        $preview = ($this->previewHandler)(new GetStoreCollectionPreview($this->collectionId($id)));

        if ($preview === null) {
            throw new NotFoundHttpException();
        }

        return StoreCollectionPreviewResource::make($preview)->response();
    }

    public function subscribe(Request $request, string $id): JsonResponse
    {
        ($this->subscribe)(new SubscribeToCollection($this->actorId($request), $this->collectionId($id)));

        // 200 on both first-time and repeat: subscribing is idempotent, never an error.
        return response()->json(['data' => ['collection_id' => $id, 'subscribed' => true]]);
    }

    public function unsubscribe(Request $request, string $id): JsonResponse
    {
        ($this->unsubscribe)(new UnsubscribeFromCollection($this->actorId($request), $this->collectionId($id)));

        return response()->json(['data' => ['collection_id' => $id, 'subscribed' => false]]);
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
