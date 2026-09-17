# GEN-3 · bank (ru→en, начальный)

Цель плана (слова ученика): «Открываю счёт и банковскую карту в банке. Я студент, приехал учиться на год»

Роль ученика в плане: Student / Студент. Сцена 1: «Открытие счёта» (Bank clerk / Сотрудник банка); сцена 2: «Банковская карта» (Bank clerk / Сотрудник банка).

> Каждый день — урок, каким его получил бы ученик (прошедший порог; при `failed` — ответ модели как написан). Находки — по ответу модели ДО починок, одним валидатором наряда и против одного и того же дня 1, так что «было» посчитано правилами, которых v4.5 не знал. Факты сюжета (цены, договорённости) и роли код не проверяет — их читает архитектор.

## День 1 — `lesson_day.v4.6`

Итог: **ready** · починок P2R: 0 · вызовов урока: 1 · цена дня $0.0975 (без скидки кэша $0.1154) · из кэша 81 % входа · 57 с · ученик: Студент · собеседник: Сотрудник банка (женщина)

### Сценарий диалога

| # | вид обмена | кто | реплика | перевод | каркас · наполнение |
|---|---|---|---|---|---|
| 1 | ответ | Сотрудник банка (собеседник) | Hello. How can I help you today? | Здравствуйте. Чем я могу вам помочь сегодня? |  |
| 1 | ответ | Студент (ученик) | I’d like to open a student account. | Я хочу открыть студенческий счёт. | p1 · a student account |
| 2 | ответ | Сотрудник банка (собеседник) | What do you need the account for? | Для чего вам нужен счёт? |  |
| 2 | ответ | Студент (ученик) | I need it for my studies. | Он нужен мне для учёбы. | p2 · my studies |
| 3 | ответ | Сотрудник банка (собеседник) | How long will you stay here? | Как долго вы будете здесь? |  |
| 3 | ответ | Студент (ученик) | I’ll stay for one year. | Я буду здесь один год. | p3 · one year |
| 4 | ответ | Сотрудник банка (собеседник) | Please show me your passport. | Пожалуйста, покажите мне ваш паспорт. |  |
| 4 | ответ | Студент (ученик) | Here is my passport. | Вот мой паспорт. | p4 · passport |
| 5 | ответ | Сотрудник банка (собеседник) | I also need your visa and proof of address. | Мне также нужны ваша виза и подтверждение адреса. |  |
| 5 | ответ | Студент (ученик) | I have my visa. | У меня есть виза. | p5 · my visa |
| 6 | вопрос ученика | Студент (ученик) | Do you need a student letter? | Вам нужно письмо из учебного заведения? | p6 · a student letter |
| 6 | вопрос ученика | Сотрудник банка (собеседник) | Yes, please. I need it for the student account. | Да, пожалуйста. Оно нужно для студенческого счёта. |  |
| 7 | вопрос ученика | Студент (ученик) | Can I get a bank card? | Я могу получить банковскую карту? | p7 · a bank card |
| 7 | вопрос ученика | Сотрудник банка (собеседник) | Yes. A debit card comes with this account. | Да. К этому счёту идёт дебетовая карта. |  |
| 8 | вопрос ученика | Студент (ученик) | Are there any monthly fees? | Есть ли ежемесячные комиссии? | p8 · any monthly fees |
| 8 | вопрос ученика | Сотрудник банка (собеседник) | No. This student account has no monthly fee. | Нет. У этого студенческого счёта нет ежемесячной комиссии. |  |

### Каркасы

