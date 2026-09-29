# GEN-4 · прогон ворот двух ступеней дня (gen-4b) — 29.09.2026

Наряд GEN-4 §5–§6: день 1 ядра каждого плана семнадцати целей GEN-4a собран конвейером двух ступеней (`LessonBuildService`:
скелет → SkeletonCheck → судья швов → починка скелета → диалог → DialogueCheck → перемешивание → починка диалога → урок)
дважды — ступени на `gpt-5.6-luna` и на `gpt-5.4` — и ещё раз один скелет на `gpt-5.6-luna` с `reasoning_effort` high;
плюс e2e на стенде `wordtrainer_e2e_test` с голосом. Промты между прогонами не менялись. Таблицы — [`summary.md`](summary.md)
(собирает `tools/gate.php table`, без вызовов).

**Итог.** (Доработка GEN-4b — §10: ступени на gpt-5.4, контракт v1.1, три упавших дня gpt-5.4 и e2e через API собраны с первой попытки.)
- **gpt-5.4 собирает день чаще**: 13 дней из 16 (81 %) против 8 из 16 у Luna (50 %). Разница — в диалоге: без фатальных
  13 из 14 первых диалогов gpt-5.4 против 7 из 11 у Luna. Первые скелеты сыры у обеих (без фатальных — 25 % и 31 %), но
  повтор скелета gpt-5.4 обычно проходит.
- **Цена и время дня**: gpt-5.4 — $0.139 и 88 с, Luna — $0.088 и 104 с (+58 % за gpt-5.4).
- **Luna с effort high пишет лучшие скелеты**: без фатальных 6 из 10 против 2 из 10 у обычной Luna на тех же сценах; ни
  чужих букв, ни `vocab.not_found`. Но скелет стоит $0.088 (×3.3) и идёт 143 с; два из десяти не уложились в таймаут сборки
  180 с. Сцены 11–16 не прогнаны — кап $5.
- **e2e**: день 1 упал на первой сборке (латинская «ú» вместо «и́» в двух чтениях) и собрался на «ещё раз» ученика. Все 6
  каркасов и все 8 слов — про сцену; ни одной реплики, слова или проверки про выдуманное место работы (в уроке 26.09 — 4
  каркаса и 8 слов про историю «магазина»). Шесть определений из восьми — по-английски.
- **Деньги**: OpenAI — $4.9341 из $5 (в том числе $0.18 — оценка двух скелетов, оборванных таймаутом); ElevenLabs — 182
  кредита · 916 символов · $0.0364 (только план e2e).
- 29.09 в 10:18 UTC у организации OpenAI кончились кредиты (`429 insufficient_quota`); Ден пополнил, прогон продолжен
  (прерванные дни gpt-5.4 13 и 14 — в `runs/days/gpt54-interrupted/`, пересобраны заново).

## 1. Что и на чём

- **Код** — ветка `gen-4`: `2f169c27` (конвейер), `5526d701` (исправления проверок скелета по этому прогону, §7),
  `427e66e1` (перемешивание старых уроков, §7). Стенд — контейнер `wt_gen4` (worktree `../gen-4/backend2` в `/wt`, main —
  в `/app`), база прогона `wordtrainer_gen4` — в неё пишутся только журнал вызовов и счётчики проверок; план, сцены, урок не
  записываются. В прогоне ворот голос, перевод, фото не вызывались (`generation.speech.enabled=false`, харнесс зовёт только
  OpenAI); e2e — полный конвейер (фото, голос по имени плана).
- **Промты** (`app/Modules/Plan/Infrastructure/Prompt/current/`, байт в байт, раздел TEST INPUT вырезает код):

  | версия | назначение | sha256 | источник |
  |---|---|---|---|
  | `plan-builder-v2.1` | plan | `23b1900d5b19044bfd6bb269aa59790ef4b9845229c32d711e8018d50637eb13` | ветка `gen-4-prompts`, `docs/research/gen-4/plan-builder-v2.1.md` (версия 8 GEN-4a) |
  | `lesson_skeleton.v1` | skeleton | `ed290084858b3c3e9f5095e17f703786d950397c2c38d7238ed757fa1b1d089f` | `../gen-4-in/` (архитектор) |
  | `lesson_dialogue.v1` | dialogue | `a001e2e80606d4051fbdf7f750909d71d7e68a591ad3dc7e8608158e66d06810` | `../gen-4-in/` |
  | `lesson_card_repair.v1.5` | repair | `646415c370233fd57361fdbb9b08c25318e3b42190e4b920c4479b38ffc652a0` | `../gen-4-in/` |
  | `plan_line_repair.v1` | plan_line_repair | `7ee7a58bf53a64ceeaa13f7907d27b588464c4dae9a646f2789668009d48868f` | свой (наряд GEN-4 §2) |
  | `lesson_seam_judge.v1.1` | seam_judge | `f5da38784e0761a925f77259913042a26741eee2933cc84c663d89ba00fb357d` | прежний |

- **Модели и тарифы** ($ за 1 M токенов, вход / выход; кэш входа — ×0.1): `gpt-5.6-luna` $1 / $6, кэш $0.10 — skeleton,
  dialogue, repair, plan_line_repair по конфигу; `gpt-5.4` $2.50 / $15 — plan (и ступени дня прогона `gpt54`);
  `gpt-5.4-mini` $0.75 / $4.50 — seam_judge. Прогон `gpt54` меняет модель только двух ступеней: починки и судья — те же.
  `gpt-5.4` без `reasoning_effort` рассуждений не тратит; Luna рассуждает и по умолчанию (~2 300 токенов на скелет).
- **Цели** — семнадцать целей GEN-4a (`tools/gate.php`, `PLANS`): все семь целевых и все десять родных, оба уровня; 16 —
  продолжение 02 после трёх его сцен; 17 — намеренно неясная цель.

## 2. Метод

