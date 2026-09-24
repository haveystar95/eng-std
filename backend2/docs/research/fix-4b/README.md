# FIX-4b — хвосты сервера разговора: союзы в судье, `hints.sentence`, промпт роли v3.2 (24.09.2026)

Наряд — Notion `3e50ed25-cde9-81da-8310-e61d9e08d15b`. Контекст — FIX-4 (SLV-143, `docs/research/fix-4/README.md`),
«Спорное» пп. 3–5 DECISIONS; этот наряд их закрывает (пп. 410–413). Работа — worktree `../backend2-fix4b`, ветка `fix-4b`,
сайдкар `wt_fix4b` (база `wordtrainer_fix4b_test`). Миграций нет. Покупки — одна живая репетиция на e2e, **$0.0376 из
$0.20**. Наряд назначен на Sonnet; исполнен сессией на Opus 5.5.

## 1. Что сделано

**§1. Союз начинает клаузу.** Ученики склеивают две конструкции в одно предложение, а распознавание отдаёт речь без
точки — засчитывался только первый каркас: «начало клаузы» было только началом предложения или местом сразу после
вводных слов его начала. Теперь часть каркаса до окна может стоять ещё и **сразу после союза** из нового ключа пакета
`clause_starters` — en: and, but, so, then, or (запятая перед союзом ничего не меняет — знаки судья снимает); ru/uk/ro —
пустой список, находок нет. Союз открывает клаузу, где бы он ни стоял, и в начале предложения тоже («But I have some
shoulder pain»). Остальные правила судьи FIX-4 §2 не тронуты: без союза середина предложения по-прежнему не начало.

Одно следствие пришлось довести, иначе склейка испортила бы значение первой конструкции: окно каркаса, у которого после
окна ничего нет, раньше тянулось до конца предложения — и «This is ___» во фразе «this is my first visit and I have about a
year of experience» получал бы в `value_target` «my first visit and I have about a year of experience» (клиент печатает
его «Ты сказал: …» и подсвечивает в пузыре). Теперь такое окно кончается **перед союзом, после которого тот же ход сказал
другую конструкцию** — «my first visit»; если после «and» конструкции нет («He has a fever and a sore throat»), окно
целиком. На вердикт (сказано / почти / нет) это не влияет, только на значение окна. Значение на проводе перечитывается
со всеми конструкциями, которые засчитал ход (`ConversationViews::valueIn`), — тем же судом, что засчитывал.

Код: `FrameJudge::starts/clauseStarts/entries/windowOf` (`match` отдаёт окно и его границы, значение считается в `move`),
ключ `clause_starters` в `config/lesson/lang/{en,ru,uk,ro}.php`.

**§2. `hints.sentence`.** Подсказка шла только придаточным под рамку клиента «Скажи, что …» — для цели-вопроса выходило
«Скажи, что мне сказать вам его температуру?». Новое поле `hints.sentence` — строка урока цели подсказки как она есть в
уроке, с заглавной и знаком конца («У него болит поясница.», «Нам нужно сделать рентген?»); та же цель и те же null,
что у `native`. Поле аддитивное, последним ключом `hints`; `hints.native` оставлен для сборки (20) и помечен «снять после
CLIENT-FIX-4» (в OpenAPI — `deprecated`); `hints.target`, `scene_id`, `ref` без изменений. Код: `ConversationHintView::
sentence`, `ConversationViews::hint`, `PlanJson::conversation`.

**§3. Промпт `conversation_agent.v3.2`.** v3.1 + ровно правила наряда (диф — §2 ниже): сказанное учеником правда, а
заготовка визита — только для несказанного; роль в своей компетенции (одно правило, два примера «как надо», без реплик
«как не надо»); не спрашивать то, что уже есть в HISTORY или EARLIER; новая роль на `start` не повторяет реплик прежней
и сразу открывает `LEAD_TO`. Из формы ответа сняты `phrases_used` и `checkpoint_done` — из промпта (JUDGEMENTS, OUTPUT),
строгой схемы (`PlanSchemas::conversationAgent`), фейка (`FakePlanModel::conversationPayload`) и тестов, которые их
подсовывали. v3.1 удалён; `PlanPromptFiles::CONVERSATION_FILE`, версия фейка и счётчики `plan_check_counters` — под v3.2.
Данные запроса роли не менялись.

