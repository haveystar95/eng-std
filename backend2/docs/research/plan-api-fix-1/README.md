# PLAN-API-FIX-1 — четыре дефекта контракта плана

Наряд: backend2, четыре дефекта, найденные golden-тестами мобилки и прогоном на стенде 11.09.
Контракт — `docs/plan-api.md`; PLAN-GEN закрыт коммитом `b816951b`. Мобильный код не тронут.

Итог: все четыре починены, каждый закрыт тестом по канону (тесты сначала проверены на НЕисправленном
коде — падают все пять), прогон на стенде через HTTP прошёл по всем шагам, ворота зелёные.

---

## Дефект 0 (блокер) — гонка сборки и запуска, план пропадает

### Причина в коде

`EloquentPlanRepository::save(Plan)` пишет **весь агрегат** — строку плана, все сцены и все дни —
из того снимка, что держит вызывающий. Два job'а держали этот снимок поперёк внешнего вызова:

| файл (до правки) | что держал | что писал |
|---|---|---|
| `BuildLessonHandler.php:58` — claim | `findByIdForUpdate($planId)`: блокировка на ВЕСЬ план | `save($plan)` |
| `BuildLessonHandler.php:86,95,100` — после вызова модели (≈30 с) | тот же снимок | `save($plan)` целиком |
| `AttachPlanImagesHandler.php:47,60` — после поиска фото (секунды на фото) | `findById($planId)`, без блокировки | `save($plan)` целиком |

Порядок на стенде 11.09 (аккаунт телефона `vitalnost.meditation@gmail.com`, план
`01M28293HB4ECWP1DSBDNYEKDZ`), ровно как описано в наряде:

```
11:05:15  BuildLessonJob прочитал план (status=ready, started_at=null, день 1 locked)
11:05:37  POST /start → status=active, started_at, день 1 open
11:05:40  BuildLessonJob дописал СВОЙ снимок целиком → status=ready, started_at=null, день 1 locked
11:05:42–47  AttachPlanImagesJob дописал свой снимок ещё раз
```

Итог в базе: план `ready`, `started_at` пуст, день 1 заперт, `opens_on` null — при этом урок
готов и картинка прикреплена. Оба сегодняшних плана лежали так (подтверждено SQL до правки).

### Что изменено

**Ни один job больше не пишет агрегат.** Правило записано в `PlanRepository` (docblock) и в
`app/Modules/Plan/README.md`: `save()` — только для обработчика, который прочитал план под
блокировкой и пишет его в той же транзакции.

- `PlanRepository` — четыре точечных метода вместо `save()` для job'ов:
  `findSceneForUpdate()` (:47), `saveScene()` (:50), `attachCoverImage()` (:56),
  `attachSceneImage()` (:59); плюс `PlanTermRepository::attachImage()`.
- `BuildLessonHandler` — блокирует и claim'ит **строку сцены**, не план
  (`findSceneForUpdate` :51); план перечитывается после claim'а только ради уровня и языков и
  **не пишется вообще**; все три записи — `saveScene()` (:59, :91, :100, :105), то есть
  `UPDATE plan_scenes SET … WHERE id = ?`, без `plan_id`/`user_id` в наборе колонок.
- `AttachPlanImagesHandler` — каждая запись это одно фото в свои три колонки, **условно**:
  `UPDATE … WHERE id = ? AND image_url IS NULL`. Условие в самом UPDATE, а не в прочитанном
  снимке, — поэтому «только то, у чего фото нет» остаётся правдой и через десять секунд поиска.
- `BuildPlanHandler` — у сборки плана точечной колонки нет (её запись И ЕСТЬ агрегат: сцены плюс
  раскладка по дням), поэтому по второму правилу наряда: план **перечитывается под
  `lockForUpdate` внутри пишущей транзакции** и перепроверяется (`write()`, :96). Побочно это
  закрывает и гонку с удалением: план, удалённый пока отвечала модель, больше не воскресает
  в `ready`.
- `PlanMapper::sceneColumns()` / `dayColumns()` больше не содержат `plan_id` и `user_id` — их
  добавляет только `save()` при создании строки. Точечный UPDATE не может переписать владельца.
- `GetPlanBuildHandler` (:30) — у `GET /plans/{id}/build` было пять значений вместо четырёх:
  запущенный во время сборки план отдавал `active`, слова, которого у этой ручки в контракте нет.
  Теперь любой собранный план читается как `ready`.

