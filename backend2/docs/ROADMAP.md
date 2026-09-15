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

## Phase 2 — remaining core domain  ✅ done
- [x] **Learning** (`learning-srs` skill): `TermProgress` aggregate keyed `(user_id, term_id)`, state machine new→learning→review↔relearning; pure `Sm2Scheduler` behind a `Scheduler` port (+ injectable `Fuzz`); `SubmitReviews` (append-only `reviews`, client ULID + `insertIgnore`, folds in `answered_at` order), `StartStudySession`, `GetDueTerms` query (due-before-new, quota, session cap); `daily_user_stats` via `StatsProjector`. Migration for all four tables (checks/FKs/partial due index). Cross-module: `TransactionManager` (Shared) + `TermExistenceReader` (Vocabulary Application) for unknown-term rejection. 30 unit tests. `arch/stan/test` green. **Presentation (HTTP) deferred to Phase 3.** Note: collection-scoped due + user-tz stats stubbed until Collections query / Identity land.
- [x] **Generation** (`ai-collection-generation` skill, built 2026-07-27): `GenerationRequest` aggregate (pending→running→succeeded|failed) + `generation_requests` table; `CollectionGeneratorPort` with `OpenAiCollectionGenerator` (structured outputs, versioned prompt file `generate_collection.v1.md`) + `FakeCollectionGenerator` (`GENERATION_DRIVER=fake`); async `GenerateCollectionJob` (3 tries, `failed()`→FailGeneration), sync path for console. `DraftValidator` (size/cefr/dedup), daily quota (non-failed requests, auto-refund on failure), token/cost tracking. Cross-module via **primitive** boundaries: Vocabulary `ImportTerm` (new shim; Generation must not touch Vocabulary Domain VOs) + Collections `CreateGeneratedCollection` (new; source=ai) + `AddTermToCollection`, all through Application. **Deferred (noted):** embedding/semantic dedup, prompt cache, language detection, push.
- [x] **Identity** (thin, Laravel-native, built 2026-07-27): Sanctum `User` (ULID id) + `Profile`, Google sign-in ported from `../backend` (`google/auth` verifier). Thin module but deptrac-clean: Eloquent/Sanctum work sits in Infrastructure behind Application ports (`GoogleSignIn`, `GoogleTokenVerifier`, `UserReader`, `ProfileUpdater`, `SignOut`); controllers depend only on ports + DTOs. `users` table reworked to ULID + `google_id`/`avatar`; `ulidMorphs` on personal_access_tokens; `profiles` migration in-module. **Devices/settings beyond profile not yet done.**

## Phase 3 — HTTP surface (endpoints)  ✅ done
Per `api-endpoint` + `mobile-sync-contract`. Each module's `Presentation/Http` (controller → Command/Query → Resource, FormRequest, Policy, `routes.php` under `/api/v1`).
Every bullet below is closed; the one leftover is **collection fork**, which is a B5 follow-up
further down this file and not a Phase-3 hole. Bullets were written when they landed and describe
the surface of that day — where a later decision moved it (`/study/due` removed, search shipped),
that is said in the bullet rather than by rewriting history.
- [x] Auth endpoints (Identity, built 2026-07-27): `POST /api/v1/auth/google`, `GET /api/v1/auth/me`, `POST /api/v1/auth/logout`, `PUT /api/v1/profile`. First live HTTP surface in backend2. RFC7807-lite: `InvalidGoogleToken` → 422 mapped in `bootstrap/app.php`. 9 feature tests (fake Google verifier). Documented in `openapi/openapi.yaml`.
- [x] Collections (built 2026-07-27): **CRUD done** — `GET /collections` (cursor-paginated summaries), `POST /collections` (client-id idempotent), `GET/PATCH/DELETE /collections/{id}` (owner-only; soft-delete tombstone). Query side added (`ListUserCollections`+reader, `GetCollection`); commands `UpdateCollection`/`DeleteCollection` + repo `delete`. **Store subscribe done (B5, below).** Items done too — `POST /collections/{id}/items`,
  `DELETE /collections/{id}/items/{termId}`, `POST /collections/{id}/items/{termId}/move` — plus
  `GET /collections/default` and term-content hydration on show. Fork stays a B5 follow-up (below).
- [x] Terms: lookup/search — `GET /search`, `GET /search/instant`, `GET /search/languages`
  (Generation module, RS-1 `7c22365`). The pair is set by the user; auto-detect was removed.
- [x] Study (built 2026-07-27): `POST /api/v1/reviews/batch` (idempotent, `{accepted,duplicates,unknown}`), `GET /api/v1/stats` (total/learned/mastered/due-today/reviews-today/streak). Progress is created on first review, so a studied term becomes due later. `POST /study/sessions` + `/complete` landed after this bullet was written and are now THE study surface; `GET /api/v1/study/due` (built here, content-only) was removed on 2026-07-30 in its favour. Also live: `GET /study/progress`, `/triage/*`, `/pool/terms/{termId}`.
- [x] Term-content hydration: Vocabulary `TermContentReader` powers collection detail items + study cards (self-contained session payload). (The lookup/search endpoint this bullet called TODO shipped later — see the Terms bullet above.)
- [x] Generation (built 2026-07-27): `POST /api/v1/generations` (202 + pending, client polls), `GET /api/v1/generations/{id}` (owner-only → 404 otherwise). Quota → 429 (mapped in `bootstrap/app.php`). Plus an artisan `generation:make {user} {prompt}` command (runs synchronously, same Application handlers). 19 tests (domain state machine, DraftValidator, cross-module ProcessGeneration, feature). Documented in `openapi/openapi.yaml`.
- [x] `GET /sync?since=` delta sync — plus `GET /sync/cursor`; the cursor is the server's
  `server_time` and lives in the client's local DB (`SyncController`).
