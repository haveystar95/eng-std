<?php

declare(strict_types=1);

namespace App\Modules\Admin\Presentation\Http\Controller;

use App\Modules\Admin\Application\Command\CurateContent;
use App\Modules\Admin\Application\Command\CurateContentHandler;
use App\Modules\Admin\Application\Port\AdminTermReader;
use App\Modules\Admin\Presentation\Http\AdminJson;
use App\Modules\Admin\Presentation\Http\Paging;
use App\Modules\Admin\Presentation\Http\Request\CurateTermRequest;
use App\Modules\Shared\Domain\ValueObject\TermId;
use App\Modules\Vocabulary\Application\Port\TermCurator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use InvalidArgumentException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class TermController
{
    public function __construct(
        private readonly AdminTermReader $terms,
        private readonly CurateContentHandler $curate,
        private readonly TermCurator $curator,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $window = Paging::of($request);
        $search = $request->string('search')->toString();

        $result = $this->terms->list($search !== '' ? $search : null, $window);

        return response()->json(AdminJson::page($result, AdminJson::termRow(...)));
    }

    public function show(string $id): JsonResponse
    {
        $detail = $this->terms->detail($id);
        abort_if($detail === null, Response::HTTP_NOT_FOUND);

        return response()->json(AdminJson::termDetail($detail));
    }

    /**
     * What a change to this term would touch. The panel asks BEFORE showing the confirm dialog, so
     * the operator reads "used in 3 collections, 2 learners have progress" rather than "are you
     * sure?" — terms are global, and the edit lands everywhere at once.
     */
    public function impact(string $id): JsonResponse
    {
        $impact = $this->curator->impact($this->termId($id));
        abort_if($impact === null, Response::HTTP_NOT_FOUND);

        return response()->json(AdminJson::termImpact($impact));
    }

    public function update(CurateTermRequest $request, string $id): JsonResponse
    {
        $applied = ($this->curate)(new CurateContent(
            adminId: (string) $request->user()?->getAuthIdentifier(),
            action: CurateContent::EDIT_TERM,
            termId: $this->termId($id)->value,
            fields: $request->only([
                'text', 'translation', 'ipa', 'example_id', 'example_sentence', 'example_translation',
            ]),
        ));
        abort_unless($applied, Response::HTTP_NOT_FOUND);

        $detail = $this->terms->detail($id);
        abort_if($detail === null, Response::HTTP_NOT_FOUND);

        return response()->json(AdminJson::termDetail($detail));
    }

    public function destroy(Request $request, string $id): JsonResponse
    {
        $applied = ($this->curate)(new CurateContent(
            adminId: (string) $request->user()?->getAuthIdentifier(),
            action: CurateContent::RETIRE_TERM,
            termId: $this->termId($id)->value,
        ));
        abort_unless($applied, Response::HTTP_NOT_FOUND);

        return response()->json(['id' => $id, 'retired' => true]);
    }

    private function termId(string $id): TermId
    {
        try {
            return TermId::fromString($id);
        } catch (InvalidArgumentException $e) {
            throw new NotFoundHttpException(previous: $e);
        }
    }
}
