# GYM-DUMP-2 — план «Тренировка в зале», дни 1 и 2 (бой, только чтение)

Факты для разбора архитектором: данные, цитаты и таблицы, без выводов и оценок. Снято 2026-09-22 с боя (`wt_db`, база `wordtrainer`). Покупок у вендоров нет: ни озвучки, ни генерации, ни судьи, ни модели.

|  |  |
|---|---|
| Аккаунт | user `01M12HTZ1QHPNDZ5J8SPKB58QP` |
| План | `01M32DX8QCABM348XP45Z1ZD4M` — «Тренировка в зале» / «Gym Workout», `status` active, уровень intermediate, ru → en |
| День 1 | `01M32DX8QF4042C0CDVAY9P7DE` · сцена «Ресепшен зала» `01M32DXHYG50H7SWQEMD33A39F` · closed |
| День 2 | `01M32DX8QF5SQ3BMJ8R4PDD7R8` · сцена «С тренером» `01M32DXHYGYMK0E1DBR01PYDHA` · in_progress |
| Разговоры | день 1 `01M32FJ5FQNRNSQH6PNC7DP5E0`, день 2 `01M34HXHP05JY8NVF1305NRY5N` |

## Как снято

- `tools/dump.py` — только SELECT через `psql` в `wt_db` с `PGOPTIONS=-c default_transaction_read_only=on` (сессия отказывает в любой записи) → `raw/*.json` строк базы. Заголовки запросов из `api_request_logs` не выгружались.
- `tools/views.php` — документы, которые получал телефон, пересобраны кодом приложения (`GetDayRoomHandler`, `GetConversationHandler` + `PlanJson`) в `wt_app`: сессия БД `READ ONLY` (проверено: `default_transaction_read_only` on, `transaction_read_only` on), `LOG_CHANNEL=stderr`, `CACHE_STORE=array`, несуществующее подключение очереди, пустые ключи вендоров, `Http::preventStrayRequests()`, часы заморожены на 2026-09-22T12:55:54+00:00 (последнее чтение дня 2 телефоном). Сверка с журналом: ответ дня 2 — 267527 байт против 267527 в журнале, тело журнала 200723 = 200723, превью совпадает: true; документ разговора дня 2 равен ответу последнего хода в журнале: true.
- `tools/report.py` — таблицы этого файла только из `raw/` (и счёт строк `plan.phrases_over_ceiling` в `storage/logs/laravel.log`).
- Время везде UTC.

## §1 Разговор дня 2

Разговор `01M34HXHP05JY8NVF1305NRY5N` · день 2 · `type` day · `state` ended · `ended_reason` natural · `turn_limit` 4 · `hints_enabled` true · начат 12:37:12, окончен 12:38:06 UTC 22.09 · `cost_usd` **0.020406** · `checkpoints_done` С тренером. Роль — «Тренер» / «Trainer», голос `elevenlabs:eleven_v3_conversational:EnjklPXGBMNldCJ7jqkE:s50`; модель `gpt-5.4-mini-2026-03-17`, промпт `conversation_agent.v2.1`.

### 1.1 Стенограмма (все ходы по порядку)

Источники: `conversation_turns` (текст, `phrases_used` сервера, `understood`, `off_topic`, `checkpoint_done`, `hint_native`, цена, время записи хода); **heard** — поле `heard` тела запроса телефона `POST …/conversation/{id}/turn` (`api_request_logs`, входящие); **phrases_used модели** — поле `phrases_used` сырого ответа модели (`api_request_logs`, исходящие `api.openai.com`, `choices[0].message.content`) на этот ход роли — модель отдаёт его в ответе на ход ученика, стоящий строкой выше. В строке хода ученика `phrases_used` — то, что записал сервер (`PhraseUse`, наряд BACK-TAILS-2 §2); в строках роли колонка сервера пуста по построению (`phrasesUsed: []`). Ходов `rescue` и `skip` в разговоре нет. Время — UTC.

| # | время | кто · вид | text_target | text_native | heard (телефон) | phrases_used сервера | phrases_used модели | understood | off_topic | checkpoint_done | hint_native (сохранён) | $ модель + голос | токены in/out | мс модель+голос |
|---|---|---|---|---|---|---|---|---|---|---|---|---|---|---|
| 1 | 12:37:15 | роль · agent | «Hi. What are you training for today?» | «Привет. Что ты сегодня тренируешь?» | — | — | [] | — | false | — | «Я работаю над общей формой.» | 0.002258 + 0.001800 | 2603/68 | 1533+1040 |
| 2 | 12:37:24 | ученик · said | «Hi I am working on general fitness» | — | «Hi I am working on general fitness» | p1 | — | — | — | — | — | 0 | — | — |
| 3 | 12:37:26 | роль · agent | «That sounds good. How much gym experience do you have?» | «Это хорошо. Какой у вас опыт в зале?» | — | — | p1 | true | false | — | «У меня около года опыта.» | 0.000840 + 0.002800 | 2629/94 | 1177+809 |
| 4 | 12:37:38 | ученик · said | «I don't have any experience» | — | «I don't have any experience» | [] | — | — | — | — | — | 0 | — | — |
| 5 | 12:37:42 | роль · agent | «Okay, we’ll keep it simple. Do you have any injuries or pain right now?» | «Хорошо, тогда всё будет просто. У вас есть сейчас травмы или боль?» | — | — | [] | true | false | — | «У меня около года опыта.» | 0.000824 + 0.003600 | 2650/87 | 2414+1107 |
| 6 | 12:37:52 | ученик · said | «Yes my» | — | «Yes my» | [] | — | — | — | — | — | 0 | — | — |
| 7 | 12:37:54 | роль · agent | «What kind of pain is it?» | «Какая это боль?» | — | — | [] | false | false | — | «У меня около года опыта.» | 0.000724 + 0.001200 | 2673/61 | 1519+522 |
| 8 | 12:38:03 | ученик · said | «I have lower back pain» | — | «I have lower back pain» | [] | — | — | — | — | — | 0 | — | — |
| 9 | 12:38:06 | роль · agent | «Thanks for telling me. We should avoid anything that bothers your back today, and I’ll keep the plan simple.» | «Спасибо, что сказали. Сегодня мы избежим всего, что беспокоит вашу спину, и я сделаю план простым.» | — | — | [] | true | false | сцена С тренером | — | 0.000960 + 0.005400 | 2694/110 | 1934+1109 |

Сумма по ходам: модель $0.005606 + голос $0.014800 = **$0.020406** (`conversations.cost_usd` = 0.020406). Подсказка на ходу у телефона — `hints.native` документа; в `conversation_turns.hint_native` хранится полная строка, в документе — её часть после «Скажи, что …» (`IntentClause`), см. `raw/conversation-view-day-2.json`.

### 1.2 Цели разговора дня 2 (`targets[]`) и их каркасы из урока

`targets[]` — из документа разговора (`raw/conversation-view-day-2.json`, тот же список, что `window.stages[conversation].targets`). Каркас — `lesson_json.phrases[]` сцены «С тренером» (`plan_scenes.lesson_json`, промпт `lesson_day.v4.7`); ● — наполнение, которое говорит диалог урока (`in_dialogue`). Реплика урока — шаг диалога, в котором ученик говорит этот каркас.

