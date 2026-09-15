# GEN-2b · урок `lesson_day.v4.5` и P2R `v1.1` в конвейере, валидатор под пару языков, «было / стало» на шести темах

Наряд 15.09.2026, репо `backend2` (mobile не трогался). Канон — `docs/plan-v2.md` §2 (вызовы), §4 (валидатор, пакеты языков,
порог, судья швов); реестр промтов — `docs/prompts/REGISTRY.md` (строки LESSON, P2R, SEAM-JUDGE); решение — `docs/DECISIONS.md`
п. 319; код — `8b228a7c` (ворота хука зелёные, 2 099 тестов). Живые прогоны — `wordtrainer_e2e_test`; план Дена не пересоздавался,
голос не озвучивался.

## §1. Итог

- **Промты** `lesson_day.v4.5` и `lesson_card_repair.v1.1` приняты байт-в-байт (sha256 совпали с incoming), `incoming/` очищен,
  прежние файлы урока и починки сняты. Схема урока — без изменений структуры; `{{rules}}` P2R цитирует v4.5 (тест проверяет
  разделы v4.5, которых у v4.4 не было). Расхождения промта со схемой — §2.
- **Порог — семь фатальных:** пять GEN-2a + `exchange.second_question` + `exchange.repeats`. Карточка «обмен» (`x3`) с
  `frame_update` ставится **атомарно** (обмен и каркас вместе или ничего); `exchange.shape` чинится ею. `failed` — после двух
  карточек, как было.
- **Валидатор — про пару языков:** всё языковое — в пакетах `config/lesson/lang/<code>.php` (en — полный для цели, ru — полный для
  родного, uk/ro — каркас без правил). Нет ключа — проверка **не запускается**, `+1` `lang.pack_missing`, не находка. Новых
  предупреждений шесть (`frame.unresolved_pronoun`, `filler.is_clause`, `filler.article_seam`, `filler.native_seam`,
  `frame.native_agreement`, `learner.restates_partner`), всего кодов 53. Судья швов — **один вызов `gpt-5.4-mini` на день**.
- **«Было / стало» на шести темах** (тот же валидатор сдачи по обоим промтам, судья по обоим): находок **83 → 69**, фатальных
  **9 → 13**. v4.5 снял то, на что писался: альтернативы в `frame_native` 3 → 0, повтор обмена 1 → 0, «не читается на родном»
  15 → 9, ключи 23 → 18, listening 2 → 1. Новые систематические дефекты v4.5 — §5: каркасы без знака конца (аэропорт — 5 фатальных
  `line.ne_frame`), поле `filler` реплики со словом каркаса (банк), придаточное в наполнении повторилось (врач, пример ✗ из
  самого v4.5). «Правило, не модель» (> 20 % реплик) — `key.contains_filler`, 27 %.
- **Порог вживую** (шесть дней через `LessonGateKeeper`, валидатор сдачи): **4 дня проходят** (три без починки, врач — двумя
  карточками), **2 дня `failed`** (банк: три фатальных карточки на бюджет две; аэропорт: шесть). Цена починок — $0.0252 (врач) /
  $0.0301 / $0.0314 на `gpt-5.4`.
- **P2R:** контекст — только нужное карточке (каркасы и слова дня, реплики вокруг): вход 3 900–5 700 токенов против ≈ 7 000+
  урока целиком. **Модель ступенью дешевле (`gpt-5.4-mini`) проверку «чинит не хуже» не прошла**: на 12 карточках снимает
  фатальные своей карточки в 7 против 11 у `gpt-5.4`, на двух обменах со вторым вопросом стирала знак вопроса («Sure. May I see
  your passport»), на каркасе банка GEN-2a ломала наполнения диалога (5 фатальных → 2 против 5 → 0). По умолчанию
  `PLAN_REPAIR_MODEL` — модель урока. Цена карточки: `gpt-5.4` $0.011–0.018 (цель $0.01 не держит; с кэшем вендора
  $0.004–0.007), `gpt-5.4-mini` $0.0034–0.0060.
- **ro→en и uk→en:** оба урока — валидный JSON с первой попытки, сборка без падений, `lang.pack_missing` = 7 на день (ровно
  семь проверок родного языка), остальные проверки работают.
- **Цена дня v4.5:** урок $0.0830 в среднем (+7.5 % к v4.4, вход +850 токенов длинного промта), судья $0.0020; день с двумя
  починками на `gpt-5.4` ≈ $0.11. **Стоимость наряда** — $1.10 по коду (с кэшем вендора $0.86), кап $5.

## §2. Промты и схема

| файл | sha256 | откуда |
|---|---|---|
| `lesson_day.v4.5.md` | `3b91fe0b…2c2ae0` | `docs/prompts/incoming/lesson_day.v4.5.md`, байт-в-байт |
| `lesson_card_repair.v1.1.md` | `36067c9a…37bc2` | `docs/prompts/incoming/lesson_card_repair.v1.1.md`, байт-в-байт |
| `lesson_seam_judge.v1.md` | `138aff2b…62952d` | написан нарядом по постановке («один вызов на день, все пары списком, да/нет; правило языка не кодируется») — зарегистрирован в реестре до первого вызова |

