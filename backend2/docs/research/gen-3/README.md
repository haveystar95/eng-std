# GEN-3 · день N знает прошлые дни: урок `lesson_day.v4.6`, P2R `v1.2`, проверки повторов, журнал вызовов, расписание дней

Наряд 17.09.2026, репо `backend2/` (+ `wt_admin/` — песочница, по согласию Дена; `mobile/` не трогался). Канон —
`docs/plan-v2.md` §2, §3, §3а, §4, §5, §9; контракт — `docs/plan-api.md`, `openapi/openapi.yaml`, `openapi/openapi-admin.yaml`;
реестр промптов — `docs/prompts/REGISTRY.md` (LESSON, P2R, строки «откат», PLAYGROUND, «Журнал вызовов модели»); решения —
`docs/DECISIONS.md` пп. 330–336 и два пункта «Отменено» (п. 307); ROADMAP — раздел GEN-3. Живые вызовы — только §7 наряда, стенд
`wordtrainer_e2e_test`; `wordtrainer` не тронут (только чтение для §4a и §6); голос и фото не покупались.

> **Доработка 17.09 — §8** (решения архитектора после сдачи): промты `lesson_day.v4.7` / `lesson_card_repair.v1.3`;
> `vocab.abbreviation` — предупреждение (фатальных 9); починке каркаса не цитируются находки о тождестве родного шаблона;
> разбор снимает пробел перед знаком конца у родного каркаса и родных наполнений. Числа §0–§7 — сдача GEN-3 как была (живые
> дни на v4.6, валидатор сдачи); пересчёт по правилам доработки — §8.

## §0. Итог

- **Промты** `lesson_day.v4.6` и `lesson_card_repair.v1.2` приняты байт-в-байт (sha256 совпали с incoming, incoming снесён);
  `v4.5`/`v1.1` лежат рядом — откат одной константой `PlanPromptFiles`. Схема урока, `plan-builder-v2`, судья швов v1.1 — без
  изменений.
