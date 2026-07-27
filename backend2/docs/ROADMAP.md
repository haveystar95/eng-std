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
- [ ] **Generation** (`ai-collection-generation` skill): `generation_requests`, `CollectionGeneratorPort` + Anthropic/OpenAI adapter, async `GenerateCollectionJob` (Horizon), versioned prompts, quotas/cost. Uses Vocabulary `FindOrCreateTerm` + Collections `CreateCustomCollection` (through their Application layers).
- [x] **Identity** (thin, Laravel-native, built 2026-07-27): Sanctum `User` (ULID id) + `Profile`, Google sign-in ported from `../backend` (`google/auth` verifier). Thin module but deptrac-clean: Eloquent/Sanctum work sits in Infrastructure behind Application ports (`GoogleSignIn`, `GoogleTokenVerifier`, `UserReader`, `ProfileUpdater`, `SignOut`); controllers depend only on ports + DTOs. `users` table reworked to ULID + `google_id`/`avatar`; `ulidMorphs` on personal_access_tokens; `profiles` migration in-module. **Devices/settings beyond profile not yet done.**

## Phase 3 — HTTP surface (endpoints)  ⬜
Per `api-endpoint` + `mobile-sync-contract`. Each module's `Presentation/Http` (controller → Command/Query → Resource, FormRequest, Policy, `routes.php` under `/api/v1`).
- [x] Auth endpoints (Identity, built 2026-07-27): `POST /api/v1/auth/google`, `GET /api/v1/auth/me`, `POST /api/v1/auth/logout`, `PUT /api/v1/profile`. First live HTTP surface in backend2. RFC7807-lite: `InvalidGoogleToken` → 422 mapped in `bootstrap/app.php`. 9 feature tests (fake Google verifier). **Still to do for this bullet: author `openapi/openapi.yaml` for these routes.**
- [ ] Collections: list/create/show/update/delete, items add/remove/reorder, fork/subscribe.
- [ ] Terms: lookup/search.
- [ ] Study: `POST /study/sessions`, `POST /reviews/batch` (idempotent), stats endpoints.
- [ ] Generation: `POST /generations` (202), `GET /generations/{id}`.
- [ ] `GET /sync?since=` delta sync.
- [ ] Author `openapi/openapi.yaml` (source of truth) alongside the routes.

## Phase 4 — mobile cutover  ⬜
- [ ] Generate the Dart client from `openapi/openapi.yaml`.
- [ ] Point the Flutter app (`../mobile`) at backend2; migrate/replace the hand-written `api_client.dart`.
- [ ] Verify on device; retire the old `../backend`.

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
