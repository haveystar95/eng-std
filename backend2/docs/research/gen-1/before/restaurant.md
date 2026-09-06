# Ужин в ресторане: сделать заказ и спокойно пожаловаться, если принесли холодное или не то блюдо

- план `01M1VTFSNVJ1VXXG95A582QKVR` · ru→en · basic · событие 2026-09-08 · статус active
- цель: «Ужин в ресторане: сделать заказ, а потом пожаловаться, что блюдо принесли холодным и не то»
- сводка: Ужин в ресторане: сделать заказ и спокойно пожаловаться, если принесли холодное или не то блюдо

## Сцены каркаса (P1)

### Сцена 1 — Заказ у стола

Вы сидите за столом и говорите с официантом. Нужно попросить меню, выбрать блюдо и сделать простой заказ так, чтобы вас поняли с первого раза.

- умение ``: сможете попросить меню или спросить, можно ли уже заказать — чек: может начать разговор с официантом и перейти к заказу
- умение ``: сможете заказать блюдо и напиток в простой форме — чек: может назвать, что хочет, без перехода на русский
- opening_lines: «Are you ready to order?» · «Here is the menu.» · «What would you like to have?» · «What would you like to drink?» · «Anything else?»
- entities: restaurant, table, menu, waiter, waitress

### Сцена 2 — Проблема с блюдом

Официант приносит еду, и вы замечаете проблему. Нужно сразу и вежливо сказать, что блюдо не то или что оно холодное, чтобы официант понял жалобу.

- умение ``: сможете сказать, что принесли не то блюдо — чек: может ясно указать, что заказ не совпадает с тем, что принесли
- умение ``: сможете сказать, что блюдо холодное — чек: может прямо сообщить о температуре блюда
- opening_lines: «Here you are.» · «Enjoy your meal.» · «Is everything okay?» · «What seems to be the problem?» · «I'm sorry about that.»
- entities: dish, order, plate, soup, salad, steak

### Сцена 3 — Исправление заказа

После вашей жалобы официант будет уточнять, что именно не так и как решить проблему. Нужно коротко ответить и попросить понятное решение, чтобы получить правильное блюдо.

- умение ``: сможете назвать, что вы заказывали вместо принесенного блюда — чек: может уточнить свой исходный заказ одним коротким ответом
- умение ``: сможете попросить заменить блюдо — чек: может прямо попросить другое блюдо вместо неправильного
- умение ``: сможете попросить подогреть или принести горячее блюдо — чек: может попросить исправить проблему с холодной едой
- opening_lines: «What did you order?» · «Would you like me to change it?» · «Do you want the same dish?» · «Would you like it hot?» · «I'll bring a new one right away.»
- entities: kitchen, manager, bill, replacement dish

## День 1 — Заказ у стола (intro, ready, попыток 1, починок 0)

### Пары (по цепочке)

**1. [answer]**
- role: «Here is the menu.» — Вот меню.
- you: «Thanks. Can I order now?» — Спасибо. Можно уже заказать? · ключ: `order now` · s1.1

**2. [answer]**
- role: «What would you like to have?» — Что вы хотели бы взять?
- you: «I'd like the soup.» — Я хочу суп. · ключ: `soup` · s1.2

**3. [answer]**
- role: «What would you like to drink?» — Что вы хотели бы выпить?
- you: «Still water, please.» — Воду, пожалуйста. · ключ: `Still water` · s1.2

**4. [ask]**
- role: «Anything else?» — Что-нибудь еще?
- you: «Which soup is today?» — Какой суп сегодня? · ключ: `today` · s1.2

**5. [answer]**
- role: «Here is your dish.» — Вот ваше блюдо.
- you: «Sorry, it's cold and not my order.» — Извините, это холодное и не то блюдо. · ключ: `cold` · s1.2

### Слова и связки

- [words] **cold** — простуда · пример: «This fish is cold.» — Эта рыба холодная.
- [words] **menu** — меню · пример: «The menu is on the table.» — Меню на столе.
- [words] **soup** — суп · пример: «The soup looks good today.» — Суп сегодня выглядит хорошо.
- [chunks] **order now** — заказать сейчас · пример: «We can order now if you are ready.» — Мы можем заказать сейчас, если вы готовы.
- [chunks] **Still water** — негазированная вода · пример: «Still water is fine for me.» — Негазированная вода мне подойдет.
- [chunks] **not my order** — не мой заказ · пример: «Sorry, this is not my order.» — Извините, это не мой заказ.

### Числа на слух

- «The soup of the day is eight pounds.» — Суп дня стоит 8 фунтов. · value `8`
- «Still water is two pounds.» — Негазированная вода стоит 2 фунта. · value `2`

### Спасатели (сервер)

- «Could you speak more slowly, please?» — Помедленнее, пожалуйста.
- «Could you write it down, please?» — Напишите, пожалуйста.
- «Could you repeat that, please?» — Повторите ещё раз, пожалуйста.
- «How much is it?» — Сколько это стоит?
- «One moment, let me check.» — Секунду, я проверю.