| ref | scene_id | text_target | text_native | said | frame_target | frame_native | вид | окно (`hint_native`) | наполнения | реплика урока |
|---|---|---|---|---|---|---|---|---|---|---|
| p1 | 01M32DXHYGYMK0E1DBR01PYDHA | «I'm working on general fitness.» | «Я работаю над общей формой.» | true | «I'm working on ___.» | «Я работаю над ___.» | answer | «цель тренировки» | ● general fitness — общей формой<br>○ strength — силой<br>○ weight loss — снижением веса | «x1: I'm working on general fitness.» |
| p2 | 01M32DXHYGYMK0E1DBR01PYDHA | «I have about a year of experience.» | «У меня около года опыта.» | false | «I have ___ of experience.» | «У меня ___ опыта.» | answer | «срок опыта» | ● about a year — около года<br>○ six months — шесть месяцев<br>○ a few years — несколько лет | «x2: I have about a year of experience.» |
| p3 | 01M32DXHYGYMK0E1DBR01PYDHA | «I have some shoulder pain.» | «У меня есть боль в плече.» | false | «I have some ___.» | «У меня есть ___.» | answer | «боль или ограничение» | ● shoulder pain — боль в плече<br>○ knee pain — боль в колене<br>○ lower back pain — боль в пояснице | «x3: I have some shoulder pain.» |
| p4 | 01M32DXHYGYMK0E1DBR01PYDHA | «How do I use this machine?» | «Как пользоваться этим тренажёром?» | false | «How do I use ___?» | «Как пользоваться ___?» | ask | «оборудование» | ● this machine — этим тренажёром<br>○ the cable machine — блочным тренажёром<br>○ the rowing machine — гребным тренажёром | «x4: How do I use this machine?» |
| p5 | 01M32DXHYGYMK0E1DBR01PYDHA | «How heavy should the weight be?» | «Насколько тяжёлым должен быть вес?» | false | «How heavy should ___ be?» | «Насколько тяжёлым должен быть ___?» | ask | «что выбрать по весу» | ● the weight — вес<br>○ the dumbbell — гантель<br>○ the bar — штанга | «x6: How heavy should the weight be?» |
| p6 | 01M32DXHYGYMK0E1DBR01PYDHA | «I'll rest for forty-five seconds.» | «Я буду отдыхать сорок пять секунд.» | false | «I'll rest for ___.» | «Я буду отдыхать ___.» | answer | «время отдыха» | ● forty-five seconds — сорок пять секунд<br>○ thirty seconds — тридцать секунд<br>○ one minute — минуту | «x7: I'll rest for forty-five seconds.» |
| p7 | 01M32DXHYGYMK0E1DBR01PYDHA | «Should I keep my shoulders down?» | «Мне держать плечи опущенными?» | false | «Should I keep ___ down?» | «Мне держать ___ опущенными?» | ask | «часть тела» | ● my shoulders — плечи<br>○ my elbows — локти<br>○ my chest — грудь | «x8: Should I keep my shoulders down?» |

Каркасы и наполнения в `plan_terms` сцены совпадают с `lesson_json.phrases[]`.

### 1.3 Цель ↔ реплики ученика (буквальное совпадение слов неподвижной части)

Неподвижная часть — `frame_target` без `___` и знаков; слова — строчными, по `[a-z0-9']+`, «’» → «'». Реплика ученика — `text_target` ходов `said` (то, что прислал телефон). Совпадение — слово в слово («I'm» и «I am» — разные слова). Оценки смысла нет.

| ref | каркас | слова неподвижной части | реплики ученика с совпадениями (ход — текст — совпавшие слова) | не встретились ни в одной реплике |
|---|---|---|---|---|
| p1 | «I'm working on ___.» | i'm, working, on | ход 2 «Hi I am working on general fitness» — working, on | i'm |
| p2 | «I have ___ of experience.» | i, have, of, experience | ход 2 «Hi I am working on general fitness» — i<br>ход 4 «I don't have any experience» — i, have, experience<br>ход 8 «I have lower back pain» — i, have | of |
| p3 | «I have some ___.» | i, have, some | ход 2 «Hi I am working on general fitness» — i<br>ход 4 «I don't have any experience» — i, have<br>ход 8 «I have lower back pain» — i, have | some |
| p4 | «How do I use ___?» | how, do, i, use | ход 2 «Hi I am working on general fitness» — i<br>ход 4 «I don't have any experience» — i<br>ход 8 «I have lower back pain» — i | how, do, use |
| p5 | «How heavy should ___ be?» | how, heavy, should, be | — | how, heavy, should, be |
| p6 | «I'll rest for ___.» | i'll, rest, for | — | i'll, rest, for |
| p7 | «Should I keep ___ down?» | should, i, keep, down | ход 2 «Hi I am working on general fitness» — i<br>ход 4 «I don't have any experience» — i<br>ход 8 «I have lower back pain» — i | should, keep, down |

### 1.4 Итог разговора (`summary`) — дни 2 и 1

Из документа разговора (`summary` ответа `GET …/conversation/{id}`, пересобран кодом приложения в режиме только чтения — `raw/conversation-view-day-{1,2}.json`; для дня 2 документ совпал с ответом последнего хода, который сохранил `api_request_logs`).

| поле | день 2 | день 1 |
|---|---|---|
| `said_count` | 4 | 4 |
| `phrases_used` | 1 | 0 |
| `phrases_total` | 7 | 7 |
| `understood_all` | false | true |
| `not_understood` | 1 | 0 |
| `rescues` | 0 | 0 |
| `ended_reason` | natural | natural |
| `minutes` | 1 | 2 |
| `returns_tomorrow` | true | true |
| не сказаны (`phrases[].used = false`) | p2, p3, p4, p5, p6, p7 | p1, p2, p3, p4, p5, p6, p7 |

День 1: разговор `01M32FJ5FQNRNSQH6PNC7DP5E0`, 17:17:33–17:18:42 UTC 21.09, `ended_reason` natural, `cost_usd` 0.020778. Цели дня 1 и `said`:

| ref | text_target | text_native | said |
|---|---|---|---|
| p1 | «Do you have a day pass?» | «У вас есть дневной пропуск?» | false |
| p2 | «What monthly memberships do you have?» | «Какие месячные абонементы у вас есть?» | false |
| p3 | «This is my first visit.» | «Это мой первый визит.» | false |
| p4 | «That works for me on weekdays.» | «Мне это подходит по будням.» | false |
| p5 | «Where are the changing rooms?» | «Где раздевалки?» | false |
| p6 | «I'll return the locker key.» | «Я верну ключ от шкафчика.» | false |
| p7 | «Can I pay by card?» | «Я могу оплатить картой?» | false |

Для справки (сырьё — `raw/conversation-day-1.json`, `raw/api-log-conversation-day-1.json`): `phrases_used` сервера у ходов ученика дня 1 — ход 2 [], ход 4 [], ход 6 [], ход 8 []; `phrases_used` модели в ответах роли — ход 1 [], ход 3 [], ход 5 [p2, p3], ход 7 [p3], ход 9 [p4].

## §2 Числа дней 1 и 2

### 2.1 По этапам

- **план, мин** — `window.stages[].minutes` окна дня: цена карточек этапа по `plan.pace` (`DayPace`, секунды на карточку по виду; «Скажи целиком» — за круг), вверх до минуты; у разговора — `plan.conversation.minutes.day` = 3. **До старта** — последний ответ `GET …/days/{n}` до открытия дня, сохранённый журналом целиком (день ещё не розданный — контур раздачи); **после дня** — пересобранный ответ на 12:55:54 22.09 (все карточки этапа, копии-повторы включительно).
- **факт ≤ 60 с** — по `day_cards.answered_at`: все отвеченные карточки дня по времени ответа; промежуток от предыдущего ответа дня (любого этапа) до этого ответа относится к этапу этой карточки и считается, если он ≤ 60 с; первый ответ дня промежутка не несёт. `plan_stage_passages` у обоих дней содержит только строку `conversation` (этапы карточек в этот журнал не пишутся), поэтому время этапов карточек — только по `answered_at`. У разговора — промежутки между соседними ходами `conversation_turns.created_at` ≤ 60 с.
- **исключено** — число и сумма промежутков > 60 с, отнесённых к этапу.
- **по правилу дня (≤ 600 с)** — то же, но с порогом `DayMetricsCalculator::PAUSE_SECONDS` = 600, которым сервер считает `minutes_spent` дня; у разговора — `Conversation::activeSeconds()` (промежутки между ходами, каждый обрезан до 60 с) и `minutes()`.
- **наполнений** — различные пары (каркас, `filler_index`), которыми сказаны карточки фраз этапа (`PhraseSeries::fillerOf` + `rounds[].filler_index` у «Скажи целиком»). **слов / каркасов / реплик** — различные (сцена карточки, `unit_ref`). **кругов** — сумма `rounds` карточек `phrase_other_slot` (+ число `own_round`). **узнаваний** — карточки видов `phrase_slot`, `phrase_choose_back`, `phrase_slot_listen`, `phrase_assemble` (`PhraseSeries::CYCLE`). **копий** — карточки с `retry_of` (повтор в конце этапа). **возвратов** — `source = returned`.

