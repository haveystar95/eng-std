# SESSION-1a · день из 28 тренажёров, судья окна `slot_judge.v1`, контракт карточек

Наряд 15–16.09.2026, репо `backend2` (`mobile/` не трогался). Правда по кадрам — канва
`docs/design/session-canvas.dc.html` (47 кадров, в git с `406af403`), карта — `docs/session-map.md`; канон —
`docs/plan-v2.md` §6 (переписан целиком), контракт — `docs/plan-api.md` «Карточки сессии» + `openapi/openapi.yaml`
(тег `Plans`); решения — `docs/DECISIONS.md` пп. 325–326; промт — `docs/prompts/REGISTRY.md`, строка **SLOT JUDGE v1**.
Код — `682db307`, документы — `cb3cdae9`; хвост (§14) — код `4e85619a`, документы — коммит после него. Живые прогоны — `wordtrainer_e2e_test`; голос, фото, уроки и планы не
покупались, план Дена не пересоздавался.

## §1. Итог

- **Реестр видов принят и раздаётся**: в enum `CardKind` **29** значений, раздаются **28** (слова 6, фразы 9, диалог 4,
  слушание 6, речь 3); `listen_pairs` (34-4) зарезервирован и не раздаётся — в уроке нет двух похожих реплик, источник
  появится в `lesson_day.v4.6`. Каждый вид принадлежит ровно одному способу зачёта, и что клиент вправе записать —
  решает код (`CardKind::allows`), а не договорённость (DECISIONS п. 325).
- **Сборка дня переписана** (`Plan/Domain/Assembly`): пять этапов заново, разнесение `Spacing::interleave` (A_i, B_{i−1},
  C_{i−2}), ротации `Rotation::pick` с зерном сцены, общие объекты карточек в одном `CardObjects`, звук и фото —
  заглушки в payload, которые доезжают на чтении (`CardViews`).
- **13 старых видов снесены без периода совместимости** одной миграцией `2026_09_16_100000_deal_session_cards`: она же
  меняет оба CHECK (`kind` 29 значений, `unit_kind` + `day`), добавляет `day_cards.response jsonb` и **сносит
  `day_cards` целиком** на всех базах после бэкапа; статусы дней, даты и метрики не тронуты. Флагов «старое/новое» и
  папок legacy нет, телефон до SESSION-1b сессию дня не проходит — принято.
- **Единица `day`**: лента, вопросы `listening`, предсказание, темп и число — карточки единицы `day`; она никогда не
  возвращается, её нет ни в `program[]` комнаты, ни в программе окна (D-05).
- **Судья окна `slot_judge.v1`** — единственный вызов модели в горячем пути: `POST …/cards/{card}/judge`, один HTTP-вызов
  без ретрая, таймаут 8 с, вне транзакции, кап 60 на ученика в местные сутки (Redis). Деградация всегда в код, никогда в
  отказ ученику (DECISIONS п. 326).
- **DayPace — секунды на карточку по ВИДУ** (28 значений в `config/plan.php` → `plan.pace`), не по этапу; окно дня и
  `minutes_left` этапов считаются по этой таблице.
- **Контракт аддитивен**: конверт карточки получил `unit {kind, ref}`, `source_day` (НОМЕР дня), `response`; `GET
  …/days/{n}` отдаёт `stages[].cards[]`; `POST …/answer` отвечает `{card, requeued, unit, day, stage}`; новый `POST
  …/judge`. Старые ключи конверта стоят рядом теми же значениями.
- **Две фикстуры — вход клиента**: `docs/fixtures/day-doctor.json` (intermediate, 344 748 байт) и
  `day-doctor-beginner.json` (beginner, 338 198 байт) — полный раздатый день из чистого урока `FakePlanModel`,
  детерминированные, тест держит их байт-в-байт.
- **Живьём на e2e**: день 1 плана «врач» (урок `lesson_day.v4.4`, intermediate) роздан в **79 карточек / 26 минут**;
  смоук судьи — 11 попыток, 7 живых вызовов `gpt-5.4-mini-2026-03-17`, **$0.003803**, оба пути деградации (молчание
  ключа и исчерпанный кап) проверены со счётчиком.
- **Ворота** (в конце наряда): deptrac — **0 нарушений**, PHPStan L8 — **0 ошибок**, Pest полный — **2 223 зелёных**
  (11 529 проверок, 10 параллельных процессов, **60.33 с**; первый прогон дал 11 мелких замечаний PHPStan и 2 222 теста —
  замечания исправлены, тест прибавился вместе с находкой `invariant-reviewer`, §13). `flutter analyze` **не гонялся** — `mobile/` не тронут,
  решение архитектора (уточнение 16.09 к правилу «обе стороны на финальном коммите»).

## §2. Реестр видов

Кадр — по `docs/session-map.md`; `payload` — ключи верхнего уровня (в каждом есть `scene_id`), «может быть `null`»
отмечено там, где встречается. Таблица сходится с `docs/plan-api.md` «Карточки сессии» и с обеими фикстурами.

