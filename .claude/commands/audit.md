---
description: Stage-1 read-only audit of a module/area — code vs skills vs invariants. Stops for confirmation.
argument-hint: "<module or area>  (e.g. Learning, Collections, mobile sync)"
disable-model-invocation: true
allowed-tools: Read, Grep, Glob, Bash(git log:*), Bash(git grep:*)
---

Run a **Stage-1 audit** of: **$ARGUMENTS**

This is **READ-ONLY reconnaissance**. Do NOT edit, write, create, refactor, or commit anything.
Do NOT propose fixes inline. Produce the report, ask your questions, and **STOP** — wait for the
user to confirm the direction before any Stage-2 work.

Ground the audit in the code and the project's own rules:
- Paradigm + invariants: root `CLAUDE.md`, `backend2/CLAUDE.md`, `backend2/ARCHITECTURE.md`.
- The skills are the authority on how things must be structured: `backend2/.claude/skills/`.
- Module layout: `backend2/app/Modules/<Context>/{Domain,Application,Infrastructure,Presentation}`
  and each module's `README.md`; boundaries in `backend2/deptrac.yaml`.

Read what you need first (the module's four layers, its migrations, its routes, the relevant
skill files), then produce the report in **exactly this format**:

### 1. Scope
What area was audited, and which files/dirs you actually read.

### 2. What's in the code
- **Layers** present (Domain / Application / Infrastructure / Presentation) and what's in each.
- **Aggregates / entities** and their invariants.
- **Domain services**.
- **Tables / migrations** owned here (columns, keys, indexes that matter).
- **Endpoints** exposed (routes → controllers).

### 3. Code vs. skills
Four explicit lists — do not merge them:
- **Matches** the skills.
- **Diverges** from the skills — and exactly *how*.
- **In the code, not described in the skills**.
- **Described in the skills, not implemented**.

### 4. Invariant checks
State PASS / FAIL / N-A with a one-line evidence (file:line) for each:
- Progress is keyed by **(user_id, term_id)** — no `collection_id` sitting next to progress.
- `reviews` and `term_triages` are **append-only** — no UPDATE against them.
- Ordering is by **client_seq**, never by device timestamps.
- `Domain/` imports **nothing** from Illuminate / Carbon / Eloquent.
- Terms are reached **only through Vocabulary's Application** (no direct table/model access).
- **One** definition of "усвоено" (`Mastery::isMastered`) — no second definition anywhere.
- Cross-module calls go **only** through the other module's Application layer.
- (If the area is the client) reads come from the **local DB, not the network**; the sync cursor
  lives in the **local DB, not the keychain**.

### 5. Вопросы к пользователю
The open decisions this audit surfaced, as concrete questions. Then **STOP** — do not proceed to
any change until the user answers.
