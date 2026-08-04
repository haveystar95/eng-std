# Session handoff — snapshot

> **Overwrite this file each session. Snapshot of current state, not a growing log.**
> Read with `CLAUDE.md`, `ARCHITECTURE.md`, `.claude/skills/`, `deptrac.yaml`, `docs/ROADMAP.md`.
> `triage-contract-findings.md` is frozen.

Branch: `feat/mobile-backend2-cutover` — **not merged to `main`**. Last updated: 2026-08-04.

---

## Current task: Generation → full feature (in progress)

Turning the generator into the product's headline feature: backend + contract + UI/UX, **Part-C
order**, one commit per point, gates green, `invariant-reviewer` before finishing. **A5, A4, A2,
A1, A6 and now A3 (Pexels images) are DONE and committed.** v4 is live (`PROMPT_VERSION='v4'`).
The next block is **Part B** (client screens: the create flow, generating→ready card, image
display + type badges, first-contact «Разобрать»). A3 touched `/sync` + the mobile drift schema
**additively** (no rename) — safe stopping point; the just-landed changes are **not device-run**.

## What's done this session — A3 Pexels images (with commit hashes)

- **Schema** (`196ce20`): nullable `image_url`, `image_api_prompt`, `image_author`,
  `image_author_url` on `terms` **and** `collections`. Additive, no backfill, no constraint.
- **ImageSearchPort + adapters** (`b1d5f9f`): `ImageSearchPort` (Generation/Application/Port,
  mirrors `CollectionGeneratorPort`) → `PexelsImageSearch` + `FakePexelsImageSearch`. Failures
  classified: empty result = `null` (no retry); rate-limit/5xx/network = `TransientImageSearchError`
  (retryable); bad key (401) fails loudly. Config `services.pexels.*` + `IMAGE_DRIVER=pexels|fake`.
- **Prompt v4 + eval** (`fd47322`): v4 = v3 + per-item `image_api_prompt` + top-level
  `collection_image_prompt`; image fields gated into the structured-output schema for **v4+ only**
  (v2/v3 stay frozen). Eval baseline `docs/generation-eval-v4.json`.
- **Flip to v4** (`cba9661`): `PROMPT_VERSION='v4'`, isolated commit after the eval.
- **AttachImagesJob** (`cc86110`): async, best-effort job dispatched (via a
  `DispatchesImageAttachment` port) after generation succeeds — **fresh and prompt-cache paths**.
  Reads only terms/collection lacking `image_url` but carrying a query, searches Pexels, writes
  url + attribution. Never overwrites (a shared term is imaged once); empty = null-no-retry;
  transient = job retry+backoff (tries=3); throttle lives in the Pexels adapter. `image_api_prompt`
  rides through `ImportTerm`/`CreateGeneratedCollection`, ensure-style (back-filled on dedup, never
  overwritten).
- **/sync + drift** (`c3525ab`): `image_url` + `image_author` + `image_author_url` ship additively
  in the term and collection sync payloads (`image_api_prompt` stays server-internal, never shipped);
  OpenAPI `/sync` schema updated. Mobile drift `Terms`/`Collections` gain the same three columns
  (schema v4 + `addColumn` migration) and populate them in the sync apply. **No client screens.**
  Same commit fixes a latent test-isolation flake (RefreshDatabase on the two outbound-calling
  generation tests, whose `api_request_logs` writes were leaking).

**Test state:** 241 backend tests green in Docker (3× consecutive `composer test` to confirm the
flake fix); `composer arch` 0 violations, `stan` clean. Mobile: `flutter analyze` clean, widget
tests green, `build_runner` regenerated. The full A3 diff passed **`invariant-reviewer` → CLEAN**.

## v4 vs v3 — the eval that justified the flip (real gpt-4o, 25 prompts)

| metric | v3 | v4 |
|---|---|---|
| under-delivered | 0 | 0 |
| avg phrase-like % | 65 | 64 |
| avg idiom+phrasal % | 8 | 8 |
| duplicates | 0 | 0 |
| **img% (items with a non-empty image query)** | — | **100%** |
| cost (25 prompts) | $0.43 | $0.49 |

No A1 regression from adding the image field; img% is a clean 100%; cost +15% from the extra
image-prompt output tokens (expected). The single eval error (`adv_inject` → empty content) is a
nondeterministic adversarial refusal — the same prompt succeeds 3/3 on retry and never leaks the
system prompt. Single run, model nondeterministic — re-run `generation:eval` if a v5 needs a fresh
compare.

## Verified vs. code-only

| Item | How verified | Status |
|---|---|---|
| A3 backend (schema, port, job, /sync) | 241 tests green; arch 0, stan clean; migrations applied on dev DB | ✅ (backend) |
| Never-overwrite / empty=null / transient=retry | unit (Term aggregate) + feature (all FakePexels modes, Postgres) | ✅ (backend) |
| v4 no A1 regression + img% 100% | real gpt-4o eval, 25 prompts (table above) | ✅ (real-LLM, single run) |
| Real Pexels attach on a real generation | key set; live `generation:make "иду открывать счёт в банке"` → cover + 8/8 terms imaged with attribution | ✅ (real Pexels + real gpt-4o) |
| A2 top-up firing on the real model | not observed (overshoot sufficed); unit-tested only | ⚠️ unobserved (carried) |
| Mobile drift v4 + sync mapping | `flutter analyze` + widget tests green; `build_runner` ran | ⚠️ code-only |
| Anything on the **device** | nothing this session touched a running client | ⚠️ device run pending (Part B) |