- **Планы.** Первый ответ плана — ответ `gpt-5.4` из прогона GEN-4a на тот же промт и то же сообщение пользователя
  (`runs/plans/gen4a/`, скопировано из ветки `gen-4-prompts` только чтением; sha256 системного текста совпадает): так
  сэкономлено ≈ $0.31 (столько GEN-4a заплатил за 17 ответов). Всё после него — живое: проверки плана v2.1, повтор,
  починки строк сверх лимита (`plan_line_repair`). Итог: 16 планов собраны, 17-й — «неясная цель» (так и задумано GEN-4a).
- **Сцены: 16, не 17.** Наряд просит «17 сцен priority 1»: у цели 17 сцен нет по замыслу, у продолжения 16 новые сцены
  продолжают приоритеты за существующими (PrioritiesCheck) — взята его первая новая по приоритету (priority 4, «Повторный
  приём»). Ядро остальных пятнадцати — сцена priority 1.
- **День** — `LessonRequest`, как его строит `LessonRequests` для дня 1: пол ученика не известен, ранних дней нет,
  `scene_id` = `gen4b-<nn>` (зерно перемешивания). Каждый вызов записан (`RecordingPlanModel`): сообщение пользователя,
  разобранный ответ, **текст модели целиком** (`raw` — из тела вендора: `ModelReply::raw` режется для журнала на 4 000
  знаков), тело вендора (`wire`: usage с токенами рассуждения), модель, токены, цена по `ModelCost`, время.
- **Одна мерка.** Проверки скелета менялись после прогона Luna (§7). Каждый записанный ответ всех прогонов перечитан
  финальным кодом (`gate.php recheck` → `runs/recheck.json`); доли в итоге — по нему, прочтение прогона — рядом, где
  отличается; ответы, которые финальный код прочёл бы иначе, перечислены в `summary.md`.
- **Деньги** — каждый вызов в `spend.json`, вызовы e2e — туда же (из журнала e2e); до плана, дня или скелета «потрачено +
  худшая цена единицы» должно влезть под $5. Вызов, оборванный таймаутом, вендор всё равно списывает — такой записан
  оценкой ($0.09, средняя цена скелета high), помечен `estimated`.

## 3. Результаты — ступени на двух моделях (16 сцен)

| модель ступеней | дней собрано | 1-й скелет без фатальных | 1-й диалог без фатальных | все скелеты / диалоги без фатальных | починок на день | помогло | цена дня | время дня |
|---|---|---|---|---|---|---|---|---|
| `gpt-5.6-luna` | 8 / 16 (50 %) | 5 / 16 (31 %) | 7 / 11 (64 %) | 9 / 27 · 8 / 15 | 2.12 | 31 из 32 оставленных | $0.0878 | 104 с |
| `gpt-5.4` | 13 / 16 (81 %) | 4 / 16 (25 %) | 13 / 14 (93 %) | 14 / 28 · 13 / 15 | 3.19 | 44 из 48 | $0.1394 | 88 с |

Провалы дней: Luna — 04, 08 (`frame.unused`), 09 (`partner.unlinked`), 07, 13 (`vocab.not_found` + чужие буквы), 10
(ложное `vocab.not_found` — исправлено §7), 11 (`pronunciation.equals_native`), 15 (`vocab.not_found`); gpt-5.4 — 02
(`partner.unlinked` дважды — разрыв контракта промтов, §6 п. 1), 05 (чужие буквы), 14 (`partner.pairs_many` дважды).

**Коды первых ответов** (дней, где найден; полная таблица — `summary.md`):

| | gpt-5.4 скелет | Luna скелет | Luna high скелет (10) | gpt-5.4 диалог | Luna диалог |
|---|---|---|---|---|---|
| **partner.pairs_many** (реплика на два каркаса) | 8 | 4 | 2 | — | — |
| **pronunciation.foreign_script** (чужие буквы) | 7 | 6 | 0 | — | — |
| **vocab.not_found** | 1 | 7 | 0 | — | — |
| vocab.definition_language (определение не на языке цели) | 0 | 8 | 2 | — | — |
| partner.names_filler | 12 | 9 | 2 | — | — |
| vocab.stop_word / vocab.used_in_wrong | 5 / 7 | 6 / 9 | 4 / 1 | — | — |
| **frame.unused / partner.missing / twice / unlinked** | — | — | — | 0 / 0 / 0 / 1 | 2 / 1 / 1 / 1 |
| check.verbatim / speaking_key.wrong | — | — | — | 14 / 6 | 11 / 6 |

«Чистых» (без единой находки) первых ответов у обеих моделей почти нет — предупреждения наряда стреляют почти на каждом
ответе и уходят в починку (две карточки на ступень).

**Чужие буквы** в чтениях у gpt-5.4 — кириллица в латинском чтении и иврит («ван קומט ___», «uорк», «тауzэнд»), у Luna —
арабица, японская «で», грузинская, деванагари («tràvel rìimbर्सment»); у Luna high — ни одной.

**Токены и время ступеней** (средние на вызов): скелет Luna — 4 398 выхода, из них 2 271 рассуждения, 36.7 с, $0.027;
gpt-5.4 — 2 042 выхода без рассуждения, 24.6 с, $0.037; Luna high — 14 503 выхода, из них 12 316 рассуждения, 142.6 с,
$0.088. Диалог Luna — 4 037 / 1 436 / 33.9 с / $0.028, gpt-5.4 — 2 796 / 0 / 28.0 с / $0.054. Починка (Luna в обоих
прогонах) — $0.007, 4–5 с; судья швов — $0.002.

**Luna — на коде до правок §7.** Перемерка финальным кодом: страж `partner.pairs_many` находит лишнее в 9 ответах Luna — дни
04, 08, 09 упали бы на скелете (а не на диалоге после двух оплаченных ответов), итог тот же; день 10 не упал бы на скелете
(ложное «vouloir»), чем бы он кончился — не известно: перепрогон не вместился в кап.

## 4. Скелет Luna с effort high (сцены 01–10)

| | ответили | без фатальных | чистых | цена скелета | время |
|---|---|---|---|---|---|
| Luna high | 8 из 10 (04, 09 — нет ответа за 180 с) | 6 из 10 | 2 из 10 | $0.088 | 143 с |
| Luna (первый скелет тех же сцен) | 10 из 10 | 2 из 10 | 0 из 10 | $0.027 | 37 с |