- **День 2 больше не повторяет слова дня 1:** v4.5 — 3 из 6 дней (deposit, backpack, sore throat), **v4.6 — 0 из 6**. Каркас дня 1
  v4.6 повторил дважды (ресторан «That's ___.», врач «He has ___.») — порог поймал, P2R починил. Аббревиатуры словом дня: v4.5 — 1
  (PIN), v4.6 — 2 (PIN, ATM), обе починены. Близнецы каркасов 2 → 0.
- **Все 18 дней `ready`**, починок на днях 2 v4.6 — 4 (2 слова, 2 каркаса), в потолок двух карточек упёрся 1 день (банк).
- **Цена дня 2**: v4.5 $0.0684 → v4.6 $0.0708 в среднем со скидкой кэша (+3.5 %; по прейскуранту $0.0814 → $0.0897, +10 %):
  прошлый день — ≈ 1 100 токенов входа вне кэша, плюс починки. Доля починок в цене дня (урок + судья) — 10.9 % со скидкой кэша,
  9.8 % по прейскуранту (канон ≤ 10 %).
- **Журнал `model_calls`**: запись до вызова, `completed`/`failed`/`lost`; ответ ждём 180 с, соединение 10 с, оборванный вызов не
  повторяется; песочница — job с опросом. На прогоне: 36 вызовов нового кода — все `completed`, $0.9997, **lost 0**.
- **Расписание (§11)**: день N+1 — с календарного дня после открытия дня N, «догоняем», день события учебный; урок N+1 заказывает
  закрытие дня N через `NextDayAccess`; день в очереди без урока — `building`, открыть — 409 `plan_day_building`.
- **Ворота**: OpenAPI ok ×2, deptrac 0, PHPStan 0, Pest **2 299 passed** (15 990 assertions); мутации **38 из 38** пойманы;
  `invariant-reviewer` — CLEAN. **Расход наряда — $1.41** со скидкой кэша ($1.70 по прейскуранту) из капа $3.
- **Открыто**: сумма панели OpenAI за 17.09 для сверки (§4a) — Costs API ключа отвечает 403; решение по
  `frame.known_native_repeat`; качество двух починок (§3).

## §1. Что изменилось

### Генерация (коммит `a51c0b4d`)

| слой | файлы |
|---|---|
| промты | **новые** `Plan/Infrastructure/Prompt/lesson_day.v4.6.md`, `lesson_card_repair.v1.2.md`; `PlanPromptFiles` (v4.6/v1.2, `REPAIR_SECTIONS` + THE STORY SO FAR и вид `term`, цитата пропускает раздел, которого нет, входы `LEARNER_ROLE`/`PARTNER_ROLE`/`EARLIER_DAYS`, `NEIGHBOURS`, короткая история); `PlanSchemas::lessonCard(вид, dialogueCount, vocabularyCount)` — одна схема на вид |
| Domain · урок | **новые** `Lesson/EarlierDay`, `EarlierDays`, `LessonRoles`; `Lesson::withRoles`, `Message::withRole`, `LessonCard` (вид `term`, адрес `v4`, `replace` держит id), `LessonCardContext` (`neighbours`, у слова — реплики собеседника), `LessonParser` (`card('term')`), `VocabularyItem`, `FrameText::identity` |
| Domain · проверки | **новый** `Check/Lesson/StoryRules` (known word/frame/native, пол той же роли); `FrameRules` (`frame.twin`), `VisitRules` (`frame.adjacent_repeat`), `VocabularyRules` (`vocab.abbreviation`), `CheckRules` (роль ученика — только из урока), `LessonCodes` (57), `LessonGate` (10 фатальных, слово — последним), `LessonValidationContext` (история, роль собеседника), `LessonValidator` |
| Domain · план | `Plan::earlierDaysOf`, `Plan::lessonRoles`; `PlanScene::needsLesson` — только `pending` |
| Application · Plan | **новый** `Service/LessonRequests` (один сборщик входов для сборки и починки); `LessonRequest` (+ роли, история, языки), `LessonCardRepairRequest` (+ соседи, история, счёты), `LessonCardRepairer` (соседи, перепроверка слова → `REFUSED`, роли после вставки), `LessonCardRepairOutcome::REFUSED`, `LessonBuildService`, `LessonContexts`, `BuildLessonHandler`, `ModelReply` |
| Infrastructure · Plan | `ContentModelPlanBuilder` (кэш-токены), `FakePlanModel` (v4.6/v1.2, роли, дни N с пометкой `-N`), `BuildLessonJob::timeoutSeconds` (960 с), `BuildPlanJob` (420 с), `PlanServiceProvider`, `PlanRepairCardCommand` (адрес `v4`, `refused`), `PlanSeedLoadCommand` |
| Generation | **новые** `Infrastructure/Adapter/VendorCall` (10 с соединение, ответ — по вызывающему, повтор только на ответ 408/409/429/5xx, журнал), `Application/Service/PlaygroundRuns`, `Dto/PlaygroundRun`, `Dto/PlaygroundRunRequest`, `Port/PlaygroundRunStore`, `Port/PlaygroundRunDispatcher`, `Adapter/CachePlaygroundRunStore`, `Adapter/QueuedPlaygroundRunDispatcher`, `Job/RunPlaygroundCallJob`; адаптеры `OpenAiCompatibleContentModel`, `AnthropicContentModel`, `GeminiContentModel`, `OpenAiCompatiblePlaygroundModel`, `AnthropicPlaygroundModel` и оба каталога — через `VendorCall`; `ModelAnswer`/`PlaygroundRawReply` + `cachedTokensIn`; `PlaygroundCall` |
| Observability | **новые** `Application/Dto/ModelCallStart`, `ModelCallUsage`, `Port/ModelCallJournal`, `Infrastructure/Eloquent/EloquentModelCallJournal`, `ModelCallModel`, миграция `2026_09_17_120000_create_model_calls_table`, `Console/SweepLostModelCallsCommand` (`model-calls:sweep-lost`, раз в 10 мин — `routes/console.php`, `bootstrap/app.php`) |
| Shared | `ModelCost::estimate(…, cachedTokensIn)` — кэшированный вход по его цене (`gpt-5.x` — 10 %, `gpt-4o*` — 50 %) |
| Admin | **новые** `Command/StartPlaygroundRun(+Handler)`, `Query/GetPlaygroundRun(+Handler)`, `Dto/PlaygroundRunView`; `PlaygroundController` (202 + `GET /playground/runs/{id}`), `PlaygroundGenerateRequest` (промт до 65 000), `AdminJson`, routes |
| config | `plan.php` (`plan_timeout`/`lesson_timeout` 180, `build_stale_seconds` 1 020), `playground.php` (180), `queue.php` (Redis `retry_after` 1 020) |
| wt_admin | **новые** `src/api/playgroundRun.ts` (опрос раз в 1,5 с, до 6 мин), `test/playgroundRun.spec.ts`; `src/api/index.ts`, `types.ts`, `views/PlaygroundView.vue` («В очереди…» / «Модель отвечает…») |
| фикстуры, тулзы | `docs/fixtures/day-doctor*.json` (версия v4.6), `docs/research/session-1d|1e/tools/probe.php` (новый `LessonRequest`) |

### Расписание (коммит `ce3154b7`)

`Plan` (`closeDay` датирует от `openedAt`, `isCatchingUp`, `isDayBuilding`, `effectiveDayStatus`, `currentSceneWithoutLesson`,
порядок проверок `openDay`), `PlanScene::isAwaitingLesson`, **новые** `Domain/Exception/PlanDayBuilding`,
`Application/Port/NextDayAccess`, `Infrastructure/Adapter/EveryNextDayAllowed`; `CloseDayHandler` (единственный триггер),
`OpenDayHandler` (без диспетчера), `BuildPlanHandler`, `ReschedulePlanHandler`, `RemoveSceneHandler`, `RunNotificationTickHandler`,
`PlanViews` / `PlanView` / `PlanJson` (`catch_up`, `building`), `DayRouteView`, `DayWindowViews`, `GetDayRoomHandler`,
`WindowStatus::Building`; `openapi/openapi.yaml`.

### Документы (коммит `c9569adc`)

`docs/plan-v2.md`, `docs/plan-api.md`, `docs/prompts/REGISTRY.md`, `docs/DECISIONS.md` (корень репо), `docs/ROADMAP.md`,
`docs/session-handoff.md`, README модулей Plan, Observability, Generation, Admin, `openapi/openapi-admin.yaml` (см. §4, п. 16), этот
отчёт и выгрузки. В этот же коммит — правки тестов после кодовых коммитов (закрытие дыр, найденных мутациями, §1 «Тесты»).

### Снесено

- `Admin/Application/Query/RunPlaygroundPrompt` и `RunPlaygroundPromptHandler` — синхронный вызов модели в веб-запросе песочницы.
- `BuildLessonHandler::topicDescription` и своя сборка запроса в `LessonCardRepairer` → один `LessonRequests`.
- `LessonCardRepairRequest::frameIds`; enum адреса, шага и каркасов дня в схеме починки.
- Повтор оборванного вызова (`ConnectionException`) в трёх адаптерах `ContentModelPort`; дефолты таймаутов 90/60 с.
- Пересборка упавшего урока при открытии и закрытии дня, при переносе и расширении плана.
- Запасной путь роли ученика в `CheckRules` (сервер больше не выводит роль сам).
- Постановка урока следующего дня при открытии дня N (`OpenDayHandler` без диспетчера); выбор «следующего дня-сцены после
  текущего» при расширении, переносе и удалении сцены; датировка следующего дня от закрытия; частный `effectiveDayStatus` в
  `PlanViews`.
- Папка `docs/prompts/incoming/` (файлы не были в git).

### Тесты, мутации, ворота

Новые файлы тестов — 27 тестов: `Unit/Plan/StorySoFarTest` (4), `Unit/Plan/LessonRequestPromptTest` (4), `Unit/Plan/PlanScheduleTest`
(5), `Feature/Plan/StoryBuildTest` (3, один — датасет из четырёх отказов), `Feature/Observability/ModelCallJournalTest` (6),
`Feature/Plan/NextDayBuildTest` (5); дополнены `LessonValidatorTest` (история, близнецы, подряд — и через rescue, пол, аббревиатуры),
`LessonGateTest` (ровно 10 фатальных), `LessonCardTest`, `DayWindowTest`, `LessonGateBuildTest` (соседи в вызове),
`AdminPlaygroundTest` (запуск без вызова модели, журнал песочницы), остальные — под новые входы и триггер. Первая строка каждого
теста называет правило наряда и дефект. Живых вызовов в тестах нет.

**Мутации** — `mutations.md`, `mutations.log` (`tools/mutate.py` + `tools/mutations.json`, копия дерева в своём контейнере и своей
базе): **38 из 38 пойманы** — по одной на правило канона (история, роли, каждый новый код, NEIGHBOURS и короткая история, перепроверка
слова, кэш, журнал, таймауты, расписание, триггер, песочница). Первый прогон дал 34 из 38: выжили смежность каркаса через rescue,
аббревиатура в перепроверке слова, одинаковость схемы починки каркаса и синхронный вызов песочницы — тесты дополнены. Замечено по
дороге: bind mount Docker Desktop отстаёт от записи на мгновение — тест сразу после восстановления файла читал половину файла
(`ParseError`, выглядевший как поимка, и красный базовый прогон); `mutate.py` теперь ждёт, пока `sha1sum` в контейнере совпадёт.

**Ворота** (один раз, в конце, `docker compose exec -T app composer check`): OpenAPI `openapi.yaml` и `openapi-admin.yaml` ok;
deptrac — 0 нарушений (uncovered 3 — прежние); PHPStan L8 — 0 ошибок на 1 551 файле; Pest — **2 299 passed, 15 990 assertions,
51.6 с**. `invariant-reviewer` по диффу кодовых коммитов — CLEAN (замечание без нарушения: Admin зовёт `PlaygroundRuns` из
Application Generation — так же, как уже звал `DistractorDryRun`; строка добавлена в README Admin). wt_admin: `vue-tsc` ok, vitest
120 passed, eslint ok.

## §2. Коды и пороги

Тождество (`FrameText::identity`) — одно для терминов, каркасов и ролей: окно → `___`, без знака конца, нижний регистр, пробелы
схлопнуты.

| код | серьёзность | правило | адрес · P2R | день 2 v4.5 | день 2 v4.6 |
|---|---|---|---|---|---|
| `vocab.known_repeat` | **фатальный** | `term_target` совпал с термином прошлого дня из `EARLIER_DAYS` | `v4` · `term` | 3 из 48 слов (6.2 %) | 0 |
| `frame.known_repeat` | **фатальный** | `frame_target` совпал с каркасом прошлого дня | `p3` · `frame` | 1 из 43 каркасов (2.3 %) | 2 (4.7 %) |
| `vocab.abbreviation` | **фатальный** | `term_target` — две и больше заглавных подряд, можно через точку или слэш (API, CI/CD, U.S., ЖКХ); «X-ray», «iPhone», «Wi-Fi» — нет | `v4` · `term` | 1 (2.1 %) | 2 (4.2 %) |
| `frame.known_native_repeat` | предупреждение | совпал только `frame_native` | `p3` | 3 (7.0 %) | 1 (2.3 %) |
| `frame.twin` | предупреждение | два каркаса дня с одним шаблоном в любом языке | поздний каркас | 2 (4.7 %) | 0 |
| `frame.adjacent_repeat` | предупреждение | реплики ученика двух обменов подряд на одном `phrase_id` (rescue между ними — не подряд) | поздний обмен | 0 | 0 |
| `role_gender.changed` | предупреждение | `role_gender` отличается от пола голоса собеседника прошлого дня с той же ролью | `lesson` | 0 | 0 |

- **Фатальных 10**: `line.ne_frame`, `filler.ungrammatical`, `check.shape`, `listening.shape`, `exchange.shape`,
  `exchange.second_question`, `exchange.repeats` + три новых. Кодов всего 57 (47 предупреждений).
- **Потолок починок** — две карточки на день, порядок «каркас → обмен → реплика → check → listening → слово»; дальше `failed`.
  **Упёрлись**: банк, день 2 v4.6 (`v5`, `v7` — обе аббревиатуры, `ready`) и врач, день 1 v4.6 (`x3` — второй вопрос, `B5` —
  реплика не по каркасу, `ready`). `failed` — ни одного.
- **Перепроверка починённого слова** сервером: `used_in` точен, слова нет среди слов прошлых дней, не аббревиатура, нет в словаре
  дня под другим id — иначе `refused` (оплачено, не вставлено, карточка потрачена). Живьём отказов не было.
- **«Правило, не модель» (> 20 %)** — ни один новый код не близок: максимум 7.0 % (`frame.known_native_repeat`, v4.5).
- `role_gender.changed` живьём не проверен: во всех шести планах собеседник дня 2 — другая роль. Первый живой случай — план Дена
  NKKGFF (обе сцены — Agent, §6).
- `exchange.second_question` на 12 днях v4.6: 1 (врач, день 1, починен обменом `x3`).

## §3. «Было / стало»: день 2 на v4.5 и на v4.6

Шесть тем GEN-2b (ru→en). На каждую: план (`plan-builder-v2`) → день 1 на v4.6 (как в бою: порог, P2R, судья) → **день 2 дважды на
одном плане с одним днём 1**: «было» — `lesson_day.v4.5` на коде до наряда (`f02cbc64`, отдельный контейнер из `git archive`:
без ролей и без `EARLIER_DAYS`, старый порог), «стало» — v4.6 на коде наряда. Дни 2 в сцены не записывались. Находки считает
**один валидатор наряда по ответу модели до починок**, против одного дня 1 — поэтому у «было» есть новые коды, которых v4.5 не знал
(и которые его порог пропустил бы ученику: починок у «было» 0). Цена — `ModelCost` по usage лога исходящих, кэшированный вход по
цене кэша; каждый вызов дня найден в логе по своим токенам (`tools/export.php`).

| тема | день 2 | термины дня 1 | каркас known | native | twin | adjacent | second_question | abbreviation | фатальных | починок | итог | повторённые слова | цена дня | из кэша | время |
|---|---|---|---|---|---|---|---|---|---|---|---|---|---|---|---|
| interview | было (v4.5) | 0 | 0 | 0 | 0 | 0 | 0 | 0 | 0 | 0 | ready | — | $0.0850 | 0 % | 47 с |
| interview | стало (v4.6) | 0 | 0 | 0 | 0 | 0 | 0 | 0 | 0 | 0 | ready | — | $0.0667 | 81 % | 44 с |
| rent | было (v4.5) | 1 | 0 | 1 | 0 | 0 | 0 | 0 | 1 | 0 | ready | deposit | $0.0694 | 77 % | 46 с |
| rent | стало (v4.6) | 0 | 0 | 0 | 0 | 0 | 0 | 0 | 0 | 0 | ready | — | $0.0655 | 80 % | 42 с |
| bank | было (v4.5) | 0 | 0 | 0 | 0 | 0 | 0 | 1 | 1 | 0 | ready | — | $0.0625 | 81 % | 41 с |
| bank | стало (v4.6) | 0 | 0 | 0 | 0 | 0 | 0 | 2 | 2 | 2 | ready | — | $0.0833 | 47 % | 44 с |
| restaurant | было (v4.5) | 0 | 0 | 2 | 2 | 0 | 0 | 0 | 0 | 0 | ready | — | $0.0624 | 80 % | 41 с |
| restaurant | стало (v4.6) | 0 | 1 | 1 | 0 | 0 | 0 | 0 | 1 | 1 | ready | — | $0.0740 | 56 % | 44 с |
| airport | было (v4.5) | 1 | 0 | 0 | 0 | 0 | 0 | 0 | 1 | 0 | ready | backpack | $0.0665 | 78 % | 46 с |
| airport | стало (v4.6) | 0 | 0 | 0 | 0 | 0 | 0 | 0 | 0 | 0 | ready | — | $0.0638 | 82 % | 51 с |
| doctor | было (v4.5) | 1 | 1 | 0 | 0 | 0 | 0 | 0 | 2 | 0 | ready | sore throat | $0.0650 | 78 % | 43 с |
| doctor | стало (v4.6) | 0 | 1 | 0 | 0 | 0 | 0 | 0 | 1 | 1 | ready | — | $0.0716 | 74 % | 50 с |
| **итого** | **было** | **3** | **1** | **3** | **2** | **0** | **0** | **1** | **5** | **0** | **6 / 6** | | **$0.0684** | **65 %** | 41–47 с |
| **итого** | **стало** | **0** | **2** | **1** | **0** | **0** | **0** | **2** | **4** | **4** | **6 / 6** | | **$0.0708** | **67 %** | 42–51 с |

(«из кэша» — доля кэшированных токенов во входе всех вызовов дня; у interview «было» 0 % — первый вызов v4.5 за день, кэш холодный.
Время — стена дня: урок, починки, судья.)

**Три уровня цен** (среднее на день по `ModelCost`; со скидкой кэша / по прейскуранту):

| | со скидкой кэша | по прейскуранту | диапазон (кэш) | из чего |
|---|---|---|---|---|
| **день 1** (v4.6, `EARLIER_DAYS` = `none`) | **$0.0822** | $0.0941 | $0.0591–0.1176 | урок $0.0745, судья $0.0021, починки $0.0057 (врач — 2 карточки) |
| **день 2 v4.5** | **$0.0684** | $0.0814 | $0.0624–0.0850 | урок (вход 7 456–7 505), судья $0.0020, починок нет |
| **день 2 v4.6** | **$0.0708** | $0.0897 | $0.0638–0.0833 | урок $0.0622 (вход 8 566–8 662, из них 7 936 из кэша), судья $0.0016, починки $0.0070 |

Строитель плана: $0.0119–0.0192 за план, 9–14 с. Прошлый день в `EARLIER_DAYS` — ≈ 1 100 токенов входа вне кэша (≈ $0.0028).
Урок v4.5 кэшировался на старом коде так же (7 936 токенов): промпт и раньше шёл системным сообщением первым — правило наряда
закрепляет порядок и держит схему починки одинаковой, скидку оно не открывало. Починки кэша почти не получили: одна из шести
(врач, `p4`: 2 816 токенов); две починки слова банка с байт-в-байт одинаковыми системным промптом и схемой шли с разницей 2 с —
у второй 0 из кэша (кэш вендора best-effort).

**Что прочитать архитектору** (факты сюжета и роли код не проверяет — выгрузки `interview.md`, `rent.md`, `bank.md`,
`restaurant.md`, `airport.md`, `doctor.md`: день 1, день 2 «было», день 2 «стало»):

- **Врач, `p4`**: v4.6 повторил каркас дня 1 «He has ___.» с тем же наполнением «a fever and a sore throat»; P2R сделал «He's got
  ___.» с родным «Что с ним? — ___.», реплика осталась «He's got a fever and a sore throat.» / «У него температура и болит горло.».
  Порог пройден (тождество лексическое), но ученик слышит ту же фразу дня 1, а родной каркас не переводит целевой.
- **Банк, `v7`**: «ATM / банкомат» → «Visa sign / знак Visa» (chunk из «any ATM with the Visa sign») — формально слово урока, по
  сути сомнительно. `v5`: «PIN» → «activation code / код активации» — нормально.
- **Ресторан, `p2`**: «That's ___.» → «It's ___ altogether.» / «Всего ___ .» (пробел перед точкой в родном каркасе).
- `frame.known_native_repeat` в ресторане v4.6: «Можно нам ___?» при «Can we get ___?» дня 1 — к решению «фатально или нет».

Файлы: `table.md`, `compare.json` (все коды по дням, цена, кэш), `answers/` (ответы модели как есть), `final/` (служащие уроки после
починок), `runs/` (записи прогонов с входами и вызовами), `live-plans.log`, `live-after.log`, `live-before.log`; инструменты —
`tools/topics.php`, `tools/live.php` (планы и дни v4.6), `tools/before.php` (v4.5 на коде `f02cbc64`), `tools/export.php`.

## §4. Расхождения — что сессия решила сама или сделала иначе, чем в наряде

1. **«Готовый» прошлый день** для `EARLIER_DAYS` — урок написан: `ready` **или** `illustrating` (урок принят, фото ищутся). Фото
   на историю не влияют, а день 2 заказывается закрытием дня 1, когда фото дня 1 могут ещё искаться.
2. **Разделитель дней** в `EARLIER_DAYS` — пустая строка; **короткая форма P2R** — с заголовком `Day {n}` над `Frames:`/`Words:`
   (наряд: «только Frames и Words») — иначе слова двух дней сливаются в один список без границы.
3. **Пол в строке дня** — пол голоса собеседника сцены (`partner_voice_gender`, то, что слышит ученик), а не `role_gender` ответа;
   сцена без него — пол по умолчанию. `role_gender.changed` сверяет с ним же.
4. **Схема починки — одна на вид**, без enum адреса, шага и id каркасов дня (было в GEN-2b): схема идёт вендору раньше правил и
   выбивала бы их из кэша на каждой карточке. Id и шаг держит сервер; реплика обмена на каркасе не из урока — ответ не по форме.
5. **Цитата разделов терпит отсутствующий раздел** (THE STORY SO FAR нет в v4.5) — иначе откат урока на v4.5 одной константой
   ломал бы починку.
6. **Повтор оборванного вызова снят у всех вызывающих `ContentModelPort`**, не только у плана: адаптеры общие, а дефект тот же —
   `retry(… when)` повторял `ConnectionException`, то есть 60-секундный обрыв покупался дважды. Повтор остался только на ответ
   вендора 408/409/429/5xx.
7. **Упавший урок сам не пересобирается** (`needsLesson` — только `pending`): раньше открытие и закрытие дня пересобирали
   `failed`. Наряд §11 велел «провал — по существующей политике пересборки», дополнение C — «автоповтора нет, повтор только руками»;
   выбрано второе: провал из-за таймаута может быть оплачен. Повтор — `POST …/lesson/retry` ученика.
8. **Судья окна (`slot_judge`) остался на 8 с и одной попытке** (п. 326): он синхронный, внутри запроса ученика. «Судья» в
   дополнении про 180 с — судья швов.
9. **Журнал пишут адаптеры `ContentModelPort` и песочница** (всё, чем пользуется план); прямые адаптеры первой генерации
   (`OpenAiWordLookup`, `OpenAiCollectionGenerator` и др.) — нет, ROADMAP.
10. **«Журнал расходов» — новая таблица `model_calls`**, а не колонки токенов на строках плана и сцены: там уже лежит сумма по
    `ModelCost`, а строка до вызова может быть только своей строкой. Назначение (`purpose`) — метка лога исходящих: у всех вызовов
    плана `plan` (урок, починка, судья различаются моделью и временем) — ROADMAP.
11. **`ModelCost` считает кэшированный вход по цене кэша** — и цены на строках сцены (`cost_usd_lesson`) теперь со скидкой: с
    числами GEN-2b «по коду» (прейскурант) они сравниваются через колонку «по прейскуранту» §3.
12. **`building` — только день, следующий в очереди** (день перед ним закрыт или это день 1 живого плана), а не любой будущий
    день без урока: дальние дни без урока — норма (урок им ещё не заказан), и `building` на них висел бы днями.
13. **Закрытие дня-сцены перед повторением ничего не заказывает**; урок дня-сцены после повторения заказывает закрытие
    повторения (буква §11.2). После расширения, переноса и удаления сцены — только урок текущего дня.
14. **Укорачивание под дату события не менялось** (`daysUntil`, день события не учебный), хотя открытие дней теперь считает
    день события учебным: наряд называл только открытие.
15. **Адаптеры ждут ответ столько, сколько сказал вызывающий** (180 с у плана), песочница — `PLAYGROUND_TIMEOUT` 180 с; job урока —
    все его возможные вызовы × 180 + 60 = 960 с (не «3× наблюдаемой задержки»: job делает до пяти вызовов), `retry_after` Redis и
    окно «мёртвой» сборки — 1 020 с.
16. **`openapi-admin.yaml` не попал в коммит генерации** (правка песочницы 202 + `GET /playground/runs/{id}` + 65 000) и идёт
    коммитом документов; код и wt_admin — в `a51c0b4d`. В тот же коммит документов — правки тестов после мутаций.
17. **`FakePlanModel` остался с порядком урока, на котором построены тесты раздачи**, и под v4.6 даёт одну находку
    `frame.adjacent_repeat@x8`; тесты с точным списком находок берут `planCleanLesson()`. Дни N фейка помечены `-N`, чтобы история
    не делала их фатальными.
18. **«Было» — на коде до наряда в отдельном контейнере**, а не v4.5 на коде наряда: «как есть» значит и без ролей, и без истории,
    и со старым порогом; находки пересчитаны валидатором наряда.
19. **Цена дня в выгрузке — по вызовам дня, найденным в логе по токенам**: первая выгрузка брала окно времени и в 5 из 6 тем
    захватывала судью предыдущей темы (исправлено до отчёта, `table.md` пересобран).
20. **wt_admin правлен** (вопрос Дену в начале, ответ «править и wt_admin»): без него песочница после 202 ничего бы не показала.

## §4a. Журнал расходов: сверка с панелью OpenAI, lost, cached_tokens

**Журнал `model_calls`** (e2e, 17.09 13:17:47–13:28:36 UTC — всё, что шло через код наряда):

| статус | вызовов | вход | из кэша | выход | $ |
|---|---|---|---|---|---|
| `completed` | 36 | 162 624 | 96 256 (59 %) | 57 430 | 0.9997 |
| `failed` | 0 | | | | |
| `lost` | **0** | | | | |

**Все вызовы OpenAI за 17.09 (UTC) по нашим логам** (`api_request_logs`, usage → `ModelCost`):

| где | что | вызовов | $ со скидкой кэша | $ по прейскуранту | без ответа |
|---|---|---|---|---|---|
| e2e | наряд: 6 планов, 6 дней 1, 6 дней 2 v4.6 (новый код, = журнал) | 36 | 0.9997 | | 0 |
| e2e | наряд: 6 дней 2 v4.5 на коде до наряда (журнала нет) | 12 | 0.4107 | | 0 |
| e2e | итого наряд | 48 | **1.4104** | 1.7047 | 0 |
| e2e | Costs API `/v1/organization/costs` — 403 (у ключа нет `api.usage.read`) | 1 | 0 | 0 | — |
| wordtrainer | песочница Дена до наряда (старый код): 11:51 — **обрыв 60 с** (cURL 28, 0 байт), 11:53 и 11:57 — ответы | 3 | 0.1997 + обрыв | | **1** |
| **итого по логам** | | **51** | **1.6101 + оборванный** | | **1** |

Остальные базы (`wordtrainer_test*`, `_a_/_b_test`, `smoke`, `s1e`) — 0 вызовов OpenAI, кроме трёх подделок `Http::fake` в
`wordtrainer_test` (10/20 токенов).

**Сверка с панелью OpenAI** — ждёт сумму панели Usage/Costs за 17.09 (UTC) от Дена: у ключа приложения нет права
`api.usage.read`, и сессия её не видит. Ожидание: панель = $1.61 + оборванный вызов песочницы (такой же промт следом стоил
$0.0925 и $0.1072) ± округления вендора ≈ **$1.70–1.72**, если ключ в этот день не тратили вне этих баз. Расхождение больше —
значит, есть траты вне логов (другой стенд, чужая сессия, прямые адаптеры без журнала) или цена `ModelCost` ≠ цене вендора.
**lost**: в журнале — 0; фактически за день — 1 (обрыв 11:51, до появления журнала; ровно этот дефект и закрыт: теперь такая
строка остаётся `started` → `lost`, а ответ ждётся 180 с).

`cached_tokens`: доля кэша во входе — 59 % по журналу, 61 % по всему наряду; скидка кэша — $1.7047 → $1.4104 (−17 %).

## §4b. Расписание дней и сборка N+1

**Открытие дней** (`Plan`, `docs/plan-v2.md` §5, DECISIONS п. 335):

- `closeDay` пишет `opens_on` следующего дня = календарный день после `opened_at` дня N **в зоне ученика**. Открыл 17.09 в 23:00
  Киева, закрыл 18.09 в 01:00 — следующий день доступен 18.09 с 01:00 (прежнее правило давало 19.09).
- `isCatchingUp(today)`: даты события нет или она прошла → нет; иначе календарных дней `today…event` включительно ≤ числа не
  закрытых дней → да. Тогда `openDay` не смотрит на дату; день перед ним всё равно должен быть закрыт.
- Порядок проверок `openDay`: день уже идёт → как есть; закрыт → 409; предыдущий не закрыт → `plan_day_locked`
  (`blocked_by_day`); урок не готов → `plan_day_building`; дата не пришла и не догоняем → `plan_day_locked` (`opens_on`); урок
  упал → `plan_lesson_not_ready`.
- `GET /plans`: `catch_up: true|false` (в `required`); `plan:shift-day` — без изменений.

**Сборка урока N+1** (`CloseDayHandler`, DECISIONS п. 336):

- Единственный триггер после сборки плана — закрытие дня N: следующий день — день-сцена, его урок `pending`,
  `NextDayAccess::nextDayAllowed` = да (`EveryNextDayAllowed`). Постановка — после коммита транзакции, в которой день закрыт под
  блокировкой строки плана; повторное закрытие — 409 `plan_day_not_open` раньше постановки.
- Открытие дня модель не зовёт. Закрытие повторения/репетиции заказывает день-сцену после них. День 1 — с планом. После
  расширения, переноса, удаления сцены — только урок текущего дня.
- День в очереди без урока: маршрут и окно — `building`, `allowed_action: null`; открыть — 409 `plan_day_building`
  (`meta.day`, `meta.lesson_status`); напоминание по такому дню не шлётся. OpenAPI и `plan-api.md` обновлены, lint зелёный.

**Тесты** (формулировки дополнения F): «открыт 23:00, закрыт 01:00 → доступен в 01:00» — `PlanScheduleTest` «opens day 2 at
01:00…»; «до события 3 дня, непройденных 3 → замка нет, catch_up true» — «does not lock the next day while…» (и `catch_up` на
проводе — `PlanApiTest`); «без даты события → замок» — «locks the next day of a plan with no event date…»; «в день события
репетиция открывается» — «opens the rehearsal on the event's own day…»; «закрытие дня N → ровно одна сборка N+1», «повторное
закрытие → ничего», «открытие дня N сборку не ставит» — `NextDayBuildTest` «asks for the next day's lesson once…»; «доступа нет →
сборки нет (подмена ответа)» — «asks for no lesson of a next day the learner may not have»; повторение — «asks for the scene day
after a review…»; `building` и 409 — «shows the next day building…» (+ юнит в `PlanScheduleTest`); упавший урок — «asks again for
a failed lesson only when the learner retries it». Все — под мутациями 28–37.

**Не проверено живьём**: на устройстве и через `:8001` расписание не гонялось (живых вызовов у §11 нет по наряду); зона ученика —
`LearnerCalendar` как был.

**Handoff клиенту** (наряд полировки): плита дня в `building` — «собираем урок», опрос `GET /plans` до `ready` (или до
`failed` — тогда кнопка повтора); 409 `plan_day_building` на открытии — та же плита; подпись «догоняем» при `catch_up: true`;
замок `locked` с `opens_on` — как был. Песочница админки уже опрашивает (wt_admin).

## §5. Расход наряда

| что | вызовов | $ со скидкой кэша | $ по прейскуранту |
|---|---|---|---|
| 6 планов (`plan-builder-v2`) | 6 | 0.0814 | 0.1131 |
| 6 дней 1 v4.6 (урок, 2 починки, 6 судей) | 14 | 0.4933 | 0.5648 |
| 6 дней 2 v4.5 «было» (урок, судья) | 12 | 0.4107 | 0.4884 |
| 6 дней 2 v4.6 «стало» (урок, 4 починки, 6 судей) | 16 | 0.4250 | 0.5384 |
| Costs API (403) | 1 | 0 | 0 |
| **итого** | **49** | **$1.4104** | **$1.7047** |

Кап $3. Голос и фото не покупались (диспетчер в харнессе заглушен); сухой прогон плана на фейке — $0. Мутации и тесты — без сети.

## §6. Команда для Дена: пересобрать день 2 плана NKKGFF на `wordtrainer` на v4.6 с голосом

> С доработки та же команда пересобирает день на `lesson_day.v4.7` / `lesson_card_repair.v1.3` — сервер берёт текущие промты (§8).

**НЕ выполнялось.** План `01M2NKKGFF8H7TQG8HB75NRJP1` «Поиск квартиры» (Ден, `Europe/Bucharest`, 3 дня, событие 18.09): день 1
закрыт, день 2 `locked` (`opens_on` 17.09), сцена `01M2NKM0B1VB7CG1WZV83CBKRZ` «Просмотр жилья» — урок `lesson_day.v4.5`, голос
38 строк (270 кредитов · 1 097 символов · $0.054), карточек дня 2 нет. На `wordtrainer` не применена одна миграция —
`model_calls`. Код GEN-3 приложение уже служит (дерево смонтировано), так что **день 2 открывать до команды нельзя**: открытый день
раздаётся из урока v4.5, и шаг 4 остановится.

Цена: урок v4.6 с судьёй ≈ $0.06–0.09 (+ до двух починок по $0.01–0.02), голос ≈ 270–420 кредитов ≈ $0.05–0.08 (покупается сам
после урока, `SPEECH_ENABLED=true`), фото — бесплатно. Итого ≈ $0.12–0.20.

Старый голос удаляется явно: озвучка хранится по (сцена, ссылка строки, голос), не по тексту, — без удаления новые реплики v4.6
играли бы звуком v4.5.

```bash
cd /Users/yalantisdenys/eng-std/backend2
```

```bash
scripts/db-backup.sh
```

```bash
docker compose exec -T app php artisan migrate --force
```

```bash
docker compose restart horizon
```

```bash
cat > storage/app/rebuild-nkkgff-day2.php <<'PHP'
<?php
require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Modules\Plan\Application\Port\LineAudioStore;
use App\Modules\Plan\Application\Port\PlanDispatcher;
use App\Modules\Plan\Domain\Repository\PlanRepository;
use App\Modules\Plan\Domain\ValueObject\PlanSceneId;
use Illuminate\Support\Facades\DB;

if (config('database.connections.pgsql.database') !== 'wordtrainer') {
    throw new RuntimeException('not wordtrainer - stop');
}
$scene = PlanSceneId::fromString('01M2NKM0B1VB7CG1WZV83CBKRZ');
$day = DB::table('plan_days')->where('scene_id', $scene->value)->first();
if ($day === null || ! in_array($day->status, ['locked', 'open'], true) || DB::table('day_cards')->where('day_id', $day->id)->exists()) {
    throw new RuntimeException('day 2 is already dealt or walked - stop');
}
DB::transaction(function () use ($scene): void {
    $plans = app(PlanRepository::class);
    $row = $plans->findSceneForUpdate($scene) ?? throw new RuntimeException('no scene');
    $row->resetLesson();
    $plans->saveScene($row);
});
$audio = app(LineAudioStore::class);
foreach ($audio->ofScene($scene) as $line) {
    $audio->drop($line);
}
app(PlanDispatcher::class)->buildLesson($scene);
echo "day 2 lesson queued\n";
PHP
docker compose exec -T app php storage/app/rebuild-nkkgff-day2.php && rm storage/app/rebuild-nkkgff-day2.php
```

Проверка (через минуту-две: урок, затем голос и фото):

```bash
docker compose exec -T db psql -U wordtrainer -d wordtrainer -c "select lesson_status, prompt_version_lesson, lesson_fail_reason, cost_usd_lesson from plan_scenes where id = '01M2NKM0B1VB7CG1WZV83CBKRZ'"
```

```bash
docker compose exec -T db psql -U wordtrainer -d wordtrainer -c "select started_at, status, model, cached_tokens, cost_usd from model_calls order by started_at desc limit 6"
```

```bash
docker compose exec -T app php artisan plan:speak-report --plan=01M2NKKGFF8H7TQG8HB75NRJP1
```

Пока урок пишется, телефон показывает день 2 как `building`. Ждать: `lesson_status = ready`, `prompt_version_lesson =
lesson_day.v4.6`, в отчёте голоса у дня 2 — строки v4.6. Урок `failed` — повтор только руками, `POST
/api/v1/plans/01M2NKKGFF8H7TQG8HB75NRJP1/scenes/01M2NKM0B1VB7CG1WZV83CBKRZ/lesson/retry` от имени Дена; откат целиком — дамп из
второй команды. Первый живой `role_gender.changed`, если v4.6 даст агенту другой пол: у обеих
сцен роль Agent, голос дня 1 — женский.

## §7. Коммиты

| коммит | что |
|---|---|
| `a51c0b4d` | генерация: урок v4.6, P2R v1.2, история и роли, коды, журнал вызовов, таймауты, песочница job'ом (+ wt_admin) |
| `ce3154b7` | расписание: открытие от открытия, «догоняем», сборка N+1 на закрытии, `building` |
| `c9569adc` | документы: канон, контракт админки, реестр, DECISIONS 330–336, ROADMAP, handoff, README модулей, отчёт, выгрузки, правки тестов после мутаций |

Хеш коммита документов вписан следующим коммитом (как в SESSION-1c).

## §8. Доработка (17.09, решения архитектора после сдачи)

Живых вызовов нет. Промты `lesson_day.v4.7.md` (sha256 `44dcdd9b49106a3547b89d4c45e798e7c314c174d40d602162115b386a3c6da7`) и
`lesson_card_repair.v1.3.md` (sha256 `9867f0b0a709dfb345c3f6fc4d5e66211bfc15419568297654aa890a087923cb`) положил Ден в incoming;
хеши совпали с нарядом, файлы перенесены байт-в-байт, incoming снесён.

### 8.1. Промты

`diff` к v4.6 / v1.2 — ровно заявленные правила (и строка версии в заголовке):

- **v4.7, VOCABULARY** (и строка самопроверки): аббревиатура или акроним — слово дня, только когда в NATIVE_LANGUAGE есть обычное
  слово для неё (ATM → банкомат, PIN → ПИН-код); без такого слова (API, CI/CD, HR) — нет, в репликах и наполнениях — как есть.
- **v1.3, каркас**: починенный каркас — другой шаблон цели, чем у прошлых дней и других каркасов дня, «не тот же шаблон со словом,
  заменённым синонимом»; родной каркас — простой естественный перевод нового, даже если совпал с родным текстом чужого каркаса,
  никогда «вопрос с тире» или другой приём, чтобы родной текст отличался; то же — в рецепте «слово или каркас прошлого дня». **Слово**:
  «не аббревиатура без обычного родного слова».

`PlanPromptFiles` — на v4.7 / v1.3; откат — `v4.6` / `v1.2` одной константой. Файлы `v4.5` / `v1.1` **сняты** (одно поколение
отката, как в GEN-2b; в git до коммита доработки), и цитата разделов снова строгая — терпимость к отсутствующему разделу была нужна
только откату на v4.5 (в нём нет THE STORY SO FAR); теперь раздела нет — пара промптов сломана, вызова нет. `FakePlanModel`,
фикстуры `docs/fixtures/day-doctor*.json` и тесты — на новые версии.

### 8.2. `vocab.abbreviation` — предупреждение

`LessonGate::FATAL` — 9 кодов (`vocab.abbreviation` убран); кодов 57, предупреждений 48. Перепроверка починённого слова больше не
отвергает аббревиатуру (`used_in`, прошлые дни, дважды в дне — как были). Причина находки теперь: «a word of the day only when the
learner's language has an everyday word for it». Код считает, судит модель.

**Пересчёт сохранённых ответов GEN-3** (без вызовов, по `compare.json`): день 2 v4.5 — фатальных 5 → 4 (банк 1 → 0), день 2 v4.6 —
4 → 2 (банк 2 → 0: PIN и ATM — слова дня с предупреждением, обе починки банка не понадобились бы). Починок на днях 2 v4.6 по этим
правилам — 2 вместо 4, в потолок двух карточек не упёрся бы ни один день. Живьём v4.7 не проверялся.

### 8.3. FINDINGS починки каркаса — без тождества родного шаблона

Решение архитектора (вопрос сессии перед кодом): вырезать только находки о тождестве родного шаблона — `frame.known_native_repeat` и
`frame.twin`, у которого совпал лишь `frame_native`; швы, согласование и фатальные находки наполнений цитируются, как были — ради них
P2R и существует. `LessonCard::cites` (Domain): у карточки каркаса `frame.known_native_repeat` — не цитируется; `frame.twin` —
цитируется, только если другой каркас урока делит с этим **шаблон цели** (`FrameText::identity`, структурно, не по тексту
причины); прочее — цитируется; у других видов карточек фильтра нет. У каркаса только такие находки — `nothing_to_repair`, вызова нет
(v1.3 такую «починку» запрещает). Фильтр — у любой починки каркаса (сборка и `plan:repair-card`), не только по `frame.known_repeat`:
правило v1.3 про родной перевод — общее для каркаса.

Честно о живых вызовах GEN-3: ни в одну из шести починок находка о родном шаблоне не уходила — врачу (`p4`) ушли
`frame.native_agreement` и `frame.known_repeat`, ресторану (`p2`) — `frame.known_repeat`. «Что с ним? — ___.» вызвало правило v1.2
(«тот же родной шаблон — тот же каркас»), его снимает v1.3; фильтр закрывает ту же дорогу со стороны находок (родной близнец у
чинимого каркаса, ручная починка по `frame.twin`).

### 8.4. Разбор урока: пробел перед знаком конца

`LessonParser` снимает пробелы перед знаком, которым кончается `frame_native` и `native` каждого наполнения (`FrameText::withEndMarkClosed`,
знаки `. ! ? …` — те же, что у сверки реплики): «Всего ___ .» → «Всего ___.». Разбор идёт до валидатора, судьи швов, починки и
карточек; хранимый `lesson_json` не переписывается — уроки читаются закрытыми при каждом разборе. Внутренние знаки («Что с ним ? —
___ !» → «Что с ним ? — ___!») и родной текст реплик не трогаются — правило только о каркасе и наполнении.

Сохранённые уроки с таким пробелом (чтение): e2e — 12 родных каркасов в 213 сценах (v4.4 и v4.6: «Сейчас я работаю ___ .», «Это
___ .»…), `wordtrainer` — 2 в 9 сценах (планы Дена, v4.5: «Это выглядит ___ .», «Я живу с ___ .»). Уже розданные карточки хранят свой
текст; нерозданные дни и починки читают закрытым.

### 8.5. Канон

`docs/plan-v2.md` §0/§2 (версии, откат, FINDINGS каркаса, строгая цитата), §4 (9 фатальных, 48 предупреждений, разбор, строки
`vocab.abbreviation`, `frame.known_native_repeat`, `frame.twin`, перепроверка слова); DECISIONS — пп. 330, 331, 333 исправлены по
месту («доработка») и два пункта «Отменено» (аббревиатура фатальна; «тот же родной шаблон — тот же каркас» при починке); реестр
промптов — LESSON v4.7, P2R v1.3, строки отката v4.6 / v1.2, история; ROADMAP — решение по `frame.known_native_repeat` закрыто,
качество починок — «проверить живьём на v4.7», строка «укорачивание под дату события (`daysUntil`) считает день события не учебным,
открытие — учебным; выровнять при следующем касании расписания» (расхождение 14); README модуля Plan; handoff.

### 8.6. Тесты и мутации

- `LessonGateTest` — «holds the day for exactly the nine fatal codes» (`vocab.abbreviation` — среди предупреждений).
- `StoryBuildTest` — отказ перепроверки без аббревиатуры; **«takes a repaired word that is an abbreviation, and only counts it»**;
  **«tells the repair of a learned frame its target match and the frame's other findings, not a native pattern it shares»** (день 2 с
  каркасом дня 1, без знака конца и с родным шаблоном своего `p2`: в починку уходят `frame.known_repeat` и `frame.no_end_punct`, родной
  близнец — нет, хотя он есть и остаётся предупреждением).
