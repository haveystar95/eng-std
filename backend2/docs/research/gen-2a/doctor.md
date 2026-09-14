# GEN-2a · «Приём у врача» — doctor (средний)

Цель плана (слова ученика): «Иду к врачу с сыном: у него третий день температура и болит горло. Нужно рассказать симптомы и понять назначения»

Сцена: «Приём у врача» / «Doctor visit» · ученик: Родитель · собеседник: Врач (женщина)

Промт `lesson_day.v4.4` · модель `gpt-5.4-2026-03-05` · урок $0.078488 · 32.2 с · токены вход/выход 6639/4126 · одна попытка: да · находок валидатора: 13

> Колонка «оценка» пустая — ставит Ден: **✓** / **так не говорят** / **слишком длинно** / **не то слово**. Реплики ученика — как их получит приложение (сервер собирает их из каркаса и наполнения); если модель написала иначе, её текст — в скобках.

## Сценарий диалога

| # | вид обмена | кто | реплика | перевод | каркас · наполнение | оценка |
|---|---|---|---|---|---|---|
| 1 | ответ | Врач (собеседник) | What seems to be the problem today? | Что вас сегодня беспокоит? |  | |
| 1 | ответ | Родитель (ученик) | My son has a fever. | У моего сына температура. | p1 · a fever | |
| 2 | ответ | Врач (собеседник) | How long has he had it? | Как долго это у него? |  | |
| 2 | ответ | Родитель (ученик) | He has had it for three days. | Это у него уже три дня. | p2 · for three days | |
| 3 | ответ | Врач (собеседник) | What is his temperature? | Какая у него температура? |  | |
| 3 | ответ | Родитель (ученик) | His temperature is 39 degrees. | У него температура 39 градусов. | p3 · 39 degrees | |
| 4 | ответ | Врач (собеседник) | Does he have a sore throat? | У него болит горло? |  | |
| 4 | ответ | Родитель (ученик) | He also has a sore throat. | У него ещё болит горло. | p4 · a sore throat | |
| 5 | ответ | Врач (собеседник) | It looks like a throat infection. Give paracetamol, warm drinks, and rest at home. | Похоже на инфекцию горла. Давайте парацетамол, тёплое питьё и пусть отдыхает дома. |  | |
| 5 | ответ | Родитель (ученик) | He should rest at home. | Ему нужно отдыхать дома. | p5 · at home | |
| 6 | переспрос | Родитель (ученик) | Could you say that more slowly, please? | Скажите, пожалуйста, это помедленнее. | — | |
| 6 | переспрос | Врач (собеседник) | Give paracetamol, warm drinks, and rest at home. | Давайте парацетамол, тёплое питьё и пусть отдыхает дома. |  | |
| 7 | вопрос ученика | Родитель (ученик) | How often should I give the paracetamol? | Как часто мне давать парацетамол? | p6 · the paracetamol | |
| 7 | вопрос ученика | Врач (собеседник) | Give it every six hours if the fever stays high. | Давайте его каждые шесть часов, если температура остаётся высокой. |  | |
| 8 | вопрос ученика | Родитель (ученик) | When should we come back if he gets worse? | Когда нам нужно прийти снова, если ему станет хуже? | p7 · if he gets worse | |
| 8 | вопрос ученика | Врач (собеседник) | Come back sooner if he has trouble breathing or cannot drink. | Приходите раньше, если ему трудно дышать или он не может пить. |  | |

## Каркасы

### p1 · ответ — «My son has ___.»

По-русски: «У моего сына ___.» · чтение: «Май сан хэз ___.» · окно: «симптом» · звучит в обменах: 1

| наполнение | перевод | чтение | в диалоге | собранная фраза | собранный перевод | оценка |
|---|---|---|---|---|---|---|
| a fever | температура | э фи́вэр | да | My son has a fever. | У моего сына температура. | |
| a cough | кашель | э коф | — | My son has a cough. | У моего сына кашель. | |
| ear pain | боль в ухе | ир пэйн | — | My son has ear pain. | У моего сына боль в ухе. | |

### p2 · ответ — «He has had it ___.»

По-русски: «Это у него уже ___.» · чтение: «Хи хэз хэд ит ___.» · окно: «сколько времени» · звучит в обменах: 2

| наполнение | перевод | чтение | в диалоге | собранная фраза | собранный перевод | оценка |
|---|---|---|---|---|---|---|
| for three days | уже три дня | фор сри дэйз | да | He has had it for three days. | Это у него уже уже три дня. | |
| since yesterday | со вчера | синс естэрдэй | — | He has had it since yesterday. | Это у него уже со вчера. | |
| since this morning | с сегодняшнего утра | синс зис морнинг | — | He has had it since this morning. | Это у него уже с сегодняшнего утра. | |

### p3 · ответ — «His temperature is ___.»

По-русски: «У него температура ___.» · чтение: «Хиз тэмпрэчэр из ___.» · окно: «температура» · звучит в обменах: 3

