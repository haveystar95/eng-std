# GEN-3 · restaurant (ru→en, начальный)

Цель плана (слова ученика): «Ужин в ресторане с семьёй: заказать еду, спросить про блюда, попросить счёт. У дочки аллергия на орехи»

Роль ученика в плане: Parent / Родитель. Сцена 1: «Заказ за столом» (Waiter / Официант); сцена 2: «Счёт» (Waiter / Официант).

> Каждый день — урок, каким его получил бы ученик (прошедший порог; при `failed` — ответ модели как написан). Находки — по ответу модели ДО починок, одним валидатором наряда и против одного и того же дня 1, так что «было» посчитано правилами, которых v4.5 не знал. Факты сюжета (цены, договорённости) и роли код не проверяет — их читает архитектор.

## День 1 — `lesson_day.v4.6`

Итог: **ready** · починок P2R: 0 · вызовов урока: 1 · цена дня $0.0638 (без скидки кэша $0.0816) · из кэша 83 % входа · 40 с · ученик: Родитель · собеседник: Официант (мужчина)

### Сценарий диалога

| # | вид обмена | кто | реплика | перевод | каркас · наполнение |
|---|---|---|---|---|---|
| 1 | вопрос ученика | Родитель (ученик) | We need a few minutes. | Нам нужно ещё несколько минут. | p1 · a few minutes |
| 1 | вопрос ученика | Официант (собеседник) | Of course. I'll come back in a few minutes. | Конечно. Я подойду снова через несколько минут. |  |
| 2 | ответ | Официант (собеседник) | Are you ready to order now? | Вы уже готовы сделать заказ? |  |
| 2 | ответ | Родитель (ученик) | I'd like the grilled chicken. | Я бы взял grilled chicken. | p2 · the grilled chicken |
| 3 | вопрос ученика | Родитель (ученик) | What's in the pasta? | Что в пасте? | p3 · the pasta |
| 3 | вопрос ученика | Официант (собеседник) | It has tomato sauce, cheese, and basil. | В ней томатный соус, сыр и базилик. |  |
| 4 | вопрос ученика | Родитель (ученик) | Does it have nuts? | Там есть орехи? | p4 · nuts |
| 4 | вопрос ученика | Официант (собеседник) | No, the pasta doesn't contain nuts. | Нет, паста не содержит орехов. |  |
| 5 | ответ | Официант (собеседник) | Do you have any allergies I should know about? | Есть ли у вас аллергия, о которой мне нужно знать? |  |
| 5 | ответ | Родитель (ученик) | My daughter has a nut allergy. | У моей дочки аллергия на орехи. | p5 · a nut allergy |
| 6 | ответ | Официант (собеседник) | Then the fish is safer. It comes with potatoes and vegetables. | Тогда рыба безопаснее. Она подаётся с картофелем и овощами. |  |
| 6 | ответ | Родитель (ученик) | I'd like the fish. | Я бы взял рыбу. | p2 · the fish |
| 7 | вопрос ученика | Родитель (ученик) | Can we get two lemonades? | Можно нам два лимонада? | p6 · two lemonades |
| 7 | вопрос ученика | Официант (собеседник) | Yes, and one lemonade is sugar-free. | Да, и один лимонад будет без сахара. |  |
| 8 | ответ | Официант (собеседник) | So that's chicken, fish, pasta, and two lemonades. | Итак, это курица, рыба, паста и два лимонада. |  |
| 8 | ответ | Родитель (ученик) | Yes, that's right. | Да, всё верно. | p7 · right |

### Каркасы

| id | вид | каркас | на родном | наполнения (в диалоге — жирным) |
|---|---|---|---|---|
| p1 | вопрос ученика | We need ___. | Нам нужно ___. | **a few minutes** / ещё несколько минут · more time / ещё время |
| p2 | ответ | I'd like ___. | Я бы взял ___. | **the grilled chicken** / grilled chicken · **the fish** / рыбу · the soup / суп |
| p3 | вопрос ученика | What's in ___? | Что в ___? | **the pasta** / пасте · the soup / супе · the salad / салате |
| p4 | вопрос ученика | Does it have ___? | Там есть ___? | **nuts** / орехи · cheese / сыр · eggs / яйца |
| p5 | ответ | My daughter has ___. | У моей дочки ___. | **a nut allergy** / аллергия на орехи · a milk allergy / аллергия на молоко · an egg allergy / аллергия на яйца |
| p6 | вопрос ученика | Can we get ___? | Можно нам ___? | **two lemonades** / два лимонада · three waters / три воды · one juice / один сок |
| p7 | ответ | That's ___. | Это ___. | **right** / верно · fine / хорошо |

