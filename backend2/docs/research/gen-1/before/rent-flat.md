# Осмотреть квартиру с хозяином и обсудить основные условия аренды, залог и коммунальные

- план `01M1VTKH6HX9AVRQ15K7CKQ4VB` · ru→en · basic · событие 2026-09-08 · статус active
- цель: «Снимаю квартиру: осмотр с хозяином и разговор об условиях аренды, залоге и коммунальных»
- сводка: Осмотреть квартиру с хозяином и обсудить основные условия аренды, залог и коммунальные

## Сцены каркаса (P1)

### Сцена 1 — Осмотр квартиры

Вы приходите смотреть квартиру вместе с хозяином или агентом. Нужно понять, подходит ли жилье, что остается в квартире и можно ли сразу задать простые вопросы по состоянию.

- умение `s1.1`: Сможете спросить, что входит в квартиру — чек: может уточнить, есть ли мебель, техника и интернет
- умение `s1.2`: Сможете указать на простую проблему в квартире — чек: может назвать простую проблему, например слабый напор воды или сломанную лампу
- умение `s1.3`: Сможете спросить о правилах проживания — чек: может уточнить правила про гостей, курение и животных
- opening_lines: «Let me show you the apartment.» · «This is the living room.» · «The rent includes the furniture.» · «The washing machine is in the kitchen.» · «Do you have any questions about the flat?» · «Pets are not allowed.»
- entities: living room, kitchen, bathroom, washing machine, internet

### Сцена 2 — Условия аренды

После осмотра вы переходите к разговору об аренде. Важно спокойно выяснить цену, срок аренды и когда можно заехать.

- умение `s2.1`: Сможете спросить цену аренды — чек: может спросить, сколько стоит аренда в месяц
- умение `s2.2`: Сможете спросить о сроке аренды — чек: может уточнить минимальный срок аренды и срок предупреждения о выезде
- умение `s2.3`: Сможете спросить о дате заезда — чек: может уточнить, когда можно заехать
- opening_lines: «The rent is eight hundred a month.» · «Bills are not included.» · «The minimum stay is six months.» · «You need to give one month's notice.» · «The apartment is available from next week.» · «When would you like to move in?»
- entities: next week, this month, six months, one month notice

### Сцена 3 — Залог и платежи

В конце разговора вы обсуждаете деньги и порядок оплаты. Ваша задача — понять сумму залога, что входит в коммунальные и как вносить платежи.

- умение `s3.1`: Сможете спросить о залоге — чек: может уточнить сумму залога и когда его возвращают
- умение `s3.2`: Сможете спросить о коммунальных — чек: может уточнить, какие коммунальные оплачиваются отдельно
- умение `s3.3`: Сможете спросить о способе оплаты — чек: может уточнить способ оплаты и дату ежемесячного платежа
- opening_lines: «The deposit is one month's rent.» · «The deposit is refundable.» · «Electricity and water are extra.» · «Internet is included.» · «You can pay by bank transfer.» · «Rent is due on the first of each month.»
- entities: bank transfer, cash, the first of the month, electricity, water, internet

## День 1 — Осмотр квартиры (intro, ready, попыток 2, починок 1)

### Пары (по цепочке)

**1. [answer]**
- role: «Let me show you the apartment.» — Позвольте показать вам квартиру.
- you: «Sure, let's see the apartment.» — Конечно, давайте посмотрим квартиру. · ключ: _вся фраза_ · s1.1

**2. [answer]**
- role: «The washing machine is in the kitchen.» — Стиральная машина на кухне.
- you: «Oh, that's a bit odd then.» — О, это немного странно тогда. · ключ: _вся фраза_ · s1.1

**3. [ask]**
- role: «Do you have any questions about the flat?» — Есть вопросы о квартире?
- you: «Is internet included in rent?» — Интернет включён в арендную плату? · ключ: `internet` · s1.1

**4. [ask]**
- role: «Anything else you want to ask?» — Есть что-нибудь еще, что вы хотите спросить?
- you: «What about overnight guests?» — Как насчет гостей на ночь? · ключ: `overnight guests` · s1.3

**5. [answer]**
- role: «Pets are not allowed.» — Домашние животные не разрешены.
- you: «Sorry, do you mean no pets at all?» — Простите, вы имеете в виду вообще никаких животных? · ключ: `no pets at all` · s1.3

### Слова и связки