**§4. Документы.** `docs/plan-api.md` — `hints.sentence`, раздел «Для CLIENT-FIX-4»: подсказка показывается как
`hints.sentence` без рамки, рамка снимается; судья целей — союз начинает клаузу. OpenAPI (оба файла). `docs/plan-v2.md`
§11 — союзы в судье, `hints.sentence`, «Правила роли v3.2» (и §§0, 1, 2 — версия и форма ответа). Реестр промптов
`docs/prompts/REGISTRY.md` — строка роли стояла на v2 со времён CONV-2, теперь v3.2. DECISIONS — пп. 410–413, «Спорное»
пп. 3–5 закрыты (п. 3 — союзы начинают клаузу; п. 4 — дверь к цели, не сказанной по новому судье, законна; п. 5 —
правила v3.2), четыре записи в «Отменено». ROADMAP — раздел FIX-4b, хвосты FIX-4 закрыты.

Канон-тесты: `tests/Unit/Plan/FrameJudgeTest.php` (четыре строки наряда слово в слово, запятая перед союзом, союз в
начале предложения, окно до союза и целиком, ru без союзов; строка «the machine and how do I use it» из «Спорного» п. 3
— теперь «сказано»), `tests/Feature/Plan/ConversationApiTest.php` (склейка двух целей через API — обе сказаны одним
ходом, у каждой своё окно и после перечитывания; `hints.sentence` у ответа и у вопроса рядом с придаточным и после
«почти»), `tests/Unit/Plan/ConversationPromptTest.php` (sha256 v3.2, каждое правило v3 · v3.1 · v3.2, ни слова о двух
полях, v3.1 удалён; схема и фейк — ровно `{reply_target, reply_native, understood, off_topic, opens, end}`).
Проверка тестов мутациями вручную: без союзов в `starts` — падают 2 теста судьи; без обрезки окна — 1; `valueIn` с одной
конструкцией — падает тест склейки через API.

## 2. Промпт v3.1 → v3.2 — диф-список

sha256 v3.1 `ed526ec971869b18c4f049e798b24799ff913b52ada5f92df628d7a100f6fe52` → v3.2
`de712b83cd717954e96a26acbdb473c307cb567b55cf46561c18e1d42f9c757c` (10 381 → 10 955 байт). Всё, что не названо, — байт в
байт.

1. Заголовок `CONVERSATION AGENT — v3.1` → `— v3.2`.
2. Новый абзац после TWO SIDES — **YOUR JOB**: «You do only what YOUR_ROLE does in real life, even where the prepared visit
   gives you more, and leave the rest to the person whose job it is: a receptionist books the visit and asks for the
   details, and it is the doctor who treats; a shop assistant sells and helps to choose, and it is a doctor who advises on
   health.»
3. Новый абзац после THE PREPARED VISIT — **THE LEARNER'S WORD**: «What the learner says about themselves and their
   situation is true: the facts of the prepared visit are only for what the learner has not said. Never correct it and
   never dispute it — where it differs from the prepared visit, the prepared visit is forgotten. Never ask what is already
   in HISTORY or EARLIER: you may only build on it.»
4. SCENES, после «…greet them first as this person, then open the door to LEAD_TO.» — добавлено: «You repeat no line of
   the person before you: your greeting is in your own words, and the door to LEAD_TO comes straight after it.»
5. JUDGEMENTS — сняты «phrases_used — the ids of TARGETS the learner actually said in HEARD, with a value of their own or
   the example, or an empty list.» и «checkpoint_done — always null: the server closes the scenes.»