| `kind` | кадр | `payload` | режим клиента | кто и как зачитывает |
|---|---|---|---|---|
| **Слова** — `unit {word, v3}` | | | | |
| `word_intro` | 31-1 | `term`, `used_in` (`null`, если слова нет в репликах), `audio {term, line}` | чтение + звук | тап «Дальше» → `passed` |
| `word_repeat` | 31-2 | `term`, `expected_text`, `coverage_min`, `audio {term}` | голос | клиент: покрытие речи; две попытки → `skipped` |
| `word_choose` | 31-3 / 31-4 | `direction`, `prompt`, `options[4]`, `correct` | тап | клиент: id варианта = `correct` |
| `word_listen` | 31-5 | `audio`, `options[4]` — слова **цели**, `correct` | тап (звук → написание) | клиент: сверка с `correct` |
| `word_assemble` | 31-6 | `term`, `tiles[]`, `expected[]` | плитки | клиент: собранное = `expected` по порядку |
| `word_in_line` | 31-7 | `line` (с `___`, `text_native`, `text_native_gapped` — может быть `null`, `audio`), `options[4]` (у каждого `audio`), `correct` | тап | клиент: сверка с `correct` |
| **Фразы** — `unit {phrase, p3}` | | | | |
| `phrase_intro` | 32-1 | `frame`, `said` | чтение + звук + чипы | тап «Дальше» → `passed` |
| `phrase_assemble` | 32-2 | `frame`, `target_native`, `tiles[]`, `chips[]`, `expected {words, slot_at, filler_index}` | плитки + чип в окно | клиент: слова = `expected.words`, окно на `slot_at`, наполнение — `expected.filler_index` |
| `phrase_choose_back` | 32-3 | `prompt` (цель: `text_target`, `pronunciation_native`, `filler_index` — может быть `null`, `audio`), `options[4]` (родной), `correct` | тап | клиент: сверка с `correct` |
| `phrase_slot` | 32-4 | `frame`, `prompt_native`, `options[4]` (у каждого `audio`), `correct` | тап + «прослушать» вариант | клиент: сверка с `correct` |
| `phrase_slot_listen` | 32-5 | `frame`, `filler_index`, `audio`, `options[4]` (без звука), `correct` | тап | клиент: сверка с `correct` |
| `phrase_repeat` | 32-6 | `frame`, `filler_index` (может быть `null`), `expected_text`, `key`, `coverage_min`, `audio` | голос | клиент: покрытие `expected_text`; две попытки → `skipped` |
| `phrase_other_slot` | 32-7 | `frame`, `filler_index` (НЕ сказанное), `task_native`, `expected_text`, `slot_expected`, `key`, `coverage_min` | голос, звука у листа нет | клиент: покрытие каркаса **И** все слова `slot_expected` (считаются раздельно) |
| `phrase_combine` | 32-8 | `exchange`, `partner_line`, `frames[3]`, `correct_frame`, `chips[]`, `correct_filler` | тап: каркас → чип | клиент: каркас = `correct_frame`, наполнение — любое из `chips` |
| `phrase_own_slot` | 32-9 | `frame`, `partner_line` (может быть `null`), `task_native`, `examples[]`, `chips[]`, `key`, `coverage_min`, `judge: true` | чипы или «сказать своё» голосом | **сервер**, `POST …/judge` |
| **Диалог** — `unit {exchange, x3}` | | | | |
| `dialogue_partner` | 33-1 | `exchange`, `partner_line`, `question_native`, `options[4]` (родной), `correct` | тап | клиент: сверка с `correct`; текст реплики открывается после верного |
| `dialogue_answer` | 33-2 / 33-3 / 33-4 | `exchange`, `partner_line`, `own_line`, `frame`, `modes {chips, voice_hint, voice_blind}`, `coverage_min` | чипы / голос с подсказкой / голос вслепую — выбирает клиент | клиент: чипами — любое наполнение; голосом — покрытие каркаса, окно любое; две попытки → `skipped` |
| `dialogue_ask` | 33-5 | те же ключи (ученик говорит первым) | то же | то же |
| `dialogue_rescue` | 33-6 | `exchange`, `asked_line` (может быть `null`), `rescue_line`, `partner_repeat`, `slow_rate`, `expected_text`, `coverage_min` | прослушивание и переспрос | тап «Дальше» → `passed`: микрофона на кадре нет, `expected_text`/`coverage_min` лежат на будущее |
| **Слушаю и отвечаю** — `unit {day, day}`; у `listen_question` ref `L2` | | | | |
| `listen_dialogue` | 34-1 | `lines[]`, `total_ms` (может быть `null`) | плеер, текста клиент не показывает | тап «Дальше» → `passed` |
| `listen_question` | 34-2 | `question {ref, text_native}`, `options[4]`, `correct`, `exchange_step` (может быть `null`) | тап | клиент: сверка с `correct` |
| `listen_review` | 34-3 | `lines[]`, `total_ms`, `answers[{question_ref, exchange_step, line_ref, span}]` (`line_ref`/`span` могут быть `null`) | разбор с подсветкой | тап «Дальше» → `passed` |
| `listen_pairs` | 34-4 | — | — | **не раздаётся**, зарезервирован в enum; схемы payload у него нет |
| `listen_predict` | 34-5 | `exchange`, `own_line`, `options[3]`, `correct`, `partner_line` | тап | клиент: сверка с `correct`; реплика A открывается после |
| `listen_pace` | 34-6 | `exchange`, `partner_line`, `rates [0.75, 1.0]` | плеер на двух темпах | тап «Понял» → `passed` |
| `listen_number` | 34-7 | `line`, `span`, `options[3]`, `correct` | тап | клиент: сверка с `correct` |
| **Говорю сам** — `unit {exchange, x3}` | | | | |
| `speak_answer` | 35-2 | `exchange`, `partner_line` (у `ask` — реплика A **предыдущего** обмена; у первого `null`), `own_line`, `task_native`, `frame`, `hint`, `key`, `coverage_min`, `judge: true` | голос; каркас-подсказка после 5 с молчания или по кнопке (35-5) | **сервер**, `POST …/judge` |
| `speak_echo` | 35-3 | `exchange`, `partner_line`, `expected_text`, `coverage_min` (0.7), `pause_ms` (3000) | голос, текст скрыт до ответа | клиент: покрытие; две попытки → `skipped` |
| `speak_retell` | 35-4 | `exchange`, `partner_line`, `reveal {text_target, text_native}`, `judge: true` | голос на **родном** | **сервер**, `POST …/judge` |

Общие объекты одинаковы везде: `term {ref, text_target, text_native, pronunciation_native, definition_target, image}`;
`frame {ref, kind, frame_target, frame_native, frame_pronunciation_native, slot}`, наполнение `{index, target, native,
pronunciation_native, in_dialogue, native_line, audio}`; `exchange {ref, step, kind}`; реплика `{ref, text_target,
text_native, audio}`; `own_line` плюс `frame_ref`, `filler_index`, `key`; звук `{ref, url, duration_ms, voice}`; фото
слова `{url, tone}`.

Ложные варианты нигде не совпадают ни с верным, ни друг с другом по тексту без учёта регистра (`Options::choose`), а id
`o1…oK` раздаются в ПОКАЗАННОМ порядке. Терпимость — одним правилом `Options::MIN = 2`: карточка выбора, у которой не
осталось двух вариантов, не раздаётся вовсе, `Spacing::interleave` принимает дыры.

## §3. Отбор и день «врач»

**Живой день** (e2e, план `01M2H13E1QT6F5D4FKJSEKTAD7`, сцена `01M2H13KSAS23K4YPF1M65SJQD`, урок `lesson_day.v4.4`,
уровень intermediate; выгрузка — `e2e-day-doctor.json`):

| этап | карточек | секунд по `plan.pace` | минут |
|---|---|---|---|
| Слова | 24 | 300 | 5 |
| Фразы | 22 | 389 | 7 |
| Диалог | 15 | 330 | 6 |
| Слушаю и отвечаю | 10 | 258 | 5 |
| Говорю сам | 8 | 265 | 5 |
| **день** | **79** | **1 542** | **26** (`window.day.minutes_estimate = 26`) |

Минуты дня считаются из секунд дня, а не суммой округлённых минут этапов (иначе было бы 28).

**Порядок видов** (единица в скобках, как роздано):

- **Слова** (8 терминов): `intro v1`, `intro v2`, `repeat v1`, `intro v3`, `repeat v2`, `in_line v1`, `intro v4`,
  `repeat v3`, `assemble v2` … — разнесение A_i / B_{i−1} / C_{i−2} видно построчно; проверок: `assemble` 6
  (многословные термины), `in_line` 1, `choose` 1.
- **Фразы** (7 каркасов): `intro p1`, `intro p2`, `choose_back p1`, `intro p3`, `assemble p2`, `other_slot p1`,
  `intro p4`, `slot p3`, `own_slot p2`, `intro p5`, `slot_listen p4`, `other_slot p3`, `intro p6`, `choose_back p5`,
  `own_slot p4`, `intro p7`, `assemble p6`, `other_slot p5`, `slot p7`, `own_slot p6`, `other_slot p7`,
  **`combine p1`** — одна на день, в конце этапа.
- **Диалог**: `partner x1`, `answer x1`, `partner x2`, `answer x2`, `partner x3`, `answer x3`, `partner x4`,
  `answer x4`, `partner x5`, **`rescue x6`**, `answer x5`, `ask x7`, `partner x7`, `ask x8`, `partner x8`. Спасение
  стоит внутри своего `answer` (D-17), у `ask` ученик говорит первым.
- **Слушаю и отвечаю**: `dialogue` → `question L1…L4` → `review` → `predict` ×2 (по ask-обменам) → `pace` → `number`.
- **Говорю сам**: `answer x1…x5`, `answer x7` (шесть, по порядку визита), `echo x5`, `retell x8`.

