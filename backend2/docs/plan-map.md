# Карта Learning Plan — справочник по коду

**Обязательное чтение перед любым нарядом про план.** Здесь только факты из кода со ссылками на
классы. Почему так устроено — в докблоках этих классов и в `docs/DECISIONS.md`; живые прогоны — в
`docs/research/plan-*-run.md`; кто платит за какой промпт — в `docs/prompts/REGISTRY.md`.

Проверено по коду на 02.09.2026 (дополнено нарядом PLAN-FIX-4: §2 лестница плана своя и порядок
дня, §2.3 ключ говорения, §3 гейт `line.translation_missing_key`; PLAN-FIX-5: §2 пол выбора;
E2E-FIX-1: §2 матрица плана перебивает глобальную, реплика роли двумя источниками, полоса длины по
собранному тексту, пояс близости; §2.4 финальный день и завершение; §4 происхождение коллекции дня;
**P2-v0.4: сцена вместо мешка** — §0 контракт дня, §1 день = одна сцена, §2 разогрев, §3 гейты v0.4).

## 0. День — это сцена, а не мешок из трёх массивов

Канон: `docs/plan-model.md`, промпты `docs/p1.plan-outline.v0.4.md` и `docs/p2.plan-day.v0.4.md`.

| что | где в коде |
|---|---|
| полки дня | `Generation/Domain/ValueObject/PlanShelf`: `hear`, `say`, `ask`, `words`, `chunks`, `numbers` + серверная `rescue` |
| tier карточки | **выводит сервер** из полки (`PlanShelf::tier()`): `understand` — `hear` и `numbers`, `speak` — всё остальное. Модель tier не задаёт, колонка `terms.tier` |
| «почему я это учу» | `terms.skill_ref` → id умения сцены (`s<сцена>.<n>`, генерирует `PlanOutline::fromArray()`, P1 их не пишет) |
| число цифрами | `terms.number_value`, только полка `numbers`; карточки-числа лежат в дне и НЕ раздаются сессией (тренажёр — NUM-1) |
| вводка сцены | `learning_plan_days.snapshot.intro` → `PlanDayView::intro` → экран дня; в превью её пока нет (DAY-2) |
| спасательный набор | `config/generation.php` → `generation.plan.rescue_kit.<target>.<support>`; сервер вшивает пять фраз в день 1 (`GeneratePlanDayHandler::writeRescuePhrase()`) и подаёт их в P2 как запретный список |
| разогрев | `BuildPlanSessionHandler` — ведро 0, секция `warmup`, полка `rescue`; в `day_task_count` не входит |


---

## 0.1. Вход в план — что шаг слуха меняет в машинерии (ENTRY-2, 03.09)

| что | где в коде |
|---|---|
| три реплики на слух и два продолжения цели | `POST /plans/listen-warmup` → `BuildListenWarmupHandler` → порт `ListenWarmupPort` → `Generation/…/PlanListenService` (**P-Listen v1.1**). Одна ручка, ДВА момента входа: без `target_lang` — только `continuations` («Дописать за тебя», кадр V4·01в), с ним — обе половины |
| тихий отбой | порт НЕ бросает: вендор упал, форма не та, список пуст — везде пусто, и блок молча не показывается. Причина — в `ListenWarmupReporter` (лог), не на экране. Пустой `lines` у вызова БЕЗ языка дефектом не считается: это ответ, о котором просили |
| учёт без плана | `PlanSpend::CALL_LISTEN`, `plan_id = NULL`: шаг стоит между уровнем и датой, а план создаётся кнопкой после даты |
| что тапнул человек | едет обратно в `POST /plans` полем `listening[]`; вердикт на проводе не ездит |
| хранение | `learning_plans.listening_diagnostics` (jsonb) → `ListeningDiagnostics`. `NULL` = шаг пропущен, и это НЕ то же самое, что «прошёл и ничего не понял» |
| вердикт | `ListeningDiagnostics::emphasis()`: все «понял» → `speaking`, иначе (смешанное тоже) → `understanding`. Считается на чтении, не хранится |
| в P1 | `{{diagnostics}}` — прозой, реплики с «understood / not understood» + вывод (`PlanPromptData::diagnostics()`) |
| в P2 | `{{balance}}` — одно слово; правило («hear к верхней границе ориентира, say к нижней, и наоборот») живёт в тексте `plan_day.v0.4.1` |
| план без даты | `learning_plans.event_date` теперь nullable. `PlanScheduler::undated()`: сцены подряд до `MAX_INTRO_DAYS`, у дней нет `scheduled_on`, финальный день — «в конце», `days_to_event` = `null`, `deadline_tight` = `false`. Дата ставится позже обычным `PATCH /plans/{id}/outline` |

---

## 1. Путь плана

