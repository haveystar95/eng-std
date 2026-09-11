# Наряд DAY-UI — отчёт (2026-09-11)

Ветка `day-ui` (от `b816951b`), коммит `2f132e1b` (ворота хука пройдены), мобильный клиент: кабинет дня (кадры 23-0a…23-0e) и сессия дня по
кадрам 23-1…23-15 канвы «План», правки «Базы» 16a/12a/12b/12i, звук и хаптика 4к-3/4е, строки
`day.*`. Параллельно шёл наряд PLAN-UI (таб, вход, домашняя карточка) — стык см. §2.9.

Источники: `docs/plan-api.md` (контракт PLAN-GEN), канвы `plan.dc.html` / `tokens.dc.html` /
`base.dc.html` (не в гите, экспорт владельца), карта экранов `docs/design/design-map.md` (переписана).

## 1. Разведка — что старого найдено и удалено

| где | что было | что сделано |
|---|---|---|
| `lib/features/plan/` | `plan_day_screen.dart` (вводка, полки, программа дня), `plan_day_stages.dart`, `plan_day_summary.dart` (итог дня, лестница A/B/C), `plan_dialogue.dart` (такты, ступени B/B+, спасатели `PlanRescueSheet`, финал `PlanDialogueDone`, `PlanDialogueSayAloud`), `plan_rehearsal_screen.dart` + `plan_rehearsal_done.dart` (прогон), `plan_feedback_screen.dart` («Как прошло?»), `plan_fail_reason.dart`, `plan_screen.dart` (список дней старой серии) | удалены |
| `lib/data/` | `plan_sitting_store.dart` (присесты), `sitting_queue.dart`; в `models.dart` — ситуативные режимы `ExerciseMode.situational{Hear,Say,Ask}`, `isSituational`, `gradesByOptionId`, `speaksAfterChoice`, `StudySession.plan`, `PlanSituation`, `PlanSessionEnvelope`, `PlanDayStateWire`, `PlanSittingKind`, `SceneRunKnobs`, `PlanDialogue`, `PlanDialogueTurn`; в `plan_models.dart` — `PlanTermRow … PlanRehearsal` (полки, разогрев, `PlanHearOptions` и всё, что строило варианты на клиенте); в `providers.dart` — `planDayProvider`, `planSittingStoreProvider`, `planSittingProvider`, `planSessionProvider`; в `api_client.dart` — `planDay`, `generatePlanDay`, `rebuildPlanDay`, `buildPlanSession`, `planRehearsal`, `recordSceneRun` | удалены; `LearningPlan`/`PlanSummary`/`PlanDayState` и `activePlanProvider` оставлены для входа и домашней карточки (PLAN-UI) |
| `lib/features/training/` | в `session_exercise.dart` — ветки прогона сцены и ситуативных карточек (`situation`, `sayIntent`, `inDialogue`, `sceneRun`, `roleSpeaking`, `_RevealedLine`, `_RecordCircle`, `_SessionOption`, `_DrawnUnderline`, `_WordChip`, `_AssemblyLine`, `_ClozeSentence`); в `session_screen.dart` — плановая ветка целиком (присесты, швы секций, диалоговая оболочка, голосовое ожидание, плановые итоги); `language_mode_support.dart`, `practice_mode_selector.dart`, `session_grading.dart` — ситуативные режимы | удалены; тренажёры коллекций переведены на общие виджеты (§2.6) |
| `tool/` | `plan_dialogue_preview.dart`, `plan_done_preview.dart`, `plan_summary_preview.dart`, `plan_list_preview.dart`, `voice_preview.dart`; вкладка «Ход в диалоге» в `speech_preview.dart` | удалены |
| тесты (24 файла) | `plan_sitting_store_test`, `plan_contract_v02_test`, `plan_day_stages_test`, `plan_dialogue_{memory,presentation}_test`, `plan_dialogue_test`, `plan_done_test`, `plan_event_test`, `plan_fail_reason_test`, `plan_screens_test`, `plan_session_{closes,seam}_test`, `plan_sitting_repairs_test`, `plan_voice_never_forever_test`, `say_aloud_mic_test`, `assemble_turn_test`, `scene_run_card_test`, `sitting_queue_test`, `situational_card_test` | удалены (тесты старого кода, не канона) |
| ARB | 270 ключей `plan*` старых серий (диалог, прогон, полки, лестница, спасатели, присесты, «Как прошло?», сборка дня v1…) — в `app_ru.arb` и `app_en.arb`; строки словаря `docs/plan-ui-glossary.md` для них | удалены; `planRescueHint` оставлен (гард `no_internal_words_in_plan_test`), словарь очищен от 298 строк |
| ассеты | `assets/sounds/verdict_*.wav` перегенерированы, добавлены `stage_closed.wav`, `day_closed.wav` (`tool/gen_feedback_sounds.dart`) | — |