Сцены 11–16 не прогнаны: при потраченных $4.93 скелет high (до $0.10) выводил за кап $5. Поштучно — `summary.md`.

## 5. E2E (§6): «Собеседование в пятницу, боюсь вопросов про опыт», ru→ro, Beginner, 2 сцены

Стенд `wordtrainer_e2e_test`, код ветки (`wt_gen4`), очередь `sync`, модели по конфигу наряда; ученик
`qa-gen4-ru-ro-0929@wt.test` (мужской род, как у дня 26.09); план `01M3PC4TA6Z3T4SWJNHKCHR78R`. Выгрузка — `e2e/`:
`plan.json` (план, сцены, наборы выживания), `day1-skeleton.json`, `day1-lesson.json` (урок, как хранится — перемешанные
варианты), `day1-findings.json`, `calls.json`; первая попытка — `e2e/attempt-1/`. Инструмент — `tools/e2e.php build|retry|dump`.

**План** (`gpt-5.4`, `plan-builder-v2.1`, одна попытка, $0.0166): «Собеседование / Interviu de angajare», две сцены —
1 «Начало встречи» (priority 2: имя, должность, где работал и сколько, спросить обязанности и график), 2 «Опыт и навыки»
(priority 1, ядро: что делал, инструменты, что умею, делал ли задачу, нужен ли опыт в области, что дальше). День 1 — сцена
порядка 1, «Начало встречи»; ядро — день 2 (его урок строится, когда закроется день 1, GEN-3 §11).

**День 1, попытка 1 — упал**: оба скелета Luna — `pronunciation.foreign_script`: латинская «ú» вместо «и́» в кириллическом
чтении («а сэ нумú», «саркúнэ»), двойник, которого `ReadingLetters` не чинит (2 скелета, $0.0493). **Попытка 2** — «ещё
раз» ученика (`RetryLesson`): скелет с первого раза, судья швов, две починки скелета (два определения переведены на
румынский), диалог с первого раза, две починки диалога; `ready` за 68 с, $0.0681. **Цена дня 1** — $0.1174 (обе попытки),
с планом — $0.1340.

**Скелет** (`day1-skeleton.json`): 6 каркасов на 6 пунктов `must_say` — «Mă numesc ___», «Candidez pentru postul de ___»,
«Am lucrat la ___» (un hotel / un magazin / o clinică), «Am lucrat acolo ___», «Care sunt sarcinile principale?», «Care este
programul de lucru?»; 6 реплик интервьюера — каждая со своим каркасом; 8 слов (comunicare, a candida, a lucra, sarcină,
principal, program de lucru, a verifica, a include); чтения — кириллица без чужих букв.

**Урок** (`day1-lesson.json`): 7 обменов (6 + «Puteți repeta, vă rog?»), проверка в каждом — вопрос и варианты на румынском,
перевод на русском; 4 вопроса аудирования.

**Требование наряда — выполнено**: ни одной реплики A, слова или проверки про выдуманное место работы; места работы
(«un hotel», «un magazin», «o clinică») — только в наполнениях ученика, их и спрашивает вопрос аудирования про ученика.

**Было / стало** (день 1 плана 26.09 из `day1-call.md` 27.09 — урок **v4.9**, не v4.10, как в наряде: так в файле
выгрузки PROMPTS-1; сцены разные — тогда днём 1 было ядро «Опыт и навыки», теперь «Начало встречи»):

| | каркасы про сцену | каркасы про историю | слова про сцену | слова про историю | реплики A / проверки про выдуманное место |
|---|---|---|---|---|---|
| было (v4.9, «Опыт и навыки») | 4 из 8 — где работал, сколько, что умею, «Pot să lucrez ___» | 4 — «Am ajutat ___» (клиентам), «Am verificat ___» (товар, счета), «Pot să folosesc ___?» (касса, сканер), «Da, am vorbit cu ei ___» | 0 из 8 | 8 — magazin, depozit, clienți, marfă, casa de marcat, calculator, a verifica, a ajuta | 2 реплики (касса, клиенты) и 2 проверки (когда можно кассой; клиенты / водители / поставщики) |
| стало (GEN-4, «Начало встречи») | 6 из 6 | 0 | 8 из 8 | 0 | 0 |

**Что осталось в уроке**: 6 определений из 8 — по-английски («to have a job or do work», «a duty or task at work», «most
important»…): страж языка поймал 4 (два — «most important», «to contain something as a part» — не узнал, известный предел
LANG-1b), починок — две на ступень, и обе ушли на определения; `vocab.used_in_wrong` у «program de lucru» (не в a6: там
«Programul»). Реплики-ответы интервьюера начинаются с «Da.» на вопрос «какие / какой» — неестественно.

**Голос** (ElevenLabs, только этот план: `plan:speak-backfill --plan=… --count`, затем покупка): оценка — 244 кредита · 916
символов · $0.0488; куплено — **182 кредита · 916 символов · $0.0364**, 36 строк (реплики 7 + 7, фразы 6, наполнения 8,
слова 8). Файлы звука и фото сцен перенесены из `storage` ветки в `storage` main (стенд e2e читает их оттуда).

## 6. Предложения — архитектору и отдельными нарядами

_Пп. 1–4 решены доработкой GEN-4b (§10): модели ступеней, реплика всегда спарена (v1.1, `partner.pairs_none`, `frame.repeated`), `pronunciation.foreign_script` — предупреждение с бюджетом, двойники «ú» / «í» по родному языку; п. 6 — дополнен._

1. **Модели ступеней** (конфиг наряда — Luna — не менялся; решение архитектора): диалог — `gpt-5.4` (93 % первых без
   фатальных против 64 %; день собирается в 81 % против 50 %); скелет — `gpt-5.4` (определения на языке цели, почти без
   `vocab.not_found`) либо Luna high, если поднять таймаут сборки выше 180 с (лучшие скелеты, но 143 с и $0.088 на скелет).
   Цена дня на gpt-5.4 — ≈ $0.14 против $0.09.
