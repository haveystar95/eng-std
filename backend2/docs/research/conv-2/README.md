# CONV-2 — бэкенд разговора: хвосты после живых прогонов

**Наряд:** четырнадцать пунктов после живых прогонов разговора 21.09 — разговоров Дена на бою (зал, день 1;
репетиция «Просмотр жилья») и заходов клиентского наряда CLIENT-CONV-1a (§5 и §1.8 его отчёта). Только `backend2/`, плюс
то, что наряд назвал сам: хук ворот (`.claude/hooks/pre-commit-gate.sh`, п. 13) и `docs/DECISIONS.md`. Канон —
`docs/plan-v2.md` §6 («Говорю сам», потолок «Фраз») и §11; контракт — `docs/plan-api.md` («Разговор с агентом») +
`openapi/openapi.yaml`; решения — `docs/DECISIONS.md` пп. **366–378**, пять записей в «Отменено», «Спорное» п. 2.

**Деньги.** Куплено на живых проверках: **$0.120781** — 109 вызовов `gpt-5.4-mini` (разговор 103, судья 6), всё на
`wordtrainer_e2e_test` (журнал `model_calls`, 18:35–19:03 UTC). Кап наряда — $1. **Генераций уроков — 0**, **озвучки — 0**
(стенд с `SPEECH_ENABLED=false`: голос разговора не проверялся и не покупался). На бою (`wordtrainer`) — только чтение
(сессии `SET SESSION CHARACTERISTICS AS TRANSACTION READ ONLY`, выгрузки — `den/`), затем выкат по §6.

> ### ⚠ Стоп-сигнал лестницы «Фраз» сработал — решение за архитектором
>
> Пол «два круга» (п. 5) на ЖИВЫХ уроках выводит карточки дня за потолок 32 минуты (DECISIONS пп. 328, 365):
>
> | день | «Фразы» было → стало (потолок 690 с) | карточки дня было → стало (потолок 32 мин) |
> |---|---|---|
> | зал Дена, день 1 (бой, intermediate, 7 каркасов × 2 значения) | 673 → **823 с** | 1816 с = 31 → **1966 с = 33 мин** |
> | «врач» стенда e2e, день 1 (beginner, 7 × 3) | 685 → **810 с** | 1798 с = 30 → **1923 с = 33 мин** (на 3 с за потолком) |
> | чистый «врач» фикстур (beginner / intermediate) | 687 / 688 → без изменений | 30 мин → без изменений (`DayBudgetTest` зелёный) |
>
> Наряд выполнил п. 5 как поставлен (два круга — пол, ступени: третье узнавание → третий круг → стоп-сигнал), и сигнал
> — ровно то, что он просил показать. Стороны спора — DECISIONS «Спорное» п. 2: поднять потолки ручками
> (`plan.phrases_budget` ≈ 830, `plan.day_cards_budget` = 34, без кода) или отдать пол там, где значения диалог говорит
> реже. Данные — `live/ladder-*.txt`, §1.5.

---

## §1. По пунктам: причина и правка

### 1.1. Роли — модель играет только роль сцены

**Где перевернулось.** На тех же входах (`tools/replay-den.php`: история разговора и услышанное — как в стенограмме боя,
`den/transcripts.json`) роль говорила реплику ученика в **11 ответах из 15**: в репетиции «Звонок агенту» + «Просмотр
жилья» — 8 из 10 (агент задавал вопросы жильца с первой реплики до последней: «Is this flat two rooms?», «What is the
rent?», «Is parking included?», «Are dogs allowed?», «How much is the deposit?», «Can we meet Friday evening?», «Can I see
the kitchen?»); в зале — 3 из 5 (администратор спросил «Do you have a day pass?», «What monthly memberships do you have…»,
«Where are the changing rooms?»). Стенограмма «было / стало» — §2.1.

**Причина.** `conversation_agent.v1` получал ключевые реплики ученика строкой «CHECKPOINTS (… the lines the learner is
preparing)» — без сторон: ни слова о том, что это реплики ДРУГОЙ стороны и что вопрос ученика роль не задаёт, а
отвечает на него. Модель читала их как сценарий сцены и шла по нему, включая чужие строки.

