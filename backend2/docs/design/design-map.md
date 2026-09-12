# Карта экранов ↔ кадров — плановый контур

**Постоянный файл. Наряд, который правит экран планового контура, обязан обновить его строку здесь.**

Переписан нарядом PLAN-UI (2026-09-11) под новый план (PLAN-GEN, `docs/plan-v2.md`, `docs/plan-api.md`).
Строки старых серий — Вход v4, День v1, Диалог v1, Главная v2 — удалены вместе с их экранами: в
клиенте не осталось ни одного экрана, класса, строки или ассета старого плана (список — в отчёте
наряда PLAN-UI).

Источник кадров: экспорт канвасов Claude Design в этой папке — **`plan-canvas.dc.html`**
(принятая 13.09 канва плана: таб 21-x, вход 22-x — она и есть эталон для этих серий),
`plan.dc.html` (прежняя канва «План»; для 21-x/22-x УСТАРЕЛА, день 23-x пока по ней), `tokens.dc.html` (токен-лист, разделы 4к–4о),
`base.dc.html` («База», только действующие кадры по оглавлению). Кадр — блок с `id` («21-2»,
«22-4b»); значения читаются из стилей блока; подписи под кадрами — спеки решений и анимаций;
таблица текстов — в конце канваса «План». Приоритет при расхождении: токен-лист (в т. ч. 4о)
старше подписи под кадром; подпись старше картинки.

Статусы: **совпадает** — экран сделан по кадру; **расходится** — есть кадр, экран другой, причина
названа; **не перенесён** — кадра нет в коде вовсе; **нет кадра** — экран есть, кадра для него не
рисовали.

**Чем сверяется совпадение.** Состояния планового контура сняты golden-тестами из фикстур ответа
сервера: снимки — `mobile/test/goldens/plan/`, тесты — `mobile/test/features/plan/plan_*_golden_test.dart`,
фикстуры (живые ответы backend2) — `mobile/test/fixtures/plan/`. Строка «совпадает» без снимка —
это слово, а не проверка; правя экран, обновляй снимок (`flutter test --update-goldens
test/features/plan/`) и смотри на него. Живые снимки симулятора — `docs/research/plan-ui/shots/`.

## Навигация (токен-лист 4к-1)

| экран | код | кадры | статус |
|---|---|---|---|
| Таб-бар: Сегодня · План · Коллекции | [home/home_screen.dart](../../../mobile/lib/features/home/home_screen.dart), [ui/floating_tab_bar.dart](../../../mobile/lib/ui/floating_tab_bar.dart) | 21-x (пилюля), 4к-1 | совпадает: три таба, «План» центральный, латунного кольца у него больше нет |
| Профиль — кружок-аватар 30 в шапке | [profile/profile_avatar.dart](../../../mobile/lib/features/profile/profile_avatar.dart), [profile/profile_screen.dart](../../../mobile/lib/features/profile/profile_screen.dart) | 21-x (шапка), 11a | совпадает; профиль толкается поверх таба; внизу профиля — строка версии (клиент · сервер) всегда; выключатель «Звуки» (4к-3) |
| Прогресс — плита статистики на «Сегодня» | [training/training_home_screen.dart](../../../mobile/lib/features/training/training_home_screen.dart) → [progress/progress_screen.dart](../../../mobile/lib/features/progress/progress_screen.dart) | 19-1, 8a | совпадает: таба нет, тап по плите толкает экран прогресса |

## Таб «План» — кадры 21-x