| наполнение | перевод | чтение | в диалоге | собранная фраза | собранный перевод | оценка |
|---|---|---|---|---|---|---|
| 39 degrees | 39 градусов | сёрти-найн дэгриз | да | His temperature is 39 degrees. | У него температура 39 градусов. | |
| 38.5 | 38,5 | сёрти-эйт пойнт файв | — | His temperature is 38.5. | У него температура 38,5. | |
| 40 degrees | 40 градусов | форти дэгриз | — | His temperature is 40 degrees. | У него температура 40 градусов. | |

### p4 · ответ — «He also has ___.»

По-русски: «У него ещё ___.» · чтение: «Хи о́лсоу хэз ___.» · окно: «ещё один симптом» · звучит в обменах: 4

| наполнение | перевод | чтение | в диалоге | собранная фраза | собранный перевод | оценка |
|---|---|---|---|---|---|---|
| a sore throat | болит горло | э сор сроут | да | He also has a sore throat. | У него ещё болит горло. | |
| a headache | болит голова | э хэдайк | — | He also has a headache. | У него ещё болит голова. | |
| a rash | сыпь | э рэш | — | He also has a rash. | У него ещё сыпь. | |

### p5 · ответ — «He should rest ___.»

По-русски: «Ему нужно отдыхать ___.» · чтение: «Хи шуд рест ___.» · окно: «где» · звучит в обменах: 5

| наполнение | перевод | чтение | в диалоге | собранная фраза | собранный перевод | оценка |
|---|---|---|---|---|---|---|
| at home | дома | эт хоум | да | He should rest at home. | Ему нужно отдыхать дома. | |
| today | сегодня | тудэй | — | He should rest today. | Ему нужно отдыхать сегодня. | |
| for two days | два дня | фор ту дэйз | — | He should rest for two days. | Ему нужно отдыхать два дня. | |

### p6 · вопрос ученика — «How often should I give ___?»

По-русски: «Как часто мне давать ___?» · чтение: «Хау о́фэн шуд ай гив ___?» · окно: «лекарство» · звучит в обменах: 7

| наполнение | перевод | чтение | в диалоге | собранная фраза | собранный перевод | оценка |
|---|---|---|---|---|---|---|
| the paracetamol | парацетамол | зэ пэрэситэмол | да | How often should I give the paracetamol? | Как часто мне давать парацетамол? | |
| the syrup | сироп | зэ си́рэп | — | How often should I give the syrup? | Как часто мне давать сироп? | |
| the drops | капли | зэ дропс | — | How often should I give the drops? | Как часто мне давать капли? | |

### p7 · вопрос ученика — «When should we come back ___?»

По-русски: «Когда нам нужно прийти снова ___?» · чтение: «Уэн шуд ви кам бэк ___?» · окно: «при каком условии» · звучит в обменах: 8

| наполнение | перевод | чтение | в диалоге | собранная фраза | собранный перевод | оценка |
|---|---|---|---|---|---|---|
| if he gets worse | если ему станет хуже | иф хи гетс уорс | да | When should we come back if he gets worse? | Когда нам нужно прийти снова если ему станет хуже? | |
| if the fever returns | если температура вернётся | иф зэ фи́вэр ритёрнз | — | When should we come back if the fever returns? | Когда нам нужно прийти снова если температура вернётся? | |
| if he stops eating | если он перестанет есть | иф хи стопс и́тинг | — | When should we come back if he stops eating? | Когда нам нужно прийти снова если он перестанет есть? | |

## Проверки обменов

| # | вопрос | варианты (✓ — верный) | пояснение | оценка |
|---|---|---|---|---|
| 1 | What does the doctor ask about? / О чём спрашивает врач? | ✓ The child's main problem today / Какая у ребёнка главная проблема сегодня · The child's age / Сколько ребёнку лет · The medicine from last time / Какое лекарство было в прошлый раз | Врач просит кратко сказать, с чем вы пришли сегодня. | |
| 2 | What time period does the doctor want to know? / О каком времени спрашивает врач? | ✓ How many days it has lasted / Сколько дней это длится · What time he took medicine / Во сколько он принял лекарство · When it gets worse / Когда ему становится хуже | Врач спрашивает о длительности симптома. | |
| 3 | What exact detail does the doctor ask for? / Какую точную информацию спрашивает врач? | How often the fever returns / Как часто температура возвращается · ✓ The number on the thermometer / Какое число показывает градусник · Whether the child feels cold / Мёрзнет ли ребёнок | Врач хочет узнать конкретную температуру. | |
| 4 | What symptom does the doctor ask about? / О каком симптоме спрашивает врач? | Pain when swallowing / Боль при глотании · ✓ Pain in the throat / Боль в горле · Pain in the ears / Боль в ушах | Врач уточняет, болит ли у ребёнка горло. | |
| 5 | What place does the doctor mention for rest? / Где, по словам врача, ребёнку нужно отдыхать? | At school after lunch / В школе после обеда · ✓ At home, not outside / Дома, а не где-то ещё · In the hospital overnight / В больнице на ночь | Врач говорит, что ребёнку нужно отдыхать дома. | |
| 6 | Which medicine does the doctor repeat? / Какое лекарство врач повторяет? | Cough syrup / Сироп от кашля · Antibiotics / Антибиотики · ✓ Paracetamol / Парацетамол | Во второй раз врач снова называет парацетамол. | |
| 7 | When does the doctor say to give the medicine? / Когда врач говорит давать лекарство? | After every meal / После каждого приёма пищи · Once every morning / Один раз каждое утро · ✓ Every six hours, but only with high fever / Каждые шесть часов, но только если температура остаётся высокой | Врач говорит про интервал в шесть часов и условие — высокая температура. | |
| 8 | Which warning sign does the doctor mention? / Какой тревожный признак называет врач? | He asks for soft food / Он просит мягкую еду · He sleeps longer than usual / Он спит дольше обычного · ✓ He has difficulty breathing / Ему трудно дышать | Врач говорит вернуться раньше при затруднённом дыхании. | |

