# CHECK-1 · точка сокращения читается валидатором как конец предложения

Наряд 20.09.2026, репо `backend2/`, только бэкенд. Промты (`lesson_day.v4.7`, `lesson_card_repair.v1.3`,
`lesson_seam_judge.v1.1`) не тронуты. Покупок у вендоров нет: вызовов модели 0, озвучки 0, фото 0. Канон —
`docs/plan-v2.md` §4; решение — `docs/DECISIONS.md` п. 348; ROADMAP не менялся (отложенного нет).

## §1. Причина

План «Визит к ветеринару» (otrishko99a@gmail.com, beginner, en), день 1 «Запись к врачу», попытка 2 (12:23:14):
каркас `p6` «I'd like the ___ appointment.» с наполнениями «3 p.m.» и «5:30 p.m.» — здоровый английский. Их зарубила
`FillerRules::seams`:

    if (preg_match('/[.?!,;:]$/u', $filler) === 1) { $problems[] = 'the filler carries its own punctuation'; }

Проверка задумана ловить целое предложение в окне, а ловила точку сокращения — `filler.ungrammatical` фатален, P2R
починил `p6` (модель вернула ту же карточку с теми же «3 p.m.» / «5:30 p.m.» — ответ починки сохранён,
`answers/vet-day1-attempt2-repair-p6.json`), валидатор снова нашёл то же, вторая карточка — и день ушёл в `failed`
с `fatal: filler.ungrammatical`. Та же ошибка у `partner.too_long`: «We have 3 p.m. and 5:30 p.m. today.» — три
предложения по `LanguageWords::sentences` (split по `[.?!…]+`). Счётчики `lesson_day.v4.7`:
`filler.ungrammatical` counted 5 / gated 5 / failed 2, `partner.too_long` counted 1. Модель и промт ни при чём —
чинится код.

## §2. Что изменилось

**Правило** — новый класс `Plan/Domain/Check/Language/SentenceEnds` (рядом с `LanguagePack`, `LanguageWords`, `PackSkips`):

- знак из `sentence_ends` пакета кончает предложение; точка — только если слово перед ней не сокращение из ключа
  `abbreviations`; «?» и «!» — всегда;
- прогон знаков — конец только там, где предложение может кончиться: перед пробелом, закрывающей кавычкой/скобкой или
  концом текста («3.5», «5:30» не режутся); из прогона считаются только знаки не сокращения («Come at 3 p.m..» —
  вторая точка);
- умеет два дела: `terminal(text)` / `terminalKind(text)` — «кончается ли текст знаком конца» и каким; `count(text)` /
  `sentences(text)` — «сколько предложений» и какие; плюс `questionMarks`;
- ключа `abbreviations` нет (или он `null`) — список пустой, каждая точка — конец; без счётчика и без находки;
  `sentence_ends` нет — правило не строится (бросает `LanguagePackKeyMissing`), проверка спрашивает контекст первой,
  как и раньше.

`LanguageWords` держит правило: `ends()`, а `terminal`, `terminalKind`, `isQuestion`, `questionMarks`, `sentences`,
`names` — тонкие делегаты. Своих regex по точкам в `LanguageWords` больше нет.

**Пакеты** (`config/lesson/lang/<code>.php`), новый ключ `abbreviations`:

| пакет | список |
|---|---|
| en | a.m., p.m., e.g., i.e., etc., vs., Mr., Mrs., Ms., Dr., St. — **без «No.»** (§7) |
| ru | т. е., т. д., т. п., г., ул. — родная сторона читает точки (`frame.no_end_punct`, `frame.native_punct` спрашивают `terminal` у `frame_native`) |
| uk, ro | `null` — список пустой, без счётчика и без находки |

**Переведённые проверки** (всё, что нашёл grep по знакам конца в `Plan/Domain/Check` и обвязке P2R):