6. OUTPUT — `{"reply_target": "...", "reply_native": "...", "understood": true, "off_topic": false, "opens": null, "end":
   "no"}` (без `"phrases_used": []` и `"checkpoint_done": null`).

## 3. Приёмка Б — три разговора 2DX8QC, FIX-4 / FIX-4b

Инструмент — `tools/replay-b.php`: приёмка А FIX-4 (`fix-4/tools/replay-a.php`) слово в слово по вычислению, запущенная
кодом того контейнера, в котором стоит, — один раз кодом `main` (FIX-4, одноразовый контейнер образа `app`), один раз
кодом ветки (сайдкар `wt_fix4b`); база боя в сессии READ ONLY, ключей нет, `Http::preventStrayRequests`, модель не
зовётся. Сравнение — `tools/compare-b.php`, таблица целиком — `live/replay-b.md`, данные — `live/replay-fix4.json` и
`live/replay-fix4b.json`.

**Итог: 37 строк из 37 совпали во всех трёх чтениях** (сцена приёмки, записанные чекпойнты, правило сервера); оба JSON
байт в байт одинаковы между собой и с `fix-4/live/acceptance-a.json` (`cmp`). Регрессий нет — и новому правилу здесь
нечего менять: ни в одной из 18 реплик ученика этих разговоров нет союза в середине предложения (and встречается только в
репликах роли, которые не судятся).

| день | ход | реплика | FIX-4 | FIX-4b | совпало |
|---|---|---|---|---|---|
| 1 | 2 ученик | Hello I need daily training | ничего | ничего | ✅ |
| 1 | 4 ученик | No I want to buy monthly membership | ничего | ничего | ✅ |
| 1 | 6 ученик | Yes it is my first visit | s1 p3 almost | s1 p3 almost | ✅ |
| 1 | 8 ученик | Weekdays works for me | ничего | ничего | ✅ |
| 2 | 2 ученик | Hi I am working on general fitness | s1 p1 said · «general fitness» | s1 p1 said · «general fitness» | ✅ |
| 2 | 4 ученик | I don't have any experience | s1 p2 said · «any» | s1 p2 said · «any» | ✅ |
| 2 | 6 ученик | Yes my | ничего | ничего | ✅ |
| 2 | 8 ученик | I have lower back pain | s1 p3 almost | s1 p3 almost | ✅ |
| 3 | 1 роль | Hello! Welcome to the gym. How can I help you today? | дверь: принято | дверь: принято | ✅ |
| 3 | 2 ученик | Hello what kind of memberships do you have | s1 p2 said · «kind of memberships» | s1 p2 said · «kind of memberships» | ✅ |
| 3 | 3 роль | We have a basic plan and an unlimited plan. Is this your first visit here? | дверь: принято | дверь: принято | ✅ |
| 3 | 4 ученик | Yes it is my first visit | s1 p3 almost | s1 p3 almost | ✅ |
| 3 | 5 роль | Great, thanks. We open at six and close at ten on weekdays. | дверь: принято | дверь: принято | ✅ |
| 3 | 6 ученик | Nice work days work for me | ничего | ничего | ✅ |
| 3 | 7 роль | …What kind of experience do you have with gym workouts? | дверь: принято | дверь: принято | ✅ |
| 3 | 8 ученик | I have about one year of experience | s2 p2 said · «about one year» | s2 p2 said · «about one year» | ✅ |
| 3 | 9 роль | Thanks. Do you have any injuries or pain right now? | дверь: foreign_scene | дверь: foreign_scene | ✅ |
| 3 | 10 ученик | Yes I have a shoulder pain | s2 p3 almost | s2 p3 almost | ✅ |
| 3 | 11 роль | …Use light weights today and stop if it hurts. | дверь: foreign_scene | дверь: foreign_scene | ✅ |
| 3 | 12 ученик | OK thank you how do I use this machine | s2 p4 extra · «this machine» | s2 p4 extra · «this machine» | ✅ |
| 3 | 13 роль | Sit tall, keep your back flat, and push through your heels. | дверь: принято | дверь: принято | ✅ |
| 3 | 14 ученик | OK thank you I will rest for 45 seconds | s2 p6 extra · «45 seconds» | s2 p6 extra · «45 seconds» | ✅ |
| 3 | 16 ученик | I have some shoulder pain | s2 p3 said · «shoulder pain» | s2 p3 said · «shoulder pain» | ✅ |
| 3 | 18 ученик | OK great | ничего | ничего | ✅ |

