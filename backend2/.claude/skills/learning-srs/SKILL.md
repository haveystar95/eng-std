---
name: learning-srs
description: Domain rules for the Learning module — spaced repetition scheduling, study sessions, review processing, progress state machine and statistics. Consult this skill for anything touching how users learn: due cards, intervals, ease factors, grades, streaks, retention, per-collection progress, or "why is this word showing up again". Also consult it before adding any field related to progress or stats, since progress is keyed by (user, term) and stats are derived, not stored by hand.
---

# Learning & spaced repetition

The `Learning` module owns everything about *what the user should see next* and
*how well they know it*. It never owns term content (that's `Vocabulary`) and never
reads collection tables directly (ask `Collections` via a Query).

## Progress is per (user, term)

A term learned inside "Travel" is learned inside "Bank" too. Collection progress is a
derived aggregate — the percentage of that collection's terms in `review` state with a
future `due_at`. Never store per-collection progress rows.

## The pool: a collection is the LIBRARY, the pool is the QUEUE

A collection is a catalogue of a topic. What the trainer actually works through is the learner's
own **pool** — the pairs whose `enrolled_at` is not null. Rules:

- **Membership is an attribute of the pair**, one nullable timestamp on `user_term_progress`.
  Never a «Мои слова» collection entity: that would duplicate every term, need a tombstone per
  removal, and give one word two progress stories depending on where it was answered.
- **Only a deliberate act enrols.** A `не знаю` / `не уверен` triage swipe, or «Учить это слово»
  on the word card. Adding a collection, generating one, subscribing to a store deck and
  answering a practice card all enrol **nothing**.
- **Every session is assembled from the pool** — study and free practice alike. `collection_id`
  is a FILTER on the pool («потренировать аптечные перед аптекой»), never a source: a word of that
  collection which is not enrolled stays out. An unscoped session is the whole pool, so a word
  whose collection was deleted or unsubscribed keeps being studied — the book went back on the
  shelf, the word did not.
- **Removal is a PAUSE, never an erasure.** `enrolled_at → NULL` and nothing else moves: the
  append-only review log, the rung, the counter and the due date all stand, so re-enrolling
  resumes exactly where the word was left. Both writes are idempotent, and enrolment keeps its
  FIRST moment — «с какого дня я это учу» is not rewritten by a second tap.
- **One card is dealt from outside the pool**: a `known` self-assessment whose verification check
  has come due. That is the system auditing a claim, not the learner's queue — dropping it would
  mean a «знаю» swipe is never questioned. Anything counting «сколько к повтору» must agree with
  the reader about this, or the home screen offers a session that comes back empty.
- **A row is not membership.** Enrolment creates the row before the word has ever been shown, so
  «does a progress row exist» stopped being the same question as «has this been taught». Anything
  that used to mean the latter asks `TermProgress::hasBeenTaught()`.

## State machine

```
new ──first review──► learning ──graduates──► review
 │  ▲                     ▲                     │
 │  │(triage: unsure)     │                  grade=again
 │  └─────────────────────┴── relearning ◄──────┘
 │
 └──(triage: known)──► known ──verification failed──► learning
                          ▲                                │
                          └──(returned to learning)────────┘
```

- `new` — never answered. Introduced at a capped rate from the user's profile
  (`daily_goal`, clamped `[0, 100]`; `0` means "reviews only"). "New" is derived: a term
  with no progress row **or** a `new` row (one returned from `known`) — both are eligible.
- `learning` — short steps until it graduates. (Intra-day 1m/10m steps are
  `> Not implemented yet` — `interval_days` is whole days, so steps are 0 = "again this
  session" or 1 day.)
- `review` — long intervals driven by the scheduler.
- `relearning` — lapsed; short steps again, then returns with a reduced interval.
- `known` — a triage self-assessment ("I know this"), **not** an SRS state: the scheduler
  refuses it. Its `due_at` is a verification check, not an interval. See Triage below.

## Acquisition ladder — a second dimension, orthogonal to the scheduler

`state` above answers **WHEN** a pair comes back. A separate column, `acquisition`
(`new | learning | graduated`) plus `learning_step`, answers **WHAT** it comes back as. The two
never read each other's fields:

- The **scheduler never sees `acquisition`.** SM-2 keeps every one of its own fields —
  `state`, `ease_factor`, `interval_days`, `due_at`, `reps`, `lapses`, `last_reviewed_at` — and
  nothing on the ladder writes them. This is why the ladder could be added without re-deriving a
  single interval: existing pairs were backfilled to `graduated` and their SM-2 fields were not
  touched at all.
- The **mode admission matrix never sees `state`.** Which trainers a pair is allowed to be shown
  in is a function of its ladder step alone (`ModeAdmission`), so a mode gated off at step 1 is
  gated off whatever the scheduler thinks.

The step is derived by ONE pure function, `LearningLadder::stepFor(acquisition, reps, learningStep)`
— mirrored by the client, so it is table-tested on both runtimes:

```
acquisition=new                    → 0  intro (no grading)
acquisition=learning, step 1       → 1  recognition, term → translation   (identity-graded)
acquisition=learning, step 2       → 2  recognition, translation → term
acquisition=graduated, reps 0–3    → 3  assembly / choice
acquisition=graduated, reps 4–5    → 4  + typed production
acquisition=graduated, reps ≥ 6    → 5  + dictation
```

- **A ladder answer never schedules.** While `acquisition` is `new`/`learning`, a graded answer
  is appended to `reviews` (it is a real retrieval and keeps the streak) and moves `learning_step`
  only. A failed step leaves the step where it was — the client re-queues the same card into the
  tail of the session, which is why a failure must not write a schedule.
- **Graduation is only the flip to `graduated`.** No interval is invented at graduation; the
  first real `Grade` afterwards enters SM-2 from `new` exactly as the first success of any new
  word does today.
- **`intro` is a mode with no grade.** It is in the mode registry (so it has a toggle like every
  other trainer) and is dealt only at step 0. It writes an append-only `term_exposures` row
  (unique per `(user, term)`, so re-upload is an ignored insert), never a `reviews` row — the
  review log holds real retrievals only, and an intro asks for nothing.
- A `known` pair is **outside the ladder**: `stepFor` returns `null` and its verification stays
  typing, as below.

## Triage — the first-pass sweep of a collection

AI generation mixes basic vocabulary (`money`, `hello`) into the useful set. Triage is a
swipe pass that filters out what the user already knows, so the trainer doesn't waste their
time. Its invariants:

- **A triage marks a term globally, on `(user, term)`** — never inside a collection. Sifting
  out `money` in "Bank" removes it from "Shop" too. Direct consequence of progress living on
  the term.
- **Three verdicts, not two** — a binary choice makes people lie toward "known". Each routes the
  pair onto the acquisition ladder, and only `known` touches the scheduler:
  `known` → SM-2 `state=known` with a verification `due_at`, `acquisition=graduated` (outside the
  ladder); `unknown` → `acquisition=new`, so it starts at the intro step; `unsure` →
  `acquisition=learning, learning_step=1`, i.e. straight past the intro — the skip is a
  *position on the ladder*, not a flag.
- **Two of the three verdicts also ENROL.** `не знаю` and `не уверен` both mean «учи это», so both
  put the pair in the pool; `знаю` means the opposite and leaves it out (and, on a word that was
  enrolled but never taught, supersedes that earlier enrolment — the later deliberate statement
  wins). A pair that has actually been taught is only enrolled by a swipe, never rewritten.
- **Triage is never written to `reviews`.** A swipe is a self-assessment, not an exercise
  answer; in the review log it would inflate retention with unproven words. It lives in an
  append-only `term_triages` log (client ULID, no `unique(user, term)` — re-triage and
  "return to learning" are new rows; the current verdict is the latest by `decided_at`).
  Progress state is a projection of this log, like it is of `reviews`.
- **The triage queue excludes already-triaged terms**, and it asks about the LADDER (a pair still
  at `acquisition = new` counts as never studied), not about row existence — so an
  `unknown`-swiped term is enrolled and studyable but is never asked again.
- **Returning a `known` term to learning keeps its history.** Reset state to `new`, but keep
  `reps`/`lapses` — a term the user manually marked known then undid must not restart from
  zero. (A `new` row and a missing row mean the same thing to selection.)

### Verifying a "known" swipe

A swipe is a claim, not proof. A pure `TriageVerificationPlanner` schedules a check: risky
verdict → soon (~7 days), obvious verdict → far out (~90 days). **Risky** = the term's cefr
is above the user's level, OR the swipe was too fast to have been read (a lower latency floor
for single words than for phrases). A `null` (unknown) cefr is neutral — never treated as
risk, or every curated term without a level would be dragged into early checks. Thresholds
are provisional constants in one place, moved by the real share of failed checks.

The check always runs in **typing** (recognition would just let them recognise it again and
prove nothing) — enforced by the `ExerciseSelector`, not stored as a flag. A known term with a
due `due_at` rides the normal due selection. Failing the check is an **explicit** transition
`known → learning` (not routed through the scheduler, whose lapse path assumes an ease/interval
a known term never had); passing keeps it known with the next check ~90 days out.

## Scheduler as a domain service

```php
interface Scheduler
{
    public function schedule(TermProgress $progress, Grade $grade, DateTimeImmutable $now): TermProgress;
}
```

Start with `Sm2Scheduler` (SM-2 with sane guards). Keep the interface so `FsrsScheduler`
can replace it later without touching handlers — FSRS is measurably better but needs
review history to fit parameters, which you won't have on day one.

SM-2 essentials, all pure functions of the current state:

```
again → lapses+1, ease -= 0.20, state = relearning, interval = 0 (steps restart)
hard  → ease -= 0.15, interval = interval * 1.2
good  → interval = interval * ease
easy  → ease += 0.15, interval = interval * ease * 1.3
ease clamped to [1.30, 3.00]; interval clamped to [1 day, 365 days]
apply ±5% random fuzz to interval so cards don't clump on the same day
```

The scheduler is pure: no DB, no clock of its own, no randomness source it doesn't
receive. That's what makes it unit-testable and what keeps this logic honest.

## Review processing

```php
final readonly class SubmitReviewsHandler
{
    public function __invoke(SubmitReviews $command): ReviewBatchResult
    {
        // 1. reject unknown term ids
        // 2. insert reviews (ON CONFLICT DO NOTHING — client ULIDs, retries are free)
        // 3. sort accepted reviews by answered_at ASC   <- offline batches arrive unordered
        // 4. per term: lock progress row, fold each review through Scheduler, save
        // 5. dispatch ReviewsSubmitted -> stats projector (queued)
    }
}
```

Ordering matters: replaying an offline batch out of order produces wrong intervals.
Folding in `answered_at` order gives the same result as if the user had been online.

## Sessions

`POST /study/sessions` returns a ready-to-play payload so the client can run offline:

```
pool pairs owed a card (mid-ladder, or due, or graduated and never scheduled)
+ pool pairs at rung 0 (first meetings, up to the remaining new-per-day quota)
+ each term's content: text, translations, one example, audio_url
```

Selection rules:
- Due before new. A backlog of 300 due cards means no new words that day.
- **Read the two populations under SEPARATE limits.** Rung-0 pairs sort ahead of everything (no
  `due_at`, ordering is NULLS FIRST), so one capped query over a freshly triaged pool of a hundred
  words comes back as a hundred first meetings, is trimmed to the daily quota, and leaves every
  repeat out of the session.
- Mix collections unless the session is scoped to one (`collection_id` filter). A pool session
  mixes topics by design, so **far options prefer neighbours from the card's own collection** —
  otherwise a far option is far by SUBJECT and the card is answerable without knowing the word.
- Never show two forms of the same term in one session.
- Session size default 20, capped at 100.

More session rules:
- **The daily new-term quota is global, one per user** — a scoped session draws from the same
  remaining quota; five open collections don't grant five times the norm.
- **Dedupe the package by `term_id`** — a term in two collections is counted once, at the
  input, not only in statistics.
- The session is self-contained (offline) and its **composition is fixed server-side** under a
  `session_id`; an answer for a term outside that session is rejected.
- **Never a dead end.** Nothing to review → offer free practice (below), not an empty screen.

## Exercises and grading

The exercise modes are `multiple_choice`, `word_bank`, `typing`, `listening`, `cloze`
(`listening`/`cloze` are `> Not implemented yet` — they need TTS and good examples). Which
mode a card gets is chosen by state (`ExerciseSelector`), rotating review modes deterministically
on the term's `reps` (never `rand()`), degrading within the config-enabled set. The mode affects
**grading only, never scheduling** — the scheduler takes a `Grade` and never learns which mode
produced it.

- **The server grades, not the client.** A grading rule that lives in two runtimes drifts —
  the same disease as two definitions of "mastered". The client sends the raw answer; the
  server grades it (`AnswerGrader`). The client still checks locally for instant offline
  feedback, but that local check **must be no stricter than the server's** and does only a
  binary correct/incorrect for the animation — never a grade, never the median, never hints.
  (Client shows green, server says `hard` → invisible to the user. The reverse — client red,
  server correct — reads as a broken app and is not allowed.)
- **Recognition modes can never award `easy`.** `multiple_choice`/`word_bank`/`cloze` cap at
  `good`; only production (`typing`/`listening`) reaches `easy`. A four-way guess sent to a
  month-long interval is a word quietly forgotten.
- **Grade:** wrong → `again`; correct with a hint / typo / slow → `hard`; correct at normal
  pace → `good`; correct, fast, no hint, production mode → `easy`. "Slow"/"fast" are relative
  to the user's **personal median for that mode** (typing is far slower than multiple choice),
  computed over **correct answers only** (wrong ones carry deliberation and skew it); until
  there are enough samples, an absolute default split by word/phrase.
- **Lenient checking, in explicit stages** (order matters — merging them lets a typo pass as
  `good`): normalise (case, spacing, punctuation, contractions, article optional both ways) →
  full grade; an accepted synonym → full grade; a single-character typo on a ≥ 5-char answer →
  capped at `hard`; else `again`.
- **The answer key of a TEXT-graded card is TARGET-language text the term owns — never a
  translation.** This rule governs every card whose answer is compared as text: typed, assembled
  from chips, or picked as a sentence. Production is always into the language being learned:
  prompt in the user's language, answer in the target. `term_translations` are the prompt side and
  are **never** in a text answer key — accepting the translation where the target was asked is a
  wrong answer scored correct.
  What counts as the key depends on what the mode ASKED for, and that is decided in exactly one
  place, `ExerciseMode::gradesAgainstExample()`:
  - a word-level mode asks for the term → `terms.text` + alternative forms of the term;
  - a sentence-level mode (`scramble`; `dictation`/`pick_correct` when they land) asks the learner
    to reproduce the term's **pinned example sentence**, so the key is that sentence — the same one
    the card was built from (`term_examples`, ordered by `id`; both readers order identically so a
    term with several examples cannot be graded against one it never showed).

  Both branches stay inside the rule: the key is target-language text belonging to the term. A
  translation is not an accepted answer in either.
- **A reverse-recognition card is graded by IDENTITY, not by text, and that is the only card whose
  correct option is a translation.** Ladder step 1 (`term → translation`, see «Acquisition ladder»)
  shows the term and offers translations to tap. There is no text grading to protect: the learner
  taps an option, the client uploads that option's **id**, and the key is an id — the card's own
  `term_id`, because every option is identified by the term whose translation it is. So no
  translation string ever enters an answer key, and the rule above is untouched: the direction of
  the question is declared by the card (`ladder_step`), and identity-graded cards are a disjoint
  set from text-graded ones. Do NOT extend this to typed or assembled answers — there the previous
  rule is absolute.
- **Distractors** (`multiple_choice`) are other terms' target text, never a translation (mixing
  languages gives it away), and exclude any candidate whose translations overlap the target's
  (the near-duplicate that reads as correct for the same prompt).