| шаг | класс | что решает |
|---|---|---|
| цель → каркас | `Learning/Application/Command/BuildPlanOutlineHandler` → `Generation/Application/Service/PlanOutlineService` | вызов **P1** |
| повтор каркаса | `PlanOutlineService::__invoke()` | ровно **две** попытки ВНУТРИ одного вызова; вторая получает нарушения первой; обе в `generation_requests` |
| разбор ответа | `Generation/Domain/Service/PlanOutlineValidator` | фатальные → отбой; warning → счётчик (§3) |
| вместимость дня | `Learning/Domain/Service/DayCapacity::forMinutes()` | 10 мин → 7, 20 → 14, 40 → 24; между точками прямая. Считается и показывается в превью, но **день больше не делит**: `split()` удалён вместе с массивами, которые он размерял |
| размер дня-сцены | `Learning/Domain/Service/SceneDay::UNITS = 25` | ориентир бюджета одного дня; сама раскладка по полкам — в P2 |
| сцены в дни | `Learning/Domain/Service/PlanScheduler::compute()` | **день = одна сцена целиком**; лишние сцены отбрасываются С КОНЦА (P1 пишет их по вероятности), `MAX_INTRO_DAYS = 14`, `MAX_STEP = 3` |
| пересчёт после правки | `PlanScheduler::recheck()` | что ещё влезает и какие умения ушли бы |
| когда писать день n+1 | `Learning/Domain/Service/PlanGenerationPolicy` | `EAGER_INTRO_DAYS = 3` (короткий план пишется вперёд), `MAX_READY_AHEAD = 2`; `nextAfterReady()` / `nextAfterDone()` |
| захват дня | `Learning/Application/Command/ClaimPlanDayHandler` → `PlanDay::claim()` | одна транзакция, `SELECT … FOR UPDATE`; отказ, если день занят/готов/бюджет исчерпан |
| материал дня | `Generation/Application/Service/PlanDayComposer::compose()` | вызов **P2** |
| суд над днём | `PlanDayComposer::judge()` | `PlanDayValidator`, одним вердиктом, с нуля. `PlanCoherenceValidator` удалён: его входом были плановые списки `constraints`/`goal_terms`/род сущностей, которых P1 v0.4 не пишет |
| починка | `Generation/Application/Service/PlanDayRepairer::repair()` | **порог**: `count(сломанных) ≤ floor(всего × 1/2)` И у каждого нарушения есть адрес. Иначе `null` — починки нет |
| после починки | `PlanDayComposer::compose()` | слияние по (`array`, `index`) → `judge()` заново целиком. Второго P2R нет никогда |
| повтор ЦЕЛОГО дня | `Learning/Application/Command/FinishPlanDayHandler` | только если после `markFailed()` статус вернулся в `pending`, то есть остался бюджет. Сообщение — только адреса последней попытки (`PlanDayComposer::retryMessage()`) |
| бюджет дня | `Learning/Domain/Entity/PlanDay::MAX_ATTEMPTS = 2` | считает **вызовы P2**, и только их: единственное, что двигает `generation_attempts`, — `claim()`. §1.1 |
| починка как деньги | `PlanDay::MAX_REPAIR_CALLS = 2`, колонка `repair_calls` | вызовы P2R, начисляются ОДИНАКОВО в `markReady()` и `markFailed()`. Деньги дня = `PlanDay::paidCalls()` = сумма двух. §1.1 |
| запись дня | `Generation/Application/Command/GeneratePlanDayHandler::materialize()` | коллекция + термины + примеры дня, одна транзакция |
| готов | `PlanDay::markReady()` | `ready`, `fail_reason` и `generation_violations` очищаются |
| конвейер | `GeneratePlanDayHandler::__invoke()` | `DispatchesExampleRepair::repairThenEnrich()` — починка эхо-примеров, ЗАТЕМ станок `BuildTermEnrichmentsHandler` (`VERSION = mech-v14.3`: принимаемые формы + **дистракторы**); отдельно `DispatchesImageAttachment::dispatch()` — **картинки** (Pexels по `image_api_prompt`). Fire-and-forget, день уже пригоден |
| сессия дня | `Learning/Application/Command/BuildPlanSessionHandler` | §2 |
| день пройден | `Learning/Application/Service/PlanDayPassing::mark()` | `passed` = все слова дня закрыли ступень A. Вызывается из `CompleteStudySessionHandler` (конец посадки) и из сборки следующей сессии |
| следующий день | `PlanDayPassing::mark()` → `PlanGenerationPolicy::nextAfterDone()` | ставится в очередь только на переходе в `done` |
| финальный день | `Learning/Domain/ValueObject/PlanDayKind::Final` | материала не имеет: `PlanDay::claim()` возвращает `false` |
| окончание | `EndPlanHandler` (`PlanEnding::Pause` / `Abandon` / `Complete`), `RecordPlanFeedbackHandler` | `PlanEnding::keepsHold()` — только `Pause` держит слова. Архивация — В ТОМ ЖЕ обработчике и в той же транзакции; `plan:archive-terms` только ремонт для планов, кончившихся ДО правила |

**Что уходит в пул при окончании** (`Abandon`/`Complete`, порядок обязателен):

1. `PlanTermReleaser::unenrolUntouched()` — пары, у которых `enrollment_sources` РОВНО `["plan:<id>"]`
   и НЕТ ни одной строки в `reviews`, покидают пул (`enrolled_at = NULL`).
2. `PlanTermReleaser::releasePlan()` — со всех остальных снимается `plan:<id>`; `enrolled_at`
   сохраняется. Слово, над которым работали, остаётся в обычном повторении.

### 1.1. Лестница отбоя: что чинится, что переписывается, и сколько это стоит

Одно решение, три исхода. Принимает `PlanDayComposer::compose()`, порог держит
`PlanDayRepairer::brokenCards()`.

| вердикт по ответу P2 | что делает лестница |
|---|---|
| пусто | день записывается |
| **все** нарушения адресованы И сломанных карточек `≤ floor(всего × 1/2)` | **P2R** по этим карточкам, слияние, суд заново целиком |
| хоть одно нарушение без адреса (`PlanViolation::isAddressed() === false`) | **повтор дня целиком** — чинить по карточкам нечего |
| сломано больше половины карточек | **повтор дня целиком** — это не хороший день с дефектами, это плохой день |

