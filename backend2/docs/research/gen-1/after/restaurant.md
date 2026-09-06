# Ужин в ресторане: заказать еду и вежливо пожаловаться, если принесли не то блюдо или оно холодное.

- план `01M1W3M4KPS8PJEFB04RZQBNY5` · ru→en · basic · событие 2026-09-08 · статус active
- цель: «Ужин в ресторане: сделать заказ, а потом пожаловаться, что блюдо принесли холодным и не то»
- сводка: Ужин в ресторане: заказать еду и вежливо пожаловаться, если принесли не то блюдо или оно холодное.

## Сцены каркаса (P1)

### Сцена 1 — Заказ у столика

Вы сидите за столиком и говорите с официантом. Нужно понять простые вопросы по заказу и назвать, что вы хотите, без долгих объяснений.

- умение `s1.1`: сделать заказ блюда и напитка простыми фразами — чек: называет, что хочет заказать
- умение `s1.2`: уточнить простую деталь заказа — чек: задает короткий вопрос о блюде
- opening_lines: «Are you ready to order?» · «What would you like?» · «What would you like to drink?» · «Would you like anything else?» · «Chicken or beef?» · «Do you want fries or salad?»
- entities: menu, starter, main course, dessert, water, tea

### Сцена 2 — Подача блюда

Официант приносит заказ к столу. Вам нужно быстро понять, что он говорит, и сразу заметить, если блюдо не ваше или выглядит неправильно.

- умение `s2.1`: сказать, что это не то блюдо — чек: сообщает, что принесли другой заказ
- умение `s2.2`: сказать, что блюдо холодное — чек: сообщает о проблеме с температурой блюда
- opening_lines: «Here you go.» · «This is your chicken salad.» · «Enjoy your meal.» · «Is everything okay?» · «You ordered the pasta, right?» · «Can I get you anything else?»
- entities: pasta, chicken salad, soup, steak, burger

### Сцена 3 — Жалоба и решение

После вашей жалобы официант уточнит проблему и предложит решение. Ваша задача — коротко повторить, что не так, и сказать, чего вы хотите: заменить блюдо, принести горячее или убрать это блюдо из заказа.

- умение `s3.1`: объяснить проблему одной-двумя простыми фразами — чек: коротко описывает, что именно не так
- умение `s3.2`: попросить заменить блюдо — чек: просит принести правильное блюдо
- умение `s3.3`: понять простой ответ официанта о решении — чек: понимает, что официант предложил замену или извинение
- opening_lines: «I'm sorry about that.» · «What seems to be the problem?» · «Would you like me to replace it?» · «I'll bring you a hot one.» · «Let me check your order.» · «It will take a few minutes.»
- entities: manager, kitchen, order, replacement

## День 1 — Заказ у столика (intro, ready, попыток 1, починок 1)

### Пары (по цепочке)

**1. [answer]**
- role: «Are you ready to order?» — Вы готовы заказать?
- you: «Yes, I want chicken.» — Да, я хочу курицу. · ключ: `chicken` · ещё: `I want chicken` / `Chicken please` · s1.1

**2. [answer]**
- role: «What would you like to drink?» — Что вы хотели бы выпить?
- you: «I want water, please.» — Я хочу воду, пожалуйста. · ключ: `water` · ещё: `Water, please` / `I'd like water` · s1.1

**3. [ask]**
- role: «Would you like anything else?» — Хотите еще что нибудь?
- you: «Do you have chicken?» — У вас есть курица? · ключ: `chicken` · ещё: `Any chicken?` / `Do you have it?` · s1.1

**4. [answer]**
- role: «Chicken or beef?» — Курица или говядина?
- you: «I want chicken, please.» — Я хочу курицу, пожалуйста. · ключ: `chicken` · ещё: `Chicken, please` / `I'd like chicken` · s1.1

**5. [answer]**
- role: «Here is your main course.» — Вот ваше блюдо.
- you: «Sorry, it is cold and wrong.» — Извините, это холодное и не то. · ключ: `cold` · ещё: `It is cold` / `This is wrong` · s1.2

### Слова и связки

- [words] **cold** — простуда · пример: «The soup is cold.» — Суп холодный.
- [words] **chicken** — курица · пример: «I want chicken and water.» — Я хочу курицу и воду.
- [words] **beef** — говядина · пример: «The beef is not for me.» — Говядина не для меня.
- [chunks] **main course** — основное блюдо · пример: «My main course is chicken.» — Мое основное блюдо это курица.
- [chunks] **and wrong** — не то · пример: «It is cold and wrong.» — Это холодное и не то.

### Числа на слух

- «Chicken is number twelve.» — Курица номер 12. · value `12`
- «Water is three dollars.» — Вода стоит 3 доллара. · value `3`

### Спасатели (сервер)

- «Could you speak more slowly, please?» — Помедленнее, пожалуйста.
- «Could you write it down, please?» — Напишите, пожалуйста.
- «Could you repeat that, please?» — Повторите ещё раз, пожалуйста.
- «How much is it?» — Сколько это стоит?
- «One moment, let me check.» — Секунду, я проверю.

## День 2 — Подача блюда (intro, ready, попыток 2, починок 2)

### Пары (по цепочке)

**1. [answer]**
- role: «Here you go. This is your chicken salad.» — Вот, пожалуйста. Это ваш куриный салат.
- you: «Sorry, this is not my order.» — Извините, это не мой заказ. · ключ: `order` · ещё: `Not my order` / `This is wrong` · s2.1

**2. [answer]**
- role: «You ordered the pasta, right?» — Вы заказывали пасту, да?
- you: «No, I ordered soup.» — Нет, я заказал суп. · ключ: `soup` · ещё: `I ordered soup` / `Not pasta` · s2.1