| проверка | было | стало |
|---|---|---|
| `filler.ungrammatical` (`FillerRules::seams`) | `preg_match('/[.?!,;:]$/u')` | `[,;:]$` без языка (как было) **или** `terminal(filler) !== ''` по правилу цели; ключ `sentence_ends` добавлен в список ключей проверки — без пакета цели эта часть пропускается со счётчиком `lang.pack_missing`, как остальная механика |
| `partner.too_long` (`PartnerRules`) | `LanguageWords::sentences` — split по `[marks]+(\s+|$)` | `SentenceEnds::count` |
| `partner.two_questions` (`asksTwice` → `questionMarks`) | подсчёт по `marks()` | `SentenceEnds::questionMarks` (поведение то же) |
| `exchange.second_question`, `learner.restates_partner` (`isQuestion`) | split последнего предложения по `[marks]+` | `SentenceEnds::sentences` — «Dr. Smith, can you come» не режется на «Dr.» |
| `check.verbatim` (`names`) | split по `[marks]+` | `SentenceEnds::sentences` — «Dr. Smith» теперь имя (было: «Smith» — первое слово «предложения» после «Dr.») |
| `frame.no_end_punct`, `frame.native_punct` (`terminal`, `terminalKind`) | rtrim кавычек + последний символ из `marks()` | `SentenceEnds::terminal` |
| сборка: `SceneMaterial::asks`, `PhraseCards`, `ListenCards` (`terminalKind === 'question'`) | через `LanguageWords` | через `LanguageWords` → правило; «?» всегда конец — поведение то же |

**Команда** `plan:check-report {--since=}` (`Plan/Infrastructure/Console`, как `plan:speak-report`, регистрация в
`bootstrap/app.php`, строка в `Plan/README.md`): только чтение — `plan_scenes.checks_json` (находки сохранённого
урока; у `failed` дня — ответ модели как написан), `plan_days`, `plans`, `plan_check_counters`. Одна строка на код:
находок, из них фатальных (по `LessonGate::FATAL`), дней с кодом, дней ушло в `failed` с ним (`fail_reason`), доля
дней с кодом от всех дней с уроком, счётчики `counted / gated / failed` по всем попыткам и версиям; ниже — 3 примера
на код: текст находки, план, день, сцена, пометка `(failed)`. `--since` — дни от момента (`generated_at`, у `failed` —
`build_started_at`); счётчики времени не знают и печатаются целиком (сказано в выводе).

## §3. Список удалённого

- `FillerRules::seams` — `preg_match('/[.?!,;:]$/u', $filler)`; на его месте `[,;:]$` + правило.
- `LanguageWords::terminal` — своя реализация (`preg_replace('/[\s»"\'”’)]+$/u')` + `array_key_exists($last, marks())`).
- `LanguageWords::isQuestion` — `preg_split('/['.markClass().']+/u')`.
- `LanguageWords::sentences` — `preg_split('/['.markClass().']+(?:\s+|$)/u')`.
- `LanguageWords::names` — `preg_split('/['.markClass().']+/u')`.
- `LanguageWords::questionMarks` — цикл по `marks()`.
- `LanguageWords::marks()` и `LanguageWords::markClass()` — приватные, снесены целиком.

Ничего не выключено флагом, папок legacy нет, deptrac чистый.

**Найдено grep'ом и оставлено (не проверка):** `FrameText::END_MARK` `[.!?…]+$` и `withEndMarkClosed` — языконезависимое
сравнение реплики с каркасом (`line.ne_frame`, `identity` для `frame.twin` / `known_repeat`) и сборка карточек: обе
стороны сравнения теряют один и тот же хвост, ни одна находка от точки сокращения там не зависит; `FrameParts` —
сборка; `ImageQueries` — запрос к фотостоку; `LessonCardRepairer` `explode('.', address)` — адрес карточки `p3.f2`,
не предложение.

## §4. Тесты — на канон

`tests/Unit/Plan/SentenceEndsTest.php` (новый, 5 тестов) и `tests/Unit/Plan/LessonValidatorTest.php` (+2):

- «3 p.m.», «5:30 p.m.», «Dr. Smith», «e.g.», «at home» в окне — `filler.ungrammatical` 0; «See you tomorrow.»,
  «Yes?», «at home,», «at home;», «at home:» — находка `p5.f3` (фатально); пакет без ключа `abbreviations` — «3 p.m.»
  снова находка;
- «We have 3 p.m. and 5:30 p.m. today.» — одно предложение; «I'm here. Are you?» — два; «Take 3.5 mg. Then rest.» —
  два; «Ask Dr. Smith. He knows.» — два; «Oh no. It hurts.» — два; «Hello» — одно; «...» и «» — ноль;
- `terminal`: «See you tomorrow.» → «.», «Yes?» → «?», «Really?!» → «!», «He said "no."» → «.», «3 p.m.» / «Dr. Smith» /
  «e.g.» / «Come at 3 p.m.» / «Come at 3 P.M.» → «», «Come at 3 p.m..» → «.», «at 3 p.m.?» → «?»; «Ask DR. SMITH. He knows.» — два (регистр списка не важен);