**Расхождения промта со схемой — схема подогнана под промт:**

1. P2R v1.1: «otherwise omit "frame_update"». Строгая схема вендора не знает необязательных ключей → `frame_update` —
   обязательный ключ типа `object | null`; «опустить» = `null`. Живые ответы обоих моделей пришли с `null` или каркасом.
2. P2R v1.1 не говорит, где стоит `frame_update` — рядом с `card` («Return ONLY a JSON object {"card": …}… add "frame_update"»):
   схема ставит его на верхний уровень, рядом с `card`.
3. P2R v1.1: обмен «keeps its step» → в схеме `step` — enum из одного шага карточки.
4. P2R v1.1 вводит вид карточки «обмен», у промта урока нет списка его разделов: цитируются LEVEL, EXCHANGE KINDS, NATURAL ORDER OF
   ONE VISIT, MOBILE-FRIENDLY MESSAGE LENGTH, CONVERSATION PARTNER RULE, LEARNER MESSAGES, TEXT QUALITY, PRONUNCIATION_NATIVE,
   CHECK PER EXCHANGE (всё, чему отвечает ход визита). Разделы остальных видов — прежние, текстом v4.5.
5. Схема урока v4.5 = v4.4 (раздел STRICT OUTPUT SCHEMA и FIELD RULES не менялись) — схема не тронута.

## §3. Валидатор под пару языков

**Механизм.** `LanguagePack` — данные одного языка из конфига; `LanguageWords` — одни и те же вопросы любого языка (служебное ли
слово, две ли формы одного слова, число ли, чем кончается предложение, вопрос ли, какой артикль перед каким звуком, придаточное
ли наполнение, на что опирается местоимение, что согласуется с окном, какими буквами можно писать чтение), ответы — из пакета.
Правило спрашивает контекст `reads(code, сторона, ключи…)`: всё есть — проверка идёт; чего-то нет — пропуск записан
(`PackSkips`, по разу на код и сторону) и ничего не найдено. Ключ без спроса — `LanguagePackKeyMissing` (ошибка правила):
тест прогоняет все 53 строки дефектов с пустыми пакетами обеих сторон — ни одного исключения. Сборка пишет `+1`
`lang.pack_missing` за каждый пропущенный код ответа модели.

**Пакеты** (`config/lesson/lang/`, по 20 ключей в каждом): `en` — 16 заполненных, сторона цели (прежние английские списки, a/an, придаточные, местоимения,
вопрос по порядку слов, повторяемые на шве слова); `ru` — 8 заполненных, сторона родного (письменность, служебные, формы слов, числа и
время — только русские формы, прошедшее после «я», согласование: притяжательные/указательные/«какой»/«один» в падежах, краткие
формы, окончания прилагательных); `uk`, `ro` — все ключи `null`, TODO-карточка — ROADMAP «Пакеты языков ученика uk и ro»
(украинские числа и слова времени, лежавшие в русских выражениях, перенесены туда). Код без файла — пустой пакет.

**Новые коды (все — предупреждения):** `frame.unresolved_pronoun`, `filler.is_clause`, `filler.article_seam`,
`frame.native_agreement`, `learner.restates_partner` — код над пакетами; `filler.native_seam` — модель-судья
(`LessonSeamJudge`, после порога, один вызов). Точное правило каждого — канон §4.

**Переписано под v4.5 и порог:**
- `exchange.second_question` — адрес теперь обмен `x3` (чинит карточка «обмен»); вопрос — знак вопроса или порядок слов вопроса.
- `exchange.repeats` (фатальный) забрал у `filler.one_in_dialogue` подслучай «одно наполнение в двух обменах».
- `key.no_content_word` — по правилу v4.5: знаменательное слово требуется, только если оно есть в каркасе вне окна; иначе ключ —
  каркас до окна как написан.
- `learner.restates_partner` читает только ответ на **утверждение** собеседника (слова вопроса возвращаются в любом ответе).

**Два уточнения эвристик по живым дням** (до финальной таблицы; обе проверены тестом с дефектом, §9):
1. «Слово удвоено на шве» (фатальный `filler.ungrammatical`) не считает частицу фразового глагола перед предлогом:
   «I can move in ___» + «in June» — английский (`seam_repeatable_words`). Живой день аренды упал на этом: P2R вернул верный каркас
   без изменений, и день ушёл в `failed`. «my my», «the the» по-прежнему считаются.
2. Вопрос узнаётся и без знака — по порядку слов последнего предложения (вспомогательный + местоимение, `question_word_order`
   пакета цели): P2R на `gpt-5.4-mini` «чинил» `exchange.second_question`, стирая знак — «Sure. May I see your passport» — и такой
   день проходил порог со сломанной репликой (ro→en). «Детекция вопроса» — ключ пакета по постановке.

Ничего нового фатальным не стало: оба уточнения — внутри семи кодов; первое уменьшает фатальные, второе закрывает обход.

## §4. P2R: узкий контекст и модель

