# Session handoff — snapshot

> **Overwrite this file each session. It is a snapshot of current state, not a growing log.**
> Read it alongside `CLAUDE.md`, `ARCHITECTURE.md`, `.claude/skills/` (esp. `learning-srs`,
> `mobile-sync-contract`), `deptrac.yaml`, and `docs/triage-contract-findings.md` (now frozen).

Branch: `feat/mobile-backend2-cutover` (not merged to `main`). Last updated: 2026-08-03.

---

## The "triage contract" task is CLOSED

Everything the triage vertical slice surfaced is resolved or deliberately deferred.
`docs/triage-contract-findings.md` carries the per-finding resolution table and is now frozen —
**new findings go to `docs/ROADMAP.md`, not there.**

### Landed across the last few sessions

- **client_seq ordering** (`72ca50e`, mobile `773d47f`) — the current triage verdict and the
  review fold are ordered by a per-user monotonic `client_seq`, never the device clock. Fixes
  silent data corruption (a lagging clock rolling back a later verdict). `GET /sync/cursor`
  exposes the high-water mark; the client seeds its counter from it on login.
- **BUG-1** (`154f6c9`) — triaged cards no longer reappear on re-entry (deck re-fetches on entry).
- **422/413 drop** (`349ec11`) — permanent rejects are dropped with a log, not retried forever.
- **word latency floor** (`c175d18`) — triage risk on single words is cefr-only.
- **Задача 3 — `remaining` envelope** (`ff9711c`) — `GET /triage/queue` → `{cards, remaining}`;
  `remaining` = eligible terms AFTER this page (what the next GET serves), not the collection
  total. Client shows "N из 40" over the loaded page and "Ещё N после синхронизации".
- **Задача 4 — chunked upload** (`74e94a6`) — durable queues flush in 100-item chunks
  (server cap 200); partial success saved, transient failure keeps the rest, 422/413 chunk
  dropped without blocking others. Triage + review sync.
- **Задача 5 — over-fetch** (`f157735`) — `transcription` / `example*` documented as optional,
  reserved for future exercise modes; kept, not cut.
- **Seeder** (`031cdd8` bumped to 60 for the device cap test, then `4137dab` reverted to a
  realistic 35). Idempotent.

### Verified on device this session (2026-08-03)

- **Задача 3 end-to-end** — 60-term collection: page = 40 + `remaining 20`, screen "1 из 40";
  finishing the page showed "Пачка разобрана" + "Ещё 20 после синхронизации" (NOT "Всё
  разобрано"); re-entry loaded the 20 with `remaining 0`; all 60 triaged once, no dupes.
- **Задача 4 — interrupt between chunks** (chunk temporarily set to 5 + a 3s inter-chunk pause,
  both reverted after): 12 offline swipes; cut the network after chunk 1 → server had exactly 5,
  the other 7 stayed queued; on restore they flushed as 5+2 → 12 total, 12 distinct ULIDs, the
  first 5 NOT re-sent. This is the case code review can't show — the reason it was worth running.

### Verified by code review only — treat as unverified

- **The 422-drop branch itself.** The chunk interrupt test exercised the *transient-failure*
  path (keep + retry); the *permanent-reject* (422/413) drop was not triggered on device
  (release hides the log; the reviews screen is stale). The logic is a small shared branch
  (`isPermanentReject`), but it has not run on a device.

---

## Decisions that must not be silently revised

- Progress is keyed `(user_id, term_id)`, never a collection item. Terms globally deduplicated.
- `reviews` and `term_triages` are append-only; idempotent by client ULID.
- **Ordering is by `client_seq`, never the device clock.** Within one batch all rows share
  `created_at`, so it can't disambiguate — the monotonic `client_seq` is the order key.
- **`remaining` is computed after excluding triaged/studied** — the same count the next GET
  serves, never "total new in the collection". A total that diverges from the real queue is the
  "усвоено" class of bug.
- **`/sync/cursor` lives in Learning, not `/auth/me`** (deptrac forbids Identity→Learning; it's
  a Learning concept and will grow next to `GET /sync?since=`).
- Server grades; the client's local check must be no stricter. Recognition modes never award
  `easy`. `Mastery::isMastered` is the one definition of "mastered".
- Pre-release: contract breaks cleanly, no deprecation.
- **Collection size is not limited; generation is** (draft validator 8–25). User-grown
  collections just add terms into `new` → next triage.

---

## Known limitations / deferred (in ROADMAP, not blockers)

- **Online triage = one POST per swipe** (~35/deck) — battery + log noise. Left as-is
  deliberately: batching online is an unverifiable behaviour change and the durable queue
  already guarantees delivery. If done: small size-based batches + keep the immediate flush of
  the last swipe on screen-exit.
- **`client_seq` collides across two devices** used in parallel — accepted for pre-release; the
  real fix (server-assigned arrival order) belongs with delta-sync / multi-device.
- **Reviews upload pipeline is stale** (pre-`client_seq`, pre-raw-answer) → 422s every flush;
  `349ec11`/`74e94a6` keep those from wedging the queue. Wire it up with the exercise/session
  screens; the `seq_review` counter is ready.
- **Per-term `cefr` badge** on the triage card — field exists, card omits it by design.
- **Offline entry to Разбор shows a load error** (the deck re-fetches on entry) — expected, not
  a bug; the durable queue is intact.
- **`debugPrint` is invisible under `flutter run --release`** — diagnose via `api_request_logs`
  + DB, not app logs.

---

## Next task (separate session — do NOT start on low context)

**Delta sync `GET /sync?since=`** + client reconciliation — the one real gap for true offline.
Builds on the same model (client ULIDs, `client_seq`, append-only logs, the `/sync/cursor`
cursor). Everything else is offline-*friendly* but not full sync. See ROADMAP "finish line".

---

## Running / verifying (recipes)

- Backend2 in Docker (`backend2/`): `docker compose up -d`; artisan via
  `docker compose exec app php artisan …`; Postgres on `localhost:5433`
  (`wordtrainer`/`wordtrainer`/`secret`). Code is volume-mounted (live).
  Gates: `composer arch && composer stan && composer test` (currently: arch 0, stan clean,
  193 pest green; `migrate:fresh` clean).
- Backend2 is fronted by the stable ngrok domain `https://greedily-thermos-finer.ngrok-free.dev`
  (the app's `apiBaseUrl` default already points there — no `--dart-define` needed).
- App on device (`mobile/`), wireless iPhone `00008110-000A7CCC3492801E`:
  `PATH="/opt/homebrew/bin:$PATH" LANG=en_US.UTF-8 flutter run --release -d <id>`.
  Deleting the app needs a one-time "Trust" (Settings → General → VPN & Device Management).
  `connectivity_plus`/`flutter_secure_storage` resolve via SPM; only `flutter_tts` uses CocoaPods.
  `flutter run --release` does not surface `debugPrint`.
- On-device observation: watch `api_request_logs` (method/path/status/bodies, redacted) + DB.
- To reproduce the Задача 3 device check: `migrate:fresh`, sign in on the device (creates the
  user), `db:seed --class=TriageDemoSeeder` — but at 35 terms the cap no longer leaves a
  remainder; bump the seeder temporarily if you need >40 again.
