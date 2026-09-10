# План — канон v2 (наряд PLAN-GEN, 2026-09-10)

> Это ЕДИНСТВЕННЫЙ канон плана на сервере. Старые `docs/plan-model.md`, `docs/plan-dialogue.md`,
> `docs/plan-map.md`, `docs/p1.*`, `docs/p2.*`, `docs/p-listen.*` удалены вместе со старой цепочкой
> (P1 → P2 → судья → починка → нарезка дня, лестница A/B/C, умения, чек-пойнты, готовность).
> Контракт API — `docs/plan-api.md`, машинно — `openapi/openapi.yaml` (тег `Plans`).
> Модуль — `app/Modules/Plan` (`README.md` там).

## 0. Что такое план

План — подготовка к одному событию за 1–10 дней. Цель своими словами + язык обучения + уровень
(`beginner` | `intermediate`) + число дней + (необязательно) дата события.

- **Один день знакомства = одна сцена = один вызов модели** (`lesson-v3`). Сцена — одно реальное
  взаимодействие с одним собеседником.
- **День = пять этапов** в фиксированном порядке: слова → фразы → диалог → слушаю и отвечаю →
  говорю сам. Состав дня известен заранее и целиком отдаётся клиенту при открытии.
- **Две модели-вызова на весь план**: строитель плана (`plan-builder-v2`) и генератор урока
  (`lesson-v3`). Всё остальное — детерминированный код: календарь, проверки, сборка карточек,
  возвраты, метрики, строки.
- Промпты лежат в `app/Modules/Plan/Infrastructure/Prompt/` и **заморожены**: версия = имя файла
  (`plan-builder-v2`, `lesson-v3`), правится файл → меняется имя → меняется версия. Сервер вырезает
  из файла раздел `TEST INPUT` и шлёт реальные входы отдельным сообщением.

## 1. Модель данных

Таблицы (`Plan/Infrastructure/Migration/2026_09_10_110000_create_plan_tables.php`), у каждой
строки — `user_id` владельца:

| таблица | что | ключевые поля |
|---|---|---|
| `plans` | план | `status` building·unclear·failed·ready·active·finished·overdue·deleted, `days_total`/`days_requested`, `event_date`, `level`, заголовки из промпта (`title_*`, `event_native`, `until_phrase_native`, `overdue_native`, роли), обложка, `prompt_version_plan`, `build_version`, `model_plan`, `cost_usd_plan`, `latency_ms_plan`, `attempts_plan`, `checks_json`, `collection_id`, `build_started_at` |
| `plan_scenes` | сцены плана | `order`, `kind` situation·variant, `priority` (1 = ядро), `title_native` ≤18 / `title_target`, `teaches_native` ≤34, `goals_native` 3–4×≤30, роли, `topic_description` (бриф урока), `image_prompt`+фото, `lesson_json` (ответ модели как есть), `lesson_status` pending·building·ready·failed, `prompt_version_lesson`, `build_version`, `model_lesson`, `cost_usd_lesson`, `latency_ms_lesson`, `attempts_lesson`, `checks_json`, `generated_at` |
| `plan_days` | календарь | `number`, `type` scene·review·rehearsal, `scene_id`, `status` locked·open·in_progress·closed, `opens_on`, `opened_at`, `closed_at`, метрики (`cards_total`, `cards_done`, `minutes_spent`, `first_try_share`, `hardest_unit_*`) |
| `day_cards` | карточки дня | `stage`, `position`, `kind` (13 видов), `payload` jsonb, `source` today·returned, `source_day_id`, `unit_kind`/`unit_ref`, `retry_of`, `result` null·passed·hinted·failed·skipped, `attempts`, `answered_at`, `returns` |
| `plan_terms` | слова/связки/фразы сцены | `kind` word·chunk·phrase, `ref` (`v3`/`p1`), тексты, чтение, определение, пример из диалога, `speaking_key`, `simplified_variants`, фото |
| `plan_line_audios` | озвучка реплик собеседника | `(scene_id, step, voice_key)` уникально, файл на приватном диске |
| `plan_check_counters` | счётчики проверок | `(prompt_version, check_name, action)` → `hits` |

Слова и фразы дня после закрытия дня уходят в коллекцию плана (`plans.collection_id`,
`collections.origin = 'plan'`, скрыта из «Мои коллекции») через `ImportTerm` + `AddTermToCollection`.

