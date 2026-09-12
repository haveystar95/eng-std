# PLAN-UI-3 — бэкенд, часть A (этапы на маршруте, фраза плана, фото сцен, языки плана)

2026-09-12. Исполнитель A, параллельно с B (события плана / пуш-токены). Коммитов нет — коммитит
ведущий. Главная база `wordtrainer` не тронута: ни одной миграции, сида и записи; `app` не
перезапускался. Всё проверено на своей базе `wordtrainer_a_test`.

## 1. Что сделано

### A1 — у каждого дня маршрута есть `stages`

`GET /plans/current`, `GET /plans/{id}`, `current_day` и `day` кабинета дня: `stages: [{stage, state}]`,
`stage` ∈ `words|phrases|dialogue|listen|speak` в порядке прохождения, `state` ∈ `done|current|locked`.
Только этапы, которые у дня есть: этап без карточек пропускается.

- Правило — чистый сервис `Domain/Service/RouteStages` (+ `Domain/ValueObject/StageState`, `RouteStage`):
  - есть карточки → счёт по этапам; все отвечены → `done`, первый незаконченный → `current`, дальше
    `locked`; закрытый день — все `done` (тот же обход, что `GetDayRoomHandler::stages`);
  - карточек нет → этапы из «наброска» раскладчика, если он есть, иначе по типу дня: сцена — пять,
    повторение — `words`+`speak`, репетиция — `speak`; первый этап `current`, только если это текущий
    день плана и он доступен сегодня (эффективный статус `open`/`in_progress`), иначе все `locked`;
    закрытый день без карточек — все `done`.
- Счёт — один сгруппированный запрос на план: `DayCardRepository::stageTallies(PlanId)`
  (`day_cards ⋈ plan_days WHERE plan_id GROUP BY day_id, stage`, `COUNT(*)`, `COUNT(result)`). Новый
  индекс не нужен — см. EXPLAIN (1). Тест «один запрос к `day_cards` на весь маршрут» есть.
- Набросок (`DayDealer::outline`) считается **только для текущего нерозданного дня** (плюс в кабинете —
  для открытого кабинетом дня, там список карточек уже в руках и передаётся в `PlanViews::day`, лишних
  запросов нет).
- DTO: `DayRouteView::$stages` (`list<RouteStageView>`), `PlanJson::day` рендерит.

### A2 — `summary` у плана

`Plan.summary: string|null`, считается при чтении: заголовки дней-сцен в порядке маршрута (только
`type = scene`), первые три, `mb_strtolower`, через «, », первая буква заглавная, затем «. » +
обещание. Строки — `NativeStrings::planSummary()` в той же таблице языков (ru, uk, en; прочие — en):
«К {day} {месяц в род. п.} скажешь всё это сам» / «Скажешь всё это сам»; uk «До 17 вересня скажеш усе
це сам»; en «By September 17 you will say all of this yourself». `null`, если заголовков нет.

### A3 — фото сцен: тон, две копии, вечный кэш

- **Тон.** `Generation\ImageResult::$avgColor` (аддитивно, коллекции его не читают), Pexels
  `avg_color` в `search()`; новый метод порта `ImageSearchPort::photo(id)` (`GET /v1/photos/{id}`,
  тот же контракт transient/null). Plan `Image::$tone` (нормализуется: не `#RRGGBB` → null, в верхний
  регистр) и `Image::version()` = 12 hex `sha1(url)`. Пишется в том же условном UPDATE, что фото:
  `plan_scenes.image_tone`, `plans.cover_image_tone`.
- **Копии 112/448.** Порт `Application/Port/SceneImageStore` (`has`/`read`/`fetch`), адаптер
  `Infrastructure/Adapter/CdnSceneImageStore`: адрес фото без query + `?auto=compress&cs=tinysrgb&fit=crop&w=S&h=S`,
  `Accept: image/jpeg`, метка `OutboundCallContext('images')`, файл `plan-images/{sceneId}/{S}.jpg` на
  диске `plan.image_disk` (по умолчанию `local`). `fetch` никогда не бросает: не скачалось — null +
  warning в лог. Под `IMAGE_DRIVER=fake` (весь сьют, офлайн-дев) сеть не трогается вообще.