(В таблице — ходы ученика и реплики роли с дверью; реплики роли без двери — в `live/replay-b.md`, там тоже ✅.) Ход 7
репетиции — «дверь: принято» в обоих: это и есть решение «Спорного» п. 4 (DECISIONS п. 411).

## 4. Приёмка В — живая репетиция на e2e

Стенд: бэкап e2e `wordtrainer_e2e_test-20260924-125732.sql.gz` (`--safety`); `php -S 127.0.0.1:8012` ВЕТКИ в сайдкаре
`wt_fix4b` на базе `wordtrainer_e2e_test` (`QUEUE_CONNECTION=sync`, `SPEECH_ENABLED=true`); ученик —
`tools/live-rehearsal.php`, сценарий FIX-4 ход в ход (план `01M2QRH5MY…`, `qa-gen3-doctor@wt.test`, день 3 — репетиция
«Запись к врачу» + «Приём у врача»). Голос врача не переключался (обе роли — female, `4NejU5DwQjevnR6mh3mb`). Разговор
`01M39DK2G736PG45CTA2ZCHDQB`, 09:57:47–09:58:08 UTC; стенограмма — `live/live-rehearsal.txt`, ответы API — `live/
live-rehearsal.json`.

```
 1 РОЛЬ  [начало «Запись к врачу»] Hello. What hurts — his upper back or his lower back?
         подсказка: «У него болит поясница.» · p1
 2 УЧЕНИК It hurts in his lower back.                                           · сказано p1
 3 РОЛЬ  That sounds painful. Did it start today, or earlier this week?          подсказка: «Началось три дня назад.» · p2
 4 УЧЕНИК It started two days ago.                                              · сказано p2 (своё значение)
 5 РОЛЬ  That's helpful. Is the pain sharp, or more of a dull ache when he bends?
         подсказка: «Боль острая, когда он наклоняется.» · p3
 6 УЧЕНИК The pain is sharp when she bends.                                     · p3 ПОЧТИ
 7 РОЛЬ  Is it your son who has the pain?
         подсказка: «Боль острая, когда он наклоняется.» + точная строка «The pain is sharp when he bends.» · p3
 8 УЧЕНИК The pain is sharp when he bends.                                      · сказано p3
 9 РОЛЬ  It sounds like a muscle strain. Does he have a fever?                  подсказка: «Температуры у него нет.» · p4
10 УЧЕНИК He doesn't have a fever.                                              · сказано p4 → все цели сцены сказаны
11 РОЛЬ  [прощание «Запись к врачу»] Thank you. Goodbye.
12 РОЛЬ  [начало «Приём у врача»] Good morning. What seems to be the problem with your son today?
         подсказка: «У него температура и болит горло.» · p1
13 УЧЕНИК He has a fever and a sore throat.                                     · сказано p1
14 РОЛЬ  How long has he had these symptoms?                                    подсказка: «Он болеет уже три дня.» · p2
15 УЧЕНИК He's been sick for three days. I gave him paracetamol.               · сказано p2 · ещё вспомнил p4
16 РОЛЬ  What is the highest temperature you’ve measured?                       подсказка: «Мне сказать вам его температуру?» · p3
17 УЧЕНИК Should I tell you his temperature?                                    · сказано p3 → последняя сцена пройдена
18 РОЛЬ  [прощание «Приём у врача»] Yes, please tell me the highest reading. Goodbye.
итог: ended · natural · ended_by_limit false · 7 из 7 · ещё вспомнил p4
```

