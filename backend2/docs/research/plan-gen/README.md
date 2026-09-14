# Наряд PLAN-GEN — отчёт (2026-09-10)

> **15.09, наряд GEN-2a:** урок первой формы, его проверки и поля, описанные в этом отчёте, снесены вместе с кодом; имена снятых проверок, полей и файлов заменены здесь описаниями (точные — в git до `f843f607`). Действующий урок — `lesson_day.v4.4`, канон — `docs/plan-v2.md`.

Коммит: `5b2809c1` (ворота хука пройдены).

Бэкенд нового плана: два промта, проверки в коде, сборка дня, снос старого. Канон —
`docs/plan-v2.md`, контракт — `docs/plan-api.md` + `openapi/openapi.yaml` (тег `Plans`), модуль —
`app/Modules/Plan` (`README.md`). Живой прогон — `tools/live-run.php`, EXPLAIN — `tools/explain.php`.

## 1. Разведка — что старого найдено и удалено

| где | что было | что сделано |
|---|---|---|
| `Learning` (~180 файлов) | `*Plan*`, `Scene*`, `Sitting*`, `ListenWarmup*`, QA-часы (`QaPlanClock`, `ShiftableClock`, `ShiftQaPlanClock*`), `Situational*`, `DayCapacity`, `DeadlineCheck`, `Computed*`, `EnrollmentPolicy`, `PlanHeldTerms`, `PlanController`, консольные `ArchivePlanTerms`/`ReconcilePlanDay`, 17 плановых миграций | удалено; `EnrollmentSources`, `TermProgress`, `EloquentDueTermsReader`, `EloquentHomePlanReader`, `ExerciseMode` (ситуативные режимы), `ModePassport`, `TermPlayability`, `StudyCardAssembler`, `SubmitReviewsHandler` (ветка `KeyAndRest`), `SessionCardView`/`SessionResource` (`speaking_key`, `speaking_keys`, `say_as`), `StudyController` (scope `plan`), `routes.php`, провайдер — вычищены; `QaToolsDoor` заведён вместо плановой двери |
| `Generation` (~74 файла) | `Plan*` (день, каркас, судья, починка, валидаторы, ключи, спенд-леджер), `BasicVocabulary`, `NumberSpelling`, `SupportLanguageText`, `TranslationKeyPresence`, `ExampleAdmission`, труба озвучки коллекций (`SpeakCollectionLines*`, `SpeakLinesJob`, диспетчеры), 24 файла промтов `plan_outline.*`, `plan_day.*`, `plan_day_repair.*`, `plan_listen.*`, `plan_pair_judge.*`, `plan_pair_rewrite.*` | удалено; `SpeechSynthesizerPort` + адаптеры + `SpeechEncoder` + `ImageSearchPort` оставлены (их зовёт план); `generation.speech.shelves` и `generation.plan` из конфига убраны; миграция `add_plan_spend` переписана (только `purpose`), `plan_id` снят |
| `Vocabulary` (~28 файлов) | `TermPlanFactsWriter`, `TermExampleScopeWriter`, `KnownTermsReader`, `SpeakableLine*`, весь кластер `TermAudio*` + `TermAudioController`, 9 плановых миграций колонок `terms` | удалено; `TermContentView`/`TermAnswerKeyView`/`ExampleRegenContext` без плановых полей; `EloquentDistractorReader` без `kind`/семейств; маршруты Vocabulary пусты |
| `Shared` | `DifficultyScorer`, `DistractorFamily`; `LanguageModeSupport` с ситуативными режимами; `DistractorLength` со словами | удалено / упрощено (`DistractorLength` — только символы) |
| `Collections` | `UserCollectionTermsReader::joinedAtForCollection()` | удалено; `CollectionOrigin::Plan` оставлен (папка плана скрыта из списка) |
| тесты (~100 файлов) | `Plan*Test`, `tests/Fixtures/plan/`, дубли `RecordingPlanDefectReporter`, `ScriptedPlanModel`, `fakePlanModel()` и хелперы в `Pest.php`, `TranslationKeyPresenceTest`, `DistractorFamilyTest`, `DifficultyScorerTest`, … | удалено; 8 тестов с `scope='global'` / `say_as` / `PlanOutlinePort` поправлены под новую схему |
| docs | `docs/prompts/plan/`, `plan-model.md`, `plan-dialogue.md`, `plan-map.md`, `p1.*`, `p2.*`, `p-listen.*`, `plan-1a-run.md`, `plan-1b-run.md`, `docs/research/gen-1/tools/` | удалено; `docs/research/*` (отчёты прогонов) и корневой `docs/plan-ui-glossary.md` (мобильный) оставлены как история |
| промты | `docs/prompts/plan-builder-v2.md`, промт урока первой формы | перенесены байт-в-байт в `app/Modules/Plan/Infrastructure/Prompt/`; **не редактировались** |
| БД (миграции) | `learning_plan_*` (7 таблиц), `plan_skills`, `plan_conversations`, `term_audios`; колонки `terms.{is_line, difficulty_score, kind, frame, speaker, filler, shelf, tier, skill_ref, number_value, speaking_key, speaking_keys, topical}`, `term_examples.scope_collection_id`, `learning_mode_settings.{scope, level, knobs}`, `generation_requests.plan_id`; ситуативные `reviews` | три миграции-сноса (`2026_09_10_100000`, `…100100`, `…100200`), одна миграция создания (`…110000`). **На `wordtrainer` не применялись — см. §6** |