**Карточное нарушение** — любое, созданное через `PlanViolation::onCard()`, то есть несущее
`array` + `index`. Практически весь `PlanDayValidator`: `day.example_is_a_term`,
`day.example_duplicated`, `day.example_missing`, `day.kind_mismatch`, `day.key_is_the_term`,
`day.key_duplicated`, `day.key_not_support_language`, `day.slot_outside_frame`,
`day.description_gives_away`, `day.image_prompt_missing`, `day.term_is_a_name`,
`day.role_line_invented`, `day.filler_not_a_card`, `line.translation_missing_key`, плюс
`plan.term_repeated` и `plan.entity_disagreement` из `PlanCoherenceValidator`.

**Нарушения БЕЗ адреса** (день чинится только целиком): `day.array_count`, `day.term_count`,
`day.checkpoint_uncovered` / `..._on_word` / `..._out_of_range`, `day.frame_slot_count`,
`day.filler_mismatch`, `plan.checkpoint_duplicated`, `day.repair_off_target`.

**Счётчики.** `generation_attempts` — вызовы P2, потолок `MAX_ATTEMPTS = 2`, двигает только
`claim()`; день становится `failed` РОВНО тогда, когда исчерпаны они. `repair_calls` — вызовы P2R,
потолок `MAX_REPAIR_CALLS = 2` (один на прогон × два прогона), начисляются одинаково на записанном и
на отбитом дне. До 01.09 починка начислялась в `generation_attempts` и только на провальном пути,
отчего одни и те же два вызова читались как «1 попытка» на дне 1 и как «3» на дне 2 (DECISIONS
п. 206, Д-18).

**Что этого НЕ решает:** качество самого ответа. Живой день 2 вернул 10 сломанных карточек из 14 —
порог сработал верно, и повтор дня был правильным ходом. Разбор — `docs/research/e2e-sim-1.md`.

---

## 2. Состав сессии плана

`BuildPlanSessionHandler`. Строгая сессия (`strict = true`) — это фокусный день вида `Intro`;
иначе мягкий прогон (`softTasks()`), который ничего не планирует и ничего не закрывает.

### Вёдра, в порядке выдачи

| # | ведро | `source` | `section` | `from_day_index` | `origin` |
|---|---|---|---|---|---|
| 0 | **разогрев** — пять фраз спасательного набора (полка `rescue`), каждый день, пока план идёт | `warmup` | `warmup` | день 1 | null |
| 1 | слова ЭТОГО дня, не закрывшие ступень A, в порядке `PlanDayOrder` (§2.2) | `new` | `day` | текущий день | null |
| 2 | слова ЭТОГО плана с прошлых дней, просроченные, на ступени B или C (B раньше C) — шов «Повторение» | `plan_review` | `review` | день ввода | null |
| — | мягкий прогон (не строгая сессия) | `soft` | `day` | день | null |

**Ведра 3 больше нет — и «нет» значит «не вызывается».** До 01.09 сессия плана доливалась всем
просроченным пользователя. 31.08 доливку сузили фильтром языковой пары и `kind = line` (DECISIONS
п. 204); 01.09 сквозь этот фильтр в урок «Аренда жилья» приехал «паспорт» из брошенного плана
«Отдых в Италии» — свой язык, своя пара, не `line`. Правило отменено целиком: список читается
`DueTermsReader::selectableForPlan()` — только пары, зачисленные ЭТИМ планом
(`jsonb_exists(enrollment_sources, 'plan:<id>')`), — поэтому чужую карточку сессия не отфильтровывает,
а не видит. Фильтра `inPlanPair()` и метода `originsFor()` больше нет.

**Разогрев идёт первым и в счёт дня не входит.** Набор принадлежит ПЛАНУ, а не сцене: он лежит в
коллекции дня 1, раздаётся в начале каждой посадки и никогда не выпадает из ротации.
`PlanSessionView::$dayTaskCount` считает только карточки дня, поэтому «N из N» на экране — про
сцену, а не про разогрев. По той же причине `PlanProgress::stageAClosedForAll()` **исключает полку
`rescue`** из условия «день пройден»: иначе голодная ступень A набора держала бы день 1 каждого
плана открытым навсегда.

**Порядок: сначала день, потом шов.** Обратный порядок («сначала повторить знакомое») был верен,
пока прошлые дни были частью ДНЯ; теперь это секция с подписью, которую читает человек, а секция,
объявленная после своих карточек, — не секция. Бюджет согласен: `array_slice` режет хвост, а
ступень A обязана закрыться за одну посадку. `PlanSessionView::$dayTaskCount` считает карточки
только сегодняшнего дня — день 1 любого плана идёт «N из N» без «Повторения».

**Обратная сторона того же правила.** Пока план идёт, его слова раздаёт только он: их нет в обычной
сессии и в «Повторить N» (`PlanHeldTerms`). Когда план кончается — их нет там уже насовсем
(`PlanTermArchiver`, DECISIONS п. 214): завершение и отмена НЕ выпускают слова в общую лестницу, и
делает это сам `EndPlanHandler`, в той же транзакции, что пишет статус.