До старта (ответ дня 16:50:34): `window.day.minutes_estimate` **34**, `status` not_started. **День 1** — «Ресепшен зала», `status` closed, открыт 2026-09-21T16:50:34+00:00, закрыт 2026-09-21T17:20:33+00:00; `cards_total` 85, `cards_done` 85, `minutes_spent` **29**. План после дня: этапы карточек 33 мин + разговор 3 мин; потолок карточек дня `plan.day_cards_budget` = 32 мин; потолок «Фраз» `plan.phrases_budget` = 690 с. `minutes_spent` = карточки 1605 с → 27 мин + разговор 2 мин = 29.

| этап | план до старта, мин | план после дня, мин | факт ≤ 60 с | исключено > 60 с | по правилу дня (≤ 600 с) | карточек | по видам | слов | каркасов | наполнений | реплик | кругов | узнаваний | возвратов с дня 1 |
|---|---|---|---|---|---|---|---|---|---|---|---|---|---|---|
| `words` | нет поля | 5 | 1:42 | 0 | 1:42 | 24 | word_intro 8<br>word_repeat 8<br>word_choose 2<br>word_listen 2<br>word_in_line 2<br>word_assemble 2 | 8 | 0 | — | 0 | — | — | 0 |
| `phrases` | нет поля | 12 | 6:15 | 3 · 3:40 | 9:55 | 30 (копий 1) | phrase_intro 7<br>phrase_choose_back 5<br>phrase_slot_listen 4<br>phrase_slot 3<br>phrase_assemble 3<br>phrase_other_slot 7<br>phrase_combine 1 | 0 | 7 | 14 | 0 | 8 + своё 7 | 14 + копий 1 | 0 |
| `dialogue` | нет поля | 6 | 2:59 | 1 · 1:18 | 4:17 | 11 | dialogue_ask 4<br>dialogue_partner 3<br>dialogue_answer 3<br>dialogue_rescue 1 | 0 | 0 | — | 8 | — | — | 0 |
| `listen` | нет поля | 5 | 1:34 | 1 · 1:59 | 3:33 | 12 | listen_dialogue 1<br>listen_question 4<br>listen_review 1<br>listen_predict 4<br>listen_pace 1<br>listen_number 1 | 0 | 0 | — | 0 | — | — | 0 |
| `speak` | нет поля | 5 | 2:47 | 3 · 4:31 | 7:18 | 8 | speak_answer 6<br>speak_echo 1<br>speak_retell 1 | 0 | 0 | — | 7 | — | — | 0 |
| `conversation` | нет поля | 3 | 1:06 | 0 | код: 66 с → 2 мин | ходов 9 | начат 17:17:33, 1-й ход 17:17:36, окончен 17:18:42 | — | целей 7 | — | — | — | — | — |

До старта (ответ дня 12:11:52): `window.day.minutes_estimate` **37**, `status` not_started. **День 2** — «С тренером», `status` in_progress, открыт 2026-09-22T12:11:52+00:00, закрыт —; `cards_total` 85, `cards_done` 85, `minutes_spent` **27**. План после дня: этапы карточек 38 мин + разговор 3 мин; потолок карточек дня `plan.day_cards_budget` = 32 мин; потолок «Фраз» `plan.phrases_budget` = 690 с. `minutes_spent` = карточки 1512 с → 26 мин + разговор 1 мин = 27.

| этап | план до старта, мин | план после дня, мин | факт ≤ 60 с | исключено > 60 с | по правилу дня (≤ 600 с) | карточек | по видам | слов | каркасов | наполнений | реплик | кругов | узнаваний | возвратов с дня 1 |
|---|---|---|---|---|---|---|---|---|---|---|---|---|---|---|
| `words` | 5 | 5 | 1:30 | 0 | 1:30 | 24 | word_intro 8<br>word_repeat 8<br>word_assemble 2<br>word_choose 2<br>word_listen 2<br>word_in_line 2 | 8 | 0 | — | 0 | — | — | 0 |
| `phrases` | 12 | 14 | 5:10 | 4 · 10:32 | 15:42 | 23 (копий 1) | phrase_intro 7<br>phrase_slot_listen 3<br>phrase_choose_back 2<br>phrase_other_slot 8<br>phrase_slot 2<br>phrase_combine 1 | 0 | 7 | 14 | 0 | 16 + своё 8 | 7 | 0 |
| `dialogue` | 6 | 6 | 1:50 | 1 · 1:03 | 2:53 | 12 | dialogue_partner 4<br>dialogue_answer 4<br>dialogue_ask 3<br>dialogue_rescue 1 | 0 | 0 | — | 8 | — | — | 0 |
| `listen` | 5 | 5 | 2:03 | 0 | 2:03 | 11 | listen_dialogue 1<br>listen_question 4<br>listen_review 1<br>listen_predict 3<br>listen_pace 1<br>listen_number 1 | 0 | 0 | — | 0 | — | — | 0 |
| `speak` | 8 | 8 | 3:04 | 0 | 3:04 | 15 | speak_answer 6<br>speak_echo 1<br>speak_retell 8 | 0 | 0 | — | 14 | — | — | 7 |
| `conversation` | 3 | 3 | 0:51 | 0 | код: 51 с → 1 мин | ходов 9 | начат 12:37:12, 1-й ход 12:37:15, окончен 12:38:06 | — | целей 7 | — | — | — | — | — |

### 2.2 «Фразы» по каркасам и лестница

Лестница (`PhrasesStage`, наряд BACK-TAILS-2 §1): ступень 0 — сборка; 1 — третьи узнавания, пока влезают; 2 — третий круг «Скажи целиком» снимается (каркас из ≥ 3 значений оставляет 2); 3 — второе узнавание снимается; дальше — выдача сверх потолка и строка `plan.phrases_over_ceiling` в журнале приложения.

**День 1** (`lesson_json.phrases`, карточки этапа `phrases` по позициям):

| ref | каркас | наполнений в уроке | узнавания (позиция, вид → результат) | «Скажи целиком» (позиция: круги `expected_text` → результат) | прочие (intro, combine) |
|---|---|---|---|---|---|
| p1 | «Do you have ___?» | 3 | 4 phrase_choose_back → passed<br>7 phrase_slot_listen → passed | 10: Do you have a day pass?; Do you have a weekly pass? + своё → passed, попыток 5 | 1 phrase_intro → passed |
| p2 | «What ___ do you have?» | 2 | 5 phrase_slot_listen → passed<br>9 phrase_assemble → failed<br>30 phrase_assemble (копия) → passed | 14: What monthly memberships do you have? + своё → passed, попыток 3 | 2 phrase_intro → passed |
| p3 | «This is ___.» | 2 | 8 phrase_slot → passed<br>13 phrase_choose_back → passed | 18: This is my first visit. + своё → passed, попыток 3 | 3 phrase_intro → passed<br>29 phrase_combine → passed |
| p4 | «That works for me on ___.» | 2 | 12 phrase_slot_listen → passed<br>17 phrase_assemble → passed | 23: That works for me on weekdays. + своё → passed, попыток 2 | 6 phrase_intro → passed |
| p5 | «Where are ___?» | 3 | 16 phrase_slot → passed<br>21 phrase_choose_back → passed | 26: Where are the changing rooms? + своё → passed, попыток 4 | 11 phrase_intro → passed |
| p6 | «I'll return ___.» | 2 | 20 phrase_slot → passed<br>24 phrase_choose_back → passed | 27: I'll return the locker key. + своё → passed, попыток 3 | 15 phrase_intro → passed |
| p7 | «Can I pay ___?» | 2 | 22 phrase_choose_back → passed<br>25 phrase_slot_listen → passed | 28: Can I pay by card? + своё → passed, попыток 2 | 19 phrase_intro → passed |