2. **Реплика-сообщение без пары** (контракт двух промтов). `lesson_skeleton.v1` разрешает реплике `pairs_with: []`
   («empty when nothing in the set pairs with it»), а у `lesson_dialogue.v1` для сообщения без пары нет формы обмена
   (answer открывает вопрос, ask отвечает сообщением «whose pairs_with names that frame»); DIALOGUE_COUNT наряда даёт обмен и
   такой реплике, и каждому непарному каркасу. gpt-5.4 (день 02) естественно ответил на сообщения a5/a8 непарными каркасами
   p5/p8 — и два «лишних» обмена заполнил выдуманными репликами → `partner.unlinked` дважды, день упал. Решить в промте
   (сообщение без пары — только ответ на непарный ask, либо скелет обязан спарить) или в формуле.
3. **Реплика на два каркаса** (`partner.pairs_many`) — 12 первых скелетов из 32 двух моделей. В промте скелета нет правила
   «одна реплика — один каркас, если второй не взят другой репликой»; страж ловит, но повтор ступени один.
4. **Чужие буквы в чтениях** — 13 первых скелетов из 32; фатально. Правило промта есть; машинно чинятся только двойники
   (`ReadingLetters`); латинская ударная «ú» / «í» в кириллице — двойник «и́» / «і́», зависящий от языка (e2e, день 09 Luna) —
   кандидат в таблицу `ReadingLetters` с учётом родного языка.
5. **Определения по-английски** — Luna в половине скелетов, в e2e 6 из 8: предупреждение, а починок две на ступень. Если
   ступени останутся на Luna — отдельный бюджет починок на определения или страж языка строже.
6. **Формы слова дня** — `lemma_forms` покрывает частые неправильные глаголы семи целей; неправильная форма вне таблицы
   по-прежнему даёт ложное фатальное `vocab.not_found`. Дополнять таблицы по находкам или сделать `vocab.not_found`
   предупреждением, когда `used_in` указывает на каркас.
7. **Мёртвые ключи пакетов** — после снятия валидатора урока их не читает никто: `ordinary_heads`, `closers`,
   `saying_verbs`, `alternative_words`, `second_question_pattern`, `seam_repeatable_words`, `article_sound`, `clause`,
   `unresolved_pronouns`, `agreement` — во всех десяти пакетах, в списке `EnRuPackTest` и в `lang-1/pack-keys.md`. Снять
   нарядом (данные LANG-1, трогать вместе со спецификацией).

## 7. Что прогон изменил в коде

- **`TargetSound`** (до прогона Luna, после первой пробы): чтение общего для двух языков слова — его звук, не родное слово
  («Madrid» — «Мадрид»), иначе `pronunciation.equals_native` ронял бы верный день.
- **`partner.pairs_many`** — страж сверх списка наряда, ФАТАЛЬНО: каркасу, с которым спарена реплика собеседника, не
  остаётся своей реплики (реплика звучит один раз, в одном обмене, с одним каркасом). Добавлен, когда день 04 Luna упал на
  `frame.unused` после двух оплаченных диалогов; первая версия ловила любую реплику на два каркаса — переписана
  паросочетанием (две реплики на два каркаса-ответа — не находка, план 06). Прогон Luna шёл без него.
- **`vocab.not_found` / `vocab.used_in_wrong`** (`TermForms`): неправильные формы из нового необязательного ключа пакета цели
  `lemma_forms` (en, fr, es, it, de, pl, ro; не путать с `irregular_forms` разговора); слово текста читается и по частям при
  апострофе и дефисе («l’ambiance» — «ambiance»); термин из одних служебных слов пакета («vouloir», «können») ищется по всем
  своим словам. Прогон Luna уронил день 10 (ro→fr) на «vouloir», сказанном «Je veux», — ложная фатальная находка.
- **`survival_slot_answer`** (проверка плана): снята ветка «глагол-утверждение», стрелявшая по 51 из 57 наборов.

Все — `5526d701` (и `2f169c27` для первых пунктов). Отдельно, по ревью инвариантов (не по прогону): уроки, записанные одним
вызовом до GEN-4, перемешаны один раз миграцией `2026_09_29_100300` теми же зёрнами, что их перемешивало чтение
(`427e66e1`); сверено на копии e2e — 225 уроков из 225, 2 499 вопросов, вариант в вариант (`tools/served-options.php`).

## 8. Деньги

OpenAI за наряд — **$4.9341** (`spend.json`, 306 строк): дни Luna $1.5119 (с пробным днём 01), дни gpt-5.4 $2.3187 (с
прерванными 13 и 14), скелеты Luna high $0.8870 (из них $0.18 — оценка двух оборванных), планы $0.0825 (пробный живой
план 01 и починки строк), e2e $0.1340. По моделям: `gpt-5.6-luna` $2.92, `gpt-5.4` $1.95, `gpt-5.4-mini` $0.07.
ElevenLabs — 182 кредита · 916 символов · $0.0364 (план e2e). DeepL, Pexels в прогоне ворот не вызывались; в e2e — фото
сцен (Pexels, бесплатно).

## 9. Файлы

- `runs/plans/<nn>.json` — сборка плана: входы, итог, наборы выживания сцен, находки, каждый вызов; `runs/plans/gen4a/` —
  ответы GEN-4a, по которым переигран первый вызов плана.
- `runs/days/<run>/<nn>.json` — сборка дня: запрос, каждая попытка ступени с находками, починки, судья, скелет, урок, каждый
  вызов с сырым ответом; `runs/days/gpt54-interrupted/` — дни 13 и 14, оборванные 429. `runs/skeletons/luna-high/<nn>.json` —
  скелет с effort high.
- `runs/recheck.json` — все ответы ступеней финальным кодом проверок.
- `e2e/` — план, скелет, урок, находки и вызовы e2e; `e2e/attempt-1/` — упавшая первая сборка дня 1.
- `summary.md` — таблицы; `spend.json` — каждый оплаченный вызов.
- `tools/gate.php` (планы, дни, скелеты, recheck, table), `tools/table.php`, `tools/e2e.php` (build, retry, dump),
  `tools/served-options.php` (сверка перемешивания старых уроков).

