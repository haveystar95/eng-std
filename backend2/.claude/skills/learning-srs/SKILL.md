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
                          ▲                     │
                          │                  grade=again
                          └── relearning ◄──────┘
```

- `new` — never answered. Introduced at a capped rate (`new_terms_per_day`, default 10,
  user-configurable) so a session doesn't drown the user.
- `learning` — short intra-day steps (1m, 10m). Not scheduled by ease yet.
- `review` — long intervals driven by the scheduler.
- `relearning` — lapsed; short steps again, then returns with a reduced interval.

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

Modes: `flashcard`, `typing`, `multiple_choice`, `listening`. Mode affects grading input,
not scheduling — a grade is a grade.

## Statistics

All stats are projections of the `reviews` log:

- **Streak** — consecutive days with ≥1 review, computed in the user's timezone
  (store `timezone` on the user; a streak computed in UTC breaks for everyone not on UTC).
- **Retention** — `good+easy` share of reviews on cards in `review` state, windowed.
- **Known terms** — count of `state='review' AND interval_days >= 21`. Define "known" once
  and reuse it; don't invent a second definition in a new endpoint.
- **Daily aggregates** — `daily_user_stats`, updated by a queued projector, rebuildable by
  replaying `reviews`. Any stats bug is a replay, not a data-loss incident.

Never compute statistics by scanning `reviews` inside an HTTP request.

## Testing (see also `testing-pest`)

The scheduler gets table-driven unit tests: given state + grade → expected state.
These are the cheapest, highest-value tests in the codebase — a scheduling regression is
invisible in QA and destroys user trust weeks later.

Cover at minimum: first review of a new term, graduation, lapse from a long interval,
ease floor, interval ceiling, out-of-order offline batch, duplicate review id.