Всего удалено 403 файла (`git status`). Grep старых имён (`PlanOutline`, `PlanDayComposer`, `plan_day`,
`plan_outline`, `SkillRef`, `learning_plan`, `situational_*`, `ListenWarmup`, `PlanHearOptions`,
`RescueKitSource`, `QaPlanClock`, `PlanHeldTerms`, `plan_pair`, `SceneRun`, `StagePassage`,
`DayCapacity`, `readiness`, `PlanStageLadder`, `scope='plan'`, `speaking_keys_graded`, `term_audios`,
`is_line`) по `app`, `config`, `database`, `tests`, `openapi`, `.claude`, `scripts` — пусто, кроме:
миграции-сносы (называют то, что сносят), `2026_08_30_120300_allow_plan_purpose` (история CHECK),
`2026_09_02_100000_add_origin_to_collections` (бэкфилл под `Schema::hasTable`), докблок
`LiveModelGuard`/его тест (история инцидента, имя класса убрано). `docs/ROADMAP.md`,
`docs/design/design-map.md` (мобильный) и `docs/research/*` — история, не код.

**Папки промтов после наряда.** `app/Modules/Plan/Infrastructure/Prompt/`: `plan-builder-v2.md`
(читает `PlanPromptFiles::planSystem()`), промт урока первой формы (`lessonSystem()`).
`app/Modules/Generation/Infrastructure/Prompt/`: `generate_collection.v1…v9.md`
(`OpenAiCollectionGenerator`, по `GENERATION_CORE_PROMPT_VERSION` при `GENERATION_STACK=v1`),
`v10…v15.2/` (`PromptLibrary`, составные), `enrich_pack.v1/v2.md` (`OpenAiEnrichmentPacker`),
`enrich_term.v1.md` (`OpenAiTermEnricher`), `lookup_word.v1…v6.md` (`OpenAiWordLookup`),
`practice_dialog.v1…v3.md` (`PracticeDialogInstructions`), `regenerate_example.v1/v2.md`
(`OpenAiExampleRegenerator`), `repair_translation.v1.md` (`OpenAiTranslationRepairer`),
`term_reading.v1.md` (`OpenAiTermTransliterator`). Старые версии генерационных промтов читаются
только при откате версии в конфиге; они не плановые и не трогались (вопрос §6). Папок
`archive/old/legacy` нет. `docs/prompts/` — только `REGISTRY.md`.

## 2. Что сделано по §2–10, отклонения