Блокировок хватает: сцену и её картинку пишут два разных job'а, но в одну и ту же строку разными
колонками — row lock самого UPDATE их сериализует, и «ту же блокировку, что и сборка» брать не
нужно, потому что общей блокировки на план больше нет ни у кого из них.

### Тест

`tests/Feature/Plan/PlanContractTest.php` — «keeps a plan started while its lesson was still
building». `FakePlanModel` нажимает «Начать» **изнутри вызова модели** (то есть ровно в окне между
claim'ом и записью), после чего job урока и job картинок доигрывают. Проверяется: план `active`,
`started_at` на месте, день 1 `open` со слотом `today`, урок `ready`, фото сцены и обложка на
месте, строка в БД `active` — и день 1 реально открывается.

---

## Дефект 0б — незапущенный план невидим

### Причина в коде

`GetCurrentPlanHandler` звал `PlanRepository::findLiveFor()` — `WHERE status IN ('active',
'overdue')`. План в `ready` (собран, не запущен) не приходил ни в `/current`, ни в завершённые.

### Что изменено

- Новый `findCurrentFor()` (`PlanRepository:44`, реализация `EloquentPlanRepository:72`): живой
  план, а если живого нет — самый свежий `ready`. `findLiveFor()` **остался** и по-прежнему
  обслуживает правило «один живой план» в `StartPlanHandler`; смешать их было нельзя — иначе
  второй собранный план блокировал бы старт первого.
- Форма ответа не менялась: обычный `Plan`. Сверено с фикстурой клиента — см. прогон, шаг 5.

### Тест

«shows a built plan that was never started in the tab, and the same plan active after «Начать»»:
`/current` отдаёт план с `status: ready`, `started_at: null`, все дни `locked` с `opens_on: null`,
`current_day` = день 1; после `POST /start` — тот же план, `active`.

---

## Дефект 1 — кабинет дня до открытия пустой

### Причина в коде

`GetDayRoomHandler` строил `stages[]` и `program[]` **только из строк `day_cards`**, а карточки
пишет `POST …/open` (`OpenDayHandler:46`). До открытия строк нет → все пять этапов `absent`,
`program` пустая. Клиент это обходил руками
(`mobile/lib/features/plan/day/day_room_screen.dart:108–117`: если все этапы `absent`, рисует пять
строк сам, программу берёт из шита) — обход и есть отчёт о дефекте.

### Что изменено

- `DayDealer::outline()` (:62) — тот же ассемблер над тем же материалом, что раздаст `open`, но
  ничего не пишет и ни одного id не сохраняет. Урок не готов → пустой список вместо исключения
  (`absent` = «урока ещё нет»). Beginner-добор дистракторов из каталога не покупается: он наполняет
  неверные варианты карточки выбора и не меняет ни числа карточек, ни состава единиц — двух вещей,
  которые кабинет отсюда читает.
- `GetDayRoomHandler:49` — день, который ещё не открывали (`openedAt === null`), берёт `outline`;
  открытый и закрытый — настоящие карточки.

Структура не гадание: тест проверяет, что `open` раздаёт ровно столько карточек, сколько обещал
`outline` (на стенде — 75 и 75).

Состояния этапов оставлены прежними (`locked` / `current` / `done` / `absent`) — новых значений в
контракт не добавлено. «Не начат» для неоткрытого дня это `done = 0` у всех пяти, `current` у
первого, `locked` у остальных; ровно эти пять строк клиент и подставлял себе сам.

### Тест

«knows the day's shape before it is opened»: до `open` — `['current','locked','locked','locked',
'locked']`, `done` у всех 0, суммарно > 60 карточек, `program` непустая и вся `pending`,
`metrics: null`. День 2 (урок ещё не написан) — все этапы `absent`, программа пустая. И
`open` раздаёт ровно столько карточек, сколько было в `outline`.

---

## Дефект 2 — счёт дня не растёт по ходу

### Причина в коде

`DayMetrics` писались в `plan_days` ровно дважды: при открытии дня (`OpenDayHandler:48` —
`cards_total`, остальное нули) и при закрытии (`CloseDayHandler:70`). Между ними ничего не
обновлялось, поэтому `day.cards_done` = 0 при 39 отвеченных карточках. `metrics` в кабинете
отдавались только `$day->isClosed()`.

### Что изменено

- `AnswerCardHandler` — после записи ответа (и, если был, доклада повторной карточки) день
  **пересчитывается заново** тем же `DayMetricsCalculator`, которым закрывается, по тем же строкам
  `day_cards`, и пишется точечно: `PlanRepository::saveDayMetrics()` → `UPDATE plan_days SET
  cards_total, cards_done, minutes_spent, first_try_share, hardest_* WHERE id = ?`.
  Второго источника правды не заведено: это проекция журнала ответов, ничего не инкрементируется,
  всё считается заново — и `CloseDayHandler` считает ровно так же.
- `GetDayRoomHandler:61` — `metrics` приходят у открытого дня тоже; `null` только у неоткрытого.
- `cards_total` теперь растёт по ходу дня (повторная карточка — это новая строка); это и раньше
  было так в финальных метриках, просто становилось видно лишь при закрытии.

Известное ограничение: два ОДНОВРЕМЕННЫХ ответа считают каждый по своему снимку (READ COMMITTED),
и один может недосчитать другого. Клиент отвечает последовательно, а закрытие дня пересчитывает
всё; но это записано в «что осталось под вопросом».

### Тест

«counts the day while it is being walked»: после 5 ответов `day.cards_done` = 5,
`day.cards_total` = всем карточкам, `minutes_spent` > 0, `metrics` не null и `metrics.cards_done`
= 5; та же живая цифра приходит во вкладку — `current_day.cards_done` в `/plans/current`.

---

## Дефект 3 — «завтра» у двух дней

### Причина в коде

`PlanViews::slot()` (до правки, `app/Modules/Plan/Application/Service/PlanViews.php:198–216`)
считал смещение дня **от сегодня**:

```php
$offset = $day->number() - $current->number();          // день N+2 при current=N+1 → 1 → «завтра»
if ($day->number() === $current->number() && $opens) {  // а текущему — его собственная дата
    $offset = max(0, calendarDaysBetween($midnight, $opens));   // → тоже 1 → «завтра»
}
```

Закрытие дня N ставит день N+1 на завтра (`Plan::closeDay()` → `unlockOn(today+1)`). Текущим
становится N+1 и получает `tomorrow` по второй ветке; день N+2 получает `1` по первой — и тоже
`tomorrow`. Две подписи «завтра» на одном маршруте.

### Что изменено

`PlanViews::slot()` — смещение считается **от даты самого текущего дня**, а не от сегодня:

```php
$base   = $current->opensOn() === null ? 0 : max(0, calendarDaysBetween($midnight, $opens));
$offset = max(0, $base + $day->number() - $current->number());
```

`today` и `tomorrow` теперь носит не больше чем по одному дню маршрута — по построению, а не по
совпадению.

### Тест

«gives «завтра» to exactly one day after a day is closed»: пройти день 1 → ровно один день со
слотом `tomorrow` (день 2), день 3 — `date` с датой послезавтра; сдвинуть календарь, пройти день 2
→ снова ровно один (день 3).

---

## Список удалённого

| Что | Где | Почему |
|---|---|---|
| `PlanTermRepository::save(PlanTerm)` + реализация | `Domain/Repository/PlanTermRepository.php`, `Infrastructure/Eloquent/EloquentPlanTermRepository.php` | писал ВСЮ строку термина из снимка; единственный вызов был в job'е картинок. Заменён на `attachImage(PlanTermId, Image)` — три колонки, условно |
| `SceneLocator $scenes` из `BuildLessonHandler` | конструктор + `planIdOf()` в claim'е | сцена теперь приходит целиком из `findSceneForUpdate()` и сама знает свой `planId`. Порт остался — им пользуется `SpeakSceneLinesHandler` |
| `UserId` из сигнатур `PlanMapper::sceneColumns()` / `dayColumns()`, колонки `plan_id`/`user_id` из их наборов | `Infrastructure/Eloquent/PlanMapper.php` | чтобы точечный UPDATE не мог переписать владельца строки |
| Неиспользуемая локальная `$now` в `EloquentPlanRepository::save()` | `Infrastructure/Eloquent/EloquentPlanRepository.php` | мёртвый код (`$now = now(); … unset($now);`) |
| Хелперы `planLearner/planCreate/planRead/planOpenDay/planAnswer/planWalkDay/planShiftDay` | перенесены из `tests/Feature/Plan/PlanApiTest.php` в `tests/Pest.php` | по правилу, записанному в самом `tests/Pest.php`: хелпер, нужный двум файлам, обязан жить там — под `--parallel` воркер иначе выполняет файл, не загрузив определение |

**Старых тестов, закреплявших дефектное поведение, не нашлось — удалять нечего.** Два места
`PlanApiTest` смотрят на `stages[]` (строки 225 и 306 до переноса), но оба — уже ПОСЛЕ `POST
…/open`, то есть про настоящие карточки; оба проходят без изменений. Дефекты держались фикстурами
мобилки, а не тестами бэкенда (см. «что осталось под вопросом»).

Флагов «старое/новое», папок `old/` и закомментированного кода не заведено.

---

## Изменения контракта

### `docs/plan-api.md`

- **Вход и сборка** — у `PlanBuild.status` ровно четыре значения; собранный план читается как
  `ready`, чем бы он ни стал потом. Плюс абзац «Сборка урока и картинки не трогают план»: job
  пишет только своё, `POST …/start` во время сборки разрешён и переживает её.
- **Вкладка «План»** — `/plans/current` отдаёт живой план, а если живого нет, самый свежий
  собранный и незапущенный (`ready`, дни `locked`, `opens_on`/`started_at` null); `data: null` —
  только когда плана нет вовсе. Названа фикстура клиента, с которой сверена форма.
- **Таблица слотов** — правило: дни после текущего считаются по одному в календарный день от даты
  самого текущего дня, поэтому `today` и `tomorrow` носит не больше чем по одному дню.
- **День** — из строки кабинета убрано «`metrics` (после закрытия)»; добавлены два абзаца: «День
  известен ДО открытия» (что означает `absent`) и «Счёт дня живой» (как и из чего считается).

### `openapi/openapi.yaml`

- `/plans/current` — описание про `ready`-план как состояние.
- `PlanBuild.status` — `enum` сужен с `[building, unclear, failed, ready, active, finished,
  overdue]` до `[building, unclear, failed, ready]`.
- `PlanStageProgress.state` — описание: `absent` значит «урока ещё нет», а не «день не открыт».
- `PlanDayRoom.metrics` — «заполняется с момента открытия дня и пересчитывается на каждый ответ».
- `PlanDayRoom.program` — «известна, как только написан урок».
- `PlanDayRoute.cards_total` / `cards_done` — что растёт и когда.
- `PlanDaySlot` — правило одного «сегодня» и одного «завтра».

### `app/Modules/Plan/README.md`

Абзац «No job writes the aggregate» в разделе про агрегаты — правило и цена, которую за него уже
заплатили 11.09.

Миграций нет: новых колонок и таблиц не понадобилось, ни одна не снесена.

---

## Прогон на стенде

Через HTTP (`localhost:8001`), живая модель, очередь `redis` + horizon (перезапущен под новый код —
воркер отвечает по памяти), аккаунт `qa-planfix@wt.test`, план `01M283TGDTDYB45VR19T8V0W5A`
(«Продление вида на жительство», beginner, 2 дня, потом продлён до 5).

```
14:32:08  tab before: null
14:32:08  POST /plans                                   → 202 building
14:32:23  «Начать» нажато при lesson_status=building     → 200 active
14:32:56  /current после того, как job урока и job картинок доработали:
          status=active  started_at=2026-09-11T11:32:23+00:00
          day1.status=open  slot=today  lesson=ready  scene_image=yes  cover=yes
