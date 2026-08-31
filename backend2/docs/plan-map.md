# Карта Learning Plan — справочник по коду

**Обязательное чтение перед любым нарядом про план.** Здесь только факты из кода со ссылками на
классы. Почему так устроено — в докблоках этих классов и в `docs/DECISIONS.md`; живые прогоны — в
`docs/research/plan-*-run.md`; кто платит за какой промпт — в `docs/prompts/REGISTRY.md`.

Проверено по коду на 01.09.2026.

---

## 1. Путь плана

| шаг | класс | что решает |
|---|---|---|
| цель → каркас | `Learning/Application/Command/BuildPlanOutlineHandler` → `Generation/Application/Service/PlanOutlineService` | вызов **P1** |
| повтор каркаса | `PlanOutlineService::__invoke()` | ровно **две** попытки ВНУТРИ одного вызова; вторая получает нарушения первой; обе в `generation_requests` |
| разбор ответа | `Generation/Domain/Service/PlanOutlineValidator` | фатальные → отбой; warning → счётчик (§3) |
| вместимость дня | `Learning/Domain/Service/DayCapacity::forMinutes()` | 10 мин → 7, 20 → 14, 40 → 24; между точками прямая |
| три числа для P2 | `DayCapacity::split()` | `phrases = ceil(0.55×cap)`, `chunks = max(1, floor(0.15×cap))`, `words` — остаток; 14 → 8/2/4 |
| упаковка умений в дни | `Learning/Domain/Service/PlanScheduler::compute()` | `need`, `capacity`, `fits`, `dropped`, `dropReason`; `MAX_INTRO_DAYS = 14`, `MAX_STEP = 3` |
| пересчёт после правки | `PlanScheduler::recheck()` | что ещё влезает и какие умения ушли бы |
| когда писать день n+1 | `Learning/Domain/Service/PlanGenerationPolicy` | `EAGER_INTRO_DAYS = 3` (короткий план пишется вперёд), `MAX_READY_AHEAD = 2`; `nextAfterReady()` / `nextAfterDone()` |
| захват дня | `Learning/Application/Command/ClaimPlanDayHandler` → `PlanDay::claim()` | одна транзакция, `SELECT … FOR UPDATE`; отказ, если день занят/готов/бюджет исчерпан |
| материал дня | `Generation/Application/Service/PlanDayComposer::compose()` | вызов **P2** |
| суд над днём | `PlanDayComposer::judge()` | `PlanDayValidator` + `PlanCoherenceValidator`, одним вердиктом, с нуля |
| починка | `Generation/Application/Service/PlanDayRepairer::repair()` | **порог**: `count(сломанных) ≤ floor(всего × 1/2)` И у каждого нарушения есть адрес. Иначе `null` — починки нет |
| после починки | `PlanDayComposer::compose()` | слияние по (`array`, `index`) → `judge()` заново целиком. Второго P2R нет никогда |
| повтор ЦЕЛОГО дня | `Learning/Application/Command/FinishPlanDayHandler` | только если после `markFailed()` статус вернулся в `pending`, то есть остался бюджет. Сообщение — только адреса последней попытки (`PlanDayComposer::retryMessage()`) |
| бюджет дня | `Learning/Domain/Entity/PlanDay::MAX_ATTEMPTS = 2` | считает **платные вызовы**: `claim()` берёт 1, `markFailed(paidCalls: 2)` доначисляет второй, когда был P2R |
| запись дня | `Generation/Application/Command/GeneratePlanDayHandler::materialize()` | коллекция + термины + примеры дня, одна транзакция |
| готов | `PlanDay::markReady()` | `ready`, `fail_reason` и `generation_violations` очищаются |
| конвейер | `GeneratePlanDayHandler::__invoke()` | `DispatchesExampleRepair::repairThenEnrich()` — починка эхо-примеров, ЗАТЕМ станок `BuildTermEnrichmentsHandler` (`VERSION = mech-v14.3`: принимаемые формы + **дистракторы**); отдельно `DispatchesImageAttachment::dispatch()` — **картинки** (Pexels по `image_api_prompt`). Fire-and-forget, день уже пригоден |
| сессия дня | `Learning/Application/Command/BuildPlanSessionHandler` | §2 |
| день пройден | `Learning/Application/Service/PlanDayPassing::mark()` | `passed` = все слова дня закрыли ступень A. Вызывается из `CompleteStudySessionHandler` (конец посадки) и из сборки следующей сессии |
| следующий день | `PlanDayPassing::mark()` → `PlanGenerationPolicy::nextAfterDone()` | ставится в очередь только на переходе в `done` |
| финальный день | `Learning/Domain/ValueObject/PlanDayKind::Final` | материала не имеет: `PlanDay::claim()` возвращает `false` |
| окончание | `EndPlanHandler` (`PlanEnding::Pause` / `Abandon` / `Complete`), `RecordPlanFeedbackHandler` | `PlanEnding::keepsHold()` — только `Pause` держит слова |

