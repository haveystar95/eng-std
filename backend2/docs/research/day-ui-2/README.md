# DAY-UI-2 — «Окно дня» по кадрам 23-0a…0d + бэкенд под него

Наряд DAY-UI-2 (14.09.2026). Канва — `backend2/docs/design/plan-canvas.dc.html`, серия 23 (кадры
23-0a «не начат», 23-0b «идёт», 23-0c «пройден», 23-0d «три вкладки в трёх состояниях»), таблицы
«Изменения · серия 23» и «Тайминг · серия 23», `@keyframes om-cab-in` / `om-check-pop`.

## Ч.0 — что прочитано и где документы расходятся

Прочитаны: `docs/DECISIONS.md` (реестр), `backend2/docs/session-handoff.md`, `docs/plan-ui-glossary.md`,
`backend2/docs/design/design-map.md`, код кадров 23-0a…0d (скриптом, дерево элементов со стилями),
`docs/plan-v2.md`, `docs/plan-api.md`, код окна DAY-UI (`day_room_screen.dart`, `DayRoomPlate` в
`ui/day_plate.dart`) и GET дня (`GetDayRoomHandler`, `PlanJson::room`).

Реестру постановка не противоречит: пп. 271–272 («цифра одна — минуты», «Пройти ещё раз») — решения
первой цепочки плана, отменённой целиком 10.09 (раздел «Отменено», первая строка: «действующие решения
о плане — пп. 304–308»), и кабинет DAY-UI уже печатает «Слова · 12 из 32».

Расхождения внутри постановки (выбрано и названо, не угадано молча):

| место | наряд | код кадра | взято |
|---|---|---|---|
| название дня на плите | «Literata 30» | `font-size:44px; line-height:48px` во всех трёх кадрах и в описании серии | **44 / 48** — наряд велит читать код кадров, а не описание |
| «прослушать» у фраз | 28 | 30 (значок 15) у фраз, 28 у собеседника; «Вычтено» 23-0d: «прослушать 28, как у фраз» | **28** везде — подпись и наряд старше картинки |
| бровь диалога | «бровь словами из summary» | «ДИАЛОГ» без числа, «ДИАЛОГ · 2 ПРОЙДЕНО» | как в кадре: у диалога общего числа нет, у слов и фраз есть |

## Ч.1 — чего не хватает контракту `GET /plans/{id}/days/{n}` (список ДО правок)

Сегодня ответ — `PlanDayRoom`: `day` (строка маршрута), `scene`, `goals_native`, `stages[]`, `metrics`,
`program[]`, `sheet_available`. Его же читают плита таба (21-2…21-4) и каркас сессии, поэтому новое
кладётся **рядом**, отдельным блоком `window`, а не переименованием старых ключей (у `day.status`,
`stages[].total` и `program` в окне другой смысл).

| # | окну нужно | GET дня сегодня | решение |
|---|---|---|---|
| 1 | статус словами: не начат / идёт / пройден | `day.status` = locked/open/in_progress/closed; день 1 неначатого плана — `locked` | `window.day.status` ∈ `not_started` / `in_progress` / `passed`; другой запертый день — `locked`, окно его не рисует (честная ошибка) |
| 2 | «≈ 20 минут» у не начатого и идущего | оценки нет нигде (клиент считал 13 с на карточку) | `window.day.minutes_estimate` — сервер, темп по этапам |
| 3 | «пройден · 19 минут» | `day.minutes_spent` у любого дня | `window.day.minutes_spent` только у пройденного, иначе null |
| 4 | фото дня под скримом, кружок 32 | `scene.image` (url, tone, url_112/448) | `window.day.image` + `image_tone` всегда |
| 5 | «научишься» с галками | `goals_native` — строки без состояния | `window.day.goals[{text, passed}]`; `passed` — только у пройденного дня |
| 6 | пять рядов: слово состояния, цифра только у текущего, «≈ N мин» текущего, полоса | `stages[{stage,total,done,state}]`: числа у ВСЕХ, `absent`, у не начатого первый `current`; минут и доли нет | `window.stages[{stage, state, done_count, total, minutes_left, share}]`; `done_count`/`total`/`minutes_left` не null ТОЛЬКО у `current`; у не начатого все `locked` |
| 7 | полоса компактной шапки — прогресс по этапам | нет | `window.day_progress` 0…1 — доля пройденных этапов |
| 8 | слова: термин, перевод, фото + тон, состояние | `program[]`: text/state `pending/passed/failed`; фото только в шите и карточках; тона у терминов в базе нет | `window.program.words.items[{ref, term, translation, image, image_tone, state}]`, state ∈ `pending/done/returns_tomorrow`; колонка `plan_terms.image_tone` |
| 9 | фразы: текст, перевод, «прослушать», состояние | текст и перевод есть; озвучки фраз на сервере нет — только реплики собеседника | серверная озвучка фраз в том же хранилище (`plan_line_audios.line_ref`), `audio_url` |
| 10 | диалог парами: собеседник (текст, перевод, audio_url), ученик (текст, перевод, состояние) | единица «обмен»: `text_target` = своя реплика, `text_native` = задание; реплика собеседника и `audio_id` только в карточках | `window.program.dialogue.items[{step, partner{text, translation, audio_url}, learner{text, translation, state}}]` |
| 11 | бровь вкладки словами | счётчиков нет, клиент считал сам | `summary {total, done, returns}` у слов, фраз и диалога |
| 12 | одна кнопка: Начать / Продолжить / Ещё раз | нет, клиент выбирал по статусу | `window.allowed_action` ∈ `start/continue/again`; «Ещё раз» = «Говорю сам» заново по существующему `GET …/cards`, без записи ответов |
| 13 | картинка у каждой карточки слова | 69 из 232 слов/связок без фото: у них нет `image_prompt`, поиск не зовётся вовсе (дев-база 14.09) | лестница запросов, тон из палитры, догрузка командой, счётчик `image_missing` |

