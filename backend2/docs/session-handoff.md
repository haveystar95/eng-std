# Session handoff — snapshot

> **Overwrite this file each session. Snapshot of current state, not a growing log.**
> Read with `CLAUDE.md`, `ARCHITECTURE.md`, `.claude/skills/` (esp. `mobile-sync-contract`,
> `learning-srs`), `deptrac.yaml`, and `docs/ROADMAP.md`. `triage-contract-findings.md` is frozen.

Branch: `feat/mobile-backend2-cutover` (not merged to `main`). Last updated: 2026-08-03.

---

## Current task: offline mode — delta-sync + local DB on the client

**Part 1 (backend `GET /sync`) — DONE this session. Part 2 (client local DB) and Part 3
(collection view screen) — NOT STARTED.** I stopped at the Part 1 commit boundary rather than
begin the client rewrite on low context (a half-migrated client = a broken app).

### Part 1 — landed (gates green: arch 0, stan L8, 201 pest, migrate:fresh clean)

- `4787cc8` — **soft-delete `collection_items`** (the item-removal tombstone mechanism). Partial
  unique `(collection_id, term_id) WHERE deleted_at IS NULL` so a removed term re-adds; the repo
  restores the trashed row on re-add; the one raw reader (`EloquentUserCollectionTermsReader`,
  triage/study) filters `ci.deleted_at`; `items_count` is unaffected (only ever written from the
  aggregate's live count). Reworked from a separate tombstone table after review — soft-delete is
  uniform with `collections` and the partial index was always the answer.
- `029efd5` — **`GET /api/v1/sync`**. Cross-module readers via Application ports (deptrac-clean):
  `CollectionSyncReader` (collections + items with op, live term-ids), Vocabulary `TermChangeReader`,
  Learning `ProgressSyncReader`. `GetSyncDeltaHandler` concatenates the four ordered streams,
  offset-slices one page, hydrates only that page's terms via `TermContentReader`. Opaque
  `SyncCursor` freezes the upper bound so paging is stable. Response: `{server_time, next_cursor,
  has_more, changes:{collections, collection_items, terms, progress}}`. Deletions are `op:delete`
  tombstones; a full snapshot (no `since`) omits deletes; only owned-collection terms sync.
  OpenAPI + tests (snapshot, delta, both tombstones, progress, pagination, empty delta).

### TWO decisions in Part 1 that differ from the brief — read these

1. **`since` is INCLUSIVE (`>=`), not exclusive.** Timestamps are second-precision (Laravel's
   pgsql grammar is `Y-m-d H:i:s`, no microseconds — verified). A strict `>` would silently drop
   any change made in the same second as the client's last sync = data loss. So `>=` is used; it
   re-sends only the boundary second, which the client MUST apply idempotently by id (last-write-
   wins by `updated_at`). Re-applying a row you already have is a no-op. This is the standard
   second-precision delta contract and is documented in OpenAPI.

2. **The sync cursor (`server_time`) must live in the CLIENT'S LOCAL DB, NOT in the keychain
   alongside `client_seq`.** The brief says "persist the cursor next to client_seq in the same
   durable store." That would BREAK reinstall: the keychain survives app deletion but the local DB
   does not, so after a reinstall the client would hold a cursor with an empty DB and fetch only a
   delta — missing the full snapshot. The cursor must be wiped together with the synced data (store
   it in the local DB, e.g. a `sync_meta` row). A fresh install then has no cursor → first sync
   omits `since` → full snapshot. This is the opposite of the brief on purpose.

---

## Part 2 — client local DB as source of truth (NOT STARTED)

The architectural core. Flip the read path from `screen → network` to `screen → local DB →
(background) sync → network`. A screen never awaits the network; no data → empty state, never a
spinner or error.

**Local DB: use `drift` (SQLite).** Reason: drift exposes reactive `watch` queries as streams, so
a Riverpod `StreamProvider` per screen rebuilds automatically when the background sync writes —
which is exactly the `screen ← local DB ← sync` flow. Type-safe, well-maintained, codegen. (sqflite
is lower-level and needs manual change notification.)

Plan:
1. **Drift schema** mirroring the sync payload: `collections`, `collection_items`, `terms`
   (text, type, transcription, translation, example, example_translation), `user_term_progress`,
   plus `sync_meta(server_time)` for the cursor. Model deletes by actually deleting local rows when
   a tombstone arrives.
2. **SyncService**: loop `GET /sync?since=<stored>&cursor=…` until `has_more=false`, applying each
   change by `op` (upsert/delete) inside a transaction, then persist the new `server_time`. First
   run has no `server_time` → full snapshot. Triggers: app start, connectivity regain
   (`connectivity_plus`, already wired + device-verified), app resume. Non-blocking (background).
3. **Read-through providers**: convert `collectionsProvider`, `collectionWordsProvider`,
   `statsProvider` (the read screens) to drift `StreamProvider`s over the local tables. Network is
   no longer read on these paths.
4. **Quiet sync status** — a subtle indicator, no modal errors. Offline is normal.
5. **Do NOT break triage.** Its durable upload queue + `client_seq` (keychain) stay; the deck still
   subtracts locally-pending term ids. Triage FETCHING the queue is a network flow (out of scope);
   only its offline upload must keep working.

## Part 3 — collection view screen (NOT STARTED)

The "metro" screen: a collection's terms with translation, example (phrases), system-TTS
pronunciation (`flutter_tts`, offline), and per-word status from local progress. Reads entirely
from drift; reachable from the collection card. (A `word_edit_dialog`/`collection_detail_screen`
exist; extend or replace for the read view.)

### Device acceptance criteria (next session runs these)
1. Airplane cold start: app opens, all tabs work, collections visible, collection screen shows
   terms + translations + examples, TTS speaks.
2. First run online → airplane: first-sync data is available offline.
3. Server change → sync → airplane: the new state shows offline.
4. Deletion: a deleted collection disappears after sync (no ghost).
5. Triage offline still works (no regression).

---

## On-later decisions (RECORD, do not implement now)

- **Offline training = prefetch ready-made session packages (3–5, refreshed in background), NOT
  client-side session assembly.** Assembling on the client would mean porting `ExerciseSelector`,
  distractor selection with fallback, and near-duplicate exclusion into Dart — duplicating rules we
  consistently keep server-side (grading, "mastered"). Decided; implement with the exercise screens.
- **Review ordering will use `seq_review`** (the keychain counter key already exists; the review
  pipeline is still stale). Sync and the exercise screens hit this at the same time — the review
  upload pipeline needs its `client_seq`/raw-answer rebuild alongside them.

## Known limitations / deferred (ROADMAP)

- **Subscriptions aren't synced** — no read path uses `user_collections` yet; `/sync` covers owned
  collections only. Add when subscribe is wired.
- **Term content edits without a term-row bump aren't delta'd** — detection is on `terms.updated_at`;
  content is set at term creation in this app, so new terms are caught. Fine until a term-content
  edit flow exists.
- Online triage = one POST/swipe; two-device `client_seq` collision; **422-drop path is code-only,
  not device-verified**; per-term cefr badge. All in ROADMAP.

## Running / verifying
- Backend2 in Docker; gates `composer arch && composer stan && composer test`; ngrok domain
  `https://greedily-thermos-finer.ngrok-free.dev` (app default). `migrate:fresh` clean.
- Device: `PATH="/opt/homebrew/bin:$PATH" LANG=en_US.UTF-8 flutter run --release -d 00008110-000A7CCC3492801E`.
  Deleting the app needs a one-time Trust. `debugPrint` is invisible in `--release`.
- Part 1 `/sync` is proven by pest, NOT yet device-run — pair that verification with the Part 2 client work.
