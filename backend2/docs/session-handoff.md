# Session handoff — snapshot

> **Overwrite this file each session. Snapshot of current state, not a growing log.**
> Read with `CLAUDE.md`, `ARCHITECTURE.md`, `.claude/skills/`, `deptrac.yaml`, `docs/ROADMAP.md`.
> `triage-contract-findings.md` is frozen.

Branch: `feat/mobile-backend2-cutover` — **not merged to `main`**. Last updated: 2026-08-04.

---

## Current task: Generation → full feature (in progress)

Turning the generator into the product's headline feature: backend + contract + UI/UX. Working in
**Part-C order**, one commit per point, gates green, `invariant-reviewer` before `/close-task`.
**A2 (under-delivery fix) is DONE and committed, verified on the real LLM.** Next is A1+A6 (type
taxonomy + prompt v3) — a readiness assessment is at the bottom; **not started**. Contract touched
only additively so far (`GET /generations/{id}` gained `requested`/`delivered`); `/sync` + drift not
yet touched — still a safe stopping point.

## What's done this session (with commit hashes)

- **A2 — fix under-delivery** (`f172d0f`): overshoot the model ask `ceil(size*1.3)` capped at 25,
  validate+dedup+trim to the requested size, and if still short do **one** top-up with an avoid-list
  (no loop) → merge+dedup+trim. Tokens/cost **summed** across both calls, never overwritten. New
  nullable `generation_requests.delivered_count`; `requested`/`delivered` surfaced on
  `GET /generations/{id}` + OpenAPI. Prompt v2 untouched — the avoid list rides in the user message.
- **A2 refactor** (`faae71b`): the overshoot+top-up+summed-spend logic extracted into
  `Generation/Application/Service/GenerationPipeline`, shared by `ProcessGenerationHandler` **and**
  `generation:eval`. Before this the eval bypassed the pipeline (single raw call), so a post-A2
  baseline wouldn't reflect A2 and a v2↔v3 compare would misattribute A2's gains to the v3 prompt.
  Behaviour unchanged: 212 tests pass with no expectation edits; handler constructor unchanged.
- **Post-A2 v2 eval baseline** (`a791316`): real-driver `generation:eval` re-run through the A2
  pipeline → `docs/generation-eval-v2-post-a2.json` (beside the old `…-v2-baseline.json`).
- (earlier this session) A5 eval set (`135a056`), A4 parts (`1201122`, `98d9021`, `f3ab9ea`).

**Test state:** 212 tests green in Docker; `composer arch` 0 violations, `stan` clean. A2 diff was
run past `invariant-reviewer` → CLEAN before the first A2 commit.

## A2 measured on the real LLM (this is the direct check the last handoff flagged as code-only)

Ran `generation:eval` (real gpt-4o, prompt v2) through the A2 pipeline, diffed vs the pre-A2 baseline:

| | before (pre-A2) | after (A2) |
|---|---|---|
| under-delivered (of 20) | **1** (`short_coffee` 9/10) | **0** (now 10/10) |
| every other prompt | delivered == requested | delivered == requested |
| avg phrase % | 56 | 57 |
| duplicates | 0 | 0 |
| total cost | $0.2746 | $0.3367 (+23%) |

- **The overshoot alone closed the gap on this run — no top-up fired** (primary `raw` came back
  ≥ requested for all 20). The top-up is the safety net for when overshoot isn't enough, and the
  +23% cost is the price of asking ~30% more items every time.
- Caveat: the old baseline had only 1 shortfall and the model is nondeterministic, so this is a
  single-run before/after, not a large sample. The *mechanism* is proven (raw lifted well above
  requested, guaranteeing a full trim); the top-up path itself is only unit-tested, not yet seen
  firing against the real model.

## Verified vs. code-only

| Item | How verified | Status |
|---|---|---|
| A2 overshoot/top-up/summed-spend/delivered_count | 212 tests green in Docker; arch 0, stan clean | ✅ (backend) |
| A2 migration reversible + applies | `migrate:fresh --env=testing` clean | ✅ (backend) |
| A2 raises delivered on the **real LLM** | real `generation:eval`, under-delivered 1→0 | ✅ (real-LLM, single run) |
| A2 **top-up** firing on the real model | not observed (overshoot sufficed); unit-tested only | ⚠️ real top-up unobserved |
| A2 invariant check | `invariant-reviewer` → CLEAN | ✅ this session |
| Anything on the **device** | nothing this session touched the client | ⚠️ device run pending (Part B) |