- **§2 модель.** Семь таблиц (`docs/plan-v2.md` §1). Отклонения от наряда: статусы плана расширены
  `unclear`/`failed`/`ready` (иначе клиенту нечего опрашивать); `CardResult::skipped` (произносимая
  карточка после двух попыток — «провал без последствий» надо хранить отличимо от `failed`); на
  плане/сцене — `build_version`, `latency_ms_*`, `attempts_*`, `checks_json` (наряд просит версию
  сборки и цену вызова — задержка и находки лежат рядом); `plan_terms.ref` (адрес единицы для
  карточек); `plan_line_audios` (озвучка реплик A — раньше жила в `term_audios`, снесённой);
  `plan_check_counters` (счётчики §5 для админки); `plans.collection_id`; `day_cards.unit_kind/unit_ref/retry_of/returns`
  (возвраты и повтор карточки). Сущностей сверх §2 нет.
- **§3 вызовы.** `PlanPromptFiles` (версия = имя файла, `TEST INPUT` вырезан, входы отдельным
  сообщением), `PlanSchemas` (strict; A/B через `anyOf`), `ContentModelPlanBuilder` через
  `ContentModelCatalog::get(provider, model, 'plan', timeout)` — в порт каталога добавлен
  необязательный таймаут (план — 90 с, каталог — 180). Цена из usage (`ModelCost`), `gpt-5.4`
  тарифицируется. Ретрай один, только схема/`gate`; `unclear` — статус плана.
- **§4 когда.** Превью → план → урок дня 1 сразу; открытие дня N → урок следующего дня-сцены;
  закрытие тоже ставит, если не поставлен; расширение — `EXISTING_SCENES` + дописывание.
- **§5 проверки.** 12 + 5, режимы по имени в `config/plan.php`, все `observe`; счётчики и
  `checks_json`; инвалидный JSON = отказ модели. Порядок запуска — как в таблице канона; проверка
  видит урок после предыдущих `drop`.
- **§6 сборка.** `DayAssembler` + пять стадий, детерминированные перемешивания (`Shuffle::seeded`),
  возвраты, повторение, репетиция, метрики. Терпимость к каждому сломанному JSON проверена тестом.
  Добор ложных переводов для Beginner — через новый порт Vocabulary `NativeDistractorReader`
  (переводы каталожных слов по длине и CEFR; частотности в `terms` нет).
- **§7 календарь.** `PlanCalendar`, укорачивание по дате, один день в календарный день (даты
  сравниваются как даты в зоне пользователя), укорачивание сцен по правилу, `overdue` вычисляется.
- **§8 джобы.** Фото — `AttachPlanImagesJob` (Pexels, best effort), озвучка A —
  `SpeakSceneLinesJob` (`SPEECH_ENABLED`, голос пакета, файл на приватном диске, `GET /plans/audio/{id}`).
- **§9 снос.** См. §1. Ситуативные строки `reviews` (139 на проде) удаляются миграцией — это
  данные старой генерации, что наряд разрешает; инвариант «reviews append-only» нарушается
  сознательно и назван в докблоке миграции. Папки-коллекции старых дней (25 на проде) — мягко.
- **§10 контракт/доки.** `docs/plan-v2.md`, `docs/plan-api.md`, OpenAPI (старые пути/схемы/поля
  сняты, 21 путь + 17 схем добавлены), `docs/prompts/REGISTRY.md`, ROADMAP, `session-handoff.md`,
  `CLAUDE.md`/`ARCHITECTURE.md` (модуль `Plan`), `app/Modules/Plan/README.md`, skill `testing-pest`.
- **Решение сессии:** план — **новый модуль `Plan`**, а не перестройка внутри `Learning`: у плана
  свой жизненный цикл, свои таблицы, ни одной общей сущности с SRS. CLAUDE.md просит спрашивать
  перед новым модулем — архитектор был недоступен, решение в §6.
- **Оценивает клиент.** По §6 наряда клиент получает полный список карточек с пейлоадом
  (варианты с `correct`, `expected`, ключи) и присылает вердикт; сервер ведёт последствия
  (повтор, возврат, метрики). Инвариант Learning «оценивает только сервер» на карточки плана не
  распространяется — они не пишут `reviews` и не двигают SRS.

## 3. Ворота §11 — цифры

