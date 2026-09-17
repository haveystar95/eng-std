# GEN-3 · rent (ru→en, средний)

Цель плана (слова ученика): «Снимаю квартиру в Берлине на год: смотрю квартиру, спрашиваю про депозит, коммунальные платежи и договор. Работаю удалённо, у меня кошка»

Роль ученика в плане: Tenant / Арендатор. Сцена 1: «Просмотр» (Landlord / Арендодатель); сцена 2: «Договор» (Landlord / Арендодатель).

> Каждый день — урок, каким его получил бы ученик (прошедший порог; при `failed` — ответ модели как написан). Находки — по ответу модели ДО починок, одним валидатором наряда и против одного и того же дня 1, так что «было» посчитано правилами, которых v4.5 не знал. Факты сюжета (цены, договорённости) и роли код не проверяет — их читает архитектор.

## День 1 — `lesson_day.v4.6`

Итог: **ready** · починок P2R: 0 · вызовов урока: 1 · цена дня $0.0669 (без скидки кэша $0.0848) · из кэша 81 % входа · 49 с · ученик: Арендатор · собеседник: Арендодатель (мужчина)

### Сценарий диалога

| # | вид обмена | кто | реплика | перевод | каркас · наполнение |
|---|---|---|---|---|---|
| 1 | ответ | Арендодатель (собеседник) | This is the living room. The rent is 1,200 euros a month. | Это гостиная. Аренда — 1200 евро в месяц. |  |
| 1 | ответ | Арендатор (ученик) | I need it for a year. | Мне нужна квартира на год. | p1 · a year |
| 2 | вопрос ученика | Арендатор (ученик) | How much is the deposit? | Сколько составляет депозит? | p2 · the deposit |
| 2 | вопрос ученика | Арендодатель (собеседник) | The deposit is two months' rent, paid before move-in. | Депозит — это аренда за два месяца, его нужно внести до заселения. |  |
| 3 | вопрос ученика | Арендатор (ученик) | What's included in the utility charges? | Что входит в коммунальные платежи? | p3 · the utility charges |
| 3 | вопрос ученика | Арендодатель (собеседник) | Water and heating are included. Electricity and internet are extra. | Вода и отопление включены. Электричество и интернет оплачиваются отдельно. |  |
| 4 | ответ | Арендодатель (собеседник) | Do you work from home regularly? | Вы регулярно работаете из дома? |  |
| 4 | ответ | Арендатор (ученик) | Yes, I work from home. | Да, я работаю из дома. | p4 · from home |
| 5 | вопрос ученика | Арендатор (ученик) | Is that acceptable here? | Это здесь нормально? | p5 · that |
| 5 | вопрос ученика | Арендодатель (собеседник) | Yes, that's fine, as long as it's quiet. | Да, это нормально, если у вас тихо. |  |
| 6 | вопрос ученика | Арендатор (ученик) | Is a cat allowed? | Можно с кошкой? | p6 · a cat |
| 6 | вопрос ученика | Арендодатель (собеседник) | Yes, one cat is allowed, but not in the shared garden. | Да, одна кошка разрешена, но не в общем саду. |  |
| 7 | ответ | Арендодатель (собеседник) | When would you like to move in? | Когда вы хотели бы въехать? |  |
| 7 | ответ | Арендатор (ученик) | I'd like to move in next month. | Я хотел бы въехать в следующем месяце. | p7 · next month |
| 8 | вопрос ученика | Арендатор (ученик) | Can I pay it by bank transfer? | Можно оплатить это банковским переводом? | p8 · by bank transfer |
| 8 | вопрос ученика | Арендодатель (собеседник) | Yes, rent and deposit are both paid by bank transfer. | Да, и аренда, и депозит оплачиваются банковским переводом. |  |

### Каркасы