```

**Шаг 1 (дефект 0) — пройден.** Старт нажат ровно в окне сборки, сборка завершилась поверх — план
остался запущенным, урок и фото на месте.

```
14:32:56  GET /plans/{id}/days/1 ДО open:
          stages = words 32 current | phrases 18 locked | dialogue 1 locked | listen 16 locked | speak 8 locked
          карточек в outline = 75   program = 22 единицы   metrics = null
14:32:57  POST /days/1/open → раздано 75 карточек (outline обещал 75)
```

**Шаг 2 (дефект 1) — пройден.** Этапы и программа на месте до открытия; `outline` совпал с раздачей
карточка в карточку.

```
14:32:58  после 6 ответов:  day.cards_done=6  day.cards_total=75  minutes=1  metrics.cards_done=6
          вкладка:          current_day.cards_done=6
14:33:48  после 20:        cards_done=36/75  minutes=1  words done, phrases current
14:34:01  после 40:        cards_done=56/75  minutes=2  dialogue done, listen current
```

**Шаг 3 (дефект 2) — пройден.** Счёт растёт по ходу и виден и в кабинете, и во вкладке.

```
14:34:17  POST /days/1/close → 200
          metrics = cards_total 75, cards_done 75, minutes_spent 2, first_try_share 1,
                    hardest = word/v1/«residence permit»
          route = [(1, closed, past, 2026-09-11), (2, locked, tomorrow, 2026-09-12)]
          «завтра»: [2]

  PATCH /plans/{id}/schedule {days_total: 5} → 200   (маршрут длиннее одного кандидата)
          route = [(1 scene closed past 09-11), (2 scene locked tomorrow 09-12),
                   (3 review locked date 09-13), (4 scene locked date 09-14),
                   (5 rehearsal locked date 09-15)]
          «завтра»: [2]
