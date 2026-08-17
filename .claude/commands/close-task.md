---
description: Definition-of-done checklist for closing a task — runs the checks and prints ✅/❌ per item.
argument-hint: "(no args)"
disable-model-invocation: true
allowed-tools: Read, Grep, Glob, Bash(git diff:*), Bash(git log:*), Bash(git status:*), Bash(cd backend2 && docker compose exec*), Bash(cd mobile && flutter*)
---

Run the **definition-of-done** checklist for the current task. **Actually run each mechanical
check** and report **✅ / ❌** with a one-line reason per item — never assume, never silently pass.

Work through the list in order:

1. **Gates green.**
   - backend2 (if it changed): `cd backend2 && docker compose exec -T app composer check`
     (arch + stan + test).
   - client (if `mobile/` changed this task): `cd mobile && PATH="/opt/homebrew/bin:$PATH" flutter analyze`
     — and `flutter test` if logic changed.
   - ✅ only if every gate that applies is green.

2. **`migrate:fresh` clean — NON-destructively** (test DB, leaves dev data alone):
   `cd backend2 && docker compose exec -T -e DB_DATABASE=wordtrainer_test app php artisan migrate:fresh`
   ✅ if it completes with no error.

3. **OpenAPI updated if the HTTP surface changed.** Inspect the task's diff (`git diff main...HEAD`
   or the session's commits): if any route / controller / FormRequest / Resource changed but
   `backend2/openapi/openapi.yaml` did not, that's ❌. ✅ if the surface is unchanged or the spec
   was updated to match.

4. **Task findings-doc closed** (if the task had one, e.g. a `*-findings.md`): every item marked
   closed with the commit that closed it. ✅ if there was no findings-doc, or all items are closed.

5. **Handoff current.** `backend2/docs/session-handoff.md` must reflect the state as of now. If it
   doesn't, regenerate it with `/handoff` before claiming this ✅.

6. **Device-unverified** — a **separate section**, not a checkbox: honestly list everything that is
   proven only by tests/analysis and has NOT been run on the device. If nothing, say so.

7. **Findings → ROADMAP** — confirm any new findings from this task are recorded in
   `backend2/docs/ROADMAP.md`, not only in the handoff. State where, or that there were none.

Then print:
- the **✅/❌ checklist** (items 1–5) with evidence,
- the **device-unverified** section (item 6),
- the **ROADMAP** confirmation (item 7).

If any item is ❌, say plainly that the task is **not** done and what's left — do not round up.