Что станет мёртвым после перехода окна на `window`: `goals_native`, `program[].text_target`,
`program[].text_native`, `program[].cards_total`, `program[].cards_done`, `metrics.first_try_share`,
`metrics.hardest_unit_*` — их читал только старый кабинет. Плита таба читает `day`, `stages[]`,
`metrics.cards_total/minutes_spent`, `program[].unit_kind/source/state`; сессия — `day`, `stages[]`,
`scene` — остаются.

## Ч.2 — контракт `GET /plans/{id}/days/{n}` после наряда

Новое — блок **`window`** (схема `PlanDayWindow`, описание — `docs/plan-api.md` «Окно дня»). Старые ключи
не переименованы: плита таба и сессия читают их как раньше. Всё, что окно пишет, считает сервер:

| поле | правило (где живёт) |
|---|---|
| `day.status` | `WindowStatus::of` — `not_started` (не открыт; день 1 собранного, но не запущенного плана — тоже), `in_progress`, `passed`; другой запертый день — `locked`, клиент такое окно не рисует |
| `day.minutes_estimate` / `minutes_spent` | `DayPace` — секунд на карточку этапа: слова 8, фразы 29, диалог 34, слушание 13, речь 41 (вверх до минуты) по неотвеченным карточкам; `minutes_spent` — метрика дня, только у пройденного |
| `day.goals[{text, passed}]` | цели сцены; `passed` true у всех — только у пройденного дня |
| `day.image` + `image_tone` | фото сцены с копиями 112/448; тон — всегда (`ImageTones::first`: тон фото → тон обложки → тон темы `#E3DCCF`) |
| `stages[5]` | `DayWindowStages` — `done` / `current` / `locked`; `done_count`, `total`, `minutes_left` не null **только** у `current`; `share` 0…1; у не начатого все `locked` |
| `day_progress` | доля пройденных этапов — полоса компактной шапки |
| `program.words/phrases/dialogue` | карточки дня (у не открытого — контур раздачи): состояние единицы по её карточкам (`UnitStates`: провал дважды → `returns_tomorrow`, все отвечены → `done`; чтение диалога обмен не проходит); `summary {total, done, returns}` — `ProgramSummary`; у реплики собеседника — `audio_url`, у реплики ученика — `state`; у фраз голоса сервера нет (Ч.4) |
| `allowed_action` | `WindowStatus::action` — `start` / `continue` / `again` (только если у дня есть «Говорю сам») |

«Ещё раз» — не пересдача: клиент берёт `GET …/cards` пройденного дня, оставляет «Говорю сам» без
повторов и ответов (`DaySession.rehearsalOf`) и ничего не отправляет — ни ответов, ни закрытий.

**Снято как мёртвое** (читал только старый кабинет): `goals_native`, `sheet_available`,
`program[].unit_ref/scene_id/text_target/text_native/cards_total/cards_done`,
`metrics.cards_done/first_try_share/hardest_unit_*`, **`GET …/sheet`** целиком. В домене вместе с ними —
`DayMetrics::firstTryShare/hardestUnit*`, расчёт «с первого раза» и «самое трудное» в
`DayMetricsCalculator`, `UnitNames`, колонки `plan_days.first_try_share/hardest_unit_kind/ref/text`
(миграция `2026_09_14_100200_drop_unread_day_metrics_from_plan_days`). Кадр 23-0c вычел подвал с
процентами — других читателей у этих чисел не было (проверено `git grep` по backend2, mobile, admin).

