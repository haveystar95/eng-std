# backend2 — build roadmap

The build order and status. A fresh session should read this + `CLAUDE.md` + the
skills, then continue at the first unchecked item. Build every backend change per
`.claude/skills/` and finish each task with `composer arch && composer stan && composer test`.

> Context: `backend2` is the paradigm-conformant rewrite of the app's API (modular
> monolith + DDD). The **old flat `../backend`** is still the live API the iOS app talks
> to — leave it running until Phase 4 cuts the app over to backend2.

## Phase 1 — foundation & core domain  ✅ done
- [x] Laravel 12 + Postgres 17/pgvector + Redis/Horizon in Docker; Deptrac + PHPStan 8 + Pest wired.
- [x] Module skeleton (6 modules × 4 layers) + ServiceProviders.
- [x] **Shared** kernel: `Ulid`, `Identifier`, `Clock`/`SystemClock`, `DomainEvent`; cross-cutting VOs (`TermId`, `CollectionId`, `UserId`, `LanguageCode`).
- [x] **Vocabulary**: `Term` aggregate, dedup (`FindOrCreateTerm`), normalizer, migration (terms/translations/examples + pgvector), repo. Verified on Postgres.
- [x] **Collections**: `Collection` aggregate (custom, items, owner rules), `CreateCustomCollection`/`AddTermToCollection`, migration (soft-delete, items_count), repo.

## Phase 2 — remaining core domain  ⬜ next
- [x] **Learning** (`learning-srs` skill): `TermProgress` aggregate keyed `(user_id, term_id)`, state machine new→learning→review↔relearning; pure `Sm2Scheduler` behind a `Scheduler` port (+ injectable `Fuzz`); `SubmitReviews` (append-only `reviews`, client ULID + `insertIgnore`, folds in `answered_at` order), `StartStudySession`, `GetDueTerms` query (due-before-new, quota, session cap); `daily_user_stats` via `StatsProjector`. Migration for all four tables (checks/FKs/partial due index). Cross-module: `TransactionManager` (Shared) + `TermExistenceReader` (Vocabulary Application) for unknown-term rejection. 30 unit tests. `arch/stan/test` green. **Presentation (HTTP) deferred to Phase 3.** Note: collection-scoped due + user-tz stats stubbed until Collections query / Identity land.
- [x] **Generation** (`ai-collection-generation` skill, built 2026-07-27): `GenerationRequest` aggregate (pending→running→succeeded|failed) + `generation_requests` table; `CollectionGeneratorPort` with `OpenAiCollectionGenerator` (structured outputs, versioned prompt file `generate_collection.v1.md`) + `FakeCollectionGenerator` (`GENERATION_DRIVER=fake`); async `GenerateCollectionJob` (3 tries, `failed()`→FailGeneration), sync path for console. `DraftValidator` (size/cefr/dedup), daily quota (non-failed requests, auto-refund on failure), token/cost tracking. Cross-module via **primitive** boundaries: Vocabulary `ImportTerm` (new shim; Generation must not touch Vocabulary Domain VOs) + Collections `CreateGeneratedCollection` (new; source=ai) + `AddTermToCollection`, all through Application. **Deferred (noted):** embedding/semantic dedup, prompt cache, language detection, push.
- [x] **Identity** (thin, Laravel-native, built 2026-07-27): Sanctum `User` (ULID id) + `Profile`, Google sign-in ported from `../backend` (`google/auth` verifier). Thin module but deptrac-clean: Eloquent/Sanctum work sits in Infrastructure behind Application ports (`GoogleSignIn`, `GoogleTokenVerifier`, `UserReader`, `ProfileUpdater`, `SignOut`); controllers depend only on ports + DTOs. `users` table reworked to ULID + `google_id`/`avatar`; `ulidMorphs` on personal_access_tokens; `profiles` migration in-module. **Devices/settings beyond profile not yet done.**