| id | вид | каркас | на родном | наполнения (в диалоге — жирным) |
|---|---|---|---|---|
| p1 | ответ | I’d like to open ___. | Я хочу открыть ___. | **a student account** / студенческий счёт · a bank account / банковский счёт · a joint account / совместный счёт |
| p2 | ответ | I need it for ___. | Он нужен мне для ___. | **my studies** / учёбы · rent payments / оплаты аренды · daily expenses / повседневных расходов |
| p3 | ответ | I’ll stay for ___. | Я буду здесь ___. | **one year** / один год · six months / шесть месяцев · two semesters / два семестра |
| p4 | ответ | Here is my ___. | Вот ___. | **passport** / мой паспорт · visa / моя виза · student letter / моё письмо из вуза |
| p5 | ответ | I have ___. | У меня есть ___. | **my visa** / виза · proof of address / подтверждение адреса · my student letter / письмо из вуза |
| p6 | вопрос ученика | Do you need ___? | Вам нужно ___? | **a student letter** / письмо из учебного заведения · proof of address / подтверждение адреса · my visa / мою визу |
| p7 | вопрос ученика | Can I get ___? | Я могу получить ___? | **a bank card** / банковскую карту · a debit card / дебетовую карту · a paper statement / бумажную выписку |
| p8 | вопрос ученика | Are there ___? | Есть ли ___? | **any monthly fees** / ежемесячные комиссии · any card fees / комиссии за карту · any account charges / комиссии по счёту |

### Проверки обменов

| # | вопрос | варианты (✓ — верный) |
|---|---|---|
| 1 | What does the bank clerk offer to do? / Что предлагает сделать сотрудница банка? | Explain today’s exchange rate / Объяснить сегодняшний курс · ✓ Help with the visit / Помочь с визитом · Print a bank card / Распечатать банковскую карту |
| 2 | What does the clerk ask about? / О чём спрашивает сотрудница? | ✓ The reason for the account / Причину открытия счёта · The learner’s home address / Домашний адрес ученика · The card delivery time / Срок доставки карты |
| 3 | What time period does the clerk ask about? / О каком сроке спрашивает сотрудница? | How often the learner studies / Как часто ученик учится · ✓ How long the learner will remain / Как долго ученик останется · When the learner arrived today / Когда ученик пришёл сегодня |
| 4 | Which document does the clerk ask to see? / Какой документ просит показать сотрудница? | A student card / Студенческий билет · ✓ A passport / Паспорт · A bank statement / Банковскую выписку |
| 5 | What two things does the clerk say she needs? / Какие две вещи говорит, что ей нужны, сотрудница? | Travel insurance and a train ticket / Страховка и билет на поезд · ✓ A visa and address proof / Виза и подтверждение адреса · A passport photo and cash / Фото на паспорт и наличные |
| 6 | Why does the clerk need that letter? / Зачем сотруднице нужно это письмо? | To send the card home / Чтобы отправить карту домой · ✓ To check the student account type / Чтобы оформить студенческий тип счёта · To change the visa dates / Чтобы изменить даты визы |
| 7 | What kind of card comes with the account? / Какая карта идёт к этому счёту? | A student ID card / Студенческая карта · A credit card / Кредитная карта · ✓ A debit card / Дебетовая карта |
| 8 | What does the clerk say about regular charges? / Что сотрудница говорит о регулярных списаниях? | ✓ They are not charged each month / Их не списывают каждый месяц · They are taken once a week / Их списывают раз в неделю · They are paid in cash today / Их нужно оплатить наличными сегодня |

### Слушаю весь визит

- L1. Какой счёт хочет открыть студент? — Совместный счёт · ✓ Студенческий счёт · Бизнес-счёт
- L2. На какой срок студент приехал? — ✓ На один год · На шесть месяцев · На два года
- L3. Какой документ сотрудница просит дополнительно к паспорту? — Страховку · ✓ Визу · Билет
- L4. Что сотрудница говорит о карте? — Её нужно заказывать отдельно · ✓ К счёту идёт дебетовая карта · Карту нельзя получить студенту
- L5. Что сотрудница говорит о ежемесячной комиссии? — Она есть каждую неделю · Её нужно оплатить сегодня · ✓ Её нет

### Словарь

| id | слово | вид | перевод | где звучит |
|---|---|---|---|---|
| v1 | student account | связка | студенческий счёт | p1, A6, A8 |
| v2 | studies | слово | учёба | p2 |
| v3 | passport | слово | паспорт | A4, p4 |
| v4 | visa | слово | виза | A5, p5 |
| v5 | proof of address | связка | подтверждение адреса | A5, p5, p6 |
| v6 | student letter | связка | письмо из учебного заведения | p4, p5, p6 |
| v7 | debit card | связка | дебетовая карта | A7, p7 |
| v8 | monthly fee | связка | ежемесячная комиссия | p8, A8 |

### Находки в ответе модели (до починок)

