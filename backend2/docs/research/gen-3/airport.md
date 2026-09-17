# GEN-3 · airport (ru→en, начальный)

Цель плана (слова ученика): «Регистрация на рейс в аэропорту: паспорт, багаж, место в самолёте. Лечу с одним чемоданом и рюкзаком»

Роль ученика в плане: Passenger / Пассажир. Сцена 1: «Стойка регистрации» (Check-in agent / Сотрудник регистрации); сцена 2: «Лишний багаж» (Check-in agent / Сотрудник регистрации).

> Каждый день — урок, каким его получил бы ученик (прошедший порог; при `failed` — ответ модели как написан). Находки — по ответу модели ДО починок, одним валидатором наряда и против одного и того же дня 1, так что «было» посчитано правилами, которых v4.5 не знал. Факты сюжета (цены, договорённости) и роли код не проверяет — их читает архитектор.

## День 1 — `lesson_day.v4.6`

Итог: **ready** · починок P2R: 0 · вызовов урока: 1 · цена дня $0.0591 (без скидки кэша $0.0770) · из кэша 85 % входа · 36 с · ученик: Пассажир · собеседник: Сотрудник регистрации (женщина)

### Сценарий диалога

| # | вид обмена | кто | реплика | перевод | каркас · наполнение |
|---|---|---|---|---|---|
| 1 | ответ | Сотрудник регистрации (собеседник) | Good morning. May I see your passport, please? | Доброе утро. Можно ваш паспорт, пожалуйста? |  |
| 1 | ответ | Пассажир (ученик) | Here is my passport. | Вот мой паспорт. | p1 · passport |
| 2 | ответ | Сотрудник регистрации (собеседник) | Thank you. Do you also have your booking confirmation? | Спасибо. У вас ещё есть подтверждение бронирования? |  |
| 2 | ответ | Пассажир (ученик) | Here is my booking confirmation. | Вот моё подтверждение бронирования. | p1 · booking confirmation |
| 3 | ответ | Сотрудник регистрации (собеседник) | How many bags do you have today? | Сколько у вас сегодня сумок? |  |
| 3 | ответ | Пассажир (ученик) | I have one suitcase and one backpack. | У меня один чемодан и один рюкзак. | p2 · one suitcase and one backpack |
| 4 | вопрос ученика | Пассажир (ученик) | I'd like to check this suitcase. | Я хочу сдать этот чемодан. | p3 · suitcase |
| 4 | вопрос ученика | Сотрудник регистрации (собеседник) | Sure. The backpack can stay with you. | Конечно. Рюкзак может остаться у вас. |  |
| 5 | вопрос ученика | Пассажир (ученик) | Can I have a window seat? | Можно мне место у окна? | p4 · a window seat |
| 5 | вопрос ученика | Сотрудник регистрации (собеседник) | Yes, seat 14A is by the window. | Да, место 14A у окна. |  |
| 6 | ответ | Сотрудник регистрации (собеседник) | Your flight leaves from gate 22. Boarding starts at 10:15. | Ваш рейс вылетает от выхода 22. Посадка начинается в 10:15. |  |
| 6 | ответ | Пассажир (ученик) | Okay, gate 22. | Хорошо, выход 22. | p5 · gate 22 |
| 7 | переспрос | Пассажир (ученик) | Sorry, could you say that more slowly? | Извините, можете сказать это помедленнее? | — |
| 7 | переспрос | Сотрудник регистрации (собеседник) | Gate 22. Boarding starts at 10:15. | Выход 22. Посадка начинается в 10:15. |  |
| 8 | вопрос ученика | Пассажир (ученик) | Can I have an aisle seat? | Можно мне место у прохода? | p4 · an aisle seat |
| 8 | вопрос ученика | Сотрудник регистрации (собеседник) | I'm sorry, only window seats are available now. | Извините, сейчас доступны только места у окна. |  |

### Каркасы

