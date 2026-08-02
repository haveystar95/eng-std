# Session handoff — snapshot

> **Overwrite this file each session. It is a snapshot of current state, not a growing log.**
> Read it alongside `CLAUDE.md`, `ARCHITECTURE.md`, `.claude/skills/` (esp. `learning-srs`),
> `deptrac.yaml`, and `docs/triage-contract-findings.md`.

Branch: `feat/mobile-backend2-cutover` (not merged to `main`). Last updated: 2026-08-02.

---

## Done since the on-device protocol run (5 commits)

The run report and the contract findings it acted on are in `docs/triage-contract-findings.md`.
Commits (oldest→newest):

- `c175d18` — **word triage risk is cefr-only**; `WORD_MIN_READ_MS` removed as inapplicable
  (on-device the honest swipe floor is ~490 ms, so a 300 ms word threshold is either dead or
  flags every fast swipe). Latency risk applies to **phrases only** (900 ms, confirmed reachable).
- `72ca50e` — **Задача 1: order by `client_seq`, not the device clock.** `term_triages` and
  `reviews` gained `client_seq` (bigint, default 0; real values start at 1). The current triage
  verdict per `(user, term)` is the greatest `client_seq` across the whole log
  (`TriageRepository::currentByTerm`), re-projected for touched terms only; reviews fold by
  `client_seq` instead of `answered_at`. `decided_at`/`answered_at` are now reference-only.
  New `GET /sync/cursor` returns `{max_triage_seq, max_review_seq}` for client counter seeding.
- `773d47f` — **mobile: per-user monotonic `client_seq`.** `SeqCounter` (keychain, two keys
  `seq_triage`/`seq_review`, survives queue clears), assigned per triage swipe; seeded from
  `/sync/cursor` on login/restore; `connectivity_plus` flush on network return.
- `154f6c9` — **BUG-1: re-fetch triage queue on every entry.** `TriageScreen` is a
  `ConsumerStatefulWidget` that invalidates the deck provider in `initState`, so triaged cards
  don't reappear on re-entry (was Riverpod auto-dispose timing, non-deterministic).
- `349ec11` — **drop 422/413-rejected records from durable queues** (was: retry forever).
  `isPermanentReject` in `api_client.dart`; applied to both triage and review flush.

Gates on every backend commit: `composer arch` (0 violations), `composer stan` (level 8, clean),
`composer test` (192 pass). Mobile: `flutter analyze` clean.

---

## Verification status — device vs code only

| Item | How verified |
|---|---|
| BUG-1 (re-entry re-fetch, no Riverpod assert) | **on device** ✅ |
| Seed on login + **full reinstall** (keychain wiped → counter seeds to server max; next swipe got `client_seq 10`, not 1) | **on device** ✅ |
| Connectivity flush (flush fired while Control Center held open, before `resumed`; `connectivity_plus` links natively via SPM) | **on device** ✅ — this cleared the earlier false positive where "auto-flush on network return" was really the Control-Center→resume trigger |
| `client_seq` monotonic (1→10 across swipes, relaunch, reinstall) | **on device** ✅ |
| Задача 1 ordering (within-batch clock skew; out-of-order chunk arrival) — triage & reviews | **automated tests** (Pest). Not hand-reproducible in the triage UI: undo removes the unsent verdict and a swiped card leaves the deck, so two verdicts for one term can't be produced by hand. |
| **422 drop (`349ec11`)** | **code review only — NOT run-tested.** Release hides the drop log, the reviews screen is stale, the keychain queue isn't inspectable. **Treat as unverified.** |

---

## Decisions that must not be silently revised

Carried over from the run brief, plus this session's:

- Progress is keyed `(user_id, term_id)`, never a collection item. Terms globally deduplicated.
- `reviews` and `term_triages` append-only; idempotent by client ULID.
- **Ordering is by `client_seq`, never the device clock.** Within one batch all rows share
  `created_at` (single write), so `created_at` can't disambiguate — the per-user monotonic
  `client_seq` is the order key. `decided_at`/`answered_at` are analytics-only.
