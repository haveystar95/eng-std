# Session handoff — snapshot

> **Overwrite this file each session. Snapshot of current state, not a growing log.**
> Read with `CLAUDE.md`, `ARCHITECTURE.md`, `.claude/skills/`, `deptrac.yaml`, `docs/ROADMAP.md`.
> `triage-contract-findings.md` is frozen.

Branch: `feat/mobile-backend2-cutover` — **not merged to `main`**. Last updated: 2026-08-04.

---

## Current task: Generation → full feature — **backend + client complete**

The generator is now a full feature end-to-end: backend (A1–A6 + A3 images), contract, and the
client UX (Part B). **A3 (Pexels images) is done and verified server-side on a real key. Part B (the
client half) is done and code-verified (`flutter analyze` + 23 tests), but NOT yet run on the
device.** The one remaining step for the whole feature is a **device end-to-end run** (scenarios
below). v4 is the live prompt (`PROMPT_VERSION='v4'`).

## What's done this session (with commit hashes)

**A3 — Pexels images (backend):**
- Schema (`196ce20`), `ImageSearchPort`+Pexels adapter+fake (`b1d5f9f`), prompt v4 (`fd47322`),
  flip to v4 (`cba9661`), `AttachImagesJob` (`cc86110`), `/sync`+drift image fields (`c3525ab`).
- Verified end-to-end server-side (`27551f9`): a real `generation:make` attached a Pexels cover +
  8/8 term photos with attribution. invariant-reviewer CLEAN.

**Part B — client generation UX (`42fd584`):**
- **B1 create screen** (`generate_screen.dart`): situation field + rotating placeholder; size
  маленькая/средняя/большая → 10/15/22 (no number); levels default from profile; target-language
  dropdown (source = UI language) — first language choice in the UI, lives on the collection; button
  greys out on exhausted quota with remaining + resets_at (device-local) from `/me`.
- **B2 pending card + reconciliation**: client-only `PendingGenerations` drift table (schema v5),
  survives an app kill; `GenerationController` polls + reconciles on launch/resume (succeeded→sync+drop,
  failed→error card+retry, pending/running→poll, >24h/404→drop+log). Card faces: generating / failed
  («Повторить») / ready (cover, title, count, "получилось N из M"), tap → collection with a
  first-contact «Разобрать» banner.
- **B3 images + type badges**: collection cover on the tile + ready card; term photo on the word card
  with a typed placeholder; badges слово/фраза/идиома/фраз. глагол (unknown→phrase); "Фото: Author ·
  Pexels" credit with a clickable author link (**new dep: `url_launcher`**). Images dock in via the
  drift stream (no reload).
- Data: image fields on `WordCollection`/`Word`; `GenerationQuota`/`GenerationStatusView`; `/me`
  quota parsed but never cached; `jobStatus` carries requested/delivered; fixed the old
  `status=='done'` bug (backend says `succeeded`).

**Also:** removed the synthetic-owner test data from the dev DB (1 collection, 8 terms, 1 request).

## Verified vs. code-only

| Item | How verified | Status |
|---|---|---|
| A3 backend (schema, port, job, /sync) | 241 tests; arch 0, stan clean; invariant-reviewer CLEAN | ✅ (backend) |
| Real Pexels attach on a real generation | live `generation:make` → cover + 8/8 terms imaged w/ attribution | ✅ (real Pexels + gpt-4o) |
| v4 no A1 regression + img% 100% | real eval, 25 prompts (`docs/generation-eval-v4.json`) | ✅ (real-LLM, single run) |
| Part B client (create/pending/images/badges) | `flutter analyze` clean; 23 widget/unit tests green | ⚠️ **code-only — NOT on device** |
| Anything on the **device** | nothing this session ran on a client | ⚠️ **device run pending (the one open step)** |

## Decisions that must not be silently revised (this feature)

- **All client reads come from the local DB** (drift v5; image fields present). The network is used
  ONLY for `POST /generations` and status polling — nothing else in Part B hits the API on a read.