**Фикстурный день** (`FakePlanModel`, чистый урок «Приём у врача», оба уровня) — **75 карточек**, `minutes_estimate`
**25**, `metrics.cards_total = 75`:

| этап | intermediate | beginner |
|---|---|---|
| Слова 24 | `intro` 8, `repeat` 8, `assemble` 5, `choose` 1, `listen` 1, `in_line` 1 | то же |
| Фразы 19 | `intro` 6, `assemble` 2, `choose_back` 2, `slot` 1, `slot_listen` 1, `repeat` 1, `other_slot` 3, `own_slot` 2, `combine` 1 | `intro` 6, `assemble` 2, `choose_back` 2, `slot` 1, `slot_listen` 1, **`repeat` 6**, `combine` 1 |
| Диалог 15 | `partner` 7, `answer` 5, `ask` 2, `rescue` 1 | то же |
| Слушание 9 | `dialogue` 1, `question` 3, `review` 1, `predict` 2, `pace` 1, `number` 1 | то же |
| Речь 8 | `answer` 6, `echo` 1, `retell` 1 | то же |

Уровень меняет **произнесение фраз** (beginner — `phrase_repeat` на каждый каркас; intermediate — круг
`other_slot` → `own_slot`) и **направление** `word_choose` (`term_to_native` против `native_to_term`); состав дня и
число карточек — те же. Лента фикстуры звучит фейковым синтезатором: `total_ms` 40 810 мс.

**Почему 79, а не 75.** День e2e собран на уроке **v4.4**, фикстура — на чистом уроке `FakePlanModel`: у v4.4-урока
**7 каркасов** против 6 (+3 карточки фраз) и **4 вопроса** `listening` против 3 (+1 карточка слушания). Отбор,
разнесение и ротации в обоих случаях одни и те же.

## §4. Расхождения — что где написано и что взяли

Расхождения документов (не кода):

- **`docs/day-trainers-architecture.md` не существует** — наряд велел прочитать его первым. Его нет и не будет
  (решение архитектора): наряд самодостаточен, архитектура живёт в канве, `session-map.md` и `plan-v2.md` §6.
- **`session-map.md` лежит в `backend2/docs/`**, не в `docs/design/` — там же, где канон; ссылка на канву внутри карты
  написана как `docs/design/design/…` (двойное `design`), файл открывается по `docs/design/session-canvas.dc.html`.
- **Кадра 35-1 в канве нет.** Кадров 47: 30-1…30-9 + 30-2b, 31-1…31-7, 32-1…32-9, 33-1…33-8, 34-1…34-8, 35-2…35-6.
  В `session-map.md` 35-1 упомянут — карта опережает канву, кадра-источника у него нет.
- **Наряд называет ручку ответа `…/result`** — такой ручки нет и не было: это существующий
  `POST /plans/{id}/days/{n}/cards/{cardId}/answer`, адрес не менялся, изменился только ответ (D-02).

Решения наряда `D-01…D-31` — полный список, как они разошлись и что взято:

| # | кадр / правило | канва | наряд | что взяли | почему |
|---|---|---|---|---|---|
| D-01 | реестр видов | кадр 34-4 `listen_pairs` есть | «28 значений», а в списке их 29 | enum **29**, раздаются **28** | в уроке нет двух похожих реплик; источник — v4.6, клиент держит ветку «неизвестный kind — пропустить» |
| D-02 | ручка ответа | — | `POST …/result` | существующий `…/answer`, ответ аддитивно `{card, requeued, unit, day, stage}` | ручка та же, адрес не менялся |
| D-03 | конверт карточки | — | новый конверт | новые ключи `unit {kind, ref}`, `source_day` (номер), `response` **рядом** со старыми `unit_kind`, `unit_ref`, `source_day_id`, `answered_at`, `returns` | аддитивность дешевле, чем два формата на проводе |
| D-04 | комната дня | — | «состав целиком отдаётся клиенту» | `GET …/days/{n}` → `stages[].cards[]` (пусто у нерозданного дня); `…/open` и `…/cards` по-прежнему отдают плоский `cards[]` | у контура нет id, которыми отвечают |
| D-05 | единица карточки | 34-1…34-7 — «весь визит», не единица | «unit = day, ref day» | `word`/`vN`, `phrase`/`pN` (у `phrase_combine` — верный каркас), `exchange`/`xN`, `day`/`day`, у `listen_question` — `day`/`L{n}` | единица `day` никогда не возвращается: у следующего дня другой диалог |
| D-06 | повтор после промаха | 30-9 | «копия в конец этапа» | копия один раз, `options`/`tiles` перемешаны заново сидом `<id>:retry` | по id вариантов нельзя запомнить верный. **Замечание канве**: в этапе слушания копия может встать ПОСЛЕ `listen_review`, который уже показал разбор |
| D-07 | `word_choose` | 31-3 `term_to_native`, 31-4 `native_to_term` | `definition_to_term` | канву: beginner — `term_to_native` (фото, звук у вопроса), intermediate — `native_to_term` (звук у вариантов) | правда по кадрам — канва |
| D-08 | `word_listen` | 31-5 «звук → написание» | «4 перевода на родном» | канву: варианты — 4 слова **цели**, у вопроса только звук | то же |
| D-09 | `word_assemble` | 31-6 | «слова термина + одно лишнее» | слова термина без артиклей + до **3** лишних слов других терминов дня | одного лишнего мало, чтобы сборка была выбором |
| D-10 | `word_in_line` | 31-7 (дистракторы «sit/walk») | «4 термина дня» | верный — поверхностная форма из самой строки, ложные — другие термины дня; плюс `text_native_gapped` | у слов канвы нет источника в уроке |
| D-11 | `phrase_choose_back` | 32-3: вопрос на цели, варианты на родном | вопрос на родном, варианты на цели | канву | правда по кадрам — канва |
| D-12 | верное наполнение узнавания | 32-3/32-4 рисуют другое наполнение | «сказанное» | наряд: **сказанное** (`said`) | кадр — иллюстрация, а не правило |
| D-13 | `phrase_slot_listen` | 32-5 | «наполнения этого каркаса» | свои наполнения + наполнения другого каркаса до четырёх | у каркаса с двумя наполнениями иначе два варианта |
| D-14 | `phrase_other_slot` | 32-7 «каркас и окно отдельно» | покрытие каркаса | добавлен `slot_expected`: клиент шлёт голос, когда каркас покрыт **и** все слова окна услышаны | канва |
| D-15 | `phrase_combine` | 32-8 | «каркас + 2 других answer-каркаса» | три каркаса **с окном** (answer, потом ask, seeded); `chips` — полные наполнения; зачёт: каркас строго `correct_frame`, наполнение — любое | у каркаса без окна нечего класть в чип |
| D-16 | `phrase_own_slot` | 32-9 (чипы рядом с микрофоном) | голос и `examples` | добавлены `chips`; `hinted` этим видом игнорируется; знакомое наполнение — зачёт кодом | каркас на экране всегда, подсказки нет |
| D-17 | порядок спасения | 33-6: «не понял» в пузыре врача, микрофона нет | «rescue → одна карточка» | `partner(k)`, `rescue(k+1)`, `answer(k)`; `dialogue_rescue` — **прохождение** (`passed`/`skipped`), `expected_text`/`coverage_min` лежат на будущее | канва: микрофона на кадре нет |
| D-18 | 4-й вариант `dialogue_partner` | 33-1 (три варианта) | «верный вариант самого дальнего обмена» | верный вариант check самого дальнего по `|Δstep|` обмена, совпадающие с тремя пропускаются; добавлен `question_native` | проверка урока даёт три варианта, четвёртый строит **сервер** |
| D-19 | `listen_predict` | 34-5 | «3 перевода реплик A» | канву: варианты = свои варианты check ask-обмена | **дубль**: те же варианты покажет `dialogue_partner` того же обмена — вопрос архитектору, в ROADMAP |
| D-20 | `listen_review` | 34-3 (подсветка ответа в реплике) | `answers [{question_ref, exchange_step}]` | добавлены `line_ref` и `span` | иначе подсветку нечем нарисовать |
| D-21 | `listen_number` | 34-7 | `line`, `options` | добавлен `span`; текста вопроса нет | в уроке нет текста вопроса — рисует клиент |
| D-22 | `speak_answer` на `ask` | 35-2 | «обмены answer/ask» | `partner_line` = реплика A **предыдущего** обмена (у первого `null`), её же видит судья; плюс `own_line` | у ask-обмена своя реплика A — ответ, а не вопрос |
| D-23 | состав «Говорю сам» | «шесть реплик» | ≤ 6 `answer` + `echo` + `retell` | наряд (итого ≤ 8) | канва считает только `speak_answer` |
| D-24 | `plan_line_audios.duration_ms` | — | «миграция + бэкфилл декодером» | колонка **уже была** с первой миграции Plan и пишется оценкой по байтам; декодера в контейнерах нет | новых пакетов не ставили; миграция наряда одна |
| D-25 | CHECK-и `day_cards` | — | «две миграции» | одна миграция, и она же меняет `kind` (29) и `unit_kind` (+`day`) | без этого не вставить ни одной новой карточки |
| D-26 | покрытие речи | — | «SpokenCoverage, как в говорении» | `Plan/Domain/Service/SpeechCoverage` на `LexicalNormalizer`, артикли — из пакета языка цели | Plan не вправе импортировать Learning (deptrac) |
| D-27 | судья в запросе | — | «синхронный ≤ 8 с» | приняли как **исключение** из DECISIONS п. 50, вызов вне транзакции и без блокировки строки | вердикт нужен ученику стоя на карточке (п. 326) |
| D-28 | ретраи вендора | — | «один вызов, без ретрая» | у `ContentModelCatalog::get` появился сквозной `?int $retries` | по умолчанию ретраев 4, и таймаут ретраится — «8 с» превратились бы в 32 |
| D-29 | живой прогон | — | «урок «врач» v4.5» | урок **v4.4** плана «врач» на e2e | сцен v4.5 с записанным днём на e2e нет (GEN-2b не писал дни в сцены); структура та же |
| D-30 | `response` | — | «услышанное, значение окна, вердикт, hinted» | принимаются `heard` ≤ 1000, `hinted_at` ≤ 40, `slot_value` ≤ 200, `filler_index` 0…9, `mode`, `no_mic`; лишнее отбрасывается | хранить произвольный JSON клиента нельзя |
| D-31 | что клиент вправе записать | — | «result ∈ passed/hinted/failed/skipped» | по виду (`CardKind::allows`): выбор — все четыре; голос — без `failed`; судья — только `skipped`; прохождение — `passed`/`skipped`; чужой итог — 422 | молчание распознавателя не доказывает провала; зачёт судейских видов пишет сервер |