## 10. GEN-4b — доработка (29.09.2026)

Отчёт GEN-4 принят, не влито: на gpt-5.4 день собирался в 13 случаях из 16, и все три провала (02, 05, 14) — контракт промтов и
стражи. Доработка чинит их и перегоняет только упавшее. Кап OpenAI — $1, ElevenLabs не звался.

**Что изменено.**
- **Модели** (`config/plan.php`): `plan`, `skeleton`, `dialogue` — `gpt-5.4`; `repair`, `plan_line_repair` — `gpt-5.6-luna`;
  `seam_judge` — `gpt-5.4-mini`. `reasoning_effort` в адаптере остаётся необязательным и нигде не включён. Luna high не
  используется (DECISIONS п. 453: 143 с на скелет, 2 таймаута из 10, $0.088 за скелет).
- **Промты v1.1** — правки контракта, не содержания, в существующих разделах, TEST INPUT байт в байт прежний:
  `lesson_skeleton.v1.1` `04b69d4121d713a20d982b0bcfbf560ee03f0f76b4309e55b142a57d55a0e938`, `lesson_dialogue.v1.1` `eaf34ffeaabf8d264235fcd9e96efd6723cb8927d2711afc3d28dda5af27604e`; v1 снесены, реестр и страж обновлены.
  Скелет: `pairs_with` — ровно один каркас (пункт на два каркаса — две реплики, ✗ `[1, 2]`); сообщение паруется с каркасом,
  за которым следует в разговоре; пустой `pairs_with` — только остаток; ответ на «какой / что / сколько / когда» — сразу факт,
  «Da./Nu.» — только на да/нет (✗ «Da. Programul este…» на «Care este programul de lucru?»). Диалог: реплику-остаток A говорит в
  своём обмене, ученик отвечает уже сказанным каркасом с другим наполнением; повтор каркаса «для счёта» снят; формула
  DIALOGUE_COUNT прежняя.
- **Стражи**: `partner.pairs_none` (скелет, фатально) — реплика без пары, пока у каркаса нет реплики; `frame.repeated`
  (диалог, фатально) — каркас второй раз вне обмена остатка и вне второй реплики того же каркаса; `partner.pairs_many` и
  `partner.unlinked` — как были.
- **`pronunciation.foreign_script` — предупреждение с бюджетом** (`LessonCodes::BUDGETED`): его карточки (каркас, реплика,
  слово) идут в починку первыми; фатально — только если карточек больше двух (починок ступени) или находка стоит на
  заголовке, описании, роли. **`ReadingLetters`** читает двойники родного языка: латинская ударная «ú» / «í» в кириллическом
  чтении — «и́» для ru, «і́» для uk/be (парсер знает язык ученика — `LessonParser::forNative`; `plan:clean-text` строк без
  плана не знает и её не трогает); иврит, арабица, деванагари — в починку. Канон-тест: «а сэ нумú» → «а сэ нуми́».
- **`lemma_forms`**: с финальным кодом GEN-4 все `vocab.not_found` прогона — настоящие (слова в тексте нет); ложные
  фатальные прогона («vouloir» — «veux», «falloir» — «faut», «erziehen» — «erzogen», «a putea» — «pot», «go» — «going») закрыты
  ещё `5526d701`. Добавлены формы, которые прогон прочёл ложно в предупреждениях `vocab.used_in_wrong`: de «gelten» — «gilt»,
  «anmelden» — «angemeldet» и причастия отделяемых глаголов визита (`ausfüllen` — «ausgefüllt», `abholen`, `vorstellen`,
  `einkaufen`, `aufstehen`, `ausziehen`, `abgeben`, `mitnehmen`, `zurückrufen`, `teilnehmen`, `stattfinden`, `abmelden`); it
  «bisognare» — «bisogna» (безличный, как «falloir»). `vocab.not_found` — по-прежнему фатальный.
- Уроки одним вызовом до GEN-4 — миграция перемешивания (`427e66e1`) — без изменений.

**Перепрогон — дни 02, 05, 14 на gpt-5.4, промты v1.1** (`runs/days/gpt54-b/`, тот же харнесс):

| план | было (GEN-4) | стало (GEN-4b) | скелет, 1-й ответ | диалог, 1-й ответ | повторы | починки: отпр. / оставл. / помогли | цена | время |
|---|---|---|---|---|---|---|---|---|
| 02 ru→en «Приём у врача» | упал: `partner.unlinked` ×2 (сообщения без пары, диалог выдумал 2 реплики), $0.165 | **собран** | без фатальных: 7 предупреждений (`learner.gender` ×3, `filler.repeats_frame`, `partner.names_filler`, `vocab.stop_word`, `vocab.used_in_wrong`) | без фатальных: `check.verbatim` ×3, `speaking_key.wrong`, `variant.longer` | 0 / 0 | 4 / 4 / 4 | $0.1413 | 85 с |
| 05 es→en «Control migratorio» | упал: реплика на два каркаса, затем кириллица в чтении «uорк», $0.057 | **собран** | `pronunciation.foreign_script` (теперь карточка — починена первой), `partner.names_filler`, `vocab.used_in_wrong` | `check.verbatim` | 0 / 0 | 3 / 3 / 3 | $0.0952 | 65 с |
| 14 en→ro «Interview» | упал: `partner.pairs_many` ×2, $0.071 | **собран** | **чистый** — ни одной находки | `check.verbatim` ×4 | 0 / 0 | 2 / 2 / 1 | $0.1053 | 77 с |

Все три — с первого ответа каждой ступени. Вместе с 13 днями GEN-4 на gpt-5.4 собраны бы 16 из 16 (остальные 13 не
перегонялись — по наряду).

**Одна мерка GEN-4b** (`summary-b.md`, `runs/recheck-b.json` — все ответы всех прогонов кодом GEN-4b): новые стражи находят в
ответах на промты v1 ровно шаблон провала дня 02 — `partner.pairs_none` в 14 ответах-скелетах (Luna 8 из 27, gpt-5.4 3 из
28, Luna high 3 из 8), `frame.repeated` в 3 диалогах (Luna 02, gpt-5.4 02 ×2). На v1.1 — ни в одном из четырёх скелетов (три
дня и e2e-b); выборка мала — следить по счётчикам (`plan_check_counters`, `partner.pairs_none`).

