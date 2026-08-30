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

Версии и модели ниже — **дефолты кода на 2026-08-30**; живое значение всегда в `config/services.php`
и `.env`. Цена — ориентир за один вызов при типичном входе, по `ModelCost` (`Shared/Domain/Service`).

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
| **P1** | `Prompt/plan_outline.v0.1.1.md` · `PlanOutlineService` | `v0.1.1` | `BuildPlanOutlineHandler` (`POST /plans/{id}/outline`) | юзер собирает Learning Plan | цель, дата события, пара языков, уровень, минуты/день, число дней (**считает сервер**) | каркас плана: `title`, `goal_restated`, `entities[]`, `constraints[]`, `goal_terms[]`, `days[]` (умения, роль, чек-пойнты, темы, бюджет), `final_day` | `gpt-5.4` | ~$0.021 | **`PlanOutlineValidator`** (`Generation/Domain`) |
| **P2** | `Prompt/plan_day.v0.1.1.md` · `PlanDayComposer` | `v0.1.1` | `GeneratePlanDayHandler` (`GeneratePlanDayJob`) | день плана переходит `pending → generating` | день каркаса + `entities`/`constraints`/`goal_terms` + известные термины юзера + бюджет (`term_budget`/`phrases`/`words` — **точные числа сервера**) | `phrases[]` (реплики, `is_line: true`) + `words[]` (подстановки) + `known[]` (только примеры) | `gpt-5.4` | ~$0.035 (9 терминов) / ~$0.050 (16) | **`PlanDayValidator`** (`Generation/Domain`) |

### Не промпты, но платные внешние вызовы

| id | класс | кто вызывает | когда | цена | заметка |
|---|---|---|---|---|---|
| **IMG** | `PexelsImageSearch` | `AttachImagesJob` | после каждой готовой коллекции | бесплатно (200 req/ч) | **своего промпта нет**: поисковый запрос `image_api_prompt` производит CORE. Меняется CORE — меняются картинки. |
| **DEEPL** | `DeepLTranslator` | `InstantTranslateHandler` | мгновенная подсказка в поиске | по символам, бюджет `TranslationMonthlyBudget` | не модель, детерминированный переводчик |
| **PLAYGROUND** | `PlaygroundCall` | `POST /admin/api/playground/generate` | админка, ручной эксперимент | по факту | текст промпта **набирает человек** — версии нет по определению; поэтому и строки с версией нет |

---

## История версий плановых промптов

| версия | что изменилось | причина |
|---|---|---|
| `v0.1.1` (30.08) | из P1 убран `recommended_days`; бюджет дня из полосы (8–10 / 16–18) стал точным числом (5 / 9 / 16), P2 получает его как заданное; потолок чек-пойнтов однодневного плана — 5 | **код, а не вкус.** `recommended_days` не проверял никто, а его критерий проходился укрупнением умения. Бюджет: длину дня выбирает юзер минутами, `PlanScheduler` считает из них ёмкость, а `PlanDayValidator` считает карточки против неё — полоса в промпте означала, что три числа расходятся по построению |
| `v0.1` (29.08) | `entities` / `constraints` / `goal_terms`; `phrases[]` отдельно от `words[]`; `type` + `is_line` вместо `kind`; `final_day.checkpoints` собирает сервер | ресёрч песочницы, `docs/research/plan-sandbox-2026-08-29.md` |

## Учёт трат

Оба плановых промпта пишут строку в `generation_requests` с `purpose = 'plan'` и `plan_id` —
в тот же реестр, где живут траты станка коллекций, и на КАЖДУЮ попытку, включая отбитую
валидатором. Отказ записи не проглатывается: `Log::error` и исключение наверх
(`PlanSpendNotRecorded`).

Правило появилось не из осторожности: на прогоне PLAN-1a три платных вызова `gpt-5.4` прошли мимо
всякого учёта, потому что CHECK лога отбил `purpose = 'plan'`, а слушатель поймал отказ и выбросил
его — правильно для ЛОГА. Лог — это лог; реестр — это строка, без которой работа не идёт дальше.
Подробности: `docs/plan-1a-run.md`.

## Как читать колонки

- **версия** — то, чем стамповано содержимое (`prompt_version` на строке контента). Бамп версии
  промахивается мимо кэша промпта и заставляет перегенерацию — это его вторая работа.
- **валидатор** — **детерминированный** код, который судит ответ. «нет» в этой колонке значит, что
  ответ принимается на слово; это допустимо (RECAP — текст для человека), но должно быть видно.
- **цена ≈** — один вызов при типичном входе. Умножать на число терминов/дней самому: план на 3 дня
  по 20 минут — это P1 + 2×P2 ≈ **$0.09**, и это до обогащения.

## Что реестр НЕ отвечает

Какая версия сейчас в бою на проде — это `.env`. Какие секции входят в составную версию — это
`PromptLibrary::COMPOSED`. Почему промпт устроен так — это сам файл промпта и отчёт наряда,
который его написал (`docs/research/`, `docs/bakeoff-*`).