- [x] `openapi/openapi.yaml` (source of truth): documents every live route — auth (incl. `/auth/dev`), profile, generations, practice dialogs, store, search, collections + items, study sessions, `/study/progress`, reviews, stats, triage, pool, sync. Keep extending as new endpoints land. **Also established:** RFC 7807 `application/problem+json` errors via a polymorphic `Shared\Domain\Exception\ProblemDetails` interface + one renderer in `bootstrap/app.php` (domain exceptions carry a stable `code`); input validation keeps Laravel's 422 `{message, errors}`.

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

## Device-batch run1 — CLOSED (2026-08-08)
Полный приёмочный прогон на iPhone с чистого аккаунта: блоки 0–9 + день-2 SRS-петля + удаление
аккаунта. Полный лог: `docs/device-batch-run1.md`. Аккаунт прогона стёрт.

**Fixed in run (13, все с зелёными гейтами, закоммичено на `feat/mobile-backend2-cutover`):**
F1 онбординг per-account → серверный `profiles.onboarded_at` (гейт+бэкфилл); F8 CTA-ветка «Учить N»
(due→learn→triage→practice); F9 закреплённая «Дальше»; F10 TTS в mute (`.playback`); F11 итог
сессии (IntrinsicHeight, release-only blowup); F12 «Проверить»→«Дальше» в word_bank; F14
`DraftValidator` CEFR-полоса = предпочтение (генерация не падает при одиночном уровне+базовой теме);
F16 TTS берёт язык коллекции, не профиля; F21 null-ответ «Не помню» не терялся (`response` nullable);
F23 удаление аккаунта чистит practice-диалоги/**транскрипты (PII)**/example_regenerations.

**Follow-up задачи из прогона (8 чипов) — Training Loop v2 + прочее:**
- [ ] **Free practice дрилит выученные-не-due слова** (F17) — сейчас practice отдаёт только due → пусто, когда ничего не due. Пул studied-терминов игнорируя due_at. → Training Loop v2.
- [ ] **F19 — SRS due по КАЛЕНДАРНОМУ дню**, а не точному таймстампу (`Sm2Scheduler:143` `now->add(P{N}D)`). **Первым фиксом после батча.** → Training Loop v2.
- [ ] **Backfill активности с сервера** (F18) — Reviews-today/за неделю/график читают локальный `daily_activity`, стирается при релогине (Home-goal и streak с сервера ок; итог-сессии и график — нет). Server per-day activity + backfill. (см. также строку про A3.6 activity ниже.)
- [ ] **Robustness очереди reviews** (F21) — клиент дропает весь чанк на 422 (batch отдаёт только агрегат) → server-side skip теряется; flush early-return под `_flushing` не дренирует; `resync` (DOWN) не пушит up-очередь. + найти клиентский `''→null` источник.
- [ ] **Слово дня как фича** — отдельный эндпоинт + курируемая таблица интересных слов по CEFR, исключая слова из коллекций юзера (сейчас: детерминированный выбор из локальных терминов).
- [ ] **Все painted-флаги** для target-языков (сейчас только en/de/es/fr/pt; + фикс English-флага F3).
- [ ] **Параллельная загрузка Pexels** (Guzzle Pool) — `AttachCollectionImagesHandler` тянет последовательно (~19 вызовов на 18 слов) → фото долго доезжают, проигрывают гонку первому /sync. + идея авто-ре-sync после succeeded.
- [ ] **Значки языковой пары** (ru→de/ru→en) на карточке коллекции + **политика смешанных языков** в одной тренировке (per-card `target_language` на бэке ИЛИ скоуп сессии по языку).
- [ ] **Офлайн-кеш картинок** (F22) — `Image.network` без кеша → в авиарежиме пусто; `cached_network_image`.
- [ ] **Релиз: решить дефолты флагов** — `STORE_ENABLED`/`PAYWALL_ENABLED`/`DEV_MENU` в `mobile/lib/data/config.dart` теперь дефолт **true** (dev-билды ставим только мы, плоский `flutter run` не должен ронять стор — так он однажды «пропал»). К релизу: `DEV_MENU` почти наверняка false, стор/пейволл — по объёму релиза. Также `kShowPhotoAttribution` → true (Pexels требует кредит фотографу). `TODO(release)` стоят у флагов.

**Прочие findings (в логе, не в чипах):** F13 («Учить N» пустая при исчерпанной квоте — уже адресовано `877a23f` honest empty state), F15 (клиентская квота генераций залипает после фейла — рефетч `/me`/инвалидация на failed), F20 (микро-подвисания анимаций сессии — нужен профайл).

**Хвост контракта:** стор-preview (`GET /store/collections/{id}/preview`) закоммичен в `49d51d7`, но
`openapi/openapi.yaml` в дереве СМЕШАН (мой `onboarded_at` уехал со стор-коммитом) — **стор-сессии
закоммитить свой openapi-хунк (preview), чтобы дерево контракта стало чистым.**

## Генерация v2 — версионирование, мультимодельность, bake-off (2026-08-20)

Отдельная глава: не улучшение промпта, а **машина**, на которой решение о модели и о схеме
пайплайна принимается по цифрам, а не на слух. Живой контент в этой главе не изменён ни строкой —
мораторий до приёмки; всё сгенерированное лежит в песочнице.

- [x] **Паспорт контента** (`60b6a8b`) — `prompt_version` + `generation_model` на СТРОКЕ
  (`terms`/`term_translations`/`term_examples`; `generation_model` рядом с существующим
  `generator_version` у `term_accepted_variants`/`example_distractors`). Старые строки помечены
  сентинелом `legacy`, DEFAULT у колонки нет: после бэкфилла NULL значит ровно одно — писатель
  создал контент и не проштамповал его. Миграция обратима, бэкап снят до применения.
- [x] **Промпт v10** (`78b4c58`) — не файл, а каталог секций, из которого собираются три формы
  (`terms` / `enrich` / `full`). Причина: правило изоморфности одинаково во всех трёх, три файла =
  три копии правила. Содержимое: v9 + ОБЕ волны изоморфности + обязательный пример + запрет
  одинаковых переводов у двух items + самопроверка «последний item читается как первый».
  **Боевая генерация осталась на v9** — переключение решается по цифрам bake-off.