| код | порог | адрес | что |
|---|---|---|---|
| `frame.unresolved_pronoun` | предупреждение | p2 | «I need it for ___.» leans on «it», and nothing in the frame is what it stands for |
| `check.verbatim` | предупреждение | x3.check | the right option «How long the learner will remain» repeats «how long» of the partner's line |
| `vocab.used_in_wrong` | предупреждение | v8 | «monthly fee» is not in frame p8 or its fillers |

## День 2 — БЫЛО: `lesson_day.v4.5`, код до наряда (без ролей и без EARLIER_DAYS)

Итог: **ready** · починок P2R: 0 · вызовов урока: 1 · цена дня $0.0625 (без скидки кэша $0.0780) · из кэша 81 % входа · 41 с · ученик: Студент, клиент банка · собеседник: Сотрудница банка (женщина)

### Сценарий диалога

| # | вид обмена | кто | реплика | перевод | каркас · наполнение |
|---|---|---|---|---|---|
| 1 | вопрос ученика | Студент, клиент банка (ученик) | When will my card arrive? | Когда придёт моя карта? | p1 · my card |
| 1 | вопрос ученика | Сотрудница банка (собеседник) | It will arrive by mail in five business days. | Она придёт по почте через пять рабочих дней. |  |
| 2 | вопрос ученика | Студент, клиент банка (ученик) | Can I use the app? | Я могу пользоваться приложением? | p2 · the app |
| 2 | вопрос ученика | Сотрудница банка (собеседник) | Yes, you can register in the app today. | Да, вы можете зарегистрироваться в приложении сегодня. |  |
| 3 | ответ | Сотрудница банка (собеседник) | First, download the app and make a login. | Сначала скачайте приложение и создайте вход. |  |
| 3 | ответ | Студент, клиент банка (ученик) | Okay, I'll download the app. | Хорошо, я скачаю приложение. | p3 · the app |
| 4 | вопрос ученика | Студент, клиент банка (ученик) | How do I get my PIN? | Как мне получить ПИН-код? | p4 · my PIN |
| 4 | вопрос ученика | Сотрудница банка (собеседник) | You choose it in the app after activation. | Вы выбираете его в приложении после активации. |  |
| 5 | вопрос ученика | Студент, клиент банка (ученик) | How do I activate the card? | Как мне активировать карту? | p5 · the card |
| 5 | вопрос ученика | Сотрудница банка (собеседник) | Open the app, enter the card number, and confirm the code we send. | Откройте приложение, введите номер карты и подтвердите код, который мы отправим. |  |
| 6 | переспрос | Студент, клиент банка (ученик) | Could you say that more slowly, please. | Скажите, пожалуйста, это помедленнее. | — |
| 6 | переспрос | Сотрудница банка (собеседник) | Open the app, enter the card number, then confirm our code. | Откройте приложение, введите номер карты, потом подтвердите наш код. |  |
| 7 | вопрос ученика | Студент, клиент банка (ученик) | Can I withdraw cash? | Я могу снимать наличные? | p6 · cash |
| 7 | вопрос ученика | Сотрудница банка (собеседник) | Yes, from any bank ATM. The daily limit is three hundred euros. | Да, в любом банкомате банка. Дневной лимит — триста евро. |  |
| 8 | ответ | Сотрудница банка (собеседник) | Your card will work for shops and online payments after activation. | После активации ваша карта будет работать в магазинах и для онлайн-платежей. |  |
| 8 | ответ | Студент, клиент банка (ученик) | Okay, I'll use it for online payments. | Хорошо, я буду использовать её для онлайн-платежей. | p7 · online payments |

### Каркасы

| id | вид | каркас | на родном | наполнения (в диалоге — жирным) |
|---|---|---|---|---|
| p1 | вопрос ученика | When will ___ arrive? | Когда придёт ___? | **my card** / моя карта · the PIN letter / письмо с ПИН-кодом |
| p2 | вопрос ученика | Can I use ___? | Я могу пользоваться ___? | **the app** / приложением · the ATM / банкоматом |
| p3 | ответ | I'll download ___. | Я скачаю ___. | **the app** / приложение · the bank guide / инструкцию банка |
| p4 | вопрос ученика | How do I get ___? | Как мне получить ___? | **my PIN** / мой ПИН-код · the code / код |
| p5 | вопрос ученика | How do I activate ___? | Как мне активировать ___? | **the card** / карту · the app / приложение |
| p6 | вопрос ученика | Can I withdraw ___? | Я могу снимать ___? | **cash** / наличные · euros / евро |
| p7 | ответ | I'll use it for ___. | Я буду использовать её для ___. | **online payments** / онлайн-платежей · shop payments / платежей в магазинах |