**Контекст** (`LessonCardContext`): каркасы дня (id, вид, тексты, наполнения с `in_dialogue`) и слова дня — всем картам; у
каркаса — обмены на нём; у обмена — соседние обмены целиком (с вопросом check), остальные — двумя репликами; у реплики — её
собеседник и соседи; у check — его обмен; у вопроса listening — весь визит на родном и другие вопросы. Проверки, чтения, ключи,
варианты, определения и запросы фото не показываются. Вход: каркас 4 150–4 300 токенов, реплика 3 900–4 200, обмен 5 590–5 720.

**«До / после» на тех же карточках** (`tools/repair-compare.php`; каждая карточка чинится **одна** на ответе модели; итог
перечитан валидатором сдачи без новых вызовов — `tools/rescore.php`). Карточки GEN-2a: `rent B3` и `bank p2` (их чинил живой порог
GEN-2a: $0.0218 и $0.0221 на v1 с уроком целиком), плюс `p1` первого урока Дена («I work as an ___», P2R v1 вернул его без
изменений); карточки наряда — фатальные карточки шести v4.5-дней и ro→en (у аэропорта — две из пяти одинаковых реплик).

| карточка | `gpt-5.4`: фатальных в уроке до → после · находки у карточки после · $ | `gpt-5.4-mini`: то же |
|---|---|---|
| GEN-2a rent `B3` (v4.4) | 4 → 3 · `variant.longer` · $0.011570 | 4 → 3 · `variant.longer` · $0.003476 |
| GEN-2a bank `p2` (v4.4) | **5 → 0** · — · $0.012765 («Here is ___» + «my passport», наполнения диалога целы) | **5 → 2** · `filler.one_in_dialogue` ×2, `pronunciation.script` · $0.003812 («Here is my ___» + «passport» — по v4.5, но наполнения, которые говорит диалог, переписаны) |
| Ден `p1` (v4.4) | 3 → 1 · `filler.one_in_dialogue` · $0.012917 («I work as ___» + «an operations manager») | 3 → 1 · то же · $0.003875 (то же) |
| bank `x1` (v4.5) | 3 → 2 · — · $0.018368 («Sure. Please show me your passport first.») | 3 → 2 · — · $0.005501 («Sure. I need to see your passport first.») |
| bank `B2` | 3 → 2 · — · $0.011725 | 3 → 2 · — · $0.003545 |
| bank `B5` | 3 → 2 · — · $0.011938 | 3 → 2 · `variant.longer` · $0.003586 |
| airport `x1` | **6 → 4** · — · $0.018230 («Please show me your passport.»; реплика ученика подогнана под каркас без точки) | **6 → 6** · `exchange.second_question`, `line.ne_frame` · $0.005978 («Sure. May I see your passport» — знак стёрт) |
| airport `B1` | 6 → 5 · — · $0.011300 (точка снята под каркас) | 6 → 6 · `line.ne_frame` · $0.003386 |
| airport `B2` | 6 → 5 · — · $0.011298 | 6 → 6 · `line.ne_frame` · $0.003389 |
| doctor `p7` (v4.5) | 4 → 1 · `filler.one_in_dialogue` · $0.013325 (наполнение диалога переписано) | 4 → 1 · `filler.one_in_dialogue` · $0.004007 («if» снят с наполнений) |
| doctor `B8` | 4 → 4 · `line.ne_frame` · $0.012075 | 4 → 4 · `line.ne_frame`, `line.too_long` · $0.003681 («When should we come back? If the fever gets worse.») |
| airport-ro `x1` | **1 → 0** · — · $0.018103 | **1 → 1** · `exchange.second_question` · $0.005413 (знак стёрт) |
| **итого** | фатальные своей карточки сняты в **11 из 12**; фатальных в уроках 48 → **29**; $0.0136 в среднем | сняты в **7 из 12**; 48 → **36**; $0.0041 в среднем |

**Вывод и решение в коде:** ступень дешевле чинит хуже — и числом, и видом (две «починки» стиранием знака вопроса, одна ломка
наполнений диалога). `PLAN_REPAIR_MODEL` по умолчанию — модель урока (`gpt-5.4`), ключ остаётся (`config/plan.php`). Цена: по
`ModelCost` (без скидки кэша, так её пишет код) — каркас/реплика $0.011–0.013, обмен $0.018; вендор кэширует повторяющийся системный
промт одного вида карточки (в логах 2 300–5 400 кэшированных токенов на повторных вызовах) — по счёту вендора $0.004–0.007.
Против GEN-2a ($0.022 на v1 с уроком целиком) — дешевле на 20–50 %; цель ≤ $0.01 держит только `gpt-5.4-mini`. Решение по цене —
за архитектором (ROADMAP).

## §5. Порог вживую

**Живая сборка** (`tools/live-run.php` → `LessonBuildService` как в `BuildLessonJob`; валидатор до двух уточнений §3, P2R на
`gpt-5.4-mini` — тогдашний дефолт):

