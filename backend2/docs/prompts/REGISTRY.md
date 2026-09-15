# Реестр промптов — всё, что тратит деньги на модель

**Правило.** Ни один промпт не появляется в коде без строки в этой таблице. Новый промпт, новая
версия существующего, новый вызывающий — сначала строка здесь, потом код. Строка без промпта
(промпт удалён, адаптер выпилен) убирается тем же коммитом, что и код.

Это НЕ инвентарь файлов — файлы видно `ls`. Это ответ на вопросы, которых в коде не видно: **кто
платит, за что, сколько это стоит и что проверяет ответ**. Реестр читается перед тем, как заводить
ещё один вызов модели: половина строк ниже — уже существующий ответ на задачу, которую хочется
решить новым промптом.

Границы: сюда попадает всё, что **отправляет текст в языковую модель и платит по токенам**.
Не-модельные внешние API (Pexels, DeepL) — в отдельной таблице внизу, потому что путать их с
промптами дороже, чем перечислить.

Версии и модели ниже — **дефолты кода на 2026-09-15**; живое значение всегда в `config/services.php`,
`config/plan.php` и `.env`. Цена — ориентир за один вызов при типичном входе, по `ModelCost` (`Shared/Domain/Service`).

---

## Промпты

| id | файл / класс | версия | кто вызывает | когда | вход | выход | модель | цена ≈ | валидатор |
|---|---|---|---|---|---|---|---|---|---|
| **CORE** | `Prompt/v15.2/*` (секции), shape `Terms` · `ContentModelCollectionGenerator` | `v15.2` (`GENERATION_CORE_PROMPT_VERSION`) | `ProcessGenerationHandler` → `GenerationPipeline` | юзер жмёт «Создать коллекцию»; `POST /generations` | тема, пара языков, уровни CEFR, размер | список терминов: `text`, `type`, `translation`, `example(+перевод)`, `description`, `transliteration`, `cefr`, `image_api_prompt` | `gpt-5.4` (`GENERATION_CORE_MODEL`) | ~$0.05–0.12 на 15 терминов | `DraftValidator` + `LanguageBarrier` + `ContentContract` (JSON Schema, `strict`) |
| **CORE-legacy** | `generate_collection.v1…v9.md` · `OpenAiCollectionGenerator` | `v9` | тот же путь при `GENERATION_STACK=v1` | только откат | то же | то же | `gpt-4o` (`OPENAI_GENERATE_MODEL`) | ~$0.03 | `DraftValidator` |
| **MECH** | `Prompt/v14.3/*`, shape `Machinery` · `MachineryEnrichmentPacker` | `v14.3` (`GENERATION_MECHANICS_PROMPT_VERSION`) | `BuildTermEnrichmentsHandler` (станок), цепочкой после генерации и после `POST /search/add` | после каждой готовой коллекции; догон по каталогу | готовые карточки: термин, ключ, пример | принимаемые формы термина + «испорченные» версии примера (дистракторы `pick_correct`) | `gpt-4o-mini` (`GENERATION_MECHANICS_MODEL`) | ~$0.0004 на термин | **`EnrichmentValidator`** (`Generation/Domain`) + `ContentContract` |
| **PACK-legacy** | `enrich_pack.v1.md`, `enrich_pack.v2.md` · `OpenAiEnrichmentPacker` | `v2` (`services.generation.enrich_pack_prompt_version`) | тот же станок при `GENERATION_STACK=v1` | только откат | то же | варианты + дистракторы | `gpt-4o-mini` | ~$0.0004 на термин | `EnrichmentValidator` |
| **ENR-TERM** | `enrich_term.v1.md` · `OpenAiTermEnricher` | `v1` | `EnrichTermHandler` (`EnrichTermJob`) | точечное обогащение одного термина | один термин + его пример | варианты/дистракторы для одного термина | `gpt-4o-mini` (`OPENAI_ENRICH_MODEL`) | ~$0.0004 | `EnrichmentValidator` |
| **SYN** | секция синонимов внутри `v14…v14.2`, shape `Machinery` | снята в `v14.3` | — (продукт выключен: `GENERATION_WRITE_SYNONYMS=false`) | не вызывается | — | near-синонимы термина | `gpt-4o-mini` | — | `EnrichmentValidator::synonymsFor()` (анти-гипоним) |
| **READ** | `term_reading.v1.md` (обёртка) + **цитата секции** `Prompt/v15.2/21-extras.md` § `` `transliteration` `` · `OpenAiTermTransliterator` | обёртка `v1`, правила = версия CORE | `WriteTermReadingHandler` (`WriteTermReadingJob`) | «Собрать карточку», слово, набранное в папку | один термин + язык поддержки | транслитерация звучания буквами языка поддержки | `gpt-5.4` (модель CORE — сознательно) | ~$0.002 | `EnrichmentValidator::transliterationFor()` (алфавит + пунктуация) |
| **LOOKUP** | `lookup_word.v1…v6.md` · `OpenAiWordLookup` | `v5` (дефолт класса) | `LookupWordHandler` | платный поиск слова, `POST /search/lookup` | запрос юзера + пара языков | карточка слова: перевод, описание, пример, тип, другие чтения | `gpt-4o-mini` (`OPENAI_SEARCH_MODEL`) | ~$0.001 | `LookupBarrier` + `SearchDirection` + `LanguagePurity`; лимит `SearchLookupDailyLimit` (30/день) |
| **EX-REGEN** | `regenerate_example.v1.md`, `.v2.md` · `OpenAiExampleRegenerator` | `v2` | `RegenerateExampleHandler`; `RepairEchoExamplesJob` (QA-7, цепочкой после генерации) | пример совпал с термином / ручная перегенерация | термин + забракованный пример | один новый пример + перевод | `gpt-4o-mini` | ~$0.0003 | `ExampleReplacement` + `ContentChecks` |
| **REPAIR-TR** | `repair_translation.v1.md` · `OpenAiTranslationRepairer` | `v1` (`services.generation.repair_prompt_version`) | `RepairContentLanguageHandler` (консольный `content:repair-language`) | ремонт полей, где язык уехал (суржик, чужой алфавит) | поле + объявленный язык | починенный перевод | `gpt-4o-mini` | ~$0.0003 | `LanguagePurity` |
| **DIALOG** | `practice_dialog.v1…v3.md` + `PracticeDialogInstructions` · `OpenAiRealtimeTokenMinter`, `GeminiLiveTokenMinter` | `v3` (`PRACTICE_PROMPT_VERSION`) | `StartPracticeDialogHandler` | «Разговорная практика» (премиум), `POST /practice/dialogs` | урок: коллекция, целевые слова, уровень | не JSON — `instructions` realtime-сессии (голос) | `gpt-realtime-2.1-mini` / `gemini-3.1-flash-live-preview` | ~$0.10–0.30 за диалог (аудио) | нет схемы; покрытие считает `DialogCoverage` постфактум |
| **RECAP** | инлайн-инструкция в `OpenAiDialogSummarizer` (не файл) | — | `FinishPracticeDialogHandler` | конец разговорной практики | транскрипт диалога | короткий разбор для юзера | `gpt-4o-mini` (`OPENAI_SUMMARY_MODEL`) | ~$0.0005 | нет |
| **PLAN** | `Plan/Infrastructure/Prompt/plan-builder-v2.md` · `ContentModelPlanBuilder` | `plan-builder-v2` (= имя файла; промпт заморожен) | `BuildPlanHandler` (`BuildPlanJob`) | `POST /plans` (превью), `POST …/build/retry`, расширение плана (`PATCH …/schedule` с бо́льшим числом дней — с `EXISTING_SCENES`) | `GOAL`, `TARGET_LANGUAGE`, `NATIVE_LANGUAGE`, `LEVEL`, `SCENES_COUNT` (считает сервер по календарю), `EXISTING_SCENES` | `status: ok` + `plan{title_native, title_target, event_native, until_phrase_native, overdue_native, cover_image_prompt, learner_role_*}` + `scenes[]` (`order`, `kind`, `priority`, `title_*`, `teaches_native`, `goals_native[]`, роли, `topic_description` пятью частями, `image_prompt`) — или `status: unclear` + `unclear_reason` | `gpt-5.4` (`PLAN_BUILDER_MODEL`), таймаут 90 с | ≈ $0.02–0.05 | `PlanSchemas::plan()` (strict) + `BlueprintParser` (форма) + **`BlueprintChecker`** (`plan_shape`, `priorities`, `topic_parts`, `goals_count`, `char_limits`; режимы в `config/plan.php`, все `observe`); один ретрай |
| **LESSON** | `Plan/Infrastructure/Prompt/lesson_day.v4.4.md` · `ContentModelPlanBuilder` | `lesson_day.v4.4` (= имя файла; промпт заморожен). История: `lesson_day.v4.3` — принят из `docs/prompts/incoming/lesson-generator-v4-frames.md` 15.09 байт-в-байт (`14a4d05e`); `v4.4` — правка владельца 15.09 (`f843f607`): вход с заказанным числом каркасов снят, каждая реплика ученика в `answer`/`ask` стоит на каркасе, каркас может стоять в двух обменах с разными наполнениями (`in_dialogue: true` у обоих), каркасов — от половины до всех answer/ask-обменов, `rescue` без каркаса | `BuildLessonHandler` (`BuildLessonJob`) | план стал `ready` (урок дня 1 — до «Начать»); открытие/закрытие дня N (урок следующего дня-сцены); `POST …/scenes/{id}/lesson/retry` | `TOPIC` (= `title_native` сцены), `TOPIC_DESCRIPTION` (бриф сцены + `About the learner, in their own words: <цель плана>`), языки, `LEVEL`, `LEARNER_GENDER` (`profiles.gender` на момент генерации, пусто → `unknown`), `VOCABULARY_COUNT`/`DIALOGUE_COUNT` (`config/plan.php`, 8/8) | `topic`, `learner_role`, `role_gender`, `dialogue[]` (обмены `answer`/`ask`/`rescue`, по два сообщения, реплика ученика — `phrase_id` + `filler` + текст, `check` на обмен), `phrases[]` (каркас: `frame_target`/`frame_native`/чтение с `___`, `kind`, `slot{hint_native, fillers[{target, native, pronunciation_native, in_dialogue}]}`), `listening.questions[]`, `vocabulary[]` (`used_in`) | `gpt-5.4` (`PLAN_LESSON_MODEL`), таймаут 90 с | ≈ $0.07–0.08, 25–33 с (GEN-2a: 6 дней) | `PlanSchemas::lesson()` (strict, enum ссылок, без длин списков) + `LessonParser` (форма) + **`LessonValidator`** (46 кодов с адресом карточки, `docs/plan-v2.md` §4) + **порог** (`LessonGate`: 5 фатальных кодов — P2R по карточке, не больше двух на день, дальше `failed` с кодом; остальные — предупреждения); сервер собирает реплики из каркасов и перемешивает места верных ответов (§3а); один ретрай урока — только по схеме |
| **P2R** | `Plan/Infrastructure/Prompt/lesson_card_repair.v1.md` (обёртка) + **цитата разделов** `lesson_day.v4.4` по виду карточки (каркас: LEVEL, FRAMES, TEXT QUALITY, PRONUNCIATION_NATIVE; реплика: LEVEL, EXCHANGE KINDS, MOBILE-FRIENDLY MESSAGE LENGTH, LEARNER MESSAGES, TEXT QUALITY, PRONUNCIATION_NATIVE; check: LEVEL, CHECK PER EXCHANGE; listening: LISTENING) · `ContentModelPlanBuilder` | `lesson_card_repair.v1` | `LessonCardRepairer` ← `LessonGateKeeper` (сборка урока) и консоль `plan:repair-card` | **сборка сама** — для карточки с фатальной находкой, до записи урока, не больше двух карточек на день (решение архитектора после GEN-2a); **команда сессии** — для предупреждений сохранённого урока | адрес и вид карточки (`p3`, `B3`, `x3.check`, `L2`), находки валидатора у неё (код + английская причина), карточка и урок целиком, языки, `LEVEL`, `LEARNER_GENDER` | `{card}` в форме карточки | модель урока | ≈ $0.02, 2–3 с | `PlanSchemas::lessonCard()` (strict) + `LessonParser::card()` (форма) + `LessonValidator` по всему уроку после вставки; в сборке — записывается прошедший порог урок, командой — только с `--apply` и только до раздачи дня |