- пакет без ключа — каждая точка конец (`terminal('3 p.m.')` → «.», три предложения);
- ru: «Я живу на ул.» → «», «Я живу на улице.» → «.», «Т. е. так. Или нет.» — два, «Т.  е. так.» — одно (пробел внутри
  сокращения — любой прогон);
- пакет без `sentence_ends` — правило бросает `LanguagePackKeyMissing`; у валидатора пакет цели со всеми ключами швов, но без `sentence_ends`, — `filler.ungrammatical` пропущен целиком (в `skips`), «See you tomorrow.» в окне не находка и не падение;
- собеседник: «We have 3 p.m. and 5:30 p.m. today.», «Ask Dr. Smith. He is here today.» — `partner.too_long` 0;
  «I'm here. Are you? Yes.» — находка `A5`.

`tests/Feature/Plan/PlanCheckReportTest.php` (новый): день `failed` с фатальными находками + день `ready` с
предупреждением фикстуры; проверяет столбцы (находок, фатальных, дней, failed, доля, счётчики двух версий сложены),
пометку `(fatal)`, пример с планом и днём и `(failed)`, ровно 3 примера, `--since` (будущее — «No day with a lesson
since…», прошлое — есть, не дата — код 1). Тестовая база хранит дни прошлых прогонов, поэтому тест читает отчёт с
момента своего старта.

## §5. Живой день — прогон без вызовов

Ответ модели попытки 2 (12:23:14 → 12:23:51) **сохранён**: `api_request_logs` (outbound, `purpose=plan`,
`01M2WSYY27GJHX7X2GMCEWAQWT`, 28 624 байта, `usage.prompt_tokens=8325`/`completion_tokens=5685` — совпадает со
строкой `model_calls` `01M2WSXSX4S0ASG7N9HQAGJESA`). Извлечён `choices[0].message.content` → `answers/vet-day1-attempt2.json`;
рядом ответ P2R той же попытки (`answers/vet-day1-attempt2-repair-p6.json`). Инструмент — `tools/tally.php`: валидатор
текущего кода по файлу, пакеты ru/en из конфига, счёты из самого ответа, без истории дней, без записи в базу.

Результат по попытке 2 (`before.json` → `after.json`):

| код | было | стало |
|---|---|---|
| `filler.ungrammatical` | 2 (`p6.f1` «3 p.m.», `p6.f2` «5:30 p.m.») | **0** |
| `partner.too_long` | 1 (`A5` «We have 3 p.m. and 5:30 p.m. today.» — 3 предложения) | **0** |
| `frame.native_agreement` | 1 (`p1` «___ нужен ветеринар.») | 1 |
| `vocab.abbreviation` | 1 (`v6` «ID» — предупреждение по замыслу, вне наряда) | 1 |
| фатальных | 2 | **0** |

То есть на новом коде попытка 2 прошла бы порог без P2R.

**Было/стало по выгрузкам `docs/research/gen-3/answers/`** (6 тем × 3 файла: день 1, день 2 v4.5, день 2 v4.6 — 18
ответов тем же инструментом): **ни одной разницы ни по одному коду** — `filler.ungrammatical` 0 → 0, `partner.too_long`
0 → 0, `frame.no_end_punct` 0 → 0, `check.verbatim` 16 → 16, `exchange.second_question` 1 → 1, всего 19 файлов, 115 находок
до и после (полные тallies — `before.json` / `after.json`). Сокращений с точкой в этих выгрузках модель не писала.

## §6. `plan:check-report` по боевой базе (20.09, 13 дней с уроком, 115 находок)

`EXPLAIN ANALYZE` запроса находок (join `plans`, left join `plan_days`, `jsonb_array_elements with ordinality`,
фильтр по статусу и `--since`): Seq Scan `plan_scenes` (17 строк) → Hash Join → Function Scan → Sort, **Execution Time
0.48 ms**, Planning 1.0 ms. Индекс не нужен: таблица сцен — десятки строк, находок — сотни; миграции нет.