## День 2 — Проблема с блюдом (intro, ready, попыток 1, починок 1)

### Пары (по цепочке)

**1. [answer]**
- role: «Here is your steak.» — Вот, пожалуйста, ваш стейк.
- you: «Sorry, I ordered the salad.» — Извините, я заказывал салат. · ключ: `salad` · s2.1

**2. [ask]**
- role: «Can I check that for you?» — Я могу что-нибудь проверить для вас?
- you: «Yes, please. It's the wrong dish.» — Да, пожалуйста. Это не то блюдо. · ключ: `wrong dish` · s2.1

**3. [answer]**
- role: «Is there another problem?» — Есть еще какая-то проблема?
- you: «Also, the steak is cold.» — И еще, стейк холодный. · ключ: `cold` · s2.2

**4. [answer]**
- role: «What seems to be the problem?» — Что кажется проблемой?
- you: «The problem is my steak.» — Проблема в том, что мой стейк. · ключ: `steak` · s2.2

**5. [answer]**
- role: «I'm sorry about that.» — Простите за это.
- you: «Thanks, I'll wait for a new dish.» — Спасибо, я подожду новое блюдо. · ключ: `dish` · s2.1

### Слова и связки

- [words] **salad** — салат · пример: «The salad is for another table.» — Салат для другого стола.
- [words] **steak** — стейк · пример: «I didn't order the steak.» — Я не заказывал стейк.
- [words] **dish** — блюдо · пример: «This dish isn't what I ordered.» — Это блюдо не то, что я заказывал.
- [chunks] **wrong dish** — неправильное блюдо · пример: «I think this is the wrong dish.» — Мне кажется, это неправильное блюдо.
- [chunks] **heat up** — разогреть · пример: «Could you heat up the soup, please?» — Не могли бы вы разогреть суп, пожалуйста?
- [chunks] **have a look** — взглянуть · пример: «Can you have a look at my steak?» — Вы можете взглянуть на мой стейк?
- [chunks] **new dish** — новое блюдо · пример: «They will bring a new dish soon.» — Они скоро принесут новое блюдо.

### Числа на слух

- «The salad will be ready in ten minutes.» — Салат готовится через 10 минут. · value `10`
- «A new steak will take fifteen minutes.» — Новый стейк будет через 15 минут. · value `15`

## День 3 — Прогон перед событием (final, pending, попыток 0, починок 0)

_материала нет_

## Реестр трат

| вызов | статус | версия | $ |
|---|---|---|---|
| outline: Ужин в ресторане: сделать заказ, а потом пожаловаться, что блюдо принесли холодным и не то | failed | plan_outline.v0.4.1 | 0.017093 |
| outline: Ужин в ресторане: сделать заказ, а потом пожаловаться, что блюдо принесли холодным и не то | succeeded | plan_outline.v0.4.1 | 0.014088 |
| pair_judge: пара 0 — Here is the menu. | succeeded | plan_pair_judge.v0.1 | 0.001150 |
| pair_judge: пара 1 — What would you like to have? | succeeded | plan_pair_judge.v0.1 | 0.001120 |
| pair_judge: пара 2 — What would you like to drink? | succeeded | plan_pair_judge.v0.1 | 0.001105 |
| pair_judge: пара 3 — Anything else? | succeeded | plan_pair_judge.v0.1 | 0.001185 |
| pair_judge: пара 4 — Here is your dish. | succeeded | plan_pair_judge.v0.1 | 0.001185 |
| day: день 1 — Заказ у стола | succeeded | plan_day.v0.6 | 0.023285 |
| pair_judge: пара 0 — Here is your steak. | succeeded | plan_pair_judge.v0.1 | 0.001135 |
| pair_judge: пара 1 — Can I check that for you? | succeeded | plan_pair_judge.v0.1 | 0.001273 |
| pair_rewrite: пара 1 — Can I check that for you? | succeeded | plan_pair_rewrite.v0.1 | 0.002170 |
| pair_judge: пара 1 — Can I check that for you? | succeeded | plan_pair_judge.v0.1 | 0.001118 |
| pair_judge: пара 2 — Is there another problem? | succeeded | plan_pair_judge.v0.1 | 0.001105 |
| pair_judge: пара 3 — What seems to be the problem? | succeeded | plan_pair_judge.v0.1 | 0.001213 |
| pair_rewrite: пара 3 — What seems to be the problem? | succeeded | plan_pair_rewrite.v0.1 | 0.002040 |
| pair_judge: пара 3 — What seems to be the problem? | succeeded | plan_pair_judge.v0.1 | 0.001123 |
| pair_judge: пара 4 — I'm sorry about that. | succeeded | plan_pair_judge.v0.1 | 0.001125 |
| day: день 2 — Проблема с блюдом | failed | plan_day.v0.6 | 0.025540 |
| day_repair: починка дня 2 — карточек 3 | succeeded | plan_day_repair.v0.2 | 0.012405 |
| **итого** | | | **0.110458** |