### Проверки обменов

| # | вопрос | варианты (✓ — верный) |
|---|---|---|
| 1 | When will the waiter return? / Когда официант вернётся? | ✓ After a short wait / Через недолгое время · At the end of dinner / В конце ужина · Right away / Сразу |
| 2 | What does the waiter ask? / О чём спрашивает официант? | Whether the family wants dessert / Хочет ли семья десерт · ✓ Whether they can order now / Готовы ли они заказать сейчас · Whether they need another table / Нужен ли им другой стол |
| 3 | What ingredients does the pasta have? / Какие ингредиенты есть в пасте? | Cream, mushrooms, and onion / Сливки, грибы и лук · ✓ Tomato, cheese, and basil / Томатный соус, сыр и базилик · Chicken, rice, and beans / Курица, рис и фасоль |
| 4 | What does the waiter confirm about the pasta? / Что официант подтверждает о пасте? | It is served cold / Её подают холодной · ✓ It has no nuts / В ней нет орехов · It comes with bread / К ней подают хлеб |
| 5 | What does the waiter ask about? / О чём спрашивает официант? | ✓ Food allergies / Пищевые аллергии · Favorite dishes / Любимые блюда · The children's ages / Возраст детей |
| 6 | What side dishes come with the fish? / С чем подают рыбу? | Rice and salad / С рисом и салатом · ✓ Potatoes and vegetables / С картофелем и овощами · Pasta and cheese / С пастой и сыром |
| 7 | What special detail does the waiter add? / Какую дополнительную деталь говорит официант? | ✓ One drink has no sugar / Один напиток будет без сахара · Both drinks are hot / Оба напитка будут горячими · The drinks come with ice cream / Напитки подают с мороженым |
| 8 | Which items does the waiter repeat? / Какие позиции повторяет официант? | Fish, salad, and tea / Рыба, салат и чай · ✓ Chicken, fish, pasta, and two lemonades / Курица, рыба, паста и два лимонада · Soup, bread, and water / Суп, хлеб и вода |

### Слушаю весь визит

- L1. Что попросил родитель в начале? — Счёт · ✓ Ещё несколько минут · Детский стул
- L2. Какое блюдо официант назвал более безопасным для дочки? — Пасту · ✓ Рыбу · Курицу
- L3. Что родитель заказал попить? — ✓ Два лимонада · Три воды · Один сок
- L4. Что входит в пасту? — ✓ Томатный соус, сыр и базилик · Сливки, грибы и лук · Курица, рис и фасоль

### Словарь

| id | слово | вид | перевод | где звучит |
|---|---|---|---|---|
| v1 | grilled chicken | связка | курица на гриле | p2 |
| v2 | tomato sauce | связка | томатный соус | A3 |
| v3 | basil | слово | базилик | A3 |
| v4 | nuts | слово | орехи | p4, A4, p5 |
| v5 | allergy | слово | аллергия | A5, p5 |
| v6 | safer | слово | безопаснее | A6 |
| v7 | vegetables | слово | овощи | A6 |
| v8 | sugar-free | связка | без сахара | A7 |

### Находки в ответе модели (до починок)

