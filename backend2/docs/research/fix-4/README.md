# FIX-4 — сервер разговора: судья каркасов, граница сцен, подсказки, «Вспомнить» (24.09.2026)

Наряд — Notion `3e40ed25-cde9-81ec-8c24-e28deae3f32d` (SLV-143). Работа — worktree `../backend2-fix4`, ветка `fix-4`, сайдкар
`wt_fix4` (база `wordtrainer_fix4_test`); **влито в `main` fast-forward и выкачено 24.09** (порядок — §10). Модель — Opus;
покупки — одна живая репетиция на e2e, **$0.039** из $1.

Решения владельца по вопросам наряда (24.09): отрицание — вариант 1 «та же конструкция» (п. 395 сохраняется, канон-тесты
ниже); §6 — вариант 2, «маленькая таблица журнала» (`model_calls` не трогаем); живой прогон — вариант 2, «врач — male на
время прогона» (и тест на двух полах в наборе).

## 1. Что сделано

| § | что | где |
|---|---|---|
| §1 «Вспомнить» | файл звука ищется по сцене **узла**, где стоит заглушка (страница «Вспомнить», реплика возврата), а не по сцене карточки; тест канона «текст озвученной записи = текст строки» (без регистра и знака конца) + проверка ADM-1 «звук ≠ текст» по дню 3 | `CardViews::resolve/scenesIn`, `tests/Feature/Plan/RecallPageSoundTest.php` |
| §2 судья | `FrameJudge` — связная фраза: часть до окна подряд в начале предложения или после вводных слов его начала, в окне ≥ 1 своё слово, часть после — сразу за окном; ПОЧТИ — одно расхождение слова; нормализация `FrameWords` (регистр, знаки, сокращения пакета, артикли вне сравнения, числа как сказаны); отрицание — та же конструкция (`not` после be/модальных/have, `do/does/did not` + глагол по основе `WordBases`); партитив «of» — только для «сказано»; судятся только каркасы текущей сцены, несказанные; не-цели → `extra_said`; «почти» не закрывает (`conversation_turns.phrases_almost`); ход судится ДО вопроса к роли | `Domain/Service/FrameJudge`, `FrameWords`, `WordBases`; ключи пакета en `contractions`, `contractions_before`, `intro_words`, `negation`, `partitive` (ru/uk/ro — пусто) |
| §3 открытия | модели — цели ТЕКУЩЕЙ сцены с короткими id `T1…T7` (место в списке целей разговора); сервер маппит и сверяет: чужая сцена / уже сказана / неизвестный id → открытие отброшено, строка журнала; открыть «почти»-цель — можно, `LEAD_TO` ведёт к ней первой | `ConversationMaterialView::shortId/byShortId`, `ConversationMoves::door`, `ConversationLead::next` |
| §4 граница сцен | `conversation_turns.scene_id`; бюджет сцены = целей + 1; сцена закрыта, когда её цели сказаны или бюджет исчерпан → прощание роли этой сцены (`scene_event: end`, `SCENE_END`, без открытия, чекпойнт здесь) → в том же ходе НОВАЯ роль здоровается (`start`, голос по полу) и открывает первую цель; прощание последней сцены — конец; лимит раньше — всё равно прощание, `ended_by_limit` (значения `ended_reason` не менялись; в админке — «лимит»). Ходов разговора по сценам = сумма бюджетов (на 2 сценах — те же 7 + 2) | `ConversationMoves::answer/sceneOver/farewell/greet`, `ConversationRules::sceneTurnsFor/turnsForScenes`, `Conversation::movesIn/endedByLimit`, промпт `conversation_agent.v3.1` |
| §5 подсказки | `hints.native` — целая фраза урока ближайшей несказанной цели сцены (только что открытая, иначе первая по порядку) придаточным для «Скажи, что …» сборки (20); после «почти» по X — `hints.target` = точная строка X, `hints.ref`/`scene_id` = X; меняется каждым ходом | `ConversationLead::hint`, `ConversationViews::hint`, `ConversationHintView` |
| §6 хвосты | «p.m..» — `FrameText::fill` по `SentenceEnds` + `plan:rebuild-card-texts`; `plan_scenes.built_at` = КОНЕЦ сборки (backfill по первому `day_ready`), «Конвейер» ADM-1 читает его, `generated_at` не тронут; журнал отбраковок `conversation_rejections` (попытка, вид, причина, id вызова `model_calls`); голос роли — по полу роли сцены реплики | `FrameText`, `FrameSentences`, `PlanRebuildCardTextsCommand`, миграции `2026_09_24_100000/100100`, `ModelAnswer::callId` → `ModelReply::callId` → `ConversationAgentReply::callId` |
| §7 контракт | только добавления: `targets[].state`, `extra_said[]`, `hints.target/scene_id/ref`, `turns[].scene_id/scene_event/extra_said`, `summary.ended_by_limit/extra_said`; раздел «Для CLIENT-FIX-4: что показывать»; OpenAPI (оба файла) | `docs/plan-api.md`, `openapi/openapi.yaml`, `openapi/openapi-admin.yaml` |
| админка | «Разговоры»: сказана / почти / нет (ходы), короткий id, «ещё вспомнил», начало и прощание сцены, «почти» и «ещё вспомнил» хода, каждая отбраковка с причиной, исходом и id вызова, метка «лимит» | `wt_admin/src/views/plan/ConversationsSection.vue`, `TalkReport`, `EloquentPlanInspectionReader::talks` |