| экран / состояние | код | кадры | статус |
|---|---|---|---|
| Плана нет — витрина | [plan/plan_tab_screen.dart](../../../mobile/lib/features/plan/plan_tab_screen.dart) `_EmptyState` + `_ExampleCard` + `PlanFinishedList`, правила — [plan_rules.dart](../../../mobile/lib/features/plan/plan_rules.dart) | 21-1 | совпадает: заголовок-обещание, подпись в одну строку, три правила (те же, что в 21-8), карточка-пример из трёх узлов без дат. Снято: вопрос «К чему готовишься?», «С чем приходят», фрагмент дня (снимки `21-1-empty`, `21-1-empty-scrolled`, `21-1-load-failed`) |
| План идёт, день не начат / идёт | `PlanHeader` + [plan_day_plate_view.dart](../../../mobile/lib/features/plan/plan_day_plate_view.dart) → [ui/day_plate.dart](../../../mobile/lib/ui/day_plate.dart); значки этапов — [ui/plan_marks.dart](../../../mobile/lib/ui/plan_marks.dart) | 21-2, 21-2b, 21-3 | совпадает: бровь «План · день N из M», короткое название Literata 30, полоса дня 4 px, слова «План» в заголовке нет; значки этапов из `assets/stages/` вместо кружков, название дня на две строки. **Расходится по контракту**: «≈ N минут» у дня и «N с подсказкой» у этапа не отдаются (снимки `21-2`, `21-2b-route`, `21-3`) |
| Подсказки первого раза | [plan/plan_tab_parts.dart](../../../mobile/lib/features/plan/plan_tab_parts.dart) `PlanHintLine`, флаги — [data/plan/plan_store.dart](../../../mobile/lib/data/plan/plan_store.dart) | 21-2c, 21-4c | совпадает: три на табе, одна при первом закрытии, без крестика, до первого действия |
| День закрыт | `PlanDayPlateView` (closed) | 21-4 | совпадает: светлая бумага radius 26, галка в круге 34, этапы шалфеем с полным счётом, подвал двумя строками («День N откроется завтра, дата» и «K карточек вернутся в день N →»), кнопки нет. После PLAN-API-FIX-1 «завтра» помечен РОВНО ОДИН день (снимки `21-4`, `21-4-returning`, `21-4c-close-hint`) |
| Маршрут целиком, масштаб 10 дней | [plan_route.dart](../../../mobile/lib/features/plan/plan_route.dart) | 21-2b, 21-5, 21-6 | совпадает: узел — круглая картинка 56 БЕЗ бейджа, номер дня первым словом меты, три строки в одном порядке, шаг 96, линия 1.5 через центры; латунь только у «сегодня»; причину открытия несёт только первый запертый день; событие без даты — пунктир и «указать дату». Фолбэк 21-6 (нет `title_native`) — цель 21/600 в три строки без троеточия (снимки `21-5-route-after-close`, `21-6`, `21-6-scrolled`, `strip-route-node`) |
| План пройден | `PlanDoneCard` | 21-7 | **расходится по контракту**: «{p} фраз и {w} слов в работе» не отдаётся — строка «7 дней» без второй половины (снимки `21-7`, `21-7-reading`) |
| Лист «Как устроен план» | [plan_sheets.dart](../../../mobile/lib/features/plan/plan_sheets.dart) `showPlanHowSheet` → [plan_rules.dart](../../../mobile/lib/features/plan/plan_rules.dart) | 21-8 | совпадает: три правила ТЕМ ЖЕ текстом и значками, что на витрине, под правилом про этапы — ряд пяти значков `assets/stages/`; один раз после первого плана |
| Меню плана | `PlanHeader` («…») → `showFloatingContextMenu` (4в) | 21-9 | совпадает: четыре действия ТЕКСТОМ, без значков; терракота последней. **Канва внутренне противоречива**: «Вычтено» под 21-9 описывает формулировку цели сверху меню, а сам кадр её не рисует — сделано по кадру (и по контрольному списку наряда) |
| Лист даты | `showPlanDateSheet` | 21-10 | **расходится по контракту**: блока «Маршрут · было 7 → станет 5» нет (нет пробного `PATCH /schedule`); заголовок «Когда приём?» собран из `event_native` (приводится к нижнему регистру — сервер отдаёт слово с заглавной); добавлен вариант «Без даты» (снимок `21-10-sheet-date`) |
| Лист нового плана | `showPlanNewSheet` | 21-11 | совпадает; имя коллекции — `title_native` плана |
| Алерт удаления | `showPlanDeleteAlert` (5f) | 21-12 | совпадает |
| Маршрут пересобран | `PlanNoticeBanner` над плитой | 21-13 | **частично**: плашка есть и остаётся на экране (состояние, не сообщение), текст — «было N дней, стало M» из `days_shortened_from`. Сколько дней пропущено и какие дни слиты, контракт не отдаёт (снимок `21-13`) |
| Событие прошло | `PlanOverdueCard` | 21-14 | совпадает по составу: «Завершить план» + «Дозаниматься · N дней» (второе действие — возврат к текущему дню, не перенос даты); «фраз и слов в работе» — как в 21-7 (снимок `21-14`) |
| День 1 собирается / готов / не собрался | `PlanDayPlateView` → `DayPlateNotice` | 22-5a, 22-5b, 22-5c | совпадает: все три состояния живут НА ПЛИТЕ, из таба никуда не уводят; «Собираем день 1 · около минуты · можно закрыть приложение» вместо шиммера, отдельной карточки `PlanDayFailedCard` больше нет (снимки `22-5a-building`, `22-5b-ready`, `22-5c-failed`, `strip-day-plate`) |
| Уведомление «План готов» | [data/plan/plan_ready_notification.dart](../../../mobile/lib/data/plan/plan_ready_notification.dart), [plan_ready_notification_host.dart](../../../mobile/lib/features/plan/plan_ready_notification_host.dart) | 22-6 | совпадает: тело — срок до события готовой строкой сервера и первый день; только если приложение было свёрнуто во время сборки дня 1. «≈ 20 минут» из кадра нет — оценки минут контракт не отдаёт |
| Кабинет дня (заглушка до DAY-UI) | [plan/plan_day_stub_screen.dart](../../../mobile/lib/features/plan/plan_day_stub_screen.dart) | — | **нет кадра, временно**: версия контракта из `Plan.versions`; удаляется нарядом DAY-UI |
| Завершённый план из списка | `PlanFinishedScreen` (тот же `PlanTabBody`, режим чтения) | 21-7 (режим чтения) | совпадает: «Открыть коллекцию» вместо «Собрать новый план» |