## Слушаю весь визит (listening)

| # | вопрос | варианты (✓ — верный) | пояснение | оценка |
|---|---|---|---|---|
| L1 | С какой основной жалобой родитель пришёл к врачу? | С сыпью на руках · С болью в животе · ✓ С температурой у сына | В начале родитель говорит, что у сына температура. | |
| L2 | Сколько дней у ребёнка держатся симптомы? | Неделю · Один день · ✓ Три дня | Родитель говорит, что это продолжается три дня. | |
| L3 | Что врач посоветовала давать ребёнку? | Витамины · Только антибиотик · ✓ Парацетамол | Врач рекомендует парацетамол. | |
| L4 | В каком случае врач сказала прийти раньше? | Если ребёнок хочет спать днём · ✓ Если ребёнок не может пить · Если ребёнок просит суп | Врач называет тревожные признаки: трудно дышать или ребёнок не может пить. | |

## Словарь

| id | слово | вид | перевод | чтение | определение | где звучит | картинка (запрос) | оценка |
|---|---|---|---|---|---|---|---|---|
| v1 | fever | слово | температура | фи́вэр | an abnormally high body temperature | p1, p7, A7 | digital thermometer showing high temperature on a bedside table | |
| v2 | sore throat | связка | боль в горле | сор сроут | pain or irritation in the throat | A4, p4 | child holding neck while sitting under a blanket | |
| v3 | throat infection | связка | инфекция горла | сроут инфэ́кшн | an infection that affects the throat | A5 | doctor examining a child's throat with a light | |
| v4 | paracetamol | слово | парацетамол | пэрэситэмол | a medicine used to reduce pain and fever | A5, A6, p6 | paracetamol tablets and a measuring spoon on a kitchen table | |
| v5 | warm drinks | связка | тёплое питьё | уорм дринкс | hot or warm liquids given for comfort or hydration | A5, A6 | mug of warm tea on a small table near a couch | |
| v6 | rest at home | связка | отдыхать дома | рест эт хоум | stay home and avoid usual activities to recover | A5, A6, p5 | child resting on a sofa at home with a blanket | |
| v7 | every six hours | связка | каждые шесть часов | э́ври сикс ауэрз | at six-hour intervals | A7 | — | |
| v8 | trouble breathing | связка | трудно дышать | трабл бриизинг | difficulty getting enough air | A8 | child sitting upright in bed while a parent watches closely | |

## Находки валидатора (режим наблюдения — день вышел)

| код | адрес | что |
|---|---|---|
| `key.contains_filler` | B1 | the key «son has a» takes words of the filler «a fever» |
| `key.no_content_word` | B2 | the key «has had it» has no content word |
| `key.no_content_word` | B4 | the key «also has a» has no content word |
| `key.contains_filler` | B4 | the key «also has a» takes words of the filler «a sore throat» |
| `key.too_long` | B7 | the key «How often should I give» has 5 words (1–4) |
| `key.too_long` | B8 | the key «When should we come back» has 5 words (1–4) |
| `check.verbatim` | x1.check | the right option «The child's main problem today» repeats «problem today» of the partner's line |
| `check.verbatim` | x5.check | the right option «At home, not outside» repeats «at home» of the partner's line |
| `check.verbatim` | x7.check | the right option «Every six hours, but only with high fever» repeats «every six» of the partner's line |
| `listening.distractor_not_filler` | L1 | the question asks p1's slot («температура»), but 1 of its wrong options are p1's other fillers (expected 2) |
| `listening.distractor_not_filler` | L2 | the question asks p2's slot («уже три дня»), but 0 of its wrong options are p2's other fillers (expected 2) |
| `listening.distractor_not_filler` | L3 | the question asks p6's slot («парацетамол»), but 0 of its wrong options are p6's other fillers (expected 2) |
| `vocab.free_combination` | v7 | «every six hours» is a free combination of ordinary words |