**`origin` всегда `null`.** Строка осталась в контракте (клиент, читающий её, не ломается), но
называть чужую полку больше нечего: доливки нет, а подписывать «из плана: <этот же план>» над
карточкой его собственного прошлого дня — то самое враньё со скрина 01.09. Клиент рисует шов по
`section` первой не-дневной карточки и подписывает его «Повторение · из прошлых дней».

### 2.1. Лестница плана считается для пары (ПЛАН, термин)

`PlanStandings::forTerms()` + `PlanStandingsReader::factsFor()/introducedAmong()`, параметр `since`.

Термины дедуплицированы глобально, поэтому журнал `(user, term)` несёт всю историю слова: чужой
план, блокнот, этот план. Доказательством для ЭТОГО плана считается только то, что случилось после
того, как карточка вошла в него. Отсечка — `collection_items.created_at` дня
(`UserCollectionTermsReader::joinedAtForCollection()`), читается ПО ДНЮ: день плана это коллекция,
записанная в один момент, а карточка дня 3 имеет свою дату.

- Новая карточка нового плана начинается с интро, что бы ни было со словом раньше. Экспозиция
  старше отсечки — не закрывает интро.
- Прогресс блокнота не меняется: сборка сессии читает лестницу и не пишет в `user_term_progress`.
- Отсечка, а не «ответы внутри сессий плана»: точное прочтение уронило бы шаг ревью, приехавшего
  без `session_id`, а незакрываемый шаг — это непроходимый день.
- Обе стороны — `timestamp(0)`; ответ в ту же СЕКУНДУ, что и запись дня, считается «после».
  В тестах поэтому `travel()`, а `ageHistory()` двигает и `collection_items.created_at`.

### 2.2. Порядок дня — `PlanDayOrder`

Четыре блока, всегда одни и те же: `word` → `chunk` → `line` (реплики ученика) → `line` со
`speaker = role`. Внутри блока — по возрастанию трудности, неоцененная карточка сортируется как
лёгкая.

**Уровень порядок НЕ решает.** `PlanLevel::wordsBeforeLines()` (от `conversational` реплики шли
первыми) удалён: «слова внутри реплики узнаются по дороге» — узнавание это отдельная карточка,
которую раздаёт лестница своего слова, и на живом дне она доходила до слов после всех реплик
(DECISIONS п. 216). Уровень по-прежнему решает число вариантов и открытые тренажёры.

### 2.3. Говорение фразы — по ключу

`terms.speaking_key`, выбирается один раз при записи дня (`PlanSpeakingKey::of()`), потому что
только этот код видит все варианты:

| порядок | ключ |
|---|---|
| 1 | `filler` — слово в дырке каркаса |
| 2 | у формулы — карточка дня, стоящая внутри реплики; самая ДЛИННАЯ из подходящих, по границам слов |
| 3 | ничего — `null`, и это значит «скажи фразу целиком» |

Читают колонку обе стороны: `TermAnswerKeyView::$speakingKey` → `SubmitReviewsHandler::expectedFor()`
(сравнение покрытием, как и раньше) и `SessionCardView::$speakingKey` → `speaking_key` в контракте
карточки. Ключ едет на КАРТОЧКЕ, а не на плановой задаче: фразу раздают и мягкий прогон, и
свободная практика, и клиент, не знающий ключа на одном из путей, покажет вердикт, который сервер
опровергнет. Клиент подчёркивает только слова ключа (`SessionGrader.keyWordIndices`) и подписывает
карточку «скажи фразу, главное — <ключ>» / «скажи фразу целиком».

### Дистракторы — `Vocabulary/Infrastructure/Eloquent/EloquentDistractorReader::forTarget()`

Принимает `UserId`. Два шага, оба через `appendCandidates()`. **Правило одно для плановой и обычной
сессии** — различаются только предпочитаемый пул и число вариантов, но не то, кому можно попасть в
варианты.

| шаг | источник | фильтры |
|---|---|---|
| 1 | `$poolTermIds` — у плана это термины ВСЕХ его дней (`BuildPlanSessionHandler::planPoolIds()`), не только сегодняшней коллекции | язык = язык цели; семейство `kind` |
| 2 | догон: `terms` ЦЕЛИКОМ, вне зависимости от коллекций и пользователей | язык; `kind` цели в SQL + семейство в PHP; **полоса длины**; `whereNotIn` цели и пула; **`source <> 'user'`, кроме терминов на полке самого ученика**; сортировка «свой CEFR первым» |

**Полоса длины** — `Shared/Domain/Service/DistractorLength`, пороги в `config/learning.php →
distractor_length`. Одной формы мало: `key` среди `accommodation` / `neighbourhood` /
`responsibility` отвечается выбором короткого, не читая. Две меры, потому что фраза — не длинное
слово: `word`, `chunk` и «без kind» меряются символами (±50 % от цели), `line` — словами (±40 %).
Вне полосы — не вариант, не «вариант похуже». Читают её все трое, как и семейство: читатель
дистракторов, `StudyCardAssembler::recognitionCard()` (далёкие варианты) и `PlanStandings` — там
счёт «сколько вариантов вообще есть» стал считаться ДЛЯ КАЖДОЙ ЦЕЛИ, а не по семействам один раз на
день: «сколько в плане слов» — одно число для `key` и для `accommodation`, а ответ у них разный. При
голоде выбор не добивается, а выпадает; счётчик `plan_distractor_starved`.