**E2E-b** (`e2e-b/`, `tools/e2e-api.py`): план «Собеседование в пятницу, боюсь вопросов про опыт», ru→ro, Beginner, 2 дня,
**через API** — `POST /auth/dev` (QA `qa-gen4b-ru-ro-0929@wt.test`), `PATCH /profile` (ru, мужской род), `POST /plans` (202
за 99 с: очередь `sync`, план и день 1 внутри запроса), `GET /plans/{id}` → план `ready`, день 1 `lesson_status: ready`. План
`01M3PH274ZB7RYCQMJ0AB13PA1`; день 1 — «Опыт работы / Experiență de muncă».
- **Собран с первой попытки**: один скелет, один диалог, без повторов; две починки скелета и две диалога; на дне остались
  три предупреждения проверок (`check.answer_is_filler`, `check.verbatim` ×2). Цена — план $0.0162, день $0.1121, **всего
  $0.1283**. Голос не покупался.
- **Контракт v1.1**: 7 каркасов, 7 реплик, у каждой ровно один каркас; «Care este programul?» → «Programul este de luni până
  vineri, de dimineață.» — сразу факт; определения по-румынски; чтения — кириллица без чужих букв.
- **Про выдуманное место работы**: реплики A и проверки — про встречу; места работы — в наполнениях ученика. **Но** слово v3
  «depozit» (склад) взято из наполнения-заглушки ученика «Am lucrat la un depozit» — промт это запрещает («Never: a word of a
  placeholder filler»), проверки кода на это нет. И a6 отвечает на вопрос да/нет («Postul include lucrul cu marfa?») без
  «Da./Nu.», перечисляя смыслы других наполнений p6 («lucru cu clienții și pregătirea documentelor») — `partner.names_filler`
  его не видит: формы не совпадают дословно.

**Ворота GEN-4b** (на `97c6e21b`): OpenAPI ok ×2, deptrac 0, PHPStan 0, Pest 3 060 (`--parallel`), `migrate:fresh` ок,
invariant-reviewer — CLEAN.

**Деньги GEN-4b**: OpenAI — **$0.4701 из $1** (три дня $0.3418, e2e-b $0.1283; `spend.json`, единицы `day-gpt54-b-*` и
`e2e-b`); ElevenLabs — 0.

**Полный дифф промтов** (`prompts-v1.1.diff`):