| день | фатальные в ответе | P2R | итог |
|---|---|---|---|
| interview | — | — | ready |
| rent | `filler.ungrammatical@p1.f2` («move in in June» — ложное, §3) | `p1` — вернул без изменений | **failed** |
| bank | `exchange.second_question@x1`, `line.ne_frame@B2`, `@B5` | `x1` (знак стёрт), `B2` | **failed** — `line.ne_frame` |
| restaurant | — | — | ready |
| airport | `exchange.second_question@x1`, `line.ne_frame` ×5 | `x1`, `B1` | **failed** — `line.ne_frame` |
| doctor | `filler.ungrammatical@p7.f1…f3`, `line.ne_frame@B8` | `p7`, `B8` | ready |
| airport-ro | `exchange.second_question@x1` | `x1` (знак стёрт) | ready — со сломанной репликой, закрыто уточнением 2 |
| doctor-uk | — | — | ready |

**Порог на валидаторе сдачи** (`tools/gate.php`, тот же ответ модели, P2R по модели):

| день | фатальные | `gpt-5.4`: карточки · итог · $ | `gpt-5.4-mini`: карточки · итог · $ |
|---|---|---|---|
| interview, rent, restaurant | — | проходят без починки | проходят без починки |
| bank | `x1`, `B2`, `B5` | `x1`, `B2` · **failed** (`B5` остался) · $0.030086 | `x1`, `B2` · **failed** (`x1`, `B5`) · $0.009032 |
| airport | `x1`, `B1`, `B2`, `B3`, `B6`, `B8` | `x1`, `B1` · **failed** (4 реплики) · $0.031385 | `x1`, `B1` · **failed** (всё осталось) · $0.009385 |
| doctor | `p7` (×3), `B8` | `p7`, `B8` · проходит · $0.025183 | `p7`, `B8` · проходит · $0.007669 |
| airport-ro | `x1` | `x1` · проходит · $0.018103 | `x1` · **failed** · $0.005408 |
| doctor-uk | — | проходит без починки | проходит без починки |
| **шесть дней** | | **2 failed**, 6 карточек, $0.0867 | **2 failed**, 6 карточек, $0.0261 |
| **восемь дней** | | **2 failed**, 7 карточек, $0.1048 | **3 failed**, 7 карточек, $0.0315 |