Чего **нет**: умений, чек-пойнтов, лестницы A/B/C, процента готовности, таблицы пар, судьи и
починки, флагов «старое/новое». Спасательный набор — статичный список из пяти фраз в
`config/plan.php` (`rescue_kit`), одинаковый для всех планов пары языков.

## 2. Вызовы модели

| вызов | входы (как в INPUTS промпта) | схема | таймаут | цена-ориентир |
|---|---|---|---|---|
| план (`plan-builder-v2`) | `GOAL`, `TARGET_LANGUAGE`, `NATIVE_LANGUAGE`, `LEVEL`, `SCENES_COUNT` (считает сервер по §5), `EXISTING_SCENES` (только при расширении) | `PlanSchemas::plan()`, strict | 90 с (`PLAN_BUILDER_TIMEOUT`) | ≤ $0.05 |
| урок (`lesson-v3`) | `TOPIC` = `title_native` сцены, `TOPIC_DESCRIPTION`, языки, `LEVEL`, `PHRASES_COUNT`/`VOCABULARY_COUNT`/`DIALOGUE_COUNT` из `config/plan.php` по уровню (6/8/8; PHRASES ≤ DIALOGUE) | `PlanSchemas::lesson()`, strict, сообщения A/B через `anyOf` | 90 с (`PLAN_LESSON_TIMEOUT`) | ≤ $0.10 |

- Модель и провайдер — `config/plan.php` (`PLAN_MODEL_PROVIDER`, `PLAN_BUILDER_MODEL`,
  `PLAN_LESSON_MODEL`); `PLAN_MODEL_DRIVER=fake` — детерминированный `FakePlanModel` (тесты, офлайн).
- Оба вызова — асинхронные джобы (`BuildPlanJob`, `BuildLessonJob`, `tries = 1`), клиент опрашивает
  `GET /plans/{id}/build` и `lesson_status` сцен. Джоба идемпотентна: сцена **захватывается**
  (`building`) в транзакции до вызова; повторная джоба находит захват и выходит; захват старше
  `build_stale_seconds` (240 с) считается мёртвым и перезахватывается.
- Цена каждого вызова считается из usage (`ModelCost`) и пишется на план/сцену вместе с
  `latency_ms`, `attempts`, версией промпта и **версией сборки сервера** (`APP_COMMIT` /
  `storage/app/commit`). Сумма всех попыток — в `cost_usd_*`.
- Ретрай — ровно один и только по невалидному JSON/схеме или по проверке в режиме `gate`; вторая
  неудача = `failed` (план) / `lesson_status = failed` (сцена), клиент может явно повторить
  (`POST …/build/retry`, `POST …/scenes/{id}/lesson/retry`). Отбитая попытка тоже оплачена и
  посчитана.
- `status: unclear` — легитимный ответ строителя: план получает `status = unclear` и
  `unclear_reason`, клиент показывает; можно повторить.

## 3. Когда что генерируется

1. `POST /plans` → план (превью). При `ready` **сразу** ставится урок дня 1 (до «Начать») и фото.
2. `POST …/start` → день 1 открыт с сегодняшнего дня.
3. Открытие дня N ставит урок **следующего дня-сцены**; закрытие дня N — тоже (если ещё не
   поставлен). Дни повторения и репетиции не генерируются — собираются при открытии.
4. Расширение (больше дней) = тот же промпт с `EXISTING_SCENES` и `SCENES_COUNT` = сколько добавить;
   новые сцены **дописываются** после существующих (`Plan::appendScenes`).

## 4. Проверки (`Plan/Domain/Check`)

Каждая проверка — класс с именем, у каждой три режима: `observe` (по умолчанию: посчитать и
пометить, ответ принимается как есть), `drop` (стереть сломанную метку/поле), `gate` (отказать,
один ретрай). Режим — по имени проверки в `config/plan.php` → `checks.lesson.*` /
`checks.plan.*`; все ушли в `observe`. Счётчики — `plan_check_counters`, видны в админке
(`GET /admin/api/plans/checks`) по версии промпта; находки каждого ответа — в `checks_json`
плана/сцены. Исключение: невалидный JSON / не по схеме = отказ модели → один ретрай, потом `failed`.

**Урок** (порядок = порядок запуска; проверка видит урок таким, каким его оставили предыдущие):