Канон: `docs/plan-v2.md` §§1, 6, 11; DECISIONS пп. **404–409**, «Отменено» (7 записей), «Спорное» пп. 3–5.

## 2. Промпт v3 → v3.1 — только правила наряда

sha256 v3 `8b2bc677403bbfb90c7dc62a5d62235dae406aa9afae8deac003339795e03d33` → v3.1
`ed526ec971869b18c4f049e798b24799ff913b52ada5f92df628d7a100f6fe52`; `conversation_agent.v3.md` удалён, реестр — v3.1
(`PlanPromptFiles::CONVERSATION_FILE`, `FakePlanModel`, счётчики `plan_check_counters` под `conversation_agent.v3.1`).
Отличия (всё остальное — байт в байт):

1. Заголовок `CONVERSATION AGENT — v3` → `— v3.1`.
2. INPUT: `YOUR_ROLE (who you are NOW …)`; `CHECKPOINTS (the scene you are in NOW — only it: …)` вместо «the scenes of
   this conversation in the order they happen … YOUR_ROLE is always the one of CURRENT_CHECKPOINT»; новый вход
   `EARLIER` (что ученик сказал в прежних сценах — факты; none в первой); `TARGETS (… IN THIS SCENE, each with its short
   id T1, T2 …)`; `LEAD_TO (the id of the target …)`; `HISTORY (every line said so far in this scene …)`; новый вход
   `SCENE_END (present only when the server has closed your scene, see SCENES)`.
3. LEAD THE CONVERSATION: «Say in `opens` the id (T…) of the TARGET … another target of TARGETS …»; фраза v3 «When the
   business of the current checkpoint is done … set checkpoint_done to its id and open the next checkpoint in the same
   reply» заменена на «The scenes are the server's: never close your scene and never begin the next one yourself.»
4. Новый абзац SCENES: одна сцена — одна роль, ты — роль сцены СЕЙЧАС; при `SCENE_END` — отреагировать в нескольких
   словах и попрощаться одним коротким предложением, ничего не спрашивать, `opens` null, `end` "no" (если `TURNS_LEFT`
   не 0); при `TURN: start` и непустом `EARLIER` — ты НОВЫЙ человек этой сцены: поздороваться первым и открыть `LEAD_TO`;
   `EARLIER` — знать, как знал бы этот человек, не пересказывать и не спрашивать снова.
5. JUDGEMENTS: `checkpoint_done — always null: the server closes the scenes.`