### Не промпты, но платные внешние вызовы

| id | класс | кто вызывает | когда | цена | заметка |
|---|---|---|---|---|---|
| **IMG** | `PexelsImageSearch` | `AttachImagesJob`; `AttachPlanImagesJob` (план: обложка, фото сцен маршрута); `IllustrateSceneJob` (день плана: фото сцены и всех слов, `searchMany` — пул 6) | после каждой готовой коллекции; после сборки плана; сразу после каждого урока (день `ready` ждёт фото, DAY-UI-3) | бесплатно (200 req/ч) | **своего промпта нет**: поисковый запрос `image_api_prompt` производит CORE; для плана — `cover_image_prompt` / `image_prompt` строителя и урока, а у слова без описания — «термин, тема сцены» (голое слово не спрашивается, DAY-UI-3). |
| **TTS** | `GeminiSpeechSynthesizer` (`speakScript`: один запрос на сценарий, диалог — `multiSpeakerVoiceConfig`, звук режет `PcmTurnCutter`) / `OpenAiSpeechSynthesizer` (строка за строкой) | `VoiceSceneJob` (план: всё, что звучит в дне — реплики обоих, фразы, слова) | сразу после каждого урока, если `SPEECH_ENABLED`; `plan:speak-backfill` | за секунды звука (`SpeechCost`), ≈ $0.015–0.02 на день | не модель текста; два голоса пакета — `generation.speech.voices.<lang>.{female,male}`; файл один на (сцена, ссылка единицы `x3`/`x3b`/`p2`/`v5`, голос); ≤ 4 запросов на день (лимит бесплатного Gemini — 10/мин, 100/сутки) |
| **DEEPL** | `DeepLTranslator` | `InstantTranslateHandler` | мгновенная подсказка в поиске | по символам, бюджет `TranslationMonthlyBudget` | не модель, детерминированный переводчик |
| **PLAYGROUND** | `PlaygroundCall` | `POST /admin/api/playground/generate` | админка, ручной эксперимент | по факту | текст промпта **набирает человек** — версии нет по определению; поэтому и строки с версией нет |