| имя | что | канон при `drop` |
|---|---|---|
| `counts` | phrases/vocabulary/dialogue ≠ заказанным | нечего стирать (канон: gate) |
| `exchange_shape` | не ровно 2 сообщения / первое ≠ инициатор | нечего стирать (канон: gate) |
| `second_message_question` | второе сообщение кончается «?» | нечего стирать (канон: gate) |
| `speaking_key_substring` | ключ не подстрока своего `text_target` | нечего стирать (канон: gate) |
| `pronunciation_script` | чтение с символами вне кириллицы/цифр/пунктуации/U+0301 (только кириллические родные) | стирает чтение |
| `variant_length` | вариант длиннее оригинала > чем на слово или равен ему | стирает вариант |
| `vocabulary_contained` | слово вложено в другое (`back` ⊂ `lower back`) | стирает короткое |
| `vocabulary_id_absent` | `vocabulary_id` на сообщении без термина (и без формы по стему) | стирает id |
| `phrase_id_absent` | `phrase_id`, где фраза не найдена по правилу «клей + местоимение» (`PhraseInMessage`) | стирает id |
| `phrase_unused` | фраза ни в одном сообщении B | стирает фразу |
| `message_length` | A > 18 слов, B > 10 | **observe навсегда** |
| `partner_statements` | < 3 утверждений A | **observe навсегда** |

**План**:

| имя | что | канон при `drop` |
|---|---|---|
| `plan_shape` | сцен ≠ `SCENES_COUNT`; `order` не 1..N (после существующих) | нечего стирать (канон: gate) |
| `priorities` | приоритеты не уникальны / не ровно один `1` | перенумеровать по порядку, ядро = первая `situation` |
| `topic_parts` | в брифе нет одной из пяти частей `Situation:` … `Not in this scene:` | нечего стирать (канон: gate) |
| `goals_count` | целей не 3–4 | лишние срезать; нехватка — посчитать |
| `char_limits` | title > 18, teaches > 34, goal > 30, title плана > 24 | **observe навсегда** (клиент обрезает) |

Ранжирование важности от модели не используется — приоритеты нужны только правилу укорачивания §5.

## 5. Календарь

`PlanCalendar::layout(days)`: 1 → `[scene]`; 2 → `[scene, scene]`; 3 → `[scene, scene, rehearsal]`;
≥ 4 → сцены, каждый третий день `review`, последний `rehearsal`. `SCENES_COUNT` = число дней-сцен.

- Дата события — метка и напоминание. Если до события меньше дней, чем просили, сервер укорачивает
  `days_total` до `daysUntil(today, event)` (1…10, день события не учебный); `days_requested` и
  `days_shortened_from` показывают, что укоротили.
- Дни открываются **по одному в календарный день** в зоне пользователя (`profiles.timezone`):
  день N+1 не раньше следующего календарного дня после закрытия N (`plan_days.opens_on`). Даты
  сравниваются как даты (`Y-m-d`), не как моменты.
- Укорачивание (`Plan::reschedule`): сначала уходят варианты (последний по порядку первым), потом
  ситуации с наибольшим номером приоритета; `priority = 1` не уходит никогда; дни, уже открытые или
  закрытые, не режутся (`plan_too_short`). Сцена, убранная в превью, оставляет день повторения,
  длина плана не меняется; ядро убрать нельзя.
- Событие прошло, план не закрыт → `overdue` (вычисляется, не хранится); «завершить» → `finished`;
  «перенести дату» = `PATCH …/schedule`.

## 6. Сборка дня (`Plan/Domain/Assembly`)

Детерминирована: перемешивания сидятся адресом карточки (`Shuffle::seeded`), повторная сборка даёт
те же плитки. Состав фиксируется при первом открытии и целиком отдаётся клиенту.

- **Слова** — 4 карточки на слово с разнесением (intro1, intro2, say1, intro3, say2, choose1 …):
  `word_intro` → `word_say` → `word_choose` → `word_cloze`. Выбор из 4: Beginner — перевод, ложные =
  переводы других слов дня (добор из каталога `NativeDistractorSource`, когда слов дня < 4);
  Intermediate — по `definition_target` выбрать слово. Cloze — пример слова с пропуском, варианты =
  слова дня; без примера или без слова в примере карточки нет.
- **Фразы** — 3 карточки: `phrase_intro` → `phrase_repeat` → `phrase_assemble` (плитки = слова фразы
  вперемешку + одно лишнее слово из словаря дня).