## Decisions that must not be silently revised (this feature)

- **`image_api_prompt` is server-internal** — the model's search query, stored on term/collection,
  used by AttachImagesJob, and **never shipped in `/sync`**. Only `image_url` + `image_author` +
  `image_author_url` reach the client.
- **Images cached per term globally, never overwritten** — a term is searched once; the first photo
  is stable and shared by every collection referencing it (`Term::attachImage` no-ops if imaged;
  the pending-reader filters `image_url IS NULL`). Same never-overwrite for the collection cover.
- **Empty search result = null, no retry** (a valid placeholder-on-client state). Only
  rate-limit/5xx/network is `TransientImageSearchError` → job retries with backoff. A bad key fails
  loudly (non-transient).
- **Image schema is gated to v4+** — v2/v3 are frozen and must not be forced to emit image queries,
  or the isolated v2↔v3 taxonomy eval stops meaning anything. The adapter adds the image fields to
  the structured-output schema only when the prompt version's number is ≥ 4.
- **Attach is best-effort and out-of-band** — dispatched AFTER generation succeeds, through a port,
  and never blocks or fails the generation. A terminal job failure just leaves null images.
- **Throttle lives in the Pexels adapter** (not the caller) so spacing travels with the vendor and
  the fake stays instant.
- **A3 touched `/sync` + drift additively only** — no field renamed; the device-verified sync
  contract is untouched.
- (carried) A2 cache stores the FINAL accepted set; spend is SUMMED; overshoot/top-up live only in
  `GenerationPipeline` (the eval must go through it). Prompt vN is a versioned file + an eval-compare
  before flipping. Client tolerates unknown term types (phrase-like fallback). System decides
  composition (маленькая/средняя/большая → 10/15/22). Pending-generation card in a drift table with
  start-up reconciliation. `resets_at` absolute UTC; quota UTC-day. Sync cursor in `sync_meta` not
  keychain; `since` inclusive; triage `TriagedTerms` marker; process rules change in `.claude/`.

## What's next — Part B (client UI/UX)

First concrete step: **add a `PEXELS_API_KEY` to `backend2/.env`** (dashboard key) and run one real
generation to confirm photos actually attach and reach the device via `/sync` — this is the one
unobserved end-to-end link. Then Part B: the create screen, the generating→ready card, image
display on the collection card + study card (drift columns are already there), per-item type badges
(where the new idiom/phrasal_verb types get their UI), and the first-contact «Разобрать». Then the
deferred blocks (extending an existing collection, «как прошло» loop, curated starter wiring, push
instead of polling).

## Known limitations / deferred (also in ROADMAP)

- **A3 verified end-to-end server-side** — `PEXELS_API_KEY` set; a live `generation:make` attached a
  real Pexels cover + all 8 term photos with attribution. Remaining gap: **not run on the device** —
  the `/sync` image fields and drift v4 migration are code-only (the `/sync` serializer is unit-proven
  but the phone hasn't pulled real image URLs yet). Part B closes this.
- Cache-path collection covers are re-searched (one extra Pexels call per cache hit) rather than
  copying the source collection's URL — accepted (terms are already imaged and skipped; Pexels
  budget is ample). Only the cover query is copied, not the URL/attribution.
- A2 top-up path unobserved on the real model; +23% token cost from overshoot accepted. v-to-v
  evals are single-run.
- No per-user timezone → `resets_at` absolute UTC; revisit the quota day-boundary with the streak.
- (carried) two-device `client_seq` collision; stale reviews upload pipeline (422s);
  triage-after-reinstall resurrection; stale offline streak/reviews-today; orphan local
  terms/progress not GC'd; `/study/progress` field-name mismatch (endpoint unused by the app).

## Running / verifying
- Backend2 in Docker (`wt_app` :8001, `wt_db` :5433). Gates: commit hook runs `composer check`
  (arch+stan+test). Manual: `docker compose exec app composer arch|stan|test`. **`composer stan`
  analyzes `app/` only.**
- `generation:eval [--fake] [--prompt=vN] [--out=path]` — manual quality gauge; the real driver
  costs money and runs the full A2 overshoot+top-up pipeline. Baselines:
  `docs/generation-eval-v3.json`, `docs/generation-eval-v4.json`.
- Image search: `IMAGE_DRIVER=fake` (or bind `FakePexelsImageSearch` in a test) exercises the attach
  job with no network; modes `found|not_found|rate_limited|transient_error` via `PEXELS_FAKE_MODE`.
- Mobile: `flutter analyze` clean; drift codegen `dart run build_runner build`; device run
  `PATH="/opt/homebrew/bin:$PATH" LANG=en_US.UTF-8 flutter run --release -d 00008110-000A7CCC3492801E`.