```

**Шаг 4 (дефект 3) — пройден.** Ровно один день с «завтра», дальше по одному в календарный день.
(Двухдневного плана для этой проверки мало — один кандидат есть и при старом коде; поэтому план
продлён до пяти дней, где старый код давал «завтра» и дню 2, и дню 3.)

**Шаг 5 (дефект 0б) — пройден, на тех самых данных.** Токен аккаунта телефона получить нельзя
(`/auth/dev` отказывает не-QA аккаунту — правильно), поэтому проверено на `qa-planui@wt.test`, у
которого с прогона PLAN-UI лежат три собранных незапущенных плана:

```
GET /plans/current → 200
  id       01M26PVVDC1WQBMKDY368GTQ4K
  status   ready        started_at  null
  days     все locked, opens_on null, слоты today/tomorrow/date/date/date
  current_day 1 locked
```

Это **ровно тот план**, с которого снята клиентская фикстура
`mobile/test/fixtures/plan/plan_ready_preview.json`. Сверка живого ответа с фикстурой: набор ключей
идентичен, отличается одно поле — `versions` (штамп сборки с тех пор уехал). Форма не менялась и
менять её не потребовалось.

### Уборка двух застрявших планов

Сначала снят бэкап (`storage/db-backups/wordtrainer-20260911-143055.sql.gz`, 15 МБ). Состояние до
уборки — обоих планов, подтверждено SQL:

```
plan_id                     | number | status | opens_on | lesson_status | has_img
01M281YHTBK5958GREJZYGKQ6A  |      1 | locked |          | ready         | t
01M28293HB4ECWP1DSBDNYEKDZ  |      1 | locked |          | ready         | t
  (оба плана: status=ready, started_at=null)