| ожидание наряда | FIX-4 | FIX-4b |
|---|---|---|
| ход 5 — роль принимает «two days ago» без спора | ✗ «You said two days ago, but earlier you said three days ago» | ✅ «That's helpful. Is the pain sharp, or more of a dull ache when he bends?» |
| ход 7 — регистратор не лечит | ✗ «That sounds like a muscle strain. He should rest and use a heating pad.» | ✅ «Is it your son who has the pain?» (уточнил «she» ученика) |
| ход 16 — врач не спрашивает про лекарство после «I gave him paracetamol» | ✗ «Did you give him any medicine for the fever?» | ✅ «What is the highest temperature you’ve measured?» |
| на `start` ноль отбраковок `own_line` | ✗ ход 12, попытка 1 — `own_line` | ✅ 0 — и во всём разговоре `conversation_rejections` пусто (в FIX-4 — 2) |
| `hints.sentence` каждого хода — целая фраза с заглавной и знаком | — (поля не было) | ✅ 8 из 8 |

Все четыре ожидания и проверка подсказки держат с первого прогона — повтора не было.

**Замечено, в ожидания наряда не входит — честно, в ROADMAP «Хвосты FIX-4b»:**
- Ход 9: регистратор всё же ставит диагноз — «It sounds like a muscle strain.» (без лечения). Заготовка e2e-сцены
  «Booking» — урок фейковой модели, где у регистратора визит врача целиком («It looks like a muscle strain, so he should
  rest and use a heating pad»); YOUR JOB снял совет по лечению, диагноз из заготовки остался.
- Ход 12: врач не повторил реплику регистратора, но сказал первую строку своей заготовки («What seems to be the problem
  with your son today?» — шаг 1 урока «Doctor visit»); в этом прогоне регистратор открыл разговор другой фразой, поэтому
  условие FIX-4 (новая роль повторила первую реплику прежней) здесь не воспроизвелось — правило SCENES проверено на
  исходе, а не на той же развилке.
- Ход 16: цель T7 — вопрос ученика («Should I tell you his temperature?»), и роль спросила о температуре сама вместо
  того, чтобы дать ученику повод спросить (правило v3 «never ask it yourself» на `mini` держит не всегда).
- Ход 7: роль открыла дверь к T4 (жар), хотя `LEAD_TO` вёл к «почти»-цели T3; подсказка по правилу — T3 с точной
  строкой, ученик сказал её следующим ходом.

**Цена.** Модель: 10 вызовов (перезапросов 0) — **33 736 → 572 токена** (из них 21 760 из кэша), **$0.013188**
(`model_calls` сходится с `conversation_turns` до токена). Голос: **486 символов · 122 кредита · $0.0244**. Итого
**$0.037588 из $0.20** (кап разговора $0.08 не задет).

## 5. Что удалено

- `app/Modules/Plan/Infrastructure/Prompt/conversation_agent.v3.1.md` (переименован в v3.2 и изменён; v3.1 — в git).
- Поля `phrases_used` и `checkpoint_done` ответа роли: из промпта (JUDGEMENTS, OUTPUT), из строгой схемы
  `PlanSchemas::conversationAgent` (вместе с `$refs` — enum id целей для `phrases_used`), из
  `FakePlanModel::conversationPayload`. Колонки `conversation_turns.phrases_used` / `checkpoint_done` не тронуты — это
  серверные вердикт судьи и граница сцен (FIX-4).
- Тестовые подмены полей, которых больше нет: «роль называет все фразы на каждом ходе» (`phrases_used`), «роль закрывает
  сцену каждым ходом» (`checkpoint_done`), «роль „слышит" p1 на каждом ходе»; константа `CONVERSATION_AGENT_V3_1_SHA256`.
- Перечитывание значения окна по одной конструкции (`ConversationViews::valueIn` с `[$phrase]`) — теперь со всеми
  конструкциями хода.
