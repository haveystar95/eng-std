# Реестр промтов

История версий — `git log` по пути файла (`git log --follow -- <путь>`).

## Plan

| имя | версия | путь | sha256 | с коммита | кто вызывает | модель |
|---|---|---|---|---|---|---|
| `plan-builder` | `plan-builder-v2` | `app/Modules/Plan/Infrastructure/Prompt/current/plan-builder-v2.md` | `4182c70234175408650a3afffdc3c72be5be1acbb85a1a43f9105933bbf4ed34` | `5b2809c1` | `BuildPlanHandler` (`BuildPlanJob`) | `gpt-5.4` (`PLAN_BUILDER_MODEL`) |
| `lesson_day` | `lesson_day.v4.10` | `app/Modules/Plan/Infrastructure/Prompt/current/lesson_day.v4.10.md` | `32df0f97227f4d248e393ca633644b78c12f79536bfab2b5da75444cf6a03fcd` | `ea1b49c8` | `BuildLessonHandler` (`BuildLessonJob`) | `gpt-5.4` (`PLAN_LESSON_MODEL`) |
| `lesson_card_repair` | `lesson_card_repair.v1.4` | `app/Modules/Plan/Infrastructure/Prompt/current/lesson_card_repair.v1.4.md` | `f236bc0e3837de024877bd354f7c5a34a05bf98d711f717904bf2d6366bb50b9` | `2b43e119` | `LessonCardRepairer` ← `LessonGateKeeper` (сборка урока) и консоль `plan:repair-card`; обёртка, в `{{rules}}` — разделы `lesson_day` по виду карточки (`PlanPromptFiles::REPAIR_SECTIONS`) | `gpt-5.4` (`PLAN_REPAIR_MODEL`) |
| `lesson_seam_judge` | `lesson_seam_judge.v1.1` | `app/Modules/Plan/Infrastructure/Prompt/current/lesson_seam_judge.v1.1.md` | `f5da38784e0761a925f77259913042a26741eee2933cc84c663d89ba00fb357d` | `093ad997` | `LessonSeamJudge` ← `LessonBuildService` | `gpt-5.4-mini` (`PLAN_JUDGE_MODEL`) |
| `slot_judge` | `slot_judge.v3` | `app/Modules/Plan/Infrastructure/Prompt/current/slot_judge.v3.md` | `035411394c79d4e6f3d814f96933eecf2f7b56323e32f2cd26525cff26d7b6e4` | `ffad4461` | `SlotJudge` ← `JudgeCardHandler` | `gpt-5.4-mini` (`PLAN_JUDGE_MODEL`) |
| `conversation_agent` | `conversation_agent.v3.4` | `app/Modules/Plan/Infrastructure/Prompt/current/conversation_agent.v3.4.md` | `8d8c414e6656d3911de55da4a65d42c52b1f3b23102b2f3c617e3b6b2874387d` | `82fb4896` | `ConversationAgent` ← `ConversationMoves` ← `StartConversationHandler` / `TakeConversationTurnHandler` | `gpt-5.4-mini` (`PLAN_CONVERSATION_MODEL`) |

Расходы — журнал `model_calls` и `plan:speak-report`.

## Generation

**Порядок — отдельным нарядом PROMPTS-2.** Раздел оставлен как был до наряда PROMPTS-1: версии и модели — дефолты кода на
2026-09-25, живое значение — `config/services.php` и `.env`; какие секции входят в составную версию — `PromptLibrary::COMPOSED`.

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

## Платные вызовы вендоров, не промты

| вендор | кто вызывает | за что платим |
|---|---|---|
| Pexels (`PexelsImageSearch`) | `AttachImagesJob`, `AttachPlanImagesJob`, `IllustrateSceneJob` | поиск фото: коллекции, обложка и сцены плана, слова дня — бесплатный тариф (200 запросов/ч) |
| ElevenLabs (`ElevenLabsSpeechSynthesizer`) | `VoiceSceneJob`, `plan:speak-backfill` | озвучка дня плана — символы (кредиты × цена кредита тарифа) |
| DeepL (`DeepLTranslator`) | `InstantTranslateHandler` | мгновенная подсказка перевода в поиске — символы, бюджет `TranslationMonthlyBudget` |
| модель песочницы (`PlaygroundCall`) | `RunPlaygroundCallJob` ← `POST /admin/api/playground/generate` | ручной эксперимент в админке — токены модели по факту; текст набирает человек |
