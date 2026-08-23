# Learning

**Owns:** progress, the personal pool, SRS scheduling, the acquisition ladder, triage,
exercises/grading, study sessions, reviews, statistics

Layers: `Domain` (pure PHP, no Laravel) · `Application` (Commands/Queries/Ports/DTOs) ·
`Infrastructure` (Eloquent, adapters, migrations, provider) · `Presentation` (Http, Phase 3).

## Key invariants

- **Progress is per `(user_id, term_id)`** (`TermProgress`), never per collection. A term
  learned in one collection is learned everywhere. Collection progress is derived, not stored.
- **The POOL is an attribute of the pair, not a collection.** `enrolled_at` non-null = the learner
  is studying this word; null = it is in the catalogue only. STUDY sessions are assembled from the
  pool and nothing else, so a collection is a catalogue of a topic and the pool is the queue. There
  is deliberately no «Мои слова» collection entity: one would duplicate terms, need a tombstone per
  removal, and give one word two progress stories.
- **Free practice scoped to a COLLECTION is the one selection that leaves the pool.** It drills the
  topic the learner pointed at, untriaged words included, so a fresh collection is playable without
  a triage pass. It costs those words nothing — practice enrols nothing, writes no exposure and
  never schedules — and they are dealt only what the matrix opens at
  `LearningLadder::STEP_UNENROLLED_PRACTICE`, never typed production or dictation. Pool terms lead
  the session and keep their own rung. UNSCOPED free practice still reads the pool alone.
- **Leaving the pool is a PAUSE.** `enrolled_at → NULL` and nothing else moves — the review log, the
  rung, the counter and the due date all stand, so re-enrolling resumes exactly where the word was
  left. Enrolment is idempotent and keeps its first moment.
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

## Three dimensions, and they never read each other

| | answers | owned by | columns |
|---|---|---|---|
| `LearningState` | **when** the pair comes back | `Sm2Scheduler` | `state`, `ease_factor`, `interval_days`, `due_at`, `reps`, `lapses`, `last_reviewed_at` |
| `Acquisition` | **what** it comes back as | `ModeAdmission` | `acquisition`, `learning_step`, `successful_reviews` |
| the pool | **whether** it comes back at all | the learner | `enrolled_at` |

Three dimensions on one row, and no transition writes two of them. The third is the newest: a word
reaches the trainer only through a deliberate act — a `не знаю` / `не уверен` triage swipe, or
«Учить это слово» on the word card. Nothing else enrols: not adding a collection, not generating
one, not answering a practice card.

The scheduler does not know `acquisition` exists; the admission matrix never looks at `state`. That
orthogonality is why the ladder landed over live data without re-deriving a single interval —
existing pairs were backfilled to `graduated` and no SM-2 column was touched.

`LearningLadder::stepFor(acquisition, successful_reviews, learning_step, is_known)` is the ONE
derivation of a pair's rung, mirrored by the client and table-tested here:

```
new                                  → 0  intro, no grading      (writes term_exposures)
learning, step 1                     → 1  recognition term→translation, graded by IDENTITY (tap an option id)
learning, step 2                     → 2  recognition translation→term
graduated, successful_reviews 0–3    → 3  assembly / choice
graduated, successful_reviews 4–5    → 4  + typed production
graduated, successful_reviews ≥ 6    → 5  + dictation
known                                → null, outside the ladder (verification is always typing)
```

Rungs 3–5 count **`successful_reviews`, not the scheduler's `reps`**: `reps` counts SM-2 calls,
`again` included, so a word nobody could remember used to ride its own failures up to dictation.
The counter grows on a correct non-practice review of a graduated pair (`hard` counts), and `again`
neither increments nor resets it. Full reasoning: the `LearningLadder` docblock.

An answer on rungs 1–2 is logged but **never schedules**: graduation invents no interval, and a
failed step moves nothing, so the client can re-queue the same card into the session's tail. The
first real grade after graduation enters SM-2 from `new`, exactly as the first success of any new
word always has.

The admission matrix is **data**, in `learning_mode_settings` beside the on/off toggle and under the
same global-plus-per-user-override mechanism — one row per `(scope, mode)`, carrying
`min_acquisition`, `min_learning_step`, `min_successful_reviews` and `options_policy`.

