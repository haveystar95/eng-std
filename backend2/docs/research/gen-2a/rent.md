# GEN-2a · «Просмотр жилья» — rent (средний)

Цель плана (слова ученика): «Снимаю квартиру в Берлине на год: смотрю квартиру, спрашиваю про депозит, коммунальные платежи и договор. Работаю удалённо, у меня кошка»

Сцена: «Просмотр жилья» / «Flat viewing» · ученик: Будущий арендатор · собеседник: Арендодатель (мужчина)

Промт `lesson_day.v4.4` · модель `gpt-5.4-2026-03-05` · урок $0.076523 · 29.1 с · токены вход/выход 6651/3993 · одна попытка: да · находок валидатора: 18

> Колонка «оценка» пустая — ставит Ден: **✓** / **так не говорят** / **слишком длинно** / **не то слово**. Реплики ученика — как их получит приложение (сервер собирает их из каркаса и наполнения); если модель написала иначе, её текст — в скобках.

## Сценарий диалога

| # | вид обмена | кто | реплика | перевод | каркас · наполнение | оценка |
|---|---|---|---|---|---|---|
| 1 | ответ | Арендодатель (собеседник) | The rent is 1,200 euros a month, including water and heating. | Аренда — 1200 евро в месяц, вода и отопление включены. |  | |
| 1 | ответ | Будущий арендатор (ученик) | What about electricity? | А электричество? | p1 · electricity | |
| 2 | ответ | Арендодатель (собеседник) | Electricity and internet are separate and paid by the tenant. | Электричество и интернет оплачиваются отдельно арендатором. |  | |
| 2 | ответ | Будущий арендатор (ученик) | How much is the deposit? | Какой депозит? | p2 · the deposit | |
| 3 | ответ | Арендодатель (собеседник) | The deposit is 2,400 euros, returned after the final inspection. | Депозит — 2400 евро, его возвращают после финального осмотра. |  | |
| 3 | ответ | Будущий арендатор (ученик) | I need a one year contract. (модель: «I need a one-year contract.») | Мне нужен договор на год. | p3 · one year | |
| 4 | ответ | Арендодатель (собеседник) | That works. The contract is fixed for twelve months. | Это подходит. Договор фиксированный на двенадцать месяцев. |  | |
| 4 | ответ | Будущий арендатор (ученик) | I work from home. | Я работаю из дома. | p4 · from home | |
| 5 | вопрос ученика | Будущий арендатор (ученик) | Can I work from home here? | Я могу работать здесь из дома? | p5 · here | |
| 5 | вопрос ученика | Арендодатель (собеседник) | Yes, that's fine, as long as it's quiet and no clients visit. | Да, это нормально, если тихо и к вам не приходят клиенты. |  | |
| 6 | вопрос ученика | Будущий арендатор (ученик) | Is a cat allowed? | Кошка разрешена? | p6 · a cat | |
| 6 | вопрос ученика | Арендодатель (собеседник) | Yes, one cat is allowed, but please note it in the contract. | Да, одна кошка разрешена, но, пожалуйста, укажите это в договоре. |  | |
| 7 | вопрос ученика | Будущий арендатор (ученик) | When can I move in? | Когда я могу въехать? | p7 · move in | |
| 7 | вопрос ученика | Арендодатель (собеседник) | You can move in on the first of next month. | Вы можете въехать первого числа следующего месяца. |  | |
| 8 | вопрос ученика | Будущий арендатор (ученик) | How much is the deposit? | Какой депозит? | p2 · the deposit | |
| 8 | вопрос ученика | Арендодатель (собеседник) | It's 2,400 euros, and I return it after inspection. | Это 2400 евро, и я возвращаю его после осмотра. |  | |

## Каркасы

### p1 · ответ — «What about ___?»

По-русски: «А как насчёт ___?» · чтение: «уот эбаут ___» · окно: «о каком расходе или пункте вы спрашиваете» · звучит в обменах: 1

| наполнение | перевод | чтение | в диалоге | собранная фраза | собранный перевод | оценка |
|---|---|---|---|---|---|---|
| electricity | электричество | илэктрисити | да | What about electricity? | А как насчёт электричество? | |
| internet | интернет | интэрнэт | — | What about internet? | А как насчёт интернет? | |
| parking | парковка | паркинг | — | What about parking? | А как насчёт парковка? | |

### p2 · вопрос ученика — «How much is ___?»

По-русски: «Сколько составляет ___?» · чтение: «хау мач из ___» · окно: «что именно вы хотите узнать по сумме» · звучит в обменах: 2, 8

| наполнение | перевод | чтение | в диалоге | собранная фраза | собранный перевод | оценка |
|---|---|---|---|---|---|---|
| the deposit | депозит | зэ дипозит | да | How much is the deposit? | Сколько составляет депозит? | |
| the rent | аренда | зэ рент | — | How much is the rent? | Сколько составляет аренда? | |
| the utility bill | коммунальный счёт | зэ ютилити бил | — | How much is the utility bill? | Сколько составляет коммунальный счёт? | |

### p3 · ответ — «I need a ___ contract.»