**День 2** (`lesson_json.phrases`, карточки этапа `phrases` по позициям):

| ref | каркас | наполнений в уроке | узнавания (позиция, вид → результат) | «Скажи целиком» (позиция: круги `expected_text` → результат) | прочие (intro, combine) |
|---|---|---|---|---|---|
| p1 | «I'm working on ___.» | 3 | 4 phrase_slot_listen → passed | 7: I'm working on general fitness.; I'm working on strength. + своё → passed, попыток 3 | 1 phrase_intro → passed<br>22 phrase_combine → passed |
| p2 | «I have ___ of experience.» | 3 | 5 phrase_choose_back → passed | 9: I have about a year of experience.; I have six months of experience. + своё → passed, попыток 3 | 2 phrase_intro → passed |
| p3 | «I have some ___.» | 3 | 8 phrase_slot_listen → passed | 12: I have some shoulder pain.; I have some knee pain. + своё → passed, попыток 4 | 3 phrase_intro → passed |
| p4 | «How do I use ___?» | 3 | 11 phrase_slot → passed | 16: How do I use this machine?; How do I use the cable machine? + своё → passed, попыток 4 | 6 phrase_intro → passed |
| p5 | «How heavy should ___ be?» | 3 | 14 phrase_slot → passed | 19: How heavy should the weight be? + своё → passed, попыток 2 | 10 phrase_intro → passed |
| p6 | «I'll rest for ___.» | 3 | 17 phrase_slot_listen → passed | 20: I'll rest for forty-five seconds.; I'll rest for thirty seconds. + своё → skipped, попыток 2<br>23 (копия): I'll rest for forty-five seconds.; I'll rest for thirty seconds.; I'll rest for one minute. + своё → skipped, попыток 1 | 13 phrase_intro → passed |
| p7 | «Should I keep ___ down?» | 3 | 18 phrase_choose_back → passed | 21: Should I keep my shoulders down?; Should I keep my elbows down? + своё → passed, попыток 4 | 15 phrase_intro → passed |

**Лестница дня 2.** Пересчёт тем же кодом, которым день раздан (день 2 открыт 22.09 12:11:52 UTC; код BACK-TAILS-2 выкачен 22.09 11:13–11:17 UTC; изменений `app/`, `config/` в `main` после 12:11 UTC нет): `DayAssembler::phrasesDeal` над материалом сцены (`tools/views.php` → `raw/phrases-deal.json`).

| ступень | секунд | карточек |
|---|---|---|
| 0 | 949 | 28 |
| 1 | 949 | 28 |
| 2 | 799 | 28 |
| 3 | 688 | 22 |

Итог: 688 с при потолке 690 с, `over_ceiling` false. Что оставлено каркасам:

| ref | наполнений в уроке | `PhraseSeries::most` | ступень 0: узнаваний / кругов | после ступени 3: узнаваний / кругов / своё | роздано в день 2: узнаваний / кругов / своё (без копий) |
|---|---|---|---|---|---|
| p1 | 3 | 3 | 2 / 3 | 1 / 2 / true | 1 / 2 / true |
| p2 | 3 | 3 | 2 / 3 | 1 / 2 / true | 1 / 2 / true |
| p3 | 3 | 3 | 2 / 3 | 1 / 2 / true | 1 / 2 / true |
| p4 | 3 | 3 | 2 / 3 | 1 / 2 / true | 1 / 2 / true |
| p5 | 3 | 1 | 1 / 1 | 1 / 1 / true | 1 / 1 / true |
| p6 | 3 | 3 | 2 / 3 | 1 / 2 / true | 1 / 2 / true |
| p7 | 3 | 3 | 2 / 3 | 1 / 2 / true | 1 / 2 / true |

Журнал сборки: строк `plan.phrases_over_ceiling` с `plan_id` 01M32DX8QCABM348XP45Z1ZD4M в `storage/logs/laravel.log` — **0** (строка пишется только когда ступени кончились, а этап всё ещё дороже потолка). День 1 раздан 21.09 16:50:34 UTC — до выката BACK-TAILS-2; его ступени нигде не записаны, по данным дня — таблица «День 1» выше (в `raw/phrases-deal.json` есть и `day_1` — это пересчёт ТЕКУЩИМ кодом, не тем, что раздал день 1).

### 2.3 Лента дня 2

День открыт 2026-09-22T12:11:52+00:00 (`POST …/open` — 12:11:53). Этап: первый и последний ответ, ответ, которым закончился предыдущий этап, число пауз > 60 с внутри этапа (включая вход в этап).

| этап | конец предыдущего этапа (ответ) / открытие дня | первый ответ / старт | последний ответ / конец | от конца предыдущего до конца этапа | паузы > 60 с | без пауз > 60 с |
|---|---|---|---|---|---|---|
| `words` | 12:11:52 | 12:11:56 | 12:13:26 | 1:34 | 0 | 1:34 |
| `phrases` | 12:13:26 | 12:14:14 | 12:29:08 | 15:42 | 4 · 10:32 | 5:10 |
| `dialogue` | 12:29:08 | 12:29:16 | 12:32:01 | 2:53 | 1 · 1:03 | 1:50 |
| `listen` | 12:32:01 | 12:32:54 | 12:34:04 | 2:03 | 0 | 2:03 |
| `speak` | 12:34:04 | 12:34:20 | 12:37:08 | 3:04 | 0 | 3:04 |
| `conversation` | 12:37:08 | 12:37:12 | 12:38:06 | 0:58 | 0 | 0:58 |

Все паузы > 60 с между ответами дня 2 — с запросами телефона внутри паузы (`api_request_logs`, входящие по дню 2: чтения дня `GET …/days/2`, вызовы судьи `…/judge`; время ответа карточки = `answered_at`):

| с (ответ) | карточка перед паузой | по (ответ) | карточка после паузы | длительность | запросы внутри |
|---|---|---|---|---|---|
| 12:14:52 | phrases 6 phrase_intro → passed | 12:16:58 | phrases 7 phrase_other_slot → passed, попыток 3 | 2:06 | GET день ×1 (12:16:53) |
| 12:17:34 | phrases 9 phrase_other_slot → passed | 12:19:33 | phrases 10 phrase_intro → passed, попыток 1 | 1:59 | — |
| 12:20:15 | phrases 12 phrase_other_slot → passed | 12:21:44 | phrases 13 phrase_intro → passed, попыток 1 | 1:29 | — |
| 12:23:22 | phrases 20 phrase_other_slot → skipped | 12:28:20 | phrases 21 phrase_other_slot → passed, попыток 4 | 4:58 | GET день ×1 (12:27:45); judge ×2 (12:28:10, 12:28:19) |
| 12:29:24 | dialogue 2 dialogue_answer → passed | 12:30:27 | dialogue 3 dialogue_partner → passed, попыток 1 | 1:03 | GET день ×1 (12:30:25) |

Паузы > 60 с: 5 шт., 11:35. Промежутки ≤ 60 с между ответами дня 2: 13:37; разговор (между ходами): 0:51. От первого ответа (12:11:56) до конца разговора (12:38:06): 26:10.

### 2.4 Итог дня 2 как отдан клиенту (кадр 30-7)