- **Job фото** (`AttachPlanImagesHandler`): у сцены с фото после записи кладёт обе копии, если их нет.
  `attachSceneImage` теперь возвращает bool: копии качает только тот писатель, чьё фото легло.
- **Ручка** `GET /api/v1/plans/images/{sceneId}/{size}` (`size` 112|448, `auth:sanctum`, та же группа
  `throttle:120,1`): `GetSceneImageHandler` → `SceneLocator::ownedImage` (PK + `user_id`) → файл, иначе
  `fetch` (самолечение), иначе 503. Контроллер: `Content-Type: image/jpeg`, `Cache-Control: public,
  max-age=31536000, immutable`, `ETag: "<sha1 байтов>"`, `If-None-Match` → 304 (Symfony
  `isNotModified`).
- **Провод:** `scenes[].image = {url, author, author_url, tone, url_112, url_448}`,
  `url_*` = `url('/api/v1/plans/images/{id}/{S}')` + `?v={version}`. Абсолютный адрес строится от
  запроса (`trustProxies('*')` уже стоит в `bootstrap/app.php`, так что через ngrok придёт https-хост
  ngrok, в симуляторе — `localhost:8001`). У `cover_image` добавлен `tone`, копий нет.
- **Бэкфилл** `php artisan plan:images-backfill {--plan=}` → `BackfillSceneImagesHandler`: тон через
  `PlanImageFinder::tone(url)` (id фото из `images.pexels.com/photos/{id}/…`, transient → null) и
  условный `attachSceneImageTone` (`WHERE image_tone IS NULL AND image_url = ?`), затем недостающие копии.
  Идемпотентен, печатает счётчики. **На главной базе не запускался.**

### A4 — языки плана с сервера (доп. задание)

`GET /api/v1/plans/languages` → `{"data": {"targets": ["en","de"]}}`; источник —
`config/plan.php` → `languages` (env `PLAN_LANGUAGES`, через запятую, по умолчанию `en,de`) →
`PlanConfig::$languages` → запрос `GetPlanLanguages`. `CreatePlanRequest` валидирует `target_lang`
через `Rule::in` по тому же запросу → 422 на прочие. Существующие тесты создают планы только на `en`
— фикстуры не менялись.

## 2. Файлы

Новые:
- `app/Modules/Plan/Domain/Service/RouteStages.php`
- `app/Modules/Plan/Domain/ValueObject/{StageState,RouteStage,SceneImageSize}.php`
- `app/Modules/Plan/Domain/Exception/SceneImageNotFound.php`
- `app/Modules/Plan/Application/Exception/SceneImageUnavailable.php`
- `app/Modules/Plan/Application/Port/SceneImageStore.php`
- `app/Modules/Plan/Application/Dto/{RouteStageView,SceneImageRef,SceneImageFile,SceneImageBackfillReport,PlanLanguagesView}.php`
- `app/Modules/Plan/Application/Query/{GetSceneImage,GetSceneImageHandler,GetPlanLanguages,GetPlanLanguagesHandler}.php`
- `app/Modules/Plan/Application/Command/{BackfillSceneImages,BackfillSceneImagesHandler}.php`
- `app/Modules/Plan/Infrastructure/Adapter/CdnSceneImageStore.php`
- `app/Modules/Plan/Infrastructure/Console/PlanImagesBackfillCommand.php`
- `app/Modules/Plan/Infrastructure/Migration/2026_09_12_120000_add_image_tone_to_plan_scenes_and_plans.php`
- `app/Modules/Plan/Presentation/Http/Controller/PlanImageController.php`
- тесты: `tests/Unit/Plan/{RouteStagesTest,PlanSummaryTest}.php`,
  `tests/Feature/Plan/{PlanRouteStagesTest,PlanSceneImageTest,PlanLanguagesTest}.php`

Изменённые:
- Plan Domain: `Service/NativeStrings.php`, `ValueObject/Image.php`, `Repository/DayCardRepository.php`,
  `Repository/PlanRepository.php`
- Plan Application: `Service/PlanViews.php`, `Query/GetDayRoomHandler.php`,
  `Command/AttachPlanImagesHandler.php`, `Port/SceneLocator.php`, `Port/PlanImageFinder.php`,
  `Dto/{DayRouteView,SceneView,PlanView,PlanConfig}.php`