| код | находок | фатальных | дней | failed | доля дней | counted / gated / failed |
|---|---|---|---|---|---|---|
| check.verbatim | 24 | 0 | 9 | 0 | 69.2 % | 26 / 0 / 0 |
| filler.native_seam | 16 | 0 | 6 | 0 | 46.2 % | 16 / 0 / 0 |
| frame.adjacent_repeat | 1 | 0 | 1 | 0 | 7.7 % | 1 / 0 / 0 |
| frame.native_agreement | 9 | 0 | 5 | 0 | 38.5 % | 10 / 0 / 0 |
| frame.no_end_punct | 5 | 0 | 3 | 0 | 23.1 % | 5 / 0 / 0 |
| frame.unresolved_pronoun | 5 | 0 | 4 | 0 | 30.8 % | 5 / 0 / 0 |
| key.contains_filler | 3 | 0 | 2 | 0 | 15.4 % | 3 / 0 / 0 |
| key.no_content_word | 3 | 0 | 1 | 0 | 7.7 % | 3 / 0 / 0 |
| key.too_long | 2 | 0 | 1 | 0 | 7.7 % | 2 / 0 / 0 |
| learner.restates_partner | 2 | 0 | 1 | 0 | 7.7 % | 2 / 0 / 0 |
| listening.distractor_not_filler | 3 | 0 | 2 | 0 | 15.4 % | 3 / 0 / 0 |
| listening.same_exchange | 2 | 0 | 2 | 0 | 15.4 % | 2 / 0 / 0 |
| native.gendered_past | 4 | 0 | 2 | 0 | 15.4 % | 6 / 0 / 0 |
| pronunciation.script | 1 | 0 | 1 | 0 | 7.7 % | 9 / 0 / 0 |
| rescue.new_fact | 1 | 0 | 1 | 0 | 7.7 % | 1 / 0 / 0 |
| variant.longer | 13 | 0 | 9 | 0 | 69.2 % | 13 / 0 / 0 |
| vocab.free_combination | 4 | 0 | 4 | 0 | 30.8 % | 6 / 0 / 0 |
| vocab.learner_share | 3 | 0 | 3 | 0 | 23.1 % | 3 / 0 / 0 |
| vocab.nested | 1 | 0 | 1 | 0 | 7.7 % | 1 / 0 / 0 |
| vocab.used_in_wrong | 13 | 0 | 6 | 0 | 46.2 % | 20 / 0 / 0 |

(`key.*` — снятые коды GEN-2b, их строки в старых уроках `lesson_day.v4.4` и счётчиках не трогались — §4 канона.)

**Фатальные коды в сохранённых уроках — ни одного**: по канону хранится только прошедший порог ответ, а единственный
`failed` день (ветеринар, день 1) владелец уже перезапустил в 12:29, и `checks_json` перезаписан удачной попыткой. Фатальные
срабатывания периода живут только в счётчиках — `filler.ungrammatical` 5 gated / 2 failed (все — попытки 1–3 этого дня) —
и в журнале запросов (§5). Моя оценка по ним:

| фатальный код | срабатываний (counters) | примеры | оценка |
|---|---|---|---|
| `filler.ungrammatical` | 5 gated, 2 failed (v4.7) | «I'd like the 3 p.m. appointment.» / «…5:30 p.m.…» — попытки 1, 2 и 3 дня 1 (в попытке 3 P2R переписал `p6` и день прошёл) | **ложное** — все пять; на новом коде 0 |
| остальные 9 фатальных | 0 в v4.7 | — | — |