Ответ `GET /plans/{id}/days/2` на 12:55:54 UTC 22.09 (последнее чтение дня телефоном) пересобран кодом приложения в режиме только чтения — `raw/room-day-2.json`; сверка с журналом: длина ответа 267527 байт = 267527 в `api_request_logs.response_bytes`, тело в журнале 200723 = 200723, первые 8192 байт превью совпадают. Клиент берёт минуты из `data.day.minutes_spent` (`session_controller.dart:286`), строки «Что было хорошо» — из `data.window.highlights` (`:275`).

| что на экране | поле | значение | из чего собрано |
|---|---|---|---|
| «N минут» | `data.day.minutes_spent` | 27 | `plan_days.minutes_spent`; = карточки (1512 с промежутков ≤ 600 с → 26 мин) + разговор, прошедший этап (`Conversation::minutes()` = 1) |
| строка 1 | `data.window.highlights[0]` | «Сказал сам 14 реплик из 15» | `DayHighlights`: карточки этапов `speak`/`recall`/`repetition` видов «говорю» — 15; `passed`/`hinted` — 14; прочие: speak 14 speak_retell x6 → skipped |
| строка 2 | `data.window.highlights[1]` | «В разговоре использовал 1 фразу из 7» | итог разговора, прошедшего этап: `phrases_used` 1 из `phrases_total` 7 (сказана: p1) |
| строка 3 | `data.window.highlights[2]` | «Понял вопросы, кроме 1 вопроса» | `not_understood` 1: ход 7 роли «What kind of pain is it?» — `understood: false` (ответ на ход ученика «Yes my») |
| статус окна | `data.window.day.status` | in_progress | день не закрыт (`plan_days.status` in_progress) |
| минуты окна | `data.window.day.minutes_spent` / `minutes_estimate` | — / 0 | `minutes_spent` окна — только у пройденного дня |
| кнопка | `data.window.allowed_action` | continue |  |
| «Повторить разговор» | `data.window.talk_again` | true |  |
| прогресс | `data.window.day_progress` | 1.0 |  |
| возвраты вкладок | `data.window.program.{words,phrases,dialogue}.summary` | words: 8/8, returns 0; phrases: 7/7, returns 0; dialogue: 15/15, returns 0 |  |

## §3 Возвраты дня 2

Карточки дня 2 с `source = returned` (`day_cards`); вид карточки как отдан — из пересобранного ответа дня (`raw/room-day-2.json`, совпал с журналом). `room.scene` дня 2 — «С тренером» (`01M32DXHYGYMK0E1DBR01PYDHA`). Полоса сцены в сессии: `SessionController.sceneOfCard` — `_day?.scene ?? _sceneById(card.payload.sceneId) ?? scene` (`mobile/lib/features/plan/session/session_controller.dart:211`): у дня со своей сценой берётся сцена дня, `payload.scene_id` читается только у дня без сцены.

| поз. в дне 2 | вид | реплика | own_line.text_target | text_native | payload.scene_id — сцена | source_day | результат в дне 2 | карточки этой реплики в дне 1 (этап, позиция, вид → результат) |
|---|---|---|---|---|---|---|---|---|
| 9 | speak_retell | x1 | «Do you have a day pass?» | «У вас есть дневной пропуск?» | `01M32DXHYG50H7SWQEMD33A39F` — «Ресепшен зала» | 1 | passed, попыток 1, heard «Do you have a day pass» | dialogue 1 dialogue_ask → passed<br>speak 1 speak_answer → hinted |
| 10 | speak_retell | x2 | «What monthly memberships do you have?» | «Какие у вас есть месячные абонементы?» | `01M32DXHYG50H7SWQEMD33A39F` — «Ресепшен зала» | 1 | passed, попыток 1, heard «What monthly membership do you have» | dialogue 2 dialogue_ask → passed<br>speak 2 speak_answer → hinted |
| 11 | speak_retell | x3 | «Yes, this is my first visit.» | «Да, это мой первый визит.» | `01M32DXHYG50H7SWQEMD33A39F` — «Ресепшен зала» | 1 | passed, попыток 1, heard «Yes this is my first visit» | dialogue 3 dialogue_partner → passed<br>dialogue 4 dialogue_answer → passed<br>speak 3 speak_answer → skipped |
| 12 | speak_retell | x4 | «That works for me on weekdays.» | «Мне это подходит по будням.» | `01M32DXHYG50H7SWQEMD33A39F` — «Ресепшен зала» | 1 | passed, попыток 1, heard «That works for me on weekdays» | dialogue 5 dialogue_partner → passed<br>dialogue 6 dialogue_answer → passed<br>speak 4 speak_answer → hinted |
| 13 | speak_retell | x5 | «Where are the changing rooms?» | «Где раздевалки?» | `01M32DXHYG50H7SWQEMD33A39F` — «Ресепшен зала» | 1 | passed, попыток 2, heard «Where is the changing room» | dialogue 7 dialogue_ask → passed<br>speak 5 speak_answer → hinted |
| 14 | speak_retell | x6 | «Okay, I'll return the locker key.» | «Хорошо, я верну ключ от шкафчика.» | `01M32DXHYG50H7SWQEMD33A39F` — «Ресепшен зала» | 1 | skipped, попыток 2, heard «OK I will return a locker key» | dialogue 8 dialogue_partner → passed<br>dialogue 10 dialogue_answer → passed<br>speak 6 speak_answer → hinted<br>speak 7 speak_echo → skipped |
| 15 | speak_retell | x8 | «Can I pay by card?» | «Я могу оплатить картой?» | `01M32DXHYG50H7SWQEMD33A39F` — «Ресепшен зала» | 1 | passed, попыток 1, heard «Can I pay my card» | dialogue 11 dialogue_ask → passed<br>speak 8 speak_retell → passed |

Возвратов в дне 2: 7, все — этап `speak`, вид `speak_retell`, `source_day_id` = день 1. У дня 1 карточек с `returns = true` — 0.

### 3.2 Озвучка своей реплики-возврата

Голос строки ищется по (сцена, ref, голос): `SceneAudioIndex::rowOf` — говорящий по ref (`xNb` — ученик), пол — `VoiceCast` сцены (собеседник — `plan_scenes.partner_voice_gender`, ученик — противоположный), ключ голоса — `SPEECH_VOICE_EN_{PARTNER|LEARNER}_{FEMALE|MALE}`. Сцена 1 «Ресепшен зала»: собеседник female; сцена 2 «С тренером»: собеседник male. День 1 — адрес звука из пересобранного ответа дня 1 (карточки дня 1 с этой же `own_line.ref`) и, где есть, из ответа `…/answer`, сохранённого журналом 21.09; день 2 — из ответа дня 2 и ответа `…/answer` карточки-возврата 22.09. Для сравнения — файл той же ref у сцены 2.