| id | вид | каркас | на родном | наполнения (в диалоге — жирным) |
|---|---|---|---|---|
| p1 | ответ | I need it for ___. | Мне нужна квартира на ___. | **a year** / год · six months / шесть месяцев · two years / два года |
| p2 | вопрос ученика | How much is ___? | Сколько составляет ___? | **the deposit** / депозит · the monthly rent / ежемесячная аренда · the agency fee / комиссия агентства |
| p3 | вопрос ученика | What's included in ___? | Что входит в ___? | **the utility charges** / коммунальные платежи · the rent / аренду · the price / цену |
| p4 | ответ | I work ___. | Я работаю ___. | **from home** / из дома · in an office / в офисе · hybrid / в гибридном формате |
| p5 | вопрос ученика | Is ___ acceptable here? | ___ здесь нормально? | **that** / это · remote work / удалённая работа · late calls / поздние созвоны |
| p6 | вопрос ученика | Is ___ allowed? | Можно с ___? | **a cat** / кошкой · a small dog / маленькой собакой · a bike / велосипедом |
| p7 | ответ | I'd like to move in ___. | Я хотел бы въехать ___. | **next month** / в следующем месяце · on Monday / в понедельник · in two weeks / через две недели |
| p8 | вопрос ученика | Can I pay it ___? | Можно оплатить это ___? | **by bank transfer** / банковским переводом · in cash / наличными · by card / картой |

### Проверки обменов

| # | вопрос | варианты (✓ — верный) |
|---|---|---|
| 1 | How much is the monthly rent? / Сколько стоит аренда в месяц? | 1,050 euros / 1050 евро · ✓ 1,200 euros / 1200 евро · 1,400 euros / 1400 евро |
| 2 | When does the deposit have to be paid? / Когда нужно внести депозит? | ✓ Before getting the keys / До получения ключей · With the second month's rent / Со вторым платежом за аренду · At the end of the lease / В конце аренды |
| 3 | Which two things cost extra? / Какие две вещи оплачиваются отдельно? | ✓ Power and internet / Электричество и интернет · Water and heating / Вода и отопление · Heating and internet / Отопление и интернет |
| 4 | What does the landlord ask about? / О чём спрашивает арендодатель? | ✓ The tenant's work location / Где арендатор обычно работает · The tenant's working hours / В какие часы работает арендатор · The tenant's employer / Кто работодатель арендатора |
| 5 | What condition does the landlord give? / Какое условие называет арендодатель? | ✓ There should be no loud noise / Не должно быть сильного шума · You need a separate office / Нужен отдельный кабинет · You can work only part-time / Можно работать только неполный день |
| 6 | Where can't the cat go? / Куда кошке нельзя? | ✓ Into the common garden / В общий сад · Into the bedroom / В спальню · Onto the balcony / На балкон |
| 7 | What does the landlord want to know? / Что хочет узнать арендодатель? | ✓ The planned move-in time / Когда арендатор планирует въехать · How long the contract is / На какой срок нужен договор · How many people will live there / Сколько человек будет жить в квартире |
| 8 | What payment method does the landlord accept? / Какой способ оплаты принимает арендодатель? | Card at the office / Картой в офисе · Cash on move-in day / Наличными в день заселения · ✓ Transfer through the bank / Банковским переводом |

### Слушаю весь визит

- L1. На какой срок арендатору нужна квартира? — ✓ На год · На полгода · На два года
- L2. Что включено в коммунальные платежи? — Интернет и электричество · ✓ Вода и отопление · Только отопление
- L3. При каком условии удалённая работа подходит? — Если есть отдельный кабинет · ✓ Если в квартире тихо · Если работать только днём
- L4. Какое ограничение есть для кошки? — ✓ Нельзя в общий сад · Нельзя на балкон · Нельзя оставлять одну

### Словарь

| id | слово | вид | перевод | где звучит |
|---|---|---|---|---|
| v1 | deposit | слово | депозит | p2, A8 |
| v2 | utility charges | связка | коммунальные платежи | p3 |
| v3 | included | слово | включён | p3, A3 |
| v4 | heating | слово | отопление | A3 |
| v5 | remote work | связка | удалённая работа | p5 |
| v6 | acceptable | слово | приемлемо | p5 |
| v7 | move-in | слово | заселение | A2, p7 |
| v8 | bank transfer | связка | банковский перевод | p8, A8 |

### Находки в ответе модели (до починок)

