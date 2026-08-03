# Session handoff — snapshot

> **Overwrite this file each session. Snapshot of current state, not a growing log.**
> Read with `CLAUDE.md`, `ARCHITECTURE.md`, `.claude/skills/` (esp. `mobile-sync-contract`,
> `learning-srs`), `deptrac.yaml`, and `docs/ROADMAP.md`. `triage-contract-findings.md` is frozen.

Branch: `feat/mobile-backend2-cutover` (not merged to `main`). Last updated: 2026-08-03.

---

## Current task: offline mode — delta-sync + local DB on the client

**Part 1 (backend `GET /sync`) — DONE (prior session). Part 2 (client local DB + sync) and
Part 3 (collection view screen) — DONE this session, in code. NOT YET RUN ON THE DEVICE.**

The whole offline read path was built and the gates are green (`flutter analyze` clean, all
Dart tests pass, backend untouched so its arch/stan/pest stay as Part 1 left them). But per this
project's hard-won rule — the device has disproved correct-looking code three times — **treat the
client offline path as UNVERIFIED until the acceptance run below passes.** The `/sync` endpoint
itself is also still only pest-proven, never device-run; the acceptance run covers it too.

### What landed this session (each a separate commit, in order)

1. **drift local DB** (`mobile/lib/data/local/app_database.dart` + generated `.g.dart`). Tables
   mirror the `/sync` payload: `collections`, `collection_items`, `terms`, `term_progress`, plus a
   `sync_meta` key/value. `applyDelta()` writes one page atomically (upsert=LWW by id,
   tombstone=row delete). **The cursor lives in `sync_meta`, NOT the keychain** (the Part-1
   deviation — a reinstall wipes it with the data → next sync is a full snapshot).
2. **SyncService** (`mobile/lib/data/local/sync_service.dart`). Pages `GET /sync?since=&cursor=`
   until `has_more=false`, applies each page, advances the cursor **only after the whole run**
   (mid-fail → re-fetch from old `since`, idempotent). Triggers: app start, network return
   (`connectivity_plus`), app resume — wired in `home_screen.dart`. Non-blocking, silent offline.
   Caches streak/reviews-today from `/stats` opportunistically (they're not in the delta feed).
3. **Read-path flip** (`providers.dart`). `collectionsProvider`, `collectionWordsProvider`,
   `statsProvider`, `collectionsProgressProvider` are now **drift `StreamProvider`s over the local
   DB** — never the network. No data → empty state, never a spinner/error. total/learned/mastered/
   due are folded locally from synced progress (mirrors `Learning\Mastery`, interval≥21); streak/
   reviews from the cache. Mutations now call `syncService.sync()` (pull the change) instead of
   invalidating a stream over unchanged local state. Dead API read methods removed. `dueCards`
   stays network (sessions are out of scope; read via `.value`, degrades to null offline).
4. **Quiet sync indicator** — a 2.5px hairline under the status bar, only while syncing. Offline
   is silent by design.
5. **Collection view (Part 3)** — `collection_detail_screen.dart` already read the flipped
   `collectionWordsProvider` with system TTS; added a per-word status badge (Выучено/Усвоено/Учу;
   not-started shows nothing) from local progress. This IS the "metro" screen; entry is the
   collection card. Fully offline.
6. **Unit tests** (`test/sync_apply_test.dart`) pin the delta application against in-memory SQLite:
   upsert=LWW, both tombstone kinds, inclusive-boundary no-op, cursor round-trip, clearAll.

### Backend verification asked for in the brief — BOTH CLEAN, no code changed

- **Same-second pagination:** safe. The cursor is an offset into a re-materialised, frozen-`upper`
  stream; every reader orders by `(updated_at, <unique id>)` — a total order identical across
  page requests. The tiebreaker is real; no boundary loss.
- **Soft-delete leakage:** none. Only raw `collection_items` readers are the sync reader (must see
  tombstones) and `EloquentUserCollectionTermsReader` (all 3 methods filter `ci.deleted_at`). Model
  uses `SoftDeletes`; Learning never touches the table directly. Session assembly + progress both
  route through that one filtered port.

---

## THE NEXT STEP: device acceptance run (I watch logs + DB; you run the phone)

Same loop as triage. Run: `PATH="/opt/homebrew/bin:$PATH" LANG=en_US.UTF-8 flutter run --release
-d 00008110-000A7CCC3492801E` (first build re-runs `pod install` for `sqlite3_flutter_libs`;
`debugPrint` is invisible in `--release`, so lean on the DB + the on-screen behaviour).

1. **First run online → airplane:** open the app online once (let the sync indicator finish), then
   turn on airplane mode and cold-start. App opens, **all three tabs work**, collections visible,
   a collection opens showing terms + translations + examples + per-word status, **TTS speaks**.
2. **Server change → sync → airplane:** change a collection on the server, foreground the app
   (sync runs), airplane, confirm the new state shows.
3. **Deletion:** delete a collection AND a single item on the server → after a sync they disappear
   locally, no ghost. (Covered in code + unit test; confirm on-device.)
4. **Reinstall (the key deviation check):** delete the app, reinstall, sign in. The cursor went
   with the DB → first sync is a full snapshot → the app fills up completely (not half-empty).
5. **Triage regression:** offline triage still records + uploads on reconnect exactly as before.

If something fails: the DB is at the app's Documents dir `wordtrainer.sqlite` (pull via Xcode
device container) — inspect `sync_meta` for the cursor and the tables for what synced.

---

## On-later decisions (RECORD, do not implement now)

- **Offline training = prefetch ready-made session packages, NOT client-side session assembly.**
  Porting `ExerciseSelector`/distractors/dedup to Dart duplicates server-owned rules. Implement
  with the exercise screens.
- **Review ordering will use `seq_review`**; the review upload pipeline still needs its
  `client_seq`/raw-answer rebuild (stale, 422s every flush). Pair with the exercise screens.

## Deferred findings from this session — all in ROADMAP

See ROADMAP's "Deferred from the offline-mode build" block. Headlines: `/sync` collections omit
`source`/`type` (AI badge gone — cosmetic); `/study/progress` field names never matched the client
(bars were dead online, now derived locally, endpoint unused); streak/reviews are cached not
delta'd (stale offline); local orphan terms/progress after a collection delete aren't GC'd
(harmless). None corrupt data — hence deferred, not fixed inline.

## Not in scope (unchanged)
- Exercise/session screens, offline training + package prefetch, language workspaces
  (`listening`/`cloze`), `seq_review` wiring in the reviews pipeline.

## Running / verifying
- Backend2 in Docker; gates `composer arch && composer stan && composer test`; ngrok domain
  `https://greedily-thermos-finer.ngrok-free.dev` (app default). backend2 still needs its own
  ngrok/tunnel for on-device use (see `../mobile/CLAUDE.md`). Mobile gates: `flutter analyze` clean,
  `flutter test` green.