- [words] **lamp** — лампа · пример: «The lamp in the bathroom is broken.» — Лампа в ванной сломана.
- [words] **internet** — интернет-связь · пример: «Is the internet included in rent?» — Интернет включён в арендную плату?
- [words] **pressure** — напор · пример: «The water pressure is low in the shower.» — Напор воды в душе слабый.
- [chunks] **water pressure** — напор воды · пример: «Can you check the water pressure in the bathroom?» — Вы можете проверить напор воды в ванной?
- [chunks] **overnight guests** — гости на ночь · пример: «Are overnight guests allowed here?» — Здесь разрешены гости на ночь?
- [chunks] **no pets at all** — никаких животных вообще · пример: «So it is no pets at all in this flat?» — То есть в этой квартире вообще никаких животных?
- [chunks] **included in rent** — включено в аренду · пример: «Is the washing machine included in rent?» — Стиральная машина включена в аренду?

### Числа на слух

- «The internet is twenty pounds a month.» — Интернет стоит 20 фунтов в месяц. · value `20`
- «The deposit is five hundred pounds.» — Залог составляет 500 фунтов. · value `500`
- «The rent is nine hundred pounds a month.» — Аренда составляет 900 фунтов в месяц. · value `900`

### Спасатели (сервер)

- «Could you speak more slowly, please?» — Помедленнее, пожалуйста.
- «Could you write it down, please?» — Напишите, пожалуйста.
- «Could you repeat that, please?» — Повторите ещё раз, пожалуйста.
- «How much is it?» — Сколько это стоит?
- «One moment, let me check.» — Секунду, я проверю.

## День 2 — Условия аренды (intro, ready, попыток 2, починок 2)

### Пары (по цепочке)

**1. [answer]**
- role: «Bills are not included.» — Коммунальные не включены.
- you: «What is the monthly rent then?» — Какая тогда месячная аренда? · ключ: `monthly` · s2.1

**2. [answer]**
- role: «The minimum stay is six months.» — Минимальный срок аренды — шесть месяцев.
- you: «Sorry, is that the minimum stay?» — Простите, это минимальный срок? · ключ: `minimum` · s2.2

**3. [ask]**
- role: «Would you like to move in next week?» — Вы бы хотели заехать на следующей неделе?
- you: «What is the notice period?» — Какой срок предупреждения? · ключ: `notice` · s2.2

**4. [answer]**
- role: «The apartment is available from next week.» — Квартира доступна со следующей недели.
- you: «Can I move in this month?» — Я могу заехать в этом месяце? · ключ: `this month` · s2.3

**5. [answer]**
- role: «When would you like to move in?» — Когда вы хотели бы заехать?
- you: «Next week would be better.» — Лучше со следующей недели. · ключ: `Next week` · s2.3

### Слова и связки

- [words] **monthly** — месячный · пример: «I need to know the monthly rent first.» — Мне нужно сначала узнать месячную аренду.
- [words] **minimum** — минимальный · пример: «Is there a minimum stay for this apartment?» — Есть ли минимальный срок аренды для этой квартиры?
- [words] **notice** — предупреждение · пример: «Do I need to give notice before I leave?» — Мне нужно предупредить заранее перед выездом?
- [words] **available** — доступный · пример: «Is the apartment available now or later?» — Квартира доступна сейчас или позже?
- [chunks] **notice period** — период уведомления · пример: «The notice period is one month.» — Срок предупреждения — один месяц.
- [chunks] **minimum stay** — минимальный срок · пример: «The minimum stay is longer than I expected.» — Минимальный срок дольше, чем я ожидал.
- [chunks] **move in** — заехать · пример: «I can move in after work on Friday.» — Я могу заехать после работы в пятницу.
- [chunks] **from next week** — со следующей недели · пример: «The room is free from next week.» — Комната свободна со следующей недели.

### Числа на слух

- «The rent is eight hundred a month.» — Аренда — восемьсот в месяц. · value `800`
- «The stay is six months.» — Срок аренды — шесть месяцев. · value `6`
- «You need to give one month's notice.» — Вам нужно предупредить за один месяц. · value `1`

## День 3 — Прогон перед событием (final, pending, попыток 0, починок 0)

_материала нет_

## Реестр трат