| id | вид | каркас | на родном | наполнения (в диалоге — жирным) |
|---|---|---|---|---|
| p1 | ответ | Here is my ___. | Вот ___. | **passport** / мой паспорт · **booking confirmation** / моё подтверждение бронирования · ticket / мой билет |
| p2 | ответ | I have ___. | У меня ___. | **one suitcase and one backpack** / один чемодан и один рюкзак · one suitcase / один чемодан · two backpacks / два рюкзака |
| p3 | вопрос ученика | I'd like to check this ___. | Я хочу сдать этот ___. | **suitcase** / чемодан · bag / сумку |
| p4 | вопрос ученика | Can I have ___? | Можно мне ___? | **a window seat** / место у окна · **an aisle seat** / место у прохода · a seat near the front / место ближе к началу салона |
| p5 | ответ | Okay, ___. | Хорошо, ___. | **gate 22** / выход 22 · gate 18 / выход 18 |

### Проверки обменов

| # | вопрос | варианты (✓ — верный) |
|---|---|---|
| 1 | What document does the agent ask for? / Какой документ просит сотрудница регистрации? | An ID card / Удостоверение личности · ✓ A passport / Паспорт · A boarding pass / Посадочный талон |
| 2 | What else does the agent want to see? / Что ещё хочет увидеть сотрудница регистрации? | Proof of travel insurance / Страховку для поездки · ✓ The booking record / Подтверждение бронирования · A luggage tag / Багажную бирку |
| 3 | How many items does the agent ask about? / О количестве чего спрашивает сотрудница регистрации? | Travel documents / Документов для поездки · ✓ Bags / Сумок · Seats / Мест в самолёте |
| 4 | Which bag can the passenger keep? / Какую сумку пассажир может оставить при себе? | ✓ The small carry-on bag / Рюкзак · The large checked case / Чемодан · Both bags / Обе сумки |
| 5 | Which seat does the agent give? / Какое место даёт сотрудница регистрации? | 12C / 12C · ✓ 14A / 14A · 16F / 16F |
| 6 | When does boarding begin? / Когда начинается посадка? | ✓ At a quarter past ten / В десять пятнадцать · At half past ten / В десять тридцать · At a quarter to ten / Без пятнадцати десять |
| 7 | What gate number does the agent repeat? / Какой номер выхода повторяет сотрудница регистрации? | Gate 18 / Выход 18 · Gate 20 / Выход 20 · ✓ Gate 22 / Выход 22 |
| 8 | Which seats are still available now? / Какие места сейчас ещё доступны? | ✓ Only seats next to the window / Только места у окна · Only seats by the aisle / Только места у прохода · Any seat in the front / Любые места впереди |

### Слушаю весь визит

- L1. Какой документ пассажир показал сначала? — Посадочный талон · ✓ Паспорт · Багажную бирку
- L2. Какой багаж пассажир сдаёт? — Рюкзак · ✓ Чемодан · Обе сумки
- L3. Какое место сначала просит пассажир? — ✓ У окна · У прохода · В начале салона
- L4. Во сколько начинается посадка? — ✓ В 10:15 · В 10:30 · В 9:45

### Словарь

| id | слово | вид | перевод | где звучит |
|---|---|---|---|---|
| v1 | passport | слово | паспорт | p1, A1 |
| v2 | booking confirmation | связка | подтверждение бронирования | p1, A2 |
| v3 | suitcase | слово | чемодан | p2, p3 |
| v4 | backpack | слово | рюкзак | p2, A4 |
| v5 | window seat | связка | место у окна | p4, A5, A8 |
| v6 | aisle seat | связка | место у прохода | p4 |
| v7 | gate | слово | выход на посадку | p5, A6, A7 |
| v8 | boarding pass | связка | посадочный талон | A1 |

### Находки в ответе модели (до починок)

| код | порог | адрес | что |
|---|---|---|---|
| `pronunciation.script` | предупреждение | p1.f1 | the reading «май паспoрт» leaves the native script |
| `pronunciation.script` | предупреждение | v1 | the reading «паспoрт» leaves the native script |
| `pronunciation.script` | предупреждение | B1 | the reading «Хиэр из май паспoрт.» leaves the native script |
| `pronunciation.script` | предупреждение | B7 | the reading «Сoри, куд ю сэй зэт мор слoули?» leaves the native script |
| `frame.adjacent_repeat` | предупреждение | x2 | exchanges 1 and 2 both stand on p1 |
| `frame.native_agreement` | предупреждение | p3 | «Я хочу сдать этот ___.»: «этот» agrees with the slot — it changes with the filler |
| `vocab.used_in_wrong` | предупреждение | v5 | «window seat» is not in the partner's line of exchange 5 |
| `vocab.used_in_wrong` | предупреждение | v5 | «window seat» is not in the partner's line of exchange 8 |
| `vocab.used_in_wrong` | предупреждение | v8 | «boarding pass» is not in the partner's line of exchange 1 |