- [x] **Мультимодельный слой** (`78b4c58`) — `ContentModelPort` + OpenAI / xAI (один адаптер, xAI
  повторяет wire-формат OpenAI) / Anthropic (свой: `x-api-key`, `system` отдельным полем,
  `output_config.format`). Нет ключа → провайдер unavailable, не ошибка. Ретраи только по статусам,
  которые сами меняются (429 — потолок токенов в минуту, 403 — никогда).
- [x] **Bake-off вхолостую** (`24b5f15`) — три трека (А список / Б обогащение / В one-shot),
  песочница `bakeoff_*`, автопроверки поверх существующих детекторов, отчёт-сравнение в один файл.
- [x] **Прогон выполнен**, `docs/bakeoff-v10.md`. Первые цифры (OpenAI gpt-4o, промпт v10):
  трек А 42/48 чистых (88%), трек Б 25/28 (89%), трек В 30/36 (83%); готовая коллекция
  А+Б **$0.1649** против one-shot **$0.0255** — в 6.5 раза дешевле; гипотеза «хвост длинного
  ответа халтурит» НЕ подтвердилась (во второй половине брака меньше: 11% против 22%).
- [x] **Адаптер Gemini** (`f2797c1`) — четвёртый провайдер в том же контракте. Цены всех
  четырёх вендоров сверены по их страницам 2026-08-20; попутно исправлена неверная цена
  Claude Sonnet 5 ($2/$10, а не $3/$15).
- [x] **Трек А на ЧЕТЫРЁХ провайдерах** (`docs/bakeoff-v10-track-a.md`, 2026-08-20): одна новая
  бытовая тема («вызываю сантехника»), промпт v10 форма terms, модели среднего тира —
  gpt-5.4 / claude-sonnet-5 / grok-4.6 / gemini-3.7-flash. **Все четверо: 12/12 чистых,
  автопроверки никого не различили.** Различают цена и латентность: Gemini $0.0087 / 80с,
  xAI $0.0178 / **303с**, OpenAI $0.0260 / 11с, Anthropic $0.0550 / 37с. Выбор — за чтением
  полных списков, они в файле.
- [x] **Аудит потока данных** (`docs/generation-data-flow-audit.md`, 2026-08-20) — что v9 отдаёт,
  что пишется, что обогащение дополняет и что перезаписывает, откуда берутся термины без примера,
  кто читает какие поля. 8 аномалий, ни одна не чинилась (боевой enrich по наряду не трогаем).
  Главные: `replace()` каскадом сносит дистракторы; обогащение бежит ДО починки примера, поэтому
  эхо-термин остаётся без дистракторов навсегда; AI-примеры помечаются `source='user'` без штампа.
- [x] **Промпт v11** (`14f4d8f`) — запрет переводов-определений, разделение форм
  (ядро / механика / ремонт / one-shot), сжатие. Проверки `definition` и `forms`.
- [x] **A/B трёх конфигураций** (`docs/bakeoff-v11-ab.md`, `b02254d`): **механика поверх готового
  ядра — $0.0006 против $0.128 у полного обогащения, в 200 раз дешевле** при равном или лучшем
  качестве. Готовая коллекция: К2 $0.024 против К1 $0.151 и one-shot $0.027. 77.7% входных
  токенов вендор отдал из кеша — цены в отчёте это верхняя граница.
- [x] **Решение по модели и по схеме пайплайна** — К2: ядро gpt-5.4, механика gpt-4o-mini.
- [x] **Пересадка боевой генерации на новый стек** (`854384e`..`08e8523`, 2026-08-20):
  - **порча данных из аудита починена ДО пересадки**: замена примера обновляет ряд, а не
    пересоздаёт его (A1 — дистракторы больше не гибнут каскадом; те, что новому предложению не
    соответствуют, снимаются по тому же правилу, что и `enrich:audit-distractors`), починка
    примеров идёт ПЕРЕД обогащением одной цепочкой `Bus::chain` (A2), оба AI-писателя штампуют
    промпт, модель и честный `source` (A3, A4);
  - **генерация коллекций** — форма v11 `terms` через `ContentModelPort`; откат
    `GENERATION_STACK=v1` возвращает прежний адаптер (v9/gpt-4o) без деплоя;
  - **станок** — форма `machinery` промпта **v12** (v11 `mechanics` отдаёт неверные ПЕРЕВОДЫ, под
    которые здесь нет таблицы, и не отдаёт дистракторов примера ни в одной форме), версия
    `mech-v12`; `back_translation`/`language_notes` из пер-терминного прохода убраны (A5) и живут
    в команде `audit:translations`;
  - **`generation:regenerate-showcase`** — создана, НЕ запускалась. Сухой прогон на 483 терминах:
    **$8.81 верхней границей** (ядро $8.54 + механика $0.27), через Batch API было бы $4.40.
    Batch не реализован — асинхронный протокол, который нечем проверить, не тратя настоящих денег.
  - **гигиена (A6)**: снесены `audio_url`/`frequency_rank`/`embedding` и HNSW-индекс на пустом
    embedding; `pos` оставлен — он компонент ключа дедупа, а не мёртвый груз.
- [ ] **Прогон перегенерации витрины** — решение заказчика после снятия моратория: бэкап,
  `--limit` на первую пачку, чтение результата, потом ширина.

**Найдено попутно (в работу НЕ бралось, мораторий):**
- 9 терминов витрины имеют ДВА `is_primary` перевода на один термин («stay calm» → «Оставайтесь
  спокойны» И «оставаться спокойным»). Вопрос карточки тогда — тот перевод, который вернул запрос.