Шага «свои полки ∪ подписки ∪ витрина» больше нет (owner, 01.09). Полка была неверным инструментом
сразу в обе стороны: слишком узко — коллекция дня плана это приватная папка ВЛАДЕЛЬЦА, поэтому любой
его прошлый план был законным источником вариантов для текущего; слишком широко — «моя полка» ничего
не говорит о том, личный ли текст на ней. Отделяет честный филлер от чужого личного не папка, а
`terms.source`: `ai`/`curated` — каталог приложения, `user` — то, что человек напечатал сам (слово
руками в коллекцию или сохранение из переводчика), и оно не попадает в ЧУЖИЕ варианты. Свои
собственные — попадают: иначе у ученика, чей словарь состоит из напечатанного руками, карточка его
же слова осталась бы вообще без вариантов. Наружу уходит только ТЕКСТ — ни владельца, ни коллекции,
ни следа происхождения.

**Семейство** — `Shared/Domain/Service/DistractorFamily::of($kind, $text)`, одно правило на всех, кто
его читает:

| kind цели | что может стоять рядом |
|---|---|
| `word` | только `word` |
| `chunk` | только `chunk` |
| `line`, текст кончается на `?` | только другие вопросы-`line` |
| `line`, всё остальное | только другие утверждения-`line` |
| `NULL` (обычная лексика, почти весь каталог) | только `NULL` — это НЕ «word» |

Через границу кандидат не проходит никогда, и добора чужим видом нет. До 01.09 семейство было
широким (`line` против `word`/`chunk`/`NULL` вместе), и живой прогон показал обе дыры: вопрос среди
трёх утверждений и связка среди одиночных слов (DECISIONS п. 207).

**Голод.** Правило читают ТРИ места, и все три через `DistractorFamily`:

**Голод больше не «всё или ничего».** Число уровня (`mc_options`) — ПОТОЛОК и предпочтение; под ним
отдельный ПОЛ — `PlanChoiceFloor`, `config/learning.php → plan.mc_min_options` = 3. Карточка, для
которой набирается три варианта своей формы и своей длины, раздаётся с тремя; ниже трёх — выпадает
целиком, как и раньше. Пол читают ОБА, из одного объекта: чек-лист (что карточка должна) и сборщик
(что можно построить).

**Популяция кандидатов одна — КАТАЛОГ** (п. 213), у чек-листа и у сборщика. Раньше чек-лист считал
по ДНЮ и звался «строго не шире читателя»; читателя при этом никто не спрашивал, и восемь карточек
живого дня 1 получили отказ там, где каталог набирал каждой по три. Теперь `PlanStandings` сначала
считает день в памяти (он уже загружен и обычно отвечает), а не дотянув до пола — спрашивает
`DistractorReader`, тот самый, из которого карточка будет построена.

| место | что делает при голоде |
|---|---|
| `PlanStandings::applicableFor()` | пятый фильтр: если своего семейства и своей полосы набирается меньше пола — в ДНЕ, а при нехватке и в КАТАЛОГЕ через `DistractorReader` — режимы выбора (`multiple_choice`, `description_match`) не попадают в `applicable`: карточка **не owed**, и ступень закрывается без неё. Счётчик `plan_distractor_starved` через `ModeFallbackReporter::distractorStarved()`, и в него пишется ПОЛ, а не предпочтение |
| `StudyCardAssembler::recognitionCard()` | ПОЯС БЛИЗОСТИ — соседи сессии. Не набрав пола, возвращает `null` НА ОБЕИХ СТОРОНАХ (не только у плана), и карточка проваливается в обычную MC, которая добирает из каталога через `DistractorReader` с тем же семейством и той же полосой. Дотягиваться дальше, а не раздавать меньше (DECISIONS п. 225) |
| `StudyCardAssembler::assemble()` | у плана отказ карточки целиком, если вариантов меньше ПОЛА; вне плана прежний пол `MIN_OPTIONS = 2` (QA-15) — колода из двух слов на свежем аккаунте тренируется |

Порядок важен: снятие ДО раздачи, потому что шаг чек-листа закрывается ответом, и шаг, карточку для
которого построить нельзя, — это ступень, которая не закрывается, и день, который не проходится.
Правило прежнее — чек-лист не имеет права попросить карточку, которую сборщик не соберёт, — но
держится оно теперь тем, что оба спрашивают ОДИН объект, а не тем, что одно правило уже второго.
Дешёвая половина (счёт по дню) остаётся верхней оценкой: она применяет семейство и полосу, но не
запрет переводов-близнецов и синонимов, — приближение не новое и в докблоке названо.

**Число вариантов** — `PlanKnobs::mcOptions`, конфиг уровня (DECISIONS п. 167), НЕ код:
`zero` → 3, `basic` → 3, `conversational` → 4, `fluent` → 4. Это ПРЕДПОЧТЕНИЕ; пол под ним — 3
(DECISIONS п. 220). Обычная (не плановая) сессия всегда даёт 4 — `StudyCardAssembler::OPTION_COUNT`.

**Реплика роли** получает только узнавание, и правило живёт в `RoleLineModes` (DECISIONS п. 223).
Производящие режимы (`word_bank`, `scramble`, `typing`, `speaking`, `cloze`, `dictation`) выпадают;
`listening` НЕ выпадает — услышать реплику и записать — ровно тот навык, ради которого она в дне.

