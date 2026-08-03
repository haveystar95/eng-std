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
7. **Offline-first session restore (the most important fix).** `restore()` was calling `/auth/me`
   on every cold start and clearing the token on ANY failure — the first offline launch logged the
   user out AND destroyed their keychain token, killing the whole feature at the front door. Now
   the user is cached in the keychain (survives reinstall); restore returns it immediately and
   refreshes in the background, clearing the token ONLY on a real 401/403. Data loss, not cosmetic.
8. **Sensible offline for a brand-new user's first sign-in.** No token → login screen shows with no
   network call (no white screen/hang); sign-in fails fast with a clear "нужна сеть" and the login
   screen shows a quiet offline hint (`connectivityProvider`).

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
-d 00008110-000A7CCC3492801E` (SPM is disabled + pods installed — see "Build setup" below;
`debugPrint` is invisible in `--release`, so lean on the DB + the on-screen behaviour).

**Run the checks in THIS order — reinstall first.** It exercises the cursor-in-DB deviation; if
that's wrong, every other check would have to be redone on a clean install anyway, so prove it first.

1. **Reinstall (the key deviation check):** with data already synced once, delete the app,
   reinstall, sign in online. The cursor went with the DB → no stored `since` → first sync is a
   FULL snapshot → the app fills up completely (not half-empty). Confirm `sync_meta.sync_cursor`
   is set only after the fill, and the tables are fully populated.
2. **Cold start in airplane:** (after a completed online sync) turn on airplane mode and
   cold-start. App opens, **all three tabs work**, collections visible, a collection opens showing
   terms + translations + examples + per-word status, **TTS speaks**.
3. **Server change → sync → airplane:** change a collection on the server, foreground the app
   (sync runs), airplane, confirm the new state shows.
4. **Deletion:** delete a collection AND a single item on the server → after a sync they disappear
   locally, no ghost. (Covered in code + unit test; confirm on-device.)
5. **Triage regression (last):** offline triage still records + uploads on reconnect as before.

Bonus (not a numbered criterion, but confirm it looks sane): **new user, first launch, no
network** — no token, no DB, sync impossible. Should land on the login screen with the offline
hint + a clear "нужна сеть" on tap; never a white screen or endless spinner. (Handled in code.)

If something fails: the DB is at the app's Documents dir `wordtrainer.sqlite` (pull via Xcode
device container) — inspect `sync_meta` for the cursor and the tables for what synced.

## After the device run passes — one small planned commit

Add `source` + `type` to the `/sync` collections payload (DTOs `CollectionSyncRow`/
`CollectionChange`, the reader `select`, the serializer, the client `_toCollection`). Restores the
"ИИ" badge and the my/store/generated distinction on the collection card, which is wanted going
forward — not just cosmetic. Deferred deliberately: DON'T touch the freshly-validated `/sync`
until the device run proves the current contract. Separate small commit, its own OpenAPI + test.

## Build setup (done this session — don't redo unless it breaks)
Flutter's Swift Package Manager integration was pulling drift's CSQLite SPM package and colliding
with the CocoaPods setup ("Package.swift modified during the build"). Fixed the aligned way:
`flutter config --no-enable-swift-package-manager`, `flutter clean`, `pub get`, then
`ios/ $ pod install` (Homebrew pod 1.17). `sqlite3`/`sqlite3_flutter_libs` now come via Pods like
every other plugin. If SPM re-enables and the error returns, same fix.

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