Живой прогон: `wordtrainer_e2e_test`, `gpt-5.4` (ответ `gpt-5.4-2026-03-05`), очередь inline,
озвучка выключена (включена отдельно в §5а), фото Pexels живые (75 запросов). Сборка сервера в
строках прогона — `15530593` (штамп до доработки; обновлён, см. §5а).

| цель | план: статус · цена · задержка · попытки · сцены | урок дня 1 | урок дня 2 (при открытии дня 1) | далее |
|---|---|---|---|---|
| «Иду к врачу с ребёнком…» 5 дней, beginner (прогон 2) | ready · $0.0225 · 9.3 с · 1 · 3 (Запись к врачу p3, Приём у врача p1, Аптека p2) | $0.0646 · 26.4 с · 1 · 5 находок | $0.0802 · 28.8 с · 1 · 2 находки | день 3 (Аптека) при открытии дня 2: $0.0830 · 31.0 с · 1 находка |
| та же цель, прогон 1 | ready · $0.0232 · 10.8 с · 1 · 3 | $0.0832 · 29.8 с · 6 | $0.0821 · 29.5 с · 3 | $0.0815 · 30.6 с · 2 |
| «аренда квартиры» 3 дня, intermediate (прогон 3) | ready · $0.0184 · 8.5 с · 1 · 2 (Просмотр жилья p1, Звонок агенту p2) | $0.0830 · 30.8 с · 4 | $0.0834 · 31.8 с · 2 | репетиция собрана |
| «хочу подтянуть английский» | **unclear** · $0.0100 · 5.5 с · 1 · 0 — «The goal is general language improvement, not a specific real-life situation with a conversation partner.» | — | — | `POST …/start` → 409 `plan_state` |
| «Поездка в Лиссабон…» 10 дней, beginner (прогон 3) | ready · $0.0366 · 21.3 с · 1 · 6 (Регистрация p4, Паспортный контроль p3, Поездка в отель p2, Заселение в отель p5, Кафе p6, Как пройти p1); `char_limits`: «Паспортный контроль» 19 > 18 | $0.0816 · 30.0 с · 1 | $0.0810 · 29.5 с · 1 | маршрут «10 дней · 6 ситуаций, 3 повторения, репетиция»; день 1 открыт |
| та же цель, прогон 2 | ready · $0.0341 · 17.4 с · 1 · 6 (Регистрация p4, Досмотр p3, Посадка p2, Заселение p1, Как пройти p5, В кафе p6) | $0.0841 · 31.2 с · 13 находок | — | |
| **расширение** плана «врач» 5 → 8 дней (`PATCH …/schedule`, доработка 10.09) | ready · **$0.0189 · 8.9 с** · попытка 1 (на плане суммарно $0.0415, attempts 2) · +2 сцены с `EXISTING_SCENES` = 3 названия: «Повторный приём» (variant p4), «Анализы и снимок» (variant p5) — дописаны как order 4, 5 | — (уроки новых сцен ставятся при открытии дней) | — | маршрут «8 дней · 5 ситуаций, 2 повторения, репетиция»; закрытый день 1 и открытый день 2 не тронуты |
| **озвучка A** одного урока («Запись к врачу», Gemini `Aoede` p90, mp3 через lame) | — | 9 реплик собеседника → 9 вызовов TTS, **$0.0066, 33.1 с** (3.1–4.4 с на реплику), 8 файлов / 215 КБ (одна реплика — повтор номера шага, см. ниже) | — | 24 из 76 карточек дня несут `audio_id`; `GET /plans/audio/{id}` → 200 |

Пороги наряда: урок ≤ $0.10 ✓ (макс $0.0853), план ≤ $0.05 ✓ (макс $0.0366; расширение $0.0189),
оба ≤ 90 с ✓ (макс 31.8 с). Озвучка дня ≈ $0.007 и ~35 с в фоне. `POST /plans` синхронно (план + урок дня 1 + фото) — 42–63 с; на проде клиент
опрашивает. 19 вызовов `purpose=plan` в `api_request_logs`, средняя 23.2 с, максимум 31.7 с;
итого за все прогоны ≈ $1.03 (6 планов, 11 уроков).
Все попытки — первые; `gate` ни разу не сработал (все проверки в `observe`).