> The column was called `min_reps` until the `2026_08_18_100000` migration renamed it; the WIRE key
> on `/sync` is still `min_reps` on purpose (`ModeAdmission::toWire()`, marked RENAME-DEFERRED).
> See the `mobile-sync-contract` skill.

## The 11th trainer: `description_match`

Read what a word MEANS, in the language being learned, and pick the word from four. The one card in
the app that shows no Russian at all — its prompt is neither the term nor its translation, so it
asks a question no translation pair can ask, and it is the only card that separates two words the
learner has collapsed onto one gloss.

Where it sits, and why each of these is what it is:

- **Content**: it needs a DESCRIPTION (`term_descriptions`, in the term's own language). This is the
  one gate with nothing to degrade to — the description *is* the question — so a term without one is
  refused (`ContentGap::NoDescription`), never dealt a lesser card. The store catalogue has no
  descriptions and is deliberately not being backfilled.
- **Pool**: its options are other pool words, through the same `DistractorReader` an ordinary
  `multiple_choice` uses. That reader already refuses a candidate whose translations overlap the
  target's, which matters more here than anywhere else: offering two words that share one Russian
  gloss would put two right answers under a description that separates them.
- **Grading**: as TEXT, against the term's own forms — like an ordinary `multiple_choice` and *not*
  like the rung-1 card. Identity grading exists because that card's correct option is a
  TRANSLATION; here the option is the WORD, so the text path is both available and correct, and it
  is what keeps `accepted_variants` meaningful and the device's check no stricter than the server's.
- **Ceiling** `good`, `forgivesTypos` false: a four-way tap is the weakest evidence the app collects.
- **Passport** `graduated`: its question is a sentence to READ about a word met minutes ago.

`ModeContentRequirements` now asks CONTENT FIRST and pool-dependence second. That order was
invisible while `multiple_choice` was the only pool-dependent mode (it fits every term); this mode
is pool-dependent *and* refusable by the term, and reporting it as merely pool-dependent would send
the owner to look at their session when the cure is the станок.

Born switched OFF in every scope, like every trainer since `dictation` — код в main ≠ режим у
пользователей.

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
- `TriageTerms` — appends triage swipes, projects the latest verdict per term onto progress and
  decides pool membership: `unknown` → rung 0 enrolled, `unsure` → rung 1 enrolled, `known` → a
  verification row (via `TriageVerificationPlanner`), NOT enrolled. A pair that has actually been
  taught (`TermProgress::hasBeenTaught`) is only enrolled, never rewritten. Never writes `reviews`.
- `EnrollTerm` / `UnenrollTerm` — «Учить это слово» and «Убрать из изучения». Both idempotent,
  both about one pair, and `UnenrollTerm` deliberately has no other branch: one column to NULL.
- `BuildStudySession` — assembles a self-contained session FROM THE POOL (optionally narrowed to
  one collection's terms, deduped, one global quota) and its RUNNING ORDER (`SessionLayout`): a
  word introduced in a session is answered in that session, at widening gaps, interleaved with due repeats and never in a block of intros. A
  word whose chain does not fit is deferred whole. Cards come from `ExerciseSelector` +
  distractors/chips, and the composition is persisted. Practice sessions never introduce new terms
  or schedule, and are laid out flat.
- Queries: `GetDueTerms` (pool repeats + pool first meetings, read under separate limits so a
  freshly triaged pool cannot crowd out the repeats), `GetTriageQueue`, `GetCollectionsProgress`,
  `GetUserStats`.

One card is dealt from OUTSIDE the pool, on purpose: a `known` self-assessment whose verification
check has come due. That is the system auditing a claim rather than the learner's queue, and
dropping it would mean a «знаю» swipe is never questioned. `DueTermsReader` and the `due_today`
stat agree about it, so the home CTA never offers a session that comes back empty.

Cross-module (via other modules' Application): Vocabulary `TermExistenceReader` /
`TermContentReader` / `TermAnswerKeyReader` / `TermDifficultyReader` / `DistractorReader`,
Collections `UserCollectionTermsReader`, Identity via `LearnerProfileReader`.

See root `CLAUDE.md`, `ARCHITECTURE.md` and `.claude/skills/learning-srs`. Boundaries
enforced by `deptrac.yaml`.