Тесты: `tests/Unit/Plan/DayWindowTest.php` (11), `tests/Feature/Plan/PlanDayWindowTest.php` (9); старые
`PlanApiTest` / `PlanContractTest` / `DayAssemblyTest` переписаны с мёртвых полей на `window`.

## Ч.3 — картинка у каждой карточки (находка PHONE-RUN-1)

- **Лестница запросов** (`PlanImageLadder`, запросы — `Domain/Service/ImageQueries`): сцена — `image_prompt`
  → название сцены; слово/связка — `image_prompt` → слово без контекста → тема сцены. Фраза фото не ищет.
- **Не нашлось ничего** — `image = null`, в `plan_terms.image_tone` пишется тон слота
  (`ImageTones`: тон фото сцены → обложки → тон темы). Это же — пометка «лестницу уже спрашивали»:
  слово не ищется заново после каждого урока (`PlanTerm::needsImage`). Счётчик `image_missing`
  (`plan_check_counters`, по версии промпта урока) растёт один раз на слово. Клиент рисует слот тоном,
  «битой» картинки нет.
- **Догрузка существующих планов** — `php artisan plan:images-backfill` (миграция
  `2026_09_14_100000_add_image_tone_to_plan_terms`, бэкап перед записью —
  `storage/db-backups/wordtrainer-20260914-110554.sql.gz`). Живой вывод на `wordtrainer`:

  > Without a photo — before: scenes 0, words and chunks 45 · after: scenes 0, words and chunks 0

  **Было пусто 45 слов/связок, стало 0** (все 45 нашлись лестницей, ни одно не осталось тоном).
  В Ч.1 стояло «69 из 232» — это вместе с удалёнными планами (24 слова); команда их не трогает.
  Сейчас на планах в статусах `active/ready/finished`: 208 слов/связок, без фото 0, сцен без фото 0;
  строк `image_missing` нет.

## Ч.4 — голос сервера: только реплики роли

**Правка при закрытии наряда (владелец, 14.09):** канон — премиум-голос у реплик роли; фразы ученика и
слова — голос телефона. Первая версия наряда озвучивала сервером и фразы — это снято целиком:

- очередь голоса — `RoleLineQueue`: реплика собеседника каждого обмена, которой у этого голоса ещё нет
  (`x3`); её покупают и job урока (`SpeakSceneLinesHandler`), и `plan:speak-backfill`. Фраз в очереди нет;
- у фраз окна дня нет `audio_url` (схема `PlanWindowPhrase`), «прослушать» у фразы читает телефон;
  клиент докачивает файлы только реплик собеседника;
- `plan_line_audios` по-прежнему ключуется `line_ref` (миграция `2026_09_14_100100`, старые строки
  стали `x<step>`); строки фраз, купленные 14.09 до правки, — **51 строка в 10 сценах, $0.0322** — лежат
  в таблице, их больше никто не называет (не удалялись: удаление данных — отдельным решением);
- **лимит вендора — поминутный и суточный:** Gemini TTS отвечает 429 `GenerateRequestsPerMinutePerProjectPerModel`
  (10 в минуту) и `GenerateRequestsPerDayPerProjectPerModel` (**100 в сутки**). Сцена — 8 реплик роли,
  два урока подряд — 16; три попытки job'а сдавались на полпути, поэтому `SpeakSceneLinesJob`
  повторяется раз в минуту до получаса (`retryUntil`), каждая попытка покупает только недостающее;
- **догрузка** — `php artisan plan:speak-backfill` (бэкап `wordtrainer-20260914-115714.sql.gz`): сцена за
  сценой, ждёт поминутный лимит, печатает, сколько реплик роли не озвучено, до и после; `--count` —
  только посчитать. До правки прогон дошёл до суточного лимита (строк голоса 184 → 228).

**Счётчик оставшихся реплик роли** (`plan:speak-backfill --count` на `wordtrainer` после правки, сегодня
суточный лимит уже исчерпан — покупать нечем):

> Role lines not voiced yet, 26 scenes: 31

Озвучено реплик роли — 177 из 208 (26 сцен с готовым уроком на планах `active/ready/finished`).
Оставшиеся 31 догружаются командой в следующие сутки; до тех пор эти реплики читает телефон.

## Ч.5 — клиент: окно дня

`lib/features/plan/day/day_window_screen.dart` + `window/` (плита, ряд этапа, компактная шапка,
вкладки, слова, фразы, диалог, кнопка, лента), модель `lib/data/plan/day_window.dart` (разбор
закрытый: нет поля или чужое слово — `PlanContractError`, окно говорит «не загрузилось»).
Тайминги — `AppMotion.window*`, по одной константе на строку таблицы «Тайминг · серия 23»:
плита → строка 56 — 240 мс ease-out; вкладки примагничиваются — 160 мс; смена вкладки — 220 мс;
содержимое вкладки `om-cab-in` — 200 мс с задержкой 80; галка закрытого этапа `om-check-pop` —
180 мс с задержкой 300, `cubic-bezier(.34,1.4,.5,1)`. Под «уменьшением движения» — сразу.

