---
description: Overwrite backend2/docs/session-handoff.md with a fresh snapshot of the current state.
argument-hint: "(no args)"
disable-model-invocation: true
allowed-tools: Bash(git log:*), Bash(git status:*), Bash(git branch:*)
---

Regenerate the session handoff at `backend2/docs/session-handoff.md`.

It is a **SNAPSHOT of the current state, not a growing log** — overwrite the whole file, do not
append. Keep it tight; a new session reads this first to continue the work.

Current repo state to ground the snapshot in (do not invent commits/hashes — use these):

- Branch: !`git branch --show-current`
- Recent commits: !`git log --oneline -20`
- Uncommitted working tree: !`git status --short`

Write the file with these **required sections** (omit none; if a section is empty, say so):

1. **Header** — branch, whether merged to `main`, and today's date.
2. **Current task** — one paragraph: what it is and where it stands.
3. **What's done** — bullet list, **each with its commit hash** (from the log above). Only list
   what actually landed as commits.
4. **Verified on device vs. code-only** — a **table** with columns *Item | How verified | Status*.
   Anything proven only by tests/analysis and NOT run on the device must be marked **explicitly**
   as code-only / unverified. This project has repeatedly seen the device disprove correct-looking
   code — never imply device-verification that didn't happen.
5. **Decisions that must not be silently revised** — the deliberate choices (and *why*), e.g. the
   sync cursor lives in the local DB not the keychain; `since` is inclusive. Anyone reversing one
   of these must do it openly.
6. **What's next** — the first concrete step for the next session.
7. **Known limitations / deferred** — carry forward the live ones (e.g. two-device `client_seq`
   collision; stale reviews upload pipeline) plus anything this session added.

Then do two things:

- If this session produced **findings not folded into the work**, make sure they are recorded in
  `backend2/docs/ROADMAP.md` (not only in the handoff). State explicitly whether you added any and
  where, or that there were none.
- After writing, print a one-line confirmation and the list of section titles you wrote, so it's
  visible that none were skipped.