| код | порог | адрес | что |
|---|---|---|---|
| `frame.unresolved_pronoun` | предупреждение | p1 | «I need it for ___.» leans on «it», and nothing in the frame is what it stands for |
| `frame.unresolved_pronoun` | предупреждение | p8 | «Can I pay it ___?» leans on «it», and nothing in the frame is what it stands for |
| `variant.longer` | предупреждение | B6 | the variant «Can I keep a cat?» has 5 words, the line 4 |
| `check.verbatim` | предупреждение | x3.check | the right option «Power and internet» repeats «and internet» of the partner's line |
| `listening.same_exchange` | предупреждение | L3 | L1 and this question are both about exchange 1 |
| `listening.distractor_not_filler` | предупреждение | L1 | the question asks p1's slot («год»): the right option «На год» is a number or a time, the wrong option «На полгода» is neither a number nor a time |
| `native.gendered_past` | предупреждение | B7 | «Я хотел бы въехать в следующем месяце.» says «хотел» about the learner while the learner's gender is unknown |
| `native.gendered_past` | предупреждение | p7 | «Я хотел бы въехать ___.» says «хотел» about the learner while the learner's gender is unknown |

## День 2 — БЫЛО: `lesson_day.v4.5`, код до наряда (без ролей и без EARLIER_DAYS)

Итог: **ready** · починок P2R: 0 · вызовов урока: 1 · цена дня $0.0694 (без скидки кэша $0.0850) · из кэша 77 % входа · 46 с · ученик: Арендатор · собеседник: Арендодатель (мужчина)

### Сценарий диалога

| # | вид обмена | кто | реплика | перевод | каркас · наполнение |
|---|---|---|---|---|---|
| 1 | ответ | Арендодатель (собеседник) | The lease is for one year, starting on May first. | Договор на один год, он начинается первого мая. |  |
| 1 | ответ | Арендатор (ученик) | The start date is May first. | Дата начала — первое мая. | p1 · May first |
| 2 | вопрос ученика | Арендатор (ученик) | What happens to the deposit? | Что происходит с депозитом? | p2 · the deposit |
| 2 | вопрос ученика | Арендодатель (собеседник) | I return it after move-out, if there is no damage. | Я возвращаю его после выезда, если нет повреждений. |  |
| 3 | вопрос ученика | Арендатор (ученик) | What is included in the monthly charges? | Что входит в ежемесячные платежи? | p3 · the monthly charges |
| 3 | вопрос ученика | Арендодатель (собеседник) | Water and heating are included, but electricity and internet are separate. | Вода и отопление включены, а электричество и интернет оплачиваются отдельно. |  |
| 4 | переспрос | Арендатор (ученик) | Could you say that more slowly, please? | Скажите, пожалуйста, это помедленнее. | — |
| 4 | переспрос | Арендодатель (собеседник) | Heating and water are included. Electricity and internet are separate. | Отопление и вода включены. Электричество и интернет отдельно. |  |
| 5 | вопрос ученика | Арендатор (ученик) | Could you explain the notice period? | Можете объяснить срок уведомления? | p4 · the notice period |
| 5 | вопрос ученика | Арендодатель (собеседник) | You need to give three months' notice before moving out. | Вам нужно предупредить за три месяца до выезда. |  |
| 6 | вопрос ученика | Арендатор (ученик) | Is there any rule about working remotely? | Есть какое-то правило насчёт удалённой работы? | p5 · working remotely |
| 6 | вопрос ученика | Арендодатель (собеседник) | Remote work is fine, as long as you do not register a business here. | Удалённая работа допустима, пока вы не регистрируете здесь бизнес. |  |
| 7 | вопрос ученика | Арендатор (ученик) | Is there any condition for my cat? | Есть какое-то условие для моей кошки? | p6 · my cat |
| 7 | вопрос ученика | Арендодатель (собеседник) | Your cat is fine, but any damage must be repaired. | Кошка допустима, но любой ущерб нужно будет устранить. |  |
| 8 | ответ | Арендодатель (собеседник) | I can email the contract today, and rent is due on the first. | Я могу отправить договор сегодня по почте, а аренда платится первого числа. |  |
| 8 | ответ | Арендатор (ученик) | Please email the contract today. | Пожалуйста, отправьте договор сегодня. | p7 · the contract today |

### Каркасы