| | |
|---|---|
| два ИСТОЧНИКА | `terms.speaker = 'role'` **и** `role_brief.role.opening_lines` дня: модель кладёт свою же вступительную реплику в карточки дня без пометки, и так «Does your child have a fever?» получила диктант |
| два ЧИТАТЕЛЯ | `PlanStandings::applicableFor()` (шаг не попадает в чек-лист) **и** `StudyCardAssembler::assemble()` (мягкий прогон дня, открытого раньше срока, берёт тренажёр с обычной лестницы и чек-листа не видит) |

`speaker` едет клиенту и в контракте дня, и в задаче сессии.

**Полоса длины** меряется по СОБРАННОМУ тексту (DECISIONS п. 224): карточка ступени 1 предлагает
переводы, и мерить английскую сторону соседей — значит мерить строку, которой на экране нет. Реплика
обязана попасть в обе полосы — словную и символьную: перенос строки делается из символов.

**Матрица плана перебивает глобальную** (DECISIONS п. 222): `PlanStandings` пересекает лестницу
ступени, уровень (`scope = plan`), ЯЗЫК и содержимое термина — и НЕ тумблеры ученика
(`scope = global`). Те правят обычную сессию и больше ничего.

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
| `outline.opening_lines` | фатально (0 или больше 6). `outline.role_shape` **отменён** — роли-объекта в v0.4 нет, реплики лежат прямо на сцене |
| `scene.intro_missing` | **фатально** — сцена без вводки: экран дня начинается со списка предложений |
| `outline.target_language` | фатально |
| `plan_outline_skill_count` | **warning**, когда умений `MAX_SKILLS`+1 … 20 |
| `plan_outline_est_terms` | **warning**, когда `est_terms` вне 3–8, но внутри 1–12 |
| `scene.intro_has_target_lang` | **warning** — во вводке изучаемый язык |

Схема ответа — `PlanSchemas::outline()`, и она обязана разрешать ровно то, что просит промпт:
структурный вывод не может вернуть ключ, которого нет в схеме. Живой прогон наряда P2-v0.4 заплатил
за это дважды: промпт просил `intro`, схема осталась v0.2, и `scene.intro_missing` отбил каркас на
всех сценах. Держит связку `tests/Unit/Generation/PlanSchemaShapeTest.php` — фикстуры валидатора
обязаны быть выразимы в схемах.

### P2 — `PlanDayValidator`

Карточные фатальные (`validate()`) — у каждого есть адрес, поэтому они идут в P2R:

| код | о чём |
|---|---|
| `card.gap_missing`, `card.gap_outside_frame` | `___` нет в `frame`, или он встретился вне его |
| `card.translation_has_gap` | `___` уехал в перевод |
| `card.translation_missing_key` | перевод реплики не содержит перевода ключа (§2.3). Сравнение по ОСНОВАМ (`TranslationKeyPresence`, длина основы по языку поддержки в `config/generation.php → translation_stems`, ru/uk = 5). Язык без правила не судится |
| `card.clone` | собранный текст совпал с другой карточкой дня, с фразой спасательного набора или с единицей прошлого дня |
| `card.example_is_a_term` | пример равен карточке или любой реплике дня |
| `card.example_skeleton_clone` | два примера — одно предложение с подменённым термином (`ExampleSkeleton`, Д-29) |
| `card.example_without_translation` | у примера нет перевода — вторая половина Д-29, с той стороны, откуда приходит модель |
| `card.filler_not_card` | **только `words`/`chunks`**: пример карточки не содержит саму карточку по границам слов. Это дырка, в которой слово тренируется: `PlayabilityAssessor` считает термин clozeable, только если пример содержит ответ |
| `card.word_is_basic` | стоп-список `BasicVocabulary`; фатально от уровня «Понимаю простое», на `zero` — warning |
| `card.chunk_is_basic` | **только `chunks`**: ВСЕ слова связки в стоп-списке `BasicVocabulary` («see it»). Уровень не смотрится — связка из базовых слов не связка ни на каком |
| `card.chunk_article_pair` | **только `chunks`**: связка из двух слов, первое — артикль языка цели («the location», «an appointment»). Само слово живёт на полке `words`. Список артиклей — `BasicVocabulary::ARTICLES`, язык без списка (de) не судится |
| `card.kind_size` | `word` > 3 слов; `chunk` вне 2–4; `say`/`ask` вне 3–8; `hear` > 12 |
| `card.term_is_a_name` | карточка `words`/`chunks` = имя из `entities` **написанное как имя** (заглавная) или слово из `goal_terms` |
| `card.translation_is_transliteration` | перевод = термин в другом алфавите (`TransliteratedSameness`) |
| `card.skill_ref_invalid` | `skill_ref` пуст или не из этой сцены |
| `card.number_value_mismatch` | число в `frame` не сходится с `value` (только `numbers`) |

Дневные фатальные: `day.shelf_missing` (нет полки `hear` / `say` / пары `words+chunks` целиком) и
порог «сломано больше половины карточек» → повтор дня целиком.

Счётчики (`warnings()`): `plan_day_size_out_of_range` (полка вне ориентиров 4–6 / 4–6 / 2–3 / 6–8 /
2–4), `plan_day_formula_cap`, `plan_day_no_question`, `plan_day_no_repair`,
`plan_day_skill_uncovered`, `plan_day_role_line_share`, `plan_day_substitution_outside_frame`,
`plan_day_intro_repeated`, `plan_day_filler_mismatch`, `plan_day_key_duplicated`,
`plan_day_image_prompt_missing`, `plan_day_key_not_support_language`, `plan_day_word_is_basic`.

**У `card.filler_not_card` два ранга, и делит их полка** (решение владельца 02.09):

