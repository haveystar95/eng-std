# Session handoff — snapshot

> **Overwrite this file each session. Snapshot of current state, not a growing log.**
> Read with `CLAUDE.md`, `ARCHITECTURE.md`, `.claude/skills/`, `deptrac.yaml`, `docs/ROADMAP.md`.
> `triage-contract-findings.md` is frozen.

Branch: `feat/mobile-backend2-cutover` — **not merged to `main`**. Last updated: 2026-08-04.

---

## Current task: Generation → full feature (in progress)

Turning the built-on-the-fly generator into the product's headline feature: backend + contract +
UI/UX. Plan agreed with the user (see ROADMAP "Generation → full feature"); working in **Part-C
order**, one commit per point, gates green, `invariant-reviewer` before `/close-task`. **Contract
(OpenAPI + `/sync` + drift) not yet touched — safe stopping point.**

## What's done this session (with commit hashes)

- **A5 — eval set + `generation:eval`** (`135a056`): `tests/Fixtures/generation-prompts.json` (~20
  prompts) + a manual quality command in `Generation/Presentation/Console` (delivered-vs-requested,
  phrase & idiom+phrasal ratio, dup rate, CEFR spread, image-prompt coverage read reflectively,
  tokens/cost; `--fake` for a no-spend smoke; fake toggled via `config`, no Infrastructure import).
  **Real v2 baseline** at `docs/generation-eval-v2-baseline.json` (20/20 ok, 1 under-delivered, avg
  phrase 56%, idiom/phrasal 0%, no dupes, ~$0.27). Diff v3 against this.
- **A4 — pipeline hygiene, COMPLETE (3 parts):**
  - part 1 (`1201122`): retry only transient errors (a rejected `InvalidGeneratedDraft` fails
    terminally — no 3× re-spend); `recordAttempt()` persists tokens/cost the instant the model
    answers, so a validation failure keeps its spend; truncated raw response on new
    `generation_requests.raw_response`.
  - part 3 (`98d9021`): `GET /me` `generation: {limit, used, remaining, resets_at}` (absolute
    next-UTC-midnight instant, client renders local). New `GetGenerationQuota` query; deptrac edge
    `Identity/Presentation → Generation/Application`; OpenAPI + tests.
  - part 2 (`f3ab9ea`): prompt-cache lookup — on a `(normalized_prompt, langs, prompt_version)` hit,
    reuse the prior collection's **term set** (fresh personal collection, `model='cache'`, no LLM
    call); prompt_version bump invalidates. New `GetCollectionTermSet` (non-owner-scoped Collections read)
    + `findCacheableCollection` repo finder.
- Docs (`4a117cd` + updates): ROADMAP "Generation → full feature" block; **timezone decision made**
  (absolute-UTC-instant; `profiles.timezone` deferred to the streak, then revisit quota day-boundary).

**Test state:** 30 Generation/Identity unit + feature tests green; `composer arch/stan/test` clean.

## What's next (Part-C order) — A2 is the next commit

**A2 — fix under-delivery ("asked 15, got 13").** Self-contained backend. Design worked out this
session (implement fresh):
- **Overshoot:** ask the model for `ceil(requested * 1.3)` (capped at the 25 hard max), not `requested`.
- **Decouple validator target from the model ask:** `DraftValidator::validate` should take an explicit
  target count (the requested size) instead of reading `brief->size`, because the model brief now carries
  the overshoot count. Add a **"supplemental" mode** (or a flag) that skips the `MIN_ITEMS` floor — a
  top-up returning 2 items is valid, not a failure.
- **One top-up, no loop:** if `delivered < requested`, one more `generate` with an **avoid list** of the
  already-accepted texts. Pass the avoid list via a new `GenerationBrief.excludeTexts` and have the
  **adapter append it to the user message** (do NOT edit the frozen `generate_collection.v2.md`; v3 gets
  a proper AVOID block in A6). Merge + dedup + trim to `requested`. Accumulate tokens/cost across both calls.
- **Honest result:** add `generation_requests.delivered_count` (migration + entity + mapper), surface
  `requested`/`delivered` on `GET /generations/{id}` (`GenerationRequestView` + `GenerationRequestResource`
  + OpenAPI). Client shows "13 из 15".