`phrases_used` и `checkpoint_done` остались в форме ответа (строгая схема), сервер их не читает — «v3.1 = v3 + ровно эти
правила». Кандидат на v3.2 — убрать оба поля из ответа.

## 3. Приёмка А — три разговора 2DX8QC новым судьёй (без модели)

Инструмент — `tools/replay-a.php` (сессия боя READ ONLY, ключей нет, `Http::preventStrayRequests`); таблицы всех ходов
трёх разговоров «было (записано) / стало» — `live/acceptance-a.md`, данные — `live/acceptance-a.json`.

**Репетиция `01M36X3W…`** (цели: T1–T4 = «Ресепшен» p1–p4, T5–T7 = «С тренером» p1–p3):

| ход | реплика | было | стало (сцена приёмки) | ожидание наряда | совпало |
|---|---|---|---|---|---|
| 2 | Hello what kind of memberships do you have | s1 p1, s1 p2 | s1 p2 said (p1 — нет: «do you have» не в начале) | s1 p2 said, p1 none | ✅ |
| 4 | Yes it is my first visit | s1 p3 | s1 p3 almost | s1 p3 almost | ✅ |
| 6 | Nice work days work for me | s1 p4, s2 p1 | ничего (s2 не судится) | s1 p4 none, s2 p1 не судим | ✅ |
| 7 роль | …What kind of experience… · открывает s1 p4 | — | дверь **принята** (по старому зачёту — already_said) | отброшено: p4 уже сказана | ⚠ см. ниже |
| 8 | I have about one year of experience | s2 p2 | s2 p2 said | s2 p2 said | ✅ |
| 9 роль | открывает s1 p3 | — | отброшено: foreign_scene | отброшено: сцена 1 не текущая | ✅ |
| 10 | Yes I have a shoulder pain | — | s2 p3 almost | s2 p3 almost | ✅ |
| 11 роль | открывает s1 p3 | — | отброшено: foreign_scene | отброшено: сцена 1 не текущая | ✅ |
| 12 | OK thank you how do I use this machine | — | s2 p4 extra_said | s2 p4 extra_said | ✅ |
| 14 | OK thank you I will rest for 45 seconds | — | s2 p6 extra_said | s2 p6 extra_said | ✅ |
| 16 | I have some shoulder pain | s2 p3 | s2 p3 said | s2 p3 said | ✅ |
| 18 | OK great | — | ничего | — | ✅ |

**Расхождения — объяснены, не подогнаны.**
- **Ход 7.** Ожидание «p4 уже сказана» стоит на старом зачёте хода 6 (ключевые слова «work … for me»); по новому судье
  ход 6 p4 не сказал — это ожидание самого наряда («6 → p4 none»). Значит, дверь к p4 на ходе 7 — законная дверь сцены 1:
  сервер её примет. Оба столбца в таблице (`стало` и «по старому зачёту»). Спорное п. 4.
- **Сцена хода.** Приёмка читает ходы 2–6 в «Ресепшене», 8+ — у «Тренера» (где разговор был на деле: администратор задал
  вопрос тренера на ходе 7, а чекпойнт закрыл на ходе 9). Правило нового сервера на тех же словах закрыло бы «Ресепшен»
  бюджетом после хода 10 (4 цели + 1), и ходы 8, 10 судились бы в сцене 1 — «ничего» (колонка «по правилу нового сервера»).
  Это не противоречие: роль v3.1 знает только цели своей сцены и не задала бы вопрос тренера на ходе 7 — такой разговор
  на новом сервере не случился бы.

**Дни 1 и 2** (одна сцена, таблицы — `live/acceptance-a.md`): день 1 — «Yes it is my first visit» → p3 almost (было:
ничего), «Weekdays works for me» → ничего (кадр «That works for me on ___» не с начала); день 2 — «Hi I am working on
general fitness» → p1 said (было p1), **«I don't have any experience» → p2 said** (канон владельца 24.09; было: ничего),
«Yes my» → ничего (обрыв), «I have lower back pain» → p3 almost (было: ничего). Прошедшие разговоры не пересчитаны:
в базе и в админке у них то, что записал старый судья.