- `say`/`ask` — **счётчик** `plan_day_filler_mismatch` (все языки). Живой прогон: три ответа P2 и
  три починки, $0.198, день 1 так и не доехал — отбивались реплики, которые человек говорит
  («Should we go to the front desk?»), потому что ключ реплики это слово ВНУТРИ неё, а не вся
  карточка, и в дырке стоит то, что день карточкой сделать не вправе (базовая лексика запрещена,
  числа живут только на своей полке). Правило переехало в промпт просьбой: «prefer as the key a
  word or chunk from today's shelves» (CONTENT RULES, правка канона 02.09).
- `words`/`chunks` — **карточный фатальный**: пример карточки обязан содержать саму карточку. Здесь
  правило выполнимо (модель просят использовать слово, которое сама и написала) и механически
  обязательно: `PlayabilityAssessor` считает термин clozeable, только если пример содержит ответ,
  поэтому пример без своего слова отнимает у карточки режим целиком.
- `hear`/`numbers` — молчит: их не произносят.

Совпадение наполнителя с карточкой дня — по ПОКРЫТИЮ, а не по равенству
(`PlanDayValidator::fillerIsTaught()`): точное совпадение, либо наполнитель целиком внутри карточки
по границам слов («front desk» внутри «come to the front desk»), либо все его слова базовые (день
их карточкой сделать не вправе).

Чтение (`transliterationFor()`) не судится вовсе: непригодная подсказка **отбрасывается**, день
живёт, счётчик `plan_day_transliteration_dropped`.

Отменены вместе с мешком: `day.array_count`, `day.term_count`, `day.kind_mismatch`,
`day.checkpoint_uncovered`, `day.role_line_invented`, `day.description_gives_away`,
`plan.term_repeated`, `plan.checkpoint_duplicated`, `plan.entity_disagreement` (весь
`PlanCoherenceValidator`).

### P2R — `PlanDayRepairer`

`day.repair_off_target` — фатально: ответ вернул не те карточки (лишнюю, пропущенную, чужой
`array`/`index`). Слияние не выполняется вообще.

### Адрес нарушения — `Generation/Domain/ValueObject/PlanViolation`

`array` + `index` + `field` + `code` + английский `reason`. `address()` — единственная форма,
которую видит модель. Русский `detail` и `subject` (текст карточки) идут в `fail_reason` и в лог и
к модели не едут. `isAddressed() === false` ⇒ починка по карточкам невозможна.

---

## 3.1. Статус дня, как его читает клиент

Пишется в `learning_plan_days.status`; отдаётся `PlanResource::day()` как есть, рядом с
`generation_attempts`, `repair_calls` и `fail_code`.

| статус | что это значит на самом деле | подпись в приложении |
|---|---|---|
| `pending` | никто ещё не взял день. Задача может быть в очереди, а может и не быть | «В очереди» |
| `generating` | воркер держит день **прямо сейчас**. Пишет это только `claim()` | «Собирается» |
| `ready` | материал есть | состав дня |
| `failed` | вызовы P2 исчерпаны | «Не собрался», и без кнопки «Продолжить» |
| `done` | ступень A дня закрыта | «пройден» |

День `done` пишется в двух моментах и одним кодом (`PlanDayPassing`): в конце сидения — по
`POST /study/sessions/{id}/complete`, который клиент шлёт последним ответом (DECISIONS п. 229), — и
на входе в следующую плановую сессию. Второе было единственным до 02.09, и день оставался `ready`
до тех пор, пока ученик не откроет следующий (Д-1, Д-28).

**Финальный день** (`kind = final`) статуса не меняет: он не пишется, и `generate` на него — 404 по
замыслу. Его сессия — прогон по всем карточкам плана (`BuildPlanSessionHandler::rehearsalTasks()`,
`is_practice`, ступеней не закрывает), а по её окончании клиент шлёт `POST /plans/{id}/complete` —
штатный `EndPlan(Complete)` с тем же архивом, что делает отказ (DECISIONS п. 230).

`pending` и `generating` — РАЗНЫЕ подписи и ОДИН поллинг (`PlanDayStatus.isBuilding` на клиенте):
опрос обязан продолжаться и на `pending`, а подпись — нет. Живой прогон подписал «Собирается» два
нетронутых дня с нулём попыток (Д-20).

**`fail_code`** — код первого фатального нарушения последней попытки, и единственное, что из вердикта
едет клиенту. Русская проза (`PlanViolation::$detail` → `fail_reason`) остаётся на сервере
(DECISIONS п. 208). Клиент формулирует сам: `mobile/lib/features/plan/plan_fail_reason.dart` — карта
код → строка, и одна нейтральная строка для кода, которого он не знает. **Появился новый код —
добавляется строка там**; выдумывать причину по форме кода нельзя.

## 3.2. Матрица режимов на свежей базе

Миграции пишут все 51 строку и шлют новый тренажёр ВЫКЛЮЧЕННЫМ глобально (правило выкатки:
себе → бете → всем из админки). `database/seeders/LearningModeSettingsSeeder` несёт то, что владелец
уже выкатил, — только `enabled` и `position` у `scope = global`, ни одного гейта и ни одной строки
`scope = plan`.

Установка свежей базы: `php artisan migrate` затем `php artisan db:seed` (сидер подключён к
`DatabaseSeeder`).

