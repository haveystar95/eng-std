# Карта экранов ↔ кадров — плановый контур

**Постоянный файл. Наряд, который правит экран планового контура, обязан обновить его строку здесь.**

Переписан нарядом DAY-UI (11.09.2026): старые серии «Вход v4», «День v1», «Диалог v1», «Главная v2»
сняты вместе с экранами, которые по ним были собраны (день = вводка, полки, шпаргалка; диалог =
такты, ступени B/B+, спасатели, финал; прогон; лестница A/B/C; разогрев). Источник кадров теперь —
три канвы: **«План»** (`plan.dc.html`, раздел «План · день», кадры 23-x), **«Токен-лист»**
(`tokens.dc.html`, 4к–4о) и **«База»** (`base.dc.html`, правки 16a, 12a, 12b, 12i). Канвы **не в
гите**: если файла нет, спросить у владельца. Приоритет при расхождении: токен-лист (включая 4о) →
подпись под кадром → картинка.

**Снимки состояний — golden-тесты** `mobile/test/goldens/day_ui_golden_test.dart`: фикстура ответа
сервера (`test/goldens/fixtures/*.json`, снято с backend2 11.09.2026) → экран → `test/goldens/<кадр>.png`.
Кадр из этой карты ищется по имени файла (`23-7c-listen-wrong.png`); перерисовать —
`flutter test test/goldens --update-goldens`.

Статусы: **совпадает** — экран сделан по кадру; **расходится** — есть кадр, экран другой, причина
названа; **не перенесён** — кадра нет в коде вовсе; **нет кадра** — экран есть, кадра для него не
рисовали (и это законно).

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
| Стык с табом «План» — заглушка | [plan_tab_screen.dart](../../../mobile/lib/features/plan/plan_tab_screen.dart) · `_DayEntryStub` | 21-x — **наряд PLAN-UI** | **заглушка DAY-UI**: день и одна кнопка → `openDayRoom`; таб по кадрам 21-x рисует PLAN-UI и заменяет её |
| Домашняя карточка плана, сборка плана, уведомления → кабинет | [home_plan_card.dart](../../../mobile/lib/features/plan/home_plan_card.dart), [plan_building_screen.dart](../../../mobile/lib/features/plan/plan_building_screen.dart), [plan_notification_host.dart](../../../mobile/lib/features/plan/plan_notification_host.dart) | — | **нет кадра** (стык): домашняя карточка и сборка ведут в кабинет через `openDayRoom`, уведомления — через ссылку `engstd://plan/current`; экран сборки опрашивает `lesson_status` дня из `GET /plans/current` |

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

`plan_notification_host.dart` (три уведомления ведут в кабинет), `plan_building_screen.dart`
(ожидание урока после «Начать» в превью), `entry/*` и `plan_preview_screen.dart` — вход в план,
кадры 21-x, наряд PLAN-UI.

## Удалено нарядом DAY-UI

Экраны и код прежних серий: `plan_screen.dart`, `plan_day_screen.dart`, `plan_day_stages.dart`,
`plan_day_summary.dart`, `plan_dialogue.dart`, `plan_rehearsal_screen.dart`,
`plan_rehearsal_done.dart`, `plan_feedback_screen.dart`, `plan_fail_reason.dart`,
`plan_sitting_store.dart`, `sitting_queue.dart`; ситуативные режимы (`situational_*`),
`SceneRunKnobs`, `PlanDialogue*`, `PlanSessionEnvelope`, `PlanSituation`, `PlanHearOptions`;
превью-инструменты `tool/plan_*_preview.dart`, `tool/voice_preview.dart`; 270 строк `plan*` из ARB.
Полный список — отчёт `docs/research/day-ui/README.md`.