- Ключ xAI есть, но у команды нет кредитов: 403 на каждом вызове. Anthropic-ключа нет вовсе.
- Организационный лимит OpenAI 30000 токенов/мин при промпте ~4.5k токенов = ~6 вызовов в минуту;
  прогон без пауз теряет треть вызовов в 429. Отсюда `--pace`.

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
- **Sign in with Apple is wired on the client but blocked on two externals (A3.7).** The login
  screen renders the official `SignInWithAppleButton` and `AuthRepository.signInWithApple()` obtains
  the Apple credential and POSTs it to `/auth/apple` (`ApiClient.appleLogin`). Neither prerequisite
  exists yet: (1) the backend `/auth/apple` exchange (Identity module — only `/auth/google` is built),
  and (2) the "Sign in with Apple" capability, which the current **free** personal Apple team can't
  enable (needs a paid membership). Until both land the button surfaces a clear message; Google works.
- **Reminders are a stored preference only, not scheduled (A3.7).** The profile toggle + time
  (кадр 13a/13b) persist to local settings (`AppSettings`, drift `sync_meta`); actually firing an OS
  notification (the 2.12 pre-permission flow 13c/13d + a local-notifications plugin + the iOS
  permission) is deferred. Same for «Автопроизношение» — stored now, consumed by the A3.8 session.