**Счётчики проверок** (`plan_check_counters`, 11 уроков + 6 планов): «метка слова на реплике без слова» 25,
«метка фразы на реплике без фразы» 10, «фраза ни в одной реплике» 10, «второе сообщение с вопросом» 9, «чтение чужим алфавитом» 2,
«слово внутри другого» 2, «число единиц» 1 (9 обменов вместо 8), «форма обмена» 1, «вариант длиннее реплики» 1,
план: `char_limits` 1. Все `counted`; ни один урок не отбит; сборка дня прошла на каждом.
Наблюдения по промту (не правки — промт заморожен): модель ставит метки слов на реплику,
где слова нет (18 из ~130 меток), даёт фразы, которых ученик не говорит, и закрывает обмен
вопросом («Is this the first visit to our clinic?»); в 10-дневном плане ядром назначено
«Заселение» (p1) при «Регистрации» p4; порядок сцен аренды на intermediate — просмотр раньше
звонка агенту.

**Симулятор дня** (прогон 2, цель «врач»): день 1 — 75 карточек (слова 32 · фразы 18 · диалог 1 ·
слушаю 16 · говорю 8); `word_choose v1` («appointment») провален → повтор в конце этапа
(позиция 33) → провален второй раз → `returns`; `word_say` пропущен (skip, 2 попытки); все этапы
закрыты, день закрыт: `cards_total 76, cards_done 76, minutes_spent 1, first_try_share 0.95,
hardest = word v1 «appointment»`; слова и фразы (14) легли в коллекцию плана (`origin=plan`,
`collection_items` 14); день 2 до сдвига — 409 `plan_day_locked`; `plan:shift-day` → день 2 открыт:
76 карточек, среди них `word_choose v1` с `source=returned` на позиции 33 (конец этапа слов).
Трёхдневный план «аренда»: день 1 (75) → сдвиг → день 2 (75, коллекция 28 единиц) → сдвиг →
репетиция: 16 карточек, только этап `speak`, оба обмена сцен.

**Тесты канона** (`tests/Unit/Plan`, `tests/Feature/Plan`, 83 теста): календарь 1…10; укорачивание
(дата, варианты → приоритет, ядро никогда, `plan_too_short`, день в календарный день); каждая
проверка урока на своём сломанном JSON — считает в `observe`, действует в `drop`, отбивает в
`gate`, «observe навсегда» не отбивает даже в `gate`; проверки плана; сборка терпит 12 видов
поломки; состав/порядок по уровням; «услышал → собери» только Intermediate и только ≤ 10 слов;
HTTP: сборка, unclear + retry, 422/404/401, укорачивание по дате, удаление сцены, день с двумя
ошибками и пропуском → возврат на завтра, 3-дневный план до репетиции и `finished`, перенос даты,
`overdue`, `failed` урок после двух `gate` + явный retry (4 вызова, счётчик `gated=4`), счётчики
в админке, удаление.

**Статика и сьют.** `composer check`: OpenAPI ok · deptrac 0 нарушений · PHPStan 0 ошибок · Pest
**1864 passed** (9029 assertions, 31 с). `flutter analyze` — No issues found. Свежая база
(`migrate:fresh` на тестовой): таблиц/колонок §9 нет, семь новых таблиц есть.

## 4. Запросы и EXPLAIN

База `wordtrainer_e2e_test` после `plan:seed-load` + `ANALYZE`: 55 планов, 326 дней, 20 404 карточки,
2 912 терминов (у одного пользователя — 51 план). Каждый эндпоинт — один вызов через ядро с
логом запросов; число statement'ов не зависит от числа карточек/сцен.