## День 2 — БЫЛО: `lesson_day.v4.5`, код до наряда (без ролей и без EARLIER_DAYS)

Итог: **ready** · починок P2R: 0 · вызовов урока: 1 · цена дня $0.0665 (без скидки кэша $0.0821) · из кэша 78 % входа · 46 с · ученик: Пассажир · собеседник: Сотрудница регистрации (женщина)

### Сценарий диалога

| # | вид обмена | кто | реплика | перевод | каркас · наполнение |
|---|---|---|---|---|---|
| 1 | ответ | Сотрудница регистрации (собеседник) | Your suitcase is three kilos over the limit. | Ваш чемодан на три килограмма тяжелее нормы. |  |
| 1 | ответ | Пассажир (ученик) | It's over by three kilos. | Перевес — три килограмма. | p1 · three kilos |
| 2 | вопрос ученика | Пассажир (ученик) | How much is the extra fee? | Сколько стоит доплата? | p2 · the extra fee |
| 2 | вопрос ученика | Сотрудница регистрации (собеседник) | It's forty dollars for the extra weight. | Это сорок долларов за лишний вес. |  |
| 3 | вопрос ученика | Пассажир (ученик) | Can I put things in my backpack? | Я могу положить вещи в свой рюкзак? | p3 · my backpack |
| 3 | вопрос ученика | Сотрудница регистрации (собеседник) | Yes, if the backpack stays under eight kilos. | Да, если рюкзак будет весить меньше восьми килограммов. |  |
| 4 | вопрос ученика | Пассажир (ученик) | What can go in the cabin? | Что можно взять в салон? | p4 · the cabin |
| 4 | вопрос ученика | Сотрудница регистрации (собеседник) | Clothes and books can go in the cabin. | Одежду и книги можно взять в салон. |  |
| 5 | ответ | Сотрудница регистрации (собеседник) | Large liquids and sharp items must stay in checked baggage. | Большие жидкости и острые предметы должны остаться в сдаваемом багаже. |  |
| 5 | ответ | Пассажир (ученик) | They must stay in checked baggage. | Их нужно оставить в сдаваемом багаже. | p5 · checked baggage |
| 6 | переспрос | Пассажир (ученик) | Could you say that more slowly, please? | Скажите, пожалуйста, помедленнее. | — |
| 6 | переспрос | Сотрудница регистрации (собеседник) | Large liquids and sharp items stay in checked baggage. | Большие жидкости и острые предметы остаются в сдаваемом багаже. |  |
| 7 | ответ | Сотрудница регистрации (собеседник) | If you move three kilos, there is no extra fee. | Если вы переложите три килограмма, доплаты не будет. |  |
| 7 | ответ | Пассажир (ученик) | I'll move three kilos. | Я переложу три килограмма. | p6 · three kilos |
| 8 | вопрос ученика | Пассажир (ученик) | Can I keep this jacket with me? | Можно оставить эту куртку при себе? | p7 · this jacket |
| 8 | вопрос ученика | Сотрудница регистрации (собеседник) | Yes, you can wear it or carry it. | Да, вы можете надеть её или нести в руках. |  |

### Каркасы

| id | вид | каркас | на родном | наполнения (в диалоге — жирным) |
|---|---|---|---|---|
| p1 | ответ | It's over by ___. | Перевес — ___. | **three kilos** / три килограмма · two kilos / два килограмма · five kilos / пять килограммов |
| p2 | вопрос ученика | How much is ___? | Сколько стоит ___? | **the extra fee** / доплата · the second bag / второй багаж · priority boarding / приоритетная посадка |
| p3 | вопрос ученика | Can I put things in ___? | Я могу положить вещи в ___? | **my backpack** / свой рюкзак · this bag / эту сумку · my carry-on / ручную кладь |
| p4 | вопрос ученика | What can go in ___? | Что можно взять в ___? | **the cabin** / салон · my backpack / рюкзак · checked baggage / сдаваемый багаж |
| p5 | ответ | They must stay in ___. | Их нужно оставить в ___. | **checked baggage** / сдаваемом багаже · the suitcase / чемодане · the hold / багажном отсеке |
| p6 | ответ | I'll move ___. | Я переложу ___. | **three kilos** / три килограмма · some books / несколько книг · my shoes / свои ботинки |
| p7 | вопрос ученика | Can I keep ___ with me? | Можно оставить ___ при себе? | **this jacket** / эту куртку · this book / эту книгу · my laptop / мой ноутбук |