- **`/sync/cursor` lives in Learning, not on `/auth/me`.** deptrac forbids Identity→Learning;
  the cursor is a Learning concept and will grow next to `GET /sync?since=`. Do not move it.
- Server grades; client's local check must be no stricter. Recognition modes never award `easy`.
- `Mastery::isMastered` is the one definition of "mastered".
- Pre-release: contract breaks cleanly, no deprecation.

---

## Known limitations / non-obvious (so they don't resurface as surprises)

- **`client_seq` is single-device.** Two phones used in parallel keep independent counters and
  **will collide** — accepted deliberately for pre-release. The real fix (server-assigned arrival
  order) belongs with multi-device sync. Documented on `/sync/cursor` and in `SeqCounter`.
- **Reviews pipeline is stale.** The mobile review upload still sends a pre-computed `grade`
  (not the raw-answer shape) and no `client_seq`, so every `/reviews/batch` flush **422s by
  contract**. The `seq_review` counter key already exists; wire it up when the exercise/session
  screens are rebuilt. Until then, `349ec11` keeps those 422s from wedging the queue.
- **Offline entry to Разбор shows a load error** (the deck now always re-fetches on entry). Not a
  bug — the durable queue is intact, nothing is lost. Expected consequence of the BUG-1 fix.
- **`debugPrint` is invisible under `flutter run --release`** (routed to os_log). Don't rely on
  app-side prints for on-device diagnosis; verify via the server (`api_request_logs`) and DB.

---

## Next up — Задача 3 + Задача 4 together (form of sending & queues)

Do them in one pass; both touch backend + client. **Do not start mid-way if context is tight —
a broken contract half-landed is worse than not starting.**

- **Задача 3 — `remaining`/`total_new` in `GET /triage/queue`.** Return an envelope so the client
  can show honest progress and decide whether to pre-fetch the rest.
  **DECISION (make it this way, or it defaults wrong):** `remaining` must be computed **after
  excluding already-triaged terms** — i.e. the same count the *next* `GET` would return, not
  "total new terms in the collection". Otherwise the client shows a remainder that won't match
  the real queue — the same class of divergence we hit with "усвоено". OpenAPI + test.
- **Задача 4 — client chunking at 200.** A single collection's queue is ≤40 so one pass is fine,
  but an accumulated offline backlog can exceed the 200-item batch cap and 422 the whole batch.
  Chunk on the client. Because ordering rides on `client_seq` (not arrival), chunks need not be
  strictly sequential — but keep it simple. Same latent issue exists for `review_sync`.

Then: **Задача 5** (over-fetch — `transcription`, word-card `example`/`example_translation`; low
priority, keep until session screens confirm they're unused). Then **delta-sync `GET /sync?since=`**,
which builds on this same model.

---

## Deferred to Phase 2 (not this cutover)

- Exercise modes `listening` / `cloze` (need TTS + good examples).
- `term_forms` (alternative answer-key forms) and `frequency_rank`.
- Squashing the migration history.

---

## Running / verifying (recipes)

- Backend2 in Docker (`backend2/`): `docker compose up -d`; artisan via
  `docker compose exec app php artisan …`; Postgres on `localhost:5433`
  (`wordtrainer`/`wordtrainer`/`secret`). Code is volume-mounted (live). Gates:
  `composer arch && composer stan && composer test`.
- Backend2 is fronted by the stable ngrok domain `https://greedily-thermos-finer.ngrok-free.dev`
  (the app's `apiBaseUrl` default already points there — no `--dart-define` needed).
- App on device (`mobile/`), wireless iPhone `00008110-000A7CCC3492801E`:
  `PATH="/opt/homebrew/bin:$PATH" LANG=en_US.UTF-8 flutter run --release -d <id>`.
  After **deleting** the app, the free personal team needs a manual "Trust" (Settings → General →
  VPN & Device Management) before it will launch. `connectivity_plus`/`flutter_secure_storage`
  resolve via SPM; only `flutter_tts` uses CocoaPods.
- On-device observation: watch `api_request_logs` (method/path/status/bodies, secrets redacted) and
  the DB directly; the app can't show release logs.