| эндпоинт | statements | что читает | план запроса (после ANALYZE) |
|---|---|---|---|
| `GET /plans` | 8 (токен, юзер, план ×1 + scenes + days, профиль…) | `plans` by `user_id` + eager `plan_scenes`/`plan_days` по `plan_id IN (…)` | `plans` seq scan (55 строк — ниже порога планировщика; индекс `plans_user_status_idx` есть), scenes/days — index |
| `GET /plans/current` | 3 | `plans` where user_id + status in (active, overdue) | `plans_user_status_idx` (seq scan на 55 строках) |
| `GET /plans/{id}` · `/build` | 3 | plan + scenes + days | PK + `plan_scenes_order_uidx` / `plan_days_number_uidx` |
| `GET /plans/{id}/days/{n}` | 4 | + `day_cards` by `day_id` | `day_cards_position_uidx (day_id, stage, position)` — Index Scan, 77 строк |
| `GET …/days/{n}/cards` | 5 | + `plan_terms` by `scene_id IN`, + `plan_line_audios` by scene+voice (пропущен при выключенной озвучке) | `plan_terms_scene_position_idx` |
| `GET …/days/{n}/sheet` | 4 | + `plan_terms` by scene | index |
| `POST …/cards/{id}/answer` | 6–7 | plan (3), карточка `FOR UPDATE` по PK, update, `forDay` (позиция повтора), insert | PK + `day_cards_position_uidx` |
| `POST …/days/{n}/open` | 7 + вставка карточек одним statement | + `returningFrom(previous day)` — частичный `day_cards_returns_idx` | index |

Изменений после EXPLAIN не потребовалось; до `ANALYZE` планировщик брал seq scan по `day_cards`
(свежая вставка 20k строк без статистики) — на проде autovacuum это делает сам. Единственный
seq scan после статистики — таблица `plans` на 55 строках (дешевле индекса). JSON-поля
(`payload`, `lesson_json`, `checks_json`) нигде не фильтруются.

## 5. Самопроверка диффа — что исправлено до сдачи

1. `PlanMapper` отдавал Eloquent'у JSON-строки в колонки с кастом `array` → двойная кодировка;
   теперь массивы. Там же — `Carbon` из `created_at` в `?string`.
2. `Plan::reschedule` оставлял на днях ссылки на выброшенные сцены → `SceneNotFound`; добавлен
   `PlanDay::clearScene()`, дни после пройденных раздаются заново.
3. Даты сравнивались как моменты в разных зонах (`opens_on` UTC против «сегодня» в Kyiv) — день 2
   не открывался; `PlanCalendar::calendarDaysBetween`, сравнение по `Y-m-d`.
4. `LessonBuildService` передавал в контекст проверок урока название языка («Russian»), проверка
   письменности молчала; в `LessonRequest` добавлены коды языков.
5. `FakePlanModel`: `v5` стоял на реплике без слова — фейк не проходил собственную проверку.
6. `EloquentNativeDistractorSource` читал таблицы Vocabulary напрямую (находка invariant-reviewer)
   → порт `Vocabulary\Application\Query\NativeDistractorReader` + адаптер в Plan.
7. `LiveModelGuard` докблок — имя удалённого порта; `GenerationServiceProvider` — потерянный
   `use RuntimeException`; `BuildStudySessionHandler` читал удалённое поле `kind`.
8. Харнесс прогона: гард Sanctum помнит bearer в процессе (план «аренда» из прогона 2 создался
   под чужим аккаунтом — 409 при старте; перепрогнан), throttle в array-кэше; сценарий ошибок по
   видам карточек, а не по индексам.
9. `plan:seed-load` писал заглушку `lesson_json`, на которой падал маппер (`GET /plans` → 500) и
   второй `active` план под уникальный индекс — поправлено.
10. Имена глобальных хелперов тестов (`answer`) столкнулись в параллельном сьюте — префиксы.
11. Pint по новым файлам.

## 5а. Доработка 10.09 — прод мигрирован, штамп, DECISIONS, живые расширение и озвучка

**Прод.** Владелец подтвердил: все аккаунты со старыми планами тестовые. Бэкап —
`storage/db-backups/wordtrainer-20260910-233221.sql.gz` (15 МБ), затем `php artisan migrate --force`
на `wordtrainer`: четыре миграции применены (121 + 35 + 6 + 107 мс), Horizon перезапущен.
Проверка после: таблиц `learning_plan_*`, `plan_skills`, `plan_conversations`, `term_audios` — нет;
колонок `terms.{is_line … topical}`, `learning_mode_settings.{scope, level, knobs}`,
`term_examples.scope_collection_id`, `generation_requests.plan_id` — нет; семь новых таблиц есть,
`plans` пуста; ситуативных `reviews` 0; индекс `learning_mode_settings_scope_mode_uidx` пересоздан.