Три примера предупреждений по каждому коду — в выводе команды (`tools/check-report-2026-09-20.txt`); из них на глаз:
`frame.no_end_punct` — настоящие (модель пишет «I'd like to ___» без точки); `check.verbatim` «Stay on a leash» ↔
«stay on» — настоящее; `filler.native_seam` «Она по всему его телу.» — судья прав, «Я могу жить с старыми окнами.» —
прав («со»); `vocab.free_combination` «come at» — настоящее.

## §7. Расхождения с нарядом

1. **«No.» не занесён в английский список** (наряд перечислял). Это и «Нет.»: с ним `He said "no."` кончался бы без
   знака, реплика «Oh no.» — тоже, а наполнение «No.» проходило бы в окно как значение. Сокращение номера почти всегда
   стоит перед цифрой и в уроках не встречалось. Тест фиксирует: «Oh no. It hurts.» — два предложения. Вернуть — одна
   строка в `en.php`.
2. **Текст, кончающийся точкой сокращения, по правилу кончается без знака** («Come at 3 p.m.» → `terminal` = ''). Это
   следствие «одного правила»: наполнение «3 p.m.» и каркас «…at 3 p.m.» — одна и та же строка, и правило не может
   сказать «наполнению — нет, каркасу — да». Для каркаса, кончающегося сокращением, это даст предупреждение
   `frame.no_end_punct` (не фатально). На 19 ответах таких каркасов нет (0 → 0). Если появятся — решение архитектора:
   второе правило «для каркасов» я не заводил.
3. **`filler.ungrammatical` теперь спрашивает `sentence_ends` пакета цели.** Раньше часть «свой знак в конце» была без
   языка; теперь «конец предложения» — правило языка, и для языка без пакета эта часть пропускается со счётчиком
   `lang.pack_missing` (запятая / `;` / `:`, окно и удвоенное слово — по-прежнему без языка). Языков цели без пакета
   сейчас нет (цель — en).
4. **`check.verbatim`**: «Dr. Smith» теперь имя (пара с ним не считается копией) — побочный эффект перевода `names` на
   правило; на выгрузках изменений нет.
5. **`check-report`**: столбец «из них фатальных» = находки фатальных кодов (все находки такого кода фатальны); в
   сохранённых уроках они бывают только у `failed` дней. Рядом добавлены счётчики `counted / gated / failed` по всем
   попыткам — иначе таблица не показывала бы ни одного фатального срабатывания периода (см. §6). `--since` на счётчики
   не действует — сказано в выводе.
6. Ответ P2R попытки 2 тоже извлечён (`answers/…-repair-p6.json`): модель вернула карточку `p6` без изменений — то есть
   починка и не могла помочь, находка была ложной.

## §8. Ворота и хеши

Ворота один раз, в конце: `composer check` (OpenAPI lint, deptrac, PHPStan L8, Pest parallel) — см. итог ниже;
мутации по изменённым файлам — `mutations.md` (15 дефектов: `SentenceEnds` ×5, `FillerRules` ×3, пакеты ×2,
`check-report` ×4, счёт предложений ×1); `invariant-reviewer` — см. ниже.

- **`composer check`** (в контейнере `wt_app`): OpenAPI lint — `openapi.yaml` ok, `openapi-admin.yaml` ok; deptrac — **Violations 0**, Skipped 0, Uncovered 3 (как было), Allowed 6 945; PHPStan L8 — **No errors**; Pest parallel — **2 316 passed** (16 153 assertions, 1 warning — прежний), 53 с. Хук ворот на коммите прогнал то же ещё раз.
- **Мутации — 15 из 15 пойманы** (`mutations.md`; копия дерева в сайдкаре `wt_check1_mut`, база `wordtrainer_check1_test`, всё снесено после прогона). Первый прогон дал 13/15: дефекты «список читается только в своём регистре» и «`sentence_ends` не спрашивается» выживали — тесты были слепы (у «DR. SMITH» `terminal` пуст и без маски, потому что текст не кончается точкой; пакет без всех ключей и так пропускал проверку). Дописаны утверждения «Come at 3 P.M.» → '', «Ask DR. SMITH. He knows.» — два, и пакет с ключами швов, но без `sentence_ends` — проверка пропущена, не упала. Второй прогон — 15/15.
- **`invariant-reviewer` — CLEAN**: Domain (`SentenceEnds`) импортирует только `Words` и `LanguagePack`; команда читает таблицы модуля Plan только `select`'ами; кросс-модульных вызовов нет.

Коммиты: код — `fda13002` (правило, пакеты, проверки, команда, тесты); доки — следующий коммит (канон §4, DECISIONS п. 348, этот отчёт с инструментами и выгрузками).

## §9. Шаги на бой

1. После влития — `docker compose restart horizon` (валидатор живёт в `BuildLessonJob`; воркер держит старый
   `FillerRules` / `LanguageWords` в памяти и продолжит рубить «3 p.m.»).
2. Один «Повторить» на плите дня 1 «Запись к врачу» плана «Визит к ветеринару» — **не нужен по факту**: владелец уже
   нажал «Повторить» в 12:29 на старом коде, попытка 3 прошла (P2R переписал `p6` на другие наполнения), день `ready`,
   `day_ready` в журнале 12:29:54. Если день всё же перезапускать — это вызов модели ($0.09), решение владельца.
3. `plan:check-report` — `docker compose exec -T app php artisan plan:check-report --since=2026-09-20` после первых
   дней на новом коде: `filler.ungrammatical` в `gated` не должен расти на точках сокращений.