---

## Учёт трат плана

Плановые вызовы не пишут в `generation_requests`: цена, задержка, число попыток, версия промпта и
**версия сборки сервера** лежат на самой строке — `plans.cost_usd_plan` / `latency_ms_plan` /
`attempts_plan` / `prompt_version_plan` / `build_version` и `plan_scenes.cost_usd_lesson` /
`latency_ms_lesson` / `attempts_lesson` / `prompt_version_lesson` / `build_version`. Цена — сумма
всех попыток (отбитая попытка тоже оплачена). Попыток у обоих вызовов не больше двух: ретрай
покупается только невалидным JSON/схемой или проверкой в режиме `gate`; вторая неудача = `failed`,
третий вызов покупает только явный запрос клиента (`…/build/retry`, `…/lesson/retry`). Лог вызовов
помечает их `purpose = 'plan'` (`api_request_logs`) — это лог, а не реестр.

Счётчики проверок — `plan_check_counters` по версии промпта (у урока — коды валидатора: `counted` — каждая находка,
`gated` — фатальная, задержавшая день, `failed` — оставшаяся при `failed`); находки каждого ответа — `checks_json` на
плане/сцене. Починка P2R дописывает свою цену (и время) в `plan_scenes.cost_usd_lesson` / `latency_ms_lesson` (попыток не
прибавляет): в сборке — всегда, командой — только при `--apply`; без него — только лог запросов. Админка: `GET /admin/api/plans/checks`; строка `plan` в
`GET /admin/api/costs` — сумма двух колонок выше.

