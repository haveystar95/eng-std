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
  prompts: everyday/travel/professional/thematic/abstract/very-short/English/adversarial) + a manual
  quality command in `Generation/Presentation/Console` (delivered-vs-requested, phrase & idiom+phrasal
  ratio, dup rate, CEFR spread, image-prompt coverage read reflectively, tokens/cost; `--fake` for a
  no-spend smoke; fake toggled via `config`, no Infrastructure import → deptrac green). **Real v2
  baseline saved** at `docs/generation-eval-v2-baseline.json` (20/20 ok, 1 under-delivered, avg phrase
  56%, idiom/phrasal 0%, no dupes, ~$0.27). Diff v3 against this.
- **A4 hygiene, part 1** (`1201122`): retry only transient errors — a rejected `InvalidGeneratedDraft`
  now fails terminally (no 3× re-spend on a deterministic bad draft); `GenerationRequest::recordAttempt()`
  persists model/tokens/cost the instant the model answers, so a later validation failure keeps its
  spend; truncated raw response kept on new `generation_requests.raw_response` for diagnosis. 18
  Generation unit tests + 6 feature tests green.

## What's next (Part-C order)

1. **A4 part 2 — prompt-cache lookup**: on a `(normalized_prompt, source_lang, target_lang,
   prompt_version)` hit, reuse the prior succeeded request's **term set** (fresh personal collection,
   no LLM call). Needs a Generation repo finder + a non-owner-scoped Collections read of a collection's
   term ids/title. Decisions to make: reuse cached title (yes), no quota refund on hit, model=`cache`/cost 0.
2. **A4 part 3 — quota in `GET /me`** (`generation: {limit, used, remaining, resets_at}`).
   **BLOCKED on an open decision:** no per-user timezone is stored, quota resets on UTC-day boundaries,
   so `resets_at` "in the user's tz" can't be computed — pick (a) absolute-UTC-instant, client renders
   local, or (b) add `profiles.timezone`. See ROADMAP Open questions.
3. **A2** overshoot (size+30%) + one top-up ("avoid these terms") + honest `requested/delivered`.
4. **A1+A6** type enum `word|phrase|idiom|phrasal_verb` (migration + `TermType` VO + every word/phrase
   branch treats idiom/phrasal_verb as phrase-like) + prompt **v3** → **eval-compare v2↔v3** before
   flipping `PROMPT_VERSION` to `v3`.
5. **A3** Pexels images: `ImageSearchPort` + adapter + fake; async `AttachImagesJob` (throttled, empty=null,
   no retry, skip terms that already have an image); `image_url` + `image_author`/`image_author_url` on
   terms and `image_url` on collections; **arrive via `/sync`** (additive) — client re-syncs a few times
   after generation to pull them promptly.
6. **B** contract (OpenAPI + `/sync` additive: term `image_url`, collection `image_url`, new type values)
   → drift **v4** (`Terms.imageUrl`, `Collections.imageUrl`, new `PendingGenerations` table) → create
   screen (size chips, placeholder rotation, quota-aware button) → generating→ready card → first-contact
   «Разобрать» → type badges.

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
- **No per-user timezone stored** → blocks `resets_at`-in-local-tz (A4 part 3). Decide before building it.
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
