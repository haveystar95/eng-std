# Session handoff — snapshot

> **Overwrite this file each session. Snapshot of current state, not a growing log.**
> Read with `CLAUDE.md`, `ARCHITECTURE.md`, `.claude/skills/`, `deptrac.yaml`, `docs/ROADMAP.md`.
> `triage-contract-findings.md` is frozen.

Branch: `feat/mobile-backend2-cutover` (not merged to `main`). Last updated: 2026-08-04.

---

## Current task: NONE OPEN — offline mode is done + device-verified; process tooling is done.

The offline-first client (delta sync + local DB + collection view + triage-from-local) is
**built, device-verified, and committed**. The process tooling (commit-gate hook, /handoff,
/audit, /close-task, invariant-reviewer) is **built and committed**. Next up is the **Generation
module** — start it with `/audit Generation` (see "What's next").

## What's done (with commit hashes)

**Offline mode (Parts 2 & 3 + triage-from-local):**
- `fef69ef` drift local DB (schema mirrors `/sync`; `applyDelta` atomic; **cursor in `sync_meta`, not keychain**).
- `a6e387d` SyncService (pages `/sync`, cursor advances only after full run, triggers on start/network/resume).
- `a800364` read-path flip — `collections/collectionWords/stats/collectionsProgress` are drift StreamProviders; mutations trigger `sync()`.
- `6057288` quiet sync indicator. `c1f144f` collection view (Part 3) + per-word status. `7ba9d44` delta-application unit tests.
- `b1a0aeb` **offline-first session restore** (user cached in keychain; token cleared only on real 401/403 — was the front-door blocker).
- `3fe2013` sensible offline for a brand-new user's first sign-in. `786dad6`/`a19f73b` iOS build via CocoaPods (SPM off).
- `f4b83ca` **triage deck built from the local DB** + durable `TriagedTerms` marker so an `unknown` swipe doesn't resurrect after sync; `1adeff2` its reinstall limitation noted.
- `4f48e91` `source`/`type` on `/sync` collections (ИИ badge / origin) + «Не знаю» marker for triaged-unknown words. `baeef47` diagnostics panel behind a flag.

**Process tooling (all in `.claude/`):**
- `838daa2` commit-gate hook · `b5f7d96` /handoff · `269d5a0` /audit · `c78d22f` /close-task · `aec2eff` invariant-reviewer · `2e31d97` CLAUDE.md doc.

## Verified on device vs. code-only

| Item | How verified | Status |
|---|---|---|
| Reinstall → full snapshot (cursor-in-DB deviation) | device, panel `since=∅` + full fill | ✅ device |
| Cold start in airplane: all tabs, terms, TTS | device | ✅ device |
| Server change → sync → airplane | device (renamed collection propagated) | ✅ device |
| Deletions: collection + item, no ghosts | device (2→1 coll, 40→14 items) | ✅ device |
| Reverse path: un-delete → sync → reappears, no dupes | device (back to 2/40/38) | ✅ device |
| Triage: offline entry, swipe, BUG-1, upload after reconnect | device + backend `term_triages` (3 verdicts) | ✅ device |
| Per-word status (Усвоено/Учу/Не знаю) | device | ✅ device |
| `source`/`type` on `/sync` | backend pest only; NO AI collection exists to show the ИИ badge | ⚠️ code-only visually |
| Commit-gate hook | ran manually (green allows, red exit 2, SKIP_GATES, scoping) | ✅ verified (not via a real session-loaded hook yet) |
| invariant-reviewer | ran the checklist via `general-purpose` (named agent not registered until next session) | ⚠️ logic verified, not yet by-name |
| /handoff, /audit, /close-task | built; `migrate:fresh`-on-test-DB step validated; slash-invocation needs a fresh session | ⚠️ not yet run as commands |

## Decisions that must not be silently revised

- **Sync cursor lives in the local DB (`sync_meta`), NOT the keychain** — so a reinstall wipes it and the next sync is a full snapshot. Reversing this reintroduces the half-empty-after-reinstall bug.
- **`since` is inclusive (`>=`)** — second-precision timestamps; the client applies deltas idempotently by id (LWW).
- **Triage exclusion uses a durable local `TriagedTerms` marker** (mirrors server `term_triages`, which isn't synced) — an `unknown` swipe writes no progress row, so without the marker it resurrects.
- **`restore()` clears the token only on 401/403, never on a network error** — offline cold start must not log the user out.
- **Process rules change in the `.claude/` files, never silently in a commit.**

## What's next

**Generation module.** First action in the new session (also the tooling registration check):
1. `/handoff` (refreshes this snapshot; confirms the command registered).
2. `/audit Generation` (Stage-1 read-only audit of the next module — what's really there, whether the skills' eval set is used, how the prompt cache works — and confirms /audit works).
3. Call `invariant-reviewer` by name (confirms the subagent registered).
Then the user provides the Generation-block prompt, informed by the audit.

## Known limitations / deferred (also in ROADMAP)

- Two-device `client_seq` collision (pre-release accepted).
- Reviews upload pipeline is stale (pre-`client_seq`/raw-answer) → 422s; rebuild with the exercise screens; `seq_review` counter ready.
- Triage after a **reinstall**: `unknown`-swiped terms reappear (marker wiped, `term_triages` not synced) — acceptable.
- Streak/reviews-today are cached from `/stats`, stale offline. Orphan local `terms`/`progress` after a collection delete aren't GC'd (harmless).
- `source`/`type` badge only shows on an AI-generated collection (existing user collections look the same).
- New commands/subagent + edited `settings.json` register on a **fresh** Claude Code session.

## Running / verifying
- Backend2 in Docker; gates `composer check` (arch+stan+test) — now enforced by the commit hook.
- ngrok domain `https://greedily-thermos-finer.ngrok-free.dev`. Device: `PATH="/opt/homebrew/bin:$PATH" LANG=en_US.UTF-8 flutter run --release -d 00008110-000A7CCC3492801E` (release only; `pod install` on first build; `debugPrint` invisible in release — use the diagnostics panel via `--dart-define=SYNC_DIAG=true`).