### Проверки обменов

| # | вопрос | варианты (✓ — верный) |
|---|---|---|
| 1 | How will the card get to the learner? / Как карта попадёт к ученику? | It will be picked up at the desk. / Её нужно будет забрать у стойки. · ✓ It will come to the address by post. / Её пришлют по адресу почтой. · It will be sent by email. / Её отправят по электронной почте. |
| 2 | When can the learner sign up in the app? / Когда ученик может зарегистрироваться в приложении? | ✓ Right away at the branch. / Сразу, в отделении. · After the card expires. / После окончания срока карты. · In about a week. / Примерно через неделю. |
| 3 | What does the clerk say to do first? / Что сотрудница говорит сделать сначала? | Set a new withdrawal limit. / Поставить новый лимит на снятие. · ✓ Install the phone application. / Установить приложение на телефон. · Call customer support. / Позвонить в поддержку. |
| 4 | Where does the learner choose the PIN? / Где ученик выбирает ПИН-код? | On a paper form at home. / На бумажной форме дома. · ✓ Inside the mobile application. / В мобильном приложении. · At the cash desk. / У кассы. |
| 5 | What must the learner enter in the app? / Что ученику нужно ввести в приложении? | ✓ The card number. / Номер карты. · The branch address. / Адрес отделения. · The study program. / Программу обучения. |
| 6 | What does the clerk say to confirm after entering the card number? / Что сотрудница говорит подтвердить после ввода номера карты? | ✓ A code from the bank. / Код от банка. · The card design. / Дизайн карты. · A cash amount. / Сумму наличных. |
| 7 | What daily amount does the clerk mention? / Какую дневную сумму называет сотрудница? | One hundred euros. / Сто евро. · Five hundred euros. / Пятьсот евро. · ✓ Three hundred euros. / Триста евро. |
| 8 | Where can the card be used after activation? / Где можно использовать карту после активации? | Only at the branch counter. / Только у стойки в отделении. · ✓ In stores and on the internet. / В магазинах и в интернете. · Only for train tickets. / Только для билетов на поезд. |

### Слушаю весь визит

- L1. Через сколько придёт карта? — ✓ Через пять рабочих дней · Через один день · Через две недели
- L2. Где ученик выбирает ПИН-код? — ✓ В мобильном приложении · У сотрудницы на стойке · В письме по почте
- L3. Что ученик спросил про снятие денег? — ✓ Можно ли снимать наличные · Можно ли закрыть счёт · Можно ли поменять адрес
- L4. Какой дневной лимит на снятие наличных назвала сотрудница? — ✓ Триста евро · Пятьдесят евро · Тысяча евро

### Словарь

| id | слово | вид | перевод | где звучит |
|---|---|---|---|---|
| v1 | by mail | связка | по почте | A1 |
| v2 | business days | связка | рабочие дни | A1 |
| v3 | register | слово | зарегистрироваться | A2 |
| v4 | PIN | слово | ПИН-код | p4 |
| v5 | activate | слово | активировать | p5, A4 |
| v6 | card number | связка | номер карты | A5, A6 |
| v7 | withdraw | слово | снимать наличные | p6 |
| v8 | online payments | связка | онлайн-платежи | p7, A8 |

### Находки в ответе модели (до починок)

| код | порог | адрес | что |
|---|---|---|---|
| `frame.unresolved_pronoun` | предупреждение | p7 | «I'll use it for ___.» leans on «it», and nothing in the frame is what it stands for |
| `variant.longer` | предупреждение | B2 | the variant «Can I use it on my phone?» has 7 words, the line 5 |
| `listening.same_exchange` | предупреждение | L4 | L3 and this question are both about exchange 7 |
| `vocab.abbreviation` | **фатальная** | v4 | «PIN» is an abbreviation or an acronym — there is nothing to translate |

