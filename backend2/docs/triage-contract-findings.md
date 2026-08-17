# Triage vertical slice — contract findings

> **STATUS: CLOSED (2026-08-03).** Every finding below is resolved or deliberately deferred;
> see the table. This document is frozen — new findings go to `docs/ROADMAP.md`, not here.

What building the triage client surfaced about the API contract. The point of the slice.
Read this before writing delta-sync — some of it is cheaper to fix now.

## Resolution

| Finding | Resolution | Commit |
|---|---|---|
| No "how many remain beyond the cap" | Envelope `{cards, remaining}`; `remaining` = eligible after exclusion | `ff9711c` |
| `latency_ms = 0` trap undocumented | OpenAPI: optional, `null`=unmeasured, never send `0` | `72ca50e` |
| Batch cap 200 vs offline backlog | Client chunks at 100 (triage + review), partial-success + 422-drop | `74e94a6` |
| Ordering relies on device `decided_at` | Reordered on per-user monotonic `client_seq` (data-corruption fix) | `72ca50e` |
| Over-fetch (`transcription`, word-card `example`) | Kept; documented as reserved for future exercise modes | `f157735` |
| No per-term `cefr` badge on the card | **Deferred** — optional client nicety, not part of the cutover → ROADMAP |
| Client must subtract locally-pending ids | Working as designed (`triageDeckProvider`); inherent to offline-first | — |
| Also fixed in passing: 422 wedging the queue | Permanent rejects dropped with a log, not retried forever | `349ec11` |
| Also fixed: triaged cards reappearing on re-entry (BUG-1) | Re-fetch queue on every entry | `154f6c9` |
| Also fixed: word latency floor unreachable on-device | Latency risk is phrase-only; word risk via cefr | `c175d18` |

On-device verification of all of the above (incl. the full-reinstall `client_seq` seed and the
independent connectivity flush) is recorded in `session-handoff.md`. The one item verified by
code review only, not on device, is the 422-drop path — noted there.

---

## Was the package self-contained? Mostly yes.

`GET /triage/queue` renders every card (term, translation, and — for phrases — an example)
from one fetch. No second request needed per card. Good.

### Missing / would-have-helped

- **No "how many remain beyond the cap".** The queue is capped at 40 and returns a flat list.
  The client cannot tell "these are all the new terms" from "these are the first 40 of 78".
  The spec's screen says "продолжить разбор на следующем заходе", but the API gives no
  `total_remaining` (or even a boolean "capped"), so the client can't show honest progress or
  decide whether to pre-fetch the rest for offline. **Recommend:** add `total_new` (or
  `remaining`) alongside the list, or return an envelope `{cards, remaining}`.
- **No per-term difficulty (`cefr`).** The card can't show a level badge. The spec wants a
  minimal card, so this is optional — but it is the one field the client would ask for next,
  and it already exists on the term.

### Came extra (mild over-fetch for triage)

- `transcription` (IPA) — the triage card never shows it.
- `example` / `example_translation` on **word** cards — the client only shows examples for
  phrases. They ride along unused on triage.

Not worth a triage-specific endpoint; noting for payload discipline if bytes ever matter.

## Where the format made the client compute a server's job

Nothing, on the read side — the queue is a clean list, no client-side aggregation. (Contrast
the still-old `/study/progress`, whose Flutter model computes a `ratio` from `learned/total` —
but that is the broken pre-slice contract, out of scope here.)

One thing the client MUST compute, correctly: **subtract locally-pending term ids from the
queue.** This is not a server failing — the server can't exclude swipes it hasn't received —
it is inherent to offline-first, and the client owns it.

## Offline friction

- **`latency_ms = 0` is a trap the contract doesn't document.** The server treats `null`
  neutrally and (since the fix) a non-positive value too, but the OpenAPI/skill never says
  "null = unmeasured, and do not send 0". A future client author reading only the spec could
  send 0 for an unmeasured swipe and silently push every "known" into a 7-day check.
  **Recommend:** document in OpenAPI that `latency_ms` is optional, `null` means unmeasured
  (neutral), and 0 must not be sent. The client here sends `null`, never `0`, and measures from
  the card's actual paint (post-frame), not the transition animation.
- **Batch cap 200 vs an offline backlog.** A single collection's queue is ≤ 40, so one pass is
  fine. But a client that accumulates swipes across collections/sessions offline can exceed 200
  and 422 the whole batch. The client must chunk at 200 (this slice does not — it sends the
  whole pending queue; fine for ≤ 40, latent for a real backlog). Same latent issue exists in
  `review_sync`. **Recommend:** document the cap prominently, or have the client chunk.
- **Ordering relies on the client's `decided_at`.** "Latest verdict wins" is keyed on a device
  clock, which the sync skill itself warns drifts. Single-device is fine; worth remembering
  before multi-device sync.

## What worked well (keep)

- Client ULID idempotency — retries are free; the whole offline/kill/retry story falls out of it.
- One self-contained fetch → the entire stack swipes with no network.
- The queue shape mirrors `reviews`, so the durable-queue + flush pipeline was a clean copy of
  the existing `review_queue`/`review_sync`.

## On-device protocol — how the code satisfies each (verify on the phone)

The five checks can only be *proven* on the device; here is how the implementation is built to
pass them, so the run is a verification, not a discovery:

1. **Load → airplane → swipe all → back online → arrive once.** Each swipe is saved to the
   keychain immediately; `flush()` runs on record (fails offline, queue kept), on app resume
   (network back) and on dispose. Upload is idempotent by ULID → exactly one set.
2. **Drop mid-flush → retry → one set on server.** `flush()` catches the error and keeps the
   queue; the retry re-sends the same ULIDs → server dedupes.
3. **Force-kill after 20 → restart → 20 kept, resume at 21.** The queue is persisted per swipe;
   on restart `triageDeckProvider` subtracts the pending term ids, so the deck resumes at the
   21st card and the 20 verdicts flush when possible.
4. **Re-enter same collection → triaged not shown.** Two layers: the server excludes triaged
   terms (once flushed), and the client excludes locally-pending ones (before flush).
5. **Undo → term back in queue.** Undo pops the history, drops the still-unsent verdict
   (`removePending`), rolls back the tally, and re-shows the card (re-arming the latency clock).

## Not done here (by design)

- The on-device protocol run itself (airplane mode, force-kill) — that is the device's job.
- The broken `/study/sessions` and raw `/reviews/batch` screens — left untouched; the training
  tab errors against the new backend, which is expected. Navigate via Коллекции.