| вызов | статус | версия | $ |
|---|---|---|---|
| outline: Снимаю квартиру: осмотр с хозяином и разговор об условиях аренды, залоге и коммунальных | failed | plan_outline.v0.4.1 | 0.016333 |
| outline: Снимаю квартиру: осмотр с хозяином и разговор об условиях аренды, залоге и коммунальных | succeeded | plan_outline.v0.4.1 | 0.015970 |
| pair_judge: пара 0 — Let me show you the apartment. | succeeded | plan_pair_judge.v0.1 | 0.001193 |
| pair_judge: пара 1 — The washing machine is in the kitchen. | succeeded | plan_pair_judge.v0.1 | 0.001245 |
| pair_judge: пара 1 — The washing machine is in the kitchen. | succeeded | plan_pair_judge.v0.1 | 0.001175 |
| pair_rewrite: пара 1 — The washing machine is in the kitchen. | succeeded | plan_pair_rewrite.v0.1 | 0.002095 |
| pair_judge: пара 2 — Do you have any questions about the flat? | succeeded | plan_pair_judge.v0.1 | 0.001218 |
| pair_rewrite: пара 2 — Do you have any questions about the flat? | succeeded | plan_pair_rewrite.v0.1 | 0.002078 |
| pair_judge: пара 2 — Do you have any questions about the flat? | succeeded | plan_pair_judge.v0.1 | 0.001203 |
| pair_judge: пара 3 — Yes, we can fix that. | succeeded | plan_pair_judge.v0.1 | 0.001200 |
| pair_rewrite: пара 3 — Yes, we can fix that. | succeeded | plan_pair_rewrite.v0.1 | 0.002058 |
| pair_judge: пара 3 — Yes, we can fix that. | succeeded | plan_pair_judge.v0.1 | 0.001140 |
| pair_judge: пара 4 — Pets are not allowed. | succeeded | plan_pair_judge.v0.1 | 0.001142 |
| day: день 1 — Осмотр квартиры | failed | plan_day.v0.6 | 0.025790 |
| day_repair: починка дня 1 — карточек 5 | succeeded | plan_day_repair.v0.2 | 0.016670 |
| pair_judge: пара 0 — Let me show you the apartment. | succeeded | plan_pair_judge.v0.1 | 0.001298 |
| pair_rewrite: пара 0 — Let me show you the apartment. | succeeded | plan_pair_rewrite.v0.1 | 0.002033 |
| pair_judge: пара 0 — Let me show you the apartment. | succeeded | plan_pair_judge.v0.1 | 0.001185 |
| pair_judge: пара 1 — The washing machine is in the kitchen. | succeeded | plan_pair_judge.v0.1 | 0.001213 |
| pair_rewrite: пара 1 — The washing machine is in the kitchen. | succeeded | plan_pair_rewrite.v0.1 | 0.002060 |
| pair_judge: пара 1 — The washing machine is in the kitchen. | succeeded | plan_pair_judge.v0.1 | 0.001175 |
| pair_judge: пара 2 — Do you have any questions about the flat? | succeeded | plan_pair_judge.v0.1 | 0.001218 |
| pair_rewrite: пара 2 — Do you have any questions about the flat? | succeeded | plan_pair_rewrite.v0.1 | 0.002098 |
| pair_judge: пара 2 — Do you have any questions about the flat? | succeeded | plan_pair_judge.v0.1 | 0.001203 |
| pair_judge: пара 3 — Anything else you want to ask? | succeeded | plan_pair_judge.v0.1 | 0.001195 |
| pair_judge: пара 4 — Pets are not allowed. | succeeded | plan_pair_judge.v0.1 | 0.001143 |
| day: день 1 — Осмотр квартиры | failed | plan_day.v0.6 | 0.026765 |
| day_repair: починка дня 1 — карточек 4 | succeeded | plan_day_repair.v0.2 | 0.014638 |
| pair_judge: пара 0 — Bills are not included. | succeeded | plan_pair_judge.v0.1 | 0.001198 |
| pair_judge: пара 1 — What would you like to ask? | succeeded | plan_pair_judge.v0.1 | 0.001150 |
| pair_judge: пара 2 — You need to give one month's notice . | succeeded | plan_pair_judge.v0.1 | 0.001160 |
| pair_judge: пара 3 — The apartment is available from next week. | succeeded | plan_pair_judge.v0.1 | 0.001218 |
| pair_judge: пара 4 — When would you like to move in? | succeeded | plan_pair_judge.v0.1 | 0.001165 |
| day: день 2 — Условия аренды | failed | plan_day.v0.6 | 0.026825 |
| day_repair: починка дня 2 — карточек 2 | succeeded | plan_day_repair.v0.2 | 0.010138 |
| pair_judge: пара 0 — Bills are not included. | succeeded | plan_pair_judge.v0.1 | 0.001240 |
| pair_judge: пара 1 — The minimum stay is six months. | succeeded | plan_pair_judge.v0.1 | 0.001218 |
| pair_judge: пара 2 — Anything else you want to ask? | succeeded | plan_pair_judge.v0.1 | 0.001183 |
| pair_judge: пара 3 — The apartment is available from next week. | succeeded | plan_pair_judge.v0.1 | 0.001248 |
| pair_judge: пара 4 — When would you like to move in? | succeeded | plan_pair_judge.v0.1 | 0.001170 |
| day: день 2 — Условия аренды | failed | plan_day.v0.6 | 0.028367 |
| day_repair: починка дня 2 — карточек 2 | succeeded | plan_day_repair.v0.2 | 0.010100 |
| **итого** | | | **0.235114** |