### Проверки обменов

| # | вопрос | варианты (✓ — верный) |
|---|---|---|
| 1 | How much over the limit is the suitcase? / Насколько чемодан превышает норму? | ✓ By three kilos / На три килограмма · By one kilo / На один килограмм · By five kilos / На пять килограммов |
| 2 | What price does the agent give? / Какую сумму называет сотрудница? | Thirty dollars / Тридцать долларов · ✓ Forty dollars / Сорок долларов · Fifty dollars / Пятьдесят долларов |
| 3 | What is the backpack limit? / Какой лимит для рюкзака? | Up to six kilos / До шести килограммов · ✓ Up to eight kilos / До восьми килограммов · Up to ten kilos / До десяти килограммов |
| 4 | Which items may stay with the passenger? / Какие вещи можно оставить при себе? | Liquids and scissors / Жидкости и ножницы · ✓ Clothes and books / Одежду и книги · Tools and spray cans / Инструменты и баллончики |
| 5 | Which things cannot go in the cabin? / Какие вещи нельзя брать в салон? | ✓ Big bottles and sharp objects / Большие флаконы и острые предметы · Jackets and magazines / Куртки и журналы · Phones and chargers / Телефоны и зарядки |
| 6 | What does the agent repeat? / Что сотрудница повторяет? | ✓ Sharp things and big liquids go below / Острые вещи и большие жидкости идут в багаж · Books and clothes stay with you / Книги и одежда остаются с вами · The backpack costs forty dollars / Рюкзак стоит сорок долларов |
| 7 | What removes the fee? / Что уберёт доплату? | Paying now at the desk / Оплата сразу на стойке · ✓ Taking out three kilos / Переложить три килограмма · Checking the backpack too / Сдать ещё и рюкзак |
| 8 | What does the agent allow with the jacket? / Что сотрудница разрешает сделать с курткой? | Leave it in the suitcase only / Оставить её только в чемодане · Put it inside the backpack only / Положить её только в рюкзак · ✓ Wear it or hold it / Надеть её или нести |

### Слушаю весь визит

- L1. На сколько килограммов чемодан был тяжелее нормы? — На один килограмм · ✓ На три килограмма · На пять килограммов
- L2. Сколько стоит доплата за лишний вес? — ✓ 40 долларов · 25 долларов · 60 долларов
- L3. Что пассажир решил сделать, чтобы не платить? — Сдать рюкзак тоже · ✓ Переложить три килограмма · Оставить всё как есть
- L4. Что можно взять в салон по словам сотрудницы? — ✓ Одежду и книги · Ножницы и большие жидкости · Инструменты и аэрозоли

### Словарь

| id | слово | вид | перевод | где звучит |
|---|---|---|---|---|
| v1 | over the limit | связка | сверх нормы | A1 |
| v2 | extra fee | связка | доплата | p2, A7 |
| v3 | extra weight | связка | лишний вес | A2 |
| v4 | backpack | слово | рюкзак | p3, A3, p4 |
| v5 | cabin | слово | салон самолёта | p4, A4 |
| v6 | checked baggage | связка | сдаваемый багаж | p5, A5, A6 |
| v7 | sharp item | связка | острый предмет | A5, A6 |
| v8 | move | слово | переложить | p6, A7 |

### Находки в ответе модели (до починок)

| код | порог | адрес | что |
|---|---|---|---|
| `variant.longer` | предупреждение | B7 | the variant «I'll take out three kilos.» has 5 words, the line 4 |
| `check.verbatim` | предупреждение | x4.check | the right option «Clothes and books» repeats «clothes and» of the partner's line |
| `check.verbatim` | предупреждение | x8.check | the right option «Wear it or hold it» repeats «wear it» of the partner's line |
| `vocab.used_in_wrong` | предупреждение | v7 | «sharp item» is not in the partner's line of exchange 5 |
| `vocab.used_in_wrong` | предупреждение | v7 | «sharp item» is not in the partner's line of exchange 6 |
| `vocab.known_repeat` | **фатальная** | v4 | «backpack» is a word the learner already learned on day 1 |

