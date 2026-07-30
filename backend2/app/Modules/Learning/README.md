# Learning

**Owns:** progress, SRS scheduling, triage, exercises/grading, study sessions, reviews, statistics

Layers: `Domain` (pure PHP, no Laravel) · `Application` (Commands/Queries/Ports/DTOs) ·
`Infrastructure` (Eloquent, adapters, migrations, provider) · `Presentation` (Http, Phase 3).

## Key invariants

- **Progress is per `(user_id, term_id)`** (`TermProgress`), never per collection. A term
  learned in one collection is learned everywhere. Collection progress is derived, not stored.
- **`reviews` is append-only** with a client-generated ULID primary key; inserts are
  idempotent (`insertIgnore` / ON CONFLICT DO NOTHING), never updated.
- **`daily_user_stats` is a projection** of the reviews log — rebuildable by replay.
- **`term_triages` is append-only** (client ULID, no `unique(user, term)`); progress state is a
  projection of it, like it is of `reviews`. Triage is never written to `reviews`.
- **The server grades**, not the client. **"Mastered" has one definition** (`Mastery::isMastered`).
  A study session's **composition is fixed** under its id — answers outside it are rejected.

## Scheduling

`Scheduler` is a domain port; `Sm2Scheduler` is the pure SM-2 implementation (day-based
intervals, ease clamped `[1.30, 3.00]`, interval clamped `[1, 365]`, injectable `Fuzz`).
Swap in an `FsrsScheduler` later without touching handlers.

## Application surface

- `SubmitReviews` — grades a batch of RAW answers (`AnswerGrader` + per-mode latency median),
  folds accepted, non-practice answers into progress in `answered_at` order (a replayed offline
  batch equals the online result), projects stats, invalidates the median cache. Rejects
  unknown terms and answers outside a named session's composition. In a `TransactionManager`.
- `TriageTerms` — appends triage swipes, projects the latest verdict per term onto progress
  (`known` schedules a verification via `TriageVerificationPlanner`); never writes `reviews`.
- `BuildStudySession` — assembles a self-contained session (scoped/global, deduped, one global
  quota), one card per exercise (`ExerciseSelector` + distractors + chips), persists the
  composition. Practice sessions never introduce new terms or schedule.
- Queries: `GetDueTerms`, `GetTriageQueue`, `GetCollectionsProgress`, `GetUserStats`.

Cross-module (via other modules' Application): Vocabulary `TermExistenceReader` /
`TermContentReader` / `TermAnswerKeyReader` / `TermDifficultyReader` / `DistractorReader`,
Collections `UserCollectionTermsReader`, Identity via `LearnerProfileReader`.

See root `CLAUDE.md`, `ARCHITECTURE.md` and `.claude/skills/learning-srs`. Boundaries
enforced by `deptrac.yaml`.
