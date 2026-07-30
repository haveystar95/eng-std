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

## Triage — the first-pass sweep of a collection

AI generation mixes basic vocabulary (`money`, `hello`) into the useful set. Triage is a
swipe pass that filters out what the user already knows, so the trainer doesn't waste their
time. Its invariants:

- **A triage marks a term globally, on `(user, term)`** — never inside a collection. Sifting
  out `money` in "Bank" removes it from "Shop" too. Direct consequence of progress living on
  the term.
- **Three verdicts, not two** — a binary choice makes people lie toward "known":
  `known` → `known` state (a verification is scheduled); `unknown` → stays `new` (full intro
  via `multiple_choice`); `unsure` → straight to `learning` (the intro recognition step is
  skipped — the skip is a *state*, not a flag).
- **Triage is never written to `reviews`.** A swipe is a self-assessment, not an exercise
  answer; in the review log it would inflate retention with unproven words. It lives in an
  append-only `term_triages` log (client ULID, no `unique(user, term)` — re-triage and
  "return to learning" are new rows; the current verdict is the latest by `decided_at`).
  Progress state is a projection of this log, like it is of `reviews`.
- **The triage queue excludes already-triaged terms**, distinct from the study "new" pool
  which only cares whether a progress row exists — so an `unknown`-swiped term stays new
  (studyable) but is never asked again.
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
due terms (due_at <= now, ordered by due_at, limit = session_size)
+ new terms (up to remaining new-per-day quota)
+ each term's content: text, translations, one example, audio_url
```

Selection rules:
- Due before new. A backlog of 300 due cards means no new words that day.
- Mix collections unless the session is scoped to one (`collection_id` filter).
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
- **The answer key is the TARGET-language forms only.** Production is always into the language
  being learned: prompt in the user's language (translation/example/audio), answer in the
  target. `ExpectedAnswer` = `terms.text` + alternative forms of the term when they exist;
  `term_translations` are the prompt side and are **never** in the answer key — accepting the
  translation where the target was asked is a wrong answer scored correct.
- **Distractors** (`multiple_choice`) are other terms' target text, never a translation (mixing
  languages gives it away), and exclude any candidate whose translations overlap the target's
  (the near-duplicate that reads as correct for the same prompt).

## Free practice

When a collection has nothing due, offer a practice session rather than an empty screen.
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