- Plan Infrastructure: `Eloquent/{EloquentDayCardRepository,EloquentPlanRepository,PlanMapper,PlanModel,PlanSceneModel}.php`,
  `Adapter/PexelsPlanImageFinder.php`, `Provider/PlanServiceProvider.php`
- Plan Presentation: `Http/PlanJson.php`, `Http/routes.php`, `Http/Controller/PlanController.php`,
  `Http/Request/CreatePlanRequest.php`
- Generation: `Application/Dto/ImageResult.php`, `Application/Port/ImageSearchPort.php`,
  `Infrastructure/Adapter/{PexelsImageSearch,FakePexelsImageSearch}.php`
- `config/plan.php` (`image_disk`, `languages`), `bootstrap/app.php` (регистрация команды)
- `openapi/openapi.yaml`, `docs/plan-api.md`, `docs/plan-v2.md` (§7, §9), `app/Modules/Plan/README.md`
- `tests/Feature/Generation/PexelsImageSearchTest.php` (+2 теста: `avg_color`, `photo(id)`)

## 3. Миграция

`2026_09_12_120000_add_image_tone_to_plan_scenes_and_plans` — `plan_scenes.image_tone varchar(7) NULL`
+ CHECK `^#[0-9A-F]{6}$`, `plans.cover_image_tone` так же. Nullable без дефолта — ALTER без
перезаписи таблицы; CHECK проверяет существующие строки, но все они NULL. Индексов нет (колонка
читается со строкой). Метка времени `120000`, а не `100000`: у B в тот же день
`2026_09_12_100000_create_device_push_tokens_table`.

Откат на `wordtrainer_a_test`: `migrate:fresh` → `migrate:rollback --step=1` (откатилась именно эта) →
`migrate` — все три зелёные, `\d plan_scenes` показывает колонку и CHECK.

## 4. EXPLAIN (ANALYZE, BUFFERS)

База `wordtrainer_a_test`, нагрузка `plan:seed-load`: пользователь A — 50 планов, пользователь B — 200;
итого 250 планов, 1 000 сцен (всем проставлены `image_url`/`image_tone`), 1 500 дней, 100 500 карточек,
14 000 терминов; `ANALYZE` после сида. План — активный план A (6 дней × 67 карточек).

**(1) Счёт этапов маршрута** (`stageTallies`):

```
HashAggregate  (cost=762.46..766.48 rows=402 width=50) (actual time=0.459..0.464 rows=30 loops=1)
  Group Key: c.day_id, c.stage
  Buffers: shared hit=111
  ->  Nested Loop  (actual time=0.061..0.386 rows=402 loops=1)
        ->  Index Scan using plan_days_number_uidx on plan_days d  (actual rows=6 loops=1)
              Index Cond: (plan_id = '01M2BGF74QJBQ43MAS1VFVMD9Y'::bpchar)
        ->  Index Scan using day_cards_position_uidx on day_cards c  (actual rows=67 loops=6)
              Index Cond: (day_id = d.id)
Execution Time: 0.652 ms
```

Чтение: дни плана — по `plan_days_number_uidx (plan_id, number)`, карточки каждого дня — по
`day_cards_position_uidx (day_id, stage, position)`; 402 строки из 100 500, 111 буферов, 0.65 мс.
Индекс покрывает доступ; `result` берётся из кучи (index-only не нужен при 67 строках на день).
Новый индекс не добавлял.

**(2) Поиск сцены ручкой картинки** (`ownedImage`):

```
Limit  (actual time=0.145..0.145 rows=1 loops=1)
  Buffers: shared hit=4
  ->  Index Scan using plan_scenes_pkey on plan_scenes  (actual rows=1 loops=1)
        Index Cond: (id = '01M2BGF74T3XFE8CJAQGB2TE9N'::bpchar)
        Filter: ((image_url IS NOT NULL) AND (user_id = '01K00000000000000000000001'::bpchar))
Execution Time: 0.153 ms
```

Чтение: по первичному ключу, владелец и наличие фото — фильтром по одной строке; 4 буфера.

**(3) Загрузка плана для `GET /plans/current`** — Eloquent `with(['scenes','days'])`, три запроса:

```
(3a) plans:
Limit  (actual time=0.416..0.416 rows=1 loops=1)
  ->  Sort  Sort Key: (CASE WHEN status = 'ready' THEN 1 ELSE 0 END), created_at DESC  (top-N heapsort)
        ->  Seq Scan on plans  (actual rows=41 loops=1)
              Filter: ((user_id = '01K00000000000000000000001') AND (status = ANY ('{active,overdue,ready}')))
              Rows Removed by Filter: 209
              Buffers: shared hit=9
Execution Time: 0.435 ms

    то же с SET enable_seqscan = off:
    ->  Bitmap Heap Scan on plans  (actual rows=41)
          ->  Bitmap Index Scan on plans_user_status_idx  (actual rows=41)
                Index Cond: ((user_id = …) AND (status = ANY ('{active,overdue,ready}')))
    Execution Time: 0.189 ms

(3b) plan_scenes:
Index Scan using plan_scenes_order_uidx on plan_scenes  (actual rows=4 loops=1)
  Index Cond: (plan_id = '01M2BGF74QJBQ43MAS1VFVMD9Y'::bpchar)
  Buffers: shared hit=11
Execution Time: 0.217 ms

(3c) plan_days:
Index Scan using plan_days_number_uidx on plan_days  (actual rows=6 loops=1)
  Index Cond: (plan_id = '01M2BGF74QJBQ43MAS1VFVMD9Y'::bpchar)
  Buffers: shared hit=3
Execution Time: 0.033 ms
```

Чтение: (3a) — 250 строк в 9 страницах, планировщик честно выбирает seq scan; индекс
`plans_user_status_idx` рабочий (виден при выключенном seq scan), на реальном объёме он и возьмётся.
(3b)/(3c) — по уникальным индексам `(plan_id, order)` / `(plan_id, number)`, сортировка из индекса.
Итого `GET /plans/current` на живом плане: 3 запроса загрузки + 1 счёт этапов + (только если текущий
день ещё не роздан) набросок раскладчика: `plan_terms` по сцене и `day_cards … WHERE returns` по
предыдущему дню-сцене — те же запросы, что уже делает кабинет дня.

## 5. Решения, которые пришлось принять

1. **Имя тестовой базы.** Указанное `wordtrainer_test_a` защитник `AppServiceProvider::isDisposableDatabase`
   (`/_test(_test_\d+)?$/`) не считает одноразовой — `RefreshDatabase` получал отказ `migrate:fresh`, и
   все фича-тесты падали на «relation users does not exist». Взял `wordtrainer_a_test` (кончается на
   `_test`), `wordtrainer_test_a` удалил.
2. **«Роздан ли день»** определяется по наличию карточек в счёте, а не по `opened_at` (для маршрута);
   кабинет по-прежнему решает по `opened_at` и передаёт свой список в `PlanViews::day`. `OpenDay`
   раздаёт карточки в той же транзакции, что ставит `opened_at`, так что расхождения нет.
3. **Набросок раскладчика — только для текущего нерозданного дня** на маршруте (как в задании).
   Кабинет дня для любого неоткрытого дня с написанным уроком уже держит набросок в руках — его
   `day.stages` берутся из него (реальный состав), тогда как тот же день в `days[]` плана показан по
   типу. Разница возможна только у будущих дней, у которых урок уже есть (обычно — следующий
   день-сцена после открытия текущего).
4. **Повторение по типу = `words` + `speak`** — так в каноне (§6), но по коду `DayAssembler::reviewDay`
   этап слов состоит только из возвратов (без дважды проваленных слов его не будет), а возвращённая
   фраза даёт `phrases`. Для текущего дня это исправляет набросок; для будущих — ориентир. Записано в
   `plan-api.md`.
5. **Не скачалась копия при запросе → 503 `plan_scene_image_unavailable`**, а не 404: 404 оставлен
   для «не твоя сцена / нет фото / не тот размер», чтобы клиент не запоминал временную неудачу как
   отсутствие.
6. **`Cache-Control`** выставляется строкой `public, max-age=31536000, immutable`; Symfony
   нормализует заголовок и может переставить директивы (`immutable, max-age=31536000, public`) —
   смысл тот же, тест проверяет наличие директив, а не порядок.