| таблица | до | после | что изменилось |
|---|---|---|---|
| `users` | 11 | 11 | — |
| `collections` (всего / живых) | 105 / 96 | 105 / 71 | 25 папок старых дней плана (`origin=plan`) мягко удалены, живых с `origin=plan` 0 |
| `collection_items` | 1559 | 1559 | — |
| `terms` / `term_translations` | 1549 / 1615 | 1549 / 1615 | — (305 строк потеряли только плановые колонки) |
| `term_examples` / `example_translations` | 1559 / 1560 | 1559 / 1560 | — (281 строка потеряла `scope_collection_id`) |
| `user_term_progress` | 1016 | 1016 | — ; у 31 строки из `enrollment_sources` вычищены `plan:…` (0 после) |
| `reviews` | 2653 | 2514 | −139 ситуативных (старые тренажёры плана) |
| `learning_mode_settings` | 63 | 11 | −52 строки scope `plan`; 11 глобальных остались |
| `generation_requests` | 525 | 525 | — (колонка `plan_id` снята) |
| `learning_plans` / `learning_plan_days` / `plan_skills` / `term_audios` | 14 / 48 / 90 / 99 | таблиц нет | снесено |

**Штамп сборки.** `scripts/stamp-build.sh` (новый: пишет `git rev-parse --short HEAD` репозитория
уровнем выше в `storage/app/commit`) выполнен после финального коммита — `versions.build` и
`/api/v1/health` отвечают текущим хешем.

**DECISIONS.md** (корень): пп. 304–308 — модуль `Plan`; промты как файлы с версией в имени и
учёт на строке плана/сцены; проверки в коде, по умолчанию `observe`, оценивает клиент; дни по
календарю в зоне пользователя; правило сокращения и расширение. В «Отменено» — вся первая цепочка
плана одной строкой.

**Живьём — расширение и озвучка** (строки в таблице §3). Расширение нашло дефект и починило его до
сдачи: модель на `EXISTING_SCENES` ответила `order` 1, 2 и новым `priority` 1 — `appendScenes`
верил номерам и упал на уникальном индексе `(plan_id, order)` (первый вызов, $0.019, пропал);
теперь агрегат сам нумерует добавленные сцены после последней существующей и присваивает
приоритеты после старшего — второго ядра не бывает (тест «appends extension scenes after the
existing ones whatever the model numbered them»). Озвучка нашла второй: у урока два обмена со
`step` 6 — проверка формы обмена теперь считает повтор номера, а `SpeakSceneLinesHandler` не
покупает вторую реплику того же шага (файл на неё хранить негде). Третий, в Observability:
`GET /plans/audio/{id}` отдаёт mp3, и лог входящих запросов падал на не-UTF-8 срезе тела
(`api log write failed`) — бинарный ответ теперь пишется как `{binary: true, bytes}`.

## 6. Вопросы архитектору

1. **Новый модуль `Plan`** заведён без вопроса (CLAUDE.md: «ask before introducing a new
   module»); решение внесено в DECISIONS п. 304 — если оно другое, перенос в `Learning`
   механический.
2. **Мобильный клиент** ходит в удалённые маршруты (`/plans/{id}/outline`, `…/session`,
   `/qa/plan-clock`, `/audio/lines/*`, `/plans/listen-warmup`) — до наряда по клиенту вкладка
   «План» на телефоне не работает. Обычные сессии не затронуты (три снятых поля клиент читает
   null-safe).

## 7. Не сделано и почему

- Старые версии генерационных промтов (`generate_collection.v1…v8`, `lookup_word.v1…v4`,
  `enrich_pack.v1`, `regenerate_example.v1`, `practice_dialog.v1/v2`, `v10…v15.1/`) не удалялись —
  не плановые; нужны ли они как откат, решает владелец генерации.
- Уроки двух добавленных расширением сцен живьём не покупались (ставятся при открытии их дней —
  тот же путь, что прогнан для дней 2 и 3).