История старых плановых промптов (P1 v0.1…v0.4.2, P2 v0.1…v0.8, P2J, P2P, P2R v0.1…v0.3, P-Listen)
снята нарядом PLAN-GEN (2026-09-10) вместе с файлами и кодом; она осталась в git до `7bb1c784` и в
`docs/research/`. Урок до каркасов снят нарядом GEN-2a (2026-09-15) вместе с кодом, данными и тестами —
в git до `f843f607`. P2R этого реестра (`lesson_card_repair.v1`) — новый промпт урока «каркасы», со старой
починкой дня его роднит только задача.

## Как читать колонки

- **версия** — то, чем стамповано содержимое (`prompt_version` на строке контента). Бамп версии
  промахивается мимо кэша промпта и заставляет перегенерацию — это его вторая работа.
- **валидатор** — **детерминированный** код, который судит ответ. «нет» в этой колонке значит, что
  ответ принимается на слово; это допустимо (RECAP — текст для человека), но должно быть видно.
- **цена ≈** — один вызов при типичном входе. Умножать на число терминов/дней самому: план на 5 дней —
  это PLAN + 3×LESSON ≈ **$0.2–0.35** (три дня-сцены; повторение и репетиция модель не зовут).

## Что реестр НЕ отвечает

Какая версия сейчас в бою на проде — это `.env`. Какие секции входят в составную версию — это
`PromptLibrary::COMPOSED`. Почему промпт устроен так — это сам файл промпта и отчёт наряда,
который его написал (`docs/research/`, `docs/bakeoff-*`).