| id | вид | каркас | на родном | наполнения (в диалоге — жирным) |
|---|---|---|---|---|
| p1 | ответ | The start date is ___. | Дата начала — ___. | **May first** / первое мая · June first / первое июня · July fifteenth / пятнадцатое июля |
| p2 | вопрос ученика | What happens to ___? | Что происходит с ___? | **the deposit** / депозитом · the first payment / первым платежом · the key fee / платой за ключ |
| p3 | вопрос ученика | What is included in ___? | Что входит в ___? | **the monthly charges** / ежемесячные платежи · the base rent / базовую аренду · the service fee / сервисный сбор |
| p4 | вопрос ученика | Could you explain ___? | Можете объяснить ___? | **the notice period** / срок уведомления · the move-out rules / правила выезда · the payment schedule / график платежей |
| p5 | вопрос ученика | Is there any rule about ___? | Есть какое-то правило насчёт ___? | **working remotely** / удалённой работы · having guests overnight / гостей с ночёвкой · using the cellar / использования подвала |
| p6 | вопрос ученика | Is there any condition for ___? | Есть какое-то условие для ___? | **my cat** / моей кошки · my bike / моего велосипеда · my washing machine / моей стиральной машины |
| p7 | ответ | Please email ___. | Пожалуйста, отправьте по почте ___. | **the contract today** / договор сегодня · the updated version / обновлённую версию · the signed copy / подписанную копию |

### Проверки обменов

| # | вопрос | варианты (✓ — верный) |
|---|---|---|
| 1 | When does the rental period begin? / Когда начинается срок аренды? | ✓ At the beginning of May / В начале мая · On the first day of June / В первый день июня · In the middle of April / В середине апреля |
| 2 | When is the deposit given back? / Когда возвращают депозит? | Before the tenant moves in / До въезда арендатора · ✓ After the tenant leaves, if the flat is fine / После выезда арендатора, если с квартирой всё в порядке · In monthly parts during the lease / По частям каждый месяц во время аренды |
| 3 | Which costs are not part of the monthly payment? / Какие расходы не входят в ежемесячный платёж? | Heating and water / Отопление и вода · ✓ Electricity and internet / Электричество и интернет · Deposit and insurance / Депозит и страховка |
| 4 | Which two costs are included? / Какие два расхода включены? | Power and web service / Электричество и интернет · ✓ Water and heating / Вода и отопление · Cleaning and parking / Уборка и парковка |
| 5 | How much advance warning does the landlord require? / За сколько нужно предупредить заранее? | About four weeks / Примерно за четыре недели · ✓ A full quarter of a year / За целый квартал года · Half a year ahead / За полгода |
| 6 | What is not allowed in the flat? / Что нельзя делать в квартире? | Use a laptop for your job / Работать за ноутбуком · Meet a client once / Один раз встретиться с клиентом · ✓ Officially base a business there / Официально оформить там бизнес |
| 7 | What responsibility does the tenant have about the pet? / Какая ответственность у арендатора из-за питомца? | Pay a higher rent every month / Платить более высокую аренду каждый месяц · ✓ Fix any harm caused in the flat / Устранить любой ущерб в квартире · Keep the cat outside at night / Держать кошку ночью снаружи |
| 8 | When is the rent payment due each month? / Когда каждый месяц нужно платить аренду? | ✓ On the first day of the month / В первый день месяца · At the end of the month / В конце месяца · In the middle of the month / В середине месяца |

### Слушаю весь визит

- L1. На какой срок предлагают договор аренды? — На шесть месяцев · ✓ На один год · На два года
- L2. Какие расходы арендатор будет оплачивать отдельно? — Воду и отопление · ✓ Электричество и интернет · Только интернет
- L3. О чём арендатор спросил насчёт своей жизни в квартире? — ✓ О своей кошке · О парковке машины · О детской площадке
- L4. Что арендодатель разрешает при удалённой работе? — ✓ Работать из квартиры без регистрации бизнеса · Открыть офис в квартире · Принимать клиентов каждый день как в салоне

### Словарь

| id | слово | вид | перевод | где звучит |
|---|---|---|---|---|
| v1 | lease | слово | договор аренды | A1, A8 |
| v2 | deposit | слово | депозит | p2 |
| v3 | monthly charges | связка | ежемесячные платежи | p3 |
| v4 | notice period | связка | срок уведомления | p4 |
| v5 | move-out | связка | выезд | A2, A5 |
| v6 | working remotely | связка | удалённая работа | p5 |
| v7 | register a business | связка | зарегистрировать бизнес | A6 |
| v8 | damage | слово | ущерб | A2, A7 |

### Находки в ответе модели (до починок)