| поз. | ref | реплика | день 1: audio id (ответ дня) | день 1: audio id (журнал answer) | день 2: audio id (ответ дня) | день 2: audio id (журнал answer) | файл (`plan_line_audios`) | voice id | роль и пол по данным | та же ref у сцены 2 (для сравнения) |
|---|---|---|---|---|---|---|---|---|---|---|
| 9 | x1b | «Do you have a day pass?» | 01M32DYZ42DK3Z5K90ADSATAYZ | 01M32DYZ42DK3Z5K90ADSATAYZ | 01M32DYZ42DK3Z5K90ADSATAYZ | 01M32DYZ42DK3Z5K90ADSATAYZ | сцена «Ресепшен зала», `plan-audio/01M32DXHYG50H7SWQEMD33A39F/x1b-1319dc3f9adf.mp3`, создан 2026-09-21T16:49:35+00:00, 23 симв., $0.0012 | `TWutjvRaJqAX89preB4e` = SPEECH_VOICE_EN_LEARNER_MALE | ученик (ref `x1b`), сцена 1 собеседник female → ученик male | `01M32FRSEDMGQCY4V9HVJVBXDV`, `Nhs7eitvQWFTQBsf0yiT` = SPEECH_VOICE_EN_LEARNER_FEMALE, 2026-09-21T17:21:10+00:00 |
| 10 | x2b | «What monthly memberships do you have?» | 01M32DYZ4WG07AHFAKEYT8DP99 | 01M32DYZ4WG07AHFAKEYT8DP99 | 01M32DYZ4WG07AHFAKEYT8DP99 | 01M32DYZ4WG07AHFAKEYT8DP99 | сцена «Ресепшен зала», `plan-audio/01M32DXHYG50H7SWQEMD33A39F/x2b-1319dc3f9adf.mp3`, создан 2026-09-21T16:49:35+00:00, 37 симв., $0.0018 | `TWutjvRaJqAX89preB4e` = SPEECH_VOICE_EN_LEARNER_MALE | ученик (ref `x2b`), сцена 1 собеседник female → ученик male | `01M32FRTFA0P56EG44CM976AQP`, `Nhs7eitvQWFTQBsf0yiT` = SPEECH_VOICE_EN_LEARNER_FEMALE, 2026-09-21T17:21:11+00:00 |
| 11 | x3b | «Yes, this is my first visit.» | 01M32DYZY289WESY1B4PY4DE7C | 01M32DYZY289WESY1B4PY4DE7C | 01M32DYZY289WESY1B4PY4DE7C | 01M32DYZY289WESY1B4PY4DE7C | сцена «Ресепшен зала», `plan-audio/01M32DXHYG50H7SWQEMD33A39F/x3b-1319dc3f9adf.mp3`, создан 2026-09-21T16:49:36+00:00, 28 симв., $0.0014 | `TWutjvRaJqAX89preB4e` = SPEECH_VOICE_EN_LEARNER_MALE | ученик (ref `x3b`), сцена 1 собеседник female → ученик male | `01M32FRTFRS7MYCV36A5YMA7V5`, `Nhs7eitvQWFTQBsf0yiT` = SPEECH_VOICE_EN_LEARNER_FEMALE, 2026-09-21T17:21:11+00:00 |
| 12 | x4b | «That works for me on weekdays.» | 01M32DZ0Y8BEHAQV6HQVP1GFX2 | 01M32DZ0Y8BEHAQV6HQVP1GFX2 | 01M32DZ0Y8BEHAQV6HQVP1GFX2 | 01M32DZ0Y8BEHAQV6HQVP1GFX2 | сцена «Ресепшен зала», `plan-audio/01M32DXHYG50H7SWQEMD33A39F/x4b-1319dc3f9adf.mp3`, создан 2026-09-21T16:49:37+00:00, 30 симв., $0.0016 | `TWutjvRaJqAX89preB4e` = SPEECH_VOICE_EN_LEARNER_MALE | ученик (ref `x4b`), сцена 1 собеседник female → ученик male | `01M32FRVGDHHFASZVS340H578E`, `Nhs7eitvQWFTQBsf0yiT` = SPEECH_VOICE_EN_LEARNER_FEMALE, 2026-09-21T17:21:12+00:00 |
| 13 | x5b | «Where are the changing rooms?» | 01M32DZ0YBNR8BB4GD4NVDN55V | 01M32DZ0YBNR8BB4GD4NVDN55V | 01M32DZ0YBNR8BB4GD4NVDN55V | 01M32DZ0YBNR8BB4GD4NVDN55V | сцена «Ресепшен зала», `plan-audio/01M32DXHYG50H7SWQEMD33A39F/x5b-1319dc3f9adf.mp3`, создан 2026-09-21T16:49:37+00:00, 29 симв., $0.0014 | `TWutjvRaJqAX89preB4e` = SPEECH_VOICE_EN_LEARNER_MALE | ученик (ref `x5b`), сцена 1 собеседник female → ученик male | `01M32FRVGKPR3XZF7636G1S1ZK`, `Nhs7eitvQWFTQBsf0yiT` = SPEECH_VOICE_EN_LEARNER_FEMALE, 2026-09-21T17:21:12+00:00 |
| 14 | x6b | «Okay, I'll return the locker key.» | 01M32DZ1ZXY05B24VN6QFK9HSR | 01M32DZ1ZXY05B24VN6QFK9HSR | 01M32DZ1ZXY05B24VN6QFK9HSR | 01M32DZ1ZXY05B24VN6QFK9HSR | сцена «Ресепшен зала», `plan-audio/01M32DXHYG50H7SWQEMD33A39F/x6b-1319dc3f9adf.mp3`, создан 2026-09-21T16:49:38+00:00, 33 симв., $0.0016 | `TWutjvRaJqAX89preB4e` = SPEECH_VOICE_EN_LEARNER_MALE | ученик (ref `x6b`), сцена 1 собеседник female → ученик male | `01M32FRWK36H6GXY71EM3TPK7E`, `Nhs7eitvQWFTQBsf0yiT` = SPEECH_VOICE_EN_LEARNER_FEMALE, 2026-09-21T17:21:13+00:00 |
| 15 | x8b | «Can I pay by card?» | 01M32DZ2YRHG1STHJ27JDJH4JD | 01M32DZ2YRHG1STHJ27JDJH4JD | 01M32DZ2YRHG1STHJ27JDJH4JD | 01M32DZ2YRHG1STHJ27JDJH4JD | сцена «Ресепшен зала», `plan-audio/01M32DXHYG50H7SWQEMD33A39F/x8b-1319dc3f9adf.mp3`, создан 2026-09-21T16:49:39+00:00, 18 симв., $0.0008 | `TWutjvRaJqAX89preB4e` = SPEECH_VOICE_EN_LEARNER_MALE | ученик (ref `x8b`), сцена 1 собеседник female → ученик male | `01M32FRXJFQQD5SZ67C9RDGK80`, `Nhs7eitvQWFTQBsf0yiT` = SPEECH_VOICE_EN_LEARNER_FEMALE, 2026-09-21T17:21:14+00:00 |

## §4 Сырьё трёх карточек дня 2

### 4.1 «Скажи фразу с каждым значением» — каркас с «weight loss»

Карточка `01M34GF5GVK9NJPW7MFZAAC6YB`, этап `phrases`, позиция 7, вид `phrase_other_slot`, каркас `p1`. Полный payload как отдан — `raw/cards-s3-s4.json` → `s4_weight_loss.card`.

| поле | значение |
|---|---|
| `frame.frame_target` / `frame_native` | «I'm working on ___.» / «Я работаю над ___.» |
| `frame.slot.fillers` (как отдан) | 0: general fitness — общей формой (`in_dialogue` true)<br>1: strength — силой (`in_dialogue` false)<br>2: weight loss — снижением веса (`in_dialogue` false) |
| `rounds` | filler 0: «I'm working on general fitness.» — «Я работаю над общей формой.»<br>filler 1: «I'm working on strength.» — «Я работаю над силой.» |
| `own_round` | {"judge": true, "examples": ["общей формой", "силой", "снижением веса"], "speech_mode": "free", "task_native": "Я работаю над ___."} |
| `speech_mode` | repeat |
| `partner_line` | «What are you training for today?» — «Над чем вы сегодня хотите поработать?» |
| `key` | «I'm working on» |
| результат | passed, `attempts` 3, ответ 2026-09-22T12:16:58+00:00 |
| `response` | {"heard": "I'm working on a strength", "judge": {"by": "code", "model": null, "accepted": true, "cost_usd": null, "tokens_in": null, "latency_ms": null, "tokens_out": null, "reason_native": null, "prompt_version": null}} |

Наполнения каркаса p1 в уроке (`lesson_json.phrases`): ● general fitness — общей формой; ○ strength — силой; ○ weight loss — снижением веса.

Лестница: в пересчёте раздачи дня 2 (§2.2) у каркаса p1 на ступени 0 — узнаваний 2, кругов 3; после ступени 3 — узнаваний 1, кругов 2, своё true; круги снимает только ступень 2 (`PhraseCards::rounds` ограничивается `ROUNDS_FLOOR` = 2 в `PhrasesStage::deal`, строки 160–177); секунды «Фраз» по ступеням: 0 — 949 с, 1 — 949 с, 2 — 799 с, 3 — 688 с. В журнале приложения строк `plan.phrases_over_ceiling` этого плана — 0.

