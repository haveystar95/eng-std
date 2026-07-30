# WordTrainer — Backend Architecture

Vocabulary trainer: Flutter/iOS client + Laravel API.
Paradigm: **modular monolith + pragmatic DDD + CQRS-lite**.

---

## 1. Stack

| Layer | Choice | Why |
|---|---|---|
| Runtime | PHP 8.4, Laravel 12 | your primary stack |
| DB | PostgreSQL 17 | jsonb, partial indexes, `pgvector` for term dedup/semantic search |
| Cache/queue | Redis + Horizon | AI generation is async by nature |
| Auth | Sanctum personal access tokens | mobile-friendly, no OAuth server needed |
| AI | Anthropic API via a port/adapter | swappable provider, structured JSON output |
| Contract | OpenAPI 3.1 (source of truth) | generates the Dart client for Flutter |
| Boundaries | Deptrac + Pest architecture tests | rules enforced by CI, not by discipline |

---

## 2. Modules (bounded contexts)

```
app/Modules/
├── Shared/        Kernel: ValueObjects, DomainEvent, Clock, Result, IDs
├── Identity/      users, tokens, devices, settings
├── Vocabulary/    Term (word | phrase), translations, examples, audio
├── Collections/   collections (system / shared / custom), items, subscriptions
├── Learning/      progress, scheduling (SRS), study sessions, reviews, stats
└── Generation/    AI collection generation: requests, prompts, quotas, cost
```

Each module has the same four layers:

```
Modules/<Context>/
├── Domain/          entities, VOs, domain services, repository interfaces, events
├── Application/     commands, queries, handlers, ports (interfaces), DTOs
├── Infrastructure/  Eloquent models, repository impls, mappers, adapters, migrations
└── Presentation/    Http controllers, FormRequests, API Resources, routes
```

**Dependency rule (enforced by Deptrac):**
Domain → nothing. Application → Domain. Infrastructure/Presentation → Application + Domain.
Cross-module: **only** through the other module's `Application` layer (a Query/Command) or via domain events. Never touch another module's Eloquent model or table directly.

**Pragmatism, deliberately:** full DDD everywhere is overkill for this app.
- Rich domain models (pure PHP + mapper): `Learning`, `Vocabulary`, `Collections` — that's where the rules live.
- Laravel-native (Eloquent + Actions): `Identity` — Sanctum's `User` stays an Eloquent model, no ceremony.
- The rule is: **logic-heavy context → rich model; CRUD context → thin.** Documented per module in its own README.

---

## 3. Data model — the decisions that matter

### 3.1 One term, many collections

```
terms(id, lang, text, normalized_text, type[word|phrase], pos, ipa, audio_url,
      source[curated|ai|user], created_by, created_at)
      UNIQUE(lang, normalized_text, pos)

term_translations(term_id, lang, text, is_primary)
term_examples(term_id, sentence, sentence_translation)

collection_items(collection_id, term_id, position, note)
      UNIQUE(collection_id, term_id)
```

`terms` is a **global canonical dictionary**. Collections only reference terms.
"Bank" generated for user A and curated in "Travel" is one row, not two — dedup on
`normalized_text` at write time, plus optional embedding similarity for near-duplicates.

### 3.2 Progress belongs to (user, term) — NOT (user, collection_item)

```
user_term_progress(user_id, term_id, state[new|learning|review|relearning],
                   ease_factor, interval_days, due_at, reps, lapses, last_reviewed_at)
      PRIMARY KEY(user_id, term_id)
```

This is the single most important choice in the schema. If a word sits in three
collections, the user must not learn it three times. Collection progress is a
**derived aggregate** over the progress of its terms, not stored state.

### 3.3 Collections: shared vs custom

```
collections(id, owner_id NULL, type[system|shared|custom], slug, title, description,
            topic, source_lang, target_lang, visibility[private|link|public],
            source[curated|ai|user], generation_request_id NULL, items_count, updated_at)

user_collections(user_id, collection_id, added_at, is_pinned, settings jsonb)
```

- `type=system|shared` + `owner_id IS NULL` → curated, read-only for users.
- `type=custom` + `owner_id` → the user's own, editable.
- Adding a shared collection to "my collections" is a row in `user_collections`, **not a copy**.
- Forking a shared collection to edit it copies rows in `collection_items`, never terms.

### 3.4 Reviews as an append-only event log

```
study_sessions(id, user_id, collection_id NULL, mode, started_at, ended_at, stats jsonb)
reviews(id ULID, user_id, term_id, session_id, grade[again|hard|good|easy],
        latency_ms, answered_at, client_generated BOOLEAN)
```