По-русски: «Мне нужен договор на ___.» · чтение: «ай нид э ___ контракт» · окно: «на какой срок нужен договор» · звучит в обменах: 3

| наполнение | перевод | чтение | в диалоге | собранная фраза | собранный перевод | оценка |
|---|---|---|---|---|---|---|
| one-year | год | уан-йир | да | I need a one-year contract. | Мне нужен договор на год. | |
| six-month | шесть месяцев | сикс-манс | — | I need a six-month contract. | Мне нужен договор на шесть месяцев. | |
| two-year | два года | ту-йир | — | I need a two-year contract. | Мне нужен договор на два года. | |

### p4 · ответ — «I work ___ .»

По-русски: «Я работаю ___ .» · чтение: «ай уорк ___» · окно: «откуда или как вы работаете» · звучит в обменах: 4

| наполнение | перевод | чтение | в диалоге | собранная фраза | собранный перевод | оценка |
|---|---|---|---|---|---|---|
| from home | из дома | фром хоум | да | I work from home. | Я работаю из дома. | |
| remotely | удалённо | римоутли | — | I work remotely. | Я работаю удалённо. | |
| full-time | полный день | фул-тайм | — | I work full-time. | Я работаю полный день. | |

### p5 · вопрос ученика — «Can I work from home ___?»

По-русски: «Я могу работать из дома ___?» · чтение: «кэн ай уорк фром хоум ___» · окно: «где именно» · звучит в обменах: 5

| наполнение | перевод | чтение | в диалоге | собранная фраза | собранный перевод | оценка |
|---|---|---|---|---|---|---|
| here | здесь | хиэр | да | Can I work from home here? | Я могу работать из дома здесь? | |
| in this flat | в этой квартире | ин зис флэт | — | Can I work from home in this flat? | Я могу работать из дома в этой квартире? | |

### p6 · вопрос ученика — «Is ___ allowed?»

По-русски: «___ разрешён?» · чтение: «из ___ элауд» · окно: «что вы хотите уточнить» · звучит в обменах: 6

| наполнение | перевод | чтение | в диалоге | собранная фраза | собранный перевод | оценка |
|---|---|---|---|---|---|---|
| a cat | кошка | э кэт | да | Is a cat allowed? | кошка разрешён? | |
| a dog | собака | э дог | — | Is a dog allowed? | собака разрешён? | |
| subletting | субаренда | саблэтинг | — | Is subletting allowed? | субаренда разрешён? | |

### p7 · вопрос ученика — «When can I ___?»

По-русски: «Когда я могу ___?» · чтение: «уэн кэн ай ___» · окно: «какое действие вы хотите сделать» · звучит в обменах: 7

| наполнение | перевод | чтение | в диалоге | собранная фраза | собранный перевод | оценка |
|---|---|---|---|---|---|---|
| move in | въехать | мув ин | да | When can I move in? | Когда я могу въехать? | |
| sign the contract | подписать договор | сайн зэ контракт | — | When can I sign the contract? | Когда я могу подписать договор? | |
| see the cellar | посмотреть подвал | си зэ сэлэр | — | When can I see the cellar? | Когда я могу посмотреть подвал? | |

## Проверки обменов

| # | вопрос | варианты (✓ — верный) | пояснение | оценка |
|---|---|---|---|---|
| 1 | Which costs are already part of the monthly rent? / Какие расходы уже входят в ежемесячную аренду? | Internet and parking / Интернет и парковка · ✓ Water and heating / Вода и отопление · Electricity and gas / Электричество и газ | Арендодатель сказал, что в сумму входят вода и отопление. | |
| 2 | Which two things does the tenant pay separately? / Какие две вещи арендатор оплачивает отдельно? | Water and heating / Вода и отопление · ✓ Electricity and internet / Электричество и интернет · Furniture and cleaning / Мебель и уборка | Арендодатель уточнил, что отдельно платят за электричество и интернет. | |
| 3 | When does the landlord say the deposit comes back? / Когда, по словам арендодателя, возвращают депозит? | ✓ After the last apartment check / После итоговой проверки квартиры · With the first rent payment / Вместе с первым платежом за аренду · Before move-in day / До дня въезда | Арендодатель сказал, что депозит возвращают после финального осмотра. | |
| 4 | How long is the rental contract? / На какой срок договор аренды? | For six months / На полгода · Month to month / Помесячно · ✓ For one full year / На один полный год | Арендодатель сказал, что договор заключают на двенадцать месяцев. | |
| 5 | What condition does the landlord give for home office use? / Какое условие арендодатель называет для работы из дома? | ✓ No noise and no client visits / Без шума и без визитов клиентов · Use only the kitchen table / Работать только за кухонным столом · Only on weekends / Только по выходным | Арендодатель разрешает работать из дома, если это тихо и без клиентов. | |
| 6 | What does the landlord want about the cat? / Чего арендодатель хочет по поводу кошки? | The cat kept on the balcony / Чтобы кошка жила на балконе · ✓ The cat mentioned in the agreement / Чтобы кошка была указана в договоре · A pet deposit only / Только отдельный депозит за питомца | Арендодатель просит указать кошку в договоре. | |
| 7 | When is the apartment available to move into? / Когда можно въехать в квартиру? | At the end of this week / В конце этой недели · In two months / Через два месяца · ✓ On the first day of next month / Первого числа следующего месяца | Арендодатель сказал, что въехать можно первого числа следующего месяца. | |
| 8 | What amount does the landlord repeat for the deposit? / Какую сумму депозита арендодатель повторяет? | 800 euros / 800 евро · 1,200 euros / 1200 евро · ✓ 2,400 euros / 2400 евро | Арендодатель повторяет, что депозит составляет 2400 евро. | |