Запросы телефона по карточке (`api_request_logs`; `tokens_in`/`tokens_out` в журнале скрыты его редактором секретов — ключ содержит «token»):

| время | запрос | тело запроса | вердикт в ответе |
|---|---|---|---|
| 12:16:58 | judge | {"heard": "I'm working on a strength", "hinted": false} | {"by": "code", "model": null, "accepted": true, "cost_usd": null, "tokens_in": "[REDACTED]", "latency_ms": null, "tokens_out": "[REDACTED]", "reason_native": null, "prompt_version": null} |
| 12:16:58 | answer | {"result": "passed", "attempts": 3, "response": {"heard": "I'm working on a strength"}} | {"by": "code", "model": null, "accepted": true, "cost_usd": null, "tokens_in": "[REDACTED]", "latency_ms": null, "tokens_out": "[REDACTED]", "reason_native": null, "prompt_version": null} |

### 4.2 «Что тебе сказали?» — вариант «К ушам»

«Что тебе сказали?» клиент ставит над проверкой `dialogue_partner` (33-1) и над проверкой `dialogue_ask` после зачёта голосом (33-5), `mobile/lib/features/plan/session/cards/dialogue_cards.dart:59,156`. Варианты — три варианта проверки своего обмена из урока и четвёртый — верный вариант проверки самого дальнего по шагу обмена (`DialogueCards::check`, `farthestRightOption`). Колонка «источник» — где текст варианта стоит в проверках урока (`lesson_json.dialogue[].check.options`, ✓ — верный там). Выбор ученика — поле `choice` запроса `…/answer` (шлёт только `dialogue_ask`).

Карточки с «К ушам»:

| день | поз. | вид | обмен | реплика собеседника (`partner_line`) | `question_native` | `options` (✓ = `correct`) ← источник | выбор ученика | результат |
|---|---|---|---|---|---|---|---|---|
| 2 | 1 | dialogue_partner | x1 | «What are you training for today?»<br>«Над чем вы сегодня хотите поработать?» | «О чём спрашивает тренер?» | o1: Способ оплаты ← x1<br>o2: Номер шкафчика ← x1<br>o3: К ушам ← x8 ✓<br>**o4: Цель тренировки на сегодня ✓** ← x1 ✓ | — | passed, попыток 1 |
| 2 | 3 | dialogue_partner | x2 | «How much gym experience do you have?»<br>«Какой у вас опыт тренировок в зале?» | «Что хочет узнать тренер?» | **o1: Как долго вы уже тренируетесь ✓** ← x2 ✓<br>o2: Сколько воды вы пьёте ← x2<br>o3: Как часто вы делаете растяжку ← x2<br>o4: К ушам ← x8 ✓ | — | passed, попыток 1 |
| 2 | 5 | dialogue_partner | x3 | «Do you have any injuries or pain right now?»<br>«Есть ли у вас сейчас травмы или боль?» | «Что проверяет тренер?» | o1: Ваш целевой вес ← x3<br>**o2: Есть ли сейчас боль или травма ✓** ← x3 ✓<br>o3: К ушам ← x8 ✓<br>o4: Ваш следующий день тренировки ← x3 | — | passed, попыток 1 |
| 2 | 7 | dialogue_ask | x4 | «Sit tall, keep your back flat, and push through your heels.»<br>«Сядьте ровно, держите спину прямой и отталкивайтесь пятками.» | «Как нужно отталкиваться?» | **o1: Пятками ✓** ← x4 ✓<br>o2: Носками ← x4<br>o3: К ушам ← x8 ✓<br>o4: Одной ногой ← x4 | o1 | passed, попыток 1 |
| 2 | 12 | dialogue_ask | x8 | «Yes, keep them down and don't lift them toward your ears.»<br>«Да, держите их опущенными и не поднимайте к ушам.» | «Куда не нужно поднимать плечи?» | **o1: К ушам ✓** ← x8 ✓<br>o2: К шее ← x8<br>o3: Цель тренировки на сегодня ← x1 ✓<br>o4: К груди ← x8 | o1 | passed, попыток 1 |

Все остальные карточки этих видов в днях 1–2:

| день | поз. | вид | обмен | реплика собеседника (`partner_line`) | `question_native` | `options` (✓ = `correct`) ← источник | выбор ученика | результат |
|---|---|---|---|---|---|---|---|---|
| 1 | 1 | dialogue_ask | x1 | «Yes, a day pass is fifteen dollars.»<br>«Да, дневной пропуск стоит пятнадцать долларов.» | «Сколько, по словам администратора, стоит вариант на один день?» | o1: Двадцать долларов ← x1<br>o2: Десять долларов ← x1<br>o3: Наверху ← x8 ✓<br>**o4: Пятнадцать долларов ✓** ← x1 ✓ | o4 | passed, попыток 1 |
| 1 | 2 | dialogue_ask | x2 | «We have a basic plan and an unlimited plan.»<br>«У нас есть базовый план и безлимитный план.» | «Какие два варианта абонемента называет администратор?» | **o1: Обычный вариант и вариант с полным доступом ✓** ← x2 ✓<br>o2: Утренний план и вечерний план ← x2<br>o3: Студенческий план и семейный план ← x2<br>o4: Наверху ← x8 ✓ | o1 | passed, попыток 1 |
| 1 | 3 | dialogue_partner | x3 | «Is this your first visit here?»<br>«Это ваш первый визит сюда?» | «Что хочет узнать администратор?» | o1: Наверху ← x8 ✓<br>**o2: Был ли посетитель здесь раньше ✓** ← x3 ✓<br>o3: Нужен ли посетителю тренер ← x3<br>o4: Принёс ли посетитель полотенце ← x3 | — | passed, попыток 1 |
| 1 | 5 | dialogue_partner | x4 | «We open at six and close at ten on weekdays.»<br>«В будни мы открываемся в шесть и закрываемся в десять.» | «Во сколько зал закрывается в будни?» | o1: Наверху ← x8 ✓<br>**o2: В десять вечера ✓** ← x4 ✓<br>o3: В полдень ← x4 | — | passed, попыток 1 |
| 1 | 7 | dialogue_ask | x5 | «They are downstairs, next to the lockers.»<br>«Они внизу, рядом со шкафчиками.» | «Где, по словам администратора, находится зона для переодевания?» | o1: Наверху у кардиотренажёров ← x5<br>o2: Рядом с ресепшеном ← x5<br>o3: Пятнадцать долларов ← x1 ✓<br>**o4: На нижнем этаже рядом со шкафчиками ✓** ← x5 ✓ | o4 | passed, попыток 1 |
| 1 | 8 | dialogue_partner | x6 | «Please bring a towel, use clean shoes, and return the locker key after training.»<br>«Пожалуйста, возьмите полотенце, используйте чистую обувь и верните ключ от шкафчика после тренировки.» | «Что, по словам администратора, нужно вернуть после тренировки?» | o1: Полотенце ← x6, x7 ✓<br>o2: Карту доступа ← x6<br>**o3: Ключ от шкафчика ✓** ← x6 ✓<br>o4: Пятнадцать долларов ← x1 ✓ | — | passed, попыток 1 |
| 1 | 11 | dialogue_ask | x8 | «Yes, card is fine, and the training floor is upstairs.»<br>«Да, картой можно, а тренировочная зона наверху.» | «Где, по словам администратора, находится основная тренировочная зона?» | **o1: Наверху ✓** ← x8 ✓<br>o2: Пятнадцать долларов ← x1 ✓<br>o3: Внизу рядом с ресепшеном ← x8<br>o4: Снаружи у входа ← x8 | o1 | passed, попыток 1 |
| 2 | 9 | dialogue_ask | x6 | «Use a weight you can lift twelve times with good form.»<br>«Возьмите вес, который сможете поднять двенадцать раз с хорошей техникой.» | «Сколько качественных повторений хочет тренер?» | **o1: Двенадцать повторений ✓** ← x6 ✓<br>o2: Пятнадцать повторений ← x6<br>o3: Десять повторений ← x6<br>o4: Цель тренировки на сегодня ← x1 ✓ | o1 | passed, попыток 1 |
| 2 | 10 | dialogue_partner | x7 | «Do three sets, and rest forty-five seconds between them.»<br>«Сделайте три подхода и отдыхайте сорок пять секунд между ними.» | «Сколько длится перерыв?» | o1: Полминуты ← x7<br>o2: Цель тренировки на сегодня ← x1 ✓<br>**o3: Сорок пять секунд ✓** ← x7 ✓<br>o4: Около минуты ← x7 | — | passed, попыток 1 |