**Почему падают дни — и что не чинится двумя карточками** (решения архитектора, ROADMAP «Решения по живому порогу»):
1. **Каркас без знака конца** (аэропорт: 5 из 7 каркасов «I'm checking in ___» без точки, реплики — с точкой). Сверка §3а
   символ в символ → пять фатальных `line.ne_frame`, дефект один — в каркасе, а порог берёт карточки реплик. Варианты: терпеть
   знак конца, которого нет в каркасе (служит текст модели), или относить одинаковые `line.ne_frame` одного каркаса к карточке
   каркаса. Сейчас — `failed`.
2. **Поле `filler` реплики со словом каркаса** (банк: каркас «Here is my ___» + наполнение «rental contract», а реплика пишет
   `filler: "my rental contract"`; текст реплики верен). v4.5 перенёс «my» в каркас, модель не перенесла его в поле реплики.
   Три фатальные карточки на бюджет две.
3. **Придаточное в наполнении** (врач: «When should we come back if ___» + «if the fever gets worse») — ровно ✗-пример v4.5;
   чинится двумя карточками.
4. **Второй вопрос собеседника в ask-обмене** («Sure. May I see your passport?») — в трёх днях из восьми, у v4.4 — ни разу
   (там закрывал ученик); `gpt-5.4` переписывает в утверждение, `gpt-5.4-mini` стирает знак.

Счётчики сборки (e2e, `lesson_day.v4.5`): `gated` — `line.ne_frame` 8, `filler.ungrammatical` 4, `exchange.second_question` 3;
`failed` — `line.ne_frame` 6, `filler.ungrammatical` 1; `lang.pack_missing` 14.

## §6. «Было / стало»: код → v4.4 → v4.5

Одни и те же шесть тем и входы (сцены GEN-2a: заголовок, бриф со словами ученика, уровень, пол неизвестен, 8 + 8), один вызов на
день, без починок; валидатор и судья сдачи — по обоим ответам (`tools/export.php` → `validator.json`). Доля — адресов с кодом
к местам, где код читается на шести днях v4.5 (реплики, каркасы, наполнения…).

| код | порог | v4.4 (6 дней) | v4.5 (6 дней) | доля v4.5 | ro/uk | пример v4.4 | пример v4.5 |
|---|---|---|---|---|---|---|---|
| `exchange.second_question` | **фатально** | 2 (rent 2) | 2 (bank 1, airport 1) | 2/48 = 4.2 % | 1 (airport-ro 1) | rent x1: the closing message of B «What about electricity?» ends with a question mark | bank x1: the closing message of A «Sure. Can I see your passport first?» ends with a question mark |
| `exchange.repeats` | **фатально** | 1 (rent 1) | 0 | 0/48 | 0 | rent x8: exchange 8 says p2 with «the deposit», which exchange 2 already said | — |
| `filler.ungrammatical` | **фатально** | 3 (bank 3) | 3 (doctor 3) | 3/119 = 2.5 % | 0 | bank p2.f1: «Here is my my passport.»: a word is doubled at the seam | doctor p7.f1: «When should we come back if if the fever gets worse?»: a word is doubled at the seam |
| `line.ne_frame` | **фатально** | 3 (rent 1, bank 2) | 8 (bank 2, airport 5, doctor 1) | 8/48 = 16.7 % | 0 | rent B3: «I need a one-year contract.» is not «I need a ___ contract.» with «one year» | bank B2: «Here is my rental contract.» is not «Here is my ___.» with «my rental contract»; served as «Here is my my rental contract.» |
| `pronunciation.script` | предупр. | 3 (bank 3) | 1 (interview 1) | 1/259 = 0.4 % | 0 | bank p2.f1: the reading «май пáспорт» leaves the native script | interview p4.f2: the reading «э պրосэс импрувмэнт» leaves the native script (армянские буквы) |
| `frame.native_alternatives` | предупр. | 3 (bank 2, restaurant 1) | **0** | 0/44 | 0 | bank p2: «Вот мой/моё ___.» writes alternatives inside the frame | — |
| `frame.native_punct` | предупр. | 0 | 1 (interview 1) | 1/44 = 2.3 % | 0 | — | interview p6: «Could you tell me about ___?» ends with «?», «Расскажите, пожалуйста, о ___.» with «.» |
| `frame.unresolved_pronoun` | предупр. | 2 (restaurant 1, doctor 1) | 2 (bank 1, restaurant 1) | 2/44 = 4.5 % | 3 (doctor-uk 3) | restaurant p4: «Does it have ___?» leans on «it» | bank p7: «Can I keep it for ___?» leans on «it» |
| `frame.native_agreement` | предупр. | 4 (rent 1, bank 3) | 4 (rent 1, bank 2, doctor 1) | 4/43 = 9.3 % | — (пакетов нет) | rent p6: «___ разрешён?»: «разрешён» agrees with the slot | rent p6: «___ разрешена?»: «разрешена» agrees with the slot |
| `filler.one_in_dialogue` | предупр. | 2 (rent 2) | 4 (bank 4) | 4/119 = 3.4 % | 0 | rent B3: «one year» is not one of p3's fillers | bank B2: «my rental contract» is not one of p2's fillers |
| `filler.is_clause` | предупр. | 3 (doctor 3) | 3 (doctor 3) | 3/119 = 2.5 % | 3 (doctor-uk 3) | doctor p7.f1: «if he gets worse» is a clause, not a value | doctor p7.f1: «if the fever gets worse» is a clause, not a value |
| `filler.article_seam` | предупр. | 1 (rent 1) | 0 | 0/119 | 0 | rent p3: «I need a ___ contract.»: «a» stands before the slot | — |
| `filler.native_seam` | предупр. | 15 (rent 7, bank 6, restaurant 1, doctor 1) | **9** (interview 1, rent 1, bank 2, restaurant 1, doctor 4) | 9/119 = 7.6 % | 1 (airport-ro 1) | rent p1.f1: «А как насчёт электричество?» does not read as Russian | bank p6.f1: «Когда карта будет готово?» does not read as Russian |
| `line.too_long` | предупр. | 0 | 1 (doctor 1) | 1/48 = 2.1 % | 0 | — | doctor B8: «When should we come back if if the fever gets worse?» has 11 words |
| `key.not_in_line` | предупр. | 2 (interview 1, rent 1) | 1 (interview 1) | 1/48 = 2.1 % | 0 | interview B1: the key «have of experience» is not in «I have five years of experience.» | то же — в v4.5 слово в слово |
| `key.contains_filler` | предупр. | 12 | 13 (interview 2, rent 4, bank 4, airport 2, doctor 1) | **13/48 = 27.1 %** | 5 | interview B7: the key «success in this» takes words of the filler «success in this role» | interview B2: the key «lead a team» takes words of the filler «a team of six» |
| `key.no_content_word` | предупр. | 7 (bank 3, airport 2, doctor 2) | 4 (bank 1, airport 1, doctor 2) | 4/48 = 8.3 % | 5 | bank B4: «I am ___.» has no content word outside the slot: the key is «I am», not «am a» | bank B3: «I'm ___.»: the key is «I'm», not «I'm a» |
| `key.too_long` | предупр. | 2 (doctor 2) | 0 | 0/48 | 0 | doctor B7: the key «How often should I give» has 5 words | — |
| `variant.longer` | предупр. | 5 | 3 (interview 1, rent 1, bank 1) | 3/48 = 6.3 % | 0 | interview B1: the variant has 7 words, the line 6 | interview B4: the variant «A product launch was a big success.» has 7 words, the line 6 |
| `check.verbatim` | предупр. | 5 (interview 2, rent 2, doctor 1) | 3 (rent 1, restaurant 1, doctor 1) | 3/48 = 6.3 % | 0 | interview x1.check: «Your background managing projects» repeats «your background» | rent x4.check: «Power and internet» repeats «and internet» |
| `listening.same_exchange` | предупр. | 1 (restaurant 1) | 0 | 0/24 | — | restaurant L3: L2 and this question are both about exchange 6 | — |
| `listening.no_learner_value` | предупр. | 1 (rent 1) | 0 | 0/6 | — | rent: no question asks for a value the learner gave | — |
| `listening.distractor_not_filler` | предупр. | 0 | 1 (bank 1) | 1/24 = 4.2 % | — | — | bank L3: the right option «Для студентов её нет» is neither a number nor a time, the wrong option «Она будет через месяц» is |
| `vocab.free_combination` | предупр. | 2 (rent 1, doctor 1) | 1 (interview 1) | 1/48 = 2.1 % | 1 | rent v6: «work from home» | interview v8: «on time» |
| `vocab.used_in_wrong` | предупр. | 4 (rent 2, airport 2) | 5 (rent 2, bank 1, restaurant 1, airport 1) | 5/48 = 10.4 % | 3 | rent v1: «deposit» is not in the partner's line of exchange 8 | rent v4: «utilities» is not in the partner's line of exchange 4 |
| `vocab.learner_share` | предупр. | 0 | 0 | 0/6 | 1 (doctor-uk) | — | — |
| **итого** | | **83** (фатальных 9) | **69** (фатальных 13) | | 23 | | |

**Ни разу на 12 днях** (27 кодов): `dialogue.count`, `vocab.count`, `exchange.shape`, `check.shape`, `listening.shape`,
`frame.count`, `frame.unused`, `frame.too_long`, `frame.no_slot_share`, `filler.count`, `line.no_frame`,
`learner.restates_partner`, `kind.ask_count`, `kind.rescue_count`, `rescue.not_first`, `rescue.new_fact`, `rescue.no_prev`,
`partner.two_questions`, `partner.too_long`, `partner.closer`, `check.about_learner`, `check.listed_alternative_as_wrong`,
`listening.count`, `vocab.everyday_word`, `vocab.nested`, `native.gendered_past`, `image_prompt.rule_text`.

**«Правило, не модель»** (доля > 20 % на шести днях v4.5): **`key.contains_filler` — 27 %** (у v4.4 — 25 %). Порог не назначается.
На каркасах, где вне окна мало слов («I lead ___» + «a team of six», «What does ___ look like?»), ключ из одной части каркаса
модель берёт вместе со словом наполнения — это правило ключа сталкивается с тем, где режется окно.

**Каркасов на день** (`answers/`): 7–8 на 7–8 answer/ask, каркас в двух обменах — 0 из 8 дней (у v4.4 — 3 из 8), без окна —
по одному в двух днях; **без знака конца — 5 каркасов аэропорта** (у v4.4 — ни одного).

**Ключи.** v4.5 снял противоречие v4.4 (пример «pain in my» против «never end on a function word»): «Here is my ___» → ключ «Here is
my» (банк, аэропорт «I have») больше не находка. Остались: `key.not_in_line` «have of experience» (собеседование — слово в слово
тот же ключ, что у v4.4), «I'm a» при «I'm ___» — артикль из наполнения.

**Швы.** v4.5 «где режется окно» сработал на родной стороне: «Вот мой/моё ___» (3 находки судьи, альтернатива, согласование) →
«Вот ___» + «мой договор аренды»; «А как насчёт ___» + «электричество» (3 падежа) → нет; «___ разрешён» + «кошка/собака» (3) →
«___ разрешена» + «кошка / маленькая собака» читается, «велосипед» — нет (1). На английской — «Here is my my passport» (3) →
ни одного «my my»; но наполнение-придаточное у врача повторилось, и новый дефект — поле `filler` реплики (§5). Судья: 26 «нет»
на 14 днях, спорных 3 («Я могу работать из дома здесь?», «Mulțumesc pentru cartea de îmbarcare.», «Что входит в куриную пасту?» —
грамматичны), пропусков по выгрузкам не видно; «Он ест почти ничего», «Когда карта будет готово?», «если если» — пойманы.

**Listening.** 2 → 1: пропали «два вопроса об одном обмене» и «ни одного вопроса о значении ученика»; появился один дистрактор
другого рода (банк L3). v4.5 listening держит «≥ 1 о значении ученика»: у всех шести дней есть.

**Канцелярит — собеседование** (реплики из `compare.json`):

| | v4.4 | v4.5 |
|---|---|---|
| зачем эта роль | B: «I am interested in the product focus.» — ✗-пример самого v4.5 | B: «I enjoy working on complex projects.» |
| сложности | B: «I handle challenges through early communication.» — ✗-пример v4.5 | обмена нет |
| успех в роли | A: «Success means predictable delivery, clear priorities, and strong cross-functional communication.» — ✗-пример v4.5 | A: «Success means the team ships on time and communicates clearly.» — почти ✓-пример |
| стиль руководства | B: «My leadership style is clear communication.» / «Мой стиль управления — это понятная коммуникация.» | **то же** («Мой стиль руководства — это понятная коммуникация.») — ✗-пример v4.5 остался |
| новое | — | A: «Why do you want this role? We need strong planning, stakeholder communication, and calm delivery under pressure.» — вопрос и справка в одном пузыре, «stakeholder communication» |

## §7. Пары ro→en и uk→en

| день | план (строитель) | урок | JSON | пропущено проверок | находок | порог |
|---|---|---|---|---|---|---|
| airport-ro «Check-in aeroport» (начальный) | $0.014240, сцена на румынском | $0.077212, 35.0 с, 1 попытка | валиден по схеме с первой попытки | 7 (`lang.pack_missing`) | 10: ключи 6, `vocab.used_in_wrong` 2, судья 1, `exchange.second_question` 1 | `x1` на `gpt-5.4` — проходит; на mini — нет |
| doctor-uk «Прийом у лікаря» (средний) | $0.014992, сцена на украинском | $0.084225, 41.3 с, 1 попытка | валиден с первой попытки | 7 | 13: `frame.unresolved_pronoun` 3, `filler.is_clause` 3 («if…» опять), ключи 4, словарь 3 | фатальных нет |

Пропускаются ровно семь проверок родного языка: `pronunciation.script`, `frame.native_punct`, `frame.native_agreement`,
`listening.same_exchange`, `listening.no_learner_value`, `listening.distractor_not_filler`, `native.gendered_past`; всё
остальное (цель en и проверки без языка) работает — 23 находки на двух днях. Счётчик e2e `lang.pack_missing` = 14. Судья швов
читает румынский и украинский без пакета (1 «нет» у ro — спорное, §6). Чтения ro — латиница с диакритиками («aim cec-king
in»), uk — кириллица; проверить их нечем, пока нет пакета.

## §8. Цена

| | v4.4 (GEN-2a, 6 дней) | v4.5 (GEN-2b, 6 дней) |
|---|---|---|
| урок, $ | 0.0772 | **0.0830** (0.0760–0.0979) |
| время, с | 28.9 | 37.5 (31.9–41.3) |
| токены вход / выход | 6 635 / 4 041 | 7 487 / 4 284 |
| судья швов, $ на день | — | 0.0020 (0.0015–0.0024, 14–24 предложения) |
| P2R, $ на карточку | 0.022 (v1, урок целиком) | 0.011–0.018 (`gpt-5.4`, узкий контекст; с кэшем вендора 0.004–0.007) |
| день: урок + судья | 0.077 | **0.085** |
| день с двумя починками | ≈ 0.12 | ≈ 0.11 |

**Счётчики пакетов:** на e2e `lang.pack_missing` = 14 (по 7 на ro→en и uk→en), на днях ru→en — 0.

## §9. Тесты — каждый проверен дефектом

Дефект вносится в код, названный тест запускается и **падает**, файл возвращается байт-в-байт (`tools/mutate.py` + `tools/mutations.json`).

| # | правило канона | тест | дефект | под дефектом |
|---|---|---|---|---|
| 1a | пакет отсутствует → пропуск со счётчиком, не находка | `LessonValidatorTest` «skips a check whose language has no pack…»; `LessonObservationTest` «builds a day for a learner whose language has no rules yet…» | пропуск не записывается | оба упали: пропуски `[]`, счётчик 0 |
| 1b | то же | те же | uk/ro читаются русским пакетом | оба упали: русские находки у румынского ученика |
| 2 | согласование — из пакета родного | `LessonValidatorTest` «reads the agreement of a native frame from the native pack…» | список слов в коде, не в пакете | упал: пакет без «разрешён» всё равно находит |
| 3a | P2R обмена ставит `frame_update` атомарно | `LessonCardTest` «puts a repaired exchange back together…»; `LessonGateBuildTest` «holds a repeated exchange…» | обмен ставится без каркаса | оба упали: отметки `in_dialogue`, `filler.one_in_dialogue` в уроке |
| 3b | то же | те же | не подходящий обмену каркас ставится | оба упали: не `null`; день `ready` вместо `failed` |
| 4a | фатальные — ровно семь | `LessonGateTest` «holds the day for exactly the seven fatal codes…» | восьмой фатальный (`partner.too_long`) | упал |
| 4b | то же | тот же | `exchange.repeats` не фатален | упал |
| 5a | судья швов — один вызов на день | `LessonObservationTest` «asks the seam judge once a day…» | судья ещё и до порога | упал: 2 вызова вместо 1 |
| 5b | все пары одним списком | тот же | в вызов уходит часть предложений | упал: 6 вместо 15 |
| 6a | команда починки хранит находки судьи | `LessonRepairTest` «keeps the judged native seams…» | находки судьи не переносятся | упал |
| 6b | кроме переписанного каркаса | тот же | переносятся и у переписанного | упал |
| 7 | частица перед предлогом — не удвоение | `LessonValidatorTest` «does not count a particle before a preposition…» | исключение пакета игнорируется | упал |
| 8 | вопрос без знака — по порядку слов | `LessonValidatorTest` «knows a closing question by its word order…» | вопрос — только знак | упал |
| 9 | ключ v4.5 | `LessonValidatorTest` «asks a content word of a key only where…» | чтение v4.4 (всегда нужно знаменательное) | упал |
| 10 | одно наполнение дважды — `exchange.repeats` | `LessonAssemblyTest` «marks in the dialogue exactly the fillers it says…» | правило визита выключено | упал |

**Удалены, не переписаны** (v4.4-специфика): тест «пять фатальных кодов», тест порядка карточек с «`exchange.shape` не на
карточке», тест сборки «фатальная не на карточке — `failed` без P2R», тест карточек с «`x3` — не карточка», тест отметок
`in_dialogue` с подслучаем «одно наполнение дважды — отметка». Их правила заменили тесты от канона GEN-2b (строки 3–4, 10).
Все 53 кода имеют строку дефекта в `LessonValidatorTest` (кроме `filler.native_seam` — его находит модель, строка 5).

## §10. Индексы и EXPLAIN

Новых таблиц, колонок, миграций и путей чтения нет: пропуск пакета, находка судьи и молчание судьи — три новых имени в том же
upsert счётчиков; находки, которые хранит команда починки, читаются из уже загруженной сцены. `tools/explain.php` →
`explain.txt` (e2e, 45 строк счётчиков): upsert `lang.pack_missing` / `filler.native_seam` / `judge.unavailable` — `Conflict
Arbiter Indexes: plan_check_counters_uidx`; чтение админки — вся таблица по порядку (как было). Бэкап e2e перед ручным
удалением строки пробного счётчика — `storage/db-backups/wordtrainer_e2e_test-20260915-195846.sql.gz`.

## §11. Стоимость наряда

По логу исходящих вызовов e2e (71 вызов, `purpose = plan`): цена по `ModelCost` / по счёту вендора со скидкой кэша.

| что | вызовов | $ (код) | $ (кэш) |
|---|---|---|---|
| живые дни: 8 уроков | 8 | 0.6593 | 0.5504 |
| живые дни: 2 плана ro/uk | 2 | 0.0292 | 0.0229 |
| живые дни: P2R (mini) и судья | 8 + 5 | 0.0446 | 0.0362 |
| сравнение P2R: `gpt-5.4` / `gpt-5.4-mini` на 12 карточках | 12 + 12 | 0.1636 + 0.0496 | 0.1509 + 0.0217 |
| порог на валидаторе сдачи: `gpt-5.4` / `gpt-5.4-mini` | 7 + 7 | 0.1048 + 0.0315 | 0.0483 + 0.0106 |
| выгрузки: судья по v4.4 и дням с починками | 10 | 0.0210 | 0.0210 |
| **итого** | **71** | **1.1036** | **0.8619** |

Голос и фото не покупались (e2e вне автоозвучки; живые дни — без задач фото). Пробный прогон инструментов — на `fake`, $0.

## §12. Что не проверено и что замечено

- **Решения архитектора** (ROADMAP «Решения по живому порогу»): каркас без знака конца; поле `filler` со словом каркаса; цена
  P2R на модели урока; `key.contains_filler` как правило; два хода порога при многих фатальных одного происхождения.
- Судья швов на `gpt-5.4-mini` — 3 спорных «нет» из 26; калибровки на человеке нет — колонка «судья» в выгрузках для Дена.
- `learner.restates_partner` — 0 на 12 днях: утверждений собеседника в answer-обменах мало (в основном вопросы); порог 60 % по
  постановке, на живых данных не откалиброван.
- Пакеты uk/ro пусты: чтения, согласование и listening этих учеников не проверяются (7 кодов). de как язык цели плана — без пакета:
  урок немецкого проверяли бы только правила без языка.
- `plan:repair-card --apply` на карточке «обмен» вживую не запускался (Pest — да); `frame_update` вживую пришёл в одной
  починке (airport `x1`, mini) и встал вместе с обменом.
- Уроки v4.4 на `wordtrainer` (план Дена) остаются со своей версией; их починка командой цитирует правила v4.5.
- Телефон, симулятор, клиент — не трогались; план Дена не пересоздавался.

## §13. Файлы

- `README.md` — этот отчёт; `interview.md`, `rent.md`, `bank.md`, `restaurant.md`, `airport.md`, `doctor.md`, `airport-ro.md`,
  `doctor-uk.md` — выгрузки v4.5-дней (ответ модели без починок) с пустой колонкой «оценка», вердиктом судьи у каждой собранной
  фразы, пропусками пакетов и порогом.
- `runs.json` — 8 живых дней: входы, каждый вызов (токены, цена, время, спрошенное), итог сборки; `answers/<slug>.json` — ответ
  модели; `final/<slug>.json` — урок после порога; `judge/` — вердикты судьи по дням (v4.4 и v4.5); `cases/` — первый урок Дена
  (v4.4) и его входы.
- `validator.json` — код → v4.4 → v4.5 → ro/uk, доли, пропуски; `compare.json` — ключи, швы, listening, реплики и каркасы шести
  тем бок о бок; `repair-compare.json` — 24 починки; `gate-gpt-5.4.json`, `gate-gpt-5.4-mini.json` — порог по моделям;
  `explain.txt`.
- `tools/live-run.php`, `tools/export.php`, `tools/repair-compare.php`, `tools/rescore.php`, `tools/gate.php`, `tools/explain.php`.