Сверка с кодом кадров (не с описанием): чтение канвы скриптом сначала пропускало `padding-top`
(белый список свойств) — отступы 24 под хайрлайном этапов и 20 над строкой итога нашлись при
сравнении живого снимка с кадром, отрисованным headless Chrome; скрипт теперь читает все свойства,
других пропусков в 23-0a…0d нет (`box-sizing`, `min-width:0`, `text-wrap:balance` — поведение CSS,
цифры `tabular-nums` уже были). Отклонения, оставленные сознательно, — Ч.0 и Ч.13.

## Ч.6 — снимки (golden) и канон

**Golden** — `test/features/plan/day_window_golden_test.dart`, 12 PNG в `test/goldens/plan/`:
`23-0a-not-started`, `23-0b-in-progress`, `23-0c-passed` и `23-0d-{not-started,in-progress,passed}-{words,phrases,dialogue}`.
Проверки таблицы «что проверяет golden» — утверждениями в тех же тестах: нет «N / M» у не начатого и
«Слова · 8» (23-0a); «6 / 16» в ряду «Слушаю и отвечаю», легенды нет (23-0b); «Ещё раз» и «2 вернутся
завтра» в брови (23-0c); одна кнопка у нижнего края и ни одной строки, упёршейся в предел (23-0d).
Фикстуры — живой план `01M2FHN9Y6HRN80KSE1737ZDMZ` (`qa-dayui2-fx@wt.test`), пройденный по API
скриптом (`room_window_not_started/in_progress/passed.json`, `cards_window_passed.json`, `plan_window.json`).

**Тест → что ловит.** Канон — `test/features/plan/day_window_canon_test.dart` (15),
`test/data/plan/day_session_test.dart` («Ещё раз», 2), `test/goldens/day_ui_golden_test.dart` (выход из
«Ещё раз»). Каждый проверен **мутацией** — дефект вносился в код, тест падал, код возвращался.

| Тест (правило) | Дефект, который ловит | мутация |
|---|---|---|
| идущий день — одна цифра, в ряду текущего этапа | счёт в каждом ряду, как у старого кабинета | — |
| счёт, присланный пройденному ряду, не рисуется | клиент рисует цифру везде, где сервер её прислал | `stageCount` без проверки `current` → падает |
| не начатый и пройденный дни — цифр нет | «0 / 16» у не начатого, «16 / 16» у пройденного | — |
| start / continue / again → «Начать» / «Продолжить» / «Ещё раз», одна кнопка, у нижнего края | вторая кнопка на плите; слово по статусу дня, а не по `allowed_action` | — |
| действия нет — кнопки нет | кнопка-заглушка без действия сервера | — |
| summary расходится с маркерами — бровь по summary | бровь, посчитанная телефоном | бровь с числом не из summary → падает |
| нулевые части брови не пишутся | «0 пройдено», «0 вернутся завтра» | — |
| нет summary — «не загрузилось» | пересчёт брови телефоном или «0» | — |
| маркер у реплики ученика, «прослушать» у собеседника | маркер у пузыря собеседника | маркер в ряд собеседника → падает |
| прокрутка: строка 56 встаёт за 240 мс, тяга вниз возвращает плиту | плита уезжает без шапки; строка без перехода; плиту не вернуть | порог сжатия недостижим → падает |
| отпущенная на полпути лента доезжает до шапки и обратно (семантика выключена) | **живой дефект**: доводка ждала следующего кадра, которого после медленного отпускания нет | доводка без заказа кадра → падает |
| второй вход в окно — свежий день | **живой дефект**: окно рисовало ответ первого входа | провайдер без `autoDispose` → падает |
| запертый день — ошибка загрузки | нарисованное «запертое» состояние | — |
| «Ещё раз»: только «Говорю сам», без повторов и ответов (живые карточки) | весь день заново или карточки прошлого прохода | — |
| «Ещё раз»: ответы не уходят, этап и день не закрываются | пересдача поверх пройденного дня | ответ уходит на сервер → падает |
| «Ещё раз»: выход сразу, без «Прогресс сохранится» | **живой дефект**: обещание сохранить то, что не сохраняется | алерт в повторе → падает |