### 4.3 Карточки с «forty-five seconds»

Карточки дня 2, которые требуют сказать «forty-five seconds» (в `rounds`, `own_line` или `expected_text`; `listen_dialogue`/`listen_review` — только слушание, не включены). heard — `day_cards.response.heard` (последняя попытка) и тела запросов телефона `…/answer` и `…/judge` (`api_request_logs`); судья — блок `judge` ответа.

| поз. | этап | вид | ref | expected_text | speech_mode | результат | `response` (в базе) | запросы телефона |
|---|---|---|---|---|---|---|---|---|
| 20 | phrases | phrase_other_slot | p6 | «I'll rest for forty-five seconds.»<br>«I'll rest for thirty seconds.» | repeat; своё: free | skipped, попыток 2 | {"heard": "I will rest for 45 seconds"} | 12:23:22 answer: {"result": "skipped", "attempts": 2, "response": {"heard": "I will rest for 45 seconds"}} |
| 23 (копия) | phrases | phrase_other_slot | p6 | «I'll rest for forty-five seconds.»<br>«I'll rest for thirty seconds.»<br>«I'll rest for one minute.» | repeat; своё: free | skipped, попыток 1 | {"heard": "I will rest for 45 seconds"} | 12:29:08 answer: {"result": "skipped", "attempts": 1, "response": {"heard": "I will rest for 45 seconds"}} |
| 11 | dialogue | dialogue_answer | x7 | «I'll rest for forty-five seconds.» | free | passed, попыток 1 | {"mode": "voice_hint", "heard": "I rest for forty-five seconds."} | 12:31:36 answer: {"result": "passed", "attempts": 1, "response": {"mode": "voice_hint", "heard": "I rest for forty-five seconds."}} |
| 6 | speak | speak_answer | x7 | «I'll rest for forty-five seconds.» | free | hinted, попыток 1 | {"heard": "I will be rest for 45 seconds", "judge": {"by": "model", "model": "gpt-5.4-mini-2026-03-17", "accepted": true, "cost_usd": "0.000668", "tokens_in": 759, "latency_ms": 917, "tokens_out": 22, "reason_native": null, "prompt_version": "slot_judge.v3"}, "hinted": true, "slot_value": "45 seconds"} | 12:35:24 judge: {"heard": "I will be rest for 45 seconds", "hinted": true} → judge {"by": "model", "model": "gpt-5.4-mini-2026-03-17", "accepted": true, "cost_usd": "0.000668", "tokens_in": "[REDACTED]", "latency_ms": 917, "tokens_out": "[REDACTED]", "reason_native": null, "prompt_version": "slot_judge.v3"} |

Попыток больше, чем сохранённых heard: позиция 20: `attempts` 2, запросов с heard — 1. Других записей о попытках нет: ни запросов `…/judge`, ни второго `…/answer` по этим карточкам; в `day_cards.response` — одно поле `heard`.

Правила сравнения речи, отданные с днём (`data.speech` ответа дня 2): `repeat_misses` 0; `number_words` — zero → 0, one → 1, two → 2, three → 3, four → 4, five → 5, six → 6, seven → 7, eight → 8, nine → 9, ten → 10, eleven → 11, twelve → 12, thirteen → 13, fourteen → 14, fifteen → 15, sixteen → 16, seventeen → 17, eighteen → 18, nineteen → 19, twenty → 20, thirty → 30, forty → 40, fifty → 50, sixty → 60, seventy → 70, eighty → 80, ninety → 90, hundred → 100, thousand → 1000; `unstressed_words` — a, an, the, to, of, in, on, at, for, with, by, from, into, about, over, under, after, before, through, up, down, out, off, as, than, am, is, are, was, were, be, been, being, do, does, did, have, has, had, will, would, shall, should, can, could, may, might, must.

## Чего нет в базе (где искал)

- **heard каждой попытки** карточек с распознаванием на телефоне (`phrase_other_slot` по кругам, `speak_retell`, `speak_echo`, `dialogue_answer`/`dialogue_ask`, `word_repeat`): в `day_cards.response` и в теле `…/answer` — только последняя попытка; `…/judge` вызывается лишь на кругах, которые судит сервер (своё окно «Скажи целиком», `speak_answer`), — для них каждая попытка есть в `api_request_logs`. `model_calls` — без привязки к карточке и без текста.
- **`phrases_used` модели** в `conversation_turns` не хранится (у ходов роли `[]` по построению) — взят из сырых ответов модели в `api_request_logs` (исходящие `api.openai.com`, `purpose = plan`).
- **Время этапов карточек**: `plan_stage_passages` у дней 1 и 2 содержит только строку `conversation`; открытие этапа не пишется нигде — только `day_cards.answered_at` и чтения дня `GET …/days/2` в `api_request_logs`.
- **Ступени лестницы дня 1** не записаны нигде (день раздан до BACK-TAILS-2); строки `plan.phrases_over_ceiling` для этого плана в `storage/logs/laravel.log` — 0.
- **Ответ `GET …/days/{n}`** в `api_request_logs` — только превью 8 КБ (`_truncated`); полный документ пересобран (`raw/room-day-*.json`). Ответы `POST …/open` и `…/close` — тоже превью.

## Файлы

| файл | что внутри |
|---|---|
| `raw/plan.json` | строка `plans`, все `plan_days`, `plan_scenes` без `lesson_json` |
| `raw/day-1.json`, `raw/day-2.json` | `plan_days`, `plan_scenes` целиком (с `lesson_json`), `plan_terms` сцены, все `day_cards` дня, `plan_stage_passages`, `plan_events` |
| `raw/conversation-day-{1,2}.json` | `conversations` + все `conversation_turns` разговора дня |
| `raw/api-log-conversation-day-{1,2}.json` | журнал запросов в окне разговора: ходы телефона (heard) и документы сервера, промпты и сырые ответы модели, вызовы голоса |
| `raw/api-log-days.json` | все входящие запросы телефона по дням 1 и 2 (open, чтения дня, answer, judge, close, conversation) |
| `raw/line-audios.json` | `plan_line_audios` сцен 1 и 2 |
| `raw/model-calls.json` | `model_calls` в окнах времени дней (связи с пользователем в таблице нет — берутся все строки окна) |
| `raw/room-day-{1,2}.json` | ответ `GET /plans/{id}/days/{n}` на 12:55:54 UTC 22.09, пересобран (окно, этапы, карточки как отданы, `speech`) |
| `raw/conversation-view-day-{1,2}.json` | документ разговора, пересобран (ходы, `targets`, `summary`) |
| `raw/phrases-deal.json` | раздача «Фраз» кодом `DayAssembler::phrasesDeal` по ступеням; `day_2` — код, которым день раздан; `day_1` — ТЕКУЩИЙ код |
| `raw/views-check.json` | проверки режима только чтения и сверка пересборки с журналом |
| `raw/cards-s3-s4.json` | карточки §3 и §4 как отданы телефону + запросы телефона по каждой |

## Коммит

Папка добавлена одним коммитом; коммит не может содержать собственный хеш — он в отчёте наряда и в `git log --oneline -- backend2/docs/research/gym-day2`.

