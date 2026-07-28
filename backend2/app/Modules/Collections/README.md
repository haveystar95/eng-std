# Collections

**Owns:** collections (system/shared/custom), items, subscriptions, forks

Layers: `Domain` (pure PHP, no Laravel) · `Application` (Commands/Queries/Ports/DTOs) ·
`Infrastructure` (Eloquent, adapters, migrations, provider) · `Presentation` (Http).

## Ownership model

- `type=custom` + `owner_id` → the user's own, editable (`assertEditableBy` = owner only).
- `type=system|shared` + `owner_id=NULL` → curated platform content, read-only for users.
  (No "system user" — ownerless is the whole point.) Adding a shared collection to "my
  collections" is a row in `user_collections`, not a copy; forking copies `collection_items`.

## HTTP (`/api/v1`, `auth:sanctum`)

- `GET /collections` — the user's collections, cursor-paginated (newest first by ULID),
  summaries only: `{data: [...], meta: {next_cursor, has_more}}`.
- `POST /collections` — create custom; accepts a client ULID `id` for offline idempotency
  (re-send ≠ duplicate); 201.
- `GET /collections/{id}` — detail with items (owner-only → 404 otherwise).
- `PATCH /collections/{id}` — update title/description (owner-only → 403).
- `DELETE /collections/{id}` — soft-delete (tombstone for delta sync).

Reads go through Query handlers (`ListUserCollections` via a reader, `GetCollection` via the
repo); writes through Commands (`CreateCustomCollection`, `CreateGeneratedCollection`,
`UpdateCollection`, `DeleteCollection`, `AddTermToCollection`). Errors are RFC 7807 with a
stable `code` (`collection_not_editable`, `collection_not_found`).

**Not built yet:** items add/remove/reorder endpoints, fork/subscribe, and hydrating term
content (Vocabulary) / progress (Learning) onto the detail view.

See root `CLAUDE.md`, `ARCHITECTURE.md`, `.claude/skills/` and `openapi/openapi.yaml`.
Boundaries enforced by `deptrac.yaml`.