Бэкенд — `DayWindowTest` / `PlanDayWindowTest` называют правило и дефект в имени теста (например,
«puts the count, the minutes left and a partial bar on the current row only — catches a number on
every row (23-0b)», «asks for a photo by the description, then the word alone, then the scene’s title —
catches a word without a description never searched»). Правка при закрытии добавила два: «voices the
partner’s lines only: the phrases stay on the phone’s voice, the learner’s line carries its state»
(ловит фразу, купленную у вендора, и маркер у пузыря собеседника) и «backfills the role’s lines a scene
still lacks, never a phrase, and counts the role lines not voiced yet» (ловит догрузку фраз и команду,
которая не может сказать, сколько осталось).

## Ч.7 — EXPLAIN (ANALYZE, BUFFERS)

База `wordtrainer_e2e_test` (`migrate:fresh`), нагрузка `plan:seed-load`: 50 планов ученика + 200
соседа — 250 планов, 1 000 сцен, 1 500 дней, **100 500 карточек**, 14 000 терминов, 14 000 строк голоса
(сид строк голоса и `opened_at` дней — `docs/research/day-ui-2/tools/explain.php seed`), `ANALYZE`.
Каждый ответ — через HTTP-ядро с журналом запросов; число запросов не зависит от числа карточек.

| форма дня | запросов (без аутентификации) | время |
|---|---|---|
| пройден (день 1) | 8: план, сцены, дни, пользователь + профиль (часы ученика), карточки дня, голос, термины | 73 мс (первый в процессе) |
| идёт (день 2) | 6 (пользователь и профиль уже прочитаны процессом) | 8 мс |
| не открыт (контур раздачи) | 8: вместо карточек дня — возвраты двух прошлых дней, термины дважды (раздатчик и окно), голос | 10 мс |

Планы новых чтений окна:

```
select * from "day_cards" where "day_id" = ? order by CASE stage … END, "position"
  Index Scan using day_cards_position_uidx on day_cards (actual rows=67 loops=1)
  Buffers: shared hit=18 · Execution Time: 0.113 ms

select * from "plan_line_audios" where "scene_id" in (?) and "voice_key" = ?
  Index Scan using plan_line_audios_uidx on plan_line_audios (actual rows=14 loops=1)
    Index Cond: (scene_id = … AND voice_key = 'gemini:gemini-2.5-flash-preview-tts:Aoede:p90')
  Buffers: shared hit=3 · Execution Time: 0.022 ms

select * from "plan_terms" where "scene_id" in (?, ?) order by "scene_id", "position"
  Index Scan using plan_terms_scene_position_idx on plan_terms (actual rows=28 loops=1)
  Buffers: shared hit=3 · Execution Time: 0.017 ms

select * from "day_cards" where "day_id" = ? and "returns" = ? …   (контур: возвраты прошлого дня)
  Index Scan using day_cards_returns_idx on day_cards (actual rows=8 loops=1)
  Buffers: shared hit=5 · Execution Time: 0.030 ms
```

Индексы по факту: `day_cards_position_uidx (day_id, stage, position)`, `day_cards_returns_idx`,
`plan_line_audios_uidx (scene_id, line_ref, voice_key)` — уникальный ключ, перенесённый миграцией на
`line_ref`, он же индекс чтения; `plan_terms_scene_position_idx`; `plan_days_number_uidx`,
`plan_scenes_order_uidx`, `plans_pkey`. Новых индексов не понадобилось. Замечание: у не открытого дня
термины сцены читаются дважды (раздатчик контура и окно) — 2 запроса по 0.3 мс, не N+1; общий кэш
терминов на запрос — кандидат на потом.

## Ч.8 — живой прогон на симуляторе

Симулятор **PlanUI3 iPhone 17** (iOS 26.5), `flutter run --debug --dart-define=API_BASE_URL=http://localhost:8001
--dart-define=DEV_LOGIN_EMAIL=qa-dayui2-live@wt.test`, связка ключей сброшена; свежий план
`01M2FJYJG4PZS1YQCAQGK30QPF` («Иду к врачу…», 3 дня, начальный) собран живой генерацией и оставлен
`ready`. Тапы — maestro по координатам, снимки — `simctl io screenshot`; кадр канвы отрисован из
`plan-canvas.dc.html` headless Chrome и стоит слева. Снимки — `shots/`. Инструменты — `tools/`:
`render_frames.py` + `frame.html` (кадр канвы в PNG), `compose.swift` (кадр рядом со снимком),
`snap_window.py` (фикстуры окна живым планом по API), `explain.php` (Ч.7).

