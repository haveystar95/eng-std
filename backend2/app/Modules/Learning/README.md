# Learning

**Owns:** progress, SRS scheduling, the acquisition ladder, triage, exercises/grading, study
sessions, reviews, statistics

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
- **`term_exposures` is append-only and keyed by the PAIR** `(user_id, term_id)` — the intro
  card's only output. An intro asks for nothing, so it is never a `reviews` row: that log holds
  real retrievals, and a row there would inflate retention with a word nobody recalled.
- **The server grades**, not the client. **"Mastered" has one definition** (`Mastery::isMastered`).
  A study session's **composition is fixed** under its id — answers outside it are rejected. A term
  may occupy several slots of one session (its ladder chain); the composition is a set of terms.

## Two dimensions, and they never read each other

| | answers | owned by | columns |
|---|---|---|---|
| `LearningState` | **when** the pair comes back | `Sm2Scheduler` | `state`, `ease_factor`, `interval_days`, `due_at`, `reps`, `lapses`, `last_reviewed_at` |
| `Acquisition` | **what** it comes back as | `ModeAdmission` | `acquisition`, `learning_step` |

The scheduler does not know `acquisition` exists; the admission matrix never looks at `state`. That
orthogonality is why the ladder landed over live data without re-deriving a single interval —
existing pairs were backfilled to `graduated` and no SM-2 column was touched.

`LearningLadder::stepFor(acquisition, reps, learning_step)` is the ONE derivation of a pair's rung,
mirrored by the client and table-tested here:

```
new                    → 0  intro, no grading      (writes term_exposures)
learning, step 1       → 1  recognition term→translation, graded by IDENTITY (tap an option id)
learning, step 2       → 2  recognition translation→term
graduated, reps 0–3    → 3  assembly / choice
graduated, reps 4–5    → 4  + typed production
graduated, reps ≥ 6    → 5  + dictation
known                  → null, outside the ladder (verification is always typing)
```

An answer on rungs 1–2 is logged but **never schedules**: graduation invents no interval, and a
failed step moves nothing, so the client can re-queue the same card into the session's tail. The
first real grade after graduation enters SM-2 from `new`, exactly as the first success of any new
word always has.

The admission matrix is **data**, in `learning_mode_settings` beside the on/off toggle and under the
same global-plus-per-user-override mechanism — one row per `(scope, mode)`, carrying
`min_acquisition`, `min_learning_step`, `min_reps` and `options_policy`.

## Scheduling

`Scheduler` is a domain port; `Sm2Scheduler` is the pure SM-2 implementation (day-based
intervals, ease clamped `[1.30, 3.00]`, interval clamped `[1, 365]`, injectable `Fuzz`).
Swap in an `FsrsScheduler` later without touching handlers.

## Application surface

- `SubmitReviews` — applies one session's events. Grades a batch of RAW answers (`AnswerGrader` +
  per-mode latency median), folds accepted, non-practice answers into progress in `client_seq`
  order (a replayed offline batch equals the online result), projects stats, invalidates the median
  cache. Rejects unknown terms and answers outside a named session's composition. Also records
  `exposures` — intro cards — which write `term_exposures`, step the pair onto the first
  recognition rung, and count toward the day's new-term quota without entering the review log. In a
  `TransactionManager`.
- `SetModeAdmission` — moves one trainer's place on the ladder, globally or per user. Separate from
  the on/off write on purpose: moving a rung must not switch a trainer on for someone.
- `TriageTerms` — appends triage swipes, projects the latest verdict per term onto progress
  (`known` schedules a verification via `TriageVerificationPlanner`); never writes `reviews`.
- `BuildStudySession` — assembles a self-contained session (scoped/global, deduped, one global
  quota) and its RUNNING ORDER (`SessionLayout`): a word introduced in a session is answered in
  that session, at widening gaps, interleaved with due repeats and never in a block of intros. A
  word whose chain does not fit is deferred whole. Cards come from `ExerciseSelector` +
  distractors/chips, and the composition is persisted. Practice sessions never introduce new terms
  or schedule, and are laid out flat.
- Queries: `GetDueTerms`, `GetTriageQueue`, `GetCollectionsProgress`, `GetUserStats`.

Cross-module (via other modules' Application): Vocabulary `TermExistenceReader` /
`TermContentReader` / `TermAnswerKeyReader` / `TermDifficultyReader` / `DistractorReader`,
Collections `UserCollectionTermsReader`, Identity via `LearnerProfileReader`.

See root `CLAUDE.md`, `ARCHITECTURE.md` and `.claude/skills/learning-srs`. Boundaries
enforced by `deptrac.yaml`.