## День 2 — СТАЛО: `lesson_day.v4.6` (роли плана, EARLIER_DAYS = день 1)

Итог: **ready** · починок P2R: 2 (v5, v7) · вызовов урока: 1 · цена дня $0.0833 (без скидки кэша $0.1011) · из кэша 47 % входа · 44 с · ученик: Студент · собеседник: Сотрудник банка (женщина)

### Сценарий диалога

| # | вид обмена | кто | реплика | перевод | каркас · наполнение |
|---|---|---|---|---|---|
| 1 | вопрос ученика | Студент (ученик) | When will my card arrive? | Когда придёт моя карта? | p1 · my card |
| 1 | вопрос ученика | Сотрудник банка (собеседник) | It usually arrives in five to seven business days. | Обычно она приходит через пять-семь рабочих дней. |  |
| 2 | вопрос ученика | Студент (ученик) | Does it come by mail? | Она приходит по почте? | p2 · by mail |
| 2 | вопрос ученика | Сотрудник банка (собеседник) | Yes, we send it to your address by mail. | Да, мы отправляем её по почте на ваш адрес. |  |
| 3 | ответ | Сотрудник банка (собеседник) | You need to activate it in the mobile app. | Вам нужно активировать её в мобильном приложении. |  |
| 3 | ответ | Студент (ученик) | I can use the mobile app. | Я могу пользоваться мобильным приложением. | p3 · the mobile app |
| 4 | вопрос ученика | Студент (ученик) | How do I get my PIN? | Как мне получить ПИН-код? | p4 · my PIN |
| 4 | вопрос ученика | Сотрудник банка (собеседник) | You choose it in the app after activation. | Вы выбираете его в приложении после активации. |  |
| 5 | ответ | Сотрудник банка (собеседник) | For the first use, pay once in a shop with the PIN. | Для первого использования один раз оплатите покупку в магазине с ПИН-кодом. |  |
| 5 | ответ | Студент (ученик) | I can pay in a shop. | Я могу оплатить в магазине. | p5 · in a shop |
| 6 | переспрос | Студент (ученик) | Could you repeat that more slowly? | Можете повторить это помедленнее? | — |
| 6 | переспрос | Сотрудник банка (собеседник) | Use the card once in a shop, and enter your PIN. | Один раз используйте карту в магазине и введите ПИН-код. |  |
| 7 | вопрос ученика | Студент (ученик) | Can I withdraw cash? | Я могу снимать наличные? | p6 · cash |
| 7 | вопрос ученика | Сотрудник банка (собеседник) | Yes, you can use any ATM with the Visa sign. | Да, вы можете использовать любой банкомат со знаком Visa. |  |
| 8 | ответ | Сотрудник банка (собеседник) | The daily cash withdrawal limit is three hundred euros. | Дневной лимит снятия наличных — триста евро. |  |
| 8 | ответ | Студент (ученик) | The limit is three hundred euros. | Лимит — триста евро. | p7 · three hundred euros |

### Каркасы

| id | вид | каркас | на родном | наполнения (в диалоге — жирным) |
|---|---|---|---|---|
| p1 | вопрос ученика | When will ___ arrive? | Когда придёт ___? | **my card** / моя карта · the PIN letter / письмо с ПИН-кодом |
| p2 | вопрос ученика | Does it come ___? | Она приходит ___? | **by mail** / по почте · by courier / курьером |
| p3 | ответ | I can use ___. | Я могу пользоваться ___. | **the mobile app** / мобильным приложением · online banking / интернет-банком |
| p4 | вопрос ученика | How do I get ___? | Как мне получить ___? | **my PIN** / ПИН-код · the activation code / код активации |
| p5 | ответ | I can pay ___. | Я могу оплатить ___. | **in a shop** / в магазине · at the cafeteria / в столовой |
| p6 | вопрос ученика | Can I withdraw ___? | Я могу снимать ___? | **cash** / наличные · euros / евро |
| p7 | ответ | The limit is ___. | Лимит — ___. | **three hundred euros** / триста евро · five hundred euros / пятьсот евро |

### Проверки обменов