| шаг | снимок |
|---|---|
| таб: день 1 не запущенного плана, «Начать» на плите | `01-tab-day1-start.png` |
| «Начать» → **окно · не начат** — фото под слоем, пять рядов «впереди», бровь «Слова · 8», «Начать» внизу | `02-23-0a-not-started.png` (слева 23-0a) |
| прокрутка → **компактная шапка 56**, вкладки под ней, сетка слов с фото | `03-23-0a-scrolled-words.png` (23-0a · прокручено) |
| вкладки тапом: «Фразы · 6» с «прослушать» 28, «Диалог» с маркерами у своих реплик | `04-…-phrases.png`, `05-…-dialogue.png` (23-0d · не начата) |
| горизонтальный свайп из «Диалога» — «Фразы» | `06-swipe-back-phrases.png` |
| тяга вниз с верха вкладки — плита вернулась | `07-pull-down-plate-back.png` |
| «Начать» в окне → план запущен (`POST /start`), вход в этап «Слова · 0 из 32» | `08-start-session.png` |
| выход из сессии → окно «идёт», текущий — «Слова 0 / 32» | `09-window-opened-words-current.png` |
| «Слова» пройдены (API, 32 карточки) → таб: «Слова пройдено, Фразы идёт» | `10-tab-after-words.png` |
| **окно · идёт — цифра только у «Фразы» (0 / 18)**, «Слова» галкой, бровь «Слова · 8 · 8 пройдено», «Продолжить» | `11-23-0b-in-progress-phrases-current.png` (23-0b) |
| прокручено: полоса дня 1/5, «≈ 19 мин» | `12-23-0d-in-progress-words.png` (23-0b · прокручено) |
| день пройден (API) → таб «День 1 закрыт · 75 карточек · 6 минут» | `13-tab-day1-closed.png` |
| **окно · пройден** — цели галками, пять рядов «пройдено», «День пройден · 6 минут», «Ещё раз» | `14-23-0c-passed-again.png` (23-0c) |
| прокручено; «Диалог · 8 пройдено», маркеры у своих реплик | `17-23-0c-scrolled.png`, `18-23-0d-passed-dialogue.png` |
| «Ещё раз» → «Говорю сам · 0 из 8», этап 5 из 5 | `15-again-speak-rehearsal.png` |
| крестик — сразу в окно, день по-прежнему пройден | `16-again-exit-no-alert.png` |

**Что прогон нашёл и что починено в этой же сессии** (у каждого — тест, проверенный мутацией, Ч.6):
1. отпущенная на полпути лента **не доводилась** до шапки — полоса плиты торчала из-под строки 56:
   доводка ждала кадра, а после медленного отпускания кадров нет (в тестах кадр заказывала включённая
   семантика, поэтому снимки и поведенческие тесты этого не видели);
2. **второй вход в окно рисовал старый день** («Слова · идёт · 0 / 32» после пройденных слов) —
   провайдер дня жил дольше окна; теперь `autoDispose`, каждый вход читает сервер;
3. выход из «Ещё раз» спрашивал «Продолжить позже? … Прогресс сохранится» — повтор ничего не сохраняет;
   теперь выходит сразу;
4. отступы 24 / 20 под хайрлайнами плиты (пропущены при чтении канвы, Ч.5).

## Ч.9 — список удалённого (удалено, не выключено; флагов и `old/` нет)

**Клиент — файлы:** `lib/features/plan/day/day_room_screen.dart` (кабинет: `DayRoomScreen`, `_Room`,
`_WordCard`, `_PhraseRow`, `_ExchangeRow`), `lib/features/plan/day/term_sheet.dart` (шит: `_TermSheet`,
`SheetTermState`), `test/features/plan/day_room_unstarted_plan_test.dart`, снимки `test/goldens/23-0a-room-open.png`,
`23-0b-room-in-progress.png`, `23-0c-room-closed.png`, `23-14-sheet-word.png`, `23-15-sheet-phrase.png`,
фикстуры `test/goldens/fixtures/plan-beginner.json`, `room-beginner-d1-open.json`, `sheet-beginner-d1.json`,
`sheet-intermediate-d1.json`.

