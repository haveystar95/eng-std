# Learning

**Owns:** progress, SRS scheduling, sessions, reviews, statistics

Layers: `Domain` (pure PHP, no Laravel) · `Application` (Commands/Queries/Ports/DTOs) ·
`Infrastructure` (Eloquent, adapters, migrations, provider) · `Presentation` (Http, Phase 3).

## Key invariants

- **Progress is per `(user_id, term_id)`** (`TermProgress`), never per collection. A term
  learned in one collection is learned everywhere. Collection progress is derived, not stored.
- **`reviews` is append-only** with a client-generated ULID primary key; inserts are
  idempotent (`insertIgnore` / ON CONFLICT DO NOTHING), never updated.
- **`daily_user_stats` is a projection** of the reviews log — rebuildable by replay.

## Scheduling

`Scheduler` is a domain port; `Sm2Scheduler` is the pure SM-2 implementation (day-based
intervals, ease clamped `[1.30, 3.00]`, interval clamped `[1, 365]`, injectable `Fuzz`).
Swap in an `FsrsScheduler` later without touching handlers.

## Application surface (Phase 2)

- `SubmitReviews` — appends a batch, folds accepted reviews into progress in `answered_at`
  order (so a replayed offline batch equals the online result), projects stats. Rejects
  reviews for unknown terms via Vocabulary's `TermExistenceReader`. Wrapped in Shared's
  `TransactionManager`.
- `StartStudySession` — opens a session (client-supplied id is idempotent).
- `GetDueTerms` (query) — due before new, new capped by the daily quota and session size.

See root `CLAUDE.md`, `ARCHITECTURE.md` and `.claude/skills/learning-srs`. Boundaries
enforced by `deptrac.yaml`.