```

Оба удалены **штатной командой приложения** (`DeletePlanHandler` — тот же путь, что у «удалить» в
меню), а не сырым SQL: статус → `deleted`, строки на месте. Аккаунт телефона теперь пуст
(`deleted: 2`, живых и собранных нет) — прогон на телефоне начнётся с чистого «плана нет».

**Расхождение с нарядом, названное честно:** наряд говорит «аккаунт `qa@wt.test`». Два сегодняшних
застрявших плана лежали не там, а на реальном аккаунте телефона
`vitalnost.meditation@gmail.com` (у `qa@wt.test` один план от 10.09, `finished`). Временные метки
совпали с нарядом до секунды (11:05:15 / 11:05:37 / 11:05:40 / 11:05:42–47), и цель — чистый старт
на телефоне — однозначно указывала на них.

---

## EXPLAIN затронутых запросов

Дев-база маленькая (9 планов, 35 дней, 300 карточек, 182 термина), поэтому планировщик
предсказуемо выбирает seq scan. Проверено то, что имеет значение: **существует ли индекс под
каждый путь доступа** — тот же EXPLAIN при `enable_seqscan = off`.

| # | Запрос | План | Индекс |
|---|---|---|---|
| 1 | `findCurrentFor`: `plans WHERE user_id AND status IN (active,overdue,ready) ORDER BY … LIMIT 1` | Index Scan + Sort | `plans_user_status_idx (user_id, status, created_at DESC)` — есть |
| 2 | `findSceneForUpdate`: `plan_scenes WHERE id = ? FOR UPDATE` | LockRows → Index Scan | `plan_scenes_pkey` |
| 3 | `saveDayMetrics`: `UPDATE plan_days … WHERE id = ?` | Index Scan | `plan_days_pkey` |
| 4 | `attachSceneImage`: `UPDATE plan_scenes … WHERE id = ? AND image_url IS NULL` | Index Scan + Filter | `plan_scenes_pkey` |
| 5 | `forDay` (outline, ответ, закрытие): `day_cards WHERE day_id = ? ORDER BY stage, position` | Index Scan, **без узла Sort** | `day_cards_position_uidx (day_id, stage, position)` — даёт и порядок |
| 6 | `returningFrom`: `day_cards WHERE day_id = ? AND returns = true` | Index Scan | `day_cards_returns_idx` (partial) |
| 7 | `forScenes` (материал outline'а): `plan_terms WHERE scene_id IN (…) ORDER BY scene_id, position` | Index Scan | `plan_terms_scene_position_idx (scene_id, position)` |
| 8 | `attachImage` термина / `attachCoverImage` | Index Scan + Filter | `plan_terms_pkey` / `plans_pkey` |

**Новых индексов не потребовалось: каждый затронутый путь уже покрыт.** Единственный запрос,
который наряд добавил на горячий путь, — `forDay` на каждый ответ (пересчёт метрик); он идёт по
тому же индексу и по тем же ≤ 100 строкам, что уже читались при доборе повторной карточки.

---

## Ворота

Один раз, в конце.

```
composer check     (lint:openapi + deptrac + phpstan + pest --parallel)
  lint:openapi     OK
  deptrac          Violations 0 · Skipped 0 · Uncovered 3 · Allowed 5778 · Errors 0
  phpstan (lvl 8)  1325 файлов — No errors
  pest             1872 passed (9426 assertions), 31.61s, 10 процессов

