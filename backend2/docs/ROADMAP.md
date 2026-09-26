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
  минуты по `DayPace` (тогда — темп этапа; с SESSION-1a — секунды на ВИД карточки, `config/plan.php` →
  `pace`), состояния единиц и счётчики бровей, одно действие
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
  - сессия дня: у реплики ученика в этапе «Диалог» нет «прослушать», а `DayVoice.prepare` докачивает голос только
    реплик собеседника — голос ученика, фраз и слов в сессии звучит файлом, только если его уже скачало окно дня
    (кадров сессии в наряде не было). Карточка того экрана снесена нарядом SESSION-1a вместе со всей прошлой
    раздачей; вопрос «кто качает голос сессии» переезжает в SESSION-1b;
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
- [x] **Промт v4.5** — принят нарядом GEN-2b (ключ на каркасе без знаменательного слова — каркас до окна).
- [ ] **Клиент читает каркасы, окно и `listening`** (было «GEN-2b» в handoff GEN-2a; наряд GEN-2b стал серверным) — `frame`,
  `used_in`, `kind`, `phrase_ref`/`filler`, `listening`, `audio_url` наполнений.

## Урок v4.5 и валидатор под пару языков — GEN-2b (2026-09-15)

Отчёт — `docs/research/gen-2b/README.md` («было/стало» v4.4 → v4.5 на шести днях, ro/uk, порог вживую, P2R по моделям;
доработка — §14); канон — `docs/plan-v2.md` §2, §3а, §4; реестр промптов — строки LESSON, P2R, SEAM-JUDGE v1.1; решения —
DECISIONS п. 319 и пп. 320–324 (доработка).

- [x] **Промты** `lesson_day.v4.5` и `lesson_card_repair.v1.1` приняты из incoming байт-в-байт; `{{rules}}` P2R — цитата v4.5.
- [x] **Порог — 7 фатальных**: + `exchange.second_question` (адрес — обмен), `exchange.repeats`; карточка «обмен» с
  `frame_update` — атомарно; `exchange.shape` чинится обменом.
- [x] **P2R — узкий контекст** (`LessonCardContext`); модель — конфиг `PLAN_REPAIR_MODEL`, по умолчанию модель урока:
  `gpt-5.4-mini` не прошёл «чинит не хуже».
- [x] **Пакеты языков** `config/lesson/lang/{en,ru,uk,ro}.php`, `lang.pack_missing` вместо находки; 6 новых предупреждений;
  судья швов `lesson_seam_judge.v1` — один вызов на день (с доработки — `v1.1`).
- [x] **«Было/стало»** на шести темах + ro→en и uk→en: 8 выгрузок с пустой колонкой оценки для Дена.
- [x] **Решения архитектора по живому порогу** (отчёт §5) — закрыты доработкой: (1) знак конца вне сверки,
  `frame.no_end_punct` — п. 320; (2) наполнение реплики находит сервер по тексту, поле `filler` модели не читается — п. 321;
  (3) цена P2R: модель урока закреплена, цель $0.01 снята, канон «починки ≤ 10 % цены дня» — п. 323; (4) ключ — серверный из
  каркаса, коды `key.*` сняты — п. 322; (5) `check.verbatim`/`vocab.used_in_wrong` — без изменений.
- [x] **Доработка** (отчёт §14): порог на тех же 8 ответах — **8 из 8 `ready`**, 4 карточки P2R `gpt-5.4`, $0.0681; «было/стало»
  пересчитано новым валидатором; судья `lesson_seam_judge.v1.1` на 8 днях; выгрузки для Дена пересобраны; кодов 50.
- [ ] **v4.6 — заметки** (к следующей правке промта урока, по живым дням GEN-2b): второй вопрос собеседника в ask-обмене
  («Sure. May I see your passport?») — 3 дня из 8 против 0 у v4.4, каждый раз карточка P2R; наполнение-придаточное
  «if the fever gets worse» при каркасе «… if ___?» — вопреки ✗-примеру самого v4.5 (врач, и uk→en); канцелярит
  «My leadership style is clear communication.» — ✗-пример v4.5 остался дословно.
- [x] **Пакеты языков ученика uk и ro** — *закрыто LANG-1 §4* (DECISIONS п. 430): uk и ro — полные пакеты (с ними be, pl,
  es, it, de, fr; en/ru дополнены), каждый читаемый кодом ключ своей стороны заполнен; правило не к языку — no-op, не
  `null` карточки (`null` = `lang.pack_missing`); дни uk→en и ro→en разведки LANG-1 — `lang.pack_missing` 0/0. Было
  (TODO-карточка GEN-2b): сейчас каркас без правил, урок такого ученика не проверяет
  8 кодов родного языка (с доработки — и сторону `frame_native` у `frame.no_end_punct`). Написать по ru: `script` (uk — кириллица с і ї є ґ и апострофом; ro — латиница с ă â î ș ț и их
  седильными двойниками ş ţ), `sentence_ends`, `function_words`, `word_forms` (для uk — как у ru; для ro — окончания короче,
  проверить на живом дне), `number_pattern` и `time_pattern` (для uk — формы, которые до GEN-2b лежали в русских
  выражениях: чотири, п'ять, шість, сім, вісім, дев'ять, раніше, пізніше, зараз, потім, хвилин, годин, дні, днів, тиж…,
  місяц…, рік/роки/років, ранок, ранку, вранці, вечір, вечора, ввечері, ніч, вчора/учора, сьогодні), `gendered_past_pattern`
  (uk: «я працював/працювала»; ro — прошедшее в роде не меняется, ключ пустым списком не писать — написать `null` и
  отметить, что правило к языку не относится), `agreement` (uk: мій/моя/моє/мої, який/яка/яке/які, дозволений…; ro —
  артикль и прилагательное после существительного: «___ este permis/permisă»). Критерий: дни uk→en и ro→en из отчёта
  GEN-2b проходят валидатор без `lang.pack_missing` и без ложных находок на выгрузках.

## День N знает прошлые дни — GEN-3 (2026-09-17): урок v4.6, P2R v1.2, журнал вызовов, расписание дней