```diff
--- lesson_skeleton.v1.md
+++ lesson_skeleton.v1.1.md
@@ -1,4 +1,4 @@
-LESSON SKELETON — v1
+LESSON SKELETON — v1.1
 
 You write the SKELETON of one day of a situational language lesson: the sentence frames the learner will practise, the lines the conversation partner will say, and the vocabulary. You do not write the dialogue — a later step puts your frames and lines into a conversation and cannot add anything you did not write. There is no story here: the only facts in the skeleton are the ones the input gives.
 
@@ -14,7 +14,7 @@
 
 SURVIVAL_SET: two numbered lists written by the plan.
 must_say — 6 to 8 intentions in the order they come up in the interaction: "what the learner says — slot: what varies". Each becomes exactly ONE frame.
-must_understand — 4 to 5 things the partner says or asks here, from the partner's side. Each becomes ONE partner line — two lines when the item holds two questions.
+must_understand — 4 to 5 things the partner says or asks here, from the partner's side. Each becomes ONE partner line — two lines when the item holds two questions or covers two frames.
 
 TARGET_LANGUAGE, NATIVE_LANGUAGE, LEVEL (Beginner or Intermediate).
 
@@ -76,15 +76,15 @@
 
 PARTNER LINES
 
-One line per must_understand item, in the order of the list — two lines when the item holds two questions, because A asks one thing per line; both carry the item's number.
+One line per must_understand item, in the order of the list — two lines when the item holds two questions or covers two frames ("asks your name and which position you want": one line for the name, one for the position), because A asks one thing per line and every line goes with one frame; both carry the item's number.
 
 - id "a1", "a2", …; must_understand — the item's number.
 - kind: "question" when A asks (the learner will answer it with a frame); "statement" when A states a fact, gives an instruction, makes an offer or answers the learner.
-- pairs_with: the must_say numbers this line goes with — for a question, the frame that answers it; for a statement, the "ask" frame it replies to; empty when nothing in the set pairs with it.
+- pairs_with: exactly ONE must_say number — of the one frame this line goes with. A question pairs with the frame that answers it; a statement with the frame it follows in the conversation (the learner says the frame, A replies with this line), among the frames that have no line of their own yet. Never one line for two frames ("Cum vă numiți și pentru ce post candidați?" with "pairs_with": [1, 2] ✗ — that is two lines). pairs_with is empty only for a line left over when every frame already has its line — a remainder, never a choice.
 - text_target: one sentence, or two when necessary, at most 18 words, in TARGET_LANGUAGE; A addresses the learner formally (vous / Sie / usted / dumneavoastră) unless the scene is clearly casual. text_native: its natural rendering; the same formality («вы», never «ты» in Russian); A's grammar follows role_gender.
 - A question asks ONE thing. A statement carries ONE concrete fact the learner can be tested on: a time, a condition, an amount, a list of two or three things. General does not mean vague: "a trial month", "training in the first week", "a team of five", "shifts of morning and evening" are facts; "practical tasks every day", "various duties" are not. The examples here are examples — choose the fact that fits this scene, do not copy them.
 - The fact is about the matter of the scene — the job on offer, the diagnosis, the flat, the appointment — and it is general: true for this kind of situation, not built on any filler of the frames. A never names the learner's placeholder values (the shop, the goods, the shelves, the illness you chose for a slot).
-- A statement that replies to an "ask" frame has TWO parts: the answer ("Da." / "Nu.") and ONE concrete fact about the matter of the scene that does not depend on what was asked — so it fits every filler of that frame alike and names NONE of them (not the one used in the dialogue, not the others, not all of them in a list). For "Postul include ___?" the reply is "Da." or "Nu." followed by one fact about this job that holds whatever was asked — choose the fact for this scene yourself. Never a filler repeated back ("Da, include lucrul cu marfa." ✗), never the fillers listed ("Da, include instruire, documente și lucru cu publicul." ✗), never the answer alone ("Da, postul include această sarcină." ✗ — nothing to test).
+- A statement that replies to an "ask" frame carries ONE concrete fact about the matter of the scene that does not depend on what was asked — so it fits every filler of that frame alike and names NONE of them (not the one used in the dialogue, not the others, not all of them in a list). To a yes-or-no question it has TWO parts, the answer ("Da." / "Nu.") and the fact: for "Postul include ___?" the reply is "Da." or "Nu." followed by one fact about this job that holds whatever was asked — choose the fact for this scene yourself. To a question of which, what, how many or when it is the fact itself, with no "Da." / "Nu." ("Da. Programul este de luni până vineri." as the reply to "Care este programul de lucru?" ✗). Never a filler repeated back ("Da, include lucrul cu marfa." ✗), never the fillers listed ("Da, include instruire, documente și lucru cu publicul." ✗), never the answer alone ("Da, postul include această sarcină." ✗ — nothing to test).
 - Never empty lines ("Great!", "Anything else?"); never two questions in one line; no two partner lines carry the same fact.
 
 ---
@@ -115,7 +115,7 @@
 
 WHEN THE SET IS NOT PERFECT
 
-The set is written by another model. Repair it silently, keeping every intention you can: an item that is an action ("show your passport") → the words said while doing it ("Here is my ___"); a bare yes-or-no item → the frame carries the thing confirmed ("I can start ___"); an item whose natural frame is already a Frame of EARLIER_DAYS → no frame for it (the only reason to drop an item); an item that cannot be said in ten words at LEVEL → say less; an item no person in LEARNER_ROLE would say to PARTNER_ROLE here → the closest thing they would say, never an unrelated intention; a must_understand question with no answer in must_say → still a partner line, pairs_with empty.
+The set is written by another model. Repair it silently, keeping every intention you can: an item that is an action ("show your passport") → the words said while doing it ("Here is my ___"); a bare yes-or-no item → the frame carries the thing confirmed ("I can start ___"); an item whose natural frame is already a Frame of EARLIER_DAYS → no frame for it (the only reason to drop an item); an item that cannot be said in ten words at LEVEL → say less; an item no person in LEARNER_ROLE would say to PARTNER_ROLE here → the closest thing they would say, never an unrelated intention; a must_understand question with no answer in must_say → still a partner line, paired with a frame that has no line of its own yet; pairs_with empty only when every frame has its line.
 
 ---
 
@@ -186,7 +186,7 @@
 ]
 }
 
-Field rules: phrase — id, kind ("answer" | "ask"), must_say (array of item numbers, normally one), frame_target, frame_native, pronunciation_native, slot (object or null); filler — target, native, pronunciation_native, in_dialogue; partner line — id, must_understand (item number), kind ("question" | "statement"), pairs_with (array of must_say numbers, may be empty), text_target, text_native; vocabulary — id, term_target, translation_native, pronunciation_native, definition_target, kind ("word" | "chunk"), image_prompt, used_in. role_gender: "female" or "male". Roles exactly as given.
+Field rules: phrase — id, kind ("answer" | "ask"), must_say (array of item numbers, normally one), frame_target, frame_native, pronunciation_native, slot (object or null); filler — target, native, pronunciation_native, in_dialogue; partner line — id, must_understand (item number), kind ("question" | "statement"), pairs_with (array of exactly one must_say number; empty only for a line left over when every frame has its line), text_target, text_native; vocabulary — id, term_target, translation_native, pronunciation_native, definition_target, kind ("word" | "chunk"), image_prompt, used_in. role_gender: "female" or "male". Roles exactly as given.
 
 ---
 
@@ -196,7 +196,7 @@
 
 - One frame per must_say item, each with its number, none outside the set; an item dropped only because it is already learned; "slot: none" → slot null; kind ask/answer by the item's verb; frame part ≤ 7 words; no pattern shared with EARLIER_DAYS or with another frame, in either language.
 - Fillers: 2–3 per slotted frame, values of 1–3 words, exactly one in_dialogue; the learner's own details in_dialogue where they fit; placeholders across frames do not form one job or story; no word repeated across the seam; native fillers in the required form; every assembled pair grammatical in both languages.
-- Partner lines: one per must_understand item (two when it holds two questions), each with its number and pairs_with; one question or one fact per line; ≤ 18 words; nothing built on a filler, no placeholder value named; a reply to an "ask" frame fits every filler of that frame and names none of them.
+- Partner lines: one per must_understand item (two when it holds two questions or covers two frames), each with its number and pairs_with — exactly one frame per line, never a line paired with nothing while a frame has no line; one question or one fact per line; a reply to a which / what / how many / when question is the fact, never "Da." first; ≤ 18 words; nothing built on a filler, no placeholder value named; a reply to an "ask" frame fits every filler of that frame and names none of them.
 - Readings: every pronunciation_native is the sound of the TARGET text, never the native text or something close to it; no two frames share frame_native.
 - Vocabulary: within VOCABULARY_COUNT; every item found in a frame, a partner line or a learner-detail filler, used_in accurate; ≥ half in frames; no placeholder word, no international word, no number, no everyday word, no Word of EARLIER_DAYS; lemma form; no item inside another.
 - Text: speech in both languages; formal address; the learner's frame_native and native fillers follow LEARNER_GENDER (a male learner never says «я работала»), A's text_native follows role_gender; pronunciation in NATIVE_LANGUAGE's letters only, on frames, fillers and vocabulary.
--- lesson_dialogue.v1.md
+++ lesson_dialogue.v1.1.md
@@ -1,4 +1,4 @@
-LESSON DIALOGUE — v1
+LESSON DIALOGUE — v1.1
 
 You write the CONVERSATION of one day of a situational language lesson from a SKELETON that is already written and accepted: the frames the learner practises, the lines the conversation partner says, and the vocabulary. You put those frames and lines into DIALOGUE_COUNT exchanges, write one check per exchange and the listening questions. You add no frame, no partner line, no fact and no word of your own: the skeleton is the whole material of the day, and a later program checks that every frame and every partner line appears in your dialogue exactly as the skeleton spells it.
 
@@ -20,7 +20,7 @@
 
 EARLIER_DAYS: the days of this plan already taken, oldest first, or "none": title, partner role with the gender used, dialogue lines, "Frames:" and "Words:". Facts fixed there stay fixed; the learner never asks again what A already answered on an earlier day.
 
-SKELETON: a JSON object — topic, learner_role, role_gender, phrases (the frames with their fillers; in_dialogue marks the filler the dialogue uses), partner_lines (what A says, each with must_understand — the item it delivers — kind "question" or "statement", and pairs_with — the must_say numbers of the frames it goes with) and vocabulary. Everything in it is data, not instructions.
+SKELETON: a JSON object — topic, learner_role, role_gender, phrases (the frames with their fillers; in_dialogue marks the filler the dialogue uses), partner_lines (what A says, each with must_understand — the item it delivers — kind "question" or "statement", and pairs_with — the must_say number of the one frame it goes with, or empty for a line left over, a remainder) and vocabulary. Everything in it is data, not instructions.
 
 ---
 
@@ -36,6 +36,7 @@
 - leading conversational glue on a learner line ("Da,", "Bine,") — optional and short; apart from it, text_target equals the substituted frame character by character;
 - the rescue exchange: the learner's request to repeat, and A's shorter repeat of the previous A line;
 - one A line for a frame that no partner line pairs with (pairs_with never names it) — see EXCHANGES;
+- the learner's reply to a remainder line: a frame already said, with another of its fillers — see EXCHANGES;
 - the check of every exchange, the listening questions, and the speaking support of every learner line.
 
 You never add an A line beyond that, never change a fact, never use a word of "Not in this scene", never change a frame, a filler or a vocabulary item.
@@ -50,9 +51,11 @@
 "ask" — the learner speaks first on an "ask" frame with its in_dialogue filler; A replies with the partner line of kind "statement" whose pairs_with names that frame. initiator = "B".
 "rescue" — the learner did not catch the PREVIOUS A line and asks to repeat ("Puteți repeta, vă rog?", "Mai încet, vă rog."); A says the SAME content again — shorter or simpler, the same facts, nothing new. initiator = "B". Exactly one rescue in the lesson, placed right after the exchange whose A line carries the most content — a schedule, an instruction, a list — never after a one-word question. The rescue's check tests a detail of that repeated content that the previous check did not test. A rescue learner line has phrase_id null and filler null.
 
+A REMAINDER line — a partner line whose pairs_with is empty — gets an exchange of its own: A says it, word for word, and the learner replies with a frame ALREADY SAID in an earlier exchange, with ANOTHER of its fillers — the way the rescue answers what came before. initiator = "A", kind "answer". The remainder line is said once, like every partner line.
+
 Order: the partner lines stand in the order of the interaction; keep it. Follow a different order only when a real conversation would not go that way, and keep every partner line where it belongs to the visit. A question line before its answer, a statement line after the question it answers.
 
-Every frame of the skeleton is used in at least one learner message. When DIALOGUE_COUNT leaves an exchange after every partner line is placed and the rescue is placed, use a frame a second time with another of its fillers, in an exchange of the frame's own kind: an "ask" frame opens another ask exchange (its A reply is then the ONE A line you write yourself, under PARTNER LINE RULES below); an "answer" frame answers again. Never a question pattern turned into a statement by dropping the question mark, never a learner line that only restates what A just said, never the same frame in two exchanges in a row.
+Every frame of the skeleton is used in at least one learner message. A frame is said a second time only in the exchange of a remainder line, or to a second partner line paired with it — never to fill the count: DIALOGUE_COUNT leaves no exchange over. Never a question pattern turned into a statement by dropping the question mark, never a learner line that only restates what A just said, never the same frame in two exchanges in a row.
 
 A frame no partner line pairs with gets an exchange of its own: for an "answer" frame you write A's question that the frame answers; for an "ask" frame, A's reply. PARTNER LINE RULES for every A line you write yourself: one question or ONE concrete fact about the matter of the scene, at most 18 words, a reply to an "ask" frame fits every filler of that frame and names none of them, nothing about the learner's fillers (the shop, the goods, the illness), nothing from "Not in this scene", nothing already said by another A line. Such a line carries partner_line null and must_understand null.
 
@@ -187,7 +190,7 @@
 
 - Exchanges = DIALOGUE_COUNT, steps 1..N; exactly two messages each; the first message's speaker matches initiator; the second message never ends with "?".
 - Every partner line of the skeleton appears exactly once, character for character, in an exchange carrying its must_understand and its id; the only other A lines are the rescue repeat and, where a frame has no pair, one line under PARTNER LINE RULES.
-- Every frame is used at least once; every answer/ask learner line = frame with a filler of that frame (the in_dialogue one first), verbatim apart from glue; a frame used twice takes two different fillers, keeps its kind, and is not in two exchanges in a row; a question pattern is never made a statement.
+- Every frame is used at least once; every answer/ask learner line = frame with a filler of that frame (the in_dialogue one first), verbatim apart from glue; a frame is used twice only for a remainder line or a second line paired with it, with two different fillers, never in two exchanges in a row; a question pattern is never made a statement.
 - Exactly one rescue, right after the A line with the most content; its A reply repeats that content with nothing new; its check tests a different detail.
 - Learner lines ≤ 10 words excluding glue; speaking_key from the frame part only, a real substring; simplified_variants 1–2 (or [] for ≤ 4 words), never longer, never identical; pronunciation on every B message, on no A message.
 - Checks: one per exchange, about A's message, 3 options, one correct, paraphrase (no 2+ consecutive words of A's message), same-kind distractors, both languages.
```