## Decisions that must not be silently revised (this feature)

- **A2:** cache stores the FINAL accepted set (after filter+top-up); top-up spend is SUMMED onto the
  primary, never overwritten. Overshoot+top-up+summation lives ONLY in `GenerationPipeline` — the
  eval must keep going through it, or v2↔v3 comparisons measure a phantom pipeline.
- **Prompt v2 is a frozen baseline.** A top-up must not edit `generate_collection.v2.md`; a
  first-class AVOID block lands with **v3 in A6**.
- **System decides composition** — no size slider; client sends маленькая/средняя/большая → 10/15/22.
- Pending-generation card lives in a drift table with start-up reconciliation (succeeded→drop,
  failed→error+retry, pending/running→poll, 404 or >24h→drop with a log note).
- Pexels attribution stored on the term; images cached per term globally, never overwrite.
- `resets_at` is an absolute UTC instant; quota boundary is UTC-day until a profile timezone exists.
- (carried) sync cursor in `sync_meta` not keychain · `since` inclusive · triage `TriagedTerms`
  marker · `restore()` clears token only on 401/403 · process rules change in `.claude/` files.

## What's next — A1+A6 (NOT started; readiness assessed this session)

Add term type taxonomy `word|phrase|idiom|phrasal_verb` + prompt **v3**, then eval-compare v2↔v3
against `…-v2-post-a2.json` before flipping `PROMPT_VERSION`. **Ready to implement, with 2 decisions
to confirm first** (see below). Concrete surface discovered this session:
- `TermType` VO **already exists** (`word|phrase`) — extend it with `Idiom`+`PhrasalVerb` and an
  `isPhraseLike()` helper (the handoff wrongly implied it was new).
- Migration: alter `terms_type_check` CHECK constraint to allow the 2 new values (reversible). No
  backfill — existing terms stay word/phrase.
- Treat idiom/phrasal_verb as phrase-like at **3 sites**: `EloquentTermAnswerKeyReader`,
  `EloquentTermDifficultyReader`, `VerificationStatsCommand` (all currently `type === 'phrase'`).
- Generation: `DraftValidator::type()` accept the 2 new values (whitespace fallback stays
  word/phrase); OpenAI schema `enum` + `items()` mapping add them; `GeneratedItem`/`ImportTerm`
  comments.
- **Decision 1 (contract):** `terms.type` ships to the client via `/sync`; OpenAPI `CollectionItem.type`
  enum is `[word, phrase]`. Do the new values start shipping in A1 (client must tolerate them; badges
  come in B) or stay server-internal until B? Needs a check that the generated Dart client won't crash
  on an unknown enum. (Invariant: client check never stricter than server.)
- **Decision 2 (v3 scope):** does prompt v3 also add a per-item `image_api_prompt` (the eval already
  has reflective `imageApiPrompt` plumbing + an img% metric), or are images left entirely to **A3**
  (Pexels)? Recommend v3 = type taxonomy + AVOID block only; images decided in A3.

Then: **A3** (Pexels images, async attach job) and **B** (contract/drift v4 + create screen +
generating→ready card + type badges).

## Known limitations / deferred (also in ROADMAP)
- A2 top-up path unobserved on the real model (overshoot sufficed on the eval run); +23% token cost
  from overshoot is accepted.
- No per-user timezone → `resets_at` is an absolute UTC instant; revisit the quota day-boundary when
  `profiles.timezone` arrives with the streak.
- Out of scope (next block): extending an existing collection, «как прошло» loop, curated starter
  content, push instead of polling.
- (carried) two-device `client_seq` collision; stale reviews upload pipeline (422s);
  triage-after-reinstall resurrection; stale offline streak/reviews-today; orphan local
  terms/progress not GC'd.

## Running / verifying
- Backend2 in Docker (`wt_app` :8001, `wt_db` :5433). Gates: commit hook runs `composer check`
  (arch+stan+test). Manual: `docker compose exec app composer arch|stan|test`. **`composer stan`
  analyzes `app/` only.**
- `generation:eval [--fake] [--out=path]` — manual quality gauge; the real driver costs money and
  now runs the full A2 overshoot+top-up pipeline. Post-A2 v2 baseline: `docs/generation-eval-v2-post-a2.json`.
- Device: `PATH="/opt/homebrew/bin:$PATH" LANG=en_US.UTF-8 flutter run --release -d 00008110-000A7CCC3492801E`.
