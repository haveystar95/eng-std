# Реестр промтов

История версий — `git log` по пути файла (`git log --follow -- <путь>`).

## Plan

| имя | версия | путь | sha256 | с коммита | кто вызывает | модель |
|---|---|---|---|---|---|---|
| `plan-builder` | `plan-builder-v2.1` | `app/Modules/Plan/Infrastructure/Prompt/current/plan-builder-v2.1.md` | `23b1900d5b19044bfd6bb269aa59790ef4b9845229c32d711e8018d50637eb13` | `GEN-4` | `BuildPlanHandler` (`BuildPlanJob`); слово сверх лимита чинит `plan_line_repair` | `gpt-5.4` (`plan.model.purposes.plan`, `PLAN_BUILDER_MODEL`) |
| `plan_line_repair` | `plan_line_repair.v1` | `app/Modules/Plan/Infrastructure/Prompt/current/plan_line_repair.v1.md` | `7ee7a58bf53a64ceeaa13f7907d27b588464c4dae9a646f2789668009d48868f` | `GEN-4` | `PlanLineRepairer` ← `PlanBuildService` (строка плана длиннее лимита, по одной строке) | `gpt-5.6-luna` (`PLAN_LINE_REPAIR_MODEL`) |
| `lesson_skeleton` | `lesson_skeleton.v1.1` | `app/Modules/Plan/Infrastructure/Prompt/current/lesson_skeleton.v1.1.md` | `04b69d4121d713a20d982b0bcfbf560ee03f0f76b4309e55b142a57d55a0e938` | `GEN-4b` | `LessonBuildService` ← `BuildLessonHandler` (`BuildLessonJob`), этап 1 | `gpt-5.4` (`PLAN_SKELETON_MODEL`) |
| `lesson_dialogue` | `lesson_dialogue.v1.1` | `app/Modules/Plan/Infrastructure/Prompt/current/lesson_dialogue.v1.1.md` | `eaf34ffeaabf8d264235fcd9e96efd6723cb8927d2711afc3d28dda5af27604e` | `GEN-4b` | `LessonBuildService` ← `BuildLessonHandler` (`BuildLessonJob`), этап 2 | `gpt-5.4` (`PLAN_DIALOGUE_MODEL`) |
| `lesson_card_repair` | `lesson_card_repair.v1.5` | `app/Modules/Plan/Infrastructure/Prompt/current/lesson_card_repair.v1.5.md` | `646415c370233fd57361fdbb9b08c25318e3b42190e4b920c4479b38ffc652a0` | `GEN-4` | `LessonCardRepairer` ← `LessonBuildService`; обёртка, в `{{rules}}` — разделы скелета или диалога по виду карточки (`PlanPromptFiles::REPAIR_SECTIONS`) | `gpt-5.6-luna` (`PLAN_REPAIR_MODEL`) |
| `lesson_seam_judge` | `lesson_seam_judge.v1.3` | `app/Modules/Plan/Infrastructure/Prompt/current/lesson_seam_judge.v1.3.md` | `70cfd12ac19b24d09a89dac16f80a8d18af1854c21bcf5f2afa13a8d14b008fe` | `GEN-4c-2` | `LessonSeamJudge` ← `LessonBuildService` (родные рамки скелета до диалога; в том же вызове — называет ли ответ собеседника на вопрос ученика наполнение, `partner.names_filler_meaning`, вердикт на каждую реплику) | `gpt-5.4-mini` (`PLAN_SEAM_JUDGE_MODEL`) |
| `slot_judge` | `slot_judge.v3` | `app/Modules/Plan/Infrastructure/Prompt/current/slot_judge.v3.md` | `035411394c79d4e6f3d814f96933eecf2f7b56323e32f2cd26525cff26d7b6e4` | `ffad4461` | `SlotJudge` ← `JudgeCardHandler` | `gpt-5.4-mini` (`PLAN_SLOT_JUDGE_MODEL`) |
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