Отчёт — `docs/research/gen-3/README.md` («было/стало» v4.5 → v4.6 на шести темах, журнал и сверка, расписание); канон —
`docs/plan-v2.md` §2, §3, §3а, §4, §5; контракт — `docs/plan-api.md`, `openapi/openapi.yaml` (`catch_up`, `building`,
`plan_day_building`), `openapi/openapi-admin.yaml` (песочница job'ом); реестр промптов — LESSON, P2R, «откат» v4.5/v1.1,
PLAYGROUND, «Журнал вызовов модели»; решения — DECISIONS пп. 330–336.

- [x] **Промты** `lesson_day.v4.6` и `lesson_card_repair.v1.2` приняты из incoming байт-в-байт, incoming снесён; `v4.5`/`v1.1`
  лежат для отката одной константой.
- [x] **Входы дня** `LEARNER_ROLE`, `PARTNER_ROLE`, `EARLIER_DAYS` (один `LessonRequests`); роли ответа сервер пишет поверх из плана.
- [x] **Коды**: фатальные `vocab.known_repeat`, `frame.known_repeat`; предупреждения `vocab.abbreviation` (с доработки),
  `frame.known_native_repeat`, `frame.twin`, `frame.adjacent_repeat`, `role_gender.changed` — 57 кодов, 9 фатальных.
- [x] **Доработка** (отчёт «Доработка»): промты `lesson_day.v4.7` / `lesson_card_repair.v1.3` (откат — v4.6/v1.2, файлы v4.5/v1.1
  сняты); аббревиатура — предупреждение, слово ли она, судит модель; починке каркаса не цитируются находки о родном шаблоне;
  разбор снимает пробел перед знаком конца у родного каркаса и родных наполнений. Живых вызовов нет.
- [x] **P2R**: `NEIGHBOURS` у обмена, короткий `EARLIER_DAYS` всем, вид `term` с перепроверкой сервером, схема одна на вид.
- [x] **Кэш вендора**: правила первыми и одинаковы, `cached_tokens` в журнал, `ModelCost` по цене кэша.
- [x] **Журнал `model_calls`**: запись до вызова, `completed`/`failed`/`lost`, `model-calls:sweep-lost`; ответ 180 с, соединение
  10 с, обрыв не повторяется, job'ы — одна попытка; песочница — job с опросом (и wt_admin).
- [x] **Расписание** (§11): день N+1 — от открытия дня N, «догоняем», день события учебный; урок N+1 — на закрытии дня N через
  `NextDayAccess`; `building` и 409 `plan_day_building`.
- [x] **«Было/стало»** на шести темах GEN-2b: 18 дней `ready`, выгрузки для архитектора; мутации тестов — 38 из 38 пойманы.
- [x] **Решение: `frame.known_native_repeat` фатально или нет** — принято (доработка): предупреждение, не фатально (цифры отчёта
  §3: на 43 каркасах дня 2 v4.5 — 3, v4.6 — 1).
- [ ] **Рычаг «в `EARLIER_DAYS` диалоги только двух последних дней»** — если цена или внимание модели покажут нужду. Сейчас прошлый
  день — ≈ 1 100 токенов входа вне кэша (≈ $0.0028 на `gpt-5.4`): последний день-сцена плана на 10 дней (день 8, пять прошлых дней-сцен) понесёт
  ≈ 5 500 лишних токенов (≈ $0.014 — пятая часть цены дня $0.064); живьём проверен только день 2.
- [ ] **Качество починок по истории — проверить живьём на v4.7 / v1.3** (отчёт §3 и «Доработка»): «Что с ним? — ___.» закрыт
  правилом v1.3 и тем, что находки о родном шаблоне починке не цитируются; «ATM → Visa sign» больше не случится — ATM законное
  слово дня. Открыто: «He's got ___.» против «He has ___.» — другой ли это шаблон цели (v1.3: «не тот же с синонимом») — судит
  модель, код сравнивает лексически. Проверка — первый живой день 2 на v4.7 (день 2 NKKGFF, отчёт §6).
- [x] **Назначение в журнале** — разнесено нарядом BACK-TAILS-1 §3.3: `plan` / `lesson` / `repair` / `judge` в `model_calls`,
  расход в логе исходящих остался одним `plan` (DECISIONS п. 343).
- [ ] **Прямые адаптеры первой генерации в журнал** (`OpenAiWordLookup`, `OpenAiCollectionGenerator`, `OpenAiTermEnricher`,
  `OpenAiEnrichmentPacker`, `OpenAiExampleRegenerator`, `OpenAiTermTransliterator`, `OpenAiTranslationRepairer`,
  `OpenAiDialogSummarizer`) — пишут только лог запросов; перевести на `VendorCall`, когда их будут трогать.
- [ ] **Сверка журнала с панелью OpenAI за 17.09** (отчёт §4a) — ждёт сумму панели от Дена: Costs API ключа отвечает 403 (нет
  `api.usage.read`).
- [ ] **Прод `wordtrainer`**: миграция `model_calls` и рестарт Horizon не сделаны (наряд не трогал боевую базу); пока таблицы нет,
  журнал пишет предупреждение в лог приложения и вызов идёт. Команда — отчёт §6.
- [ ] **Клиент** (наряд полировки): плита дня `building` («собираем урок», опрос `GET /plans` до `ready`), подпись «догоняем» при
  `catch_up: true`, 409 `plan_day_building` на открытии; песочница админки уже опрашивает.
- [ ] **PAY-1** — пейволл после дня 1 и сборка урока по факту оплаты: запоры дней по подписке пришли с ACC-1 (за
  рубильником, DECISIONS п. 422); за PAY-1 — покупки и тело `NextDayAccess::nextDayAllowed` (сейчас `EveryNextDayAllowed`,
  всегда «да»): сборка на оплате.
- [x] **Пакеты языков uk и ro** — *закрыто LANG-1 §4*, пункт GEN-2b выше.
- [ ] **Контент урока gen3 (из отчёта клиента 1c, BACK-TAILS-2)** — «уже уже» в переводе p2; «It looks like a viral
  infection» как реплика ученика x5b; «Я дал(а)»; «Сколько дней у него это?». В работу не брано — хвосты генерации.
- [x] **Укорачивание под дату события (`daysUntil`) считает день события не учебным, открытие дней — учебным** (расхождение 14
  отчёта GEN-3) — выровнено нарядом BACK-TAILS-1 §3.4: день события учебный и там (DECISIONS п. 344). Затрагивает только
  новые планы.

## Хвосты бэкенда и контракты под канву серии 36 — BACK-TAILS-1 (2026-09-17)

Отчёт — `docs/research/back-tails-1/README.md`; канон — `docs/plan-v2.md` §3а/§4/§5/§6, контракт — `docs/plan-api.md`;
решения — DECISIONS пп. **337–345**. Живых вызовов модели не было, `wordtrainer` не тронут.

- [x] **`speak_retell` → «Повтори свою реплику»** (кадр 35-4): реплика ученика, зачёт клиентский по покрытию; серверный
  путь пересказа снесён вместе с входом `TASK` промпта — `slot_judge.v2` (п. 337).
- [x] **«Что прозвучит в ответ?»** (кадр 34-5): варианты `listen_predict` — реплики дня со звуком и обоими текстами,
  отвлекающие — отвечающие ученику, добор по форме (п. 338).
- [x] **«Поймай число»** (кадр 34-7): варианты — только числа и количества, новый ключ пакета `amount_pattern` (п. 339).
- [x] **`dialogue_ask` несёт проверку обмена** (кадр 33-5), отдельная карточка у `ask`-обменов снята; `plan.pace` —
  45 с (п. 340).
- [x] **Перевод предложения для сказанного наполнения** — `text_native` реплики модели, кроме реплик после клея (п. 341).
- [x] **`mode = "rounds"`** принимается сервером (хвост SESSION-1b §15.1 п. 12).
- [x] **Нестабильный `SessionWordChecksTest`** — причина найдена (круг из четырёх видов на дне из двух слов, старт от
  id сцены), тест переписан на день, где круг детерминирован; не «стабилизирован повтором».
- [x] **Дыра хука ворот с `git -C`** — закрыта: подкоманда ищется после опций `git`, рабочий каталог берётся из `-C` /
  `--work-tree`.
- [x] **Пробел перед знаком конца снимается и в `frame_target`** (§3.1).
- [x] **`pronunciation.foreign_script`** — чужие буквы в чтении фатальны, фатальных кодов 10 (п. 342).
- [x] **Копии базы по возрасту, `--safety` ничего не удаляет** (п. 345).

Доработка (18.09, решения владельца; DECISIONS пп. **346–347**):

- [x] **Выбор на `dialogue_ask` едет на сервер полем `choice`** и возвращает обмен тем же правилом, что было у снятой
  `dialogue_partner`; голосовой итог не трогается; неведомый выбор — 422 `plan_card_choice_not_allowed` (п. 346).
- [x] **Вариант «Поймай число» — количество в форме реплики**, с предлогом и определением («на этой неделе»), не голое
  существительное; новый ключ пакета `amount_prefix` (п. 347).
- [x] Подтверждено владельцем правило отвлекающих `listen_predict` (п. 338) — вопрос снят.
- [x] **Клиент шлёт `choice` на `dialogue_ask`** — закрыто нарядом SESSION-2b (`ced1b1bd`, отчёт
  `docs/research/session-2b/README.md`): локальный зачёт выбора снят, ответ карточки ждёт выбора и уходит одним
  (`result` + `choice`, id варианта); карточка без проверки и «Пропустить» отвечают сразу, без `choice`. До этого
  сервер принимал выбор, а клиент его не слал — ни копии, ни возврата обмена не происходило.

Хвосты наряда — найдено, в работу НЕ бралось:

- [ ] **`docs/plan-dialogue.md`, названный нарядом каноном `speak_retell` и вариантов-озвучек, не существует** — снесён
  PLAN-GEN вместе со старой цепочкой (`5b2809c1`). Канон этих правил записан в `docs/plan-v2.md` §6 и `docs/plan-api.md`.
- [ ] **Подсказка намерения в диалоге («Спроси про работу») — генерация**, позже: сервер задания к `dialogue_ask` не даёт,
  клиент формулирует сам (`docs/plan-api.md`, «Чего сервер не даёт», кадр 33-5).

## Голос сервера — TTS-2 (2026-09-15): ElevenLabs, снос прежнего вендора голоса

Отчёт — `docs/research/tts-2/README.md`; канон — `docs/plan-v2.md` §7; решение — DECISIONS п. 318.

- [x] **Один порт синтеза, одна реализация — ElevenLabs** (`eleven_v3_conversational`, stability 0.5, mp3 44,1 кГц 128 кбит/с);
  каждая строка — отдельный вызов своим голосом (уточнение архитектора: диалог одним звуком не собирается и не режется).
- [x] **Голоса по роли и полу**, голоса ролей в сцене всегда разные (доработка): собеседница `4Nej…`, собеседник `Enjk…`,
  ученик `TWut…`, ученица `Nhs7…` — утвердил Ден на слух 15.09; строки `SPEECH_VOICE_EN_*` в `.env`.
- [x] **Темп всегда обычный** (решение архитектора): замедление ученика пометкой в тексте построено и снято тем же днём
  (+43 % к цене дня).
- [x] **Кап одного запуска покупок** `SPEECH_JOB_CREDITS_CAP` (3 000): задача сцены или бэкфилл, проверка до покупки, стоп и
  письмо в лог; `--count` называет цену недостающего — кредиты · символы · $.
- [x] **e2e-стенд исключён из автоозвучки и бэкфилла** — голос там только по явному `--plan`.
- [x] **`--drop-unread`** — переозвучка только строк сменившегося голоса.
- [x] **Переозвучка плана Дена** обычным темпом по «да» — 40 строк голоса ученика: цена 338 · 1 293 · $0,0676, счёт
  324 кредита · 1 293 символа · $0,0648; день Дена снова 421 · 1 685 · $0,0842, `audio_url` у всех 48 строк.
- [x] **Мёртвые файлы замедленного голоса** удалены без переозвучки (`--drop-only`): симулятор — 70 строк, e2e — 153.
- [ ] **Строки ученика плана симулятора (70) и 4 дней e2e (153)** — без серверного голоса, звучат голосом телефона; озвучить
  только по команде, с ценой из `--count`.
- [x] **Фразы с наполнениями** — `p3.f2`, `audio_url` у наполнений в GET дня (аддитивно).
- [x] **Отказы**: 429 — повтор и ожидание; 401/402 и голос не по тарифу — job `failed` с кодом, письмо в лог, день читает
  телефон; предохранитель — остаток кредитов < 10 %.
- [x] **Учёт**: символы, кредиты из `character-cost`, $ по цене кредита тарифа, id вызова; `plan:speak-report`.
- [x] **Снос без остатка**: прежний вендор голоса, пачки под суточную квоту, нарезка и её прослушка, кодировщик в образе;
  77 строк и файлов старого голоса удалены и переозвучены; grep-тест.
- [x] **Живой прогон** (Starter): план Дена — 48 строк, 1 685 символов, 421 кредит, $0,0842, GET дня с `audio_url` у всех
  строк; симулятор — 2 дня, «прослушать» играет файлы сервера; 6 дней GEN-2a — 275 строк, $0,378. Остаток подписки —
  36 346 из 39 224.
- [ ] **Образ**: кодировщик mp3 убран из `Dockerfile` — пересобрать образ при следующем обновлении.

## Разговор — CONV-2 (2026-09-21): хвосты после живых прогонов

Отчёт — `docs/research/conv-2/README.md`; канон — `docs/plan-v2.md` §6 («Говорю сам», потолок «Фраз») и §11; контракт —
`docs/plan-api.md` «Разговор с агентом» + `openapi/openapi.yaml`; решения — DECISIONS пп. **366–378**, пять записей в
«Отменено», спорное п. 2 (пол «два круга» против потолка дня).

- [x] **Роль говорит только свою сторону** — `conversation_agent.v2` (обмены с обеих сторон, TWO SIDES) + страховка кода
  `RoleLines` (перезапрос один раз с `REDO`, вырез предложения, счётчики). На входах Дена 21.09: 11 переворотов → 0.
- [x] **«Ещё раз» не снимает этап** — журнал `plan_stage_passages` (append-only, первый собственный конец), `replay` на
  проводе, `plan:reconcile-talks` для дней до журнала (на бою — см. отчёт §6).
- [x] **Минуты разговора — по промежуткам ≤ 60 с**; пять часов простоя = 2 минуты.
- [x] **Переспрос** — слова из пакета (`rescue_line`), роль перефразирует, страховка ≥ 0,7.
- [x] **Лестница «Фраз»: два круга — пол**; ступени — третье узнавание → третий круг → стоп-сигнал. **Стоп-сигнал
  сработал** на двух живых днях (33 мин карточек) — решение за архитектором (DECISIONS, «Спорное» п. 2).
- [x] **«Говорю сам» — только реплики ученика**: эхо на своей реплике (`own_line`, `partner_line` — та же, временно).
- [x] **Судья окна v3** — `MODE answer` по смыслу, `own_value` по роду значения, пустое окно — отказ кодом с подсказкой,
  `heard` в ответе. Попытки Дена: 8 из 8 верно.
- [x] **`highlights` при всех пройденных этапах**, до «Закрыть день».
- [x] **`targets[]`** — один список на вход 37-5, полоску ленты и итог; `phrases_used` с текстом.
- [x] **Строки сервера** — `hints.native` придаточным, `talk_title_native`, `scenes_count`, `usage` у `phrase_intro`,
  enum этапов в OpenAPI.
- [x] **Хук ворот** — каждая простая команда цепочки, сквозь кавычки; `SKIP_GATES=1` на самом коммите.
- [x] **Рубильник** — на бою включён, кэша конфига нет, процедура — отчёт §7; комментарий в `config/plan.php` исправлен.

**Хвосты CONV-2 (найдено, в работу НЕ бралось):**

- [x] **Потолок дня против пола «два круга»** — стоп-сигнал: день зала Дена 33 мин карточек («Фразы» 823 с), «врач» e2e
  33 мин (810 с) при потолке 32 / 690. *Закрыто BACK-TAILS-2 §1* (DECISIONS п. 379): пол «1 узнавание + 2 круга +
  своё», третья ступень снимает второе узнавание — оба дня 31 мин карточек, «Фразы» 713 с (сигнал +23 с в журнале).
- [ ] **Клиент (наряд 1c): `SessionRules.replayAccepted` строже сервера** у `speak_answer` — повтор пройденного дня
  («Ещё раз» из итога) требует слов каркаса, а сервер после п. 7 их не требует (инвариант «клиент не строже сервера»,
  отчёт §8 п. 1). Зеркало серверного правила без модели: не услышано ничего — нет; только слова каркаса и служебные —
  нет; иначе — да.
- [x] **Клиент: три теста прибиты к старому эху** (`session_rules_test.dart:139`, два теста 35-3 в
  `session_speak_cards_test.dart`) — фикстуры `day-doctor*.json` теперь раздают эхо на реплике ученика (отчёт §8 п. 2).
  *Закрыто CLIENT-CONV-1b:* тесты читают реплику из карточки, правила 35-3 те же, код эха не тронут.
- [x] **Снять `partner_line` у `speak_echo`**, когда клиент читает `own_line` (сборка после 17) — одна строка в
  `SpeakCards::echoLine` и схема OpenAPI. *Снято BACK-TAILS-2 §10* (DECISIONS п. 388); на бой — после сборки (19).
- [x] **Роль повторяет реплику ученика эхом от первого лица** — в повторе Дена (ход 15) администратор сказал «That works
  for me on weekdays», и страховка пропустила это как эхо сказанного учеником («Weekdays works for me», 67 %).
  *Закрыто BACK-TAILS-2 §9* (DECISIONS п. 387): вторая сверка против последнего хода по ключевым словам с обменом лиц,
  перезапрос, вырез, нейтральный ход; промпт v2.1 — одно правило.
- [x] **Разговор репетиции и повторения живьём с v2 не проверялся** — *проверено BACK-TAILS-2 §12* на v2.1: повторение
  4 хода, репетиция 2 сцены × 10 ходов, день-сцена 4 хода — роли свои, эха 0 (отчёт BACK-TAILS-2 §4).
- [x] **Фикстуры разговора `docs/fixtures/conversation-*.json`** — снимки живого сервера до CONV-2 (без `targets`,
  `replay`, `talk_title_native`). *Пересняты BACK-TAILS-2 §13* с живого прогона на копии e2e (handoff §9).
- [x] **Комментарий над `PLAN_CONVERSATION_ENABLED` в боевом `.env` всё ещё говорит «Выключен»** — значение `true`;
  `.env` не в git, наряд его не правил (только чтение на бою). *Закрыто ACC-1 §3:* рубильника больше нет, строка и
  комментарий сняты с боевого `.env` при выкате.

## Хвосты дня и разговора — BACK-TAILS-2 (2026-09-22)

Отчёт — `docs/research/back-tails-2/README.md`; канон — `docs/plan-v2.md` §6 («Фразы», «Говорю сам»), §11 (разговор);
контракт — `docs/plan-api.md` + `openapi/openapi.yaml`; клиенту — `docs/session-handoff.md` §9; решения — DECISIONS
пп. **379–388**, шесть записей в «Отменено», «Спорное» п. 2 закрыто.

- [x] **Лестница «Фраз»** — пол «1 узнавание + 2 круга + своё», ступени: третье узнавание → третий круг → второе
  узнавание → стоп-сигнал в журнал сборки (`plan.phrases_over_ceiling`). «Врач» e2e и зал Дена: 33 → 31 мин карточек.
- [x] **`targets[].said` «как человек»** — `PhraseUse`: ключевые слова цели по основам, любой порядок, пропуск от
  четырёх, слово модели — вторая опора. Зал Дена: 0 → 3 из 7 (ходов было четыре).
- [x] **Этап `repetition`** у дня повторения — миграция данных обратимая; enum этапов в OpenAPI везде.
- [x] **Окно**: `stages[].minutes` у всех рядов, `stages[].targets` у ряда разговора (тот же список, что у `POST
  …/conversation`, `said` — последний разговор дня), `sources[]`, `talk_again`.
- [x] **«Вспомнить»** — реплика `{ref, text_target, text_native, audio}`; **одно имя сцены** — из плана,
  `plan:reconcile-scenes` для розданных листов.
- [x] **Повтор разговора** на пройденном дне, кап 3 в сутки ученика → 409 `plan_conversation_replay_limit`.
- [x] **`minutes_spent`** = карточки + разговор, прошедший этап (с момента прохождения); повторы не входят.
- [x] **Эхо роли** — вторая сверка против последнего хода, перезапрос, вырез, нейтральный ход; `conversation_agent.v2.1`.
- [x] **`partner_line` у эха снят.**
- [x] **Эхо старой формы приведено к нынешней** (дополнение по отчёту клиента 1c, решение Дена): обратимая миграция
  данных `2026_09_22_110000_bring_old_echo_cards_to_todays_form` — только `speak_echo` без `own_line`: `own_line`,
  `expected_text`, `speech_mode` той же функцией, что у новой раздачи (`DayDealer::echoOf()`), `coverage_min` снят,
  `partner_line` оставлен для отката. На бою — 10 карточек (все 10 равны свежей раздаче своего обмена, кроме
  `partner_line`), на e2e — 1; на e2e миграция не прогнана (к шагу 6 выката).
- [x] **Живой прогон** на копии e2e: $0.040269 (36 вызовов, голос выключен); фикстуры дня и разговора пересняты.
- [x] **§11 (решение архитектора 22.09):** снести `plan_days.has_conversation` и рубильник `PLAN_CONVERSATION_ENABLED`
  следующим бэкенд-нарядом после недели (19) на бою; 4 дня `in_progress` на удалённых планах закрыть той же миграцией.
  Дни удалённых планов — не препятствие; в ветке BACK-TAILS-2 §11 не делается. *Сделано ACC-1 §3:* дни на пяти этапах
  (4 в работе — удалённых планов, 6 закрытых) не закрыты, а получили шестой этап ПРОПУЩЕННЫМ (п. 423).
- [x] **e2e на ветке** (решение Дена 22.09, без влития в `main`): бэкап → `migrate` кодом ветки (657 карточек →
  `repetition`) → `plan:reconcile-scenes --apply` (1 лист) → сайдкар `wt_app_e2e` (:8010) на worktree; новый контракт на
  стенде проверен (отчёт §1 «e2e на ветке»).
- [x] **Бой — ОДНИМ заходом по команде Дена после сборки (19)**: `scripts/db-backup.sh --safety` → `migrate` боя →
  ff-влитие в `main` → `restart horizon` → `plan:reconcile-scenes --apply` (1 лист, план Дена) → сайдкар e2e обратно на
  `main` → worktree снести → `wordtrainer_test` догнать (отчёт §9). *Выкачено 22.09, 11:13–11:17 UTC:* эхо старой формы —
  10 строк, три ключа; `repetition` — 0; `main` → `39eb3804` (rebase + ff); reconcile — 1 лист; e2e на `main` + 1 эхо;
  проверка на QA-аккаунте боя — окно с `minutes` и `targets`, день раздаётся, разговор стартует; ошибок в логах нет.
- [ ] **Инфра (не сейчас): бой исполняет рабочее дерево `main`, поэтому любое влитие = выкат.** `wt_app`, `wt_horizon`,
  `wt_scheduler` (и сайдкар e2e) смонтированы на `/Users/yalantisdenys/eng-std/backend2`: ff в `main` сразу отдаёт код
  боевому API, миграция и сборка клиента его не ждут. Нужен отдельный checkout или тег для боя, чтобы влитие в `main`
  перестало быть деплоем.

**Хвосты BACK-TAILS-2 (найдено, в работу НЕ бралось):**

- [ ] **Роль переспрашивает сказанное** — *частично FIX-3 §7*: промпт v3 («приготовленный визит — не сценарий»), `DONE`
  у сказанных обменов, страж повтора своей реплики; живой прогон FIX-3 всё ещё ловит переспрос (отчёт FIX-3 §7). На живом прогоне врач спросил «How long has he had the fever?» сразу после «he
  has had it for three days», «Does he also have a sore throat?» после «he also has a sore throat», регистратор — «his
  upper back or his lower back?» после «His lower back hurts». Не эхо (правило §9 его и не должно ловить) — роль не
  слушает; промпт в этом наряде правился одним правилом. Кандидат в следующую версию роли.
- [ ] **Лицо в цели разговора** — «What should we do at home now?» не засчитано как «What should I do at home?»: у цели три
  ключевых слова (what, I, home), пропуск разрешён от четырёх, «we» ≠ «I». Человек засчитал бы. Решение архитектора:
  обмен лиц I↔we в правиле §2 или порог пропуска.
- [ ] **Фикстура репетиции на стенде e2e**: сцена «Запись к врачу» вставлена в план руками (1b) и не имеет своего дня —
  `sources[].day_number = null`. В плане генератора так не бывает; стенд при случае пересобрать.
- [x] **Страж эха роли читает только последний ход ученика** — *закрыто FIX-3 §11*: все ходы разговора.
  Было: — в повторе роль повторила вопросом фразу с хода 2 (документ
  клиента `docs/research/client-conv-1c/live/conversation-replay-live.json`, ход 5).
- [x] **Задание 35-2 — придаточным** — *закрыто FIX-3 §11*: `task_clause_native`. Было: `task_native` у `speak_answer` отдавать придаточным, как `hints.native` (клиент держит
  зеркало `IntentClause`).
- [x] **Числа итога этапа 30-6 — сервером** — *закрыто FIX-3 §10*: `stages[].summary {done, total, first_try, returns}`.
- [ ] **Род роли в пакете** — для «повторил / повторила».

## Языки плана — LANG-1 (2026-09-26): семь целевых и девять родных, десять языковых пакетов, урок v4.8

Отчёт — `docs/research/lang-1/README.md` (часть A — разведка 14 пар, часть D — живые дни; спецификация 42 ключей пакета —
`pack-keys.md`); контракт — `openapi/openapi.yaml` (`GET /languages`, 422 `language_pair_invalid`, `PATCH /profile`,
`number_tens_joiners`); реестр промптов — строка LESSON v4.8; решения — DECISIONS пп. **427–438**, «Отменено»: п. 180 в
части списков языков, п. 319 в части «uk и ro — каркас», файл `lesson_day.v4.6`. **Выкачено 26.09, 00:54–00:57 UTC**
(ff `main` до `1e30b2e7`, миграций нет, Horizon и scheduler перезапущены, админка пересобрана; сверка сборки (21) до/после —
разница только `versions.build` и `versions.prompt_lesson`); worktree `../lang1`, ветка `lang-1`, сайдкар `wt_lang1` и базы
сняты; коммиты — `git log f5aac8c3..1e30b2e7`. Покупки — ≈ $3.04 из $6
(модели $2.8720; голос 857 кредитов · 3 434 символа · $0.1714).

- [x] **Часть A — разведка** («часть A — разведка 14 дней новых пар, «на глаз», база фатальных кодов, образцы голосов»):
  14 дней на v4.7, 6 `ready` · 8 `failed`; перечитана с пакетами — `lang.pack_missing` 0/0 у всех пар.
- [x] **§7 Списки и пара** (пп. 427–429) — `LanguageRoles::planTargets()` = en pl ro es it de fr, `planNatives()` = ru uk
  be pl ro es it de fr — в коде, не в конфиге (п. 145); `PLAN_LANGUAGES` только сужает цели. `be` — строка в трёх
  справочниках (сервер, телефон, wt_admin). `GET /plans/languages` — прежняя форма для сборки (21); новый `GET /languages`
  → `{targets, natives}` с `code/endonym/flag`. `POST /plans`: родной — из профиля; цель или родной вне списков, одинаковые
  или испорченный родной → 422 `language_pair_invalid` до записи и до пейволла. `PATCH /profile` — алиас `PUT`; первый вход
  пишет родной по `Accept-Language` (первый тег из `planNatives`, только при создании профиля).
- [x] **§4 Пакеты** (п. 430) — `config/lesson/lang/{en,ru,uk,be,pl,ro,es,it,de,fr}.php`: каждый читаемый кодом ключ своей
  стороны заполнен, правило не к языку — no-op; новые ключи `common_words`, `talk_title_template`, `number_tens_joiners`;
  ядро судьи (п. 432) — отрицание списком слов, элизии fr/it, кавычки „…“ « … » и ¿¡.
- [x] **§4 Числа словами** (п. 431) — записи из нескольких слов, союз после десятков (es y, ro și, fr et); PHP и Dart;
  en/ru байт в байт (40 млн и 260 млн последовательностей — 0 расхождений).
- [x] **§5 Страж перевода по частым словам** (п. 433) — пары одной письменности: < 2 слов только родного и ≥ 2 только
  соседа — не перевод; 1 322 родные строки разведки — 0 ложных отказов.
- [x] **§6 Заголовок разговора** (п. 434) — родным без склонений нейтральный шаблон пакета («Rozmowa: recepcjonistka i
  lekarz»); ru/uk — склонение как было.
- [x] **§8 `lesson_day.v4.8`** (п. 435) — одна фраза FINAL INTERNAL VALIDATION: чтение буквами алфавита родного (на v4.7
  родные de/es/fr получали чтение кириллицей — 42–44 фатальных на день); откат — v4.7, файл v4.6 снесён.
- [x] **§9 Голоса по языку цели** (п. 436) — шесть слотов у каждой из 7 целей (`SPEECH_VOICE_<LANG>_<SLOT>` →
  `SPEECH_VOICE_EN_<SLOT>` → утверждённый); `language_code` цели в вызове ElevenLabs (`SPEECH_LANGUAGE_CODE`, по умолчанию
  `true`), ключ файла прежний (п. 248); проба — 12 из 12 образцов.
- [x] **Механические ложные срабатывания разведки** (п. 437; «по разведке — строчная после цифры не «форма», латинские
  двойники в кириллическом чтении чинятся кодом»); апостроф ʼ — буква общей письменности.
- [x] **§§10–13 Клиент** (п. 438; код и снимки, сборка не делалась) — списки языков с сервера (память + запас в бандле),
  22-2 — цели минус родной, без выбора родного (п. 180); родной — онбординг и профиль «Родной язык»; `Accept-Language`;
  распознавание pl/ro через сеть, be → ru-RU; канон на фикстуре pl→de; снимки 22-2 пересобраны.
- [x] **Часть D — живые дни на e2e** — ru→de (4 провала, 5-я сборка `ready`) и be→en: день 1 и разговор пройдены, дни не
  закрыты; pl→en — день 1 `failed` ×7 (FIX-3 §5, хвост ниже); голос с кодом языка — 88 строк дня и 38 реплик; «Размова:
  рэгістратар» живьём; стража перевода — 0 отказов в двух разговорах.
- [x] **Влитие и выкат** — *выкачено 26.09, 00:54–00:57 UTC* (отчёт LANG-1, § «Выкат»). Миграций нет; после влития `/plans/languages` на бою отдаст 7 целей
  (`PLAN_LANGUAGES` не задан), родной у всех 23 профилей боя — `ru`. Звук и фото части D перенесены из `storage` worktree в
  main до сноса (00:57).
- [ ] **Сборка телефона** с клиентской частью LANG-1 — на устройстве не проверялась.

**Хвосты LANG-1 (найдено, в работу НЕ бралось):**

- [x] **FIX-3 §5 `options.form_mismatch` роняет дни независимо от языка** — *закрыто LANG-1b §1* (пп. 439–440): «кусок» —
  предупреждение `options.partner_fragment`, одна автопересборка; на 53 днях ≥ 3 фат. карточек 74 % → 32 %. Было — решение архитектора. На 26 сохранённых днях ru→en
  (GEN-3, GEN-2b, CHECK-1) срабатывает в 24; ≥ 3 фатальных карточек (P2R чинит 2) — у 62 % (дни 1 — 69 %); разведка — 8 из
  14 `failed`; часть D — ru→de 4 провала подряд, pl→en 7 из 7. 22–29 % срабатываний «кусок реплики» ложные (время с числом,
  адрес, цена, имя: «Завтра в одиннадцать», «King Street 14», «Двести леев»). На бою после 22.09 собрано 2 дня — оба прошли.
  Предложение: освободить числа, время, имена и адреса в подпункте «кусок реплики» (как `check.verbatim`); «длина»
  пограничен на 1–3 буквы.
- [ ] **`exchange.second_question`** на закрывающей реплике собеседника ask-обмена — в 12 из 13 сборок v4.8 части D (13 находок;
  нет только у be→en, `docs/research/lang-1/live/builds.md`), почти в каждом дне съедает одну починку P2R (к правке промта урока; ср. «v4.6 — заметки» GEN-2b).
- [x] **Цели с родом (pl, ro, es, it, fr)** — *закрыто в части промта LANG-1b §5* (`lesson_day.v4.9`, п. 444); что осталось —
  «Спорное» п. 12 (обращение и род в РОДНЫХ переводах). Было: промт урока («LEARNER_GENDER affects only NATIVE_LANGUAGE», «role_gender never
  changes TARGET_LANGUAGE») исходит из английской цели: в переводах реплик регистратора ученик — мужского рода и на «ты»
  (pl «Jesteś zapisany», ro «Ești programat»).
- [ ] **L10N новых родных** — `NativeStrings` (маршрут, итоги, счётчики, причина судьи) знает ru/uk/en, `NotificationTexts` —
  ru/en: 7 новых родных видят английский. Спасательный набор — *закрыт LANG-1b §2* (п. 441: набор цели с переводом на
  каждый родной). Интерфейс телефона — карточка L10N-1.
- [ ] **22-2 длиннее канвы** — 7 карточек, уровень ниже сгиба; подпись канвы — «языков три, остальные за поиском».
- [ ] **Распознавание на телефоне** — pl/ro через сеть (п. 48) на устройстве не проверены; uk — модели на устройстве нет,
  надиктовка украинцем, вероятно, не работает давно.
- [x] **Родные de/es/fr на v4.8 живьём не проверены** — *проверено LANG-1b §8 на v4.9*: de — латиница ✓; **es — кириллица в
  обоих ответах** («Спорное» п. 11); fr не проверялся.
- [ ] **Код, найденный исполнителями языков:** `frame.native_agreement` (`LanguageWords::agreeingWithSlot`) после окна
  читает тот же список, что перед ним, — ложные предупреждения у языков с артиклем; `FrameJudge`: значение каркаса,
  открывающегося окном, забирает вводные слова; `NumberValues::GAP` — «las cinco y media» распадается на два значения;
  `SpeechMatch::foldAbbreviations` — аббревиатуры с пробелом внутри («p. m.», «z. B.») остаются двумя словами; порядковая
  точка немецкого «am 3. Mai» режет предложение (`SentenceEnds`); `LanguageWords::names` читает немецкие существительные
  именами; `CheckRules::aboutLearner` не узнаёт роль с суффиксальным артиклем румынского; ru `amount_pattern` «ден[ьи]» —
  «Добрый день» становится значением «поймай число»; `RoleLines::isEcho` читает `sentence_ends` без `has()` (пока у каждой
  цели пакет — не стреляет).
- [ ] **Телефон вне плана** — `generate_screen.dart` держит свою таблицу локалей распознавания без be и ro (падают в
  `en_US`); `store_view.dart` (`_TargetLangSheet`) предлагает целью каждую строку справочника, включая be.

## Доработки после LANG-1 — LANG-1b (2026-09-26): «кусок» — предупреждение и автопересборка, набор по паре, подсказка — строка урока, v4.9

Отчёт — `docs/research/lang-1b/README.md` (переигровка ворот на 53 днях — `replay/`, три живых дня §8 — `live/`); решения —
DECISIONS пп. **439–446**, «Спорное» пп. 6, 8 закрыты, 9 — в части набора, новые 11–12; контракт — `openapi/openapi.yaml`
(`rescue_kit[].audio_url`, `GET /plans/rescue-audio/{key}`, `line_native`), `docs/plan-api.md` («Для CLIENT-START»).
**Выкачено 26.09, 11:01–11:05 UTC** (ff `main` до `e93b9f95`, миграций нет, Horizon и scheduler перезапущены,
`plan:clean-text --apply` — 1 строка; сверка сборки (21) до/после — только ожидаемые поля). Деньги — **$0.3786 из $0.60** (модели,
§8); голос не покупался.

- [x] **§1 `options.form_mismatch` разделён** (п. 439) — «кусок» — предупреждение `options.partner_fragment` с исключениями
  чисел, времени, имён; длина и строчная — фатальные. 53 дня: `form_mismatch` 107 → 37 находок, ≥ 3 фат. карточек 74 % → 32 %
  (ru→en 62 % → 19 %), ложных фатальных «кусков» 0.
- [x] **§1 одна автопересборка** (п. 440) — урок, не прошедший ворота, ещё раз в том же job; `lesson.auto_rebuild`; job 1 680 с,
  `retry_after`/`build_stale_seconds` 1 740 с. Живьём: день 1 pl→en (7/7 failed) — ready с первой сборки.
- [x] **§2 спасательный набор по паре** (п. 441) — ключ пакета цели `rescue` (6 × 9 родных), звук `VoiceRescueKitJob` голосом
  ученика, файл на (цель, пол, голос, строка); `config plan.rescue_kit` снят.
- [x] **§3 подсказка — строка урока** (п. 442) — `hints.sentence` и новое `targets[].line_native`; `example_native` — значение окна
  (решение Дена); `plan.hint_assembled` в журнале сборки.
- [x] **§4 `vocab.definition_language`** (п. 443) — предупреждение; `TextLanguage` — одно чтение и для стража перевода.
- [x] **§5 `lesson_day.v4.9`** (п. 444) — род и обращение языка цели; v4.8 снят, откат — v4.7.
- [x] **§6 невидимые символы** (п. 445) — чистка на приёме ответа модели, `plan:clean-text`; на бою — U+0004 в названии дня.
- [x] **§7 голоса** (п. 446) — английские голоса пакета для всех семи целей (решение Дена).
- [x] **§8 три дня на e2e** — pl→en и de→en ready; es→en failed (кириллица, «tú»).
- [ ] **CLIENT-START** — 37-5 / 37-8d / 37-12 показывают `line_native` (склейку клиента снести); спасательный набор с
  `audio_url`; подпись «Сеть пропала» у дня с упавшим уроком — это не сеть; «Повторить». Сборка (22).

**Хвосты LANG-1b (найдено, в работу НЕ бралось):**

- [ ] **Чтение для родного es на v4.9 — кириллицей** («Спорное» п. 11): правило «Latin for the others» только в FINAL INTERNAL
  VALIDATION.
- [ ] **Обращение и род в родных переводах** («Спорное» п. 12): es «tú»; pl «pani» ученику неизвестного пола.
- [ ] **≤ 15 % провалов — только с автопересборкой**; следующий рычаг — `exchange.second_question` («Спорное» п. 7), длина вариантов.
- [ ] **Первый план боя с целью ro** (Ден, «Собеседование», ru→ro) — сборка (21) строже сервера на румынских числах (вопрос 2
  LANG-1) — до сборки (22).
- [ ] **`options.partner_fragment` для родного de почти молчит** — `LanguageWords::names` читает существительные именами.
- [ ] **Набор для планов, озвученных до LANG-1b**, `plan:speak-backfill` не докупает — купится при следующем принятом уроке.
- [ ] **Откат урока — v4.7** (по букве «v4.8 удалить») — без правила чтения v4.8.

## Аккаунт и доступ — ACC-1 (2026-09-25): удаление аккаунта, «один план, день 1 бесплатно», снос `has_conversation`

Отчёт — `docs/research/acc-1/README.md`; контракт — `docs/plan-api.md` (разделы «Доступ и пейволл», «С ACC-1») +
`openapi/openapi.yaml`, `openapi/openapi-admin.yaml`; канон — `docs/plan-v2.md` §11; решения — DECISIONS пп. **420–426**,
четыре записи в «Отменено». Работа — worktree `../backend2-acc1`, ветка `acc-1`, сайдкар `wt_acc1`; влито в `main`
fast-forward, **выкачено 25.09** (рубильник пейволла на бою выключен); worktree, ветка, сайдкар и его базы — снесены.

- [x] **§1 Удаление аккаунта** — `DELETE /auth/me` до последней строки и файла (файлы — после коммита); журнал запросов
  (и тела строк ученика), аудит админки, термины — без связи; `model_calls` не тронут; `account_deletions` (HMAC id, дата,
  число планов); повтор — 401 тем же токеном, 404 `account_not_found` в гонке. `POST /auth/logout` был.
- [x] **§2 Доступ** — `entitlements` + `access:grant` / `access:revoke` + `GET /auth/me` → `access` + `GET /admin/api/users/{id}/access`;
  пейволл «один план, день 1 бесплатно» (`lock_reason: subscription`, 402 `plan_subscription_required`, 409
  `plan_active_limit`) за рубильником `access.paywall_enabled` — **на бою выключен**.
- [x] **§3 Снос `plan_days.has_conversation` и `PLAN_CONVERSATION_ENABLED`** — шестой этап у каждого дня; пропуск — строка
  прохождения без разговора (дни на пяти этапах получили его миграцией).
- [x] **§4 Изоляция тестов** — серийный прогон всего сьюта зелёный (2 549 passed): пять файлов без `RefreshDatabase`
  получили его (три — Plan, два — Generation: строки журналов); озвучка и фото на тестовом диске; лог, шаблоны и выгрузки
  сьюта вне дерева; стражи `TestDiskGuardTest` и `DatabaseIsolationGuardTest`.
- [x] **§5 `hints.native` снят.**
- [x] **§6 `conversation_agent.v3.4`** — прощание сцены принимает предложенное.
- [x] **Выкат** (25.09, 16:30–16:34 UTC) — бэкап боя `wordtrainer-20260925-193027.sql.gz` и e2e; две новые таблицы кодом
  ветки; ff `main` (`d4707480`); `.env` боя (`ACCESS_PAYWALL_ENABLED=false`, блок `PLAN_CONVERSATION_ENABLED` снят);
  `restart horizon scheduler`; снос колонки (10 дней — пропуск шестого этапа); e2e и `wordtrainer_test` догнаны;
  `access:grant lifetime` всем 24 пользователям боя; админка пересобрана; `stamp-build`. Сверка «до/после» глазами
  телефона: вне `lock_reason`/`access` — только штамп сборки.
- [x] **Живая репетиция v3.4 на e2e** — ход 18: «Yes, please tell me the highest reading. Thank you, and please come back if
  he gets worse.» — принимает; $0.013001.
- [ ] **PAY-1** — покупки (RevenueCat → `entitlements`, источники `apple` / `google`) и сборка урока по факту оплаты (тело
  `NextDayAccess`): при включённом рубильнике урок дня 2 бесплатного плана до PAY-1 собирается и ждёт подписки. Там же —
  свести `profiles.tier` (стор, лимит генераций, практика) с `entitlements`.
- [ ] **Клиентский наряд с пейволлом** — `lock_reason: subscription` → экран подписки (кадр 37-14), 402/409 на «новый
  план», `access` из `/auth/me`; после него — включить рубильник на бою (`ACCESS_PAYWALL_ENABLED=true` + `restart horizon
  scheduler`).

**Хвосты ACC-1 (найдено, в работу НЕ бралось):**

- [ ] **Исходящие строки журнала запросов** (вызовы вендоров с целью плана и речью ученика в теле) пользователя не
  называют и при удалении аккаунта не чистятся — их берёт будущая ротация журнала (`api_request_logs` retention).
- [ ] **Мусор тестов в `storage` основного дерева** — до ACC-1 хук ворот гонял сьют в `wt_app`, и тесты писали фейковые
  mp3 в `storage/app/private/plan-audio` рядом со звуком боя (счёт — отчёт ACC-1 §4); чистить только сверкой путей с
  базами боя и e2e.
- [ ] **Страница плана в админке** читает статус дня без пейволла (`OverviewReport::statusOf`) — при выключенном рубильнике
  одно и то же; сделать с ADM-2.
- [ ] **Письмо `day_ready`** («День 2 собран — можно начинать») при включённом пейволле уйдёт и ученику без подписки —
  решить с клиентским нарядом пейволла (письмо-приглашение или молчание).
- [ ] **Ключи Redis судьи окна** `plan:slot_judge:{user}:{дата}` при удалении аккаунта не снимаются — истекают к полуночи
  ученика сами; снимать ли — решить, если журнал Redis станет предметом удаления.
- [ ] **Прощание v3.4 просит то, чего уже не услышит** (репетиция ACC-1, ход 18: «Yes, please tell me the highest
  reading.» — и сцена закрыта). Буква v3.4 соблюдена; если нужно «принять и закрыть» без встречной просьбы — уточнение
  фразы (v3.5), решение архитектора.

## Хвосты сервера после CLIENT-FIX-4 — FIX-4c (2026-09-25): второй голос собеседника, hints всегда, род роли, заголовок сцен, страж перевода

Отчёт — `docs/research/fix-4c/README.md`; канон — `docs/plan-v2.md` §11 («Правило роли v3.3», «Голос роли — голос сцены»);
контракт — `docs/plan-api.md` (раздел «С FIX-4c») + `openapi/openapi.yaml`; решения — DECISIONS пп. **414–419**, четыре
записи в «Отменено». Работа — worktree `../backend2-fix4c`, ветка `fix-4c`; влито в `main` fast-forward, **выкачено
25.09** (миграция `partner_voice_id` с бэкфиллом). Покупки — $0.057935 из $0.40.

- [x] **§1 Второй голос собеседника на пол** — `SPEECH_VOICE_EN_PARTNER_FEMALE_2` / `_MALE_2` (Maisie, Caleb — выбор Дена
  по образцам 25.09); голос сцены `plan_scenes.partner_voice_id` — один раз при приёме урока, чередование сцен одного
  пола по порядку плана; бэкфилл: озвученным — голос 1, неозвученным — по правилу.
- [x] **§2 `hints` в обоих режимах** — `enabled` остаётся флагом режима.
- [x] **§3 `window.sources[].partner_gender`**.
- [x] **§4 `talk_title_native` по всем ролям** — «Поговори с регистратором и врачом».
- [x] **§5 «Сказал сам» репетиции** — правило верно (свои карточки речи дня), «1 из 9» — находка стенда.
- [x] **§6 Страж перевода** (`native_missing`, второй провал — без перевода) + `conversation_agent.v3.3`.
- [x] **Ворота и выкат 25.09** — `composer check` (Pest 2 515, PHPStan 0, deptrac 0), `flutter analyze` чисто,
  invariant-reviewer CLEAN → `db-backup --safety` → миграция боя кодом ветки (16 озвученных сцен → голос 1, 3 без урока)
  → ff `main` → вторые голоса в `.env` → `restart horizon` → e2e и `wordtrainer_test` догнаны → стенд снесён → `stamp-build`.
- [x] **Живая репетиция на e2e** (`01M3CF7Z9J…`, «Без подсказок») — регистратор F1 6/6, врач F2 4/4; `hints` при
  `enabled=false` во всех 8 документах и по GET; `partner_gender` у обеих сцен; «Поговори с регистратором и врачом»;
  перевод 10/10, `native_missing` 0; $0.0339.
- [ ] **Телефону** — `partner_gender` для «начнёт первым / первой»; `hints` сервера в «Без подсказок» вместо своего
  подбора цели. (`hints.native` на сервере снят нарядом ACC-1 §5.)

**Хвосты FIX-4c (найдено, в работу НЕ бралось):**

- [ ] **Статические фикстуры** `docs/fixtures/day-rehearsal.json`, `day-review.json`, `conversation-*.json` — снимки
  прошлых нарядов, тесты сервера их не держат: без `partner_gender`, заголовок репетиции старый. Освежить с клиентским
  нарядом, который начнёт читать новые поля.
- [ ] **Грамматика перевода роли** («Что у него с спиной?», ход 1 репетиции) — страж проверяет язык, не грамматику.
- [x] **Прощание по закрытию сцены отклоняет предложение ученика** («Should I tell you his temperature?» → «No, that's
  okay. We can finish here.», ход 18) — *закрыто ACC-1 §6*: `conversation_agent.v3.4`.
- [x] **Тесты голоса разговора пишут фейковые mp3 в настоящий `storage`** — *закрыто ACC-1 §4*: тестовый диск во всём
  Feature-сьюте, страж `TestDiskGuardTest`.

## Хвосты сервера разговора — FIX-4b (2026-09-24): союзы в судье, `hints.sentence`, промпт роли v3.2

Отчёт — `docs/research/fix-4b/README.md`; канон — `docs/plan-v2.md` §11 («Правила роли v3.2»); контракт —
`docs/plan-api.md` (`hints.sentence`, раздел «Для CLIENT-FIX-4») + `openapi/openapi.yaml`; решения — DECISIONS пп.
**410–413**, четыре записи в «Отменено», «Спорное» пп. 3–5 закрыты. Работа — worktree `../backend2-fix4b`, ветка `fix-4b`;
влито в `main` fast-forward, **выкачено 24.09** (миграций нет).

- [x] **§1 Союз начинает клаузу** — каркас засчитывается и сразу после and / but / so / then / or (`clause_starters`);
  окно первой конструкции кончается перед союзом второй.
- [x] **§2 `hints.sentence`** — целая родная фраза урока, с заглавной и знаком; `hints.native` — до CLIENT-FIX-4.
- [x] **§3 `conversation_agent.v3.2`** — правда ученика, своя компетенция, известное не переспрашивать, новая роль не
  повторяет прежнюю; `phrases_used`/`checkpoint_done` сняты из ответа.
- [x] **Приёмка Б** (3 разговора 2DX8QC, без модели) — 37 из 37 как в FIX-4 · **В** (живая репетиция на e2e) — четыре
  ожидания держат, отбраковок 0, $0.0376.
- [x] **Ворота и выкат 24.09** — `composer check` (Pest 2 501, PHPStan 0, deptrac 0), `flutter analyze` чисто,
  invariant-reviewer CLEAN → `db-backup --safety` → ff `main` → `restart horizon` → бой читает v3.2 → стенд снесён →
  `stamp-build`.
- [x] **CLIENT-FIX-4** — чип подсказки: `hints.sentence` без рамки «Скажи, что …» (сборка (21), 25.09); снять `hints.native`
  на сервере — ещё нет (FIX-4c его не трогал).

**Хвосты FIX-4b (найдено, в работу НЕ бралось):**

- [ ] **Регистратор ставит диагноз** («It sounds like a muscle strain», ход 9 живой репетиции) — заготовка e2e-сцены
  «Booking» отдаёт регистратору визит врача; YOUR JOB v3.2 снял лечение, но не диагноз — наряд промпта или данных стенда.
- [ ] **Цель-вопрос роль задаёт сама** (ход 16: «What is the highest temperature you've measured?» перед «Should I tell you
  his temperature?») — правило v3 «never ask it yourself» на `mini` держит не всегда.

## Сервер разговора — FIX-4 (2026-09-24): судья каркасов, граница сцен, подсказки, «Вспомнить»

Отчёт — `docs/research/fix-4/README.md`; канон — `docs/plan-v2.md` §§1, 6, 11; контракт — `docs/plan-api.md` (раздел «Для
CLIENT-FIX-4: что показывать») + `openapi/openapi.yaml`; решения — DECISIONS пп. **404–409**, семь записей в «Отменено»,
три в «Спорном». Работа — worktree `../backend2-fix4`, ветка `fix-4`; влито в `main` fast-forward, **выкачено 24.09**.

- [x] **§1 «Вспомнить» звучит своей сценой** — файл ищется по сцене узла (`CardViews`); тест канона «текст записи = текст
  строки»; ADM-1 «звук ≠ текст» по всем планам боя — число в отчёте.
- [x] **§2 Судья каркасов — связная фраза** (`FrameJudge`): сказано / почти / нет, только текущая сцена, «ещё
  вспомнил»; отрицание — та же конструкция (решение владельца 24.09); `PhraseUse` удалён.
- [x] **§3 Цели роли — `T1…T7` своей сцены**; сервер проверяет каждое открытие, отброшенное — в журнал.
- [x] **§4 Граница сцен — сервер**: бюджет целей + 1, прощание (`end`) и приветствие новой роли (`start`, свой голос),
  `ended_by_limit`; промпт `conversation_agent.v3.1`.
- [x] **§5 Подсказка — целая фраза урока**; после «почти» — точная строка (`hints.target`, `ref`).
- [x] **§6 Хвосты**: «p.m..» — правило + `plan:rebuild-card-texts`; `plan_scenes.built_at` (конец сборки) в «Конвейере»;
  журнал отбраковок `conversation_rejections` (id вызова `model_calls`); голос по полу роли сцены реплики.
- [x] **§7 Контракт — только добавления** (сборка (20) работает); админка «Разговоры» — почти, отбраковки, «лимит».
- [x] **Приёмка А** (без модели, 3 разговора 2DX8QC) · **Б** (живая репетиция на e2e, $0.039) — отчёт §§3–4.
- [x] **Выкат 24.09** — `db-backup --safety` → `migrate` боя кодом ветки → ff `main` (rebase не понадобился) →
  `restart horizon` → `plan:rebuild-card-texts --apply` (6 карточек / 7 строк, повтор 0) → приёмка В (ADM-1 «звук ≠
  текст» по всем 9 планам боя = **0**) → админка пересобрана → `wordtrainer_test` догнан → стенд ветки снесён → `stamp-build`.
- [x] **CLIENT-FIX-4 — телефон**: почти, «ещё вспомнил», граница сцен, точная строка подсказки — сборка (21), 25.09.

**Хвосты FIX-4 (найдено, в работу НЕ бралось):**

- [x] **Каркас в середине предложения** не засчитывается (DECISIONS «Спорное» п. 3) — FIX-4b §1: союз начинает клаузу (п. 410).
- [x] **Роль на `mini`** спорит со своим значением ученика, даёт совет не своей роли, переспрашивает сказанное (живая
  репетиция FIX-4) — FIX-4b §3: `conversation_agent.v3.2` (п. 413).
- [x] **Подсказка для цели-вопроса** читается криво в рамке «Скажи, что …» — FIX-4b §2: `hints.sentence` без рамки
  (п. 412); клиенту — CLIENT-FIX-4.

## Восемь находок зала — FIX-3 (2026-09-22): окно 1 — сервер, окно 2 — телефон

Отчёт — `docs/research/fix-3/README.md`; канон — `docs/plan-v2.md` §§4, 6, 11; контракт — `docs/plan-api.md` +
`openapi/openapi.yaml`; клиенту — `docs/session-handoff.md` §9; решения — DECISIONS пп. **389–400**, восемь записей в
«Отменено». Работа — worktree `../backend2-fix3`, ветка `fix-3`; в `main` не влито.

- [x] **§1 Голос ученика — пол профиля**, собеседник — пол роли; `plan:revoice-learner` (dry по умолчанию; зал Дена —
  37 строк, $0.05, не куплено); родовые строки сервера про ученика — по профилю.
- [x] **§2 Прейскурант измерен на телефоне** (медиана × 1,3), у плана снимок `plans.pace`, `plan:repace --all`; пауза 120 с.
- [x] **§3 Лестница не режет круги**; p5 зала — один круг из-за скрытых судьёй швов значений → добор с глоссой.
- [x] **Потолок «Фраз» 690 → 900 с** (приёмка окна 1, 22.09; DECISIONS п. 401): на честных ценах урок v4.7 дороже
  прежнего потолка на 30–170 с, и сигнал горел бы каждый день. После правки: зал — 840 и 885 с, «врач» e2e — 870 с,
  фикстуры — 695 / 820 с, сигнал молчит везде; потолок карточек дня (32 мин) не менялся.
- [x] **§4 Числа — цифрами с обеих сторон**, составные складываются, `number_joiners`.
- [x] **§5 Варианты обмена — только свои**; `options.form_mismatch` — фатальный.
- [x] **§6 Цель — конструкция** `{…frame…, value_target}`; `PhraseUse` переписан; зал: день 1 — 2, день 2 — 2.
- [x] **§7 Разговор v3**: ходов — цели + 2, минуты 5 / 6 / 4 — жёсткий стоп, `LEAD_TO`/`opens`, подсказка по двери,
  стражи повтора своей реплики и раннего конца, обрывок ≠ «не понял»; живьём — 6 разговоров, $0.085.
- [x] **§8 `stages[].again`**; `talk_again` и `allowed_action = again` сняты.
- [x] **§9 `source`/`scene` у единиц программы**; `summary.returns` — сколько вернулось.
- [x] **§10 `stages[].summary`** — итог этапа 30-6 сервером.
- [x] **§11 Эхо — против всех ходов**; `task_clause_native`; `partner_line` эха снят миграцией данных.
- [x] **e2e на ветке**: бэкап → `migrate` кодом ветки (снимок прейскуранта, `opens_target`, `partner_line` — 1) →
  `plan:repace --all` (63 плана) → сайдкар `wt_app_e2e` (:8010) на worktree.
- [ ] **Приёмка архитектора окна 1**, коммит «canvas: серия 38 принята» — до них окно 2 (телефон) НЕ начинается.
- [ ] **Выкат — по команде Дена после сборки (20)**: `db-backup --safety` → rebase на `main` → `migrate` (снимок
  прейскуранта, `opens_target`, снятие `partner_line` у 10 карточек) → ff `main` → `restart horizon` → `plan:repace --all`
  → сайдкар e2e обратно на `main` → worktree снести → `wordtrainer_test` догнать.
- [ ] **Окно 2 — телефон** (после приёмки окна 1).

**Хвосты FIX-3 (найдено, в работу НЕ бралось):**

- [ ] **Роль на `mini` следует приготовленному визиту**: переспрашивает сказанное, отвечает приготовленным значением
  («window seat» на «a seat near the front»), после перезапроса эха — вопросом, на который уже ответили. Промпт v3 и
  стражи сервера это уменьшили, но не сняли (отчёт §7). Кандидат: модель сильнее или визит без приготовленных ответов.
- [ ] **`speak_echo` — 70 с по n = 7** — цена с малой выборкой; пересчитать `tools/prices.py`, когда ответов станет больше.

## Клиент: репетиция, повторение, правки прохода 21.09 — CLIENT-CONV-1b (2026-09-21, окно 1 из 2)

Отчёт — `docs/research/client-conv-1b/README.md` (34 снимка `shots/`, живой проход `live/`).

- [x] **Окно повторения и репетиции** (37-1, 37-2): плита «системного» дня, строка состояния, «Из каких дней / сцен».
- [x] **«Вспомнить»**: вид `recall_scenes` и обзор 37-3, пересказы 35-4 под `recall`, итог 37-4; полоса сцены идёт за
  карточкой; единица этапа — обмен своей сцены.
- [x] **Разговор целиком**: вход без числа сцен, полоса — сцена разговора, итог 37-12b по сценам.
- [x] **Правки прохода 21.09** (а)–(ж): плитка молчит до «Проверить», блок «Что тебе сказали?», чип сразу после промаха,
  тишина по длине (1 с / до 2 с), «услышал: …», тап по значению 32-1, текст роли открыт.
- [x] **Мелочи §1.8 отчёта 1a** — см. пункт выше.
- [x] **Сборка 1.0.0 (18)** — снимки приняты 21.09; Runner.app 23:08:55, на телефоне 1.0.0 (18) (отчёт §7).

**Хвосты CLIENT-CONV-1b (найдено, в работу НЕ бралось):**

- [x] **Список сцен / дней у окна повторения и репетиции** — у `window` его нет, клиент читает из карточек или по
  правилу раздачи; «N карточек» 37-2 не приходит. Дать `window.sources[]` (отчёт §5 п. 1). *Дано BACK-TAILS-2 §4*
  (DECISIONS п. 382).
- [x] **Минуты у запертых рядов** — кадры 37-1/37-2 печатают «около N минут» у каждого ряда, `minutes_left` — только у
  текущего (§5 п. 2). *`stages[].minutes` — BACK-TAILS-2 §4.*
- [x] **Одно имя у сцены**: обзор `recall_scenes` — «У врача с сыном», план — «Приём у врача» (§5 п. 3). *BACK-TAILS-2
  §6* (DECISIONS п. 384), розданные листы — `plan:reconcile-scenes`.
- [x] **`minutes_spent` дня до закрытия без разговора**: 30-7 — «6 минут», закрытый день — 9 (§5 п. 4). *BACK-TAILS-2
  §8* (DECISIONS п. 386).
- [x] **У пройденной репетиции нет действия** — кадр рисует «Повторить разговор» (§5 п. 5). *`window.talk_again` и
  повтор на пройденном дне — BACK-TAILS-2 §7* (DECISIONS п. 385).
- [ ] **Окно 1c (клиент)** — всё из CONV-2 §8: `targets[]`, `talk_title_native`, `scenes_count`, `replay`, «Sorry?»
  пузырём, «услышал» из ответа судьи, `speak_echo.own_line`, `phrase_intro.usage`, пересъёмка фикстур разговора.

## Сессия дня — SESSION-1a (2026-09-16): 28 тренажёров, судья окна, контракт карточек

Отчёт — `docs/research/session-1a/README.md`; кадры — `docs/session-map.md` (канва
`docs/design/session-canvas.dc.html`, 47 кадров); контракт — `docs/plan-api.md` «Карточки сессии» +
`openapi/openapi.yaml`; решения — DECISIONS пп. 325, 326.

- [x] **Реестр видов** — `CardKind`: 29 значений, раздаваемых 28 (слова 6, фразы 9, диалог 4, слушание 6, речь 3),
  `listen_pairs` зарезервирован и не раздаётся (в уроке нет двух похожих реплик; источник — v4.6). Вид принадлежит
  ровно одному способу зачёта — выбор/сборка (клиент, без сети), голос (покрытие), судья (сервер), проход; что клиент
  вправе прислать на вид, решает код (`CardKind::allows`), а не договорённость.
- [x] **Сборка дня написана заново** (`Domain/Assembly`) — три карточки на слово и на каркас с разнесением
  A_i / B_{i−1} / C_{i−2}, проверки и узнавания по seeded-ротациям, одна `phrase_combine` на день, диалог по порядку
  визита (ask — ученик первым, спасатель одной карточкой), слушание лентой → вопросы → разбор → предсказания → темп →
  число, речь ≤ 8; перемешивания и ротации сидятся адресом карточки — повторная сборка даёт тот же день.
- [x] **Снос 13 видов прошлой раздачи** без периода совместимости, без флагов и папок legacy: виды, их сборщики,
  `DayPace` по этапам, их тесты и фикстуры дня. `day_cards` снесены на всех базах после бэкапа (одна миграция
  `2026_09_16_100000`: `response jsonb`, CHECK на 29 видов и на четыре рода единицы); дни раздаются заново при
  открытии, статусы дней, их даты и метрики целы.
- [x] **Единица `day`** — у ленты слушания своей единицы нет: `unit.kind = day` никогда не возвращается, в программу
  окна не попадает и пропускается `UnitStates`.
- [x] **Судья окна `slot_judge.v1`** — `POST …/cards/{card}/judge` для трёх видов, судимых по смыслу: один вызов
  ≤ 8 с вне транзакции, кап 60 на ученика в сутки (Redis, TTL до полуночи его зоны), деградация в код с
  `+1 judge.unavailable`; промт — файл, принят байт-в-байт (DECISIONS п. 326).
- [x] **`DayPace` — секунды на ВИД карточки** (`config/plan.php` → `pace`, 28 строк): по нему считаются минуты окна
  и итог этапа; значения начальные, крутятся в конфиге после телефона, не в коде.
- [x] **Контракт аддитивно** — конверт карточки (`unit`, `source_day`, `response` рядом со старыми ключами),
  `stages[].cards[]` в `GET …/days/{n}`, ответ POST результата (`{card, requeued, unit, day, stage}`) и `…/judge`;
  `docs/plan-api.md` + `openapi/openapi.yaml`.
- [x] **Фикстуры клиента** — `docs/fixtures/day-doctor.json` (intermediate) и `day-doctor-beginner.json`: полный
  раздатый день из чистого урока `FakePlanModel` — 75 карточек (слова 24, фразы 19, диалог 15, слушание 9, речь 8),
  окно ≈ 25 мин; тест держит их байт-в-байт. **Это вход наряда SESSION-1b.**
- [x] **Звук** — `duration_ms` у каждого звучащего элемента дня; колонка `plan_line_audios.duration_ms` есть с первой
  миграции плана и заполняется оценкой по байтам (`SpeechCost::mp3DurationMs`, CBR 128 кбит/с) — настоящего декодера
  нет, ни ffprobe, ни getID3 в образах не появилось; бэкфилл — `plan:audio-durations` (идемпотентна, ничего не
  покупает, печатает «было / стало»).
- [x] **Живой прогон** — день 1 плана «врач» на e2e (урок `lesson_day.v4.4`: сцен v4.5 на e2e нет, GEN-2b не писал дни
  в сцены) → `docs/research/session-1a/e2e-day-doctor.json`; смоук судьи по сценариям
  `docs/research/session-1a/tools/judge-scenarios.json` → `judge-smoke.jsonl` (пути кода, модели и `unavailable`,
  кап; вход, вердикт, латентность и цена каждого — таблицей в отчёте).

**Хвосты SESSION-1a (найдено, в работу НЕ бралось) — каждый пункт самостоятельная задача:**

- [x] **Два правила покрытия речи в двух модулях** — **закрыто 20.09 нарядом FIX-2 п. 2** (DECISIONS п. 350): обе
  реализации снесены, осталась одна — `Shared/Domain/Service/SpeechMatch` (в ядро переехали и `SpokenWordBoundary` с
  `SpokenSuffixTolerance`). У правила два режима, и какой из них — говорит сама карточка полем `speech_mode` вместо
  числа `coverage_min`; списки языка едут телефону раз на день блоком `speech`. Learning считает свои пороги по тому
  же правилу.
- [x] **«Фразы» дороже 600 с — стоп-условие состава сработало** — **закрыто 20.09 доработкой наряда FIX-2**
  (DECISIONS п. 354; отчёт `docs/research/fix-2/README.md` §1.5а). У этапа теперь **свой потолок — 690 с**
  (`plan.phrases_budget`, `PLAN_PHRASES_BUDGET`; потолок дня 32 мин не менялся) и одна лестница урезания: третье
  узнавание → третий круг «Скажи целиком» → второй круг, у каркасов с наименьшим числом реплик в диалоге. Цена
  `plan.pace.phrase_other_slot` стала ценой ОДНОГО круга (25), и карточка говорит, сколько их у неё. Нижняя граница —
  «2 узнавания + 1 круг + своё»: круг «со своим словом» лестница не снимает никогда, тренажёр целиком тоже; если этап
  не влез и с потраченной лестницей, день собирается, а превышение остаётся сигналом.
  Ручки рядом: `plan.phrases_budget`, `plan.pace.phrase_other_slot`, `PhrasesStage::RECOGNITIONS`,
  `PhraseCards::BEGINNER_ROUNDS`.
- [x] **Лишняя точка в реплике модели** — закрыто нарядом CONV-1 (п. 7): `FrameText::withoutDoubledStop` снимает
  ВТОРУЮ точку на входе разбора урока (`LessonParser`) — у реплики, у каркаса обоих языков и у родного наполнения;
  «I can come at 3 p.m..» читается как «I can come at 3 p.m.» всеми, кто читает урок. Ровно две: три и больше —
  многоточие, а «Well...» не слип. Тест канона — `tests/Unit/Plan/LessonNativeMarksTest.php`.
- [x] **Включить разговор вместе с клиентом, потом снести колонку и рубильник.** *Снесены нарядом ACC-1 §3 (25.09).* На бою стоял
  `PLAN_CONVERSATION_ENABLED=false` (хвост CONV-1): сервер шестой этап умеет, клиент ещё нет, и день
  раздаётся на пяти — тем же путём, что дни, розданные до наряда. Порядок:
  1. сдан клиентский наряд с разговором → `PLAN_CONVERSATION_ENABLED=true` в `.env` + `docker compose restart horizon`
     (воркер держит конфиг в памяти). *Клиент сдан 21.09* (CLIENT-CONV-1a: снимки приняты, сборка 1.0.0 (17)).
     Наряд рубильник не трогал, но на бою он **уже `true`**: `.env` правлен руками 21.09 в 00:25, комментарий над
     строкой всё ещё говорит «Выключен», `config:show` живого `wt_app` — `enabled true`, horizon поднят после правки.
     **Подтверждено нарядом CONV-2** («сейчас на бою разговор включён, так и оставить»): шаг 1 сделан; кэша конфига на
     бою нет, процедура включения и выключения — отчёт CONV-2 §7;
  2. когда закроются ВСЕ дни, розданные без разговора (сейчас это аккаунт Дена и QA-аккаунты), снести
     рубильник (`plan.conversation.enabled`, `ConversationRules::ENABLED`, параметр `dealsTalk`) и вместе с ним
     временную колонку `plan_days.has_conversation` с тремя её читателями — `DayStages::walksConversation`,
     `CloseDayHandler`, `CloseStageHandler`, — и шестой этап станет просто составом дня.
  Проверка «не осталось ли»: `plan_days` с `has_conversation = false` и статусом не `closed`.
  *Проверено BACK-TAILS-2 §11 (22.09, бой, только чтение):* таких дней в работе — **4**, все `in_progress` на
  УДАЛЁННЫХ планах (`01M2HV228M3NVK8DVDNJ5R0EFA`, `01M2N5VGKY3Z5WF9CNZ2FTKJT4`, `01M2NPRKVGA7DCNA4KVSHB8A6Y`,
  `01M2TJ8595N2T9HDQ8FX93QWPF`), плюс 16 запертых дней, которые при раздаче получат разговор. Условие наряда не выполнено —
  колонка и рубильник стоят. Решено 22.09 — см. строку «§11» в разделе BACK-TAILS-2.
- [ ] **Хвосты контракта разговора, найденные клиентом** (наряд CLIENT-CONV-1a, отчёт §5) — **пп. 1–12 закрыты
  нарядом CONV-2** (отчёт `docs/research/conv-2/README.md` §1, DECISIONS пп. 366–375; что клиенту подхватить — там же
  §8); открыты 13 и 14 — в CONV-2 не входили. Запись о паузе 1,5 с — DECISIONS п. 376. Исходный список:
  1. `phrases_used` называет фразу ссылкой без текста и границ — подчерк шалфея в своём пузыре (37-8)
     рисуется только для фраз, чей текст есть у дня; у репетиции чужие сцены дню не принадлежат.
     Дать `text_target` (лучше `span`) прямо в `phrases_used`.
  2. `window.highlights` заполняется только у ПРОЙДЕННОГО дня, а 30-7 показывается, пока день ещё
     открыт: на первом проходе блока «Что было хорошо» нет. Считать, когда пройдены все этапы.
  3. Роль приходит только в именительном — заголовок 37-5 печатается «Поговори с собеседником»
     вместо «Поговори с врачом». Дать готовую строку, как у `highlights`.
  4. Число сцен разговора известно только ИЗ документа разговора — на входе репетиции (37-5) его нет.
     Дать в ряду этапа окна.
  5. `phrase_intro` не несёт `usage` — блок «В разговоре» (32-1) собирается сверкой текста с диалогом
     дня. Дать `usage`, как у слова.
  6. `PlanWindowStage.stage` в OpenAPI всё ещё `[words, phrases, dialogue, listen, speak]`, а сервер
     шлёт ещё `recall` и `conversation`: схема врёт про свой же ответ.
  7. `hints.native` приходит целым предложением («У моего сына температура.»), а не придаточным, как
     обещает контракт — клиент правит регистр и точку сам (`TalkTexts.clause`). Слать придаточным.
  8. У переспроса нет слов: `rescue` с пустым `text_target`, а кадр 37-7 рисует «Sorry?» тёмным
     пузырём. Клиент ставит пометку «переспросил». Строка переспроса в ходе — или правка канвы.
  9. `summary.minutes` — стенные часы от старта до конца: «Разговор окончен · 323 минуты», и
     `minutes_spent` дня стал 436. Минуты по ходам с отсечкой простоя.
  10. Роль говорит за ученика: врач спросил «How often should I give the paracetamol?» (фраза p6
      родителя). Промпт роли.
  11. «Он повторит проще» (правило 37-5) не выполняется: после «Не понял» роль повторяет реплику слово
      в слово. Промпт — или правило.
  12. «Ещё раз» распроходит этап: после нового разговора ряд «Разговор» снова «идёт», и день не
      закрыть, пока второй не доведён. Этап пройден, если есть хоть один законченный разговор дня.
  13. Сверка фраз строже человека: «Yes, he has a sore throat.» (дважды) не засчитано за «He also has
      a sore throat.» → «не прозвучало — вернётся завтра».
  14. Контент: `text_native` фразы p2 дня «врач» — «Это у него уже уже три дня.»
  Все четырнадцать — в бэкенд-наряд (решение архитектора при сдаче снимков: клиенту с ними ничего
  не делать). Туда же — запись в DECISIONS о паузе 1,5 с в разговоре (принята архитектором как
  исключение, отчёт client-conv-1a §5).
- [x] **Расхождения с кадрами, замеченные при приёмке снимков CLIENT-CONV-1a и не тронутые** — список в отчёте
  `docs/research/client-conv-1a/README.md` §1.8 (значки правил 37-5 и строка переспроса, «слушай» над погашенной
  кнопкой 37-8, галка у «Понял все вопросы» 37-12, «прослушать» у реплики в «В разговоре» 32-1, «Пропустить» латунью
  и прежний лист у остальных 32-x, пузырь своей реплики 34-5) плюс тень чипа подсказки из §1.7. **Вход наряда 1b**
  (решение архитектора 21.09); в 1a по ним ничего не делалось. *Закрыто CLIENT-CONV-1b* (отчёт §1.5) — кроме строки
  переспроса «Sorry?» (окно 1c) и «прослушать» у реплики в «В разговоре» 32-1 (кадр его не рисует; в список наряда 1b
  не входил).
- [ ] **Наряд L10N-1: интерфейс на uk и ro** (~2 400 ключей). Языки ПАРЫ работают полностью
  (распознавание на языке цели, намерения и подсказки — готовыми строками сервера на родном), но сам
  интерфейс живёт на ru и en (`kSupportedLocales`); `app_uk.arb` / `app_ro.arb` с половиной ключей
  решено не заводить (решение архитектора при сдаче CLIENT-CONV-1a).
- [x] **Разговор дня ПОВТОРЕНИЯ живьём не проверялся** (наряд CONV-1): он собирается и раздаётся —
  сцены двух повторяемых дней, 4 хода, — но живая проверка наряда прошла день-сцену и репетицию
  (отчёт §4). Стоит прогнать на e2e одним разговором, когда на стенде окажется план с днём
  повторения и двумя готовыми сценами. *Прогнан CLIENT-CONV-1b* на e2e через клиент (4 хода, $0.023, отчёт §4) — на
  роли v1, до прихода CONV-2; с v2 — хвост CONV-2 ниже.
- [x] **Хук ворот не перехватывает `git -C … commit`** — закрыто BACK-TAILS-1 §2.4 (подкоманда после опций `git`);
  цепочки (`git add … && git commit …`), подоболочки и `bash -c '…'` — закрыты нарядом CONV-2 п. 13 (DECISIONS п. 377).
- [ ] **`docs/research/plan-gen/tools/live-run.php` говорит на старом реестре** — харнесс живых прогонов зовёт снятые
  виды по именам и шлёт `passed` на всё подряд; на следующем живом прогоне он сломается. В SESSION-1a не трогали
  (исторический инструмент наряда PLAN-GEN).
- [x] **`listen_predict` повторяет варианты `dialogue_partner` того же ask-обмена** (канва 34-5) — закрыто хвостом
  SESSION-1a (D-19 отменён): варианты предсказания — перевод ответа собеседника этого обмена и переводы двух других его
  реплик; `check` обмена остаётся только у `dialogue_partner`.
- [x] **Единица, провалившаяся дважды, возвращается дважды, когда между днями-сценами стоит день повторения** —
  закрыто хвостом SESSION-1a: возврат ровно один раз, на ближайший следующий день любого типа; день повторения берёт
  ещё единицы двух предыдущих сцен, которые никуда не возвращались (карточки `source = returned` с этим днём провала),
  сцена после него их не берёт; карточка-возврат единицу снова не помечает. Тест цепочки сцена → сцена → повторение →
  сцена (`SessionReturnsTest`).
- [x] **Копия проваленной карточки слушания встаёт после `listen_review`, который уже показал ответ** (D-06) —
  закрыто хвостом SESSION-1a: этап «Слушаю и отвечаю» копий не раздаёт (`CardKind::requeues`), неверный
  `listen_question` / `listen_predict` / `listen_number` — окончательный `failed`.
- [ ] **Закрытый день после сноса `day_cards` остаётся без карточек**: раздача происходит только при открытии дня,
  поэтому дни со статусом `closed` (например, `qa-dayui3`, день 1, 75/75) остались с метриками и без единой карточки —
  `stages[].cards[]` пуст. Решить: пере-раздача закрытого дня по запросу, честный пустой экран истории или ничего.
- [ ] **Клиенту нужны формы роли собеседника и портрет** (кадры 30-1 / 30-2b / 33-5): сцена отдаёт роли только
  именительным падежом (`partner_role_native` «Регистратор»), а полоса сцены просит «Приём у врача · **с врачом**» и
  подпись роли у реплики; портрета собеседника в контракте нет вовсе. Либо сервер отдаёт склоняемые формы и портрет,
  либо кадры переписываются под именительный.
- [ ] **Минуты этапа и «ещё N» шапки считаются по разным величинам** (кадры 30-2 / 30-6 / 33-8): шапка и итог этапа
  считают ЕДИНИЦЫ («ещё 3 слова»), а сервер отдаёт карточки и минуты по `DayPace` — на слово их три, на каркас три.
  Решить, кто считает единицы: сервер отдаёт счёт единиц этапа рядом с карточками, или клиент выводит его из `unit`.

## SESSION-1e (2026-09-16): раздача дня по находкам с телефона — круг проверок слов, «на слух» на родном, комбинация на вопрос, наполнения с «нет» судьи

Отчёт — `docs/research/session-1e/README.md`; канон — `docs/plan-v2.md` §6; контракт — `docs/plan-api.md` «Карточки
сессии» + `openapi/openapi.yaml`; решение — DECISIONS п. 329. `mobile/` не тронут. Миграций нет, вызовов модели нет.

- [x] **Проверки слов — один круг по всем словам с обменом** (`WordChecks`): `word_choose` → `word_listen` → `word_in_line`
  → `word_assemble` по индексу слова от старта по зерну; неподходящий вид — обмен с ближайшим словом (решение архитектора
  16.09); вид без материала выпадает. Живой «врач»: было 6 сборок и 0 «на слух», стало 2/2/2/2 (и при любом старте круга).
- [x] **`word_choose` в обе стороны у любого уровня**, направления чередуются по выборам дня; этап «Слова» один на оба
  уровня; каталожный добор переводов — у любого уровня.
- [x] **`word_listen` — звук → перевод**: варианты на родном, `direction: term_to_native`, текста цели нет.
- [x] **`phrase_combine` — только на вопрос собеседника**, первый по визиту; у каждого каркаса `said` — целая фраза.
- [x] **Наполнение с «нет» судьи швов** (`filler.native_seam`) не показывается ни в одной карточке дня; сказанное
  диалогом остаётся; помечены все несказанные — узнавание одно.
- [x] **Фикстуры** пересобраны: 85 карточек, проверки слов по две каждого вида, комбинация на `x1`, ≈ 27 мин; 170
  карточек проходят `PlanCard`.
- [x] **Живая проверка** — день 1 «врача» на e2e пере-роздан в транзакции с откатом (плюс искусственные находки судьи в
  той же транзакции); состояние e2e после отката совпадает с исходным.
- [x] **Чистка `wordtrainer`** — бэкап `wordtrainer-20260916-172715.sql.gz`; пользователь `01M2MW5VV9X91AE5ZTW0TR093W` и
  его токены уже удалены SESSION-1b (0 строк); открытых дней без ответов нет — снесено 0 карточек.

**Хвосты SESSION-1d по пунктам этого наряда:** ни один из пяти хвостов SESSION-1d проверок слов, `word_listen`,
`phrase_combine` и судьи швов не касался — закрывать нечего, все пять остаются ниже как были.

**Хвосты SESSION-1e (найдено, в работу НЕ бралось):**

- [ ] **Клиент не читает новые ключи**: `word_listen.direction` (варианты «на слух» теперь на родном — рисовать как
  `word_choose` `term_to_native`) и `frames[].said` у `phrase_combine` (каркасы — целой фразой). 1b′ / 1c.
- [ ] **Канва 31-5** рисует «звук → написание» — сервер отдаёт переводы; канву правит архитектор.
- [ ] **Судья окна не знает скрытых наполнений**: `SlotJudge` берёт известные наполнения из `frame.slot.fillers` карточки —
  скрытое по судье швов наполнение, сказанное учеником на «Скажи целиком» / `speak_answer`, судит модель (кап тратится),
  а не код.
- [ ] **Окно дня** (`DayWindowViews`, блок `window`) отдаёт все наполнения каркаса, скрытые тоже; клиент их там сейчас не
  рисует — если начнёт, фильтр нужен и там.
- [ ] **Контур дня без каталога**: `DayDealer::outline` не спрашивает каталожный добор — у дня меньше чем из четырёх слов
  контур может показать слово без проверки, которую даст раздача.
- [ ] **Формулировка скилла `learning-srs`** («карточка term → translation — единственная, чей верный вариант — перевод»)
  читается шире модуля Learning: у карточек дня Plan выборы на родном с зачётом по id были и до п. 329 (`word_choose`,
  `phrase_choose_back`, `listen_*`, `dialogue_partner`), теперь и `word_listen`. Вопрос `invariant-reviewer` на закрытии
  SESSION-1e; уточнить скилл («для упражнений Learning») — отдельным изменением процесса.

## SESSION-1d (2026-09-16): фраза через разные окна — серия узнаваний, возврат тем же видом, три хвоста

Отчёт — `docs/research/session-1d/README.md`; канон — `docs/plan-v2.md` §6; контракт — `docs/plan-api.md` «Карточки
сессии» + `openapi/openapi.yaml`; решения — DECISIONS пп. 327, 328. `mobile/` не тронут.

- [x] **Серия узнаваний** (`PhraseSeries` — один построитель на каркас, виды — методы `PhraseCards` одной подписи): каждому
  каркасу с окном — по узнаванию на наполнение, до двух; третье — самым частым каркасам с тремя наполнениями, пока
  «Фразы» укладываются в потолок этапа по `DayPace` (690 с, `plan.phrases_budget`); своё наполнение каждому (первым — сказанное); круг `phrase_slot` →
  `phrase_choose_back` → `phrase_slot_listen` → `phrase_assemble` от открывающего выбора по зерну каркаса (сборка не
  первая); ложные — сначала свои наполнения, добор — следующих каркасов.
- [x] **Разнесение `Spacing::apart`** — минимум две карточки чужих каркасов между карточками каркаса, интро открывают
  волны, `phrase_combine` последней; на чистом уроке и живом «враче» держится везде.
- [x] **Произнесение** — beginner `phrase_repeat` с несказанным наполнением; `phrase_other_slot` — не занятое узнаваниями.
- [x] **Копия и возврат** — копия фразы того же вида с другим наполнением; возврат — видом последнего провала с другим
  наполнением, один раз; провал произнесения фразы — `skipped` после двух попыток с микрофоном (п. 327).
- [x] **Хвосты** — `word_in_line`: строка вне окна (собеседник первым), полный перевод, `text_native_gapped` снят,
  ложные не из своего каркаса; `phrase_combine`: ложные каркасы из самых далёких обменов; `listen_predict`: ложные той
  же формы, что верный.
- [x] **Фикстуры** пересобраны — 85 карточек (фразы 29), ≈ 28 мин; все 28 видов в двух файлах, 170 карточек проходят
  `PlanCard` спеки.
- [x] **Живая проверка** — день 1 «врача» на e2e пере-роздан в транзакции с откатом (день проходит SESSION-1b): 90
  карточек / 29 мин, фразы 33 / 534 с; сценарий провала и день повторения — в отчёте.

**Хвосты SESSION-1d (найдено, в работу НЕ бралось):**

- [ ] **Копия и возврат не разносятся** — копия проваленной карточки и карточка-возврат встают в конец этапа как есть, и
  «минимум две чужие» для них не проверяется (копия может встать сразу за карточкой того же каркаса).
- [ ] **У плана «врач» на e2e один день** — возврат и день повторения на нём живьём не увидеть; живая проверка вставляла
  день повторения внутри откатываемой транзакции. Нужен e2e-план из двух дней на уроке v4.4/v4.5.
- [ ] **Секунды третьих узнаваний считаются по задуманному виду произнесения** — если `other_slot` не соберётся и уступит
  `phrase_repeat`, а их темп в конфиге разойдётся, потолок этапа посчитается с погрешностью в одну карточку.
- [ ] **Мутации SESSION-1a частично не находят свой код** — `docs/research/session-1a/tools/mutations.json` целится в
  подписи и строки, которые SESSION-1d изменил (узнавание одним видом, `farthestRightOption`, `text_native_gapped`); как
  отчёт наряда 1a он история, прогонять его заново бессмысленно.
- [ ] **Канва 31-7 рисует перевод с пропуском** — сервер отдаёт полный перевод; канву правит архитектор.
- [ ] **Канва 32-7 рисует снятый выбор значения** — кадр «Скажи целиком · выбери значение» показывает плашки как
  ВЫБОР и подпись задания «Выбери, что вставить, и скажи фразу целиком». С наряда FIX-1 §6 и DECISIONS п. 352 плашки
  — состояние («сказано · сейчас · впереди»), карточка идёт кругами, а подпись — `planSessionTaskSayEachMeaning`
  («Скажи фразу с каждым значением»). Кадр 32-6 при этом совпадает с кодом слово в слово. Канву правит архитектор;
  найдено сверкой снимков доработки FIX-2 (отчёт `docs/research/fix-2/README.md` §5а).

## SESSION-1b (клиент: сессия дня на 28 видах) — не начат

Вход — фикстуры `docs/fixtures/day-doctor.json` и `day-doctor-beginner.json` (полный день на проводе; **пересобраны
SESSION-1d**: 85 карточек, этап фраз 29 — перечитать состав фраз, ключи те же, у `word_in_line` снят
`text_native_gapped`); контракт —
`docs/plan-api.md` «Карточки сессии» + `openapi/openapi.yaml`; кадры — `docs/session-map.md` и канва (47 кадров);
решения — DECISIONS пп. 325, 326. До этого наряда телефон сессию дня не проходит (принято владельцем).

- [ ] **Экраны сессии по 28 видам** — по кадрам канвы: слова 31-1…31-7, фразы 32-1…32-9, диалог 33-1…33-8, слушание
  34-1…34-8, речь 35-2…35-6, обвязка 30-1…30-9 и 30-2b. Состав дня клиент не выбирает — читает `stages[].cards[]` в
  `position`.
- [ ] **Режим ввода выбирает клиент** — чипы / плитки / голос по уровню плана и переключателю «Без подсказок» (30-1);
  в payload лежат данные для всех режимов, состав дня от переключателя не зависит.
- [ ] **Зачёт на клиенте** — выбор, сборка и покрытие речи считаются детерминированно, без сети, результат уходит
  `POST …/answer` (`response`: услышанное, `hinted_at`, значение окна, режим); что вид принимает — `CardKind::allows`
  на сервере, 422 на чужой итог.
- [ ] **Судья окна** — `POST …/cards/{card}/judge` с `heard` и `hinted`: вердикт, значение окна и причина на родном
  приходят от сервера; «Ещё раз» — новая попытка, «Пропустить» — обычный `skipped`.
- [ ] **Шапка, итоги этапа и дня** — из ответа POST (`{card, requeued, unit, day, stage}`) собираются «сколько
  сделано / что вернётся» (30-6, 33-8, 34-8, 35-6) и итог дня (30-7); счёт единиц — открытый хвост SESSION-1a.
- [ ] **Старые экраны сессии сняты** — маршруты и карточки прошлой раздачи в приложении мертвы (виды удалены на
  сервере), включая догрузку голоса реплик (хвост DAY-UI-3 выше).

**Четыре находки GEN-2a, которые остаются за клиентом:**

- [ ] **Порядок реплик ask/rescue**: в обменах, где ученик говорит первым, окно рисует собеседника первым; у пары окна
  есть `kind` (`ask`/`rescue` — реплика ученика первой). Снимок — `docs/research/gen-2a/screens/07-dialogue-ask-rescue.png`.
- [ ] **Перевод реплики ученика и перевод фразы расходятся**: реплика в диалоге — перевод модели в контексте («Она у
  него уже три дня.»), фраза — каркас с наполнением («У него это уже три дня.»); один текст на обе карточки или
  пометка, какой где.
- [ ] **Перенос длинного слова**: перевод на карточке слова ломается посреди слова («жаропонижающе / е») — перенос по
  слогам или уменьшение кегля. Снимок — `docs/research/gen-2a/screens/03-words.png`.
- [ ] **Замедление «Повтори вслух»** — клиент воспроизводит файл сервера на 0.85× (решение архитектора TTS-2: серверный
  голос всегда обычного темпа; замедление на сервере пометкой в тексте делало день ученика-мужчины дороже на 43 %).

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