**Что уходит в пул при окончании** (`Abandon`/`Complete`, порядок обязателен):

1. `PlanTermReleaser::unenrolUntouched()` — пары, у которых `enrollment_sources` РОВНО `["plan:<id>"]`
   и НЕТ ни одной строки в `reviews`, покидают пул (`enrolled_at = NULL`).
2. `PlanTermReleaser::releasePlan()` — со всех остальных снимается `plan:<id>`; `enrolled_at`
   сохраняется. Слово, над которым работали, остаётся в обычном повторении.

---

## 2. Состав сессии плана

`BuildPlanSessionHandler`. Строгая сессия (`strict = true`) — это фокусный день вида `Intro`;
иначе мягкий прогон (`softTasks()`), который ничего не планирует и ничего не закрывает.

### Вёдра, в порядке выдачи

| # | ведро | `source` | `section` | `from_day_index` | `origin` |
|---|---|---|---|---|---|
| 1 | слова ПЛАНА, просроченные, на ступени B или C (B раньше C) | `plan_review` | `day` | день ввода | null |
| 2 | слова этого дня, не закрывшие ступень A, в порядке `PlanDayOrder` | `new` | `day` | текущий день | null |
| 3 | доливка: всё остальное просроченное | `other_review` | `review` | `null` | `{kind, title}` |
| — | мягкий прогон (не строгая сессия) | `soft` | `day` | день | null |

Порядок «сначала день, потом доливка» — **контракт**: `PlanSessionView::$dayTaskCount` указывает
границу, `PlanSessionTaskView::$section` дублирует её на каждой задаче.

### Фильтры ведра 3 — `BuildPlanSessionHandler::inPlanPair()`

Применяются к списку `due` ЦЕЛИКОМ, до сборки, поэтому отсечённое не попадает и в пул дистракторов.

- термин, на котором стоит план (есть в `$standings`), — **остаётся всегда**, без проверок;
- иначе обязаны совпасть **обе** половины пары: `TermContentView::$lang` = `targetLang` плана И
  `CardLanguageResolver::forTerms()` = `supportLang` плана;
- иначе **`kind = line` не проходит никогда** — реплика повторяется только внутри своего плана.
  `word`, `chunk` и `kind = NULL` (обычная лексика) проходят.

`origin` считает `BuildPlanSessionHandler::originsFor()`: `CollectionPairReader::collectionByTerm()`
даёт папку, `PlanDayCollectionTitles::titlesByDayCollection()` превращает папку-день в название
ПЛАНА (`kind: plan`), иначе `kind: collection`.

### Дистракторы — `Vocabulary/Infrastructure/Eloquent/EloquentDistractorReader::forTarget()`

Принимает `UserId`. Два шага, оба через `appendCandidates()`:

| шаг | источник | фильтры |
|---|---|---|
| 1 | `$poolTermIds` — у плана это термины коллекции дня (`UserCollectionTermsReader::termIdsForCollection()`, доступ = владелец ∪ активная подписка) | язык = язык цели; семейство `kind` |
| 2 | догон, если шага 1 не хватило: `terms` × `collection_items` × `collections`, `readable()` = **свои коллекции ∪ подписки ∪ витрина** (`type = system` И `visibility = public`) | язык; семейство `kind`; `whereNotIn` цели и пула; сортировка «свой CEFR первым» |

Общие запреты в `appendCandidates()`: дубль текста, синонимы цели в обе стороны (`synonymBan()`),
пересечение переводов с уже занятыми (`overlaps()`).

**Семейство** — `EloquentDistractorReader::familyOf()`: `line` против «подстановки» (`word`, `chunk`,
`NULL`). Через границу кандидат не проходит никогда; внутри семейства порядок прежний.

---

## 3. Гейты

### P1 — `PlanOutlineValidator`

| код | фатально / warning |
|---|---|
| `outline.not_a_list`, `outline.no_scenes` | фатально |
| `outline.scene_count` | фатально за `HARD_MAX_SCENES = 8` (ориентир 1–5) |
| `outline.skill_count` | фатально вне `MIN_SKILLS`–`HARD_MAX_SKILLS = 20` |
| `outline.skill_without_outcome`, `outline.checkpoint_missing` | фатально |
| `outline.checkpoint_echoes_outcome`, `outline.outcome_two_actions` | фатально |
| `outline.est_terms` | фатально вне `HARD_MIN_EST_TERMS = 1` … `HARD_MAX_EST_TERMS = 12` |
| `outline.role_shape`, `outline.opening_lines` | фатально (`opening_lines`: 0 или больше 6) |
| `outline.target_language` | фатально |
| `plan_outline_skill_count` | **warning**, когда умений `MAX_SKILLS`+1 … 20 |
| `plan_outline_est_terms` | **warning**, когда `est_terms` вне 3–8, но внутри 1–12 |

### P2 — `PlanDayValidator`

Фатальные (`validate()`):

| код | о чём |
|---|---|
| `day.array_count`, `day.term_count` | три числа и сумма |
| `day.kind_mismatch` | `is_line`/массив/`speaker`/`type` противоречат друг другу |
| `day.checkpoint_uncovered` / `..._on_word` / `..._out_of_range` | чек-пойнты |
| `day.frame_slot_count` | дырок в каркасе больше одной |
| `day.filler_mismatch` | дырка без наполнителя или наполнитель без дырки |
| `day.filler_not_a_card` | наполнитель ≠ карточка дня. **Фатально только при `target_lang = en`** (`STRICT_FILLER_LANG`), иначе warning |
| `day.slot_outside_frame` | `___` в `text`/`translation`/`description`/`example`/`example_translation`/`image_api_prompt` |
| `day.role_line_invented` | реплика собеседника не из `opening_lines` (или у неё есть наполнитель) |
| `day.example_is_a_term`, `day.example_duplicated`, `day.example_missing` | примеры |
| `day.key_is_the_term` | ключ = термин, **в том числе тот же термин в другом алфавите** (`TransliteratedSameness`) |
| `day.term_is_a_name` | карточка `words`/`chunks` = имя из `entities` или `goal_terms`. Реплики исключены |
| `day.key_duplicated`, `day.key_not_support_language` | ключи |
| `day.description_gives_away` | описание называет свой термин |
| `day.image_prompt_missing` | нет `image_api_prompt` |

Счётчики (`warnings()`): `plan_day_formula_cap`, `plan_day_no_question`, `plan_day_no_repair`,
`plan_day_filler_mismatch`, `plan_day_chunk_outside_frame`, `plan_day_no_role_line`,
`plan_day_substitution_outside_frame`, `plan_day_role_line_share`.