**Чего нет в уроке — и клиент это не получит** (`docs/plan-api.md`, «Чего сервер не даёт»):

| кадр | чего нет | что есть вместо |
|---|---|---|
| 31-1 | толкования слова на **родном** | `term.definition_target` (на языке цели) и `text_native` |
| 33-5 | текста задания «Спроси про работу» | `own_line.text_native` — задание клиент формулирует сам |
| 34-7 | текста вопроса («что за число?») | `line`, `span`, варианты — вопрос рисует клиент |
| 30-1 / 30-2b / 33-5 | **склоняемых форм роли собеседника** («с врачом», «спроси у врача») | `scene.partner_role_native` / `partner_role_target` — только именительный |
| 30-2b и шапка сессии | **портрета собеседника** | фото сцены (`scene.image`); кружок роли клиенту рисовать нечем |

Две последние строки — открытый вопрос: кто отдаёт склоняемые формы и портрет (сервер, модель урока или клиент),
решения нет. В ROADMAP, хвосты SESSION-1a.

## §5. Судья окна

**Порядок для `speak_answer` и `phrase_own_slot`** (`Application/Service/SlotJudge`, вне транзакции):

1. **код**: не покрыты слова каркаса вне окна (`SpeechCoverage` по `coverage_min` карточки) → `accepted: false`,
   `slot_value: null`, «Каркас не прозвучал — скажи его целиком». Модель не зовётся, кап не тратится;
2. **код**: наполнение, которое урок знает, услышано подряд (`containsSequence`) → зачёт, `by = code`,
   `slot_value` = это наполнение. Сюда же попадает каркас без окна;
3. **кап**: `SlotJudgeQuota::take` (Redis, ключ `plan:slot_judge:{user}:{дата зоны}`, TTL до местной полуночи, 60 в
   сутки) не дал — `unavailable`;
4. **модель**: `PLAN_JUDGE_MODEL`, TASK=`answer`, строгая схема `{accepted, slot_value, reason_native}`, **один**
   HTTP-вызов, таймаут 8 с. Любое исключение или ответ не по форме — `unavailable`;
5. **вердикт модели** → `accepted`, `slot_value`, `reason_native`; `by = model`.

`speak_retell` — всегда модель (TASK=`retell`, `PATTERN`/`SLOT_HINT`/`EXAMPLE_VALUES` пустые, `slot_value` всегда
`null`). `unavailable` на любом виде — **зачёт по коду**: `slot_value` = услышанные слова вне каркаса, `reason_native`
`null`, `+1` `judge.unavailable` в `plan_check_counters` под `slot_judge.v1` (action `counted`).

Промт — `app/Modules/Plan/Infrastructure/Prompt/slot_judge.v1.md`, принят из наряда байт-в-байт, sha256
`b8af3273…3ed6f74`; строка реестра — `docs/prompts/REGISTRY.md`, **SLOT JUDGE v1**.

**Смоук** (e2e, `tools/judge-smoke.php` + `judge-scenarios.json` → `judge-smoke.jsonl`; 11 попыток, **7** живых вызовов
`gpt-5.4-mini-2026-03-17`):