**Клиент — код:** шапка кабинета в `lib/ui/day_plate.dart` — `DayRoomPlate`, `DayRoomStage`, `DayRoomNumber`,
подвалы `DayRoomStart/Progress/Closed` (тёмная шапка «начни отсюда», числа Literata 56 и проценты,
«Продолжить · осталось N»), `_BlurredPhoto`, `_Goals`, `_RoomStageRow`, `_Footer`, `_RoomPaperButton`,
`_ClosedCheck`, `_Number`; `DaySectionLabel` (легенда и лейблы секций); `ApiClient.daySheet`; `DayTerm`,
`DaySheet`, расширения `DayRoomProgram`, `DayStageRemaining`, `DayMetricsPercent`; `DayRules.firstTryPercent`;
`DayTexts.level`; поля `PlanDayRoom.goalsNative/sheetAvailable`, тексты единиц программы; токены
цветов и типографики старой плиты; **33 строки ARB** — `dayBack`, `dayClosedTitle`, `dayCtaContinue`,
`dayCtaPlan`, `dayCtaStart`, `dayGoalLabel`, `dayHardest`, `dayInWork`, `dayIntroBadgeRepeat`,
`dayLabel`, `dayLevelBeginner`, `dayLevelIntermediate`, `dayNewWords`, `dayNumCards`, `dayNumFirstTry`,
`dayNumMinutes`, `daySectionPhrases`, `daySectionTalk`, `daySectionWords`, `daySheetInTalk`,
`daySheetRoleYou`, `daySheetStateHinted`, `daySheetStatePassed`, `daySheetStateReturns`,
`dayStageCount`, `dayStageSubHinted`, `dayStageSubStart`, `dayStageSubUnfinished`, `dayTabRoute`,
`dayTabTitle`, `dayTries`, `dayWillReturn`, `dayWordsExtra`; тест `DayRules.firstTryPercent`.

**Сервер:** `GET /plans/{id}/days/{n}/sheet` (маршрут, `PlanDayController::sheet`, `GetDaySheet`,
`GetDaySheetHandler`, `SheetView`, `TermView`, `CardViews::term`, `PlanJson::sheet/term`, схемы OpenAPI
`PlanSheet`, `PlanTerm`); поля ответа из Ч.2; `DayRoomView.goalsNative/sheetAvailable`,
`ProgramUnitView` без текстов и счётчиков, `DayMetricsView` из двух чисел; `UnitNames`; «с первого
раза» и «самое трудное» в домене и четыре колонки `plan_days`.

## Ч.10 — новые подписи для глоссария

`docs/plan-ui-glossary.md` перегенерирован (212 строк). Новые ключи (13): `planWindowBack` «Назад»,
`planWindowStateNotStarted` «не начат», `planWindowStateInProgress` «идёт», `planWindowStatePassed`
«пройден», `planWindowApprox` «≈ {minutes}», `planWindowJoin` «{first} · {second}», `planWindowGoalsLabel`
«научишься», `planWindowStageCount` «{done} / {total}», `planWindowPassedLine` «День пройден · {minutes}»,
`planWindowBrowDone` «{n} пройдено», `planWindowBrowReturns` «{n} вернётся/вернутся завтра»,
`planWindowCtaAgain` «Ещё раз», `planWindowListen` «Прослушать». Переиспользованы со своими словами
(описания дополнены окном дня): `planPlateLabel`, `planPlateStage*`, `planPlateState*`,
`planPlateCtaStart/Continue`, `planMinutesCount/Short`, `planRouteDayTitle/Review/Rehearsal`.

## Ч.11 — коммиты и ворота

| коммит | что |
|---|---|
| `d9547324` | канва серии 23 (архитектор) и Ч.0–Ч.1 этого отчёта — список недостающего до правок |
| `c870df2d` | бэкенд: `window` в GET дня, лестница фото и `plan:images-backfill`, голос (тогда — и фраз) и `plan:speak-backfill`, снос шита и мёртвых полей, три миграции, тесты, OpenAPI, `plan-api.md`, `plan-v2.md`, README модуля |
| `dc819ef3` | клиент: окно дня, снос старого кабинета и шита, снимки, канон, фикстуры, ARB, словарь плана |
| `a7de0bb3` | отчёт со снимками прогона, инструмент EXPLAIN, `design-map.md`, `session-handoff.md`, ROADMAP, `mobile/CLAUDE.md` |
| правка при закрытии (коммит после `a7de0bb3`) | голос сервера — только реплики роли (`RoleLineQueue`), у фраз окна нет `audio_url`, клиент не докачивает фразы, счётчик оставшихся реплик роли в `plan:speak-backfill`; документы и этот отчёт (Ч.4, Ч.12, Ч.13); ворота — один раз, хуком коммита |

**Ворота** (полный прогон перед коммитами и хук на каждом коммите): `composer check` — deptrac 0
нарушений, PHPStan L8 0 ошибок, **Pest 2002 passed** (10 221 проверка, параллельно, 51 с);
`flutter analyze` — 0; **`flutter test` — 1389 passed**. Откат миграций — `migrate:fresh` →
`migrate:rollback --step=3` → `migrate` на `wordtrainer_test`, зелёные. `invariant-reviewer` — чисто, с
одним вопросом (Ч.13 п. 3).

**Бэкапы перед записью в `wordtrainer`:** `storage/db-backups/wordtrainer-20260914-110554.sql.gz`
(миграции `image_tone`, `line_ref`, догрузка фото), `wordtrainer-20260914-114653.sql.gz` (снос колонок
метрик), `wordtrainer-20260914-115714.sql.gz` (догрузка голоса). Horizon перезапущен после каждой
правки job'ов.