Grep по `lib`, `test`, `tool`: `PlanDayScreen`, `PlanScreen`, `PlanDialogue`, `PlanRehearsal`,
`PlanHearOptions`, `SittingQueue`, `SceneRunKnobs`, `situational`, `plan_day_screen`,
`plan_dialogue` — пусто (кроме `speech_preview.dart` — имя файла в докблоке истории).

## 2. Что сделано по §2–8, отклонения

### 2.1 Контракт и машина состояний (`lib/data/plan/`)

`plan_contract.dart` — зеркало `docs/plan-api.md` (Plan, PlanDayRoute, PlanScene, DayRoom,
DayStageProgress, DayUnit, DayMetrics, DayCard с типизированными геттерами пейлоада, DayCards,
DayAnswerOutcome, DaySheet/DayTerm; enum'ы видов карточек, этапов, результатов, источников).
`api_client.dart` — `currentPlan`, `planById`, `dayRoom`, `openDay`, `dayCards`, `answerDayCard`,
`closeStage`, `closeDay`, `daySheet`, `retryLesson`, `planAudioUrl`, `problemCode/problemMeta`.
`day_session.dart` — `DaySession` (ChangeNotifier): порядок и состав от сервера, текущая карточка,
повтор в конце этапа по `requeued`, «вернётся», закрытие этапа/дня, 409 `plan_card_answered`
терпится, сеть упала — вердикт закрывается локально. `day_rules.dart` — чистые правила §5.

### 2.2 Кабинет дня (§2) — `day/day_room_screen.dart`, `day/term_sheet.dart`

Одно окно, три состояния, плита 4н шапкой (фото под blur 24 / saturate .8 / скрим .88→.95,
скругление только снизу 28), «научишься» с маркерами (все разом при закрытии), пять строк этапов с
полосками 6 px в трёх цветах и колонкой 64, подвал по состоянию («Начать» / три числа 26 +
«Продолжить · осталось N» / галка + «День N закрыт» + три числа Literata 56 с «%» 28). На бумаге —
«Слова · 8» + «+ N из дня D» латунью, сетка 2×4 с фото 110, маркером в углу на подложке .9 и
пилюлей «из дня N»; фразы строками 60; обмены парами 72; состояние только маркером 4л. В закрытом
дне — «Далось труднее всего» и «В работе» с «Вернутся в день N · K карточек». Тап по слову/фразе —
шит 23-14/23-15 (bottom sheet без кнопок, строка состояния цветом). Крестик сессии и закрытие
последнего этапа возвращают в кабинет; закрытие проигрывает анимацию 23-0b → 23-0c (числа 420 мс,
галка 220 с задержкой 120, маркеры разом 220 с задержкой 260, звук «день закрыт», haptic success
один раз, конфетти нет).

**Отклонения (все — от контракта сервера, не от кадра по выбору):**

| что на кадре | что на экране | почему |
|---|---|---|
| 23-0a: «75 карточек · ≈ 20 минут», «0 / 32» у этапов, программа с обменами | до открытия дня: мета-строка без чисел, пять строк этапов без счётчиков, программа — слова и фразы из шита, обменов нет | сервер раздаёт карточки на `POST …/open`; до него `stages` = `absent`, `program` пуст (`GetDayRoomHandler`), обменов в шите нет |
| 23-0b: «91 %» с первого раза | процент считает клиент по карточкам (`DayRules.firstTryPercent`) | `metrics.first_try_share` сервер отдаёт только в закрытии |
| «≈ 20 минут» | оценка клиента 13 с/карточка | минут на день у сервера нет |
| «3 попытки» у «Далось труднее всего» | сумма `attempts` карточек единицы, которую назвал сервер | у метрик нет числа попыток |
| 23-0e: другое фото | тот же экран, фото сцены дня | отдельного кода нет — состояние = данные |

### 2.3 Каркас сессии (§3) — `day/day_session_screen.dart`, `day/day_card_frame.dart`

Шапка: крестик, «Слова · 12 из 32», пять сегментов 3 px = пять этапов, текущий заливается по
карточкам за 160 мс; тап по центру ничего не открывает. Вход в этап 23-2a (лейбл, Literata 30,
факты, шаги, «N новых · M вернулись из дня D», список единиц с мини-фото 32 / фразами / парами и
галками у пройденного), 23-2b («продолжаем · осталось N из M · ≈ минуты», «Продолжить», ничего не
переигрывается). Итог этапа 23-9 (галка 30, «Слова закрыты», «32 карточки · 7 минут», полоска в
трёх цветах, «8 слов в работе · 2 с подсказкой · 1 вернётся», «Дальше: Фразы · 18 карточек · ≈ 5
минут» + список; звук «этап закрыт», haptic). Выход 23-11 — алерт 12k с точной позицией; после
выхода — кабинет. Вернувшаяся карточка — латунная пилюля «Вернулось из дня N». Переходы карточек
±24 / 180–220 мс, под reduce-motion — только непрозрачность. 409 `plan_day_locked` — «Сначала
закончи день N» / «Откроется {date}»; `plan_lesson_not_ready` — «Собираем день · около минуты» с
опросом раз в 4 с, `failed` — «День не собрался» + «Повторить» (`…/lesson/retry`).

### 2.4 Карточки (§4) — `day/cards/*.dart`, по одному виджету на вид

`WordIntroCard` (23-1/23-13, 16a — `IntroLayout` общий с коллекциями), `WordSayCard` (23-3a–d),
`WordChooseCard` (12a; «Выбери перевод» / «Выбери слово» по определению — по `mode`),
`WordClozeCard` (12i, `ClozeSentence`), `PhraseIntroCard` (23-4, ключ подчёркнут 2 px ink,
«В разговоре»), `PhraseRepeatCard` (23-5, шалфейная подложка на ключе), `PhraseAssembleCard`
(12b), `DialogueReadCard` (23-6a/b, лейблы «ТЫ»/«АГЕНТ…» один раз на сторону, переводы открыты
/ свёрнуты по `translations_collapsed`, раскрытие 200 мс), `ListenQuestionCard` (23-7a–c: карточка
«…ГОВОРИТ» с волной 24×2 без текста, звучит сама, «ЧТО ОН СПРОСИЛ», варианты на языке уровня;
верно — раскрытие текста и перевода; неверно — объяснение, «Вернётся в конце этапа»),
`AnswerChooseCard` (23-7d), `AnswerAssembleCard` (23-7e, после сборки «…ОТВЕЧАЕТ»),
`ListenAssembleCard` (23-7f–h; вид отдаёт сервер, клиент рендерит), `SpeakCard` (23-8a–e: лента
прошедших обменов — собеседник слева, ты справа тёмной плитой с распознанным; блок «СКАЖИ
ПО-АНГЛИЙСКИ» + перевод в кавычках; «Подсказка»: ключ Literata 19 → весь текст (охра, «вернётся в
день N»); «Пропустить» после двух попыток; после записи следующая реплика звучит сама, «Дальше»
нет). Задание ученика везде — перевод его реплики; чтение — только на знакомстве, «произнеси» и в
шите. `DayCardContext` — общий контекст карточек (сессия, голос, уровень, роль собеседника, день
возврата, фабрика микрофона).

Отклонение: «Понятно» на знакомствах и диалоге шлёт `passed` с `attempts: 1` — контракт требует
`result` на каждую карточку, и знакомство оценивать нечем.

### 2.5 Зачёт (§5) — `DayRules`, `SpeechAttemptController`

Выбор/сборка: верно с первого раза → `passed`; ошибка → `failed`, сервер возвращает `requeued` в
конец этапа; вторая ошибка (у `retry_of`) → «вернётся в день N», этап идёт. Микрофон: зачёт по
`speaking_key` (сплошным куском) или ≥ `coverage` (по умолчанию 0,7) слов текста/любого варианта —
тем же токенайзером, что `Words::tokens` сервера; после двух неудач «Пропустить» = `skipped`;
произношение никогда не «неверно»; подсказка-ключ → `passed`, подсказка-текст → `hinted`.
Движок — существующий форк с `contextualStrings` (слова дня + варианты). Аудио по `audio_id`
через `LineAudioCache` (`DayVoice.prepare` докачивает на входе в день); без файла — читает
телефон, под волной тихая строка. Метрики — сервер; клиент шлёт `answer` на каждую карточку.

Дев-дверь QA (симулятор без микрофона): ряд `QA · said / part / miss` под микрофоном, только у
QA-аккаунта (`qaTools` с сервера).

### 2.6 «База» у коллекций (§6)

16a — `IntroLayout` общий (фото 220, слово 46, чтение в слэшах, пример курсивом с подчёркиванием
2 px, бейдж над словом, эхо «повтори вслух» под примером); 12a — `AnswerOption` (маркер + тонировка,
без контура) + `TaskBlock`; 12b — `WordTile` 44 / `AssemblyBoard` серая подложка с тонировкой
вердикта / `TileTray`; 12i — `TaskBlock` + `ClozeSentence`. Поведение тренажёров не тронуто: «Не
помню», подсказка «Собери из слов ниже», чужое слово волной. Тесты коллекций правлены только там,
где кадр изменил вид (капитель лейбла, подчёркивание вместо жирного, бейдж над словом, подложка
вместо линии, `ensureVisible` — плитки 44 сделали карточку выше окна теста).

### 2.7 Звук и хаптика (§7) — `theme/feedback.dart`

Четыре звука ≤ 300 мс (`verdict_correct`, `verdict_wrong` — мягкое «нет», `stage_closed`,
`day_closed`; E-мажорная пентатоника, генератор `tool/gen_feedback_sounds.dart`), через
`AudioServicesPlaySystemSound` (тихий режим телефона уважается), тумблер «Звуки» в профиле
(`AppSettings.soundsEnabled` → `AppFeedback.soundsEnabled`), хаптика остаётся. Звук вердикта на
карточках со звучащей репликой ждёт её конца (`_DeferredVerdict`, сторож 15 с). Микро-анимации по
подписям: вердикт 220, shake 180, «думаем» 300, авто-уход «произнеси» 600, раскрытие перевода 200,
сегмент шапки 160; reduce-motion — только цвет/непрозрачность.

### 2.8 Строки (§8)

114 ключей `day*` + `profileRowSounds`/`profileSoundsHint` в `app_ru.arb` и `app_en.arb` с
plural-формами и плейсхолдерами; задания с сервера не дублируются. В коде ни одной строки.
`DayTexts` — выбор ключа по этапу/уровню/виду единицы. Кегли ≥ 15 / 14 / 11 по ролям `AppTextDay`.

### 2.9 Стык с PLAN-UI (по указанию владельца в ходе наряда)

Штатный вход в кабинет — ссылка `engstd://plan/day/{dayId}` (`lib/data/deep_links.dart`,
`SceneDelegate.swift` → `AppDelegate.handleLink`, канал `com.denis.engstd/links`, схема в `Info.plist`); принимаются также
`engstd://plan/{planId}/day/{n}` и `engstd://plan/current`. Уведомления плана ходят той же дверью.
Таб «План» — заглушка `_DayEntryStub` (день и одна кнопка → `openDayRoom`), домашняя карточка и
экран сборки → `openDayRoom`; ничего сверх этого в табе/входе не правилось. При слиянии PLAN-UI
заменяет заглушку своими плитами; конфликты ожидаются в `plan_tab_screen.dart`, `home_plan_card.dart`,
`plan_building_screen.dart`, `plan_notification_host.dart`, `app_*.arb`, `docs/plan-ui-glossary.md`.

## 3. Кадр → снимок → расхождения

Снимки — golden-тесты `mobile/test/goldens/day_ui_golden_test.dart` (41 состояние): фикстура
ответа сервера (`test/goldens/fixtures/`, снято с живого backend2: план «врач» Beginner
`01M26KPM34Z9C9B31GBRR4YZGA`, план аренды Intermediate `01M26KTYVVTXRN9T4AE4807BXV`, 75 карточек
дня 1) → экран → `test/goldens/<кадр>.png`. Фото в тестах не грузятся (сеть закрыта) — слоты серые.

| кадр | снимок | расхождения |
|---|---|---|
| 23-0a | `23-0a-room-open.png` | до открытия дня без счётчиков и обменов (§2.2) |
| 23-0b, 23-0d | `23-0b-room-in-progress.png` (страница целиком, стык секций виден) | процент клиента (§2.2) |
| 23-0c | `23-0c-room-closed.png` | попытки суммой (§2.2); анимация закрытия в golden не снимается |
| 23-0e | — | тот же экран с другим фото сцены; отдельного состояния нет |
| 23-1 | `23-1-word-intro.png` | фото в тесте не грузится |
| 23-13 | `23-13-word-intro-returned.png` | — |
| 23-2a / 23-2b | `23-2a-stage-entry.png` / `23-2b-stage-resume.png` | — |
| 23-3a–d | `23-3a-say-idle` · `23-3b-say-listening` · `23-3c-say-heard` · `23-3d-say-retry-skip` | — |
| 12a | `12a-choose` · `12a-choose-correct` · `12a-choose-wrong` | Intermediate — по определению (кадр рисует Beginner) |
| 12i | `12i-cloze` · `12i-cloze-correct` | — |
| 23-4 / 23-5 | `23-4-phrase-intro` · `23-5-repeat-idle` · `23-5-repeat-heard` | у фиксутры фразы нет `speaking_key` — подчёркивания ключа нет, подложка «услышали» на всей фразе |
| 12b | `12b-assemble` · `12b-assemble-correct` · `12b-assemble-wrong` | — |
| 23-6a / 23-6b | `23-6a-dialogue-open` · `23-6b-dialogue-collapsed` | — |
| 23-7a–c | `23-7a-listen-question` · `23-7b-listen-correct` · `23-7c-listen-wrong` | «без озвучки — читает телефон» в тесте (файла нет) |
| 23-7d | `23-7d-answer-choose.png` | — |
| 23-7e | `23-7e-answer-assemble` · `23-7e-answer-assemble-replied` | — |
| 23-7f–h | `23-7f-listen-assemble` · `23-7g-…-correct` · `23-7h-…-wrong` | — |
| 23-8a–e | `23-8a-speak-idle` · `23-8b-speak-hint-key` · `23-8c-speak-hint-text` · `23-8d-speak-heard` · `23-8e-speak-feed` | обмены фикстуры начинает ученик — карточки «…ГОВОРИТ» перед заданием нет (она есть у обменов, где начинает собеседник, см. 23-7a) |
| 23-9 | `23-9-stage-done.png` | — |
| 23-11 | `23-11-exit-alert.png` | — |
| 23-14 / 23-15 | `23-14-sheet-word` · `23-15-sheet-phrase` | — |

## 4. Ворота §9

1. `flutter analyze` — **0 issues**; `flutter test` — **1270 passed, 0 failed**; канон-тесты:
   `test/data/plan/day_rules_test.dart` (25), `test/data/plan/day_session_test.dart` (9),
   `test/goldens/day_ui_golden_test.dart` (41 состояний «сервер → экран»).
2. Симулятор — **см. §6 (не сделано полностью)**.
3. Таблица — §3.
4. Тренажёр коллекций после «Базы» — тесты `intro_card_test`, `session_copy_test`,
   `assembly_line_test`, `word_bank_give_up_test`, `own_answer_verdict_test`,
   `description_match_card_test` зелёные с правками под кадры; поведение сохранено.
5. Звук — файлы в бандле, allowlist в `AppDelegate`, тумблер в профиле; **на слух не проверено** (§6).
6. Версия сборки — на профиле («клиент b816951b+ · 2026-09-11 01:28 · сервер b816951b» видно на
   симуляторе, `docs/shots/day-ui/profile-bottom.png`). Grep старых имён — пусто (§1).
7. Самопроверка диффа — §5.

## 5. Самопроверка диффа — что исправлено до сдачи

- `AnimatedSwitcher` каркаса пересоздавал карточку на каждое уведомление сессии (ключ рос на любой
  `notifyListeners`) — вердикт терялся сразу после ответа. Найдено golden-тестом 12a: ключ теперь
  меняется только с id карточки.
- `WordTile` растягивался на всю ширину в `Wrap` (`Container.alignment`) — плитки стояли столбиком.
- `SpeechAttemptController` уведомлял после `dispose` (ход микрофона переживал карточку) — флаг
  `_disposed`.
- `_lineDone.future.timeout(15 с)` в карточках «слушаю» держал таймер после ухода карточки —
  заменён на отменяемый `_DeferredVerdict`.
- `AnimatedSize` с `Duration.zero` под reduce-motion падал в layout — 1 мс.
- Обмены в программе кабинета брали «свою» реплику из карточки «что он спросил» (ответ-вариант)
  — теперь из `speak` / `answer_*` / диалога.
- `git checkout` ARB во время чистки ключей снёс свежие `day*` — восстановлены из журнала
  сессии, проверено `gen-l10n` + анализатор.
- В кабинете до открытия дня «0 карточек · ≈ 0 минут» и пять строк «0 / 0» — заменено на
  мета-строку без чисел и строки без счётчиков (§2.2).

## 6. Не сделано и почему

- **Живой сквозной прогон на симуляторе (ворота 2) — частично.** Собрана и поставлена debug-сборка с
  QA-аккаунтом `qa-dayui@wt.test` на свой симулятор «DayUI iPhone 17» (`F97AD291-…`, чтобы не
  делить устройство с PLAN-UI). Живьём пройдено: дев-вход → онбординг → таб (заглушка) → кабинет
  дня 1 по тапу и по ссылке `engstd://plan/day/…` с живого сервера (снимок
  `mobile/docs/shots/day-ui/live-01-room-via-link.png`: плита с фото сцены под материалом, пять
  этапов, «начни отсюда · 8 новых слов», программа из шита с фото). Первая ссылка спрашивает «Open
  in Eng Std?», дальше открывается молча; ссылки идут через `SceneDelegate` (приложение на
  UIScene-цикле, `application(_:open:)` не зовётся — найдено этим прогоном). Дальше
  автоматизация тапов встала: maestro делит порт XCTest-драйвера с параллельной сессией PLAN-UI и
  зависает даже на приватном порту (`--driver-host-port 7002`), MCP-панель симулятора ждёт
  подтверждения доступа владельца. По указанию владельца автоматизацию не чинил — состояния сняты
  golden-тестами. Остаток прогона (пять этапов живьём, сдвиг календаря `plan:shift-day`, день 2 с
  возвратами, Intermediate живьём, звук на слух, тихий режим) — **не выполнен**; всё это покрыто
  тестами машины состояний и golden'ами, но не ушами и не живым сервером.
- 23-0e отдельным снимком не снят (то же состояние с другим фото).
- `docs/plan-ui-glossary.md`: строки `day*` не добавлены — гард словаря только для `plan*`;
  таблица `day.*` живёт в канве и ARB.

## 7. Вопросы архитектору

1. До `POST …/open` сервер не отдаёт ни этапов, ни программы (обменов) — кадр 23-0a требует
   «75 карточек · ≈ 20 минут», «0 / 32» и программу с обменами до «Начать». Нужен ли в
   `GET …/days/{n}` состав дня без раздачи (или раздача на первом `GET`)?
2. `metrics` есть только у закрытого дня — «с первого раза» и минуты в идущем дне (23-0b) клиент
   считает сам. Оставить или отдать `metrics` и в `in_progress`?
3. Найдено на сервере при подготовке стенда: `POST /plans/{id}/start`, посланный пока
   `BuildLessonJob` ещё пишет план, затирается джобой (план возвращается в `ready`, дни
   `locked`) — потерянное обновление; повторный `start` после `lesson_status: ready` чинит.
4. У фраз в фикстуре `speaking_key: null` — подчёркивать ключ (23-4) нечего; так и задумано для
   коротких фраз?

## 8. Как повторить

- Тесты: `cd mobile && flutter analyze && flutter test`; перерисовать эталоны —
  `flutter test test/goldens --update-goldens`.
- Стенд: планы QA-аккаунтов созданы через API (`scratchpad/mkplan.sh <email> <goal> <level> <days>`
  — `POST /auth/dev`, `PUT /profile`, `POST /plans`, опрос `/build`, `POST …/start`, ожидание
  `lesson_status: ready`); кабинет — `xcrun simctl openurl <udid> "engstd://plan/day/<dayId>"`;
  день 2 — `docker compose exec app php artisan plan:shift-day <plan> --days=1`.

## 9. Слияние с PLAN-UI (доработка, 11.09.2026)

`git merge main` в `day-ui`: PLAN-UI переписал таб «План» (21-x), вход (22-x), модели плана и снёс
остатки старого контура. 23 конфликта; ниже — каждый и чем решён. Правило было одно: по смыслу, а
не «моя сторона».

| конфликт | решение |
|---|---|
| `lib/data/plan_models.dart` (снесён у них, правлен у меня) | удалён: модели плана переехали в `lib/data/plan/plan_models.dart` |
| `lib/data/plan/plan_contract.dart` × `lib/data/plan/plan_models.dart` — **два набора моделей одного контракта** | ОДИН набор: база — `plan_models.dart` PLAN-UI (план, маршрут, сцены, кабинет, сборка, версии, архив, открытые enum'ы с `unknown`); мой контракт ужат до дневной части и переименован в `day_contract.dart` (карточки, шит, вердикты) — он импортирует общие модели и не повторяет ни одного их поля. Что дню не хватало, добавлено в общий набор, а не продублировано: `PlanStage.wire/ordinal/known`, `PlanScene.learnerRoleNative/partnerRoleNative`, `PlanDayMetrics.hardestUnitKind/hardestUnitRef`, `PlanProgramUnit.sceneId`, `Plan.sceneById`; выборки дня (`words`/`phrases`/`exchanges`, `stageOf`, `cardsTotal`, `remaining`, `firstTryPercent`) — расширениями в `day_contract.dart` |
| `api_client.dart` | плановые методы — их (`currentPlan` с raw для офлайн-кэша, `plan`, `plans`, `createPlan`, `planBuild`, `startPlan`, `reschedulePlan`, `finishPlan`, `deletePlan`, `planDayRoom`, `retryPlanLesson`, `planVersions`); мои дубли (`currentPlan`, `planById`, `dayRoom`, `retryLesson`) удалены, остались только карточки дня (`openDay`, `dayCards`, `answerDayCard`, `closeStage`, `closeDay`, `daySheet`, `planAudioUrl`) и QA-часы. `ApiClient.problemCode/problemMeta` сняты в пользу их top-level `problemCode`; для `catch (e)` добавлены `problemCodeOf` / `problemMetaOf` |
| `lib/data/providers.dart` | их версия: старые `activePlanProvider`, `planArchiveProvider`, `planProvider`, `planNotificationsProvider` и `LearningPlan` удалены вместе с экранами |
| мой `lib/data/plan/plan_providers.dart` × их `features/plan/plan_providers.dart` | мой переименован в `day_providers.dart` и ужат до дня (`dayRoomProvider`, `daySheetProvider`, `dayCardsProvider`); `currentPlanProvider` удалён — план берётся из `planTabProvider` |
| `lib/ui/day_plate.dart` (add/add) | один файл, два размера компонента 4н: их `DayPlate` — карточка таба (кадры 21-2 … 21-4, под их 34 снимками), мой `DayRoomPlate` — шапка кабинета (23-0a … 23-0e). Почему два класса, а не `DayPlateSize.header`, как обещал их докблок, — написано в файле: в кадрах у них общий только материал, а строки этапов, обложка и подвал разные; обещание в докблоке исправлено. Общее вынесено: `StageBar` |
| `plan_tab_screen.dart` | их целиком; моя заглушка `_DayEntryStub` удалена. `_openDay` открывает кабинет через `openDayRoom` |
| `plan_day_stub_screen.dart` (их заглушка «до DAY-UI») | удалена вместе с тремя строками ARB — её место занял кабинет |
| `home_plan_card.dart`, `plan_building_screen.dart`, `plan_notification_host.dart`, `tool/speech_preview.dart` (снесены у них, правлены у мной) | удалены: домашнюю карточку, ожидание сборки и уведомления PLAN-UI переписал под 21-x/22-x |
| `session/sitting_queue.dart` + тест (снесён у меня, правлен у них) | восстановлены: очередь присеста — механика тренажёров коллекций (повтор ошибки в конце), а не плана; я снёс её вместе с плановой веткой по ошибке |
| `session_screen.dart` | их версия (в ней `SittingQueue` и чистка плана); поверх вернул своё: знакомство 16a рисуется во всю ширину без полей экрана |
| `session_exercise.dart`, `models.dart` | моя сторона: ветки прогона сцены и ситуативных карточек (`sayIntent`, `_isSceneRun`, `_isAssembleTurn`, `situational*`) удалены |
| `app_settings.dart`, `profile_screen.dart`, `language_mode_support.dart` | их сторона (оба наряда независимо сделали «Звуки»): ключ хранения `sounds_enabled`, их формулировки; от моей версии оставлен сеттер, который гасит звук в `AppFeedback` |
| `ui/ui.dart` | оба экспорта (`cloze_sentence`, `choice_card`) |
| ARB × 2 | база — их набор; сверху мои 114 `day*`. Снесённые старые ключи не воскрешены; расхождения по значению (`planEmptyTitle`, `planEntryNext`, `tabHome`, …) — их, копирайт таба и входа принадлежит PLAN-UI. Итог: 869 ключей, ru и en совпадают ключ в ключ |
| `design-map.md` | объединён: их шапка и разделы (навигация, таб 21-x, вход 22-x) + мои (общие виджеты, кабинет 23-0x, сессия 23-1…23-15, «База», звук, «удалено») |
| `docs/plan-ui-glossary.md` | их таблица `plan*` целиком + мой раздел про `day*` |
| канвы `plan/base/tokens.dc.html` | их закоммиченные версии (мои untracked копии побайтово те же — удалены) |

**Что удалено при слиянии:** `plan_contract.dart` (слит в `plan_models.dart` + `day_contract.dart`),
`plan_providers.dart` мой (→ `day_providers.dart`), `plan_day_stub_screen.dart` и его три строки
ARB, моя заглушка таба `_DayEntryStub`, `home_plan_card.dart`, `plan_building_screen.dart`,
`plan_notification_host.dart`, `tool/speech_preview.dart`, дубли «Звуков» и `problemCode/Meta`.

**Найдено слиянием и починено:** шапка сессии рисовала ШЕСТЬ сегментов вместо пяти — у общего
`PlanStage` есть `unknown`, а я шёл по `.values`; поймали golden'ы (`PlanStage.known`).

**Ворота после слияния:** `flutter analyze` — 0; `flutter test` — **1327 passed, 0 failed** (41
golden дня + 32 golden'а PLAN-UI, оба набора зелёные без перерисовки эталонов).

**Уведомления плана** после слияния — `plan_ready_notification_host.dart` PLAN-UI («План готов»,
кадр 22-6); ссылки `engstd://…` слушает он же — мой `plan_notification_host.dart` удалён, слушатель
переехал туда одной функцией. Лист «Как устроен план», выключатель «Напоминания»
и `clientBuildProvider` — PLAN-UI, не тронуты.