**3. [answer]**
- role: «I see. Here is your steak.» — Понятно. Вот ваш стейк.
- you: «Sorry, the steak is cold.» — Извините, стейк холодный. · ключ: `steak` · ещё: `It is cold` / `The steak is cold` · s2.2

**4. [answer]**
- role: «Is everything okay?» — Все в порядке?
- you: «No, the soup is cold too.» — Нет, суп тоже холодный. · ключ: `cold` · ещё: `Soup is cold` / `It is cold too` · s2.2

**5. [ask]**
- role: «Can I get you anything else?» — Могу я принести вам что-нибудь еще?
- you: «Can you bring hot soup?» — Вы можете принести горячий суп? · ключ: `soup` · ещё: `Hot soup please` / `Bring hot soup?` · s2.2

### Слова и связки

- [words] **soup** — суп · пример: «The soup is for table eight.» — Суп для восьмого столика.
- [words] **steak** — стейк · пример: «This steak is not hot.» — Этот стейк не горячий.
- [words] **order** — заказ · пример: «I think this order is theirs.» — Думаю, этот заказ их.
- [chunks] **not my order** — не мой заказ · пример: «I think this is not my order.» — Думаю, это не мой заказ.
- [chunks] **is cold** — холодный · пример: «The pasta is cold now.» — Паста сейчас холодная.
- [chunks] **hot soup** — горячий суп · пример: «I asked for hot soup earlier.» — Я раньше попросил горячий суп.

### Числа на слух

- «The chicken salad is number twelve.» — Куриный салат — это номер двенадцать. · value `12`
- «The pasta is number eight.» — Паста — это номер восемь. · value `8`

## День 3 — Прогон перед событием (final, pending, попыток 0, починок 0)

_материала нет_

## Реестр трат

| вызов | статус | версия | $ |
|---|---|---|---|
| outline: Ужин в ресторане: сделать заказ, а потом пожаловаться, что блюдо принесли холодным и не то | succeeded | plan_outline.v0.4.2 | 0.014645 |
| pair_judge: пара 0 — Are you ready to order? | succeeded | plan_pair_judge.v0.2 | 0.002875 |
| pair_judge: пара 1 — What would you like to drink? | succeeded | plan_pair_judge.v0.2 | 0.002870 |
| pair_judge: пара 2 — Would you like anything else? | succeeded | plan_pair_judge.v0.2 | 0.003218 |
| pair_rewrite: пара 2 — Would you like anything else? | succeeded | plan_pair_rewrite.v0.2 | 0.002878 |
| pair_judge: пара 2 — Would you like anything else? | succeeded | plan_pair_judge.v0.2 | 0.003123 |
| pair_rewrite: пара 2 — Would you like anything else? | succeeded | plan_pair_rewrite.v0.2 | 0.002860 |
| pair_judge: пара 2 — Would you like anything else? | succeeded | plan_pair_judge.v0.2 | 0.002883 |
| pair_judge: пара 3 — Chicken or beef? | succeeded | plan_pair_judge.v0.2 | 0.002875 |
| pair_judge: пара 4 — Here is your main course. | succeeded | plan_pair_judge.v0.2 | 0.002893 |
| day: день 1 — Заказ у столика | failed | plan_day.v0.7 | 0.026108 |
| day_repair: починка дня 1 — карточек 3 | succeeded | plan_day_repair.v0.3 | 0.013255 |
| pair_judge: пара 1 — What would you like to drink? | succeeded | plan_pair_judge.v0.2 | 0.002878 |
| pair_judge: пара 3 — Chicken or beef? | succeeded | plan_pair_judge.v0.2 | 0.002883 |
| pair_judge: пара 0 — This is your chicken salad. | succeeded | plan_pair_judge.v0.2 | 0.003138 |
| pair_rewrite: пара 0 — This is your chicken salad. | succeeded | plan_pair_rewrite.v0.2 | 0.002987 |
| pair_judge: пара 0 — This is your chicken salad. | succeeded | plan_pair_judge.v0.2 | 0.002898 |
| pair_judge: пара 1 — You ordered the pasta, right? | succeeded | plan_pair_judge.v0.2 | 0.003165 |
| pair_rewrite: пара 1 — You ordered the pasta, right? | succeeded | plan_pair_rewrite.v0.2 | 0.002915 |
| pair_judge: пара 1 — You ordered the pasta, right? | succeeded | plan_pair_judge.v0.2 | 0.002890 |
| pair_judge: пара 2 — Is everything okay? | succeeded | plan_pair_judge.v0.2 | 0.002880 |
| pair_judge: пара 3 — Can I get you anything else? | succeeded | plan_pair_judge.v0.2 | 0.002893 |
| day: день 2 — Подача блюда | failed | plan_day.v0.7 | 0.026807 |
| day_repair: починка дня 2 — карточек 7 | succeeded | plan_day_repair.v0.3 | 0.023040 |
| pair_judge: пара 0 — Here you go. This is your chicken salad. | succeeded | plan_pair_judge.v0.2 | 0.002915 |
| pair_judge: пара 1 — You ordered the pasta, right? | succeeded | plan_pair_judge.v0.2 | 0.002890 |
| pair_judge: пара 2 — I see. Here is your steak. | succeeded | plan_pair_judge.v0.2 | 0.002905 |
| pair_judge: пара 3 — Is everything okay? | succeeded | plan_pair_judge.v0.2 | 0.002875 |
| pair_judge: пара 4 — Can I get you anything else? | succeeded | plan_pair_judge.v0.2 | 0.002895 |
| day: день 2 — Подача блюда | failed | plan_day.v0.7 | 0.028097 |
| day_repair: починка дня 2 — карточек 6 | succeeded | plan_day_repair.v0.3 | 0.020948 |
| **итого** | | | **0.223382** |

