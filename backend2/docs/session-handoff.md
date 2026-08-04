# Session handoff — snapshot

> **Overwrite this file each session. Snapshot of current state, not a growing log.**
> Read with `CLAUDE.md`, `ARCHITECTURE.md`, `.claude/skills/`, `deptrac.yaml`, `docs/ROADMAP.md`.
> `triage-contract-findings.md` is frozen.

Branch: `feat/mobile-backend2-cutover` — **not merged to `main`**. Last updated: 2026-08-04.

---

## Current task: Generation → full feature (in progress)

Turning the generator into the product's headline feature: backend + contract + UI/UX, **Part-C
order**, one commit per point, gates green, `invariant-reviewer` before committing risky diffs.
**A2 (under-delivery), A1 (type taxonomy) and A6 (prompt v3) are DONE, committed, and v3 is live
in production (`PROMPT_VERSION='v3'`).** Next is **A3** (Pexels images). Contract touched only
additively so far; `/sync` + drift not yet touched — safe stopping point.

## What's done this session (with commit hashes)

- **A2 — fix under-delivery** (`f172d0f`) + **refactor** (`faae71b`): overshoot the ask
  `ceil(size*1.3)` capped at 25, validate+trim to requested, one avoid-list top-up if still short
  (no loop), tokens/cost **summed** across both calls; `delivered_count` recorded; `requested`/
  `delivered` on `GET /generations/{id}`. Logic lives in one shared `GenerationPipeline` used by the
  handler **and** `generation:eval`.
- **Post-A2 v2 eval baseline** (`a791316`): `docs/generation-eval-v2-post-a2.json`.
- **Mobile forward-compat** (`243450a`): term `type` is a plain string on the client (no enum → no
  crash); `isPhrase` is now `type != 'word'` so idiom/phrasal_verb (and any future value) behave
  phrase-like.
- **A1 — term type taxonomy** (`130089e`): `TermType` += `Idiom`,`PhrasalVerb` + `isPhraseLike()`;
  `terms_type_check` widened (reversible, no backfill); the 3 phrase-vs-word branches use phrase-like
  semantics; `DraftValidator` + OpenAI schema/mapping + OpenAPI enums accept 4 values.
- **A6 — prompt v3** (`758bf81`): `generate_collection.v3.md` = v2 + type-taxonomy + first-class
  AVOID block (NO `image_api_prompt` — images are A3). Adapter loads the prompt file **by version**;
  `generation:eval --prompt=v3` trials a version without flipping production. 5 starter-collection
  prompts added to the eval set.
- **v3 eval baseline** (`237b795`) + **flip to v3** (`62f9dc9`): see the comparison below.

**Test state:** 218 tests green in Docker; `composer arch` 0 violations, `stan` clean. A2 and A1
diffs each passed `invariant-reviewer` → CLEAN before committing.

## v3 vs v2 — the eval that justified the flip (real gpt-4o)

Both measured through the A2 pipeline. On the **20 prompts shared** with the post-A2 v2 baseline:

| metric | v2 (post-A2) | v3 |
|---|---|---|
| under-delivered | 0 | 0 |
| avg phrase-like % | 57 | 63 (+6) |
| avg idiom+phrasal % | 0 | 9 (in 14/20 prompts) |
| duplicates | 0 | 0 |
| cost (20 prompts) | $0.34 | $0.34 |

The 5 starter collections (v3 only, first-run content) all delivered 15/15; adversarial prompts
still didn't leak the system prompt. v3 adds the taxonomy with no delivery/dedup regression and a
flat cost, so `PROMPT_VERSION` was flipped to `v3`. Caveat: single run, model nondeterministic — the
idiom/phrasal signal (0→9%, structural) and phrase-ratio lift are strong, but re-run `generation:eval`
if a future prompt change needs a fresh comparison. The v3 prompt is ~1150 input tokens (vs ~750 for
v2) — output tokens and cost are unchanged.

## Verified vs. code-only