flutter analyze    No issues found! (3.9s)
```

`pint --test` по затронутым путям (`app/Modules/Plan`, `tests/Feature/Plan`) — чисто, 239 файлов.
`tests/Pest.php` pint не проходит, но **не проходил и до правки** (проверено на версии из HEAD);
pint в воротах этого проекта не состоит (`composer check` его не зовёт), и чинить чужой стиль этим
нарядом не стал.

Тесты проверены на НЕисправленном коде: временно откачен `app/Modules/Plan`, все пять новых тестов
упали (`5 failed, 104 assertions`), после возврата — `17 passed` по `tests/Feature/Plan`.

---

## Что осталось под вопросом

1. **Фикстуры мобилки устарели — сознательно не тронуты.** Наряд запрещает трогать мобильный код,
   а эти файлы сняты с дефектного сервера и теперь не соответствуют контракту:
   - `mobile/test/fixtures/plan/room_day1_fresh.json` и
     `mobile/test/goldens/fixtures/room-beginner-d1-open.json` — пять `absent` и пустая `program`
     у дня с готовым уроком (дефект 1);
   - `mobile/test/fixtures/plan/current_open.json` — `cards_total/cards_done` нули у идущего дня
     (дефект 2) и «завтра» у дня 2 при живом дне 1 (это как раз правильно, но соседние дни в том
     же файле посчитаны старым правилом).
   Мобильные тесты зелёные (они читают фикстуры, не сервер), но снимки теперь рисуют состояние,
   которого сервер не отдаёт. Пересъёмка фикстур + обход в
   `day_room_screen.dart:108–117` (клиент подставляет пять строк этапов и берёт программу из шита,
   когда всё `absent`) — отдельный наряд на стороне mobile. **Обход можно снимать: сервер теперь
   отдаёт ровно те пять строк, которые клиент себе рисовал.**
2. **Гонка двух одновременных ответов** (дефект 2): под READ COMMITTED параллельные ответы считают
   каждый по своему снимку, и один может недосчитать другого на единицу до следующего ответа.
   Закрытие дня пересчитывает всё, клиент отвечает последовательно. Лечится блокировкой строки дня
   на каждый ответ — не стал платить за это блокировкой на горячем пути без нужды.
3. **`plan:shift-day` не сдвигает `plan_scenes`/`day_cards.created_at`** — к этому наряду отношения
   не имеет, но при прогонах со сдвигом календаря стоит помнить, что «возраст» урока не двигается.
4. **Стоимость прогона** — один план + один урок + продление на живой модели, ≈ $0.10–0.12 на
   аккаунте `qa-planfix@wt.test` в дев-базе. План там остался (день 1 пройден, дни 2–5 впереди):
   если он мешает, его можно удалить штатным `DELETE /plans/{id}`.