| код | порог | адрес | что |
|---|---|---|---|
| `frame.unresolved_pronoun` | предупреждение | p4 | «Does it have ___?» leans on «it», and nothing in the frame is what it stands for |
| `variant.longer` | предупреждение | B3 | the variant «What is in the pasta?» has 5 words, the line 4 |
| `variant.longer` | предупреждение | B4 | the variant «Are there nuts in it?» has 5 words, the line 4 |
| `check.verbatim` | предупреждение | x2.check | the right option «Whether they can order now» repeats «order now» of the partner's line |
| `check.verbatim` | предупреждение | x6.check | the right option «Potatoes and vegetables» repeats «potatoes and» of the partner's line |
| `vocab.used_in_wrong` | предупреждение | v4 | «nuts» is not in frame p5 or its fillers |
| `vocab.learner_share` | предупреждение | lesson | 3 of 8 items are in the learner's frames or fillers (at least half) |

## День 2 — БЫЛО: `lesson_day.v4.5`, код до наряда (без ролей и без EARLIER_DAYS)

Итог: **ready** · починок P2R: 0 · вызовов урока: 1 · цена дня $0.0624 (без скидки кэша $0.0779) · из кэша 80 % входа · 41 с · ученик: Посетитель · собеседник: Официант (мужчина)

### Сценарий диалога

| # | вид обмена | кто | реплика | перевод | каркас · наполнение |
|---|---|---|---|---|---|
| 1 | вопрос ученика | Посетитель (ученик) | Could we have the bill? | Можно нам счёт? | p1 · the bill |
| 1 | вопрос ученика | Официант (собеседник) | Of course. I'll bring it now. | Конечно. Сейчас принесу. |  |
| 2 | ответ | Официант (собеседник) | Here you are. The total is forty-eight pounds. | Вот, пожалуйста. Итого сорок восемь фунтов. |  |
| 2 | ответ | Посетитель (ученик) | Okay, it's forty-eight pounds. | Хорошо, это сорок восемь фунтов. | p2 · forty-eight pounds |
| 3 | вопрос ученика | Посетитель (ученик) | Can I pay by card? | Можно оплатить картой? | p3 · by card |
| 3 | вопрос ученика | Официант (собеседник) | Yes, card is fine. You can tap here. | Да, картой можно. Можете приложить её здесь. |  |
| 4 | переспрос | Посетитель (ученик) | Could you say that more slowly, please? | Скажите, пожалуйста, помедленнее. | — |
| 4 | переспрос | Официант (собеседник) | Yes. You can tap your card here. | Да. Вы можете приложить карту здесь. |  |
| 5 | вопрос ученика | Посетитель (ученик) | Can I pay in cash? | Можно оплатить наличными? | p4 · in cash |
| 5 | вопрос ученика | Официант (собеседник) | Yes, cash is fine too. I can take it here. | Да, наличными тоже можно. Я могу принять оплату здесь. |  |
| 6 | вопрос ученика | Посетитель (ученик) | Could we pay on one bill? | Можно оплатить одним счётом? | p5 · one bill |
| 6 | вопрос ученика | Официант (собеседник) | Yes, that's no problem. One payment is fine. | Да, без проблем. Одним платежом можно. |  |
| 7 | ответ | Официант (собеседник) | Would you like to pay now? | Вы хотите оплатить сейчас? |  |
| 7 | ответ | Посетитель (ученик) | Yes, I'd like to pay now. | Да, я хочу оплатить сейчас. | p6 · now |
| 8 | ответ | Официант (собеседник) | Please tap your card. The payment is complete. | Пожалуйста, приложите карту. Оплата прошла. |  |
| 8 | ответ | Посетитель (ученик) | Thank you. | Спасибо. | p7 · Thank you |

### Каркасы

| id | вид | каркас | на родном | наполнения (в диалоге — жирным) |
|---|---|---|---|---|
| p1 | вопрос ученика | Could we have ___? | Можно нам ___? | **the bill** / счёт · the card reader / терминал · a receipt / чек |
| p2 | ответ | It's ___. | Это ___. | **forty-eight pounds** / сорок восемь фунтов · fifty-two pounds / пятьдесят два фунта · thirty-eight pounds / тридцать восемь фунтов |
| p3 | вопрос ученика | Can I pay ___? | Можно оплатить ___? | **by card** / картой · in cash / наличными |
| p4 | вопрос ученика | Can I pay ___? | Можно оплатить ___? | **in cash** / наличными · by card / картой |
| p5 | вопрос ученика | Could we pay on ___? | Можно оплатить ___? | **one bill** / одним счётом · two bills / двумя счетами · separate bills / раздельными счетами |
| p6 | ответ | I'd like to pay ___. | Я хочу оплатить ___ . | **now** / сейчас · in a minute / через минуту · after dessert / после десерта |
| p7 | ответ | ___ . | ___ . | **Thank you** / Спасибо · That's fine / Хорошо |
| p8 | ответ | One person will pay. | Платить будет один человек. | — |