## Вход в план — кадры 22-x

| экран / состояние | код | кадры | статус |
|---|---|---|---|
| Каркас шагов: стрелка назад, четыре точки, кнопка внизу | [entry/entry_scaffold.dart](../../../mobile/lib/features/plan/entry/entry_scaffold.dart), сводки — [entry_summary.dart](../../../mobile/lib/features/plan/entry/entry_summary.dart), [plan_entry_screen.dart](../../../mobile/lib/features/plan/entry/plan_entry_screen.dart) | 22-1…22-3b | совпадает. Снято: шапка «Отмена · Новый план · Далее» и лента «Изм.» (`entry_tape.dart` удалён) — тап-цель теперь вся строка сводки |
| Цель | [entry_goal_step.dart](../../../mobile/lib/features/plan/entry/entry_goal_step.dart) | 22-1, 22-1c | совпадает: печатающийся плейсхолдер (примеры НЕ из списка историй), микрофон 44 в четыре состояния, «Так пишут другие» — три истории, тап подставляет. Снято: чипы категорий и их заготовки (снимки `22-1-goal`, `22-1c-goal-short`, `strip-mic`) |
| Язык и уровень | [entry_language_step.dart](../../../mobile/lib/features/plan/entry/entry_language_step.dart) | 22-2 | совпадает: карточка на язык с монограммой в кружке 36 и приветствием, уровень описан умением. **Расходится продуктово**: канва называет ТРИ языка (en/es/de) — вход их и предлагает (`kPlanEntryLanguageCodes`), а профильный `kStudyLanguageCodes` держит два (снимок `22-2-language-level`) |
| Длина плана | [entry_days_step.dart](../../../mobile/lib/features/plan/entry/entry_days_step.dart) | 22-3a | совпадает: 3/5/7/10, состав словами и мини-маршрут точками. Состав — ТАБЛИЦА КАНВЫ в коде, а не формула: `route_summary` сервер отдаёт только у готового плана (снимок `22-3a-days`) |
| Дата разговора | [entry_date_step.dart](../../../mobile/lib/features/plan/entry/entry_date_step.dart) | 22-3b | совпадает: три строки выбора, «дата пока неизвестна» равноправна, строка следствия про репетицию, кнопка «Собрать план». «Сегодня» приходит параметром, поэтому кадр снимается снимком (`22-3b-date`, `22-3b-date-unknown`) |
| Превью: собирается → готов → не собрался → цель непонятна | [entry_preview_step.dart](../../../mobile/lib/features/plan/entry/entry_preview_step.dart) | 22-4a, 22-4b, 22-4c, 22-4d | совпадает по составу; шапка и кнопка на одних местах во всех четырёх состояниях. Снято: свайп «убрать день» (канва: «правок здесь нет»). **Расходится по контракту**: строк «скажешь / спросишь» сервер не отдаёт — рисуются `goals_native` словами (снимки `22-4a-building`, `22-4b-ready`, `22-4b-ready-scrolled`, `22-4c-failed`, `22-4c-offline`, `22-4d-unclear`) |