## Free practice

When a collection has nothing due, offer a practice session rather than an empty screen. It draws
the same pool a study session does (optionally narrowed to one collection) — training is for words
someone decided to train.
Practice answers **are written to `reviews`** (with `is_practice = true`) but never affect
`user_term_progress` — not intervals, not state, not lapses. Reasons to write, not drop:

- the streak is "days with ≥ 1 review", and an hour of practice must not lose it;
- the "stats are rebuildable from `reviews`" invariant holds.

Practice is excluded from the latency median and from retention, and never spends the daily
new-term quota or introduces new terms.

## Statistics

All stats are projections of the `reviews` log:

- **Streak** — consecutive days with ≥1 review, computed in the user's timezone
  (store `timezone` on the user; a streak computed in UTC breaks for everyone not on UTC).
- **Retention** — `good+easy` share of reviews on cards in `review` state, windowed.
- **Mastered ("усвоено")** — `(state='review' AND interval_days >= 21) OR state='known'`.
  This is the single definition; it lives in one place (`Mastery::isMastered`) and every
  screen asks there — a second definition elsewhere guarantees two figures that disagree.
  Screens may *break it down* (confirmed by exercises vs `known` self-assessment vs in
  progress), but that is a breakdown of the one definition, never a second one.
- **Daily aggregates** — `daily_user_stats`, updated by a queued projector, rebuildable by
  replaying `reviews`. Any stats bug is a replay, not a data-loss incident.

Never compute statistics by scanning `reviews` inside an HTTP request.

## Testing (see also `testing-pest`)

The scheduler gets table-driven unit tests: given state + grade → expected state.
These are the cheapest, highest-value tests in the codebase — a scheduling regression is
invisible in QA and destroys user trust weeks later.

Cover at minimum: first review of a new term, graduation, lapse from a long interval,
ease floor, interval ceiling, out-of-order offline batch, duplicate review id.