Канон-тесты отрицания (решение владельца): «I don't have any experience» → said; «he doesn't have a fever» → said для
«He has ___»; «I have no experience» → said (`tests/Unit/Plan/FrameJudgeTest.php`).

## 4. Приёмка Б — живая репетиция на e2e

План `01M2QRH5MY…` (`qa-gen3-doctor@wt.test`), день 3 — репетиция двух сцен: «Запись к врачу» (Receptionist) и «Приём у
врача» (Doctor). Стенд: бэкап e2e (`wordtrainer_e2e_test-20260924-103034.sql.gz`), `migrate` e2e кодом ветки,
`php -S 127.0.0.1:8012` ветки в сайдкаре на e2e (`SPEECH_ENABLED=true`, очередь sync), ученик — `tools/live-rehearsal.php`
(реплики урока через API, одна — нарочно «почти»). **Голос врача:** `plan_scenes.partner_voice_gender` сцены
`01M2QRHF9M69AMBE2XGD18KQ8P` → male в **07:31:49 UTC**, обратно female в **07:33:00 UTC** (разговор — 07:32:13–07:32:44).

Стенограмма (`live/live-rehearsal.txt`, ответы API целиком — `live/live-rehearsal.json`):

```
 1 РОЛЬ  [начало «Запись к врачу»] Hello. What seems to be the problem today?      подсказка: «у него болит поясница» · p1
 2 УЧЕНИК It hurts in his lower back.                                             · сказано p1
 3 РОЛЬ  When did it start?                                                        подсказка: «началось три дня назад» · p2
 4 УЧЕНИК It started two days ago.                                                · сказано p2 (своё значение)
 5 РОЛЬ  You said two days ago, but earlier you said three days ago. Is the pain sharp when he bends?   подсказка p3
 6 УЧЕНИК The pain is sharp when she bends.                                       · p3 ПОЧТИ
 7 РОЛЬ  That sounds like a muscle strain. He should rest and use a heating pad.   подсказка p3 + точная строка «The pain is sharp when he bends.»
 8 УЧЕНИК The pain is sharp when he bends.                                        · сказано p3
 9 РОЛЬ  Is there a fever too?                                                     подсказка: «температуры у него нет» · p4
10 УЧЕНИК He doesn't have a fever.                                                · сказано p4 → все цели сцены сказаны
11 РОЛЬ  [прощание «Запись к врачу»] Good. Then it sounds like a muscle strain. Goodbye.
12 РОЛЬ  [начало «Приём у врача»] Hello. What symptoms does your son have today?   подсказка: «у него температура и болит горло» · p1
13 УЧЕНИК He has a fever and a sore throat.                                       · сказано p1
14 РОЛЬ  How long has he been sick?                                                подсказка: «он болеет уже три дня» · p2
15 УЧЕНИК He's been sick for three days. I gave him paracetamol.                  · сказано p2 · ещё вспомнил p4
16 РОЛЬ  Thank you. Did you give him any medicine for the fever?                   подсказка: «мне сказать вам его температуру?» · p3
17 УЧЕНИК Should I tell you his temperature?                                      · сказано p3 → последняя сцена пройдена
18 РОЛЬ  [прощание «Приём у врача»] Yes, please tell me the highest reading. Goodbye.
итог: ended · natural · ended_by_limit false · 7 из 7 · ещё вспомнил p4
```