Чтение (`transliterationFor()`) не судится вовсе: непригодная подсказка **отбрасывается**, день
живёт, счётчик `plan_day_transliteration_dropped`.

### План против самого себя — `PlanCoherenceValidator`

`plan.term_repeated`, `plan.checkpoint_duplicated`, `plan.entity_disagreement` — все фатальные.
`plan.checkpoint_duplicated` идёт **без адреса**, поэтому день с ним чинится только целиком.

### P2R — `PlanDayRepairer`

`day.repair_off_target` — фатально: ответ вернул не те карточки (лишнюю, пропущенную, чужой
`array`/`index`). Слияние не выполняется вообще.

### Адрес нарушения — `Generation/Domain/ValueObject/PlanViolation`

`array` + `index` + `field` + `code` + английский `reason`. `address()` — единственная форма,
которую видит модель. Русский `detail` и `subject` (текст карточки) идут в `fail_reason` и в лог и
к модели не едут. `isAddressed() === false` ⇒ починка по карточкам невозможна.

---

## 4. Счётчики `plan_*`

Пишет `Generation/Infrastructure/Adapter/LoggingPlanDefectReporter` через порт `PlanDefectReporter`.

- **Лог — всегда**, на каждой попытке: `Log::warning('Plan answer has a shape defect', …)` с
  `counter`, `plan_id`, `day_index`, `detail`, `kept`.
- **Счётчик — только у ПРИНЯТОГО ответа** (`counted: true`), ключ = имя счётчика, хранилище — `Cache`.

Полный список: `plan_outline_skill_count`, `plan_outline_est_terms`, `plan_day_formula_cap`,
`plan_day_no_question`, `plan_day_no_repair`, `plan_day_filler_mismatch`,
`plan_day_chunk_outside_frame`, `plan_day_no_role_line`, `plan_day_substitution_outside_frame`,
`plan_day_role_line_share`, `plan_day_transliteration_dropped`.

**Где их читать: НИГДЕ.** `PlanDefectReporter::warnings()` и `droppedTransliterations()` не
вызываются ни из одного экрана, эндпойнта или консольной команды — только из тестов. Значение
достаётся из кэша руками. Экрана нет ни у одного счётчика.

---

## 5. Промпты

Рендерит `Generation/Infrastructure/Prompt/PlanPromptLibrary` (порт — `PlanPromptSource`). Шапка
файла до первого `---` до модели не доезжает.

| id | версия / константа | файл | кто вызывает | схема ответа |
|---|---|---|---|---|
| **P1** | `plan_outline.v0.2` — `PlanPromptLibrary::OUTLINE_VERSION` | `plan_outline.v0.2.md` | `PlanOutlineService` | `PlanSchemas::outline()` |
| **P2** | `plan_day.v0.3` — `DAY_VERSION` | `plan_day.v0.3.md` | `PlanDayComposer` | `PlanSchemas::day()` |
| **P2R** | `plan_day_repair.v0.2` — `REPAIR_VERSION` | `plan_day_repair.v0.2.md` | `PlanDayRepairer` | `PlanSchemas::repair()` |

Обе половины плейсхолдеров форматирует `Generation/Application/Service/PlanPromptData`
(`entities()`, `bullets()`, `json()`) — одна на P2 и P2R, чтобы брифы не разъехались.

Учёт: `RecordsPlanSpend` → `EloquentPlanSpendLedger` → `generation_requests` с `purpose = 'plan'`,
`plan_id` и `prompt` вида `outline: …` / `day: день N — …` / `day_repair: починка дня N — карточек K`
(`PlanSpend::CALL_OUTLINE` / `CALL_DAY` / `CALL_DAY_REPAIR`). Отказ записи не проглатывается:
`PlanSpendNotRecorded` наверх.

Длины массивов (`minItems`/`maxItems`) в плановых схемах нет **сознательно** — DECISIONS п. 202.