7. **Версия в query (`?v=`), а не в пути** — маршрут `/{sceneId}/{size}` ровно как в задании;
   сервер `v` не читает, он только меняет адрес. Путь файла на диске без версии: адрес фото сцены
   пишется один раз.
8. **Отказ сети под fake-драйвером.** `CdnSceneImageStore` под `services.generation.image_driver = fake`
   не качает ничего — иначе каждый фича-тест, создающий план (очередь `sync`), ходил бы на
   `images.pexels.test`. Тесты самолечения и job-а включают `pexels` + `Http::fake` +
   `Http::preventStrayRequests()`.
9. **`attachSceneImage` возвращает bool** (изменение интерфейса репозитория внутри модуля): копии
   качает только писатель, чьё фото легло. Других реализаций интерфейса нет.
10. **Бэкфилл возвращает отчёт-DTO** (`SceneImageBackfillReport`) — команда, которая вместо id отдаёт
    счётчики для консоли; ничего кроме консоли его не читает.
11. **`public` при `auth:sanctum`** — как задано. Промежуточных кэшей между телефоном и API нет
    (ngrok не кэширует); если появится CDN — копии всё равно одинаковы для всех, кто знает ULID сцены,
    но стоит помнить, что владелец проверяется только на промахе кэша.
12. **Языки плана** читаются из синглтона `PlanConfig` (как счётчики и rescue kit): смена
    `PLAN_LANGUAGES` требует перезапуска `app`/`horizon`, как любой конфиг.

## 6. Результаты ворот

- `composer arch` — Violations 0, Errors 0, Uncovered 3 (старые: `GoogleAuthTokenVerifier`,
  `UserFactory` ×2).
- `composer stan` — `[OK] No errors` (в промежуточном прогоне было 3 ошибки в файлах B — Identity
  `GetUsualVisitTimeHandler`, `EloquentPushTokenStore`, `EloquentVisitLog`; к финальному прогону B их
  убрал).
- `composer lint:openapi` — `openapi.yaml ok`, `openapi-admin.yaml ok`.
- Pest на `wordtrainer_a_test`, серийно: `tests/Feature/Plan tests/Unit/Plan` + image-тесты Generation
  (`FakePexelsImageSearchTest`, `PexelsImageSearchTest`, `AttachImagesTest`, `EnrichTermTest`,
  `GenerationIdempotencyTest`, `GenerationTargetLangFallbackTest`, `BuildReadingTest`) —
  **211 passed (3333 assertions)**. Новых тестов 38: Unit `RouteStagesTest` 11, `PlanSummaryTest` 7;
  Feature `PlanRouteStagesTest` 4, `PlanSceneImageTest` 10, `PlanLanguagesTest` 4,
  `PexelsImageSearchTest` +2. Полный параллельный сьют не запускал (по заданию — ведущий).
- Однажды весь набор Plan упал 500-ми на всех тестах разом и сразу же прошёл при повторе без правок —
  совпало с тем, что B в этот момент писал свои файлы; при финальном прогоне воспроизведения нет.

## 7. Что осталось / вопросы

- **Прогон на главной базе (ведущий):** бэкап → `migrate` (одна миграция A) → `docker compose restart
  horizon` (job фото и `PlanConfig` читаются воркером) → `php artisan plan:images-backfill` (тоны и
  копии для существующих сцен; можно по одному `--plan=`). Без бэкфилла старые сцены отдают
  `tone: null`, а копии добудет сама ручка при первом запросе.
- **Диск.** `plan.image_disk = local` → `storage/app/private/plan-images/…` внутри контейнера; том
  `storage` должен переживать пересборку, иначе копии просто перекачаются (самолечение), тоны — нет
  (они в БД).
- **Троттлинг 120/мин** общий с остальным API: первый показ вкладки с ~10 сценами — ~10 запросов
  картинок; дальше `immutable`. Если клиент будет грузить обе плотности сразу — вдвое.
- «К 2 сентября» — по-русски грамотнее «Ко 2 сентября» (и «ко 2-му»); строка владельца оставлена
  как есть, спросить, нужна ли эта тонкость.
- Тон у фото терминов (`plan_terms`) не пишется — вне объёма.
- Удалённый план (`status = deleted`): его сцены по-прежнему отдают картинку владельцу (проверяется
  только `user_id`). Если нужно 404 — добавить join на `plans.status`.