## Общие виджеты дизайн-системы (`mobile/lib/ui/`)

Одни и те же виджеты у коллекций и у плана — это проверяется воротами наряда, а не обещанием.

| виджет | код | токен / кадр | статус |
|---|---|---|---|
| Вариант ответа 60 с маркером 22 | [answer_option.dart](../../../mobile/lib/ui/answer_option.dart) | 4л; 12a, 16c, 23-7a–d | совпадает |
| Маркер вердикта 22 (шалфей · охра · терракота · контур) | [verdict_marker.dart](../../../mobile/lib/ui/verdict_marker.dart) | 4л, 4к-2 | совпадает |
| Блок задания (лейбл caps + текст, полоса 3 px) | [task_block.dart](../../../mobile/lib/ui/task_block.dart) | 4м; 12a, 12b, 12i, 23-3, 23-5, 23-7, 23-8 | совпадает |
| Плитка 44 · подложка сборки · ряд плиток | [assembly_board.dart](../../../mobile/lib/ui/assembly_board.dart) | 4л; 12b, 23-7e–h | совпадает; в коллекциях чужое слово в собранной строке подчёркнуто волной (поведение тренажёра сохранено) |
| Плита дня (карточка на табе / шапка кабинета) + полоска этапа в трёх цветах | [day_plate.dart](../../../mobile/lib/ui/day_plate.dart) | 4н; 23-0a…0e, 21-x | совпадает |
| Знакомство во весь экран | [intro_layout.dart](../../../mobile/lib/ui/intro_layout.dart) | 16a; 23-1, 23-13 | совпадает |
| Микрофон 80 с амплитудой | [record_button.dart](../../../mobile/lib/ui/record_button.dart), [speech_wave.dart](../../../mobile/lib/ui/speech_wave.dart) | 2б «Микрофон», 4е | совпадает |
| Воспроизведение 44/28 | [play_circle.dart](../../../mobile/lib/ui/play_circle.dart) | 4л | совпадает |
| Пример с пропуском по ширине слова | [cloze_sentence.dart](../../../mobile/lib/ui/cloze_sentence.dart) | 12i | совпадает |

## Кабинет дня — «План · день», 23-0x

