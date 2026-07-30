---
name: mobile-sync-contract
description: The offline-first contract between this backend and the Flutter/iOS client — delta sync, client-generated ULIDs, conflict rules, payload shape, push notifications and API versioning. Consult this skill whenever a change could affect the mobile app: new or changed response fields, anything the user does while offline (studying, creating collections), sync endpoints, breaking changes, or when deciding what a list endpoint should return.
---

# Mobile sync contract

The app must work on a plane. That constraint shapes the API more than anything else.

## Principles

1. **The client owns a local database** (SQLite/Drift) mirroring collections, terms and
   progress. The API's job is to hand over deltas, not to be queried per screen.
2. **Writes that happen offline get client-generated ULIDs.** The client is allowed to
   create ids for reviews and custom collections. The server accepts them and treats a
   repeat as a duplicate, not an error.
3. **Reviews are append-only**, which is why offline study has no merge conflicts.
   Never turn a review into a mutable row.
4. **Everything else is last-write-wins by `updated_at`**, with the server as tiebreaker.
   Say so explicitly in the OpenAPI description of each mutable resource.

## Delta sync

```
GET /api/v1/sync?since=2026-07-20T10:00:00Z&cursor=<opaque>
```

```json
{
  "server_time": "2026-07-27T09:12:00Z",
  "next_cursor": "eyJ0IjoiMjAy...",
  "has_more": false,
  "changes": {
    "collections":  [{"id": "...", "op": "upsert", "...": "..."}],
    "collection_items": [{"collection_id": "...", "term_id": "...", "op": "delete"}],
    "terms":        [{"id": "...", "op": "upsert", "...": "..."}],
    "progress":     [{"term_id": "...", "state": "review", "due_at": "...", "...": "..."}],
    "settings":     {"new_terms_per_day": 10}
  }
}
```

- Deletions ship as tombstones (`op: "delete"`), which is why `deleted_at` exists on
  `collections` — hard deletes make offline clients keep ghosts forever.
- The client stores `server_time` and sends it as the next `since`. Never let the client
  compute the cutoff from its own clock — device clocks drift and skip changes.
- Page with `next_cursor` until `has_more` is false. First sync after install is a full
  snapshot delivered through the same endpoint with `since` omitted.
- Only ship terms the user actually has (their collections + subscribed shared ones).
  The global dictionary is not synced.

## Upload

```
POST /api/v1/reviews/batch          up to 200 reviews, client ULIDs, per-item results
POST /api/v1/collections            accepts a client-provided id
POST /api/v1/collections/{id}/items idempotent by (collection_id, term_id)
```

Never fail a whole batch for one bad item. The client retries the batch on any network
error, so partial acceptance plus idempotency is what keeps data from being lost or doubled.

## Term creation from the client

Offline, a user can add a word that doesn't exist server-side yet. The client stores it
with a local ULID and, on sync, calls `POST /terms/lookup` with the text; the server
returns the canonical term (existing or newly created) and the client **remaps its local
id to the canonical one**. Terms are global and deduplicated — the client does not get to
mint canonical term ids, only review and collection ids.

Document this remap in the OpenAPI response: `{"id": "canonical", "matched": true}`.

## Payload discipline

Mobile users are on cellular. Every field costs battery and bytes.

- List endpoints return summaries; details on demand.
- Audio and images are URLs on a CDN with long cache headers, never base64 in JSON.
- Support `gzip`/`br`; return `ETag` on collection details so a re-fetch is a 304.
- A study session payload must be self-contained — the client cannot make another call
  mid-session, so include everything needed to render every card and grade it.

## Push

`CollectionGenerated`, `StreakAtRisk` and `DailyReviewReminder` are pushed via APNs.
Push carries an id, never content — the client fetches through the normal API so payloads
stay small and permissions stay enforced.

## Versioning and breaking changes

> **Pre-release: breaking changes are free, no deprecation cycle. Revisit at App Store launch.**
> There are no shipped clients — the app and this API move together, and the database can be
> wiped. Break the contract cleanly (rename, retype, remove a field, narrow an enum) and update
> the client in the same change. A dead endpoint kept "for compatibility" is just code to
> maintain that someone will mistake for a live contract — delete it instead of deprecating it.

Once there are clients in the wild (App Store), the App Store means old builds live for months,
and the rules below start to apply:

- Adding an optional field is safe. Removing or renaming a field, changing a type, or
  narrowing an enum is **breaking** and requires `/api/v2` alongside `/api/v1`.
- Enums are the classic trap: the client parses `state`, `grade`, `exercise_mode`. Adding a
  value breaks old builds. Add new enum values only with a documented fallback, and have the
  client treat unknown values as a neutral default from day one.
- Every response includes `X-Api-Version`; the client sends `X-Client-Version`. Log the pair
  so you know when a version is safe to retire.
- Deprecate with `Deprecation` and `Sunset` headers, and give at least two release cycles.

## Checklist for any API change

1. Can a client from three months ago still parse this response?
2. If this write can happen offline, is it idempotent and does it carry a client ULID?
3. Do deletions produce tombstones that reach `/sync`?
4. Is the new field actually needed on the list endpoint, or only on details?
5. Is the OpenAPI spec updated so the Dart client regenerates cleanly?
