<?php

declare(strict_types=1);

namespace App\Modules\Generation\Presentation\Http\Controller;

use App\Modules\Generation\Application\Command\AddSearchResult;
use App\Modules\Generation\Application\Command\AddSearchResultHandler;
use App\Modules\Generation\Application\Command\LookupWord;
use App\Modules\Generation\Application\Command\LookupWordHandler;
use App\Modules\Generation\Application\Dto\CachedLookup;
use App\Modules\Generation\Application\Dto\SearchHitView;
use App\Modules\Generation\Application\Query\InstantTranslate;
use App\Modules\Generation\Application\Query\InstantTranslateHandler;
use App\Modules\Generation\Application\Query\SearchTerms;
use App\Modules\Generation\Application\Query\SearchTermsHandler;
use App\Modules\Generation\Presentation\Http\Request\AddSearchResultRequest;
use App\Modules\Generation\Presentation\Http\Request\LookupWordRequest;
use App\Modules\Shared\Domain\ValueObject\CollectionId;
use App\Modules\Shared\Domain\ValueObject\TermId;
use App\Modules\Shared\Domain\ValueObject\UserId;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Word search, in three steps that are deliberately three endpoints.
 *
 *  * `GET /search` — free, instant, over what the database already has. Safe to call on a keystroke.
 *  * `POST /search/lookup` — ONE cheap model call for a word we don't have. Costs money, so it is a
 *    POST the learner taps and never something a debounce fires.
 *  * `POST /search/add` — the save: term, folder, pool.
 *
 * Splitting the second one out is the whole reason this is not a single «search» endpoint that
 * falls back to the model: a search box that generates on its own would spend the daily cap while
 * the learner was still typing.
 */
final class SearchController
{
    public function __construct(
        private readonly SearchTermsHandler $search,
        private readonly LookupWordHandler $lookup,
        private readonly AddSearchResultHandler $add,
        private readonly InstantTranslateHandler $instant,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $query = trim($request->string('q')->toString());
        $results = $query === ''
            ? []
            : ($this->search)(new SearchTerms(
                actorId: $this->actorId($request),
                query: $query,
                limit: min(50, max(1, $request->integer('limit', 20))),
            ));

        return response()->json(['data' => array_map($this->hit(...), $results)]);
    }

    /**
     * The grey line under the field: one word, one translation, as fast as it can be had.
     *
     * ALWAYS 200, and always the same shape. This is a hint on a debounced field — there is no
     * failure here worth interrupting somebody who is typing, so «no key», «no budget», «no answer»
     * and «dead vendor» are all just a null translation. The client renders a line when there is one
     * and nothing when there is not; it has no error path at all.
     */
    public function instant(Request $request): JsonResponse
    {
        $hint = ($this->instant)(new InstantTranslate(
            actorId: $this->actorId($request),
            query: trim($request->string('q')->toString()),
        ));

        return response()->json(['data' => [
            'query' => $hint->query,
            'translation' => $hint->translation,
            // Internal: which rung answered. The client does not show it — where a translation came
            // from is this app's business, not the learner's — but the ledger and the tests need it.
            'source' => $hint->source,
            'feature_disabled' => $hint->featureDisabled,
            'limit_reached' => $hint->limitReached,
        ]]);
    }

    public function lookup(LookupWordRequest $request): JsonResponse
    {
        $outcome = ($this->lookup)(new LookupWord(
            actorId: $this->actorId($request),
            query: (string) $request->validated()['query'],
        ));

        // The cap is a 200, not a 429. It is a normal answer the app has a screen for — «на сегодня
        // лимит, вот что нашлось в базе» — and the client shows the free results beside it. An error
        // status would push it down the error path, where the results it should be showing are not.
        return response()->json([
            'data' => [
                'lookup' => $outcome->lookup !== null ? $this->lookupCard($outcome->lookup) : null,
                'limit_reached' => $outcome->capReached,
                'daily_cap' => $outcome->dailyCap,
                'used_today' => $outcome->usedToday,
            ],
        ]);
    }

    public function add(AddSearchResultRequest $request): JsonResponse
    {
        $data = $request->validated();

        $saved = ($this->add)(new AddSearchResult(
            actorId: $this->actorId($request),
            lookupId: isset($data['lookup_id']) ? (string) $data['lookup_id'] : null,
            termId: isset($data['term_id']) ? TermId::fromString((string) $data['term_id']) : null,
            // `sometimes|nullable` means the key may be absent OR explicitly null; a client that
            // sends `collection_id: null` means «Сохранённые» just as much as one that omits it.
            collectionId: ($data['collection_id'] ?? null) !== null
                ? CollectionId::fromString((string) $data['collection_id'])
                : null,
        ));

        return response()->json([
            'data' => [
                'term_id' => $saved->termId,
                'collection_id' => $saved->collectionId,
                'collection_title' => $saved->collectionTitle,
                'collection_is_default' => $saved->collectionIsDefault,
                'added' => $saved->added,
                'enrolled' => $saved->enrolled,
            ],
        ], Response::HTTP_CREATED);
    }

    /** @return array<string, mixed> */
    private function hit(SearchHitView $hit): array
    {
        return [
            'term_id' => $hit->termId,
            'text' => $hit->text,
            'type' => $hit->type,
            'transcription' => $hit->transcription,
            'translation' => $hit->translation,
            'description' => $hit->description,
            'example' => $hit->example,
            'example_translation' => $hit->exampleTranslation,
            'cefr' => $hit->cefr,
            'folders' => $hit->folders,
        ];
    }

    /** @return array<string, mixed> */
    private function lookupCard(CachedLookup $lookup): array
    {
        return [
            'lookup_id' => $lookup->id,
            'text' => $lookup->text,
            'type' => $lookup->type,
            'transcription' => $lookup->transcription,
            'translation' => $lookup->translation,
            'description' => $lookup->description,
            'example' => $lookup->example,
            'example_translation' => $lookup->exampleTranslation,
            'cefr' => $lookup->cefr,
            // Whether THIS call paid for it. The client shows nothing different; it is here because
            // «сколько lookup-ов сегодня стоило денег» has to be answerable without reading the log.
            'fresh' => $lookup->fresh,
        ];
    }

    private function actorId(Request $request): UserId
    {
        return UserId::fromString((string) $request->user()?->getAuthIdentifier());
    }
}