- `LessonRepairTest` — **«asks nothing for a frame whose only finding is a native pattern another frame shares»**.
- `LessonCardTest` — **«tells a frame repair every finding but that its native pattern is another frame's»** (близнец по цели —
  цитируется, по родному — нет, `known_native_repeat` — нет, согласование и фатальные наполнений — да, у обмена фильтра нет).
- **`LessonNativeMarksTest`** — «reads a native frame and a filler's native text without the space before the mark they end with»
  (дефект — «Всего ___ .» ресторана; внутренние знаки и реплики не тронуты; судья швов получает шаблон закрытым).
- `LessonValidatorTest` — причина теста аббревиатуры: «считается, не держит».

**Мутации — 45 из 45 пойманы утверждениями** (`mutations.md`, `mutations.log`): №14 и №18 перевёрнуты под новое правило («аббревиатура снова
фатальна», «перепроверка отказывает аббревиатуре»), №13 (аббревиатура с точками и слэшем — находка ставится) — как была; новые №39–45:
родной близнец цитируется, близнец по цели вырезан, `known_native_repeat` цитируется, фильтр у не-каркаса, разбор не закрывает знак у
каркаса / у наполнения, пробел снимается и перед внутренним знаком. Первый прогон поймал №18 через `ErrorException` (у упавшего урока
нет `lesson_json`) — тест сделан устойчивым, мутация ловится утверждением.

### 8.7. Ворота

Один раз, в конце, `docker compose exec -T app composer check`: OpenAPI `openapi.yaml` и `openapi-admin.yaml` ok; deptrac —
0 нарушений (uncovered 3 — прежние); PHPStan L8 — 0 ошибок; Pest — **2 303 passed, 16 109 assertions, 51.0 с**. `flutter analyze` —
без замечаний (`mobile/` не тронут). `invariant-reviewer` по диффу доработки — CLEAN (Domain чист: `LessonCard` берёт
`Domain\Check\LessonCodes`, разбор — `Domain\Service\FrameText`; ключ реплики строится из текстов цели и закрытием родного
текста не задет).

### 8.8. Коммит доработки

Один коммит через хук — следующий за `5307da6d` (`docs/fix(plan): доработка GEN-3 …`); его хеш — в сдаче наряда.