- **Progress-screen activity accrues locally from now, no backfill (A3.6).** The activity chart, week
  calendar and «за неделю»/«сегодня» read a client-only `daily_activity` table bumped once per review
  in `ReviewSync.record` (session + practice, not triage — so it converges with the streak dots). It
  starts empty on any install and has no history: **backfill activity from the server reviews log — if
  needed** (there's no history endpoint today; an alternative is a `daily_user_stats` history read).
  «Лучший результат» is likewise a local running max of the observed streak, not a server field.
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
- [x] **B1 — enrich bare user terms** (this session): "Add word" now takes an **optional** translation
  (`AddWordRequest`/`AddWordToCollection`); when omitted the term is created bare and an async
  `EnrichTermJob` fills in translation + IPA + example (versioned prompt `enrich_term.v1.md`, OpenAI
  structured outputs, `gpt-4o-mini`) then a Pexels photo via the existing image path. Only
  `source=user` terms with no translation are enriched — that eligibility check (Vocabulary
  `EnrichableTermReader`) is the idempotency gate, so a job retry never re-spends on the model. Spend
  is written to a new `term_enrichments` table (`ModelCost` extracted from `GenerationPipeline`, one
  pricing table now). Cross-module trigger is clean: Collections' add-word handler calls a new
  **Vocabulary** port `DispatchesTermEnrichment` (Collections→Vocabulary is legal), which Generation
  fulfils with the queue job (deptrac edge `GenerationInfrastructure → VocabularyApplication`). Fake
  enricher + fake image drivers for tests; feature + unit tests (enrich e2e, not-enriched-with-
  translation, idempotent re-run, ModelCost).
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
- [x] **B4 — default target language** (this session): scope corrected — `profiles.target_language`
  already IS the default learning language (nullable, default `en`, already in `/auth/me` + `PATCH
  /profile`), so **no new column** (a `default_target_lang` beside it would be a shadow field with
  two sources of truth). Generation now falls back to it: `POST /generations` without `target_lang`
  → the handler resolves `command.targetLang ?? profiles.target_language ?? en` via a new Identity
  `DefaultTargetLangReader` port (Generation Application already reaches Identity Application from B5).
  OpenAPI documents the fallback; feature test covers default-from-profile, explicit-override, and
  no-profile→en.
- [x] **B6 — regenerate example** (this session): `POST /terms/{id}/regenerate-example` (the "New
  example" button) — the LLM writes a fresh example (avoids the current one, versioned prompt
  `regenerate_example.v1.md`, `gpt-4o-mini`) + its translation, which **replace** the term's stored
  example, and returns them so the client updates in place. Replacement is a focused Vocabulary write
  (`ReplaceTermExample` → `TermExampleWriter`, delete+insert on `term_examples`) — examples aren't
  hydrated into the Term aggregate, so an aggregate mutation can't express a replace. **Counts as a
  generation in the daily quota:** a new `example_regenerations` table (per-user), and
  `EloquentGenerationQuota::usedOn` now sums `generation_requests` + `example_regenerations` for the
  day; spend recorded there. Quota gate → 429 when exhausted (tested). **Note (single-user):**
  `term_examples` are global, so this replaces the shared example — fine here; a multi-user build
  would need per-user example overrides. Fake regenerator for tests; feature test (replace + spend,
  429-when-exhausted, 404-unknown, auth).
- [x] **B2 — client-ULID idempotency on POST /generations** (this session): the endpoint now accepts
  an optional client-generated `id` (ULID). Re-sending the same id returns the **existing** request
  with **200** (no new row, no second job dispatched, no quota spent) instead of **202** — the backend
  half of the client's durable offline generation queue (client side = session A). The handler
  short-circuits on a found id before the quota check; a different user re-using an id → **409**
  `generation_id_conflict` (never resolve or leak another user's request). Handler now returns a
  `GenerationRequestOutcome{id, created}` so the controller picks 200 vs 202 and dispatches only when
  created. OpenAPI documents id + 200/409; feature tests (202-then-200 idempotent, no-dup, quota not
  double-spent, cross-user 409 at the handler, server-generates-when-absent).
- [ ] **Global-term dedup can't hold context-specific translations** (design finding, 2026-08-07;
  surfaced while publishing starter store content). Terms are globally deduplicated on
  `(lang, normalized_text, pos)`, and translations hang off the **term**, not the (collection, term)
  pair. So one surface form with two senses in two collections shares a single translation set: the
  banking sense of "set up" («подключить/оформить услугу») and the general phrasal-verb sense
  («настроить/организовать») collide on the same row — editing one changes the other. Worked around
  once by minting a distinct, less-ambiguous term ("set up an account") for the store deck rather than
  overwriting the shared "set up". The real fix is a **collection-level translation override** (a
  per-`(collection_item)` translation that shadows the term's default) — **backlog candidate, not
  being built now.** Until then, curators must pick disambiguated surface forms when a word's sense is
  collection-specific. (Progress stays keyed on `(user, term)` regardless — this is only about display
  text, never scheduling.)
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
  **Locale (added A3.0, 2026-08-06):** push copy is user-facing text, so the notification builder
  must render in the user's UI language. The client now has a locale override (device-local, in
  drift; §4з copy lives in the app's ARB), but the backend has **no** locale for a user yet — when
  APNs lands, add a `profiles.locale` (or device-level locale on the token register) and localise the
  §4з notification strings server-side. Spec only; not implemented.

## Realtime-практики (фаза 2 — не в работу)

Голосовой разговор с ИИ по коллекции работает: OpenAI Realtime (WebRTC) как основной транспорт и
второй транспорт **Gemini Live API (WebSocket)** — выбор по `provider` из `POST /practice/dialogs`.
Идеи развития зафиксированы, но НЕ в работе:

- **Вариативность диалогов** — подмешивать в урок summary прошлого диалога (`GET …/last-dialog` уже
  есть): не повторять сценарий, смещать фокус на **непокрытые** (не прозвучавшие) целевые слова.
- **Промпт-роль «учитель»** — подталкивать к нужной лексике, сужать контекст вопросов, перепроверять
  употребление целевых слов (усилить `systemInstruction`/`session_setup`).
- **Стриминг текста реплики агента** — показывать растущий текст в одном пузыре. Сейчас реплика
  агента эмитится и уходит в `/transcripts` **по `turnComplete`** (фрагменты `outputTranscription`
  агрегируются в одну строку с восстановлением пробелов, чтобы не ломать серверный coverage фраз).
- **Тариф / размещение фичи** — решение Дена (пейволл-точка «Разговоры», дневной лимит).
- **Gemini как дефолт при масштабировании** — примерно ~2× дешевле OpenAI Realtime; пересмотреть,
  когда Gemini Live выйдет из preview / появится кеширование.
- **Gemini `liveConnectConstraints`** — сейчас живой `auth_tokens` отвергает это поле (доки впереди
  деплоя), поэтому урок уходит клиенту в `session_setup` (bare-токен). Когда Google выкатит поле —
  включить `PRACTICE_GEMINI_CONSTRAINED=true`: урок запечётся в токен, `session_setup` станет не нужен.

## Хвосты наряда A-4.1 (2026-08-25) — найдено, в работу НЕ бралось

Три бага с телефона закрыты нарядом (см. `session-handoff.md`); ниже то, что всплыло попутно и
осталось нерешённым. Каждый пункт — самостоятельная задача, не «доделка» A-4.1.

- [ ] **Транскрипты диктовки попадают в общий кэш переводов.** В `instant_translations` лежали две
  надиктованные ситуации для генерации коллекции (89 и 116 символов) — под `en:ru`, рядом с
  однословными подсказками. Кэш общий, вечный и оплачен посимвольно; надиктованная фраза не перевод
  слова и обслуживать подсказку под полем поиска не может. Сами строки удалены 25.08, **путь, которым
  они туда попали, открыт**. Решить: у диктовки свой кэш, либо она не кешируется вовсе.
  **Кандидат в `DECISIONS.md`** — правило, а не фикс.
- [ ] **Эхо на карточке знакомства для `pl`/`ro` предложит кнопку, которая офлайн не слушает.**
  Сервер знает (`LanguageModeSupport::isOnlineOnly`, п. 48: on-device распознавания у iOS для этих
  языков нет), интро на это не смотрит — по решению владельца от 25.08 условие «язык распознаваем» на
  интро не проверяется. Вопрос открыт отдельно: либо интро читает `online_only`, либо эхо честно
  говорит «нужна сеть».
- [ ] **Справочная коллекция в карусели «Мои коллекции» показывает полосу прогресса**, как обычная,
  тогда как на полке у таких вместо прогресса стоит счёт слов. Отложено владельцем в следующий
  mobile-наряд (25.08); прогресс-UI наряд A-4.1 трогать запрещал.
- [ ] **Подсказки под полем поиска — только английские** (carried). `assets/wordlist/en_frequency.txt`,
  46 693 слова, один список на все пары: в паре `ru → en` набор кириллицей не даёт подсказок вообще,
  в паре с испанским предлагаются английские слова. Правится частотными списками на язык, не кодом.
- [ ] **`terms.pos` пуст** (carried) — нужны данные, а не решение.

## Learning Plan — PLAN-GEN (2026-09-10): два промта, проверки, сборка дня, снос старого

Канон — `docs/plan-v2.md`, контракт — `docs/plan-api.md` + `openapi/openapi.yaml` (тег `Plans`),
модуль — `app/Modules/Plan` (`README.md`). Старая цепочка P1 → P2 → судья → починка → нарезка дня,
лестница A/B/C, умения, чек-пойнты, готовность, спасатели как сущность, `learning_plan_*`,
`plan_skills`, `term_audios`, плановые колонки `terms.*`, `learning_mode_settings.scope` — снесены
целиком (миграции `2026_09_10_100000`, `2026_09_10_100100`, `2026_09_10_100200`; коммит ниже).
История серии PLAN-1a…DAY-GATE-1 и SPEECH-2 в части плана — в git до `7bb1c784` и в `docs/research/`.

- [x] **Модуль `Plan`** (новый; решение сессии, вопрос архитектору в отчёте): `plans`, `plan_scenes`,
  `plan_days`, `day_cards`, `plan_terms`, `plan_line_audios`, `plan_check_counters`; агрегат `Plan`
  (статусы, календарь `PlanCalendar` 1…10, открытие по одному дню в календарный день зоны
  пользователя, укорачивание «варианты → старший приоритет, ядро никогда», расширение с
  `EXISTING_SCENES`); `DayCard` (первый провал — в конец этапа, второй — возврат завтра, skip без
  последствий); `PlanTerm::fromLesson`.
- [x] **Два промта** `plan-builder-v2` / урок первой формы (снят нарядом GEN-2a) — файлы заморожены, версия = имя файла,
  `TEST INPUT` вырезается загрузчиком; strict JSON Schema (`PlanSchemas`); один ретрай только по
  схеме или `gate`; цена/задержка/попытки/версия промпта/**версия сборки** на строке плана и
  сцены; `GET /plans/versions`.
- [x] **Проверки §5** — 12 на урок (сняты нарядом GEN-2a вместе с уроком, их место занял валидатор
  урока в режиме наблюдения), 5 на план, режимы `observe` / `drop` / `gate` по имени в `config/plan.php`
  (все `observe`); счётчики `plan_check_counters` → `GET /admin/api/plans/checks`.
- [x] **Сборка дня** детерминирована (`DayAssembler`): 5 этапов, состав по уровню, «услышал →
  собери» только Intermediate и только утверждение ≤ 10 слов, возвраты, повторение, репетиция,
  метрики; терпима к любому сломанному уроку.
- [x] **API** `/plans*` (см. `docs/plan-api.md`), все склоняемые строки — с сервера.
- [x] **Тесты канона** — календарь 1…10, укорачивание, каждая проверка на своём сломанном JSON в
  трёх режимах, терпимость сборки, состав/порядок по уровням, HTTP-прогон дня с двумя ошибками и
  пропуском, 3-дневный план до репетиции.
- [x] **Прод мигрирован (доработка 10.09):** владелец подтвердил, что аккаунты со старыми планами
  тестовые; бэкап `wordtrainer-20260910-233221.sql.gz`, четыре миграции применены, коллекции и
  словарь сверены до/после (отчёт §5а). DECISIONS пп. 304–308, штамп сборки `scripts/stamp-build.sh`.
- [x] **Живьём — расширение плана (+2 сцены, $0.019 / 8.9 с) и озвучка A одного урока ($0.0066 /
  33 с, 8 mp3).** Нашли и починили: `appendScenes` теперь нумерует добавленные сцены сам (модель
  отвечает `order` с 1), повтор `step` считается проверкой формы урока и не покупается дважды, лог
  входящих запросов переживает бинарный ответ `GET /plans/audio/{id}`.
- [ ] **Открыто:** мобильный клиент пишется под новый контракт отдельным нарядом (старые экраны
  плана работают против удалённых маршрутов).
- [ ] **Хвосты:** `NativeDistractorSource` добирает переводы по длине и CEFR без частотности
  (колонки нет); `char_limits` только считается — клиент обрезает; озвучка реплик A включена в
  `.env` (`SPEECH_ENABLED=true`, прежний вендор голоса) — цена на день не замерялась отдельно.

## Окно дня — DAY-UI-2 (2026-09-14): кадры 23-0a…0d, контур окна в GET дня

Отчёт — `docs/research/day-ui-2/README.md`; контракт — `docs/plan-api.md` «Окно дня».

- [x] **`window` в `GET …/days/{n}`** — статус словами, ряды этапов с цифрой только у текущего,
  минуты по темпу этапа (`DayPace`), состояния единиц и счётчики бровей, одно действие
  (`start`/`continue`/`again`); мёртвые поля старого кабинета и `GET …/sheet` сняты, колонки
  «с первого раза» / «самое трудное» удалены.
- [x] **Фото у каждого слова** — лестница запросов, тон слота вместо «битой» картинки, `image_missing`,
  `plan:images-backfill` (было пусто 45 → 0).
- [x] **Голос сервера — только реплики роли** (канон владельца при закрытии): `RoleLineQueue`,
  `plan_line_audios.line_ref`, `SpeakSceneLinesJob` повторяется до получаса, `plan:speak-backfill`
  со счётчиком оставшихся реплик роли; фразы ученика и слова — голос телефона.
- [x] **Клиент** — окно дня одной лентой (плита → строка 56, вкладки, одна кнопка), «Ещё раз» без
  записи ответов; старый кабинет и шит снесены; golden 12 кадров, канон 15 тестов + 3 на «Ещё раз».
- [ ] **Хвосты (найдено, в работу не бралось):**
  - суточный лимит запросов прежнего вендора голоса — 100 на проект и модель: 31 реплика роли существующих сцен
    не озвучена на 14.09 — `plan:speak-backfill` в следующие сутки; 51 строка фраз, купленная 14.09 до
    канона, лежит в `plan_line_audios` непрочитанной (удалять — отдельным решением) — снято TTS-2: голос прежнего
    вендора удалён, строки переозвучены ElevenLabs;
  - ~~job голоса не отличает суточный отказ от поминутного~~ — закрыто DAY-UI-3 (`quotaId`, ожидание до сброса
    суточной квоты); ~~51 строка фраз лежит непрочитанной~~ — DAY-UI-3 читает их расстановкой голосов сцены;
  - у не открытого дня термины сцены читаются дважды (раздатчик контура и окно) — общий кэш на запрос;
  - окно дня читает сеть и не держит копию для офлайна (как и старый кабинет) — правило «экраны
    читают локальную базу» для дня плана не решено;
  - длинное русское слово в колонке 165 переносится по буквам — переносов Flutter не расставляет;
  - вход в этап (23-2a, сессия DAY-UI) режет длинную реплику собеседника троеточием.

## Окно дня — DAY-UI-3 (2026-09-14/15): кадры 23-0a…0e, голос всего, фото при генерации

Отчёт — `docs/research/day-ui-3/README.md`; контракт — `docs/plan-api.md` «Окно дня»; решения — DECISIONS пп. 309, 310.

- [x] **Контракт окна** — у слова `pronunciation`, `definition`, `audio_url`, `usage {text, translation, offset,
  length, audio_url}`, `returns_day`; у фразы `pronunciation`, `audio_url`; `audio_url` у обеих реплик; урок + `role_gender`
  (пол собеседника); внутренний статус `illustrating` (на проводе `building`).
- [x] **Фото при генерации дня** — job сцены сразу после урока, пул 6, повторы; `ready` ждёт фото; лестница без
  голого слова; день не показывает одну картинку дважды; `--requery` переспросил 61 фото голого слова и 7 повторов.
- [x] **Голос всего двумя голосами** — вызовы сценариями под суточный лимит запросов прежнего вендора, бэкфилл
  пакетами по видам. **Заменено TTS-2**: вендор — ElevenLabs, каждая строка — свой вызов своим голосом.
- [x] **Клиент** — плита во всю ширину, пилюля на шве, шапка — функция прокрутки, шит слова 23-0e, «прослушать» у
  каждой строки, общий `AudioLoader`, весь день в загрузку при открытии; golden 16, канон 28, мутации.
- [x] **Утро 15.09** — снято нарядом TTS-2: голос переведён на ElevenLabs, план Дена, симулятор и шесть дней GEN-2a
  озвучены заново, стоимость дня — `plan:speak-report` (отчёт `docs/research/tts-2/README.md`).
- [ ] **Хвосты (найдено, в работу не бралось):**
  - **таб «План» не перечитывает сервер при возврате на таб** — `refresh()` только в `initState`; план, созданный
    вне приложения (или сменившийся на сервере), не виден до перезапуска;
  - сессия дня: у реплики ученика в этапе «Диалог» нет «прослушать» (`dialogue_read_card`), а
    `DayVoice.prepare` докачивает голос только реплик собеседника — голос ученика, фраз и слов в сессии звучит
    файлом, только если его уже скачало окно дня (кадров сессии в наряде не было);
  - фикстуры окна (`test/fixtures/plan/room_window_*`) сняты при исчерпанной квоте — у слов и фраз `audio_url`
    пустые; переснять после бэкфилла, если снимкам понадобится голос.

## Урок «каркасы» — GEN-2a (2026-09-15): `lesson_day.v4.4` в конвейере, снос урока первой формы

Отчёт — `docs/research/gen-2a/README.md` (таблица валидатора, 6 выгрузок дней, цена первой формы → v4.4, список удалённого);
канон — `docs/plan-v2.md` §2, §3а, §4; реестр промптов — строки LESSON и P2R.

- [x] **Снос урока первой формы без остатка** — промт, схема, 12 проверок с режимами, правило «клей +
  местоимение», вопрос на обмене старой формы, метки фраз и слов на сообщениях, режимы и счётчик фраз в
  конфиге, каст голосов сцены «до полов», тесты; данные — все планы на `wordtrainer` и `wordtrainer_e2e_test`
  (миграция `2026_09_15_110000`, бэкапы в отчёте).
- [x] **Промт** `lesson_day.v4.3` принят из incoming байт-в-байт; `v4.4` — правка владельца: число каркасов
  выводит модель, каркас может стоять в двух обменах.
- [x] **Хранение** — ответ модели как есть, служащий урок собирается сервером (реплики из каркасов, места
  верных ответов перемешаны); `plan_terms.frame_* / slot / used_in`; `profiles.gender` → LEARNER_GENDER.
- [x] **Валидатор** — 46 кодов с адресом карточки; **P2R** по одной карточке — `plan:repair-card` (без `--apply`
  ничего не пишет; с ним — только до раздачи дня).
- [x] **Порог** (решение архитектора по отчёту §4, DECISIONS п. 317) — 5 фатальных кодов: урок не раздаётся до P2R по
  адресу, сборка зовёт его сама, не больше двух карточек на день, дальше `failed` с кодом; остальные — предупреждения;
  `answer.index_skew` снят; `check.verbatim` без чисел/имён/предметов, `listening.distractor_not_filler` — только другой
  род. Живьём на 6 днях: 2 дня прошли порог одной карточкой (отчёт §15).
- [x] **Контракт** — GET дня и карточки совместимы с клиентом (тест против клиентских фикстур); новые поля
  окна аддитивны.
- [x] **Живой прогон** — 6 дней на тестовой базе, выгрузки для оценки Деном; день 1 свежего плана в текущем клиенте
  на симуляторе (снимки), голос диалога двумя голосами проверен нарезкой (отчёт §8).
- [x] **Голос фраз и слов на сценах v4.4** — ждал суточной квоты прежнего вендора; озвучено нарядом TTS-2.
- [ ] **Промт v4.5** — придёт от архитектора после оценки выгрузок Деном (кандидаты: ключ реплики на каркасе без
  знаменательного слова, пример «pain in my» — отчёт §4, §13).
- [ ] **GEN-2b** — клиент читает каркасы, окно и `listening` (handoff).

## Голос сервера — TTS-2 (2026-09-15): ElevenLabs, снос прежнего вендора голоса

Отчёт — `docs/research/tts-2/README.md`; канон — `docs/plan-v2.md` §7; решение — DECISIONS п. 318.

- [x] **Один порт синтеза, одна реализация — ElevenLabs** (`eleven_v3_conversational`, stability 0.5, mp3 44,1 кГц 128 кбит/с);
  каждая строка — отдельный вызов своим голосом (уточнение архитектора: диалог одним звуком не собирается и не режется).
- [x] **Голоса по роли и полу**: собеседник-женщина `4tRn…`, ученик-мужчина `TWut…`, собеседник-мужчина `Enjk…`.
- [x] **Фразы с наполнениями** — `p3.f2`, `audio_url` у наполнений в GET дня (аддитивно).
- [x] **Отказы**: 429 — повтор и ожидание; 401/402 и голос не по тарифу — job `failed` с кодом, письмо в лог, день читает
  телефон; предохранитель — остаток кредитов < 10 %.
- [x] **Учёт**: символы, кредиты из `character-cost`, $ по цене кредита тарифа, id вызова; `plan:speak-report`.
- [x] **Снос без остатка**: прежний вендор голоса, пачки под суточную квоту, нарезка и её прослушка, кодировщик в образе;
  77 строк и файлов старого голоса удалены и переозвучены; grep-тест.
- [x] **Живой прогон** (Starter): план Дена — 48 строк, 1 685 символов, 421 кредит, $0,0842, GET дня с `audio_url` у всех
  строк; симулятор — 2 дня, «прослушать» играет файлы сервера; 6 дней GEN-2a — 275 строк, $0,378. Остаток подписки —
  36 346 из 39 224.
- [ ] **Уши владельца**: мужской голос собеседника `Enjk…` подобран по описанию каталога — прослушать (дни GEN-2a «ресторан»,
  «аренда») и при несогласии сменить строку конфига.
- [ ] **Образ**: кодировщик mp3 убран из `Dockerfile` — пересобрать образ при следующем обновлении.

## SESSION-1 (клиент, сессия и окно дня) — найдено в GEN-2a, в работу НЕ бралось

- [ ] **Порядок реплик ask/rescue**: в обменах, где ученик говорит первым, окно рисует собеседника первым; у пары окна
  есть `kind` (`ask`/`rescue` — реплика ученика первой). Снимок — `docs/research/gen-2a/screens/07-dialogue-ask-rescue.png`.
- [ ] **Перевод реплики ученика и перевод фразы расходятся**: реплика в диалоге — перевод модели в контексте («Она у
  него уже три дня.»), фраза — каркас с наполнением («У него это уже три дня.»); один текст на обе карточки или
  пометка, какой где.
- [ ] **Перенос длинного слова**: перевод на карточке слова ломается посреди слова («жаропонижающе / е») — перенос по
  слогам или уменьшение кегля. Снимок — `docs/research/gen-2a/screens/03-words.png`.

## Приёмка 08.09 — баг-репорт (разбор: `docs/research/acceptance-0908.md`)

> Относится к СТАРОЙ цепочке плана, снесённой нарядом PLAN-GEN (2026-09-10); пункты про
> `plan_sitting_empty`, гейты пар и `raw_response` больше не воспроизводимы. Оставлено как история.

Семь находок с телефона владельца, одна починена. Каждая с доказательством и предложением в отчёте.

- [x] **Пара «реплика роли ↔ твой ход» бралась по умению, а не по цепочке** — починено `15530593`,
  решение 303.
- [ ] **«6months» ≠ «six months»: верная реплика получает «Не то».** Распознаватель пишет число
  цифрой и склеивает со следующим словом; пере-нарезка границ разложить не может, потому что
  сверяется со словами цели, а там `six`. Нормализация речи получает шаг числительных (расклейка
  цифра↔буква против слов цели, затем цифра → слово). Внутри полномочий SPEECH-2.
- [ ] **Экран пройденного дня зовёт в никуда**, когда следующий день не собрался: «Продолжить» →
  409 `plan_sitting_empty`. Вкладка «План» при тех же данных показывает всё правильно.
- [ ] **Riverpod ретраит 409 десять раз** (`ProviderContainer.defaultRetry`, 200 мс → 6.4 с) —
  35 секунд белого экрана на ответ, который не изменится. Провайдерам нужен `retry`, отбивающий
  окончательный доменный ответ (4xx с RFC 7807 `code`).
- [ ] **Гейт `card.translation_missing_key` не умеет вид глагола.** «подходить» → основа «подхо», а
  естественный русский — «не подойдёт»; корень меняется, префикс не перепрыгивает. Повтор истории с
  «тихий», стоившей $0.17. Сравнивать фактическое общее начало с полом 3 буквы.
- [ ] **`raw_response` не пишется для плановых вызовов** — что именно написала модель и почему это
  отказали, узнать нечем.
- [ ] **Сцена спорит с гейтами:** умение «спросить о минимальном сроке аренды» против
  `card.chunk_is_basic [six months]` — то, ради чего сцена написана, гейты писать не дают. Вопрос
  владельцу, кодом не решается.
- [ ] **`planDayTrainMore` («Дотренировать», кадр D-06б) — мёртвая строка:** есть в обоих `.arb` и
  не используется ни одной строкой кода.

## Хвосты наряда SPEECH-2 (2026-09-08) — найдено, в работу НЕ бралось

- [ ] **Сторож записи в 15 с включает время на размышление.** `listen_seconds` теперь ограничивает
  ВСЮ запись от нажатия (SPEECH-2, Ч.2.1), а не ожидание первого слова. Человек, нажавший и
  подумавший пять секунд, получает на длинную реплику десять. Пол 15 с — из наряда; поднимать
  число, если живой прогон покажет обрывы, надо на сервере (`SCENE_RUN_LISTEN_SECONDS`), выката
  приложения это не требует.

## Хвосты наряда input1 (2026-08-27) — найдено, в работу НЕ бралось

Наряд про честность интерфейса закрыт по коду (`c338901`…`e539d00`, см. `session-handoff.md`); ниже
то, что всплыло попутно. Каждый пункт — самостоятельная задача.

- [ ] **`mobile/CLAUDE.md` обещает превью в Chrome, которого нет.** Раздел «Verify without the phone»
  говорит, что `tool/preview.dart` и `tool/ladder_preview.dart` запускаются «on the iOS simulator …
  or in Chrome». Web-сборка падает: `drift` тянет `sqlite3`, тот — `dart:ffi`, которого на web нет.
  Утверждение протухло и стоило времени в этом наряде. Решить: либо убрать половину про Chrome, либо
  завести web-вариант БД для харнессов.
- [ ] **Полоса плотности не отличает «Отложено» от «Разобрать».** `classifyDensity` читает
  `enrolled_at`, а «было ли слово когда-то зачислено» локальное зеркало не хранит, поэтому
  отложенное слово считается неразобранным. С точки зрения полки это честно (снова слово без
  решения), и карточка слова различает их правильно — но на полосе четвёртого статуса нет. Решать
  только вместе с вопросом «нужен ли четвёртый сегмент», которого дизайн-система не предусматривает.
- [ ] **Строка-подсказка в списке полок не говорит про карточки.** «Учить 5» под обложкой 96 pt
  осталась в словах: Ч.3 распространялась на кнопки, а это строка. Либо распространить, либо
  зафиксировать, что подсказка в списке — не обещание сессии.
- [ ] **Панель симулятора Claude Code падает и не поднимается переоткрытием.** Счётчик аварий живёт
  в MCP-сервере, лечится только новой сессией; при этом `xcrun simctl` продолжает снимать экран, а
  ввода нет. Из-за этого Ч.6 наряда не доведён. Кандидат в `docs/qa/PLAYBOOK.md` — как обходить,
  а не как чинить.