| сценарий | вид · вход (`heard`) | вердикт | `by` | `result` | модель / полная, мс | $ | HTTP |
|---|---|---|---|---|---|---|---|
| `answer-noframe` | `speak_answer` x1 · «he is not feeling well» | ✗ «Каркас не прозвучал — скажи его целиком» | code | `null` | — / 36 | — | 0 |
| `answer-filler` | `speak_answer` x2 · «He has had it since yesterday» | ✓ slot «since yesterday» | code | `passed` | — / 154 | — | 0 |
| `answer-meaning` | `speak_answer` x1 · «My son has a stomach ache» | ✓ slot «a stomach ache» | model | `passed` | 2 900 / 3 200 | 0.000516 | 1 |
| `answer-kind` | `speak_answer` x1 · «My son has yesterday» | ✗ «Ты назвал не симптом.», slot «yesterday» | model | `null` | 1 040 / 1 054 | 0.000533 | 1 |
| `answer-hinted` | `speak_answer` x5 · «He should rest in bed», `hinted: true` | ✓ slot «in bed» | model | **`hinted`** | 1 089 / 1 166 | 0.000539 | 1 |
| `own-meaning` | `phrase_own_slot` p4 · «He also has a runny nose» | ✓ slot «a runny nose» | model | `passed` | 3 584 / 3 673 | 0.000523 | 1 |
| `own-kind` | `phrase_own_slot` p2 · «He has had it a headache» | ✗ «Ты сказал не про время, а про вещь.» | model | `null` | 1 081 / 1 185 | 0.000559 | 1 |
| `retell-right` | `speak_retell` x8 · «Приходите раньше, если ему тяжело дышать…» | ✓ | model | `passed` | 1 063 / 1 241 | 0.000523 | 1 |
| `retell-opposite` | `speak_retell` x8 · «Приходить не нужно, даже если…» | ✗ «Ты сказал противоположное: …» | model | `null` | 1 150 / 1 164 | 0.000610 | 1 |
| `no-key` (ключ модели снят) | `speak_answer` x3 · «His temperature is thirty eight» | ✓ slot «thirty eight» | **unavailable** | `passed` | — / 110 | — | 0 |
| `over-cap` (кап = 1) | `speak_answer` x4 · «He also has a runny nose» | ✓ slot «a runny nose» | **unavailable** | `passed` | — / 957 | — | 0 |
| **итого** | 11 попыток | 7 ✓ модели и кода, 4 ✗ | | | модель 1.0–3.6 с | **0.003803** | **7** |

**Счётчики.** `judge.unavailable` вырос на **2** (по одному на `no-key` и `over-cap`), оба — `slot_judge.v1` / `counted`.
Пути 1 и 2 (отказ по каркасу, зачёт по знакомому наполнению) счётчик не трогают и кап не тратят. Вход модели — 548–586
токенов, выход 22–43; при капе 60 верхняя граница дня ученика ≈ $0.033.

**Два пути деградации проверены живьём**: (а) ключа модели нет → исключение адаптера → зачёт по коду, `+1`, 0 HTTP;
(б) кап исчерпан (на время теста 1) → зачёт по коду, `+1`, 0 HTTP. В обоих случаях ученик получает `passed`, а не
отказ: за молчание вендора он не платит.

## §6. Контракт

Изменилось на проводе (всё аддитивно, старые ключи стоят рядом):

| место | что стало |
|---|---|
| конверт карточки | `{id, stage, position, kind, unit {kind, ref}, source, source_day, retry_of, payload, result, attempts, response}` + прежние `unit_kind`, `unit_ref`, `source_day_id`, `answered_at`, `returns`. `source_day` — **номер** дня |
| `GET …/days/{n}` | у каждого `stages[]` — `cards[]` в порядке `position`; у нерозданного дня список пуст |
| `POST …/cards/{id}/answer` | тело + необязательный `response`; ответ `{card, requeued, unit {kind, ref, returns_tomorrow, returns_day}, day {cards_total, cards_done, minutes_spent}, stage {stage, minutes_spent}}` |
| `POST …/cards/{id}/judge` | **новый**: `{heard, hinted}` → `{accepted, slot_value, reason_native, result, attempts, card}`; 422 `plan_card_not_judged`, 409 `plan_card_answered` / `plan_day_not_open` |
| `response` карточки | ключи клиента (`heard`, `hinted_at`, `slot_value`, `filler_index`, `mode`, `no_mic`) + `hinted` и `judge {accepted, reason_native, by: code\|model\|unavailable, model, prompt_version, cost_usd, latency_ms, tokens_in, tokens_out}` от судьи |
| 422 | `plan_card_result_not_allowed` (`meta {kind, result}`), `plan_card_not_judged` (`meta {kind}`) — отказ до записи |

**Фикстуры — вход клиента.** `docs/fixtures/day-doctor.json` (intermediate, 344 748 байт, 7 092 строки) и
`day-doctor-beginner.json` (beginner, 338 198 байт, 6 978 строк): тело `data` ответа `GET /api/v1/plans/{id}/days/1` как
есть (`plan_id`, `day`, `scene`, `stages[]` с карточками, `metrics`, `program`, `window`). Id и адреса подставные и
детерминированные (`ulid-0001…`, `http://localhost/api/v1/plans/audio/…`), сцена прибита к фиксированному id
`01J8SESS1XTVRESCENE000000N` (иначе сиды случайны), тест `SessionDayFixtureTest` держит файлы байт-в-байт. В двух
фикстурах встречаются **все 28** раздаваемых видов.

**OpenAPI** (`openapi/openapi.yaml`, 4 047 → 5 129 строк, `OpenApiLintTest` зелёный):

- `PlanCard.payload` — `oneOf` из **28** схем `PlanCardPayload*`, по одной на раздаваемый вид, у каждой `title` =
  значение `kind`; общие объекты вынесены (`PlanCardAudio`, `PlanCardImage`, `PlanCardTerm`, `PlanCardFrame`,
  `PlanCardFiller`, `PlanCardSaid`, `PlanCardExchange`, `PlanCardLine`, `PlanCardOwnLine`, `PlanCardVisitLine`,
  `PlanCardOption`);
- чтобы `oneOf` разрешался ровно в одну ветку, у всех 28 стоит **`additionalProperties: false`**, а неразличимые по
  ключам `dialogue_answer` / `dialogue_ask` разведены `const` на `exchange.kind`. **Цена**: любой будущий аддитивный
  ключ payload сделает карточку невалидной по спеке, пока схему не поправят (решение владельца — оставить `oneOf` или
  перейти на `allOf` + `if/then`);
- **`listen_pairs` есть в enum `kind`, но схемы payload у него нет** — он не раздаётся, и карточка такого вида спеку не
  прошла бы. Сознательно;
- все 150 карточек обеих фикстур проверены против `PlanCard` временным валидатором: 0 ошибок, у каждой карточки
  `oneOf` разрешается ровно в одну ветку, неописанных ключей и отсутствующих `required` нет.

## §7. Снос

Удалено, не переписано и не спрятано за флагом:

- **13 старых видов** (`down()` миграции их перечисляет): `word_intro`, `word_say`, `word_choose`, `word_cloze`,
  `phrase_intro`, `phrase_repeat`, `phrase_assemble`, `dialogue_read`, `listen_question`, `listen_assemble`,
  `answer_choose`, `answer_assemble`, `speak`. Семь имён исчезли совсем (`word_say`, `word_cloze`, `dialogue_read`,
  `listen_assemble`, `answer_choose`, `answer_assemble`, `speak`), шесть остались именем при другом смысле и другом
  payload;
- **`Domain/Assembly/CardPayloads.php`** — сборщик payload всех старых видов;
- **пять старых сборщиков этапов** `WordsStage`, `PhrasesStage`, `DialogueStage`, `ListenStage`, `SpeakStage` — имена
  переиспользованы, код написан с нуля (старый не переносился ни строкой);
- **`Application/Query/GetPlanTargetLang.php`** — читатель языка плана, которого больше никто не звал;
- `SceneMaterial::completeExchanges`, `PartnerLines::of`, мёртвый `DialogueCards::learnerLine` (не переносился);
- **`DayCard::isGraded()` и `CardKind::isGraded()`** — «зачитывается ли вид» теперь не один вопрос, а четыре семейства
  (`isChoice` / `isSpoken` / `isJudged` / `isWalkthrough`) и `allows()`;
