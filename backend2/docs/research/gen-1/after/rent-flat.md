# Осмотреть квартиру с хозяином и обсудить аренду, залог и коммунальные на простом английском.

- план `01M1W3H273M0RZQ7DE4AM555TD` · ru→en · basic · событие 2026-09-08 · статус active
- цель: «Снимаю квартиру: осмотр с хозяином и разговор об условиях аренды, залоге и коммунальных»
- сводка: Осмотреть квартиру с хозяином и обсудить аренду, залог и коммунальные на простом английском.

## Сцены каркаса (P1)

### Сцена 1 — Осмотр квартиры

Вы приходите на просмотр и идете по квартире вместе с хозяином. Нужно понимать простые комментарии о квартире и задавать короткие вопросы по ходу осмотра.

- умение `s1.1`: Понимать, что входит в квартиру и что остается жильцу. — чек: Понимает фразы про мебель, технику и состояние квартиры.
- умение `s1.2`: Задать вопрос о проблеме в квартире. — чек: Спрашивает про отопление, воду или поломку.
- умение `s1.3`: Сказать, что вам подходит или не подходит вариант. — чек: Коротко говорит свое мнение о квартире.
- opening_lines: «This is the living room.» · «The rent includes the fridge and the washing machine.» · «The heating works well.» · «There is a small problem with the window.» · «Do you have any questions about the flat?» · «The previous tenant moved out last week.»
- entities: living room, kitchen, bathroom, window, heating, washing machine

### Сцена 2 — Условия аренды

После осмотра вы переходите к главным условиям. Нужно уточнить цену, срок аренды и понять, что требуется от вас перед заселением.

- умение `s2.1`: Спросить размер ежемесячной аренды. — чек: Задает прямой вопрос о цене в месяц.
- умение `s2.2`: Уточнить срок аренды. — чек: Спрашивает, на какой срок заключается аренда.
- умение `s2.3`: Понять, что нужно для заселения. — чек: Понимает фразы про документы и оплату до въезда.
- opening_lines: «The rent is eight hundred pounds a month.» · «The minimum term is six months.» · «I need one month’s rent in advance.» · «You can move in next Monday.» · «Do you have proof of income?» · «We usually sign a one-year contract.»
- entities: contract, proof of income, next Monday, six months, one year

### Сцена 3 — Залог и коммунальные

Теперь вы обсуждаете дополнительные платежи. Важно понять сумму залога, какие счета включены, и при необходимости задать простой уточняющий вопрос.

- умение `s3.1`: Спросить размер залога. — чек: Задает вопрос о сумме залога.
- умение `s3.2`: Понять, какие коммунальные включены в аренду. — чек: Понимает, включены ли вода, газ, электричество или интернет.
- умение `s3.3`: Уточнить, кто платит отдельный счет. — чек: Спрашивает, кто оплачивает конкретную услугу.
- opening_lines: «The deposit is one month’s rent.» · «Bills are not included.» · «Water is included, but electricity and gas are extra.» · «The internet is already set up.» · «You pay the electricity bill yourself.» · «Do you want me to explain the bills?»
- entities: deposit, water, gas, electricity, internet, bill

## День 1 — Осмотр квартиры (intro, ready, попыток 1, починок 1)

### Пары (по цепочке)

**1. [answer]**
- role: «This is the living room.» — Это гостиная.
- you: «I like the living room.» — Мне нравится гостиная. · ключ: `living room` · ещё: `Looks good` / `Nice room` · s1.3

**2. [answer]**
- role: «The rent includes the washing machine and the fridge.» — В аренду входят стиральная машина и холодильник.
- you: «That is good for me.» — Это хорошо для меня. · ключ: _вся фраза_ · ещё: `Good for me` / `That works` · s1.1

**3. [answer]**
- role: «The heating works well.» — Отопление работает хорошо.
- you: «Good, that is important.» — Хорошо, это важно. · ключ: _вся фраза_ · ещё: `Very important` / `Good to know` · s1.2

**4. [answer]**
- role: «There is a small problem with the window.» — Есть небольшая проблема с окном.
- you: «Okay, I'll check the window.» — Хорошо, я проверю окно. · ключ: `window` · ещё: `I'll check it` / `Okay, I'll look` · s1.2

**5. [ask]**
- role: «Do you have any questions about the flat?» — Есть вопросы о квартире?
- you: «Is there hot water in the bathroom?» — В ванной есть горячая вода? · ключ: `bathroom` · ещё: `Hot water there?` / `Does it have hot water?` · s1.2