## День 2 — СТАЛО: `lesson_day.v4.6` (роли плана, EARLIER_DAYS = день 1)

Итог: **ready** · починок P2R: 0 · вызовов урока: 1 · цена дня $0.0638 (без скидки кэша $0.0817) · из кэша 82 % входа · 51 с · ученик: Пассажир · собеседник: Сотрудник регистрации (женщина)

### Сценарий диалога

| # | вид обмена | кто | реплика | перевод | каркас · наполнение |
|---|---|---|---|---|---|
| 1 | ответ | Сотрудник регистрации (собеседник) | Your suitcase is three kilos over the limit. | Ваш чемодан на три килограмма тяжелее нормы. |  |
| 1 | ответ | Пассажир (ученик) | It's over by three kilos. | Перевес на три килограмма. | p1 · three kilos |
| 2 | вопрос ученика | Пассажир (ученик) | How much is the extra fee? | Сколько стоит доплата? | p2 · the extra fee |
| 2 | вопрос ученика | Сотрудник регистрации (собеседник) | It's thirty dollars for up to five extra kilos. | Это тридцать долларов за перевес до пяти килограммов. |  |
| 3 | вопрос ученика | Пассажир (ученик) | Can this stay in the cabin? | Это можно оставить в салоне? | p3 · in the cabin |
| 3 | вопрос ученика | Сотрудник регистрации (собеседник) | Yes, your backpack can stay with you in the cabin. | Да, ваш рюкзак может остаться с вами в салоне. |  |
| 4 | ответ | Сотрудник регистрации (собеседник) | You can move some items from the suitcase to the backpack. | Вы можете переложить часть вещей из чемодана в рюкзак. |  |
| 4 | ответ | Пассажир (ученик) | I'll move some items. | Я переложу несколько вещей. | p4 · some items |
| 5 | вопрос ученика | Пассажир (ученик) | Can I keep my laptop with me? | Можно мне оставить ноутбук при себе? | p5 · my laptop |
| 5 | вопрос ученика | Сотрудник регистрации (собеседник) | Yes, keep your laptop and charger in the backpack. | Да, оставьте ноутбук и зарядку в рюкзаке. |  |
| 6 | переспрос | Пассажир (ученик) | Sorry, could you say that more slowly? | Извините, можно помедленнее? | — |
| 6 | переспрос | Сотрудник регистрации (собеседник) | Keep the laptop and charger in the backpack. | Оставьте ноутбук и зарядку в рюкзаке. |  |
| 7 | ответ | Сотрудник регистрации (собеседник) | Liquids over one hundred milliliters must be checked. | Жидкости больше ста миллилитров нужно сдавать в багаж. |  |
| 7 | ответ | Пассажир (ученик) | I'll check my shampoo. | Я сдам свой шампунь в багаж. | p6 · my shampoo |
| 8 | ответ | Сотрудник регистрации (собеседник) | That works. Your suitcase is within the limit now. | Так подходит. Теперь ваш чемодан в пределах нормы. |  |
| 8 | ответ | Пассажир (ученик) | Great, it's within the limit. | Отлично, теперь всё в пределах нормы. | p7 · within the limit |

### Каркасы

| id | вид | каркас | на родном | наполнения (в диалоге — жирным) |
|---|---|---|---|---|
| p1 | ответ | It's over by ___. | Перевес на ___. | **three kilos** / три килограмма · two kilos / два килограмма |
| p2 | вопрос ученика | How much is ___? | Сколько стоит ___? | **the extra fee** / доплата · the second bag / второй багаж |
| p3 | вопрос ученика | Can this stay ___? | Это можно оставить ___? | **in the cabin** / в салоне · with me / при себе |
| p4 | ответ | I'll move ___. | Я переложу ___. | **some items** / несколько вещей · a few clothes / немного одежды |
| p5 | вопрос ученика | Can I keep ___ with me? | Можно мне оставить ___ при себе? | **my laptop** / ноутбук · my medicine / лекарство |
| p6 | ответ | I'll check ___. | Я сдам в багаж ___. | **my shampoo** / свой шампунь · my water bottle / свою бутылку воды |
| p7 | ответ | It's ___. | Теперь всё ___. | **within the limit** / в пределах нормы · fine now / в порядке |