| экран | код | кадры | статус |
|---|---|---|---|
| Кабинет · не начат / идёт / закрыт, стык секций, другой день | [day/day_room_screen.dart](../../../mobile/lib/features/plan/day/day_room_screen.dart) | 23-0a, 23-0b, 23-0c, 23-0d, 23-0e | совпадает; отклонения: (1) в идущем дне процент «с первого раза» считает клиент по карточкам (`DayRules.firstTryPercent`) — сервер отдаёт `first_try_share` только в закрытии; (2) «≈ N минут» в мета-строке — оценка клиента по числу карточек (13 с/карточка), минут на день у сервера нет; (3) «Далось труднее всего» — попытки суммируются по карточкам единицы, которую назвал сервер |
| Шит слова / фразы (bottom sheet) | [day/term_sheet.dart](../../../mobile/lib/features/plan/day/term_sheet.dart) | 23-14, 23-15 | совпадает; «В разговоре» у фразы — реплика собеседника из карточки знакомства, если день открыт (до открытия карточек нет) |
| **Штатный вход в кабинет — ссылка `engstd://plan/day/{dayId}`** (также `engstd://plan/{planId}/day/{n}`, `engstd://plan/current`) | [data/deep_links.dart](../../../mobile/lib/data/deep_links.dart), [day/open_day.dart](../../../mobile/lib/features/plan/day/open_day.dart) · `openDayLink`, `ios/Runner/SceneDelegate.swift` → `AppDelegate.handleLink` (канал `com.denis.engstd/links`), `Info.plist` (схема `engstd`) | — | **нет кадра**: механика. Уведомления плана и QA-прогон (`xcrun simctl openurl`) ходят этой же дверью; холодный старт держит ссылку до готовности Dart |
| Стык с табом «План»: плита дня → кабинет | [plan_tab_screen.dart](../../../mobile/lib/features/plan/plan_tab_screen.dart) · `_openDay` → [day/open_day.dart](../../../mobile/lib/features/plan/day/open_day.dart) | 21-2 → 23-0a | совпадает: тап по плите и её кнопке открывает кабинет того же дня; заглушка `plan_day_stub_screen.dart` удалена |


## Сессия дня — «План · день», 23-1 … 23-13

| экран | код | кадры | статус |
|---|---|---|---|
| Каркас: шапка «Слова · 12 из 32», пять сегментов, вход в этап, итог этапа, выход | [day/day_session_screen.dart](../../../mobile/lib/features/plan/day/day_session_screen.dart) | 23-2a, 23-2b, 23-9, 23-11 | совпадает; 409 `plan_day_locked` / `plan_lesson_not_ready` печатаются словами (`day.locked.*`, `day.lesson.*`), «Повторить» зовёт `…/lesson/retry` |
| Знакомство со словом · вернувшееся слово | [day/cards/word_cards.dart](../../../mobile/lib/features/plan/day/cards/word_cards.dart) · `WordIntroCard` | 23-1, 23-13 | совпадает |
| Произнеси (до записи · пишу · услышали · ещё раз / пропустить) | `WordSayCard` | 23-3a, 23-3b, 23-3c, 23-3d | совпадает; «Услышали» — шалфейная подложка на слове и строка «Услышали: …», карточка уходит сама через 600 мс |
| Выбор из четырёх (перевод / по определению) | `WordChooseCard` | 12a («База») | совпадает |
| Слово в пример | `WordClozeCard` | 12i («База») | совпадает |
| Знакомство с фразой · повтори вслух · собери | [day/cards/phrase_cards.dart](../../../mobile/lib/features/plan/day/cards/phrase_cards.dart) | 23-4, 23-5, 12b | совпадает |
| Диалог — весь разговор (переводы открыты / свёрнуты) | [day/cards/dialogue_read_card.dart](../../../mobile/lib/features/plan/day/cards/dialogue_read_card.dart) | 23-6a, 23-6b | совпадает |
| Слушаю и отвечаю: что он спросил (верно / неверно), что ответишь, ты начинаешь, услышал → собери | [day/cards/listen_cards.dart](../../../mobile/lib/features/plan/day/cards/listen_cards.dart) | 23-7a, 23-7b, 23-7c, 23-7d, 23-7e, 23-7f, 23-7g, 23-7h | совпадает; звук вердикта ждёт конца реплики (4к-3) |
| Говорю сам: лента, подсказка-ключ, подсказка-текст, пропуск, ответ собеседника | [day/cards/speak_card.dart](../../../mobile/lib/features/plan/day/cards/speak_card.dart) | 23-8a, 23-8b, 23-8c, 23-8d, 23-8e | совпадает; после записи следующая реплика звучит сама, «Дальше» нет |
| Микрофон: один контроллер на три карточки речи, дев-ряд QA | [day/speech_attempt.dart](../../../mobile/lib/features/plan/day/speech_attempt.dart) | 2б, 4е | совпадает; на симуляторе микрофон мёртв — ход подставляется дев-рядом `QA · said / part / miss` (только у QA-аккаунта) |
| Голос дня: реплики файлом по `audio_id`, слова синтезом | [day/day_voice.dart](../../../mobile/lib/features/plan/day/day_voice.dart) | 2б «Реплика собеседника» | совпадает; без файла — читает телефон, под волной тихая строка «без озвучки — читает телефон» |