- Tests: overshoot trims to requested; top-up fills a shortfall; still-short is an honest success (not a
  failure) with delivered<requested recorded.

Then: **A1+A6** (type enum `word|phrase|idiom|phrasal_verb` — migration + `TermType` VO + every
word/phrase branch treats idiom/phrasal_verb as phrase-like — + prompt **v3**, then eval-compare v2↔v3
before flipping `PROMPT_VERSION`); **A3** (Pexels images: `ImageSearchPort`/adapter/fake, async
`AttachImagesJob` throttled/empty=null/no-retry/skip-existing; `image_url`+`image_author`/`image_author_url`
on terms, `image_url` on collections; arrive via `/sync`); **B** (contract + drift **v4** with
`PendingGenerations` table + create screen + generating→ready card + first-contact «Разобрать» + type badges).

## Decisions that must not be silently revised (this feature)

- **System decides composition** — no size slider; client sends маленькая/средняя/большая → 10/15/22.
- **Pending-generation card lives in a drift table** (survives app kill) with start-up reconciliation:
  succeeded→drop (collection arrives via sync), failed→error+retry (drop on dismiss), pending/running→poll,
  **404 or >24h old→drop with a log note** (the ceiling against "forever generating").
- **Pexels attribution stored on the term** (`image_author`, `image_author_url`) next to `image_url`,
  shipped in `/sync`; UI credit is unobtrusive. Images cached **per term globally** (shared terms, one
  search), never overwrite an existing image.
- **`resets_at` must not be a naive wall-clock** — return an absolute instant (client renders local);
  quota reset boundary is UTC-day until/unless a profile timezone is added.
- (carried) sync cursor in `sync_meta` not keychain · `since` inclusive · triage `TriagedTerms` marker ·
  `restore()` clears token only on 401/403 · process rules change in `.claude/` files, not silently.

## Verified vs. code-only

| Item | How verified | Status |
|---|---|---|
| `generation:eval --fake` and real v2 baseline | ran in Docker (`wt_app`); baseline JSON committed | ✅ (backend) |
| A4 part 1 (retry/failed-usage/raw) | 18 unit + 6 feature Generation tests green in Docker | ✅ (backend) |
| Commit-gate hook fires on a real session commit | two commits this session passed the gate (not blocked) | ✅ now confirmed by-session |
| `/handoff`, `/audit`, `invariant-reviewer` by name | all three ran this session | ✅ registered |
| Anything on the **device** | nothing this session touched the client yet | ⚠️ device run pending (whole Part B) |

## Offline mode (prior work, still true) — done + device-verified
Delta sync + local drift DB + collection view + triage-from-local: built, device-verified, committed
(`b1a0aeb`, `f4b83ca`, `4f48e91`, …). Cursor in `sync_meta`; deletions/reverse-path/triage verified on
device. See git log if detail is needed.

## Known limitations / deferred (also in ROADMAP)
- **No per-user timezone stored** → `resets_at` is an absolute UTC instant (decided). `profiles.timezone`
  arrives with the **streak** (learning-srs needs local-midnight day boundaries); revisit the quota
  day-boundary then.
- Out of scope for this feature (next block): extending an existing collection, «как прошло» loop,
  curated starter content, push instead of polling.
- (carried) two-device `client_seq` collision; stale reviews upload pipeline (422s); triage-after-reinstall
  resurrection; stale offline streak/reviews-today; orphan local terms/progress not GC'd.

## Running / verifying
- Backend2 in Docker (`wt_app` :8001, `wt_db` :5433). Gates: commit hook runs `composer check`
  (arch+stan+test). Manual: `docker compose exec app composer arch|stan|test`. **`composer stan` analyzes
  `app/` only** — don't be alarmed by phpstan errors when you point it at `tests/` by hand.
- `generation:eval [--fake] [--out=path]` — manual quality gauge (real driver costs money).
- Device: `PATH="/opt/homebrew/bin:$PATH" LANG=en_US.UTF-8 flutter run --release -d 00008110-000A7CCC3492801E`.