| Item | How verified | Status |
|---|---|---|
| A2 / A1 / v3 backend | 218 tests green; arch 0, stan clean; `migrate:fresh --env=testing` clean | ✅ (backend) |
| A2 raises delivered on real LLM | real eval, under-delivered 1→0 | ✅ (real-LLM, single run) |
| v3 adds idiom/phrasal without regressions | real eval v3 vs v2-post-a2 (table above) | ✅ (real-LLM, single run) |
| A2 top-up firing on the real model | not observed (overshoot sufficed); unit-tested only | ⚠️ unobserved |
| Mobile `isPhrase` fallback | `flutter analyze` clean; NOT run on device | ⚠️ code-only |
| Anything on the **device** | nothing this session touched a running client | ⚠️ device run pending (Part B) |

## Decisions that must not be silently revised (this feature)

- **A2:** cache stores the FINAL accepted set (after filter+top-up); top-up spend is SUMMED, never
  overwritten. Overshoot/top-up/summation lives ONLY in `GenerationPipeline` — the eval must keep
  going through it, or v-to-v comparisons measure a phantom pipeline.
- **Prompt v2 is frozen**; v3 is the live prompt. A prompt change is a new versioned file + an
  eval-compare against the previous baseline **before** flipping `PROMPT_VERSION` — never accept a
  prompt on vibes. The adapter loads the file named by the version; the recorded `prompt_version`
  and the file used must always match.
- **Client tolerates unknown term types** with a phrase-like fallback (`type != 'word'`); the server
  may add types freely. New types ship to the client immediately (badges come in Part B).
- **v3 excludes `image_api_prompt`** — images are sourced in A3 (Pexels), kept out of the prompt so
  the v2↔v3 eval isolated the taxonomy.
- **System decides composition** — no size slider; client sends маленькая/средняя/большая → 10/15/22.
- Pending-generation card in a drift table with start-up reconciliation (succeeded→drop,
  failed→error+retry, pending/running→poll, 404 or >24h→drop with a log note).
- Pexels attribution stored on the term; images cached per term globally, never overwrite.
- `resets_at` is an absolute UTC instant; quota boundary UTC-day until a profile timezone exists.
- (carried) sync cursor in `sync_meta` not keychain · `since` inclusive · triage `TriagedTerms`
  marker · `restore()` clears token only on 401/403 · process rules change in `.claude/` files.

## What's next — A3 (Pexels images)

`ImageSearchPort` + adapter + fake; async `AttachImagesJob` (throttled, empty result → null, no
retry, skip terms that already have an image); `image_url` + `image_author`/`image_author_url` on
terms, `image_url` on collections; images arrive via `/sync`. Images cached per term globally
(shared terms → one search), never overwriting an existing image. Then **B** (contract/drift v4 +
create screen + generating→ready card + first-contact «Разобрать» + type badges — where the new
idiom/phrasal_verb types get their UI badge).

## Known limitations / deferred (also in ROADMAP)
- A2 top-up path unobserved on the real model (overshoot sufficed); +23% token cost from overshoot
  accepted. v2↔v3 is a single-run comparison.
- The post-A2 v2 baseline is 20 prompts; the v3 baseline is 25 (adds the 5 starters). A future
  matched-set v2 re-run isn't needed unless a v4 comparison wants the starters on both sides.
- No per-user timezone → `resets_at` absolute UTC; revisit the quota day-boundary with the streak.
- Out of scope (next block): extending an existing collection, «как прошло» loop, curated starter
  content wiring, push instead of polling.
- (carried) two-device `client_seq` collision; stale reviews upload pipeline (422s);
  triage-after-reinstall resurrection; stale offline streak/reviews-today; orphan local
  terms/progress not GC'd.

## Running / verifying
- Backend2 in Docker (`wt_app` :8001, `wt_db` :5433). Gates: commit hook runs `composer check`
  (arch+stan+test). Manual: `docker compose exec app composer arch|stan|test`. **`composer stan`
  analyzes `app/` only.**
- `generation:eval [--fake] [--prompt=vN] [--out=path]` — manual quality gauge; the real driver
  costs money and runs the full A2 overshoot+top-up pipeline. Baselines:
  `docs/generation-eval-v2-post-a2.json`, `docs/generation-eval-v3.json`.
- Mobile: `flutter analyze` clean; device run `PATH="/opt/homebrew/bin:$PATH" LANG=en_US.UTF-8 flutter run --release -d 00008110-000A7CCC3492801E`.