## Слушаю весь визит (listening)

| # | вопрос | варианты (✓ — верный) | пояснение | оценка |
|---|---|---|---|---|
| L1 | Что входит в ежемесячную аренду? | ✓ Вода и отопление · Интернет и электричество · Только отопление | Арендодатель сказал, что вода и отопление включены. | |
| L2 | На какой срок нужен договор арендатору? | На шесть месяцев · ✓ На год · На два года | Арендатор говорит, что ему нужен договор на год. | |
| L3 | Что арендодатель сказал про удалённую работу? | Она запрещена · Она разрешена только по вечерам · ✓ Она разрешена, если тихо и без клиентов | Работать из дома можно, если это тихо и без визитов клиентов. | |
| L4 | Что нужно сделать по поводу кошки? | Платить отдельную аренду за неё · ✓ Указать её в договоре · Спросить соседей | Арендодатель просит указать кошку в договоре. | |

## Словарь

| id | слово | вид | перевод | чтение | определение | где звучит | картинка (запрос) | оценка |
|---|---|---|---|---|---|---|---|---|
| v1 | deposit | слово | депозит | дипозит | money paid before renting and returned later if there is no damage | p2, A3, A8 | rental contract with a deposit amount highlighted on a table | |
| v2 | utilities | слово | коммунальные услуги | ютилитиз | services like water, heating, or electricity for a home | p2 | apartment utility bills and keys on a wooden desk | |
| v3 | heating | слово | отопление | хитинг | the system that keeps a home warm | A1 | radiator under a window in an apartment room | |
| v4 | final inspection | связка | финальный осмотр | файнэл инспекшн | the last check of the apartment before the deposit is returned | A3 | landlord checking an empty apartment room with a clipboard | |
| v5 | fixed-term | слово | с фиксированным сроком | фиксд тёрм | lasting for a set period and not open-ended | A4 | — | |
| v6 | work from home | связка | работать из дома | уорк фром хоум | do your job at home instead of an office | p4, p5 | laptop on a dining table in a small apartment | |
| v7 | allowed | слово | разрешён | элауд | permitted by rules or by the owner | p6, A6 | — | |
| v8 | move in | связка | въехать | мув ин | start living in a new home | p7, A7 | person carrying a box into an apartment doorway | |

## Находки валидатора (урок как его написала модель; фатальные держат день до P2R, §15 отчёта)

| код | порог | адрес | что |
|---|---|---|---|
| `exchange.second_question` | предупреждение | B1 | the closing message «What about electricity?» ends with a question mark |
| `exchange.second_question` | предупреждение | B2 | the closing message «How much is the deposit?» ends with a question mark |
| `filler.one_in_dialogue` | предупреждение | p2 | exchanges 2 and 8 say p2 with the same filler «the deposit» |
| `filler.one_in_dialogue` | предупреждение | B3 | «one year» is not one of p3's fillers |
| `filler.one_in_dialogue` | предупреждение | p3.f1 | «one-year» is marked in_dialogue, but no line says it |
| `key.no_content_word` | предупреждение | B1 | the key «What about» has no content word |
| `line.ne_frame` | **фатально** | B3 | «I need a one-year contract.» is not «I need a ___ contract.» with «one year»; served as «I need a one year contract.» |
| `key.not_in_line` | предупреждение | B3 | the key «need a contract» is not in «I need a one year contract.» |
| `key.contains_filler` | предупреждение | B4 | the key «work from» takes words of the filler «from home» |
| `key.contains_filler` | предупреждение | B6 | the key «cat allowed» takes words of the filler «a cat» |
| `variant.longer` | предупреждение | B6 | the variant «Can I keep a cat?» has 5 words, the line 4 |
| `key.no_content_word` | предупреждение | B7 | the key «When can I» has no content word |
| `check.verbatim` | предупреждение | x1.check | the right option «Water and heating» repeats «water and» of the partner's line |
| `check.verbatim` | предупреждение | x7.check | the right option «On the first day of next month» repeats «of next» of the partner's line |
| `listening.no_learner_value` | предупреждение | lesson | no question asks for a value the learner gave (a filler said in the dialogue) |
| `vocab.used_in_wrong` | предупреждение | v1 | «deposit» is not in the partner's line of exchange 8 |
| `vocab.used_in_wrong` | предупреждение | v5 | «fixed-term» is not in the partner's line of exchange 4 |
| `vocab.free_combination` | предупреждение | v6 | «work from home» is a free combination of ordinary words |