- Устаревшая строка CONV-AGENT v2 в `docs/prompts/REGISTRY.md` (заменена строкой v3.2).

## 6. Ворота и выкат

Ворота — **один раз, в конце наряда**, на последнем коммите ветки (`76a202cd`: весь код и документы до выката), в
сайдкаре ветки `wt_fix4b` (`composer check`, база `wordtrainer_fix4b_test`). Промежуточные коммиты шли с `SKIP_GATES=1`
(гонялись только целевые тесты своего параграфа); коммит отчёта после выката — тоже `SKIP_GATES=1`: он трогает только
документы, код тот же, что прошёл ворота.

| что | итог |
|---|---|
| `composer check` | lint:openapi — оба файла ok; deptrac — **0 нарушений** (7 903 allowed, 3 uncovered); PHPStan — **No errors** (0); Pest — **2 501 passed, 24 003 assertions**, 124,2 с (параллельно, 10 процессов) |
| `flutter analyze` | No issues found (клиент наряд не трогал: `mobile/` в ветке и в `main` одинаков) |
| invariant-reviewer | **CLEAN** (Domain без фреймворка — `FrameJudge` на чистом PHP; Plan наружу не ходит; судит только сервер) |
| мутации (вручную) | без союзов в `FrameJudge::starts` — 2 теста падают; без обрезки окна в `windowOf` — 1; `valueIn` по одной конструкции — тест склейки через API падает |

**Выкат 24.09** (миграций нет — наряд их не требовал):

1. `DB=wordtrainer scripts/db-backup.sh --safety` → `wordtrainer-20260924-130747.sql.gz` (21 МБ); e2e перед живым
   прогоном — `wordtrainer_e2e_test-20260924-125732.sql.gz`.
2. `git merge --ff-only fix-4b` в `main` (10:07:55 UTC; `main` не уходил от `5ffaebfe`, rebase не нужен). `wt_app`,
   `wt_horizon`, `wt_scheduler` и `wt_app_e2e` исполняют рабочее дерево `main` — влитие и есть выкат.
3. `docker compose restart horizon` → `Horizon is running`.
4. Проверка: бой (`wt_app`) читает `conversation_agent.v3.2`; e2e (`wt_app_e2e`, `main`) отдаёт `hints` с ключом
   `sentence`.
5. Отчёт, handoff, ROADMAP — коммитом на `main`; `scripts/stamp-build.sh` — `/health` отдаёт хеш этого коммита.
6. Звук живой репетиции (10 файлов, оплачен) писался в `storage` worktree — перенесён в `storage` `main`
   (`plan-audio/conversations/01M39DK2G736PG45CTA2ZCHDQB/`): e2e отдаёт его 10 из 10. (Звук репетиции FIX-4 так и остался
   в снесённом worktree `../backend2-fix4` — у разговора `01M3958HM2…` на e2e файлов нет.)
7. Стенд ветки снесён: worktree `../backend2-fix4b`, ветка `fix-4b`, сайдкар `wt_fix4b`, база `wordtrainer_fix4b_test` и
   десять параллельных `wordtrainer_fix4b_test_test_N`. `wordtrainer_test` догонять нечего — миграций нет.

Админка не пересобиралась: её код наряд не трогал (`openapi-admin.yaml` — только описание ходов).

## 7. Коммиты (`main`)

| хеш | что |
|---|---|
| `d467b627` | §1 союз начинает клаузу: `clause_starters`, `FrameJudge`, окно до союза, `valueIn` со всеми конструкциями хода |
| `ce350cec` | §2 `hints.sentence` |
| `0a0eaa5c` | §3 `conversation_agent.v3.2`, схема и фейк без `phrases_used`/`checkpoint_done` |
| `76a202cd` | документы: контракт, OpenAPI, канон, реестр промптов, DECISIONS пп. 410–413, ROADMAP, приёмки Б и В |
| (этот) | отчёт, выкат, handoff |