| проверка | итог |
|---|---|
| прощание — `end`, чекпойнт на нём | ✅ ходы 11 и 18 (`checkpoint_done` = своя сцена) |
| приветствие новой роли — `start`, с открытием | ✅ ход 12 открыл T5 (`…18KQ8P:p1`) |
| смена голоса ровно на `start` | ✅ ходы 1–11 — `4NejU5DwQjevnR6mh3mb` (female), с хода 12 — `EnjklPXGBMNldCJ7jqkE` (male) |
| подсказки — полные фразы | ✅ каждая — фраза урока со значением, без «…» |
| «почти» → `hints.target` | ✅ ход 7: «The pain is sharp when he bends.»; на ходе 9 — снова null |
| все открытия — цели текущей сцены | ✅ `opens_target` ходов 1–9 — сцены 1, 12–16 — сцены 2; отброшенных открытий нет |
| журнал отбраковок | 2 строки: ход 7, попытка 1 — `learner_line` (роль сказала строку ученика «The pain is sharp when he bends.»; вызов `01M3958WFX…`); ход 12, попытка 1 — `own_line` (новая роль повторила «What seems to be the problem today?» администратора; вызов `01M395959B…`) |

**Цена.** Модель: 12 вызовов (10 реплик + 2 перезапроса) — **39 458 → 845 токенов** (из них 26 880 кэш), **$0.015253**
(`model_calls` сходится с `conversation_turns` до токена). Голос: **471 символ · 118 кредитов · $0.0236**. Итого
**$0.038853** (кап разговора $0.08 не задет; лимит наряда $1).

Наблюдения о роли (вне наряда, в «Спорное» п. 5 и ROADMAP): администратор спорит со своим значением ученика (ход 5,
сверяет с приготовленным визитом), даёт врачебный совет (ход 7), врач спрашивает о лекарстве, о котором ученик только
что сказал (ход 16). Подсказка к цели-вопросу («Скажи, что мне сказать вам его температуру?») в рамке «Скажи, что …»
читается криво — клиенту (раздел «Для CLIENT-FIX-4»).

## 5. Двойные токены ходов 7 и 17 репетиции 2DX8QC — причина

Не ретраи вендора, а **перезапросы стражей сервера**: у каждого хода — две строки `model_calls` и одна строка
`conversation_turns` с суммой.

| ход | `conversation_turns` | вызовы `model_calls` | страж |
|---|---|---|---|
| 7 | 7 732 → 240, $0.003078 | `01M36X5SFN…` 3 845 → 110 + `01M36X5VT4…` 3 887 → 130 | `conversation.learner_line` (роль сказала реплику ученика) |
| 17 | 7 993 → 198, $0.002393 | `01M36X8967…` 3 971 → 96 + `01M36X8B41…` 4 022 → 102 | `conversation.early_end` (роль закрыла разговор с ходами в запасе) |

Счётчики `plan_check_counters` под `conversation_agent.v3`: `learner_line` 1, `early_end` 1. Сумма в `conversation_turns`
— честная цена хода (решение владельца), оставлена; теперь каждая попытка — своя строка `conversation_rejections` с номером,
причиной и id вызова (на живой репетиции — §4), админка показывает их по ходам.

## 6. «p.m..» и «звук ≠ текст»

- **Пересборка текстов** на бою: сухой прогон — **6 карточек, 7 строк**, все — план `WSW1H6`, день 1, «I can come at 3
  p.m..»: `listen_dialogue` lines.10, `listen_review` lines.10, `speak_answer` own_line, `listen_predict` own_line,
  `dialogue_ask` modes.voice_hint + own_line, `word_intro` used_in. Наряд ждал 5 — это пять карточек, где строку видит
  ученик в тренажёре; шестая — пример употребления слова (`word_intro.used_in`), вторая строка `dialogue_ask` — голосовая
  подсказка. `--apply`: исправлено 6 / 7; повтор — 0; `p.m..` в `day_cards` — 0. Только пересборка из каркаса и
  наполнения, без покупок.
- **Приёмка В** (`tools/sound-text.php`, код боя после выката): 9 планов, находок «звук ≠ текст» — **0**. Три активных
  (`H35564`, `WSW1H6`, `2DX8QC`) проверены по ответу клиенту; шесть удалённых клиенту не отдаются — проверка честно
  помечает их «не проверено по ответу клиенту».

## 7. Что удалено

- `Domain/Service/PhraseUse` (судья по ключевым словам) и `tests/Unit/Plan/PhraseUseTest.php`; мера эха стража —
  в `LineShare` (без изменений).