| # | вопрос | варианты (✓ — верный) |
|---|---|---|
| 1 | How long does the clerk say delivery usually takes? / Сколько обычно занимает доставка, по словам сотрудницы? | ✓ About one working week / Около одной рабочей недели · The same afternoon / В тот же день после обеда · Around two full weeks / Примерно две полные недели |
| 2 | Where does the clerk say the card is sent? / Куда, по словам сотрудницы, отправляют карту? | ✓ To your address / На ваш адрес · To your university office / В офис вашего университета · To this branch for pickup / В это отделение для получения |
| 3 | How does the clerk say you activate the card? / Как, по словам сотрудницы, активировать карту? | ✓ Through the bank's app / Через банковское приложение · By calling customer support / Позвонив в поддержку · At the front desk only / Только у стойки в отделении |
| 4 | When does the clerk say you choose the PIN? / Когда, по словам сотрудницы, выбирают ПИН-код? | ✓ After the card is activated / После активации карты · Before the card is mailed / До отправки карты по почте · At the first cash withdrawal / При первом снятии наличных |
| 5 | What first use does the clerk recommend? / Какое первое действие с картой рекомендует сотрудница? | ✓ Make one store payment with the code / Один раз оплатить покупку в магазине с кодом · Transfer money to another account / Перевести деньги на другой счёт · Use contactless payment three times / Три раза оплатить бесконтактно |
| 6 | Where does the clerk say to use the card first? / Где, по словам сотрудницы, сначала нужно использовать карту? | ✓ At a store checkout / На кассе в магазине · At an ATM outside / У банкомата на улице · In the mobile app / В мобильном приложении |
| 7 | Which ATMs does the clerk say you can use? / Какими банкоматами, по словам сотрудницы, можно пользоваться? | ✓ Machines showing the Visa logo / Банкоматами со знаком Visa · Only this bank's indoor machines / Только банкоматами этого банка в помещении · Only airport ATMs / Только банкоматами в аэропорту |
| 8 | What daily amount does the clerk mention? / Какую сумму в день назвала сотрудница? | ✓ Three hundred euros / Триста евро · One hundred euros / Сто евро · Five hundred euros / Пятьсот евро |

### Слушаю весь визит

- L1. Через сколько обычно приходит карта? — ✓ Через пять–семь рабочих дней · Через две недели · В тот же день
- L2. Как студенту сказали активировать карту? — ✓ В мобильном приложении · По телефону · В университете
- L3. О чём студент спросил сотрудницу? — ✓ Можно ли снимать наличные · Можно ли открыть второй счёт · Нужен ли паспорт
- L4. Какой дневной лимит снятия наличных назвала сотрудница? — ✓ Триста евро · Сто евро · Пятьсот евро

### Словарь

| id | слово | вид | перевод | где звучит |
|---|---|---|---|---|
| v1 | business days | связка | рабочие дни | A1 |
| v2 | by mail | связка | по почте | p2, A2 |
| v3 | mobile app | связка | мобильное приложение | A3, p3 |
| v4 | activate | слово | активировать | A3, A4 |
| v5 | activation code | связка | код активации | p4 |
| v6 | withdraw cash | связка | снимать наличные | p6 |
| v7 | Visa sign | связка | знак Visa | A7 |
| v8 | withdrawal limit | связка | лимит снятия | A8, p7 |

### Находки в ответе модели (до починок)

| код | порог | адрес | что |
|---|---|---|---|
| `frame.unresolved_pronoun` | предупреждение | p2 | «Does it come ___?» leans on «it», and nothing in the frame is what it stands for |
| `learner.restates_partner` | предупреждение | B8 | «The limit is three hundred euros.» repeats 6 of 9 words of the partner's «The daily cash withdrawal limit is three hundred euros.» (the, limit, is, three, hundred, euros) |
| `rescue.new_fact` | предупреждение | A6 | the repeat says «card», «enter», which exchange 5 did not |
| `check.verbatim` | предупреждение | x2.check | the right option «To your address» repeats «your address» of the partner's line |
| `vocab.abbreviation` | **фатальная** | v5 | «PIN» is an abbreviation or an acronym — there is nothing to translate |
| `vocab.abbreviation` | **фатальная** | v7 | «ATM» is an abbreviation or an acronym — there is nothing to translate |
| `vocab.used_in_wrong` | предупреждение | v8 | «withdrawal limit» is not in frame p7 or its fillers |