`reviews.id` is a **client-generated ULID**. Append-only means offline sync has no
conflicts to resolve — a duplicate upload is an ignored insert, not a merge.
`user_term_progress` is a projection folded from this log; stats are read models
(`daily_user_stats`) rebuilt from it, so any stats bug is fixable by a replay.

### 3.5 Generation

```
generation_requests(id, user_id, prompt, normalized_prompt, source_lang, target_lang,
                    status[pending|running|succeeded|failed], model, prompt_version,
                    tokens_in, tokens_out, cost_usd, collection_id NULL, error, created_at)
```

---

## 4. Request flow (example: "иду в банк")

```
POST /api/v1/generations {prompt: "иду в банк", target_lang: "en"}
  → RequestCollectionGeneration (command)
  → generation_requests: pending, returns 202 + id
  → queue: GenerateCollectionJob
      → CollectionGeneratorPort->generate(prompt, langs)   [Anthropic adapter, JSON schema]
      → validate + normalize terms
      → Vocabulary: FindOrCreateTerms (dedup on normalized_text, then embeddings)
      → Collections: CreateCustomCollection(owner=user, source=ai)
      → event CollectionGenerated → push notification / client polls GET /generations/{id}
```

Everything expensive is async, the mobile client never waits on an LLM inside a request.

---

## 5. API principles

- `/api/v1`, Sanctum bearer tokens, cursor pagination, snake_case JSON.
- OpenAPI spec is the contract; the Dart client is generated from it, never hand-written.
- **Idempotency:** clients send ULIDs they generated themselves; `POST /reviews/batch` is safe to retry.
- **Delta sync:** `GET /sync?since=<iso8601>` returns changed collections/terms/progress + a new cursor.
- Errors: RFC 7807 problem+json, stable machine-readable `code`.

---

## 6. Why this and not the alternatives

- **Not microservices:** one developer, one deploy. Modules give you the seams; you can extract `Generation` later because it already talks through a port.
- **Not plain Laravel MVC:** SRS scheduling, dedup and progress aggregation are real rules that deserve to live outside controllers and be unit-tested without a DB.
- **Not full DDD/Event Sourcing everywhere:** the review log already gives you the replay benefit where it matters, without the cost elsewhere.

---

## 7. Skills

The `skills/` folder encodes these rules for Claude Code so every generated piece of
code lands in the same paradigm. Read `skills/laravel-modular-ddd/SKILL.md` first —
it is the umbrella; the others are specializations.

---

## Appendix A — initial endpoint sketch

Design-time sketch only. Once `openapi/openapi.yaml` and the route files exist, they are
the source of truth and this appendix is frozen history.

```
GET    /me                                  profile + settings + quota
GET    /collections?scope=mine|shared|public&topic=&cursor=
POST   /collections                         create custom
GET    /collections/{id}                    details + items + per-term progress
PATCH  /collections/{id}
DELETE /collections/{id}
POST   /collections/{id}/fork               copy a shared set into mine
POST   /collections/{id}/subscribe          add shared set to my list (no copy)
POST   /collections/{id}/items              add term  (term_id or {text,...})
DELETE /collections/{id}/items/{termId}
PATCH  /collections/{id}/items/order

GET    /terms/{id}
POST   /terms/lookup                        find-or-create by text (dedup!)
GET    /terms/search?q=

POST   /study/sessions                      start session -> due terms payload
POST   /study/sessions/{id}/finish
POST   /reviews/batch                       idempotent, offline-friendly
GET    /stats/overview                      streak, totals, retention
GET    /stats/daily?from=&to=
GET    /collections/{id}/progress

POST   /generations                         202 + request id
GET    /generations/{id}                    status + resulting collection_id
GET    /generations?cursor=                 history + quota

GET    /sync?since=                         delta for offline cache
```

---

## Appendix B — initial schema design

Design record. The migrations are authoritative once they exist.