- **The pending-generation card lives in a drift table** (`PendingGenerations`), survives an app
  kill, and is reconciled on launch/resume — never held only in memory.
- **`image_api_prompt` is server-internal** — never shipped in `/sync`, never on the client.
- **Images cached per term globally, never overwritten**; empty result = null (no retry); transient
  = job retry+backoff; image schema gated to v4+.
- **Language lives on the collection** — the create screen's target-language dropdown is the first
  UI language choice; no workspaces.
- **Size is a feel, not a number** — маленькая/средняя/большая → 10/15/22, decided server-side.
- **`/me` generation quota is fetched fresh, never cached** in the persisted user (staleness); the
  server is the real gate — an offline/unknown quota still lets the user try.
- **`resets_at` is an absolute UTC instant**, rendered in device-local time.
- (carried) A2 cache stores the final accepted set; spend summed; prompt vN = versioned file +
  eval-compare before flip; client tolerates unknown term types (phrase-like); sync cursor in
  `sync_meta` not keychain; `since` inclusive; process rules change in `.claude/`.

## What's next — device end-to-end run (the finish line)

Run `flutter run --release -d 00008110-000A7CCC3492801E` and walk these scenarios (each maps to code
that is currently only test-verified):

1. **Generation end-to-end with images** — create a collection ("иду в банк"); the pending card
   shows generating → ready with a real cover; open it; term photos + attribution appear as `/sync`
   lands them (screen updates from the drift stream, no reload); tap an author link → opens Pexels.
2. **Under-delivery** — a prompt the model under-fills; ready card shows "получилось N из M".
3. **Kill during generation** — start a generation, kill the app before it finishes; relaunch → the
   pending card is still there and reconciliation resumes polling → ready (or error).
4. **Quota exhausted** — after the daily limit, the create button is grey with remaining +
   resets_at in local time; submit is blocked client-side.
5. **Offline view after sync** — with the collection synced, go offline; the collection, its words
   and images (cached) still open and render from the local DB.
6. **TTS on a non-standard target language** — generate with a non-en target; the speaker button
   pronounces in that language (`ttsLocaleFor`).

## Known limitations / deferred

- **Whole Part B is unverified on device** — the above run is the gate.
- Study/session cards still come from the network (online-only, pre-existing) and don't show term
  photos — images are on the drift-backed screens (collection tile + word card) by design.
- New client dep **`url_launcher`** (image attribution links). iOS opens https externally without
  Info.plist changes.
- A3 server findings (carried): cache-path collection covers are re-searched (one extra Pexels call
  per cache hit) rather than copying the source URL — accepted.
- (carried) A2 top-up unobserved on the real model; two-device `client_seq` collision; stale reviews
  upload pipeline; triage-after-reinstall resurrection; stale offline streak/reviews-today; orphan
  local terms/progress not GC'd; `/study/progress` field-name mismatch (endpoint unused by the app).

## Running / verifying
- Backend2 in Docker (`wt_app` :8001, `wt_db` :5433). Gates: commit hook runs `composer check`
  (arch+stan+test) for backend, `flutter analyze` for mobile. **`composer stan` analyzes `app/` only.**
- `generation:eval [--fake] [--prompt=vN] [--out=path]` — manual quality gauge (real driver costs
  money). Baselines: `docs/generation-eval-v3.json`, `docs/generation-eval-v4.json`.
- Image search: `IMAGE_DRIVER=fake` + `PEXELS_FAKE_MODE=found|not_found|rate_limited|transient_error`
  exercises the attach job with no network. `PEXELS_API_KEY` is set for real runs.
- Mobile: `flutter analyze` clean; drift codegen `dart run build_runner build`; `flutter test`
  (23 tests). Device: `PATH="/opt/homebrew/bin:$PATH" LANG=en_US.UTF-8 flutter run --release -d 00008110-000A7CCC3492801E`.
