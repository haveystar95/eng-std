# Collections

**Owns:** collections (system/shared/custom), items, subscriptions, forks

Layers: `Domain` (pure PHP, no Laravel) · `Application` (Commands/Queries/Ports/DTOs) ·
`Infrastructure` (Eloquent, adapters, migrations, provider) · `Presentation` (Http).

## Ownership model

- `type=custom` + `owner_id` → the user's own, editable (`assertEditableBy` = owner only).
- `type=system|shared` + `owner_id=NULL` → curated platform content, read-only for users.
  (No "system user" — ownerless is the whole point.) Adding a shared collection to "my
  collections" is a row in `user_collections`, not a copy; forking copies `collection_items`.
- `is_default` → the owner's «Сохранённые» folder, where a one-tap save from search lands.
  Created LAZILY on first ask (`EnsureDefaultCollection`), exactly one per owner (a partial
  unique index, not just the handler). It is an ordinary custom collection in every other
  respect — renameable, practisable, its words are pool words on the usual terms. The flag
  buys two behaviours and no more: it is the destination of an unaddressed save, and
  `assertDeletableBy` refuses to remove it. Never looked up by TITLE — the owner may rename it.

## A folder is a shelf, not the queue

Deleting a folder, or moving a word out of one, touches NOTHING in Learning: `enrolled_at`
stays, the rung stays, the due date stays, and the append-only review log is not written to.
The only way out of the pool is «убрать из тренировки» (`DELETE /pool/terms/{termId}`).
Pinned by `tests/Feature/Collections/UserFolderApiTest.php`.

## HTTP (`/api/v1`, `auth:sanctum`)

- `GET /collections` — the user's collections, cursor-paginated (newest first by ULID),
  summaries only: `{data: [...], meta: {next_cursor, has_more}}`.
- `POST /collections` — create custom; accepts a client ULID `id` for offline idempotency
  (re-send ≠ duplicate); 201.
- `GET /collections/{id}` — detail with items (owner-only → 404 otherwise).
- `PATCH /collections/{id}` — update title/description (owner-only → 403).
- `DELETE /collections/{id}` — soft-delete (tombstone for delta sync); 409
  `collection_not_deletable` on the default folder.
- `GET /collections/default` — the «Сохранённые» folder, created on first ask.
- `POST /collections/{id}/items` — add a word: `term_id` (an existing term) XOR `text` (create/dedup).
- `POST /collections/{id}/items/{termId}/move` — move a term to another of the actor's own folders.

Reads go through Query handlers (`ListUserCollections` via a reader, `GetCollection` via the
repo); writes through Commands (`CreateCustomCollection`, `CreateGeneratedCollection`,
`UpdateCollection`, `DeleteCollection`, `AddTermToCollection`). Errors are RFC 7807 with a
stable `code` (`collection_not_editable`, `collection_not_found`).

**Not built yet:** items add/remove/reorder endpoints, fork/subscribe, and hydrating term
content (Vocabulary) / progress (Learning) onto the detail view.

See root `CLAUDE.md`, `ARCHITECTURE.md`, `.claude/skills/` and `openapi/openapi.yaml`.
Boundaries enforced by `deptrac.yaml`.