## Phase 3 — HTTP surface (endpoints)  ⬜
Per `api-endpoint` + `mobile-sync-contract`. Each module's `Presentation/Http` (controller → Command/Query → Resource, FormRequest, Policy, `routes.php` under `/api/v1`).
- [x] Auth endpoints (Identity, built 2026-07-27): `POST /api/v1/auth/google`, `GET /api/v1/auth/me`, `POST /api/v1/auth/logout`, `PUT /api/v1/profile`. First live HTTP surface in backend2. RFC7807-lite: `InvalidGoogleToken` → 422 mapped in `bootstrap/app.php`. 9 feature tests (fake Google verifier). **Still to do for this bullet: author `openapi/openapi.yaml` for these routes.**
- [~] Collections (built 2026-07-27): **CRUD done** — `GET /collections` (cursor-paginated summaries), `POST /collections` (client-id idempotent), `GET/PATCH/DELETE /collections/{id}` (owner-only; soft-delete tombstone). Query side added (`ListUserCollections`+reader, `GetCollection`); commands `UpdateCollection`/`DeleteCollection` + repo `delete`. **Store subscribe done (B5, below).** **Still TODO: items add/remove/reorder, fork (B5 follow-up, below), term-content hydration on show.**
- [ ] Terms: lookup/search.
- [x] Study (built 2026-07-27): `POST /api/v1/reviews/batch` (idempotent, `{accepted,duplicates,unknown}`), `GET /api/v1/study/due` (hydrated cards, content from Vocabulary `TermContentReader`), `GET /api/v1/stats` (total/learned/mastered/due-today/reviews-today/streak). Progress is created on first review, so a studied term becomes due later. **`POST /study/sessions` deferred** (reviews carry an optional session id; app can omit).
- [x] Term-content hydration: Vocabulary `TermContentReader` powers collection detail items + study cards (self-contained session payload). **Terms lookup/search endpoint still TODO** (not needed for the cutover — display is covered by hydration).
- [x] Generation (built 2026-07-27): `POST /api/v1/generations` (202 + pending, client polls), `GET /api/v1/generations/{id}` (owner-only → 404 otherwise). Quota → 429 (mapped in `bootstrap/app.php`). Plus an artisan `generation:make {user} {prompt}` command (runs synchronously, same Application handlers). 19 tests (domain state machine, DraftValidator, cross-module ProcessGeneration, feature). **Still to do for this bullet: `openapi/openapi.yaml`.**
- [ ] `GET /sync?since=` delta sync.
- [~] `openapi/openapi.yaml` started (source of truth): documents Auth + Generation + Collections. Extend as new endpoints land. **Also established:** RFC 7807 `application/problem+json` errors via a polymorphic `Shared\Domain\Exception\ProblemDetails` interface + one renderer in `bootstrap/app.php` (domain exceptions carry a stable `code`); input validation keeps Laravel's 422 `{message, errors}`.

## Phase 4 — mobile cutover  ✅ effectively done (2026-07-29)
- [~] ~~Generate the Dart client from `openapi/openapi.yaml`~~ — **skipped by choice**: the
  hand-written `mobile/lib/data/` (models/api_client/providers) was adapted directly to the
  contract (`/api/v1`, `data`-wrapped, ULID strings, `/reviews/batch`, `/study/*`,
  `/generations`). Codegen deferred indefinitely; not worth it for one client.
- [x] Flutter app points at backend2. Exposed via ngrok service `wt_ngrok` (static domain
  `https://greedily-thermos-finer.ngrok-free.dev`, = default in `mobile/lib/core/config.dart`).
