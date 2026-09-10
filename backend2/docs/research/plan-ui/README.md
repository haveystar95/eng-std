# Отчёт наряда PLAN-UI — мобильный клиент: таб «План» и вход в план по кадрам

Дата: 2026-09-11. Источники: канвас «План» (`backend2/docs/design/plan.dc.html`, секции «План · таб»
21-x и «План · вход» 22-x, подписи, таблица текстов, записка разработчику), токен-лист
(`tokens.dc.html`, разделы 2, 4в, 4г, 4д, 4е, 4к, 4н, 4о), контракт `docs/plan-api.md` + OpenAPI.
Коммит клиента и хеш на экране профиля — см. раздел 3.

## 1. Разведка и чистка — таблица удалённого

Разведка нашла старый план в 20 файлах `lib/features/plan/**` (~11 100 строк), трёх файлах данных,
секции `api_client.dart`, блоке `providers.dart`, конверте плана внутри `models.dart` и
`session_screen.dart`, 441 строке `plan*` в ARB, восьми харнессах `tool/` и 20 тестах. Всё ниже
**удалено** (не выключено); папок «old/legacy» и флагов «новый план» нет.

| что | где было | судьба |
|---|---|---|
| Экраны старого плана: таб, список дней, кабинет дня, экран дня, сборка дня, итог дня, финал, прогон (репетиция), «Как прошло?», причины отказа, оболочка диалога, спасатели/разогрев как сущности, плановый UI (латунные плашки, полосы готовности, слова состояния) | `lib/features/plan/*.dart` (15 файлов) | удалены |
| Вход v4: шаги, шаг слуха, сборка плана, UI входа (терракотовая CTA, лента, карточки-выбор) | `lib/features/plan/entry/*.dart` (4 файла) | удалены |
| Карточка плана на главной | `lib/features/plan/home_plan_card.dart`, `HomeBlockKeys.plan`, слот на «Сегодня» | удалены |
| Модели старого плана: `LearningPlan`, `PlanSummary`, `PlanDay`, `PlanDayDetail`, `PlanSession`, `PlanSessionTask`, `PlanTermRow`, `PlanRehearsal`, `ListenWarmup` и др. (1535 строк) | `lib/data/plan_models.dart` | удалён |
| Три напоминания по дате события | `lib/data/plan_notifications.dart`, `plan_notification_host.dart` | удалены; вместо них одно событийное «План готов» |
| Хранилище присеста | `lib/data/plan_sitting_store.dart`, ключ `plan_sitting` | удалено |
| API старого плана: `activePlan`, `plans`, `createPlan(minutes, listening)`, `listenWarmup`, `buildPlanOutline`, `reschedulePlan(outline)`, `pause/abandon/complete`, `planDay`, `generate/rebuildPlanDay`, `buildPlanSession`, `planRehearsal`, `recordSceneRun`, `submitPlanFeedback`, QA-часы `setQaPlanClock/qaPlanClock` | `lib/data/api_client.dart` | удалены; новая секция по `docs/plan-api.md` |
| Провайдеры: `planNotificationsProvider`, `activePlanProvider`, `planArchiveProvider`, `planProvider`, `planDayProvider`, `planSittingStoreProvider`, `planSittingProvider`, `planSessionProvider` | `lib/data/providers.dart` | удалены |
| Конверт плана в сессии: `StudySession.plan`, `PlanSessionEnvelope`, `PlanDayStateWire`, `PlanSittingKind`, `PlanDialogue`, `PlanDialogueTurn` | `lib/data/models.dart` | удалены |
| Плановая ветка сессии: аргументы `planId/planDayIndex/planStage/planIsFinalDay`, `planSessionProvider`, восстановление присеста, докачка озвучки посадки, оболочка диалога, финал разговора, экран между присестами, плановый итог дня, репетиция, плановая полоса и латунная плашка в шапке, швы секций, прогон сцены и `POST scene-runs` | `lib/features/training/session_screen.dart` (3041 → 1385 строк) | удалены; сессия тренажёров осталась одна |
| `SittingQueue.resume` | `lib/features/training/session/sitting_queue.dart` | удалён (нужен был только присесту плана) |
| `_QaPlanClockRow` (QA-часы плана) | `lib/features/profile/profile_screen.dart` | удалён вместе с `/qa/plan-clock`, которого на сервере больше нет |
| Латунное кольцо центрального таба (`FloatingTabItem.accent`) | `lib/ui/floating_tab_bar.dart` | удалено — кадры 21-x рисуют три ровных таба |
| Ситуационные режимы в реестре языков (`situational_*`) | `lib/data/practice/language_mode_support.dart` | убраны из списка — сервер их больше не регистрирует (парити-тест читает PHP-таблицу; падал ещё до наряда) |
| Строки ARB `plan*` (441) + `tabProgress`, `tabProfile`, `tabSearch`, `homePlanCardReadiness`; `planSayIntent` → `sessionSayIntent`, `planSceneRunHint` → `sessionSceneRunHint` (это строки тренажёра) | `lib/l10n/app_ru.arb`, `app_en.arb` | удалены / переименованы; новый набор из таблицы канваса — 136 ключей |
| Харнессы: `entry_preview(.dart, _data)`, `plan_list_preview`, `plan_summary_preview`, `plan_done_preview`, `plan_dialogue_preview`, `voice_preview`, `speech_preview` | `tool/` | удалены (последние два импортировали оболочку диалога) |
| Тесты старого плана (17 файлов `test/features/plan/`), `plan_sitting_store_test`, тест `resume` очереди | `test/` | удалены; `build_stamp_test` переехал в `test/features/profile/` |
| Словарь `docs/plan-ui-glossary.md` (старые 441 ключ) | `docs/` | переписан под новые ключи, скрипт `docs/plan-ui-glossary.py` |
| Карта экранов старого плана | `backend2/docs/design/design-map.md` | переписана под новый план |