### Проверки обменов

| # | вопрос | варианты (✓ — верный) |
|---|---|---|
| 1 | How much is the suitcase over? / Насколько чемодан тяжелее нормы? | By one kilo. / На один килограмм. · ✓ By three kilos. / На три килограмма. · By five kilos. / На пять килограммов. |
| 2 | What charge does the agent give? / Какую сумму назвала сотрудница? | Twenty dollars. / Двадцать долларов. · ✓ Thirty dollars. / Тридцать долларов. · Fifty dollars. / Пятьдесят долларов. |
| 3 | What does the agent say about the backpack? / Что сотрудница сказала про рюкзак? | It must go under the plane. / Его нужно сдать в багаж. · ✓ It can stay with the passenger. / Его можно оставить при себе. · It has to be weighed again. / Его нужно снова взвесить. |
| 4 | What does the agent suggest moving? / Что сотрудница предлагает переложить? | ✓ Some things into the backpack. / Несколько вещей в рюкзак. · Travel papers into a pocket. / Документы в карман. · Shoes into another suitcase. / Обувь в другой чемодан. |
| 5 | Which two things should stay in the backpack? / Какие две вещи должны остаться в рюкзаке? | ✓ A laptop and its charger. / Ноутбук и зарядка. · A jacket and a book. / Куртка и книга. · Toothpaste and keys. / Зубная паста и ключи. |
| 6 | What should the passenger keep with them? / Что пассажиру нужно оставить при себе? | ✓ A laptop and charger. / Ноутбук и зарядку. · A coat and passport. / Пальто и паспорт. · A wallet and headphones. / Кошелёк и наушники. |
| 7 | Which item rule does the agent mention? / Какое правило про вещи назвала сотрудница? | ✓ Large liquids cannot stay in the cabin. / Большие ёмкости с жидкостью нельзя брать в салон. · All electronics must go under the plane. / Всю электронику нужно сдавать в багаж. · Food is not allowed in backpacks. / Еду нельзя класть в рюкзаки. |
| 8 | What is the baggage situation now? / Какая теперь ситуация с багажом? | ✓ The bag now meets the allowed weight. / Багаж теперь соответствует допустимому весу. · The bag still needs another payment. / За багаж всё ещё нужно доплатить. · The bag must be opened again. / Багаж нужно снова открыть. |

### Слушаю весь визит

- L1. Насколько чемодан был тяжелее нормы? — ✓ На три килограмма · На один килограмм · На пять килограммов
- L2. Сколько стоит доплата за перевес? — Двадцать долларов · ✓ Тридцать долларов · Пятьдесят долларов
- L3. Что пассажир решил оставить при себе? — ✓ Ноутбук · Бутылку воды · Шампунь
- L4. Что в итоге стало с чемоданом? — Его всё равно нужно было оплачивать · ✓ Он оказался в пределах нормы · Его нельзя было сдавать

### Словарь

| id | слово | вид | перевод | где звучит |
|---|---|---|---|---|
| v1 | overweight | слово | с перевесом | A1 |
| v2 | extra fee | связка | доплата | p2, A2 |
| v3 | cabin | слово | салон самолёта | p3, A3 |
| v4 | move | слово | переложить | p4, A4 |
| v5 | charger | слово | зарядка | A5, A6 |
| v6 | liquids | слово | жидкости | A7 |
| v7 | milliliters | слово | миллилитры | A7 |
| v8 | within the limit | связка | в пределах нормы | p7, A8 |

### Находки в ответе модели (до починок)

| код | порог | адрес | что |
|---|---|---|---|
| `variant.longer` | предупреждение | B7 | the variant «My shampoo goes in checked baggage.» has 6 words, the line 4 |
| `variant.longer` | предупреждение | B8 | the variant «Okay, it's under the limit now.» has 6 words, the line 5 |
| `check.verbatim` | предупреждение | x3.check | the right option «It can stay with the passenger.» repeats «can stay» of the partner's line |
| `check.verbatim` | предупреждение | x4.check | the right option «Some things into the backpack.» repeats «the backpack» of the partner's line |
| `vocab.used_in_wrong` | предупреждение | v1 | «overweight» is not in the partner's line of exchange 1 |
| `vocab.used_in_wrong` | предупреждение | v2 | «extra fee» is not in the partner's line of exchange 2 |