**Правка.**
- **Промпт `conversation_agent.v2`** (sha256 `9052efcd…`): `CHECKPOINTS` показывают визит обмен за обменом с обеими
  сторонами и именем стороны — «LEARNER asks: … = перевод → YOU answer: …» у ask-обмена, «YOU: … → LEARNER answers: … =
  перевод» у answer-обмена; правило **TWO SIDES** («You play ONLY YOUR_ROLE… LEARNER lines and PLAN_PHRASES are the
  learner's part, never yours… YOU are the one who answers it — never the one who asks it»); правило «HISTORY may be
  muddled» — история боя сама полна перевёрнутых строк v1, и роль не должна продолжать их; открытие — своей репликой
  YOU или приглашением спросить. v1 снят (`git mv`), версия = имя файла.
- **Страховка кода** `Domain/Service/RoleLines::learnerLineIn()`: единица ответа — предложение, у вопроса ещё и часть до
  запятой («What monthly memberships do you have in mind, …» — 43 % целым, совпадение частью); не короче 3 слов;
  совпадение `Options::share` (мультимножество слов, Жаккар) ≥ `Options::APART` = 0,5 с любой репликой ученика плана;
  вопрос против утверждения — не совпадение («Yes, parking is included.» — 75 % от «Is parking included?», и это ОТВЕТ);
  эхо того, что ученик уже сказал в разговоре, — не нарушение (врач повторяет «Your son has a fever» за родителем).
- **Один перезапрос** (`ConversationMoves::checked()`): ответ с репликой ученика не озвучивается, ход просится ещё раз с
  `REDO: learner_line — do not say «…»: it is a LEARNER line…`. Отвергнутый ответ в `REDO` НЕ цитируется: повтор на
  стенограммах показал, что `mini`-модель, которой показали её ответ, возвращает его же. Оба вызова — в счёт хода
  (`TurnCost::plusModelCall`). Второй ответ тоже с репликой ученика — предложение с ней вырезается из ответа и из
  перевода, если число предложений совпадает и что-то остаётся (`withoutLearnerLines`, счётчик `…_cut`); иначе ответ
  говорится как есть (`…_kept`) — страховка не делает ход хуже, чем без неё, и не превращает его в «не отвечает».
  Второй вызов молчит — остаётся первый ответ.
- **Счётчики** `plan_check_counters` под версией `conversation_agent.v2`: `conversation.learner_line`, `…_cut`, `…_kept`.

**Итог на тех же входах**: v1 — 11 переворотов; v2 первым ответом — 4; после страховки — **0** (4 перезапроса, 2
выреза), $0.017005 за 15 ходов с перезапросами. Живой разговор на e2e с ask-обменом — §2.2: роль открыла своим вопросом,
на ask-реплику родителя «How often should I give the paracetamol?» ответила сама («Give it every six hours if the fever
stays high.»), перезапросов 0. Одно несовершенство — §9 п. 3.

### 1.2. «Ещё раз» не снимает «пройден» с этапа

**Причина.** Закрытие этапа и дня, ряд «Разговор» в окне и кабинете, итог дня и возвраты читали
`ConversationRepository::latestForDay()` — ПОСЛЕДНИЙ разговор дня. «Ещё раз» заводит новый разговор, и этап снова «идёт»:
день не закрыть, пока второй не доведён (CLIENT-CONV-1a §5 п. 15, стенд e2e: план `01M2H13E1QT6F5D4FKJSEKTAD7`, день 1).

**Правка** (DECISIONS п. 367, дух п. 298):
- **Журнал `plan_stage_passages`** (миграция `2026_09_21_200000`): одна строка на (день, этап), append-only — без
  `updated_at`, запись `insertOrIgnore`, уникальный индекс `(day_id, stage)` = правило «пройден один раз»; CHECK на семь
  этапов; `conversation_id` — разговор, который этап прошёл (он и есть результат дня).
- **Один писатель** — `Application/Service/ConversationPassing::mark()`, в транзакции разговора, из обеих дверей, через
  которые разговор кончается (первая реплика и ход). Этап проходит **первый разговор дня, окончившийся сам**
  (`Conversation::passesStage()`: `natural` / `limit` / `declined`; `replayed` — оборван «Ещё раз», не окончен).
- **Читатели** — `CloseDayHandler`, `CloseStageHandler`, `DayWindowStages` / `RouteStages` (`TalkStage`: ahead / open /
  passed), `PlanViews`, `GetDayRoomHandler`, `DayDealer` (завтрашние возвраты — из разговора, прошедшего этап).
- **Повтор на проводе** — `replay: true` у разговора поверх пройденного этапа (этап прошёл другой разговор, окончившийся
  раньше, чем начался этот); у повтора `summary.returns_tomorrow: false`.
- **Дни до журнала** — команда `php artisan plan:reconcile-talks {--dry}`: читает журнал разговоров, для каждого дня
  без прохождения пишет первый собственный конец, печатает «было / стало»; идемпотентна. Ручной UPDATE вместо неё
  запрещён (правило живёт в `ConversationPassing` и нигде больше). Стенд e2e: 1 запертый день → записан, повторный
  запуск — 0 (§2.6). **На бою** — два разговора Дена, оба окончились сами: репетиция, день 3 плана
  `01M2TSRM3DJPGCR5VQNBE8N3S7` (план удалён, день `in_progress`), и зал, день 1 плана `01M32DX8QCABM348XP45Z1ZD4M`
  (закрыт). Без прохождения новый код прочёл бы их этап как не пройденный; путь — §6.

### 1.3. `summary.minutes` — время разговора, не часов

**Причина.** `Conversation::minutes()` считал `ended_at − started_at`: разговор, начатый утром и законченный днём, дал
«323 минуты», а `minutes_spent` дня — 436 (CLIENT-CONV-1a §5 п. 12).

**Правка** (п. 368): `activeSeconds()` — сумма промежутков между соседними строками журнала, каждый не больше
`MAX_GAP_SECONDS` = 60; `minutes()` — вверх до минуты, не меньше одной. Те же минуты — вклад разговоров дня (прошедшего
этап и повторов, `allForDay`) в `minutes_spent` при закрытии. Тесты: разговор, пролежавший открытым пять часов между
двумя репликами, — **2 минуты** (юнит и API).

### 1.4. Переспрос

**(а) Слова.** Ход `rescue` писался с пустым `text_target` — клиент ставил пометку «переспросил» вместо пузыря «Sorry?»
кадра 37-7 (§5 п. 11). Теперь ход пишется со строкой `rescue_line` пакета языка цели: en «Sorry?», ro «Poftim?», uk
«Перепрошую?», ru «Простите?» (`LanguagePack::rescueLine()`); присланное клиентом в `heard` на переспросе не читается.

**(б) Проще, а не слово в слово.** Оба живых захода CLIENT-CONV-1a получили переспрошенную реплику обратно дословно (§5
п. 14): v1 просил «the same thing simpler and slower», и модель повторяла. v2: «say the SAME meaning again in DIFFERENT
words — simpler and shorter than before, never your previous line word for word». Страховка: ответ на `rescue`,
совпадающий с переспрошенной репликой (`Conversation::lineBeforeLastMove()`) по `Options::share` ≥ 0,7
(`RoleLines::SAME_WORDS`), просится ещё раз с `REDO: same_words` (здесь реплика цитируется — «те же слова» и есть
ошибка); счётчики `conversation.rescue_same_words` / `…_kept`. Живьём: «How long has he had it?» → «Sorry?» → «How long
has he been sick?» (§2.3).

### 1.5. Лестница «Фраз» и день зала Дена

**Разбор дня 1 зала** (`tools/ladder-den.php` — день раздаётся заново в памяти тем же `DayDealer::outline()`, сессия
только на чтение; старый код — живой стек, новый — дерево наряда): **«That works for me on ___» вышел с одним кругом из-за
ЛЕСТНИЦЫ, не из-за фильтра швов.** Оба значения («weekdays / weekends») стоят в окне карточки — фильтр швов ничего не
скрыл; урезала прежняя третья ступень «второй круг значений»: этап без неё стоил бы 823 с при потолке 690, и лестница
сняла второй круг у **шести каркасов из семи** (p2–p7), оставив 673 с. Урок intermediate дал по два значения на каркас,
а третьего круга, который снимала бы ступень 2, у таких каркасов нет — резать было нечего, кроме второго.

**Правка** (п. 370, меняет п. 354): у каркаса с двумя значениями и больше два круга — пол, не ступень
(`PhrasesStage::ROUNDS_FLOOR`); ступени: (1) третье узнавание — не добавляется, (2) третий круг — снимается у каркасов,
чьи значения диалог говорит реже, (3) стоп-сигнал — этап раздаётся с превышением. Круг «со своим словом» не снимается
никогда, как и было.

**Что вышло** — таблица в шапке: на обоих живых днях «Фразы» за 800 с и карточки дня 33 минуты; чистый «врач» фикстур не
изменился. Сигнал — архитектору.

### 1.6. Эхо и «Повтори свою реплику» — только реплики ученика

**Причина.** `speak_echo` брал самую длинную реплику СОБЕСЕДНИКА ≤ 18 слов, не занятую `listen_pace` (`PartnerLines`), —
день зала попросил Дена сказать правила администратора «Please bring a towel, use clean shoes, and return the locker
key after training» (§2.5). `speak_retell` уже стоял на реплике ученика (BACK-TAILS-1 §1.1), `speak_answer` — тоже.

**Правка** (п. 371): эхо и пересказ делят свободные (без `speak_answer`) реплики ученика ≤ 18 слов полных обменов
`answer`/`ask` (`SpeakStage::learnerLines()`, `MAX_WORDS`): пересказ — самую длинную, эхо — следующую; свободной для эха
нет — самую длинную реплику дня, кроме пересказа; реплик ученика нет — эха нет. Карточка эха несёт `own_line`;
**`partner_line` — ТА ЖЕ реплика**, временно: сборка 1.0.0 (17) на телефоне читает звук эха под этим ключом и пропустила
бы карточку без него (а пропущенная карточка — этап, который не закрыть). **Проверены все три вида этапа**: на дне зала
после правки все восемь карточек «Говорю сам» — реплики ученика (§2.5), тесты — §3.

### 1.7. Судья «Ответь своими словами» — по смыслу

**Причина.** `SlotJudge` первым шагом требовал слова каркаса (режим `free`) у ОБОИХ судимых видов: «Yes it is my first
visit» на «Is this your first visit here?» при каркасе «This is ___.» получил три отказа кодом подряд, модель даже не
спросили («Каркас не прозвучал — скажи его целиком», `den/judge-calls.json`, вход Дена). А на «That works for me» без
дней модель сказала «Ты не сказал слово в пропуске» — словами, которых ученик не видит.

**Правка** (п. 372): `slot_judge.v3` (sha256 `03541139…`) и вход `MODE`.
- `answer` (`speak_answer`): каркас дословно не нужен. Код зачитывает известное значение окна, услышанное подряд (у
  каркаса без окна — его слова); остальное судит модель: осмысленный ли это ответ на `PARTNER_LINE`, говорящий то, о чём
  подсказка окна. «"Yes, it is my first visit" is a right answer to "Is this your first visit here?" whatever PATTERN
  is» — строкой в промпте.
- В обоих режимах код отказывает без модели и капа: ничего не услышано — «Не расслышал — скажи ещё раз»; сверх слов
  каркаса только служебные слова пакета (`function_words`) — **«Не сказал главного — {подсказка окна}»**: «That works for
  me» → «Не сказал главного — в какие дни это подходит» (`NativeStrings::judgeReason`, ru/uk/en).
- `reason_native` модели — по смыслу, словами подсказки, без «слот / пропуск / окно / каркас / шаблон».

Живьём на попытках Дена — 8 из 8 верно (§2.4). Тесты на промт-фикстурах — `SlotJudgeTest`, `SlotJudgePromptTest` (§3).

### 1.8. Ложный отказ полотенцу — причина и правка

**Запрос из журнала боя** (`api_request_logs`, исходящий, 21.09 17:00:45 UTC, `den/judge-calls.json`, id
`01M32EKD3WG6R0EK21SNRWNJ9G`): `PATTERN: I'll return ___.`, `SLOT_HINT: что нужно вернуть`, `EXAMPLE_VALUES: the locker
key; the access card`, **`HEARD: I will return the towel`** — услышанное дошло целым, распознавание ни при чём;
**`PARTNER_LINE: Please bring a towel, use clean shoes, and return the locker key after training.`** Ответ модели:
`accepted: false`, «Ты не назвал то, что нужно вернуть после тренировки». Минутой позже «I will return the best key» —
зачёт.

**Причина — промпт и вход.** Круг «со своим словом» судился как ответ собеседнику: v2 давал модели реплику, где полотенце
надо ПРИНЕСТИ, а вернуть — ключ, и модель прочла подсказку «что нужно вернуть» как «то, что назвал администратор».
Упражнение же — сказать каркас со СВОИМ значением.

**Правка.** Режим `own_value`: `PARTNER_LINE` пуст; «SLOT_HINT names the KIND of value, not the one right answer: for
"что нужно вернуть" any thing one returns is right, "the towel" as much as "the locker key". It does NOT have to be one of
EXAMPLE_VALUES or anything the scene mentioned». Живьём: «I will return the towel» — зачёт (§2.4, п. 6). **В ответ судьи
добавлен `heard`** — как пришло (`JudgeOutcome::heard`, `PlanJson::judge`): клиент печатает «услышал: …», и ученик видит,
ослышался распознаватель или ошибся судья.

### 1.9. «Что было хорошо» — когда пройдены все этапы

**Причина.** `window.highlights` считались только у `WindowStatus::Passed` — у закрытого дня, а 30-7 стоит ДО «Закрыть
день» (§5 п. 2). **Правка** (п. 373): `DayWindowStages::allWalked()` — все этапы состава пройдены (карточки — своим
счётом, разговор — журналом прохождений); у дня-сцены, репетиции и повторения одинаково. Тест — API: пусто, пока хоть
один этап не пройден; строки появляются до `POST …/close`.

### 1.10. `targets[]` — что сказать в разговоре

**Правка** (п. 374): документ разговора несёт `targets[]` — `{scene_id, ref, text_target, text_native, said}`, до семи
фраз плана по чекпойнтам по порядку (`Domain/Service/ConversationTargets`: внутри сцены — в том порядке, в каком ученик
говорит их в визите, потом каркасы без реплик; у разговора по нескольким сценам места раздаются по одному, сцена за
сценой). `scene_id` сверх заказанного — у репетиции `ref` повторяются между сценами (`p1` каждой). `said` сервер
пересчитывает каждым ходом тем же `SpeechMatch`. Итог (`phrases_used / phrases_total`, `phrases[]`) и завтрашние
возвраты (`DayDealer::unsaidInTalks`) считаются **по тому же списку**. `phrases_used` хода — ссылка и текст
(`text_target`, `text_native`, §5 п. 1). Handoff под 37-5 и полоску ленты — §8.

### 1.11. `hints.native` — придаточным

**Причина.** Намерение — своя реплика ученика на родном, то есть предложение: «У моего сына температура.» — клиент
чинил его сам (`TalkTexts.clause`, §5 п. 10). **Правка** (п. 375): `Domain/Service/IntentClause` — без одной закрывающей
точки, первая буква строчная, если вторая строчная (аббревиатура остаётся: «США …»), «?», «!», «…» и кавычки остаются;
идемпотентно. Применяется при чтении (`ConversationViews`), поэтому строки, записанные до правила, выходят так же. Живьём
на проводе — «у моего сына температура» (§2.2). Клиент снимает свою правку — §8.

### 1.12. Строки входа, число сцен, `usage`, enum этапов

- `talk_title_native` — «Поговори с врачом» (п. 375): роль первой сцены в творительном по правилу ru/uk
  (`Domain/Service/InstrumentalRole`: прилагательные согласуются с главным словом, генитив после него не трогается,
  «HR-менеджер» держит «HR»); где окончание зависит от ударения и слова нет в списке («-ец», шипящие, «-ь») — «Поговори с
  собеседником»: нейтральное слово лучше неверного окончания. Тот же заголовок — в ряду разговора окна.
- `scenes_count` в ряду разговора окна — «Разговор целиком · 3 сцены» до `POST` (§5 п. 4).
- `phrase_intro.usage` — `{exchange, line, offset, length, partner_line}`: первая реплика ученика в визите на каркасе,
  реплика собеседника того же обмена, место фразы в реплике (`CardObjects::usage`, §5 п. 5).
- OpenAPI: `PlanWindowStage.stage` — все семь этапов с `recall` и `conversation` (§5 п. 7); новые поля — в схемах
  `PlanConversation`, `PlanWindowStage`, ответе судьи, `speak_echo`, `phrase_intro`. `composer lint:openapi` — ok.

### 1.13. Хук ворот — каждая команда `git commit` в цепочке

**Причина.** Хук брал ПЕРВОЕ слово `git` команды и смотрел его подкоманду: `git add … && git commit …` читался как `add`
и проходил без ворот и без предупреждения — молчаливый `SKIP_GATES`. Документированный обход `SKIP_GATES=1` при этом
работал только из окружения самого хука, которое сессия задать не может.

**Правка** (п. 377): команда режется на простые по `&&`, `||`, `;`, `|`, переводу строки, скобкам подоболочки, обратной
кавычке и `$(`, читается сквозь кавычки (`bash -c 'git commit …'`); в каждой простой команде — `git` за опциями (`-C`,
`-c`, `--namespace`, `--work-tree=`) и подкоманда `commit`; `--help`/`--dry-run` — не коммит; первый найденный коммит
решает, где гонять ворота, и просил ли он обход — `SKIP_GATES=1` в окружении хука **или на самом коммите**; обход всегда
предупреждает. Харнесс — `tools/hook-detect.sh` (без коммитов и без ворот): старый хук не видел **7 форм из 17**, новый
видит все, лишних не видит (§2.7).

### 1.14. Рубильник на бою — §7

Кэша конфига на бою нет, переменная не приходит в контейнеры через compose — `.env` читается при старте процесса. На
бою `true`, так и оставлено. Процедура — §7; устаревший комментарий в `config/plan.php` исправлен.

---

## §2. Стенограммы «было / стало»

### 2.1. Роли — те же входы, v1 против v2 со страховкой (`live/replay-den.txt`)

«Было» — что роль сказала Дену 21.09 (v1). «Стало» — v2 на той же истории и том же услышанном. ← — реплика ученика в
устах роли.

| # | ход | услышано | было (v1) | стало (v2 + страховка) |
|---|---|---|---|---|
| 1 | start | — | Hello, this is the agent. **Is this flat two rooms?** ← | Hello. Yes, this flat has one living room and one bedroom. What would you like to know? |
| 2 | said | Hello yes this flight showrooms | Yes, it is a two-room flat. What is the rent you want to ask about? | ~~…What is the rent?~~ ← REDO → ← → **вырезано**: «Yes, it has a living room and one bedroom.» |
| 3 | said | It is two room flat | Yes, it is a two-room flat. **What is the rent?** ← | ← REDO → ← → **вырезано**: «Yes, it has a living room and one bedroom.» |
| 4 | said | The rent is $800 per month | Yes, the rent is $800 per month. **Is parking included?** ← | The rent is £1,200 a month, not including bills. What would you like to know next? |
| 5 | said | No parking is not included | Right, parking is not included. **Are dogs allowed?** ← | That's right, parking isn't included. There is a paid car park nearby. |
| 6 | said | Yes dogs allowed but it will cost double deposit | Yes, dogs are allowed, but there is an extra deposit. **How much is the deposit?** ← | Yes, dogs are allowed, but there is a £100 pet fee. The building also requires dogs to be registered with management. |
| 7 | said | Deposit is one month rent | Yes, the deposit is one month's rent. **Can we meet Friday evening?** ← | ← REDO → «Yes, the deposit is one month's rent. It's a twelve-month minimum term too.» |
| 8 | said | No Friday evening I am busy | Okay, Friday evening does not work. Can we meet on another day? | Friday at six works. Please bring your ID and recent payslips. |
| 9 | said | Yes | Great, Friday works. **Can I see the kitchen?** ← | Great. Friday at six works. Please bring your ID and recent payslips. |
| 10 | said | There are Kitchen but it's studio | Yes, there is a kitchen, and it is a studio. **Can I see the kitchen?** ← | Of course. The kitchen is this way, next to the living room. |
| 11 | start | — | Hello! Welcome to the gym. How can I help you today? | Hello, welcome to the gym. Is this your first visit here? |
| 12 | said | Hello I need daily training | **Do you have a day pass?** ← | ← REDO → «Sure — a day pass is fifteen dollars.» |
| 13 | said | No I want to buy monthly membership | We have a few monthly memberships. This is your first visit, right? | We have a basic plan and an unlimited plan. |
| 14 | said | Yes it is my first visit | Great, then I can show you the monthly options. **What monthly memberships do you have in mind**, and do you need weekdays only? ← | Great. We open at six and close at ten on weekdays. What would you like to know next? |
| 15 | said | Weekdays works for me | Great, that works. **Where are the changing rooms?** ← | That works for me on weekdays. Please note that we open at six and close at ten on weekdays. (§9 п. 3) |

**Переворотов: v1 — 11 · v2 первым ответом — 4 · после страховки — 0** (перезапросов 4, вырезов 2). $0.017005.

### 2.2. Живой разговор на e2e с ask-обменом (`live/talk-doctor.txt`)

«Врач», день 1 плана `01M2H13E1QT6F5D4FKJSEKTAD7` (beginner), 4 хода сцены, повтор поверх пройденного этапа.

```
 1 РОЛЬ   What seems to be the problem today?                     (своя реплика роли, не ученика)
 2 УЧЕНИК My son has a fever.                                     · p1
 3 РОЛЬ   How long has he had it?
 4 УЧЕНИК [rescue] Sorry?                                         (слова переспроса — из пакета)
 5 РОЛЬ   How long has he been sick?                              (тот же смысл, другие слова)
 6 УЧЕНИК He has had it for three days.                           · p2
 7 РОЛЬ   How high is his temperature?
 8 УЧЕНИК How often should I give the paracetamol?               · p6 (ask-реплика ученика)
 9 РОЛЬ   Give it every six hours if the fever stays high.        (роль ОТВЕЧАЕТ, не спрашивает)
ИТОГ: сказал сам 3 · фразы 3 из 7 · переспросов 1 · конец natural · минут 1 · вернётся завтра: нет (повтор)
подсказка на проводе: «у моего сына температура» — придаточным
модель $0.005351 · голос $0 (без звука) · ход p50 1278 мс, максимум 1425 мс · перезапросов 0
```

Документ нового вида целиком — `live/conversation-day-ended-v2.json` (`replay: true`, `talk_title_native`, `targets[]`,
`phrases_used` с текстом, ход `rescue` с «Sorry?»).

### 2.3. Переспрос

- **Было** (CLIENT-CONV-1a, оба захода): реплика роли 3 → «Не понял» → реплика 5 — слово в слово та же.
- **Стало**: «How long has he had it?» → `Sorry?` → «How long has he been sick?» (§2.2, ходы 3–5); страховка не
  понадобилась. Правило ≥ 0,7 прибито тестом API (`asks for other words when a rescue says the line again`).

### 2.4. Судья — попытки Дена 21.09, судимые заново (`live/judge-den.txt`)

Карточки дня 1 зала (`den/gym-day1-cards.json`), попытки — что прислал телефон (входящий журнал `…/judge`), судья —
`SlotJudge` + `slot_judge.v3` против плана той же пары и уровня на e2e.

| # | карточка | услышано | было | стало |
|---|---|---|---|---|
| 1 | `speak_answer` x3 | Yes it my first visit | код: «Каркас не прозвучал — скажи его целиком» | код: зачёт (my first visit) |
| 2 | `speak_answer` x3 | Yes it is my first visit | код: «Каркас не прозвучал…» | код: зачёт (my first visit) |
| 3 | `speak_answer` x3 | Yes it is my first day | код: «Каркас не прозвучал…» | модель: зачёт (my first day) |
| 4 | `speak_answer` x4 | That works for me | модель: «Ты не сказал слово в пропуске.» | код: **«Не сказал главного — в какие дни это подходит»** |
| 5 | `speak_answer` x4 | That works for me for weekdays | код: зачёт (weekdays) | код: зачёт (weekdays) |
| 6 | `phrase_other_slot` p6 | I will return the towel | модель: «Ты не назвал то, что нужно вернуть после тренировки.» | модель: **зачёт (the towel)** |
| 7 | `phrase_other_slot` p6 | I will return the best key | модель: зачёт | модель: зачёт |
| 8 | `speak_answer` x6 | OK I will return | модель: «Ты не сказал, что именно вернёшь.» | код: «Не сказал главного — что нужно вернуть» |

8 из 8 — как сказал бы человек; модель спрошена трижды (пп. 3, 6, 7), остальное решил код; $0.001958.

### 2.5. Лестница и эхо — день 1 зала Дена (`live/ladder-den-before.txt` / `-after.txt`)

```
было:  «Фразы»: 29 карточек · 673 с               стало: «Фразы»: 29 карточек · 823 с
  p1 Do you have ___?          (3 знач.) 2 круга + своё     2 круга + своё
  p2 What ___ do you have?     (2 знач.) 1 круг  + своё     2 круга + своё
  p3 This is ___.              (2 знач.) 1 круг  + своё     2 круга + своё
  p4 That works for me on ___. (2 знач.) 1 круг  + своё     2 круга + своё
  p5 Where are ___?            (2 знач.) 1 круг  + своё     2 круга + своё
  p6 I'll return ___.          (2 знач.) 1 круг  + своё     2 круга + своё
  p7 Can I pay ___?            (2 знач.) 1 круг  + своё     2 круга + своё
день: 84 карточек · 1816 с = 31 мин               день: 84 карточек · 1966 с = 33 мин (потолок 32)
эхо:  x6 СОБЕСЕДНИК «Please bring a towel, use clean shoes, and return the locker key after training.»
                                                   эхо:  x1 ученик «Do you have a day pass?»
```

«Врач» e2e, день 1 (`live/ladder-doctor-e2e-*.txt`): «Фразы» 685 → 810 с (p3–p7: 1 → 2 круга), день 1798 → 1923 с;
эхо — x5 СОБЕСЕДНИК «It looks like a throat infection. Give paracetamol, warm drinks, and rest at home.» → x2 ученик «He
has had it for three days.».

### 2.6. «Ещё раз» — день стенда до и после команды (`live/reconcile-e2e-*.txt`)

```
было (новый код, до команды):                     стало:
окно: conversation — current                      окно: conversation — done, «Поговори с врачом», сцен 1
кабинет: conversation — current                   кабинет: conversation — done
«Что было хорошо»: —                              «Что было хорошо»: Сказал сам 8 реплик из 8 · В разговоре
прохождение: нет                                     использовал 4 фразы из 7 · Понял все вопросы
                                                  прохождение: разговор 01M323Y9P4CX41BZCVGV2PTPHX
разговоры дня: 01M323Y9… ended natural; 01M324FF… your_turn («Ещё раз», открыт)
plan:reconcile-talks → «дней … было 1 / стало 0 · записано прохождений: 1»; второй запуск — «было 0 / стало 0 · 0»
```

### 2.7. Хук — какие команды он принимает за коммит (`live/hook-detect-*.txt`)

| команда | было | стало |
|---|---|---|
| `git commit -m "x"` | да | да |
| `git add backend2/app && git commit -m "x"` | **—** | да |
| `git add -A; git commit -m "x"` | **—** | да |
| `(cd backend2 && git add . && git commit -m "x")` | **—** | да |
| `git stash && git pull --rebase && git commit -am "x"` | **—** | да |
| `git add x \| tee log && git commit -m "y"` | **—** | да |
| `bash -c 'git commit -m x'` | **—** | да |
| `git -C /tmp/wt commit`, `git -c user.name=x commit`, `git --work-tree=/tmp/wt commit`, `cd backend2 && git commit` | да | да |
| `SKIP_GATES=1 git commit -m "wip"` — обход | ворота (обход не виден) | обход с предупреждением |
| `git add x && SKIP_GATES=1 git commit -m "wip"` | **—** (ни ворот, ни предупреждения) | обход с предупреждением |
| `git status && git log`, `git commit --dry-run`, `git log --grep=commit`, `echo done \| cat` | — | — |

---

## §3. Тесты — правило и дефект каждого

Модель в воротах — `FakePlanModel` (v2/v3; на `rescue` фейк перефразирует, как велит промпт). Новых тестов 30, заменено 6
(старые удалены вместе с правилом, которое они держали).

| файл | тест | правило (канон) | ловит |
|---|---|---|---|
| `Unit/Plan/RoleLinesTest` | catches the lines the gym receptionist said in the member's place | п. 1: реплика ученика в устах роли — ≥ 0,5 | страховку, читающую ответ только целиком (43 %), и роль, открывающую первой репликой ученика — на репликах боя |
| | catches the tenant's questions the agent asked in the rehearsal | п. 1 | 8 из 9 вопросов агента по репликам боя; собственный вопрос агента после отказа не трогается |
| | leaves the role's answers alone: an answer in the question's words, an echo of the learner, a short line | п. 1 | страховку, которая отсылает обратно каждый ответ-эхо (в сцене, где спрашивает ученик, — каждый ход дважды) |
| | cuts the learner's line out of an answer that keeps saying it… | п. 1, «одна попытка» | вырез, после которого перевод говорит то, чего реплика уже не говорит, и вырез до пустоты |
| | takes a rescue for the same line when it says the same words… | п. 4б: ≥ 0,7 | переспрос, повторяющий реплику, и страховку, отказывающую настоящему перефразу |
| `Unit/Plan/ConversationPromptTest` | keeps the role's prompt frozen under its own version, with the two sides, the rescue and REDO in it | пп. 1, 4б: v2 заморожен (sha) | правку файла под старым именем и v2 без сторон, без перефраза и без REDO |
| | shows both sides of every exchange, names whose they are, and writes REDO on the second try of a move only | п. 1 | заголовок «the lines the learner is preparing» (вход разговоров 21.09) и REDO, протёкший в первую попытку; REDO не цитирует отвергнутый ответ |
| `Feature/Plan/ConversationApiTest` | asks the role again when it says the learner's line, once, with the reason, and counts it | п. 1 | перезапрос без причины, второй перезапрос, неоплаченный второй вызов, пропущенный счётчик |
| | takes the second answer whatever it says, and keeps the first when the second does not come | п. 1 | ход, упавший в «не отвечает» из-за страховки |
| | cuts the learner's line out when the second answer says it too, and says the rest | п. 1 | реплику ученика, озвученную со второй попытки |
| | asks for other words when a rescue says the line again, and counts it | п. 4б | переспрос без страховки |
| | keeps the stage walked through «Ещё раз», closes the day on the first talk, and returns what that talk did not hear | п. 2 | этап, снятый повтором; 409 на закрытии после «Ещё раз»; возвраты из повтора |
| | writes the passages of talks that ended before the journal of stages, once, from the first natural end | п. 2 | команду, пишущую дважды, пишущую `replayed` или не первый конец |
| | reports the minutes the talk was talked, not the hours it stood open | п. 3 | стенные часы в `summary.minutes` и `minutes_spent` |
| | writes «Что было хорошо» once every stage is walked, before the day is closed, and nothing before that | п. 9 | строки только у закрытого дня (заменил тест «on the day it is passed») |
| | carries the talk's targets, ticks them off turn by turn, and names the heard phrases with their text | п. 10 | итог, считающий не тот список, и `phrases_used` без текста |
| | names the talk and counts its scenes on the talk's row of the window | п. 12 | ряд окна без заголовка и числа сцен |
| `Unit/Plan/ConversationTest` | counts the minutes the talk was talked, not the hours it stood open | п. 3: промежутки ≤ 60 с | пять часов простоя = 2 минуты |
| | walks the stage by an end of its own and never by being replayed | п. 2 | `replayed`, проходящий этап |
| | knows which line of the role the learner's last move answers | п. 4б | страховку переспроса, сравнивающую не с той репликой |
| | reads the summary off the journal … and which targets did not sound | п. 10 | итог по всем фразам сцен вместо целей |
| `Unit/Plan/ConversationTargetsTest` | asks a talk for its phrases in the order of its scenes and their visits, seven at most, shared between scenes | п. 10 | семь фраз первой сцены у репетиции, чужой порядок, больше семи |
| | sends the intention as a clause: no capital, no closing full stop, the rest as written | п. 11 | «Скажи, что У моего сына температура.»; снятые «?» и аббревиатуры |
| | names the talk «Поговори с врачом», by rule, and says «с собеседником» where the ending would be a guess | п. 12 | «Поговори с Врач» и неверное окончание по ударению |
| `Feature/Plan/SlotJudgeTest` | passes «Yes it is my first visit» and refuses «That works for me» with the days named, both without the model | п. 7 | три отказа кодом у ответа своими словами; «слово в пропуске» |
| | refuses by code a round of «Скажи целиком» without its frame, and hears an answer in other words as an answer | п. 7 | снятую проверку каркаса у круга «со своим словом» (заменил «rejects by code an attempt without the frame») |
| | asks the model about an own value in the mode own_value, with what was heard, and says it back | п. 8 | `PARTNER_LINE` в режиме своего значения; ответ судьи без `heard` |
| `Unit/Plan/Session/SlotJudgePromptTest` | (правлены в месте) | пп. 7–8: v3 заморожен (sha `03541139…`), `MODE` первой строкой входа | правку файла судьи под старым именем |
| `Unit/Plan/Session/PhrasesStageTest` | cuts «Фразы» in exactly one order under a lower ceiling and stops at two rounds — the floor is not a rung | п. 5 | третью ступень «второй круг» (заменил «stops at the floor — the own word survives it») |
| `Unit/Plan/Session/SpeakStageTest` | echoes a line of the learner's own — never the partner's | п. 6 | эхо на реплике собеседника — день зала (заменил «echoes the longest partner line…») |
| `Unit/Plan/Session/HelpersTest` | picks the pace line by length, the lower step between equals | п. 6 | `PartnerLines` без исключения, которое было нужно только эху |

Правлены в месте под новое правило: `RouteStagesTest` (ряд разговора по журналу прохождений), `SessionDayAssemblyTest`
(состав «Говорю сам»), `ModelCallJournalTest` (второй вызов хода), фикстуры клиента `docs/fixtures/day-doctor*.json`
(эхо на реплике ученика, `usage` у `phrase_intro`; тест держит их байт-в-байт).

---

## §4. Что снесено

| что | где было | чем заменено |
|---|---|---|
| `conversation_agent.v1.md` | `Infrastructure/Prompt/` | `conversation_agent.v2.md` (`git mv`, v1 — в git) |
| `slot_judge.v2.md` | `Infrastructure/Prompt/` | `slot_judge.v3.md` (`git mv`) |
| третья ступень лестницы «второй круг значений» и нижняя граница «1 круг» | `PhrasesStage::build()` | пол `ROUNDS_FLOOR = 2`, стоп-сигнал |
| эхо на реплике собеседника, `PartnerLines::SPEAK_MAX_WORDS`, исключение pace-строки для эха | `SpeakStage`, `PartnerLines` | `SpeakStage::learnerLines()`, `MAX_WORDS` |
| «пройден = последний разговор дня окончен» (`latestForDay()->isEnded()`) | `CloseDayHandler`, `CloseStageHandler`, `DayDealer`, окно и кабинет | журнал `plan_stage_passages` |
| минуты по стенным часам | `Conversation::minutes()` | `activeSeconds()` |
| «каркас не прозвучал» у `speak_answer` | `SlotJudge` | суд по смыслу (`MODE answer`) |
| шесть тестов старых правил | §3, «заменил» | новые тесты того же места |

---

## §5. Хеши и ворота

**Промпты** (заморожены, версия = имя файла): `conversation_agent.v2.md` — sha256
`9052efcd4409e197de503138cd9f0488e2eda7188a8e2ce9f89672c873cf0b04`; `slot_judge.v3.md` — sha256
`035411394c79d4e6f3d814f96933eecf2f7b56323e32f2cd26525cff26d7b6e4`.

**Коммиты** (ветка `conv-2`, в `main` — fast-forward): код — `ffad4461`; документы — {{DOCS}}; шаги на бой и хеши —
последний коммит наряда.

**Ворота** (один раз в конце, на коде ветки — сайдкар `wt_conv2`, база `wordtrainer_conv2_test`): `composer check` —
OpenAPI `openapi.yaml` ok и `openapi-admin.yaml` ok (3.1.0), deptrac **0** нарушений (3 «uncovered» — давние, Identity),
PHPStan **0** ошибок, Pest **2386 passed** (18 735 утверждений, 10 процессов, 108,8 с); `flutter analyze` мобильного
дерева ветки — «No issues found». Хук ворот основного дерева прогнал свои ворота на каждом коммите ветки (его стек
смонтирован на основное дерево — код ветки он не видит, поэтому ворота выше гонялись вручную в сайдкаре ветки).

**Проверка инвариантов** (`invariant-reviewer`, по диффу): одно нарушение — **правило 6 на клиенте** (§8 п. 1); вопрос
про append-only журнала — отвечен: писатель один (`insertOrIgnore`), UPDATE/DELETE в коде нет; каскады с днём и планом
(удаление плана, укорачивание, стирание аккаунта) — общий жизненный цикл данных плана, как у `day_cards` и
`conversations`; одиночного удаления разговора в коде нет, поэтому `nullOnDelete` у `conversation_id` не срабатывает.
Остальные правила — чисто.

---

## §6. Шаги на бой

Порядок выбран так, чтобы у живого API не было ни одного запроса, где новый код ищет несуществующую таблицу или читает
старый день «не пройденным»: миграция и дописчик идут кодом ветки ДО слияния (таблица аддитивная, старый код её не
читает), потом слияние и перезапуск воркера.

{{DEPLOY}}

---

## §7. Рубильник разговора — как включать и выключать надёжно (п. 14)

**Как читается.** `plan.conversation.enabled` ← `env('PLAN_CONVERSATION_ENABLED', true)` (`config/plan.php`). На бою
кэша конфига нет — `bootstrap/cache/` держит только `packages.php` и `services.php`, `config.php` нет; переменная не
задаётся контейнерам ни через `environment`, ни через `env_file` compose (`docker inspect wt_app` / `wt_horizon` — ни
одной `PLAN_*`). Значит, `.env` читает каждый процесс при старте: веб (`wt_app`, `php artisan serve`) — на каждом
запросе, horizon — при старте воркера. Кто спрашивает рубильник: `OpenDayHandler` (раздача дня — HTTP) и чтения окна и
кабинета для ещё не открытого дня (HTTP). Воркер его сейчас не читает, но перезапуск — часть процедуры, чтобы не
зависеть от этого.

**Сейчас:** `.env` — `PLAN_CONVERSATION_ENABLED=true` (комментарий над строкой всё ещё говорит «Выключен» — `.env` не в
git, наряд на бою только читал; поправить руками при случае); `config:show` — `enabled true`. **Оставлено включённым**
(постановка наряда; DECISIONS п. 378).

**Выключить** (включить — то же с `true`), из `backend2/`:

```bash
grep -n '^PLAN_CONVERSATION_ENABLED' .env
```

1. Поправить строку в `.env`: `PLAN_CONVERSATION_ENABLED=false`.
2. Перезапустить воркер — он держит конфиг в памяти:

```bash
docker compose restart horizon
```

3. Проверить, что читает живой процесс:

```bash
docker compose exec -T app php artisan config:show plan.conversation
```

4. Не делать `php artisan config:cache` на бою, пока рубильник жив: с кэшем правка `.env` молча перестанет действовать
   (если кэш всё же появился — `php artisan config:clear` и шаг 2).

**Что делает выключенный рубильник — и чего не делает.** Он про РАЗДАЧУ: день, открытый при выключенном, раздаётся на
пяти этапах (`has_conversation = false`), закрывается без разговора, `POST …/conversation` отвечает 422. День, уже
розданный с разговором, **остаётся с ним** — состав фиксируется при первом открытии, — и закрыть его можно только после
разговора: выключение не отпирает начатые дни. Клиент (17) умеет и шесть этапов, и пять — состав читает из ответа.

---

## §8. Handoff клиенту — что появилось на проводе (наряд 1c)

1. **Нарушение инварианта «клиент не строже сервера» — чинить первым.** `SessionRules.replayAccepted`
   (`mobile/lib/data/plan/session/session_rules.dart:202`) судит `speak_answer` при повторе пройденного дня («Ещё раз» из
   итога, сервер не спрашивается) по словам каркаса — а сервер после п. 7 ответ своими словами принимает. Зеркало
   серверного правила без модели: не услышано ничего — нет; сверх слов каркаса только служебные слова (`speech` дня) —
   нет; иначе — да. Круг «со своим словом» у «Скажи целиком» — как был (каркас обязателен и на сервере).
2. **Три теста прибиты к старому эху** — фикстуры `day-doctor*.json` теперь раздают `speak_echo` на реплике ученика:
   `test/data/plan/session/session_rules_test.dart:139` и два теста 35-3 в
   `test/features/plan/session/session_speak_cards_test.dart` ждут текст реплики собеседника. Правка — ожидаемый текст;
   клиентский код эха их не касается (сборка 17 читает `partner_line`, в нём та же реплика ученика).
3. **`speak_echo.own_line`** — своя реплика (текст, перевод, звук); `expected_text` — её текст. Читать `own_line`;
   `partner_line` (та же реплика) уйдёт следующим бэкенд-нарядом после сборки, которая его не читает.
4. **Разговор, 37-5 и лента:**
   - `targets[]` — список «Скажи в разговоре» на входе 37-5 и полоска в ленте: `{scene_id, ref, text_target, text_native,
     said}`, по порядку; `said` приходит с каждым ходом. Итог 37-12 считает тот же список.
   - `talk_title_native` — готовый заголовок «Поговори с врачом»: печатать как есть, запасное «Поговори с собеседником»
     снять. То же — в ряду разговора окна; там же `scenes_count` («Разговор целиком · 3 сцены» до старта).
   - `replay: true` — «Ещё раз» поверх пройденного этапа: ряд «Разговор» остаётся `done`, «Закрыть день» доступен,
     `summary.returns_tomorrow: false`.
   - Ход `rescue` несёт `text_target` («Sorry?» на языке цели) — рисовать своим пузырём, пометку «переспросил» снять.
   - `hints.native` — придаточным: «у моего сына температура». `TalkTexts.clause` снять.
   - `turns[].phrases_used` — с `text_target`/`text_native`: подчерк в своём пузыре и для фраз чужих сцен репетиции.
   - `summary.minutes` — время разговора; клиенту менять нечего.
5. **Итог дня 30-7** — `window.highlights` приходят, как только пройдены все этапы: блок «Что было хорошо» виден на
   первом проходе, до «Закрыть день».
6. **Судья** — ответ `POST …/judge` несёт `heard`: печатать «услышал: …» под отказом. Новые строки отказа кодом: «Не
   расслышал — скажи ещё раз», «Не сказал главного — {подсказка окна}».
7. **`phrase_intro.usage`** — блок «В разговоре» 32-1 без сверки текста с диалогом: `line` (реплика ученика, звук),
   `partner_line`, `offset`/`length` фразы в реплике (null — фраза в реплике не дословно).
8. **Фикстуры разговора** `docs/fixtures/conversation-*.json` НЕ пересняты — их читают golden-снимки клиента; документ
   нового вида — `docs/research/conv-2/live/conversation-day-ended-v2.json`, пересъёмка — `dump-talk.php` клиентского
   наряда, когда он будет готов.
9. Пауза 1,5 с в разговоре — записана в DECISIONS п. 376 (клиенту ничего не делать).

---

## §9. Чего наряд не сделал, и что найдено

1. **Стоп-сигнал лестницы** — шапка отчёта, DECISIONS «Спорное» п. 2.
2. **Пункты CLIENT-CONV-1a §5, не вошедшие в наряд**: 6 (строка-суть каркаса — поля нет), 8 (`skip` на экране — клиент),
   9 (интерфейс uk/ro — наряд L10N-1), 16 (сверка фраз строже человека: «Yes, he has a sore throat.» не засчитано за «He
   also has a sore throat.»; ROADMAP, хвосты контракта разговора п. 13), 17 (контент: «Это у него уже уже три дня.» —
   виден и в `targets` стенда; там же п. 14).
3. **Роль повторила реплику ученика эхом от первого лица** — повтор, ход 15: администратор сказал «That works for me on
   weekdays». Страховка пропустила это как эхо сказанного учеником («Weekdays works for me» — 67 %). Отличить эхо «от лица
   роли» от реплики ученика мерой слов нельзя; один случай из 15, в ROADMAP.
4. **Разговоры репетиции и повторения с v2 живьём не гонялись** — живьём: день-сцена на e2e и повтор стенограмм
   (репетиция «Просмотр жилья» в нём есть). Озвучка разговора не проверялась (стенд без голоса).
5. **Мутационный прогон не делался**: тесты написаны на канон и на реплики боя; наряд его не заказывал.
6. **Фикстуры разговора** — §8 п. 8.