**6. [answer]**
- role: «The previous tenant moved out last week.» — Предыдущий жилец съехал на прошлой неделе.
- you: «Oh, that's good.» — О, это хорошо. · ключ: _вся фраза_ · ещё: `That's good` / `Oh, good` · s1.2

### Слова и связки

- [words] **living room** — гостиная · пример: «The living room is very bright.» — Гостиная очень светлая.
- [words] **washing machine** — стиральная машина · пример: «The washing machine is in the kitchen.» — Стиральная машина находится на кухне.
- [words] **heating** — отопление · пример: «The heating is on in winter.» — Отопление включено зимой.
- [words] **window** — окно · пример: «The window in the bedroom is open.» — Окно в спальне открыто.
- [words] **bathroom** — ванная · пример: «Is the bathroom clean?» — Ванная чистая?
- [chunks] **rent includes** — входит в аренду · пример: «The rent includes a table and two chairs.» — В аренду входят стол и два стула.
- [chunks] **works well** — работает хорошо · пример: «The shower works well now.» — Душ теперь работает хорошо.
- [chunks] **small problem** — небольшая проблема · пример: «There is a small problem in the kitchen.» — На кухне есть небольшая проблема.

### Числа на слух

- «The deposit is five hundred pounds.» — Залог составляет 500 фунтов. · value `500`
- «The rent is nine hundred pounds a month.» — Аренда составляет 900 фунтов в месяц. · value `900`
- «The bills are usually eighty pounds a month.» — Коммунальные обычно составляют 80 фунтов в месяц. · value `80`

### Спасатели (сервер)

- «Could you speak more slowly, please?» — Помедленнее, пожалуйста.
- «Could you write it down, please?» — Напишите, пожалуйста.
- «Could you repeat that, please?» — Повторите ещё раз, пожалуйста.
- «How much is it?» — Сколько это стоит?
- «One moment, let me check.» — Секунду, я проверю.

## День 2 — Условия аренды (intro, ready, попыток 2, починок 2)

### Пары (по цепочке)

**1. [answer]**
- role: «The rent is eight hundred pounds a month.» — Аренда составляет восемьсот фунтов в месяц.
- you: «Okay, that is fine for me.» — Хорошо, это нормально для меня. · ключ: `fine` · ещё: `That is okay` / `Okay for me` · s2.1

**2. [answer]**
- role: «We usually sign a one-year contract.» — Обычно мы подписываем договор на один год.
- you: «I need a shorter term.» — Мне нужен более короткий срок. · ключ: `shorter` · ещё: `Less time` / `A short term` · s2.2

**3. [answer]**
- role: «I need one month’s rent in advance.» — Мне нужна аренда за один месяц вперед.
- you: «Okay, I understand.» — Хорошо, я понимаю. · ключ: `understand` · ещё: `I understand` / `Okay got it` · s2.3

**4. [answer]**
- role: «Do you have proof of income?» — У вас есть подтверждение дохода?
- you: «Yes, I have it.» — Да, оно у меня есть. · ключ: `it` · ещё: `Yes I do` / `I have it` · s2.3

**5. [ask]**
- role: «Any more questions?» — Есть еще вопросы?
- you: «What is the minimum term?» — Какой минимальный срок? · ключ: `minimum` · ещё: `Minimum term?` / `What term?` · s2.2

### Слова и связки

- [words] **minimum** — минимальный · пример: «The minimum term is important for me.» — Минимальный срок важен для меня.
- [words] **fine** — нормально · пример: «The rent is fine for me.» — Аренда для меня нормальная.
- [words] **shorter** — более короткий · пример: «I need a shorter contract.» — Мне нужен более короткий договор.
- [words] **understand** — понимаю · пример: «I understand the payment before move-in.» — Я понимаю оплату до заселения.
- [chunks] **in advance** — вперед · пример: «Do I pay the first month in advance?» — Я плачу за первый месяц вперед?
- [chunks] **proof of income** — подтверждение дохода · пример: «I can send my proof of income tonight.» — Я могу отправить подтверждение дохода сегодня вечером.
- [chunks] **one-year contract** — договор на один год · пример: «A one-year contract is too long for me.» — Договор на один год для меня слишком долгий.
- [chunks] **minimum term** — минимальный срок · пример: «Is the minimum term six months?» — Минимальный срок — шесть месяцев?

### Числа на слух

- «The rent is 800 pounds a month.» — Аренда — 800 фунтов в месяц. · value `800`
- «The minimum term is six months.» — Минимальный срок — шесть месяцев. · value `6`
- «I need 1 month’s rent in advance.» — Мне нужен 1 месяц аренды вперед. · value `1`
- «We usually sign a contract for one year.» — Обычно мы подписываем договор на 1 год. · value `1`

## День 3 — Прогон перед событием (final, pending, попыток 0, починок 0)

_материала нет_

## Реестр трат

| вызов | статус | версия | $ |
|---|---|---|---|
| outline: Снимаю квартиру: осмотр с хозяином и разговор об условиях аренды, залоге и коммунальных | failed | plan_outline.v0.4.2 | 0.015640 |
| outline: Снимаю квартиру: осмотр с хозяином и разговор об условиях аренды, залоге и коммунальных | succeeded | plan_outline.v0.4.2 | 0.015560 |
| pair_judge: пара 0 — This is the living room. | succeeded | plan_pair_judge.v0.2 | 0.002870 |
| pair_judge: пара 1 — The rent includes the washing machine and the fridge. | succeeded | plan_pair_judge.v0.2 | 0.002900 |
| pair_judge: пара 2 — The heating works well. | succeeded | plan_pair_judge.v0.2 | 0.002878 |
| pair_judge: пара 3 — There is a small problem with the window. | succeeded | plan_pair_judge.v0.2 | 0.003103 |
| pair_rewrite: пара 3 — There is a small problem with the window. | succeeded | plan_pair_rewrite.v0.2 | 0.002988 |
| pair_judge: пара 3 — There is a small problem with the window. | succeeded | plan_pair_judge.v0.2 | 0.002900 |
| pair_judge: пара 4 — Do you have any questions about the flat? | succeeded | plan_pair_judge.v0.2 | 0.002895 |
| pair_judge: пара 5 — The previous tenant moved out last week. | succeeded | plan_pair_judge.v0.2 | 0.003080 |
| pair_rewrite: пара 5 — The previous tenant moved out last week. | succeeded | plan_pair_rewrite.v0.2 | 0.002853 |
| pair_judge: пара 5 — The previous tenant moved out last week. | succeeded | plan_pair_judge.v0.2 | 0.002898 |
| day: день 1 — Осмотр квартиры | failed | plan_day.v0.7 | 0.033423 |
| day_repair: починка дня 1 — карточек 4 | succeeded | plan_day_repair.v0.3 | 0.016075 |
| pair_judge: пара 0 — The rent is eight hundred pounds a month. | succeeded | plan_pair_judge.v0.2 | 0.002902 |
| pair_judge: пара 1 — We usually sign a one-year contract. | succeeded | plan_pair_judge.v0.2 | 0.002895 |
| pair_judge: пара 2 — I need one month’s rent in advance. | succeeded | plan_pair_judge.v0.2 | 0.002893 |
| pair_judge: пара 3 — Do you have proof of income? | succeeded | plan_pair_judge.v0.2 | 0.002888 |
| pair_judge: пара 4 — Anything else you want to ask? | succeeded | plan_pair_judge.v0.2 | 0.002883 |
| day: день 2 — Условия аренды | failed | plan_day.v0.7 | 0.030595 |
| day_repair: починка дня 2 — карточек 4 | succeeded | plan_day_repair.v0.3 | 0.015058 |
| pair_judge: пара 0 — The rent is eight hundred pounds a month. | succeeded | plan_pair_judge.v0.2 | 0.002900 |
| pair_judge: пара 0 — The rent is eight hundred pounds a month. | succeeded | plan_pair_judge.v0.2 | 0.002918 |
| pair_judge: пара 1 — We usually sign a one-year contract. | succeeded | plan_pair_judge.v0.2 | 0.002898 |
| pair_judge: пара 2 — I need one month’s rent in advance. | succeeded | plan_pair_judge.v0.2 | 0.002893 |
| pair_judge: пара 3 — Do you have proof of income? | succeeded | plan_pair_judge.v0.2 | 0.002888 |
| pair_judge: пара 4 — Any more questions? | succeeded | plan_pair_judge.v0.2 | 0.002868 |
| day: день 2 — Условия аренды | failed | plan_day.v0.7 | 0.032255 |
| day_repair: починка дня 2 — карточек 3 | succeeded | plan_day_repair.v0.3 | 0.013220 |
| **итого** | | | **0.233017** |