## Правки «Базы» у тренажёров коллекций (16a, 12a, 12b, 12i)

| экран | код | кадры | статус |
|---|---|---|---|
| Знакомство во весь экран | [training/session/intro_card.dart](../../../mobile/lib/features/training/session/intro_card.dart) | 16a | совпадает; слово в примере подчёркнуто 2 px (не жирное), бейдж над словом; эхо «повтори вслух» осталось под примером |
| Выбор из четырёх с блоком задания и вердиктом 4л | [training/session/session_exercise.dart](../../../mobile/lib/features/training/session/session_exercise.dart) · `_choicePrompt`, `_pickCorrectPrompt`, `_descriptionPrompt` | 12a | совпадает; лейбл блока задания набран капителью (тесты копий ищут капитель) |
| Сборка: плитки 44, серая подложка, тонировка вердикта | `_wordBankPrompt`, `_scramblePrompt`, `_board` | 12b | совпадает; подсказка «Собери из слов ниже» под пустой подложкой и «Не помню» — поведение тренажёра, без изменений |
| Вставь слово: блок задания + пропуск по ширине слова | `_clozePrompt` | 12i | совпадает |

## Звук и хаптика

| место | код | токен | статус |
|---|---|---|---|
| Четыре звука (верно · неверно · этап закрыт · день закрыт), тумблер «Звуки» в профиле, тихий режим телефона | [theme/feedback.dart](../../../mobile/lib/theme/feedback.dart), [profile_screen.dart](../../../mobile/lib/features/profile/profile_screen.dart), `ios/Runner/AppDelegate.swift` | 4к-3, 4е | совпадает; файлы `assets/sounds/*.wav` генерирует `tool/gen_feedback_sounds.dart` |

## Экраны без кадра (законно)

Состояние «сервер не ответил, кэша нет» на табе (`PlanLoadFailedCard`) — карточка с «Повторить» по
§6 наряда PLAN-UI. Ссылка `engstd://plan/day/{dayId}` — механика входа в кабинет (наряд DAY-UI,
см. раздел кабинета). Заглушка `plan_day_stub_screen.dart` удалена нарядом DAY-UI: её место занял
кабинет дня.

## Удалено нарядом DAY-UI

Экраны и код прежних серий: `plan_screen.dart`, `plan_day_screen.dart`, `plan_day_stages.dart`,
`plan_day_summary.dart`, `plan_dialogue.dart`, `plan_rehearsal_screen.dart`,
`plan_rehearsal_done.dart`, `plan_feedback_screen.dart`, `plan_fail_reason.dart`,
`plan_sitting_store.dart`, `sitting_queue.dart`; ситуативные режимы (`situational_*`),
`SceneRunKnobs`, `PlanDialogue*`, `PlanSessionEnvelope`, `PlanSituation`, `PlanHearOptions`;
превью-инструменты `tool/plan_*_preview.dart`, `tool/voice_preview.dart`; 270 строк `plan*` из ARB.
Полный список — отчёт `docs/research/day-ui/README.md`.