**ПЛАНУ СИДЕР БОЛЬШЕ НЕ НУЖЕН** (DECISIONS п. 222). Раньше без него план на новом аккаунте выходил
без интро и без говорения, и ступень A схлопывалась с четырёх шагов до двух (Д-14, Д-15): плановая
матрица пересекалась с глобальными тумблерами, а те шлются выключенными. Пересечения больше нет —
план знает свою лестницу. Сидер остаётся тем, чем он и был: тем, что владелец выкатил для ОБЫЧНОЙ
сессии.

## 3.3. Коллекция дня помечена происхождением

День владеет обычной коллекцией (`type = custom`, `visibility = private`, `owner_id = ученик`) —
это то, что позволяет машинерии сессии раздавать его карточки, не зная, что планы существуют. Чтобы
он при этом не был полкой ученика, коллекция несёт `collections.origin = 'plan'`
(`CollectionOrigin`, DECISIONS п. 228).

| место | что делает |
|---|---|
| `Collections/…/EloquentUserCollectionsReader::forUser()` | `whereNull('origin')` — кормит `GET /collections` и плитку разбора на Главной |
| `mobile … AppDatabase::watchCollections()` | «Мои коллекции» и все шесть экранов, что читают список папок ученика |
| `mobile … AppDatabase::challengeMirror()` | «слово-вызов» на Главной (Д-35: брал плановые реплики в дистракторы и после архивации) |
| дельта-фид | строку ШЛЁТ, с `origin` на проводе: пара карточки резолвится через её коллекцию, и без неё плановая сессия на телефоне не играется |

## 4. Счётчики `plan_*`

Пишет `Generation/Infrastructure/Adapter/LoggingPlanDefectReporter` через порт `PlanDefectReporter`.

- **Лог — всегда**, на каждой попытке: `Log::warning('Plan answer has a shape defect', …)` с
  `counter`, `plan_id`, `day_index`, `detail`, `kept`.
- **Счётчик — только у ПРИНЯТОГО ответа** (`counted: true`), ключ = имя счётчика, хранилище — `Cache`.

Полный список: `plan_outline_skill_count`, `plan_outline_est_terms`, `scene.intro_has_target_lang`,
`plan_day_size_out_of_range`, `plan_day_formula_cap`, `plan_day_no_question`, `plan_day_no_repair`,
`plan_day_skill_uncovered`, `plan_day_filler_mismatch`, `plan_day_substitution_outside_frame`,
`plan_day_role_line_share`, `plan_day_intro_repeated`, `plan_day_key_duplicated`,
`plan_day_image_prompt_missing`, `plan_day_key_not_support_language`, `plan_day_word_is_basic`,
`plan_day_transliteration_dropped`.

Особняком — `plan_distractor_starved` (§2): его пишет НЕ этот репортер, а
`Learning/Infrastructure/Adapter/LoggingModeFallbackReporter` через порт `ModeFallbackReporter`,
потому что он про сборку сессии, а не про ответ модели. Хранилище то же (`Cache`), читателя тоже нет.

**Где их читать: НИГДЕ.** `PlanDefectReporter::warnings()` и `droppedTransliterations()` не
вызываются ни из одного экрана, эндпойнта или консольной команды — только из тестов. Значение
достаётся из кэша руками. Экрана нет ни у одного счётчика.

---

## 5. Промпты

Рендерит `Generation/Infrastructure/Prompt/PlanPromptLibrary` (порт — `PlanPromptSource`). Шапка
файла до первого `---` до модели не доезжает.

| id | версия / константа | файл | кто вызывает | схема ответа |
|---|---|---|---|---|
| **P1** | `plan_outline.v0.4` — `PlanPromptLibrary::OUTLINE_VERSION` | `plan_outline.v0.4.md` | `PlanOutlineService` | `PlanSchemas::outline()` |
| **P2** | `plan_day.v0.4.1` — `DAY_VERSION` | `plan_day.v0.4.1.md` | `PlanDayComposer` | `PlanSchemas::day()` |
| **P2R** | `plan_day_repair.v0.2` — `REPAIR_VERSION` | `plan_day_repair.v0.2.md` | `PlanDayRepairer` | `PlanSchemas::repair()` |
| **P-Listen** | `plan_listen.v1.1` — `LISTEN_VERSION` | `plan_listen.v1.1.md` | `PlanListenService` | `PlanSchemas::listen()` |

Обе половины плейсхолдеров форматирует `Generation/Application/Service/PlanPromptData`
(`entities()`, `bullets()`, `json()`) — одна на P2 и P2R, чтобы брифы не разъехались.

Учёт: `RecordsPlanSpend` → `EloquentPlanSpendLedger` → `generation_requests` с `purpose = 'plan'`,
`plan_id` и `prompt` вида `outline: …` / `day: день N — …` / `day_repair: починка дня N — карточек K`
(`PlanSpend::CALL_OUTLINE` / `CALL_DAY` / `CALL_DAY_REPAIR`). Отказ записи не проглатывается:
`PlanSpendNotRecorded` наверх.

Длины массивов (`minItems`/`maxItems`) в плановых схемах нет **сознательно** — DECISIONS п. 202.
Схема и промпт двигаются ОДНОВРЕМЕННО: структурный вывод физически не отдаёт ключ, которого нет в
схеме, поэтому «промпт просит, схема не разрешает» читается как «модель не слушается». Держит
`PlanSchemaShapeTest`.

Версии v0.2/v0.3 остались файлами и строками реестра: план, начатый на них, дочитывается
(`PlanOutline::fromArray()` понимает обе формы каркаса).