- Кредит фраз по ответу модели (`phrases_used` роли, «вторая опора») и чтение `checkpoint_done` модели.
- `Conversation::moveInHand`, `creditMove`, `hintNative`; `ConversationPhrase::hintNative` (каркас с многоточием).
- `conversation_agent.v3.md`.
- `PlanInspectionData::sceneReadyAt` (конец сборки — `built_at`); в `TalkReport` — «частично» по ключевым словам.
- `CardViews`: сцена карточки как единственная сцена всех заглушек payload.

## 8. Ворота и проверки (один раз, в конце наряда, сайдкар ветки)

| что | итог |
|---|---|
| `composer check` | lint:openapi — оба файла ok; deptrac — **0 нарушений** (7 903 allowed, 3 uncovered); PHPStan — **No errors**; Pest — **2 498 passed, 23 947 assertions**, 95,9 с (параллельно, 10 процессов) |
| `flutter analyze` | No issues found |
| wt_admin | vue-tsc, eslint — чисто; vitest — 133 passed (раздел «Разговоры» — новый тест) |
| invariant-reviewer | **CLEAN** (Domain без фреймворка, Plan → Generation только через Application, журналы append-only) |
| мутации | **24 из 24 пойманы** (`mutations.md`; изолированная копия, свой контейнер и база; мутация 2.6 «артикли» сперва выжила — добавлен канон-тест `1bb3d0eb`) |
| EXPLAIN | `live/explain.txt`: журнал отбраковок, ходы с новыми колонками, окна сборок с `built_at` — таблицы малы, seq scan; индекс `conversation_rejections_turn_idx` на рост |
| Horizon | перезапущен после влития |

## 9. Коммиты (`main`)

| хеш | что |
|---|---|
| `b1687207` | §1 «Вспомнить» по сцене каждой страницы |
| `593aa2a1` | §6 одна точка после сокращения, пересборка текстов, `built_at` |
| `08974f1b` | §6 ответ модели называет свою строку `model_calls` |
| `6a988d6a` | §2 судья каркасов — связная фраза |
| `058a9b5e` | §§2–6 разговор: судья по сцене, граница сцен, T-id, подсказки, журнал отбраковок, ADM-1 |
| `94e8f2f7` | админка «Разговоры» |
| `1bb3d0eb` | канон-тест артиклей (из мутаций) |
| `b8497ca5` | документы, OpenAPI, DECISIONS, приёмки, мутации |
| (этот) | отчёт, выкат, handoff |

## 10. Выкат (24.09)

1. `DB=wordtrainer scripts/db-backup.sh --safety` → `wordtrainer-20260924-105133.sql.gz` (21 МБ).
2. `migrate` боя кодом ветки (`--pretend`, затем `--force`): `add_built_at_to_plan_scenes` (14,9 мс; `built_at` у 16 из
   19 сцен по `day_ready`), `add_scenes_and_rejections_to_conversations` (16,8 мс). Только разрешённые колонки и таблица.
3. `git merge --ff-only fix-4` (rebase не нужен — `main` не уходил) → `docker compose restart horizon`.
4. `plan:rebuild-card-texts` → `--apply` (6 / 7) → повтор 0.
5. Приёмка В — 0.
6. Админка: `docker compose build admin && up -d admin` (5175 → 200); вид раздела «Разговоры» проверен на моках
   dev-сервера (консоль чистая).
7. e2e: мигрирована кодом ветки до прогона; `wt_app_e2e` исполняет `main` — теперь FIX-4.
8. `wordtrainer_test` догнан; worktree, ветка `fix-4`, сайдкар `wt_fix4`, базы `wordtrainer_fix4_test` (+10
   параллельных) снесены; `scripts/stamp-build.sh`.

Клиент (сборка 20) работает как работал: все поля — добавлены. Телефонная часть — наряд CLIENT-FIX-4 (раздел
`docs/plan-api.md` «Для CLIENT-FIX-4: что показывать»).