- **`DayPace` по этапам** — секунды на карточку этапа сменились таблицей по ВИДУ (`config/plan.php` → `plan.pace`,
  28 значений);
- **тесты и фикстуры старой раздачи**: `tests/Unit/Plan/DayAssemblyTest.php` (валидные метрики перенесены в
  `SessionWindowTest`), тест карточек в `DayClientContractTest` и его фикстура
  `tests/Fixtures/plan-client/cards_window_passed.json` (в папке осталась только `room_window_in_progress.json`);
- **старый §6 `plan-v2.md`** (4 139 знаков) — переписан целиком под реестр (17 285 знаков); в `plan-api.md` снесены
  абзацы «Карточка (`PlanCard`): … `plan_term_id`, `exchange_step`, `partner`, `audio_id`» и «Клиент оценивает сам…
  `speaking_key` + `variants`»;
- **старые схемы `PlanCard`** в OpenAPI: enum из 13 значений, описание payload по старым видам, `speaking_key`,
  `translations_collapsed`, `plan_term_id`; поправлены две описательные строки, опиравшиеся на снесённое поведение
  (`PlanWindowUnitState`, `PlanProgramUnit`). Grep по старым именам в `openapi.yaml`, `plan-v2.md`, `plan-api.md`,
  `ROADMAP.md` пуст;
- **данные**: `day_cards` снесены на всех базах (миграция; `down()` тоже удаляет — карточки прошлой раздачи не
  возвращаются).

## §8. Тесты и мутации

Новые и переписанные файлы (в скобках — число `it()`):

| семья | файлы |
|---|---|
| `tests/Unit/Plan/Session/` | `HelpersTest` (8), `CardKindTest` (6), `DayCardSessionTest` (7), `SpeechCoverageTest` (5), `WordsStageTest` (11), `PhrasesStageTest` (16), `DialogueStageTest` (9), `ListenStageTest` (15), `SpeakStageTest` (9), `SessionDayAssemblyTest` (6), `SessionWindowTest` (6), `SlotJudgePromptTest` (4) — **102** |
| `tests/Feature/Plan/` — новые | `SessionAnswerTest` (8), `SlotJudgeTest` (16), `SessionDayFixtureTest` (1 на две фикстуры), `AudioDurationsCommandTest` (3) |
| `tests/Feature/Plan/` — правленые | `PlanApiTest` (10), `PlanDayWindowTest` (25), `DayClientContractTest` (2, карточный тест снят), `PlanContractTest`; `tests/Pest.php` — `planAnswer` под новый ответ, `planWalkDay` (судейские → `skipped`, голосовые → `passed`, идёт по `requeued`) |
| `tests/Unit/Plan/` | `DayWindowTest` (12) — под `DayPace` по видам |

Прогон по файлам на шаге интеграции (не ворота): `Unit/Plan` — **284** зелёных, `Feature/Plan` — **119** зелёных.
Полные ворота — §1.

**Мутации** (`tools/mutate.py` + `tools/mutations.json`, как в GEN-2b): **49 записей — по одной на правило канона
наряда §8 плюс правило, найденное `invariant-reviewer` (§13)**; финальный прогон (БД `wordtrainer_test_test_10`) —
**49 caught, 0 survived, 0 equivalent, 0 skipped, 0 baseline red**, все файлы восстановлены байт-в-байт. Полная таблица «правило → тест → дефект → что упало» —
`mutations.md`.

Покрытие: 1 разнесение; 2–3 ротации (зерно сцены, шаг по индексу единицы); 4–5 `word_assemble` только у многословных и
артикли из пакета; 6 `word_in_line` без строки → `word_choose`; 7 `Options::MIN`; 8 ложные варианты не совпадают;
9–10 каркас без окна; 11–13 `phrase_combine`; 14–16 диалог (ask, rescue, половинчатый обмен); 17–19 четвёртые варианты;
20 `listen_predict` только на ask; 21–23 `listen_number`; 24 порядок слушания; 25–27 речь (≤ 8, порядок, pace и echo не
делят реплику); 28–29 повторение ≤ 10 и репетиция ≤ 12; 30–31 возвраты; 32 requeue; 33 единица `day` не возвращается;
34–35 `CardKind::allows`; 36–41 судья (шесть правил); 42–44 окно и программа; 45 таблица темпа код ↔ конфиг; 46 метрики;
47 фикстуры байт-в-байт; 48 покрытие речи; 49 пустой пересказ отклоняется кодом.

**В первом прогоне выжили две мутации — обе НЕ эквивалентные, обе указывали не на тот тест-охранник:**

1. «ask — ученик говорит первым»: перестановка `DialogueCards::ask` и `::partner` переживала тест
   `DialogueStageTest` «deals an ask as the learner's own line first…» — тот достаёт карточки по виду и ref, а не по
   порядку, и перевёрнутый обмен для него неотличим. Порядок реально охраняет «deals the visit in its order …», запись
   перецелена, мутация убита. Тонкое место: имя теста обещает порядок, а проверяет содержимое;
2. «`listen_number` только там, где пакет цели видит число или время»: первая версия портила `NumberValues::isTime`, но
   у английского пакета `time_pattern` = `null` (`config/lesson/lang/en.php:64`) — для цели-английского мутация была
   мертва. Перецелено на `isNumber` (у `en` `number_pattern` непустой) — убита.

**Чего мутации не доказывают, и это надо назвать прямо:** правки ревью **ARCH-4** (мёртвый параметр) и **ARCH-9**
(неиспользуемые импорты) — снос мёртвого кода, у которого нет и не может быть теста с дефектом: испортить нечего.
То же у сноса `PartnerLines::of`, `SceneMaterial::completeExchanges` и `GetPlanTargetLang` — их защищает только
PHPStan и то, что после сноса всё зелёное.

## §9. База, EXPLAIN, звук

**Бэкапы** (`scripts/db-backup.sh` → `storage/db-backups/`, до миграции):
`wordtrainer-20260916-022829.sql.gz` — **19M**; `wordtrainer_e2e_test-20260916-022840.sql.gz` — **640K**.

**Проверка отката** — на одноразовой `wordtrainer_test`: `migrate:fresh` → `migrate:rollback --step=1` → `migrate`,
зелено. На `wordtrainer` и `wordtrainer_e2e_test` — только `migrate` вперёд.

**Что сделала миграция** (`2026_09_16_100000_deal_session_cards`):

| база | было | стало |
|---|---|---|
| `wordtrainer` | `day_cards` **156** (план Дена, день 1 `in_progress` — 81 карта, 3 отвечены; `qa-dayui3`, день 1 `closed` — 75/75) | **0** |
| `wordtrainer_e2e_test` | карточки старых видов | 0, дни раздаются заново при открытии |

Метрики `plan_days` не тронуты — сверено после миграции: `1: 75/75/9 closed`, `1: 81/3/1 in_progress`,
`2: 0/0/0 locked` (`cards_total`/`cards_done`/`minutes_spent`). Колонка `response` есть, CHECK `unit_kind` принимает
`day`, CHECK `kind` — 29 значений.

**EXPLAIN** (e2e, `tools/explain.php` → `explain.txt`; `day_cards` 20 179 строк после синтетической нагрузки
`plan:seed-load` — 50 планов, 20 100 карточек; `plan_check_counters` 46; `plan_line_audios` 122):