```sql
-- Vocabulary
terms(
  id ULID PK, lang CHAR(5), text TEXT, normalized_text TEXT,
  type TEXT CHECK (type IN ('word','phrase')), pos TEXT NULL,
  ipa TEXT NULL, audio_url TEXT NULL, frequency_rank INT NULL,
  source TEXT CHECK (source IN ('curated','ai','user')), created_by ULID NULL,
  embedding vector(1536) NULL, created_at, updated_at)
UNIQUE (lang, normalized_text, COALESCE(pos,''))

term_translations(id, term_id FK, lang, text, is_primary BOOL, created_at)
  UNIQUE (term_id, lang, text)
term_examples(id, term_id FK, sentence, sentence_translation, source)

-- Collections
collections(
  id ULID PK, owner_id ULID NULL, type TEXT CHECK (type IN ('system','shared','custom')),
  slug TEXT NULL, title TEXT, description TEXT NULL, topic TEXT NULL,
  source_lang CHAR(5), target_lang CHAR(5),
  visibility TEXT CHECK (visibility IN ('private','link','public')),
  source TEXT CHECK (source IN ('curated','ai','user')),
  generation_request_id ULID NULL, items_count INT DEFAULT 0,
  created_at, updated_at, deleted_at)

collection_items(id, collection_id FK, term_id FK, position INT, note TEXT NULL, created_at)
  UNIQUE (collection_id, term_id)

user_collections(user_id, collection_id, added_at, is_pinned BOOL, settings JSONB)
  PRIMARY KEY (user_id, collection_id)

-- Learning
user_term_progress(
  user_id, term_id,
  state TEXT CHECK (state IN ('new','learning','review','relearning')),
  ease_factor NUMERIC(4,2) DEFAULT 2.50, interval_days INT DEFAULT 0,
  due_at TIMESTAMPTZ NULL, reps INT DEFAULT 0, lapses INT DEFAULT 0,
  last_reviewed_at TIMESTAMPTZ NULL, updated_at)
  PRIMARY KEY (user_id, term_id)

study_sessions(id ULID PK, user_id, collection_id NULL, mode, started_at, ended_at, stats JSONB)

reviews(  -- append-only
  id ULID PK,            -- generated by the CLIENT for idempotency
  user_id, term_id, session_id NULL,
  grade TEXT CHECK (grade IN ('again','hard','good','easy')),
  latency_ms INT NULL, answered_at TIMESTAMPTZ, created_at)

daily_user_stats(  -- read model, rebuildable from reviews
  user_id, date, reviews_count, new_terms_count, correct_count,
  study_seconds, PRIMARY KEY (user_id, date))

-- Generation
generation_requests(
  id ULID PK, user_id, prompt TEXT, normalized_prompt TEXT,
  source_lang, target_lang, status TEXT, model TEXT, prompt_version TEXT,
  tokens_in INT, tokens_out INT, cost_usd NUMERIC(10,6),
  collection_id ULID NULL, error TEXT NULL, created_at, finished_at)
```

## Indexes that must exist

```sql
CREATE INDEX ON user_term_progress (user_id, due_at) WHERE state <> 'new';  -- the hot query
CREATE INDEX ON collection_items (collection_id, position);
CREATE INDEX ON collection_items (term_id);          -- "which collections contain this term"
CREATE INDEX ON reviews (user_id, answered_at DESC);
CREATE INDEX ON collections (owner_id) WHERE deleted_at IS NULL;
CREATE INDEX ON collections (type, visibility) WHERE deleted_at IS NULL;
CREATE INDEX ON terms USING hnsw (embedding vector_cosine_ops);   -- near-duplicate detection
```

The due-cards query (`user_id`, `due_at <= now()`, ordered, limited) runs on every
session start on mobile. Any change near `user_term_progress` needs an `EXPLAIN` check.

---

## Appendix C — Addendum: decisions that evolved

The sections above are the frozen initial design. Where a decision has since changed, the old
text stays and the change is recorded here (the migrations and `.claude/skills/` are authoritative
for current behaviour).

- **Progress states** — `> Superseded 2026-07-30 (§3.2)`: a fifth state `known` was added (the
  triage "I know this" shortcut). It is not an SRS state — the scheduler refuses it; its `due_at`
  is a verification check. New `term_triages` table (append-only, client ULID) is the source of
  triage verdicts.
- **Reviews & grading** — `> Superseded 2026-07-30 (§3.4, §5)`: the client uploads the RAW answer
  (`exercise_mode`, `response`, `latency_ms`, `used_hint`, `is_practice`); the **server grades**
  it (`AnswerGrader` + per-mode latency median), so the grading rule lives in one runtime.
  `reviews` gained `exercise_mode`, `is_correct`, `is_practice`, `response`.
- **Study endpoint** — `> Superseded 2026-07-30 (§5, Appendix A)`: `POST /study/sessions` returns
  a self-contained package (mode per card, distractors, chips) and fixes the session composition;
  the earlier content-only `GET /study/due` was deleted (no shipped clients — clean break).
- **Daily quota** — `> Superseded 2026-07-30`: the new-term quota is read from the profile's
  `daily_goal` (clamped `[0, 100]`), one global cap per user; a scoped session does not multiply it.
- **AI provider** — `> Superseded 2026-07-27 (§1)`: implemented against **OpenAI** (structured
  outputs), not Anthropic, behind the same port; swappable.
- **"Mastered"** now `(review AND interval ≥ 21) OR known`, defined once (`Mastery::isMastered`).
- **Pre-release**: breaking API changes are free with no deprecation cycle until App Store launch.