| код | порог | адрес | что |
|---|---|---|---|
| `variant.longer` | предупреждение | B2 | the variant «How do I get the deposit back?» has 7 words, the line 5 |
| `check.verbatim` | предупреждение | x3.check | the right option «Electricity and internet» repeats «electricity and» of the partner's line |
| `listening.same_exchange` | предупреждение | L4 | L3 and this question are both about exchange 6 |
| `vocab.used_in_wrong` | предупреждение | v1 | «lease» is not in the partner's line of exchange 8 |
| `vocab.used_in_wrong` | предупреждение | v5 | «move-out» is not in the partner's line of exchange 5 |
| `vocab.known_repeat` | **фатальная** | v2 | «deposit» is a word the learner already learned on day 1 |
| `frame.known_native_repeat` | предупреждение | p3 | the native «Что входит в ___?» is the native pattern of «What's included in ___?», learned on day 1 |

## День 2 — СТАЛО: `lesson_day.v4.6` (роли плана, EARLIER_DAYS = день 1)

Итог: **ready** · починок P2R: 0 · вызовов урока: 1 · цена дня $0.0655 (без скидки кэша $0.0834) · из кэша 80 % входа · 42 с · ученик: Арендатор · собеседник: Арендодатель (мужчина)

### Сценарий диалога

| # | вид обмена | кто | реплика | перевод | каркас · наполнение |
|---|---|---|---|---|---|
| 1 | ответ | Арендодатель (собеседник) | The lease is for twelve months, starting on May first. | Договор на двенадцать месяцев, он начинается первого мая. |  |
| 1 | ответ | Арендатор (ученик) | That start date works for May first. | Эта дата начала мне подходит: первое мая. | p1 · May first |
| 2 | вопрос ученика | Арендатор (ученик) | Could you explain the notice period? | Вы можете объяснить срок уведомления? | p2 · the notice period |
| 2 | вопрос ученика | Арендодатель (собеседник) | It's three months, and it must be in writing. | Он составляет три месяца, и уведомление должно быть письменным. |  |
| 3 | вопрос ученика | Арендатор (ученик) | What happens to the deposit? | Что происходит с депозитом? | p3 · the deposit |
| 3 | вопрос ученика | Арендодатель (собеседник) | I return it after the final inspection, if there is no damage. | Я возвращаю его после финальной проверки, если нет повреждений. |  |
| 4 | ответ | Арендодатель (собеседник) | The monthly charges cover water, heating, and building cleaning. | Ежемесячные платежи покрывают воду, отопление и уборку дома. |  |
| 4 | ответ | Арендатор (ученик) | Good, monthly charges include building cleaning. | Хорошо, в ежемесячные платежи входит уборка дома. | p4 · building cleaning |
| 5 | вопрос ученика | Арендатор (ученик) | Is electricity paid separately? | Электричество оплачивается отдельно? | p5 · electricity |
| 5 | вопрос ученика | Арендодатель (собеседник) | Yes, electricity and internet are separate, and you arrange both yourself. | Да, электричество и интернет оплачиваются отдельно, и вы сами всё оформляете. |  |
| 6 | переспрос | Арендатор (ученик) | Sorry, could you say that more slowly? | Извините, можете сказать это помедленнее? | — |
| 6 | переспрос | Арендодатель (собеседник) | Electricity and internet are extra. You set them up yourself. | Электричество и интернет отдельно. Вы сами их оформляете. |  |
| 7 | вопрос ученика | Арендатор (ученик) | Is there any rule about my cat? | Есть ли какое-то правило насчёт моей кошки? | p6 · my cat |
| 7 | вопрос ученика | Арендодатель (собеседник) | Yes. Please keep the cat inside the flat, not in the garden. | Да. Пожалуйста, держите кошку в квартире, а не в саду. |  |
| 8 | вопрос ученика | Арендатор (ученик) | Could you clarify this clause? | Вы можете пояснить этот пункт? | p7 · this clause |
| 8 | вопрос ученика | Арендодатель (собеседник) | It says home office is fine if you do not disturb neighbors. | Там сказано, что работать из дома можно, если вы не мешаете соседям. |  |

### Каркасы