### Проверки обменов

| # | вопрос | варианты (✓ — верный) |
|---|---|---|
| 1 | What does the waiter say he will do now? / Что официант говорит, что сейчас сделает? | ✓ Bring the check to the table / Принесёт счёт к столу · Pack the leftovers / Упакует еду с собой · Call another waiter / Позовёт другого официанта |
| 2 | How much is the total? / Какая итоговая сумма? | Fifty-two pounds / Пятьдесят два фунта · ✓ Forty-eight pounds / Сорок восемь фунтов · Thirty-eight pounds / Тридцать восемь фунтов |
| 3 | How does the waiter say you can pay? / Как официант говорит, можно оплатить? | ✓ With a contactless card at the machine / Бесконтактной картой на терминале · Only with cash at the counter / Только наличными у кассы · By bank transfer later / Банковским переводом позже |
| 4 | What does the waiter repeat? / Что повторяет официант? | ✓ Use your card on this reader / Приложите карту к этому терминалу · Sign the paper bill first / Сначала подпишите бумажный счёт · Pay in cash at the bar / Оплатите наличными у бара |
| 5 | What does the waiter say about cash? / Что официант говорит о наличных? | ✓ Cash also works at the table / Наличными тоже можно оплатить за столом · Cash is not accepted tonight / Сегодня наличные не принимают · Cash must go to the kitchen / Наличные нужно отнести на кухню |
| 6 | What payment arrangement does the waiter accept? / Какой вариант оплаты официант принимает? | ✓ A single payment for everyone / Один платёж за всех · Separate payments for each person / Отдельную оплату за каждого · Half now and half later / Половину сейчас и половину позже |
| 7 | When does the waiter ask about paying? / О каком времени оплаты спрашивает официант? | ✓ At this moment / Сейчас · Tomorrow morning / Завтра утром · After dessert / После десерта |
| 8 | What does the waiter say about the payment? / Что официант говорит об оплате? | ✓ It went through successfully / Она прошла успешно · It needs a signature / Нужна подпись · It was declined / Она отклонена |

### Слушаю весь визит

- L1. Какую сумму назвал официант? — ✓ Сорок восемь фунтов · Тридцать восемь фунтов · Пятьдесят два фунта
- L2. Как сначала хотел оплатить посетитель? — Наличными · ✓ Картой · Переводом
- L3. Как решили оплатить счёт? — Отдельно за каждого · ✓ Одним общим платежом · Половину сейчас, половину позже
- L4. Что сказал официант в конце? — Что нужна подпись · ✓ Что оплата прошла · Что терминал не работает

### Словарь

| id | слово | вид | перевод | где звучит |
|---|---|---|---|---|
| v1 | bill | слово | счёт | p1, p5 |
| v2 | total | слово | итоговая сумма | A2 |
| v3 | by card | связка | картой | p3, p4 |
| v4 | in cash | связка | наличными | p3, p4 |
| v5 | tap | слово | приложить | A3, A4, A8 |
| v6 | card reader | связка | терминал | p1 |
| v7 | one payment | связка | один платёж | A6 |
| v8 | receipt | слово | чек | p1 |

### Находки в ответе модели (до починок)

| код | порог | адрес | что |
|---|---|---|---|
| `frame.count` | предупреждение | lesson | 8 frames for 7 answer/ask exchanges (at most all of them) |
| `frame.twin` | предупреждение | p4 | «Can I pay ___?» / «Можно оплатить ___?» is the target pattern of p3 |
| `frame.twin` | предупреждение | p5 | «Could we pay on ___?» / «Можно оплатить ___?» is the native pattern of p3 |
| `frame.unused` | предупреждение | p8 | no learner line stands on «One person will pay.» |
| `frame.known_native_repeat` | предупреждение | p1 | the native «Можно нам ___?» is the native pattern of «Can we get ___?», learned on day 1 |
| `frame.known_native_repeat` | предупреждение | p2 | the native «Это ___.» is the native pattern of «That's ___.», learned on day 1 |

