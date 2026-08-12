<?php

declare(strict_types=1);

namespace App\Modules\Admin\Presentation\Http\Controller;

use App\Modules\Admin\Application\Command\CurateContent;
use App\Modules\Admin\Application\Command\CurateContentHandler;
use App\Modules\Admin\Application\Port\AdminCollectionReader;
use App\Modules\Admin\Presentation\Http\AdminJson;
use App\Modules\Admin\Presentation\Http\Paging;
use App\Modules\Admin\Presentation\Http\Request\CurateCollectionRequest;
use App\Modules\Admin\Presentation\Http\Request\DeleteCollectionRequest;
use App\Modules\Collections\Application\Port\CollectionCurator;
use App\Modules\Shared\Domain\ValueObject\CollectionId;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use InvalidArgumentException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class CollectionController
{
    public function __construct(
        private readonly AdminCollectionReader $collections,
        private readonly CurateContentHandler $curate,
        private readonly CollectionCurator $curator,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $window = Paging::of($request);
        $type = $request->string('type')->toString();
        $search = $request->string('search')->toString();

        $result = $this->collections->list(
            $type !== '' ? $type : null,
            $search !== '' ? $search : null,
            $window,
        );

        return response()->json(AdminJson::page($result, AdminJson::collectionRow(...)));
    }

    public function show(string $id): JsonResponse
    {
        $detail = $this->collections->detail($id);
        abort_if($detail === null, Response::HTTP_NOT_FOUND);

        return response()->json(AdminJson::collectionDetail($detail));
    }

    /** How many people would lose this deck — read before the confirm dialog is drawn. */
    public function impact(string $id): JsonResponse
    {
        $impact = $this->curator->impact($this->collectionId($id));
        abort_if($impact === null, Response::HTTP_NOT_FOUND);

        return response()->json(AdminJson::collectionImpact($impact));
    }

    public function update(CurateCollectionRequest $request, string $id): JsonResponse
    {
        $applied = ($this->curate)(new CurateContent(
            adminId: (string) $request->user()?->getAuthIdentifier(),
            action: CurateContent::EDIT_COLLECTION,
            collectionId: $this->collectionId($id)->value,
            fields: $request->only(['title', 'description']),
        ));
        abort_unless($applied, Response::HTTP_NOT_FOUND);

        return $this->show($id);
    }

    public function addTerm(Request $request, string $id): JsonResponse
    {
        $validated = $request->validate(['term_id' => ['required', 'string', 'size:26']]);

        $applied = ($this->curate)(new CurateContent(
            adminId: (string) $request->user()?->getAuthIdentifier(),
            action: CurateContent::ADD_TERM_TO_COLLECTION,
            termId: (string) $validated['term_id'],
            collectionId: $this->collectionId($id)->value,
        ));
        abort_unless($applied, Response::HTTP_NOT_FOUND);

        return $this->show($id);
    }

    public function removeTerm(Request $request, string $id, string $termId): JsonResponse
    {
        $applied = ($this->curate)(new CurateContent(
            adminId: (string) $request->user()?->getAuthIdentifier(),
            action: CurateContent::REMOVE_TERM_FROM_COLLECTION,
            termId: $termId,
            collectionId: $this->collectionId($id)->value,
        ));
        abort_unless($applied, Response::HTTP_NOT_FOUND);

        return $this->show($id);
    }

    public function destroy(DeleteCollectionRequest $request, string $id): JsonResponse
    {
        $impact = $this->curator->impact($this->collectionId($id));
        abort_if($impact === null, Response::HTTP_NOT_FOUND);

        // Typing the title back is the guard against a misclick that would take the deck away from
        // every subscriber at once. Enforced server-side, not just in the dialog.
        abort_unless(
            $request->string('confirm_title')->toString() === $impact->title,
            Response::HTTP_UNPROCESSABLE_ENTITY,
            'confirm_title must match the collection title',
        );

        $applied = ($this->curate)(new CurateContent(
            adminId: (string) $request->user()?->getAuthIdentifier(),
            action: CurateContent::DELETE_COLLECTION,
            collectionId: $impact->collectionId,
            fields: ['title' => $impact->title, 'subscribers' => $impact->subscribers],
        ));
        abort_unless($applied, Response::HTTP_NOT_FOUND);

        return response()->json(['id' => $id, 'deleted' => true]);
    }

    private function collectionId(string $id): CollectionId
    {
        try {
            return CollectionId::fromString($id);
        } catch (InvalidArgumentException $e) {
            throw new NotFoundHttpException(previous: $e);
        }
    }
}