| id | вид | каркас | на родном | наполнения (в диалоге — жирным) |
|---|---|---|---|---|
| p1 | ответ | That start date works for ___. | Эта дата начала мне подходит: ___. | **May first** / первое мая · June first / первое июня |
| p2 | вопрос ученика | Could you explain ___? | Вы можете объяснить ___? | **the notice period** / срок уведомления · the payment schedule / график платежей · the move-out rules / правила выезда |
| p3 | вопрос ученика | What happens to ___? | Что происходит с ___? | **the deposit** / депозитом · the first payment / первым платежом |
| p4 | ответ | Monthly charges include ___. | В ежемесячные платежи входит ___. | **building cleaning** / уборка дома · water / вода · heating / отопление |
| p5 | вопрос ученика | Is ___ paid separately? | ___ оплачивается отдельно? | **electricity** / электричество · internet / интернет |
| p6 | вопрос ученика | Is there any rule about ___? | Есть ли какое-то правило насчёт ___? | **my cat** / моей кошки · quiet hours / тихих часов |
| p7 | вопрос ученика | Could you clarify ___? | Вы можете пояснить ___? | **this clause** / этот пункт · that sentence / то предложение |

### Проверки обменов

| # | вопрос | варианты (✓ — верный) |
|---|---|---|
| 1 | When does the lease begin? / Когда начинается договор? | ✓ On the first day of May / В первый день мая · At the end of April / В конце апреля · In the middle of May / В середине мая |
| 2 | How must the tenant give notice? / Как арендатор должен подать уведомление? | By phone call / По телефону · ✓ In written form / Письменно · With a text message / Сообщением |
| 3 | When is the deposit given back? / Когда возвращают депозит? | ✓ After the apartment check at the end / После итоговой проверки квартиры · Before the tenant moves out / До выезда арендатора · With the first monthly payment / Вместе с первым ежемесячным платежом |
| 4 | Which service is part of the monthly charges? / Какая услуга входит в ежемесячные платежи? | Apartment insurance / Страховка квартиры · ✓ Cleaning of shared areas / Уборка общих зон · Private parking / Личное парковочное место |
| 5 | Which two services does the landlord say are extra? / Какие две услуги арендодатель называет отдельными? | ✓ Power and web service / Электричество и интернет · Water and heating / Вода и отопление · Cleaning and parking / Уборка и парковка |
| 6 | Who sets up those extra services? / Кто оформляет эти отдельные услуги? | ✓ The tenant does it / Это делает арендатор · The building manager does it / Это делает управляющий домом · The landlord does it / Это делает арендодатель |
| 7 | Where should the cat stay? / Где должна находиться кошка? | ✓ Inside the apartment / Внутри квартиры · In the shared garden / В общем саду · In the basement area / В подвальном помещении |
| 8 | What condition is attached to working from home? / Какое условие связано с работой из дома? | ✓ You must avoid bothering other residents / Нужно не мешать другим жильцам · You need a separate work room / Нужна отдельная рабочая комната · You can only work at weekends / Можно работать только по выходным |

### Слушаю весь визит

- L1. На какой срок договор аренды? — На шесть месяцев · ✓ На один год · На два года
- L2. Что арендатор уточнил про отдельную оплату? — ✓ Электричество · Уборку дома · Отопление
- L3. Когда арендодатель возвращает депозит? — ✓ После финальной проверки · В день въезда · Через неделю после подписания
- L4. Какое правило действует для работы из дома? — Можно только днём · ✓ Нужно не мешать соседям · Нужен отдельный кабинет

### Словарь

| id | слово | вид | перевод | где звучит |
|---|---|---|---|---|
| v1 | lease | слово | договор аренды | A1 |
| v2 | notice period | связка | срок уведомления | p2 |
| v3 | in writing | связка | в письменном виде | A2 |
| v4 | final inspection | связка | финальная проверка | A3 |
| v5 | damage | слово | повреждения | A3 |
| v6 | building cleaning | связка | уборка дома | p4, A4 |
| v7 | separately | слово | отдельно | p5 |
| v8 | clause | слово | пункт договора | p7 |

### Находки в ответе модели (до починок)

| код | порог | адрес | что |
|---|---|---|---|
| `variant.longer` | предупреждение | B5 | the variant «Do I pay electricity separately?» has 5 words, the line 4 |
| `rescue.new_fact` | предупреждение | A6 | the repeat says «extra», «set», which exchange 5 did not |