- **Диалог** — одна `dialogue_read` со всеми обменами по порядку и переводами; Intermediate —
  `translations_collapsed = true`.
- **Слушаю и отвечаю** — 2 карточки на обмен. Первая: Beginner — `listen_question` с вариантами
  по-русски; Intermediate — если A задаёт вопрос → `listen_question` на языке цели; утверждение ≤ 10
  слов → `listen_assemble` («услышал → собери», плитки A + одно лишнее); длиннее → `listen_question`.
  Вторая: инициатор A → `answer_choose` из 3 (своя + две реплики B других обменов дня с < 50 % общих
  слов); инициатор B → `answer_assemble` (собрать свою реплику из плиток, потом реплика A).
- **Говорю сам** — `speak` на обмен: реплика A звучит (`audio_id`), задание = `text_native`
  реплики B; зачёт по `speaking_key` или ≥ 70 % слов любой из форм (`expected` / `variants`);
  подсказка 1 = ключ, подсказка 2 = текст (`result = hinted`). Задание всегда `text_native`
  реплики ученика.
- **Ошибки**: первый `failed` — карточка возвращается в конец этапа новой позицией (`requeued`,
  `retry_of`); второй — остаётся `failed`, `returns = true`. Say/repeat/speak: две попытки без
  зачёта → `skipped` = провал без последствий.
- **Возврат**: следующий день знакомства получает провалившиеся дважды единицы предыдущего дня
  в своих этапах с `source = returned` (одна карточка на единицу: слово → `word_choose`, фраза →
  `phrase_assemble`, обмен → `speak`), в конце этапа после сегодняшних.
- **Повторение**: этап Слова (возвраты двух предыдущих дней-сцен) + Говорю сам (все обмены двух
  предыдущих сцен). **Репетиция**: один этап Говорю сам по всем сценам плана.
- **Терпимость**: ключ не в реплике → без подчёркивания (карточка есть); чтение чужим алфавитом →
  как есть; второе сообщение с «?» → обычный обмен; фраза не в репликах → только в этапе фраз;
  id слова не в тексте → без подсветки; обмен с одним сообщением → не попадает в слушание и речь
  (в диалоге остаётся).
- **Метрики** (`DayMetricsCalculator`): `cards_done`, `minutes_spent` (по `answered_at`, паузы >
  10 мин не считаются), «верно с первого раза» = passed без подсказки и attempts ≤ 1 среди
  оцениваемых карточек, «самое трудное» = единица с максимумом попыток.

## 7. Фоновые работы

- **Фото** (`AttachPlanImagesJob`, `PexelsPlanImageFinder` → `ImageSearchPort`): обложка плана по
  `cover_image_prompt`, сцены по `image_prompt`, слова/связки по `image_prompt` (null = без фото).
  Best effort, ретрай только на transient; день не ждёт.
- **Озвучка** (`SpeakSceneLinesJob`, `GenerationLineSpeaker` → `SpeechSynthesizerPort` +
  `VoiceCatalog`): реплики A премиум-голосом языкового пакета, если `SPEECH_ENABLED`; слова, фразы,
  реплики B — системный голос телефона. Файл один на (сцена, шаг, голос); отдаётся
  `GET /plans/audio/{id}`. Ничего не блокирует.

## 8. Строки

Все динамические, склоняемые тексты приходят готовыми (`NativeStrings`): `until_phrase`
(«До приёма · 5 дней»), `overdue_native` (из промпта), слот дня («сегодня», «завтра», дата),
`route_summary» («5 дней · 3 ситуации, 1 повторение, репетиция»). Даты и числа — сырьём.

## 9. Конфиг (`config/plan.php`)

`model.*` (драйвер, провайдер, две модели, два таймаута), `build_stale_seconds`, `counts` по
уровню, `checks.lesson.*` / `checks.plan.*` (режимы), `rescue_kit`, `audio_disk`.

## 10. Инструменты QA

- `php artisan plan:shift-day {plan} --days=N` — сдвинуть даты плана в прошлое (симулятор
  «наступил следующий день»); на `wordtrainer` отказывает без `--force`.
- `php artisan plan:seed-load {user} --plans=50` — синтетическая нагрузка для EXPLAIN (только на
  тестовой базе).
- `FakePlanModel` — валидные план/урок без сети; замыкание в конструкторе даёт сломанный ответ.