## День 2 — СТАЛО: `lesson_day.v4.6` (роли плана, EARLIER_DAYS = день 1)

Итог: **ready** · починок P2R: 1 (p2) · вызовов урока: 1 · цена дня $0.0740 (без скидки кэша $0.0919) · из кэша 56 % входа · 44 с · ученик: Родитель · собеседник: Официант (мужчина)

### Сценарий диалога

| # | вид обмена | кто | реплика | перевод | каркас · наполнение |
|---|---|---|---|---|---|
| 1 | вопрос ученика | Родитель (ученик) | Could we have the bill? | Можно нам счёт? | p1 · the bill |
| 1 | вопрос ученика | Официант (собеседник) | Of course. I'll bring it now. | Конечно. Сейчас принесу. |  |
| 2 | ответ | Официант (собеседник) | Here is your bill. The total is forty-eight pounds. | Вот ваш счёт. Итого сорок восемь фунтов. |  |
| 2 | ответ | Родитель (ученик) | Okay, it's forty-eight pounds altogether. | Хорошо, это сорок восемь фунтов. | p2 · forty-eight pounds |
| 3 | вопрос ученика | Родитель (ученик) | Can I pay by card? | Можно оплатить картой? | p3 · by card |
| 3 | вопрос ученика | Официант (собеседник) | Yes, card is fine. I can bring the card machine. | Да, картой можно. Я могу принести терминал. |  |
| 4 | вопрос ученика | Родитель (ученик) | Do you take cash? | Вы принимаете наличные? | p4 · cash |
| 4 | вопрос ученика | Официант (собеседник) | Yes, we take cash too. You can pay at the table. | Да, наличные тоже принимаем. Можете оплатить за столом. |  |
| 5 | ответ | Официант (собеседник) | If you pay by card, I'll bring the machine here. | Если будете платить картой, я принесу терминал сюда. |  |
| 5 | ответ | Родитель (ученик) | We'll pay by card. | Мы оплатим картой. | p5 · by card |
| 6 | переспрос | Родитель (ученик) | Sorry, could you say that again? | Извините, повторите, пожалуйста. | — |
| 6 | переспрос | Официант (собеседник) | I’ll bring the card machine here. | Я принесу терминал сюда. |  |
| 7 | ответ | Официант (собеседник) | Will one person pay, or are you splitting it? | Платит один человек или будете делить счёт? |  |
| 7 | ответ | Родитель (ученик) | One person will pay. | Оплатит один человек. | p6 · one person |
| 8 | ответ | Официант (собеседник) | All right. Please tap your card here. | Хорошо. Пожалуйста, приложите карту здесь. |  |
| 8 | ответ | Родитель (ученик) | Okay, I'll tap here. | Хорошо, я приложу здесь. | p7 · here |

### Каркасы

| id | вид | каркас | на родном | наполнения (в диалоге — жирным) |
|---|---|---|---|---|
| p1 | вопрос ученика | Could we have ___? | Можно нам ___? | **the bill** / счёт · the receipt / чек |
| p2 | ответ | It's ___ altogether. | Всего ___ . | **forty-eight pounds** / сорок восемь фунтов · fifty-two pounds / пятьдесят два фунта |
| p3 | вопрос ученика | Can I pay ___? | Можно оплатить ___? | **by card** / картой · in cash / наличными |
| p4 | вопрос ученика | Do you take ___? | Вы принимаете ___? | **cash** / наличные · cards / карты |
| p5 | ответ | We'll pay ___ . | Мы оплатим ___ . | **by card** / картой · in cash / наличными |
| p6 | ответ | ___ will pay. | Оплатит ___. | **one person** / один человек · my partner / мой партнёр |
| p7 | ответ | I'll tap ___. | Я приложу ___ . | **here** / здесь · there / там |