- [x] Verified on device (user runs `flutter run --release` on the iPhone; iterating on UX).
- [ ] Retire the old `../backend` — **not yet** (kept as reference/fallback; no data to migrate
  beyond the single user's test data).

## Phase 5 — product polish & feature depth (this session, 2026-07-29)
Built on top of the cutover; branch `feat/mobile-backend2-cutover` (not merged to main yet).

- [x] **Mobile "liquid glass" redesign** — `mobile/lib/core/glass.dart` (AmbientBackground,
  GlassCard/Chip/Button/Field, SpringTap, AppFeedback haptics+click). All screens redone:
  login, onboarding (first-run language/level/goal, local `onboarded` flag), training home
  (glass dashboard), collections + generate/word/collection dialogs, collection detail,
  profile/settings, and a floating glass bottom tab bar.
- [x] **Learning Tier 1 (offline-first reviews)** — `ReviewQueue` + `ReviewSync`: each answer
  is recorded locally (client ULID + answer-time `answered_at`) then flushed as a batch to
  `/reviews/batch`; flush on answer/finish/launch/resume. Idempotent, survives no-network.
- [x] **Learning Tier 2 (new words enter SRS)** — `/study/due` returns due **then new** (terms
  in the user's collections with no progress row), capped by a daily quota (`GetStudyCards`
  → `IntroducedTermsReader`). "New" is derived, not seeded. Home counts due+new.
- [x] **Collection progress + scoped study (2026-07-29)** — `GET /study/progress` (derived
  per-collection learned/mastered/due, Learning folds `ProgressSnapshotReader` over
  Collections' `UserCollectionTermsReader`) → progress bars on tiles; `GET
  /study/due?collection_id=` → collection sessions use the scoped SRS queue.
- [x] **Training UX** — 2-swipe model (right=Знаю/good, left=Не знаю/again) + secondary
  Трудно/Легко buttons + full SM-2 grades; content-hugging card; swipe legend.
- [x] **AI generation quality (prompt v2)** — persist IPA + examples on terms (were being
  dropped), enforce the requested term count (validator trims to `size`), richer prompt with
  `transcription` + `example_translation`; `prompt_version=v2`.
- [x] **Observability module** — `api_request_logs` table; terminable middleware logs inbound
  requests, an Http-client listener logs outbound (OpenAI) calls; pure `SecretRedactor`
  keeps credentials out; debug stack traces trimmed, bodies capped 16KB.

## Where the finish line is (definition of done for the app)
The app is **usable end-to-end today**. "Done" for this single-user product means:
- [ ] **Delta sync** `GET /sync?since=` + client reconciliation — the one real gap for true
  offline (Phase 3). Everything else is offline-*friendly* (idempotent writes) but not full sync.
- [ ] **Log retention** — prune `api_request_logs` (grows unbounded); a scheduled cleanup.
- [ ] **Generation depth (optional)** — prompt cache by `(normalized_prompt, langs, version)`,
  semantic dedup (pgvector), an eval set in `tests/Fixtures/`.
- [ ] **Retire `../backend`** once confident backend2 covers everything on device.
- [ ] **Merge `feat/mobile-backend2-cutover` → main** (only on the user's explicit ask).
Nice-to-haves, not blockers: undo-last-swipe + end-of-session flush, `Terms lookup/search`,
push notifications, `POST /study/sessions`, per-item CEFR badge, AI open-answer check.

## Generation → full feature (in progress, started 2026-08-04)
Turning the built-on-the-fly generator into the product's headline feature (backend + contract +
UI/UX). Plan agreed with the user; working in Part-C order, one commit per point, gates green.

**Done this session (committed):**
- **A5 — eval set + `generation:eval`** (`135a056`): `tests/Fixtures/generation-prompts.json` (~20
  prompts across categories) + a manual quality command (delivered-vs-requested, phrase &
  idiom+phrasal ratio, dup rate, CEFR spread, image-prompt coverage, tokens/cost; `--fake` for a
  no-spend smoke). **v2 baseline saved** at `docs/generation-eval-v2-baseline.json` (20/20 ok, 1
  under-delivered, avg phrase 56%, idiom/phrasal 0%, no dupes, ~$0.27). Diff v3 against this.
- **A4 hygiene, part 1** (`1201122`): retry only transient errors (a rejected `InvalidGeneratedDraft`
  fails terminally now, no 3× re-spend); `recordAttempt()` persists tokens/cost the moment the model
  answers so a validation failure no longer vanishes from the spend model; truncated raw response
  kept on `generation_requests.raw_response` for diagnosis.

**Done since (committed 2026-08-04):**
- **A1 + A6 — type taxonomy + prompt v3** (`130089e`, `758bf81`, `237b795`, `62f9dc9`): term type
  `word|phrase|idiom|phrasal_verb`; prompt v3 (taxonomy + AVOID block); eval-compared v2↔v3, flipped.
- **A3 — Pexels images** (`196ce20`, `b1d5f9f`, `fd47322`, `cba9661`, `cc86110`, `c3525ab`): nullable
  image columns on terms + collections; `ImageSearchPort`/Pexels adapter/fake; prompt v4 (per-item
  `image_api_prompt` + `collection_image_prompt`, gated to v4+); flipped `PROMPT_VERSION='v4'` after a
  real-LLM eval (no A1 regression, img% 100%, `docs/generation-eval-v4.json`); async best-effort
  `AttachImagesJob` (never-overwrite, empty=null-no-retry, transient=retry+backoff, adapter throttle);
  `image_url` + attribution shipped additively in `/sync` + mirrored in the mobile drift schema (v4).
  Passed `invariant-reviewer` CLEAN. **Verified end-to-end server-side** (`PEXELS_API_KEY` set): a
  live `generation:make` attached a real Pexels cover + 8/8 term photos with attribution. **A3
  findings (deferred):** (1) not yet run on the device — `/sync` image fields + drift v4 are code-only.
  (2) cache-path collection covers are re-searched (one extra Pexels call per cache hit) rather than
  copying the source URL — accepted. Also fixed a latent test-isolation flake (RefreshDatabase on two
  outbound-calling generation tests whose `api_request_logs` leaked).

**Post-device-run UX fixes (DONE, `9eab408` backend + `e651608` client):** flip-card triage
(front = target term only; tap → back with image + translation + example; verdict from either face)
+ a `revealed` signal (flipped before deciding) that rides `/triage/batch` into `term_triages` and
makes a peeked «known» a verification risk factor (planner, on par with a too-fast swipe; latency now
measured to the FIRST event); photo attribution hidden behind `kShowPhotoAttribution=false`
(**must be flipped on before any public release — Pexels API requires crediting the photographer**;
data still syncs); create-screen keyboard no longer covers «Создать»; term translations calmed from
loud coral to secondary. Backend: gates green + invariant-reviewer CLEAN. Client: analyze + 27 tests.

**Part B — client generation UX (DONE, `42fd584`):** create screen (situation + rotating placeholder,
size маленькая/средняя/большая→10/15/22, level from profile, target-language dropdown, quota-greyed
button with remaining+resets_at); pending card backed by a client-only `PendingGenerations` drift
table (survives kill) with launch/resume reconciliation; images (collection cover + term photo) and
type badges from the drift stream; Pexels attribution (clickable, adds `url_launcher`); first-contact
«Разобрать» banner. All reads from the local DB; network only for POST /generations + polling.
`flutter analyze` clean, 23 tests. **NOT yet run on device — that end-to-end run is the finish line**
(scenarios in `session-handoff.md`: e2e w/ images, under-delivery, kill-during-gen, quota exhausted,
offline view after sync, TTS on a non-standard target language).

**Still open from Part C (backend hygiene, optional):**
- **A4 hygiene, part 2 — prompt-cache lookup**: on a `(normalized_prompt, source_lang, target_lang,
  prompt_version)` hit, reuse the prior succeeded request's **term set** (build a fresh personal
  collection, no LLM call). Needs a Generation repo finder + a non-owner-scoped Collections read for
  the cached collection's term ids/title. Minor decisions to make: reuse cached title? (yes), no
  quota refund on a hit, label model `cache`/cost 0.
- **A4 hygiene, part 3 — quota in `GET /me`**: `generation: {limit, used, remaining, resets_at}`.
  deptrac edge `Identity/Presentation → Generation/Application` (legal, cross-module via Application).
  **OPEN QUESTION (below): no per-user timezone is stored, so `resets_at` in the user's tz can't be
  computed — decide absolute-UTC-instant vs. adding a profile timezone.**
- (A2, A1+A6, A3 above are DONE — see "Done since". A4 parts 2–3 are the only remaining backend
  hygiene items and are optional; the headline path now moves to **Part B**.)

**Decided with the user (do not silently revise):** system decides collection composition (no
size-slider — client sends маленькая/средняя/большая → 10/15/22); pending-generation card lives in a
**drift table** (survives app kill) with start-up reconciliation (succeeded→drop, failed→error+retry,
pending→poll, 404-or->24h→drop+log); Pexels attribution stored **on the term** (`image_author`,
`image_author_url`) next to `image_url` and shipped in `/sync`; images cached **per term globally**
(shared terms, one search), empty result = null + placeholder, no retry on empty.

**Out of scope for this feature (deferred here, per the user):**
- **Extending an existing collection** (passing already-owned terms into the prompt) — next block.
- **«Как прошло» / post-session feedback loop** — next block.
- **Curated starter content** — separate; needs a user-facing situation picker.
- **Push instead of polling** for generation-ready — client polls for now.

Deferred from the triage-contract close-out (2026-08-03; `triage-contract-findings.md` is now
frozen — closed items live there, open ones here):
- **Online triage sends one POST per swipe** (~35/deck) — battery + log noise. Left as-is:
  batching online is a behaviour change not worth landing unverified, and the durable queue
  already guarantees delivery. If addressed: small size-based batches + keep the immediate
  flush of the last swipe on screen-exit. Offline is already chunked (Задача 4).
- **Per-term `cefr` badge on the triage card** — the field exists on the term; the card omits it
  by design (minimal card). Purely a client nicety.
- **`client_seq` collides across two devices used in parallel** — accepted for pre-release; the
  real fix (server-assigned arrival order) belongs with delta-sync / multi-device.
- **Reviews upload pipeline is stale** (pre-`client_seq`, pre-raw-answer) → 422s every flush;
  wire it up when the exercise/session screens are (re)built. The `seq_review` counter is ready.

Deferred from the offline-mode build (Parts 2 & 3, 2026-08-03 — local DB + delta sync + collection
view landed; client reads now come from drift, not the network):
- **`/sync` collections payload carries no `source`/`type`** → the "ИИ" badge and AI icon are lost
  now that the collection list reads from the local mirror (not just offline — everywhere). PLANNED
  (not "someday"): the my/store/generated distinction on the collection card is wanted going forward,
  not only the badge. Add `source` + `type` to `CollectionSyncRow`/`CollectionChange`, the reader
  `select`, the serializer and the client mapper (`_toCollection`) — same faithful-mirror principle
  as the Part-1 deviations. **Sequencing: do it as one small commit AFTER the device acceptance run**,
  so the just-validated `/sync` isn't touched before it's verified on the phone.
- **`GET /study/progress` field names never matched the client.** The resource sends `terms_total`/
  `due_count`/`mastered_count`; the mobile model reads `total`/`learned`/`mastered`/`due` (+ a
  `learned` the resource never had) → the progress bars parsed to all-zeros and rendered nothing
  online. The client now derives per-collection progress locally (bars work for the first time), so
  the endpoint is **unused by the app**. Either delete it (pre-release) or realign the contract if a
  server-computed progress read is wanted again.
- **Streak / reviews-today are cached, not delta'd.** They come from `daily_user_stats`, which isn't
  in `/sync`; the client caches them opportunistically from `/stats` while online, so offline they're
  last-known (never wrong-to-zero, but stale). For accurate offline streak, add `daily_user_stats`
  (today's row) to the delta feed.
- **Triage deck now builds from the local DB, so it opens offline.** One residual: an
  `unknown` swipe writes no progress row and `term_triages` isn't in the delta feed, so the local
  `TriagedTerms` marker is the only thing keeping such a term out of the deck. It's wiped on
  reinstall, so after a reinstall unknown-swiped terms reappear for re-triage (known/unsure survive
  via their synced progress rows). Acceptable (reinstall re-syncs everything). To fully match the
  server, add a `triaged` signal (or `term_triages`) to `/sync`.
- **Orphaned local `terms`/`progress` after a collection delete aren't GC'd on the client.** Harmless
  (reads join through `collection_items`, so orphans don't render; future syncs stop re-sending them),
  but the rows linger. GC on the client if local size ever matters.
- **Two Part-1 review items — both VERIFIED clean, no action.** (1) Same-second pagination is safe:
  the cursor is an offset into a frozen, totally-ordered stream (`ORDER BY updated_at, <unique id>` in
  every reader), not a bare timestamp — no boundary loss. (2) No soft-deleted `collection_items` leak
  past `SoftDeletes`: the only raw readers are the sync reader (must see tombstones) and
  `EloquentUserCollectionTermsReader` (all three methods filter `ci.deleted_at`); the model uses
  `SoftDeletes`; Learning never touches the table directly.

## Decisions & conventions already established (don't re-derive)

- **Where knowledge auto-loads:** `CLAUDE.md` files (root `../CLAUDE.md`, this dir's `CLAUDE.md`, `../mobile/CLAUDE.md`) load automatically; the 7 skills in `.claude/skills/` are directory-scoped to `backend2/`; the user's memory notes load each session. Plain docs like this one are read on demand — start by reading them.
- **Cross-cutting VOs live in `Shared/Domain/ValueObject`**: `Ulid`, `Identifier` (base), `TermId`, `CollectionId`, `UserId`, `LanguageCode`. Reason: Deptrac lets any module's `Domain` depend only on `SharedDomain`, so ids/lang referenced across modules must be Shared. Module-specific VOs (e.g. `TermType`, `Visibility`) stay in their module.
- **ULID**: pure-PHP generator in `Shared\Domain\ValueObject\Ulid` (no symfony/uid in Domain). Stored as `char(26)`.
- **Repository pattern**: interface in `Domain/Repository`, `Eloquent<X>Repository` + `<X>Mapper` + `<X>Model` in `Infrastructure/Eloquent`; repo returns Domain entities, never models; writes wrapped in `DB::transaction`; provider binds interface→impl in `Infrastructure/Provider/<Module>ServiceProvider::register()`.
- **Migrations** live in each module's `Infrastructure/Migration` (auto-loaded by its provider). Postgres-specifics (extensions, `vector`, `COALESCE`/partial/hnsw indexes, CHECK constraints) via `DB::statement`.
- **Tests**: Domain/Application unit-tested with **no DB** using doubles in `tests/Doubles/` (`FixedClock`, `InMemoryTermRepository`, `InMemoryCollectionRepository`). Pest binds Laravel `TestCase` only to `tests/Feature`.
- **Commands are invokable handlers** (`__invoke`), return an id or void. No business logic in controllers/jobs.
- **Run it:** `cd backend2 && docker compose up -d`; then `docker compose exec app php artisan <cmd>` / `docker compose exec app php vendor/bin/{pest,phpstan,deptrac}` (or `composer arch|stan|test`). The `APP_NAME variable is not set` warning from `docker compose` is cosmetic.

## Open questions
- **SRS algorithm**: ARCHITECTURE.md sketches SM-2 (`ease_factor/interval`); the old MVP used FSRS and it worked well. The `learning-srs` skill is authoritative — reconcile there before building Learning.
- Data migration from old `../backend` (if any real user data must be carried over — currently just the single user's test data).
- `generation_request_id` on collections: column exists; a `Shared` `GenerationRequestId` VO will be added when the Generation module is built.
- **`profiles.timezone` — needed for the streak** (learning-srs: a lesson at 23:50 local must count
  as "today" or the streak breaks for anyone not on UTC). Add the column when the streak reaches the
  client; **at that point also revisit the generation-quota day boundary** (move it from UTC-day to
  local midnight if wanted). DECIDED for now (2026-08-04): `GET /me` `generation.resets_at` is an
  **absolute next-UTC-midnight instant** (ISO with `Z`); the client renders it in device-local time,
  which answers "when can I generate again" without any stored timezone. Quota stays UTC-day.

## Backend tails (B-series, 2026-08-06)

Small backend tasks accumulated by the design pass; each is its own commit, gates + invariant-reviewer.

- [x] **B8 — word_bank eligibility + phrasal-verb decoys** (`96b8979`): `ExerciseSelector` gates
  word_bank to multi-word answers (single word in learning → multiple_choice); `ChipShuffler` mixes
  1–2 decoy particle chips into a phrasal verb's bank. Tests for both; invariant-reviewer CLEAN.
- [x] **B5 — store: premium + listing + tier quota** (this session): `collections.is_premium`,
  `profiles.tier` (free|premium); `GET /store/collections` (public+system by language pair, ordered by
  topic for client sections, keyset-paginated on `(topic,id)`, marks `is_subscribed`);
  `POST/DELETE /store/collections/{id}/subscribe` (idempotent add/remove to `user_collections`);
  premium gate → 403 `subscription_required` for a free tier. Generation daily limit now comes from
  the tier (**free 3 / premium 20**, `GenerationDailyLimit`) — read via Identity's `UserTierReader`
  (new deptrac edges `Generation/Collections Application → Identity Application`). `SubscriptionTier`
  is a Shared VO. StoreKit receipt validation flips `profiles.tier` later — for now it is set
  out-of-band and defaults to free.
- [x] **B3 — account deletion** (`DELETE /api/v1/auth/me`, this session): erases the user across
  every module through each module's own Application eraser (never a raw cross-table query) in one
  transaction — Collections (owned decks + items by cascade + store subscriptions), Learning
  (progress, reviews, triages, sessions, daily stats), Generation (requests), plus authorship
  anonymised on global `terms` (`created_by` → null; terms stay) and `api_request_logs.user_id` →
  null. All Sanctum tokens revoked, the user row (and profile by FK cascade) deleted last.
  Orchestrated from `Identity\Infrastructure\CrossModuleAccountEraser` behind an Identity
  `AccountEraser` port — the fan-out lives in **Infrastructure** so no module-graph cycle forms with
  the B5 `Collections/Generation Application → Identity Application` edges. Feature test covers the
  full spread + an untouched second user. **Finding (minor, deferred):** the `DELETE /auth/me`
  request is itself logged by the terminable request-logging middleware *after* the response, so one
  fresh `api_request_logs` row carries the just-deleted user's id. Harmless (already secret-redacted)
  and swept by the planned log-retention prune; a stricter fix would skip user_id for this endpoint.
- [ ] **B5 follow-up — fork a store collection into a custom one** (deferred, decided semantics):
  `POST /store/collections/{id}/fork` creates a new **custom** collection owned by the user and
  **copies the `collection_items` rows** (each referencing the **same global `term_id`**) into it.
  Terms are globally deduplicated and are **never duplicated** — a fork copies references, not terms;
  progress stays keyed on `(user, term)` and carries across. Same premium gate as subscribe
  (`subscription_required` for a free user forking a premium deck). This is the invariant the
  invariant-reviewer guards: any "duplicate the terms" implementation is wrong.
- [ ] **B7 — evening-slot push rule** (spec only, not implemented; recorded here per the design pass):
  the app sends **one daily reminder in an evening slot**, but it is **suppressed on any day the user
  already studied** — the streak is the reward, so a day that's already done gets no nudge (never
  double-notify). **Event-driven pushes** (generation ready, verification due) are *outside* this
  daily limit and fire regardless. **APNs delivery is a release blocker**: it needs the Apple push
  cert, a device-token register endpoint (Identity — devices are noted as "not yet done"), and a
  scheduler for the evening slot; until that lands the client polls. No table/endpoint yet — this
  paragraph is the contract for whoever builds push. Ties into the deferred `profiles.timezone`
  (the "evening slot" and the "already studied today" check are both local-day questions).