| путь чтения | план |
|---|---|
| карточки дня по этапу и позиции (`forDay`, 79 строк) | **Index Scan using `day_cards_position_uidx`** (`Index Cond: day_id = …`), дальше quicksort по `CASE stage` + `position`; 0.147 мс. С `enable_seqscan = off` — тот же план |
| upsert счётчика судьи (`recordCodes`, откачен) | **Conflict Arbiter Indexes: `plan_check_counters_uidx`**, 1 конфликтующая строка, 0.029 мс |
| голос сцены (`forScenes`, 122 строки) | **Seq Scan** (cost 19.83, 0.040 мс, отфильтровано 114); с `enable_seqscan = off` — **Index Scan using `plan_line_audios_uidx`**, 0.010 мс |

Третий путь читается последовательно **по выбору планировщика**, а не из-за отсутствия индекса: на 122 строках seq scan
дешевле, и как только таблица вырастет, планировщик возьмёт `plan_line_audios_uidx` — что и показывает прогон с
выключенным seq scan. Первый путь на 79 строках дня внутри таблицы в 20 тысяч идёт по индексу.

**Звук.** `plan:audio-durations`: `wordtrainer` — «было 0 / стало 0», `wordtrainer_e2e_test` — «было 0 / стало 0»;
`--dry` не пишет ничего. Пустых `duration_ms` нет ни одной (`wordtrainer` 0 из 64, e2e 0 из 122), потому что колонка
существует с первой миграции Plan и **заполняется при записи файла оценкой по байтам** (`SpeechCost::mp3DurationMs`,
CBR 128 кбит/с). Настоящего декодера в контейнерах нет — ни `ffprobe`, ни `getID3`; новых пакетов без согласия не
ставили. Команда идемпотентна и оставлена для строк, которые приедут без длительности (не-mp3, пропавший файл).

## §10. Стоимость наряда

| что | вызовов | $ (код) | $ (кэш вендора) |
|---|---|---|---|
| смоук судьи окна, `gpt-5.4-mini-2026-03-17` | 7 | **0.003803** | 0.003803 |
| **итого наряда** | **7** | **0.003803** | **0.003803** |

Кэшированных токенов вендор не вернул ни на одном вызове (`cached_tokens: 0` во всех семи строках), поэтому «по коду» и
«по кэшу» здесь совпадают. Ни голоса, ни фото, ни уроков, ни планов не покупали; всё остальное — `FakePlanModel`,
$0. Кап наряда $0.50 — израсходовано 0.8 %.

## §11. Что не проверено и что замечено

**Не проверено:**

- **Телефон не запускался.** Клиент сессии — наряд SESSION-1b; сегодня приложение старую сессию дня не проходит и это
  принято (п. 325). Симулятор, `mobile/` и `flutter analyze` не трогались.
- **Закрытый день `qa-dayui3` (75/75) и день 1 плана Дена (81 карта, 3 отвечены) потеряли карточки** — их сносит
  миграция. Метрики целы, поэтому у закрытого дня остались числа без строк, а **день 1 Дена при открытии раздастся
  заново** (≈ 79 карточек на его уроке v4.4), и `cards_done` пересчитается из карточек при первом же ответе — три
  прежних ответа не сохранились. Живьём переоткрытие не проверяли.
- Хвосты «единица возвращается дважды» и «закрытый день без карточек» найдены чтением кода (`DayDealer::returnedUnits`,
  `OpenDayHandler`), живьём не воспроизводились.

**Замечено (в ROADMAP, «Хвосты SESSION-1a», в работу не бралось):**

- `coverage_min` уезжает на провод **целым** `1` (а не `1.0`) — клиент обязан читать поле как `num`, иначе разбор
  падает на первой голосовой карточке. Записано в контракт, код не менялся;
- **`listen_predict` повторяет варианты `dialogue_partner`** того же ask-обмена (D-19, канва 34-5): ученик видит один и
  тот же набор дважды за день. Вопрос архитектору;
- **единица возвращается дважды**, когда между днями-сценами стоит день повторения: возвраты берут одну предыдущую
  сцену, а повторение — две;
- **копия проваленной карточки слушания** встаёт в конец этапа и может оказаться **после** `listen_review`, который уже
  показал разбор (D-06);
- `docs/research/plan-gen/tools/live-run.php` говорит на старом реестре (шлёт `passed` на всё, называет `word_say`) —
  исторический харнесс, не трогали, сломается на следующем живом прогоне;
- **хук ворот не перехватывает `git -C … commit`.** Коммит карты тренажёров `e1dce275` (`docs/session-map.md`) прошёл
  мимо ворот именно так: вызван как `git -C … commit`, пока исполнители держали тестовые базы. Эквивалент
  `SKIP_GATES=1`, но без предупреждения — дыру закрыть отдельным микро-нарядом в хуке;
- **два правила покрытия речи в репозитории**: `Learning/Domain/Service/SpokenCoverage` и
  `Plan/Domain/Service/SpeechCoverage`. Плану нельзя импортировать Learning (deptrac, D-26), поэтому правило написано
  второй раз; свести в `Shared` — отдельный микро-наряд, Learning не трогать;
- `additionalProperties: false` на 28 схемах payload (§6) — аддитивный ключ сломает валидацию спеки до её правки;
- `phrase_assemble` кладёт в `expected.filler_index` индекс **сказанного** наполнения, и нигде не сказано, засчитывается
  ли другое (у `phrase_combine` проходит любое). Документ написан по коду — строго `said`; подтверждение владельца
  нужно;
- `dialogue_rescue` раздаётся прохождением (микрофона на кадре 33-6 нет), но несёт `expected_text` и `coverage_min` «на
  будущее»;
- DECISIONS п. 316 отменён **в части карточек** п. 325, но строки в раздел «Отменено» не добавлено (по конвенции файла
  частичные отмены туда попадали) — решение владельца.

## §12. Файлы

- `README.md` — этот отчёт; `mutations.md` — 49 правил канона, тест и дефект у каждого; `judge-smoke.jsonl` — 11 попыток
  смоука судьи построчно (вход, вердикт, `by`, токены, цена, латентность, HTTP, дельта счётчика); `explain.txt` — три
  плана чтения + индексы трёх таблиц; `e2e-day-doctor.json` — живой день 1 плана «врач» (e2e, урок v4.4) целиком.
- `tools/live-day.php` — раздача и выгрузка дня на e2e; `tools/judge-smoke.php` + `tools/judge-scenarios.json` — смоук
  судьи (включая пути «ключа нет» и «кап 1»); `tools/explain.php` — планы чтения; `tools/mutate.py` +
  `tools/mutations.json` — 49 мутаций.
- Вход клиента: `docs/fixtures/day-doctor.json`, `docs/fixtures/day-doctor-beginner.json`.
- Контракт и канон: `docs/plan-api.md` («Карточки сессии», «Судья окна», «Чего сервер не даёт», «Входные фикстуры
  клиента»), `openapi/openapi.yaml` (тег `Plans`), `docs/plan-v2.md` §0/§1/§2/§4/§6/§9/§10,
  `app/Modules/Plan/README.md`, `docs/prompts/REGISTRY.md` (SLOT JUDGE v1), `docs/DECISIONS.md` пп. 325–326,
  `docs/ROADMAP.md` (глава SESSION-1a + хвосты + SESSION-1b).
- Промт: `app/Modules/Plan/Infrastructure/Prompt/slot_judge.v1.md`. Миграция:
  `app/Modules/Plan/Infrastructure/Migration/2026_09_16_100000_deal_session_cards.php`. Бэкапы:
  `storage/db-backups/wordtrainer-20260916-022829.sql.gz`, `…/wordtrainer_e2e_test-20260916-022840.sql.gz`.