### Проверки обменов

| # | вопрос | варианты (✓ — верный) |
|---|---|---|
| 1 | What will the waiter do now? / Что официант сейчас сделает? | ✓ He will bring the check to the table. / Он принесёт счёт к столу. · He will pack the food to go. / Он упакует еду с собой. · He will bring the dessert menu. / Он принесёт меню десертов. |
| 2 | How much is the meal altogether? / Сколько всего стоит ужин? | Fifty-four pounds. / Пятьдесят четыре фунта. · ✓ Forty-eight pounds. / Сорок восемь фунтов. · Thirty-eight pounds. / Тридцать восемь фунтов. |
| 3 | What payment method does the waiter accept? / Какой способ оплаты принимает официант? | Only cash. / Только наличные. · ✓ A bank card. / Банковскую карту. · A phone transfer. / Перевод по телефону. |
| 4 | Where can the customer pay with cash? / Где клиент может заплатить наличными? | ✓ At the table. / За столом. · At the bar. / У бара. · At the front desk. / У стойки. |
| 5 | What will the waiter bring for card payment? / Что официант принесёт для оплаты картой? | A receipt folder. / Папку для счёта. · ✓ A card reader. / Терминал для карты. · A menu. / Меню. |
| 6 | Where will the waiter bring the machine? / Куда официант принесёт терминал? | ✓ To this table. / К этому столу. · To the kitchen. / На кухню. · To the door. / К двери. |
| 7 | What two payment options does the waiter ask about? / О каких двух вариантах оплаты спрашивает официант? | ✓ Paying together or dividing the amount. / Оплатить одному или разделить сумму. · Paying now or later. / Оплатить сейчас или позже. · Paying by card or by cash. / Оплатить картой или наличными. |
| 8 | What does the waiter ask the customer to do? / Что официант просит сделать клиента? | Sign the paper bill. / Подписать бумажный счёт. · ✓ Touch the reader with the card. / Приложить карту к терминалу. · Show an ID card. / Показать удостоверение. |

### Слушаю весь визит

- L1. Какую сумму назвал официант за весь ужин? — ✓ Сорок восемь фунтов · Тридцать восемь фунтов · Пятьдесят восемь фунтов
- L2. Как сказал родитель, чем они будут платить? — Наличными · ✓ Картой · Переводом
- L3. Что официант принесёт к столу для оплаты? — Десертное меню · ✓ Терминал · Сдачу
- L4. Как будет оплачен счёт? — Каждый платит за себя · ✓ Счёт оплатит один человек · Половину сейчас, половину позже

### Словарь

| id | слово | вид | перевод | где звучит |
|---|---|---|---|---|
| v1 | bill | слово | счёт | p1, A2 |
| v2 | total | слово | итоговая сумма | A2 |
| v3 | pounds | слово | фунты | p2 |
| v4 | pay by card | связка | платить картой | p3, p5, A5 |
| v5 | cash | слово | наличные | p4, A4 |
| v6 | card machine | связка | терминал | A3, A5, A6 |
| v7 | split the bill | связка | разделить счёт | A7 |
| v8 | tap | слово | приложить | p7, A8 |

### Находки в ответе модели (до починок)

| код | порог | адрес | что |
|---|---|---|---|
| `partner.two_questions` | предупреждение | A7 | «Will one person pay, or are you splitting it?» asks two things at once |
| `check.verbatim` | предупреждение | x4.check | the right option «At the table.» repeats «the table» of the partner's line |
| `vocab.used_in_wrong` | предупреждение | v6 | «card machine» is not in the partner's line of exchange 5 |
| `vocab.used_in_wrong` | предупреждение | v7 | «split the bill» is not in the partner's line of exchange 7 |
| `vocab.nested` | предупреждение | v1 | «bill» is inside «split the bill» (v7) |
| `frame.known_native_repeat` | предупреждение | p1 | the native «Можно нам ___?» is the native pattern of «Can we get ___?», learned on day 1 |
| `frame.known_repeat` | **фатальная** | p2 | «That's ___.» is the frame «That's ___.» the learner already learned on day 1 |
