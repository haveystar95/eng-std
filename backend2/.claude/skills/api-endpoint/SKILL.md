---
name: api-endpoint
description: How to add or change an HTTP endpoint in this Laravel API for the Flutter/iOS client — controller, FormRequest, Resource, policy, OpenAPI entry, error format, pagination, idempotency and tests. Consult this skill whenever the task involves routes, controllers, requests, responses, API versioning or the mobile client's contract, including small additions like "add a field to the response" or "expose an endpoint for X".
---

# API endpoints

Base: `/api/v1`, Sanctum bearer tokens, JSON only, snake_case keys.
The OpenAPI spec (`openapi/openapi.yaml`) is the contract — the Dart client is generated
from it, so an endpoint that isn't in the spec effectively doesn't exist.

## The shape of an endpoint

The controller is a translator. It has no `if` about business rules.

```php
final class CollectionItemController
{
    public function __construct(private CommandBus $bus, private QueryBus $queries) {}

    public function store(AddTermRequest $request, string $collectionId): JsonResponse
    {
        $this->bus->dispatch(new AddTermToCollection(
            collectionId: CollectionId::fromString($collectionId),
            termId: TermId::fromString($request->string('term_id')->toString()),
            actorId: UserId::fromString($request->user()->id),
        ));

        return response()->json(status: 204);
    }
}
```

Steps for every new endpoint:

1. **Route** in the module's `Presentation/Http/routes.php`, inside
   `->middleware(['auth:sanctum', 'throttle:api'])`.
2. **FormRequest** — validation and authorization of *input shape* only. Rules like
   "only the owner may edit" belong to the domain entity or a Policy, not to rules arrays.
3. **Command or Query** dispatched. One endpoint = one command or one query. If you need
   two commands, you probably need one command that expresses the real intent.
4. **API Resource** for the response, built from a read DTO — never from an Eloquent model.
5. **Policy** if the resource has ownership/visibility semantics (collections do).
6. **OpenAPI** entry with request/response schemas and error codes.
7. **Feature test** covering 200/201, 403, 422 and the empty case.

## Conventions

**Status codes:** 200 read, 201 create with body, 204 write with no body,
202 accepted for async (AI generation), 409 for conflicts, 422 for validation.

**Errors — RFC 7807 with a stable machine code the Flutter app switches on:**

```json
{
  "type": "https://api.wordtrainer.app/errors/collection-not-editable",
  "title": "Collection is not editable",
  "status": 403,
  "code": "collection_not_editable",
  "detail": "Shared collections must be forked before editing.",
  "meta": {"collection_id": "01J..."}
}
```

Domain exceptions map to codes in one exception handler mapping table, so a new domain
exception surfaces as a proper error without controller changes.

**Pagination:** cursor-based (`?cursor=&limit=`), never offset — the client scrolls
lists that change underneath it. Response envelope:

```json
{"data": [...], "meta": {"next_cursor": "01J...", "has_more": true}}
```

**Field selection:** mobile is bandwidth-sensitive. List endpoints return summaries
(id, title, counts, progress percent); details come from the item endpoint. Don't ship
full term lists inside a collections list response.

**Idempotency:** any endpoint the client may retry offline accepts a client-generated
ULID as the resource id (reviews, custom collections created offline). Re-sending the
same id returns the existing resource with 200 instead of creating a duplicate.

**Batch endpoints:** the client syncs in batches. `POST /reviews/batch` takes up to 200
items, returns per-item results, and never fails the whole batch because of one bad row:

```json
{"results": [{"id": "01J...", "status": "accepted"},
             {"id": "01J...", "status": "duplicate"},
             {"id": "01J...", "status": "rejected", "code": "unknown_term"}]}
```

**Auth:** `POST /auth/register`, `/auth/login`, `/auth/refresh`, `/auth/apple` (Sign in
with Apple is effectively mandatory for an iOS app with accounts), `DELETE /auth/session`.
Tokens are per-device; store a device name so users can revoke.

**Rate limits:** `throttle:api` globally; AI generation has its own tighter limiter
(see `ai-collection-generation`). Return `X-RateLimit-*` headers — the client shows quota.

## Finding the existing surface

Do not keep a list of endpoints here — it would rot within a week. The current API is
defined by two things, in this order:

1. `openapi/openapi.yaml` — the contract, and the source of truth.
2. The route files: `app/Modules/*/Presentation/Http/routes.php`.

Read both before adding anything, so you extend the existing shape instead of inventing a
parallel one. If they disagree, that's a bug: the spec wins and the code gets fixed,
because the Flutter client is generated from the spec.

The initial endpoint sketch for this product is in `ARCHITECTURE.md` — a design document
for bootstrapping, not a live index. Once routes exist, trust the routes and the spec.

## Checklist

1. Route registered under the right module and middleware?
2. Controller free of business logic and of Eloquent?
3. Response built from a DTO, with only fields the client needs?
4. OpenAPI updated in the same commit?
5. Feature tests for success, forbidden, validation, and empty results?
