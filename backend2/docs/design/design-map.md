# Карта экранов ↔ кадров — плановый контур

**Постоянный файл. Наряд, который правит экран планового контура, обязан обновить его строку здесь.**

Переписан нарядом PLAN-UI (2026-09-11) под новый план (PLAN-GEN, `docs/plan-v2.md`, `docs/plan-api.md`).
Строки старых серий — Вход v4, День v1, Диалог v1, Главная v2 — удалены вместе с их экранами: в
клиенте не осталось ни одного экрана, класса, строки или ассета старого плана (список — в отчёте
наряда PLAN-UI).

Источник кадров: экспорт канвасов Claude Design в этой папке — `plan.dc.html` (канвас «План»:
секции таб 21-x / вход 22-x / день 23-x), `tokens.dc.html` (токен-лист, разделы 4к–4о),
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
| Плана нет | [plan/plan_tab_screen.dart](../../../mobile/lib/features/plan/plan_tab_screen.dart) `_EmptyState` + `PlanFinishedList` | 21-1 | совпадает (снимки `21-1-empty`, `21-1-empty-finished`, `21-1-load-failed`) |
| План идёт, день не начат / брошен | `PlanTabBody` + [plan/plan_day_plate_view.dart](../../../mobile/lib/features/plan/plan_day_plate_view.dart) → [ui/day_plate.dart](../../../mobile/lib/ui/day_plate.dart) (4н, свёрнутый размер) | 21-2, 21-2b, 21-3 | **расходится по контракту**: на плите нет «≈ N минут» и «N с подсказкой» (`docs/plan-api.md` не отдаёт оценку минут и подсказки по этапам); у НЕОТКРЫТОГО дня плита стоит и без строк этапов — кабинет до `POST …/open` отдаёт все этапы `absent` (снимки `21-2-plate-fresh`, `21-2b-route-and-kit`, `21-3-abandoned`) — вопросы архитектору |
| Подсказки первого раза | [plan/plan_tab_parts.dart](../../../mobile/lib/features/plan/plan_tab_parts.dart) `PlanHintLine`, флаги — [data/plan/plan_store.dart](../../../mobile/lib/data/plan/plan_store.dart) | 21-2c, 21-4c | совпадает: три на табе, одна при первом закрытии, без крестика, до первого действия |
| День закрыт | `PlanDayPlateView` (closed) | 21-4 | совпадает: светлая карточка, галка 30, «Вернутся в день N · K карточки» из `program[].state = failed` (снимки `21-4-closed`, `21-4c-close-hint`). На маршруте после закрытия дня 1 сервер помечает «завтра» ДВА дня — 2 и 3 |
| Маршрут целиком, масштаб 10 дней | [plan/plan_route.dart](../../../mobile/lib/features/plan/plan_route.dart) | 21-5, 21-6 | совпадает: узлы 22, строки 64, фото 48, слот с сервера (галка / сегодня / завтра / пусто), ink до узла «сегодня» |
| План пройден | `PlanDoneCard` | 21-7 | **расходится по контракту**: «{p} фраз и {w} слов в работе» не отдаётся — строка «7 дней» без второй половины |
| Лист «Как устроен план» | [plan/plan_sheets.dart](../../../mobile/lib/features/plan/plan_sheets.dart) `showPlanHowSheet` | 21-8 | совпадает; один раз после первого плана (флаг на клиенте) |
| Меню плана | `PlanGoalRow` → `showFloatingContextMenu` (4в) | 21-9 | совпадает: дата / новый план / коллекция / удалить (терракота последним); «коллекция» — только когда `collection_id` есть |
| Лист даты | `showPlanDateSheet` | 21-10 | **расходится по контракту**: блока «Маршрут · было 7 → станет 5» нет (нет пробного `PATCH /schedule`); заголовок «Когда приём?» собран из `event_native` (приводится к нижнему регистру — сервер отдаёт слово с заглавной); добавлен вариант «Без даты» (снимок `21-10-sheet-date`) |
| Лист нового плана | `showPlanNewSheet` | 21-11 | совпадает; имя коллекции — `title_native` плана |
| Алерт удаления | `showPlanDeleteAlert` (5f) | 21-12 | совпадает |
| Пропущены дни | — | 21-13 | **не перенесён**: контракт не отдаёт ни факта пропуска, ни строки «маршрут пересобран» — вопрос архитектору |
| Событие прошло | `PlanOverdueCard` | 21-14 | совпадает по составу; «фраз и слов в работе» — как в 21-7 |
| День 1 собирается / готов / не собрался | `PlanDayPlateView` (building) · `PlanDayFailedCard` | 22-5a, 22-5b, 22-5c | совпадает; опрос `GET /plans/current` каждые 3 с, пока `lesson_status = building` |
| Уведомление «План готов» | [data/plan/plan_ready_notification.dart](../../../mobile/lib/data/plan/plan_ready_notification.dart), [plan/plan_ready_notification_host.dart](../../../mobile/lib/features/plan/plan_ready_notification_host.dart) | 22-6 | совпадает: только если приложение было свёрнуто во время сборки дня 1; тап → таб «План» |
| Кабинет дня (заглушка до DAY-UI) | [plan/plan_day_stub_screen.dart](../../../mobile/lib/features/plan/plan_day_stub_screen.dart) | — | **нет кадра, временно**: версия контракта из `Plan.versions`; удаляется нарядом DAY-UI |
| Завершённый план из списка | `PlanFinishedScreen` (тот же `PlanTabBody`, режим чтения) | 21-7 (режим чтения) | совпадает: «Открыть коллекцию» вместо «Собрать новый план» |

## Вход в план — кадры 22-x

| экран / состояние | код | кадры | статус |
|---|---|---|---|
| Каркас шагов: шапка «Отмена · Новый план · Далее», точки, лента «Изм.» | [plan/entry/entry_scaffold.dart](../../../mobile/lib/features/plan/entry/entry_scaffold.dart), [entry_tape.dart](../../../mobile/lib/features/plan/entry/entry_tape.dart), [plan_entry_screen.dart](../../../mobile/lib/features/plan/entry/plan_entry_screen.dart) | 22-1…22-4 | совпадает |
| Цель | [entry_goal_step.dart](../../../mobile/lib/features/plan/entry/entry_goal_step.dart) | 22-1a, 22-1b, 22-1c | совпадает; заготовки для чипов «Аренда / Собеседование / Поездка» добавлены нарядом по образцу «Врач» (в таблице канваса есть только «Врач») |
| Язык и уровень | [entry_language_step.dart](../../../mobile/lib/features/plan/entry/entry_language_step.dart) | 22-2 | совпадает: языки — `studyLanguagesFor` (английский, немецкий + язык аккаунта), подписаны в языке интерфейса («Английский», `languageNameFor` — как в таблице канваса, а не эндонимом); «Средний» предвыбран при B1+ (снимок `22-2-language-level`) |
| Дни | [entry_days_step.dart](../../../mobile/lib/features/plan/entry/entry_days_step.dart) | 22-3a, 22-3b | **расходится по контракту**: строки расчёта «5 дней · 3 ситуации, 1 повторение, репетиция» под чипами нет — сервер отдаёт `route_summary` только с готовым планом |
| Превью: скелет → маршрут → не собрался → цель непонятна | [entry_preview_step.dart](../../../mobile/lib/features/plan/entry/entry_preview_step.dart) | 22-4a, 22-4b, 22-4c, 22-4d | совпадает; «около 10 секунд» — одна строка (другой для 7–9 сцен в контракте нет); свайп сцены → `DELETE /scenes/{id}` |

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