## §13. invariant-reviewer

**CLEAN — нарушений инвариантов нет.** Проверены 69 изменённых файлов, новые файлы `Plan` и три файла `Generation`;
`deptrac` ревьюер прогнал сам (0 нарушений, 0 пропусков). По пунктам: `Domain` чист от `Illuminate`/`Carbon`/Eloquent и
от `config()`/`now()` (таблица темпа приходит в `DayPace` конструктором из провайдера); прогресс, append-only журналы,
`client_seq`, «усвоено» и ключ ответа — вне диффа; кросс-модульная граница с `Generation` осталась на порту
`ContentModelCatalog` (новый `?int $retries` — на порту), в `Learning` модуль не ходит. Клиентский зачёт карточек плана
ревьюер не считает нарушением правила «оценивает только сервер»: это действующее решение п. 306, и серверная сторона
держит его — `CardKind::allows()` отбивает `passed` на судейском виде **422** до того, как карточка тронута, зачёт
судейских видов пишет только `…/judge`, который не читает `result`/`attempts` из запроса, вызов модели идёт вне
транзакции, а повтор отбивается проверкой `result !== null` под блокировкой строки.

**Вопрос ревьюера — принят и закрыт в этом же наряде.** У `speak_retell` не было кодового порога: каркаса у него нет,
а деградация (кап исчерпан, модель молчит или отвечает не по форме) засчитывает попытку. Значит пустое `heard` —
ученик промолчал — уходило в зачёт, хотя промт судьи сам называет пустой пересказ неверным. Добавлено правило: пустой
пересказ отклоняет **код** («Я ничего не услышал — перескажи своими словами»), модель не зовётся, кап не тратится;
у каркасных видов эту работу и раньше делал шаг 1 (покрытие каркаса). Тест
`SlotJudgeTest «refuses a retelling that was never said»`, мутация 49 — поймана; правило записано в `plan-v2.md` §6 и
`plan-api.md`.

## §14. Хвост

Та же сессия, 16.09, команда архитектора по итогам §11. Вызовов модели нет, голос, фото и уроки не покупались.

| # | правило | что сделано | тест |
|---|---|---|---|
| 1 | `listen_predict`: варианты — перевод ответа собеседника этого ask-обмена и переводы двух других его реплик, не совпадающие по тексту; `check` обмена не используется (D-19 отменён) | `ListenCards::predict` берёт ложные из `SceneMaterial::partnerLines()` (кроме своего обмена), перемешанных зерном `<адрес>:others`; верный — `text_native` реплики собеседника | `ListenStageTest` «…among the translations of two other partner lines» |
| 2 | возврат единицы — **ровно один раз, в ближайший следующий день любого типа**; день повторения берёт единицы двух предыдущих сцен, которые ещё никуда не возвращались; сцена после повторения их не берёт | `DayDealer::returnedUnits`: источники — вчерашний день (любого типа) и, у повторения, два предыдущих дня-сцены; уже вернувшееся отсеивается по карточкам `source = returned` с этим днём провала (`DayCardRepository::returnedFrom`, частичный индекс `day_cards_source_day_idx`). `DayCard::answer`: `returns` только у `source = today` — карточка-возврат единицу снова не помечает. Репетиция берёт вчерашние возвраты (`DayAssembler::rehearsalDay` + исключение возвращаемого обмена из её выбора), `ReturnDay` называет любой следующий день | `SessionReturnsTest` — цепочка сцена → сцена → повторение → сцена (каждая единица встречается один раз), возврат на репетицию; `DayCardSessionTest` «never marks a unit to return again…»; `SessionDayAssemblyTest` «…returns on a rehearsal…»; `SessionWindowTest` (день возврата любого типа) |
| 3 | этап «Слушаю и отвечаю» без копий: неверный `listen_question` → `failed` без requeue | `CardKind::requeues()` = выбор вне этапа слушания; `DayCard::answer` копирует только такие | `DayCardSessionTest` «deals no copy of a failed listening choice…», `SessionReturnsTest` «deals no copy of a wrong listening answer…», `SessionAnswerTest` (ответ `requeued: null`) |
| 4 | `coverage_min` на проводе — с дробной частью | `JSON_PRESERVE_ZERO_FRACTION` при записи карточки в `jsonb` (`EloquentDayCardRepository`) и в ответах, которые несут карточки (`PlanDayController::reply`, `PlanCardJudgeController`); нормализация фикстур — с тем же флагом. Попутно с дробной частью уезжают и доли окна (`share`, `day_progress`) | `SessionDayFixtureTest` — обе фикстуры пересобраны: `coverage_min` 1.0 / 0.7 |
| 5 | DECISIONS «Отменено» — частичная отмена п. 316 пунктом 325 | строка в разделе «Отменено»: снята часть «карточки», поля окна и аддитивность каркасов остаются | — |
| 6 | `plan-api.md`, `plan-v2.md` §6, OpenAPI — под 1–3; ROADMAP — хвосты D-19, двойной возврат и копия после разбора закрыты | см. файлы; остальные хвосты §11 остаются открытыми | — |

**Что изменилось на проводе.** `listen_predict.options` — другие тексты (переводы реплик собеседника вместо вариантов
проверки); `requeued` у этапа слушания всегда `null`; `unit.returns_day` может назвать репетицию; у карточки-возврата
`unit.returns_tomorrow` всегда `false`; числа-доли — с дробной частью. Фикстуры: `day-doctor.json` — 344 974 байта,
`day-doctor-beginner.json` — 338 422 байта.

**Замечено, не менялось.** Варианты `listen_predict` иногда выдаёт форма: на ask-обмене верный — утверждение
собеседника, а среди двух других его реплик бывают вопросы («У него есть температура?» рядом с «Нет, при растяжении
мышцы рентген не нужен.» в фикстуре). Правило архитектора не выбирает реплики по форме; если это мешает — предпочитать
утверждения отдельным решением. Мутант «вчерашний день-сцена вместо вчерашнего дня любого типа» сегодня эквивалентен:
день повторения не может сам пометить единицу (его карточки судейские, провал возврата единицу не помечает), поэтому
правило 2 проверено дефектом «репетиция не берёт вчерашние возвраты» (мутация 54). Живой день e2e (§3) после хвоста
**пере-роздан** (карточки дня 1 плана «врач» сняты на `wordtrainer_e2e_test` и розданы заново тем же `live-day.php`,
модель не зовётся): те же 79 карточек / 26 минут, `listen_predict` на x7/x8 — переводы реплик собеседника,
`coverage_min` 1.0 / 0.7; `e2e-day-doctor.json` переписан. Попытки смоука судья писал на прежние карточки — таблица §5
и `judge-smoke.jsonl` остаются как были.

**Мутации** — 57 записей (49 + восемь правил хвоста: 50–57; мутации 11 и 19 перецелены на изменившийся код), полный
прогон на `wordtrainer_test_test_10`: **57 caught, 0 survived, 0 skipped, 0 baseline red**, таблица `mutations.md`
пересобрана. **Ворота** (один раз, на коде хвоста) — deptrac **0 нарушений**, PHPStan **No errors**, Pest **2 229 зелёных**
(11 959 проверок, 31,7 с, 10 процессов). Коммиты хвоста: код `4e85619a`, документы — этот коммит документов.