**Не тронуто (общее с тренажёрами), с вопросом:** `PlanSituation` и `SceneRunKnobs` в `models.dart`
и параметры `situation` / `sceneRun` / `inDialogue` / `sayIntent` / `roleLineText` у
`SessionExerciseCard` (ситуационные тренажёры и прогон сцены живут внутри `session_exercise.dart`,
2964 строки, с собственными тестами `situational_card_test`, `scene_run_card_test`,
`assemble_turn_test`). Их раздавал только старый план; на сервере режимов уже нет. Снос — это правка
тренажёра, а не плана → вопрос 5.1. Также осталась инфраструктура озвучки (`line_audio.dart`,
ветка `pronouncer.dart`, нативный плеер) — она нужна карточкам нового дня (`audio_id`), это DAY-UI.
Ассеты: у старого плана не было своих; `assets/tts_samples/` — дев-экран «Голоса реплик», не план.

`grep -rn "LearningPlan\|PlanSession\b\|PlanSittingStore\|PlanDialogue\|activePlanProvider\|planSessionProvider\|PlanNotifications\b\|plan_models.dart\|EntryCta\|PlanPill" mobile/lib mobile/test mobile/tool` — пусто.

## 2. Что сделано по разделам 2–7

**§2 Навигация.** Три таба Сегодня · План · Коллекции (`home_screen.dart`, `kPlanTabIndex`);
профиль — `ProfileAvatarButton` (кружок 30 на подложке #E3DCCF) в шапках всех трёх табов, экран
профиля толкается с шевроном назад; прогресс — тап по плите статистики на «Сегодня» толкает
`ProgressScreen` (тоже с шевроном). Табы «Прогресс» и «Профиль» удалены вместе со строками.
`tabHome` стал «Сегодня» (tab.today).

**§3 Таб «План».** `features/plan/plan_tab_screen.dart` + части (`plan_tab_parts.dart`,
`plan_route.dart`, `plan_day_plate_view.dart`, `plan_sheets.dart`). Состояния — только из ответа
сервера (`PlanTabState`, `plan_providers.dart`): плана нет · плита дня (не начат / брошен) ·
день закрыт (плита про закрытый сегодня день, если следующий завтра) · план пройден · событие
прошло (`status = overdue` + `overdue_native`) · день 1 пишется (`lesson_status = building`,
опрос раз в 3 с) · день не собрался (`failed` → `POST …/lesson/retry`). Плита — `lib/ui/day_plate.dart`
(4н, свёрнутый размер): строки этапов из `GET /plans/{id}/days/{n}` `stages[]`, вторая строка
текущего — «начни отсюда · N новых слов» (слова `program[]` с `source = today`) или «не закончен · N
карточек»; подвал — «Начать» / «Продолжить» / закрытая карточка на светлой бумаге с галкой 30 и
«Вернутся в день N · K карточки» (`program[].state = failed`). Маршрут — узлы 22, строки 64, фото
48 у сцен (будущие .72), повторение/репетиция tertiary, слот справа — строка сервера (галка у
закрытого, «сегодня»/«завтра» латунью), линия ink до узла «сегодня», мишень события двумя строками
(дата и день недели — по локали), заголовок — `until_phrase` как есть. Спасательный набор —
карточка 21-2b с первой фразой, «все 5 →» раскрывает остальные на месте. Завершённые планы — список
из `GET /plans` (`status = finished`), тап открывает план в режиме чтения (21-7 с «Открыть
коллекцию»). Подсказки первого раза — три на табе и одна при первом закрытии, флаги в `sync_meta`
(`PlanStore`), гаснут после первого действия. Лист «Как устроен план» — один раз после первого
«Начать» (флаг на клиенте), через 320 мс после возврата на таб. Меню плана — `showFloatingContextMenu`
(4в): дата / новый план / коллекция (если `collection_id` есть) / удалить (терракота последним).
Листы даты, нового плана, алерт удаления — по кадрам; удаление → `DELETE /plans/{id}` → 21-1.
Офлайн — последний ответ `GET /plans/current` из `sync_meta` (`plan_current`) под строкой «нет
сети»; без кэша — карточка «Не получилось загрузить план» с «Повторить». Тап по плите — заглушка
кабинета (`plan_day_stub_screen.dart`) с версией контракта (`Plan.versions`), удаляется в DAY-UI.

**§4 Вход.** `features/plan/entry/`: каркас как онбординг — шапка «Отмена · Новый план · Далее»,
четыре точки, лента ответов с «Изм.» (`entry_tape.dart`), вопрос Literata 30. Шаг цели: поле
#FCFAF5 с тенью (в фокусе глубже), плейсхолдер-пример, микрофон (speech_to_text на родном языке),
строка «Добавь, с кем и что важно» при < 8 словах, пять бумажных чипов с заготовками. Язык и
уровень: чипы из `studyLanguagesFor` (языки настроек), две карточки уровня (`ChoiceCard`, выбор —
заливка ink), «Средний» предвыбран при B1+ и строка про «свободно говорю» под ним. Дни: чипы
1·3·5·7·10 (Literata 20), переключатель даты между хайрлайнами, календарь, строка сокращения
латунью и чип переезжает. Превью: `POST /plans` (202) → опрос `GET /plans/{id}/build` раз в 2 с →
`ready` — маршрут с обложкой 52; `unclear` — 22-4d, «К цели» возвращает на шаг с сохранённым
текстом; `failed` или сеть — 22-4c с «Ещё раз» (`POST build/retry`) и «Изменить цель»; свайп
сцены → `DELETE /scenes/{id}` (ядро — 409, строка отпружинивает). «Начать» → `POST /start`, вход
закрывается, таб принимает план (22-5a). Уведомление «План готов» — `PlanReadyNotification`,
только если приложение было свёрнуто, пока день 1 был `building`; тап → таб «План».

**§5 Строки.** Таблица канваса перенесена в `app_ru.arb`/`app_en.arb` как есть (136 ключей
`plan*`/`planEntry*`; формы числа — ICU plural: карточки, минуты, дни, новые слова, фразы,
«Вернётся/Вернутся»); словарь `docs/plan-ui-glossary.md`; гарды — каждый ключ в словаре, каждый
ключ читается из кода, словарь 4к-4. Строки, которых нет в таблице и которые пришлось добавить,
названы в словаре и в описаниях ключей (см. 2а ниже).

**§6 Контракт и ошибки.** Все вызовы — по `plan-api.md` (`api_client.dart`, секция «The plan»).
Без алертов и тостов: сеть на табе — кэш + «нет сети»; на входе — 22-4c с текстом «Без сети план
не собрать»; отказы 409 при действиях меню — тихая хаптика warning, план остаётся как был.

**§7 Звук и хаптика.** Звуков на табе и входе нет; хаптика light на тапах, warning на отказах.
Выключатель «Звуки» в профиле (`AppSettings.soundsEnabled`, по умолчанию включён) → `AppFeedback.soundsEnabled`.
Success при закрытии дня — это DAY-UI (закрытие происходит там).

**2а. Отклонения от кадров и причины (контракт не отдаёт):**

| кадр | что не нарисовано | почему |
|---|---|---|
| 21-2, 21-3 | «≈ 20 минут» в мете плиты и «≈ 4 мин» во второй строке брошенного этапа | оценки минут в `PlanDayRoute`/`PlanDayRoom` нет (только `minutes_spent`) |
| 21-3, 21-4 | «1 с подсказкой» под этапом | `PlanStageProgress` несёт только total/done/state; `program[].state` не различает hinted |
| 21-7, 21-14 | «41 фраза и 52 слова в работе» | агрегата по плану нет (только `program[]` дня) |
| 21-10 | блок «Маршрут · Было 7 дней → станет 5» и строки «− День повторения (день 3)» | пробного `PATCH /schedule` нет; клиент маршрут не считает (§ «Что клиенту НЕ надо считать»); заголовок «Когда приём?» собран из `event_native` — по таблице его склоняет сервер |
| 21-13 | плашка «Ты пропустил 3 дня — маршрут пересобран» целиком | в `Plan` нет ни факта пропуска, ни строки изменений |
| 22-3a | «5 дней · 3 ситуации, 1 повторение, репетиция» под чипами | `route_summary` приходит только с готовым планом; вызова «посчитай дни» до `POST /plans` нет |
| 22-4a | «другая строка для 7–9 сцен» | в `PlanBuild` нет оценки; стоит одна «около 10 секунд» |
| 22-1b | заготовки чипов «Аренда», «Собеседование», «Поездка» | в таблице есть только «Врач» — добавлены по её образцу, иначе четыре чипа из пяти ничего не делали бы |
| 21-2b | «все 5 →» | второго экрана в кадрах нет — набор раскрывается на месте, добавлена строка «свернуть» |

Добавленные строки (нет в таблице): `planKitCollapse`, `planMenuLabel` (подпись меню для
читалки), `planDateTitleNoEvent`, `planDateRemove` («Без даты» — `event_date: null` по контракту),
`planDeleteBodyNoCollection` (у плана без закрытого дня коллекции ещё нет), `planTabOffline`,
`planTabLoadFailedTitle`, `planTabRetry`, `planDayStub*`, `planRouteDayRepeatSubOne` («дня 1» для
повторения после одной сцены), `planEntryGoalTemplateRent/Interview/Trip`, `planEntryGoalDictate*`,
`planEntryTapeLanguageValue`, `planEntryTapeDaysValueDated`, `planEntryOffline`.

## 3. Кадр → скриншот → расхождения

_(заполняется по прогону на симуляторе — см. ниже)_

## 4. Самопроверка кода — что переписано

## 5. Вопросы к архитектору — только блокирующие

## 6. Что не сделано и почему