**Стоимость живой генерации:** план фикстур первого захода (удалён при переснятии) $0.2031, план
фикстур $0.1994, план прогона $0.1447 (план + уроки + голос каждого), догрузка голоса старых сцен
$0.0273; фото Pexels бесплатны — **≈ $0.575 из $2**.

## Ч.12 — сборка на телефон

- `flutter clean` → `flutter build ios --release --dart-define=BUILD_SHA=dc819ef3
  --dart-define=BUILD_AT=2026-09-14T13:10+03:00`, `DEVELOPER_DIR=…/privar_sert/Xcode-beta.app`;
- автоподпись: `Automatically signing iOS for device deployment using specified development team … 7A5U4R66CB`;
- `pod install` 3.5 с, **`Xcode build done. 102.7s`**;
- **`✓ Built build/ios/iphoneos/Runner.app (49.8MB)`, бинарь `Runner` 810 576 байт, Runner.app — 13:12:56 14.09.2026**;
- штамп сервера: `scripts/stamp-build.sh` → `dc819ef3`;
- установка тогда не выполнена: «iPhone (Denis)» был `unavailable` («No target device found»).

**После правки при закрытии (телефон в сети):**
- `flutter clean` → `DEVELOPER_DIR=…/privar_sert/Xcode-beta.app ./scripts/build_ios.sh build` — штамп
  **`a7de0bb3+`**: `+` по правилу скрипта — «коммит `a7de0bb3` плюс несохранённые правки», то есть
  дерево правки, которое следующим движением ушло в коммит (штамп сервера — тот же `a7de0bb3+`,
  после коммита переставлен на хэш коммита `scripts/stamp-build.sh`);
- **`Xcode build done. 84.5s`**, **`✓ Built build/ios/iphoneos/Runner.app (49.8MB)`**, бинарь `Runner`
  810 576 байт, **Runner.app — 14:30:42 14.09.2026**;
- `flutter install --release -d 00008110-000A7CCC3492801E` → «Installing com.denis.engstd to iPhone
  (Denis)… Uninstalling old version…»; `devicectl device info apps` видит `com.denis.engstd 1.0.0`.
  Установка снесла данные приложения — на телефоне нужно войти заново.

## Ч.13 — что не проверено живьём и открытые вопросы

**Не проверено живьём:**
- «прослушать» у реплики собеседника серверным голосом на устройстве — кнопку в прогоне не нажимали;
  дорога файла проверена тестом `PlanDayWindowTest` и докачкой `LineAudioCache` (DAY-UI); у фраз после
  правки — голос телефона;
- `om-check-pop` галки этапа при возврате из сессии — этапы в прогоне закрывались по API, не в сессии;
- растворение фото 200 мс, тень примагниченных вкладок 160 мс, `om-cab-in` — видны глазом на
  симуляторе, длительности живьём не мерились (в коде — константы `AppMotion.window*`, в тестах — 240 мс
  сжатия плиты);
- «прокрутка вкладки помнится» (`PageStorageKey`) — отдельным шагом не проверялась;
- «уменьшение движения» на устройстве; хаптика;
- сама карточка «Говорю сам» в «Ещё раз» — на симуляторе нет микрофона (вход в этап и выход — проверены);
- приложение на телефоне после установки не открывалось руками (Ч.12).

**Вопросы архитектору / владельцу:**
1. ~~Голос фраз против суточного лимита~~ — **закрыт владельцем 14.09:** премиум-голос — только реплики
   роли, фразы ученика и слова — голос телефона (Ч.4). Остаётся лимит 100 запросов в сутки на реплики
   роли: 31 не озвучена, догружаются `plan:speak-backfill` в следующие сутки.
2. **Отклонения от кадров, взятые сознательно** (Ч.0): название 44/48 по коду кадров (наряд — 30);
   «прослушать» 28 и у фраз (в картинке 30); бровь диалога без общего числа. Карточка фразы — `min-height
   102` из кадра — у короткой фразы оставляет пустоту снизу. Длинное русское слово в колонке 165
   переносится по буквам; `text-wrap: balance` у названия во Flutter нет.
3. **Правило «экран читает локальную базу»** (`invariant-reviewer`): окно дня, как и старый кабинет,
   читает сеть и копии для офлайна не держит; теперь — на каждом входе. Считать план исключением или
   класть ответ дня в `sync_meta`, как таб?
4. Вход в этап 23-2a (сессия DAY-UI) режет длинную реплику собеседника троеточием — это вне кадров
   23-0x, но против «ellipsis 0».
5. Job голоса не отличает суточный отказ вендора от поминутного — адаптер Generation отдаёт один
   `rateLimited`; различать — правка модуля Generation.
