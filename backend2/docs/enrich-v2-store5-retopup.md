<!-- snapshot: 2026-08-13T10:57:59Z · head: 012f1dd4c2a2 -->
# Выгрузка станка на вычитку

Снимок: **2026-08-13T10:57:59Z** · HEAD: `012f1dd4c2a2` · версия генератора: `enrich-v1-topup2` (топап №2, режим по покрытию).

Снимок старше правок в базе — выгрузку надо снять заново: то, что здесь написано, было
верно на момент снимка и с тех пор могло быть починено.

Термины без вариантов, дистракторов и флагов в выгрузку не попадают — это рабочий список,
а не дамп базы. Колонка «флаги» — то, что требует решения человека. Флаги показаны только
для версии `enrich-v1-topup2` (этот топап-прогон); флаги более ранних прогонов уже разобраны
предыдущими вычитками и здесь не повторяются.

## В банке

### I'd like to open an account.

- **перевод (промпт):** Я бы хотел открыть счёт.
- **эталон-пример:** I'd like to open an account with your bank.
- **дистракторы:**
    - `I'd like to open account with your bank.` — article: **account** → `an account`

### bank account

- **перевод (промпт):** банковский счёт
- **эталон-пример:** I need a bank account to receive my salary.
- **принимаемые варианты:**
    - `savings account` — _Также является синонимом для банковского счёта._
    - `checking account` — _Синоним для банковского счёта._
- **дистракторы:**
    - `I need bank account to receive my salary.` — article: **bank account** → `a bank account`
    - `I need a bank accounts to receive my salary.` — article: **a bank accounts** → `a bank account`
    - `I needs a bank account to receive my salary.` — tense: **needs** → `need`
    - `I need a bank account for receive my salary.` — preposition: **for receive** → `to receive`

### debit card

- **перевод (промпт):** дебетовая карта
- **эталон-пример:** I prefer using my debit card for everyday purchases.
- **дистракторы:**
    - `I prefer using the debit card for everyday purchases.` — article: **the debit card** → `my debit card`
    - `I prefer using my debit cards for everyday purchases.` — article: **my debit cards** → `my debit card`

### credit card

- **перевод (промпт):** кредитная карта
- **эталон-пример:** She got a new credit card with a higher limit.
- **дистракторы:**
    - `She got new credit card with a higher limit.` — article: **new credit card** → `a new credit card`
    - `She got a new credit card at a higher limit.` — preposition: **at a higher limit** → `with a higher limit`

### Could you help me with this form?

- **перевод (промпт):** Не могли бы вы помочь мне с этой формой?
- **эталон-пример:** Could you help me with this form? I'm not sure how to fill it out.
- **дистракторы:**
    - `Could you help me with this forms? I'm not sure how to fill it out.` — tense: **this forms** → `this form`
    - `Could you help me with this form? I not sure how to fill it out.` — tense: **I not sure** → `I'm not sure`
    - `Could you help me with form? I'm not sure how to fill it out.` — article: **with form** → `with this form`
    - `Could you help me with this form? I'm not sure to fill it out.` — modal_to: **sure** → `sure how`
    - `Could you help me with this form? Not sure how to fill it out.` — word_order: **Not sure** → `I'm not sure`

### fill out

- **перевод (промпт):** заполнять (форму, анкету)
- **эталон-пример:** Please fill out this application to proceed.
- **принимаемые варианты:**
    - `fill in` — _британский вариант_
    - `complete` — _Это синоним в данном контексте._
- **дистракторы:**
    - `Please fill out this application for proceed.` — preposition: **for proceed** → `to proceed`
    - `Please fill out a application to proceed.` — article: **a application** → `this application`

### ATM

- **перевод (промпт):** банкомат
- **эталон-пример:** I withdrew cash from the ATM last night.
- **принимаемые варианты:**
    - `automated teller machine` — _другое название для банкомата._
    - `cash dispenser` — _Другой способ обозначить банкомат._
    - `cash machine` — _Эквивалентный термин для банкомата._
- **дистракторы:**
    - `I withdrawn cash from the ATM last night.` — tense: **withdrawn** → `withdrew`
    - `I withdrew cash from ATM last night.` — article: **ATM** → `the ATM`

### transfer money

- **перевод (промпт):** перевести деньги
- **эталон-пример:** I need to transfer money to my sister's account today.
- **дистракторы:**
    - `I need to transfer money in my sister's account today.` — preposition: **in my sister's account** → `to my sister's account`
    - `I need to transferred money to my sister's account today.` — tense: **transferred** → `transfer`

### standing order

- **перевод (промпт):** постоянное поручение (в банке)
- **эталон-пример:** I set up a standing order to pay my rent each month.
- **дистракторы:**
    - `I set up a standing order for pay my rent each month.` — preposition: **for pay** → `to pay`
    - `I set up a the standing order to pay my rent each month.` — article: **the standing order** → `standing order`
    - `I set up a standing order to pays my rent each month.` — tense: **to pays** → `to pay`

### set up an account

- **перевод (промпт):** подключить, оформить счёт (услугу)
- **эталон-пример:** You can set up an account online in just a few minutes.
- **принимаемые варианты:**
    - `create an account` — _синоним оформления счёта._
    - `register an account` — _синоним оформления счёта._
- **дистракторы:**
    - `You can set up account online in just a few minutes.` — article: **set up account** → `set up an account`
    - `You can set up an account in just a few minutes online.` — word_order: **in just a few minutes online** → `online in just a few minutes`
    - `You can set ups an account online in just a few minutes.` — tense: **set ups** → `set up`

### currency exchange

- **перевод (промпт):** обмен валюты
- **эталон-пример:** Where is the nearest currency exchange counter?
- **дистракторы:**
    - `Where is nearest currency exchange counter?` — word_order: **nearest currency exchange counter** → `the nearest currency exchange counter`
    - `Where is the nearest the currency exchange counter?` — article: **the currency exchange** → `currency exchange`

### direct debit

- **перевод (промпт):** автосписание (прямое дебетование)
- **эталон-пример:** I pay my utility bills by direct debit.
- **принимаемые варианты:**
    - `automatic payment` — _Синоним автосписания._
    - `direct withdrawal` — _Схожее значение с прямым дебетованием._
- **дистракторы:**
    - `I pay my utility bills for direct debit.` — preposition: **for direct debit** → `by direct debit`
    - `I pay my utility bills by the direct debit.` — article: **the direct debit** → `direct debit`
    - `I pays my utility bills by direct debit.` — tense: **pays** → `pay`

### withdrawal limit

- **перевод (промпт):** лимит снятия
- **эталон-пример:** My ATM has a daily withdrawal limit of $500.
- **дистракторы:**
    - `My ATM has a daily withdrawal limits of $500.` — word_order: **withdrawal limits** → `withdrawal limit`
    - `My ATM has daily withdrawal limit of $500.` — article: **daily withdrawal limit** → `a daily withdrawal limit`
    - `My ATM have a daily withdrawal limit of $500.` — tense: **have** → `has`

### savings account

- **перевод (промпт):** сберегательный счёт
- **эталон-пример:** I opened a savings account for future expenses.
- **дистракторы:**
    - `I opened savings account for future expenses.` — article: **savings account** → `a savings account`
    - `I opened a savings account for future expense.` — tense: **future expense** → `future expenses`

### online banking

- **перевод (промпт):** онлайн-банкинг
- **эталон-пример:** Do you offer online banking services?
- **дистракторы:**
    - `Do you offer online the banking services?` — article: **the banking** → `banking`
    - `Do you offers online banking services?` — tense: **offers** → `offer`
    - `Do you offer online banking at services?` — preposition: **at services** → `services`

### Could you explain the fees?

- **перевод (промпт):** Не могли бы вы объяснить комиссии?
- **эталон-пример:** Could you explain the fees for maintaining this account?
- **принимаемые варианты:**
    - `Can you explain the fees?` — _Это то же самое выражение._
    - `Would you explain the fees?` — _Это синонимичное выражение._
- **дистракторы:**
    - `Could you explained the fees for maintaining this account?` — tense: **explained** → `explain`
    - `Could you explain the fees to maintaining this account?` — preposition: **to maintaining** → `for maintaining`
    - `Could you explain the fees for maintain this account?` — tense: **maintain** → `maintaining`

### interest rate

- **перевод (промпт):** процентная ставка
- **эталон-пример:** What's the current interest rate for savings accounts?
- **дистракторы:**
    - `What are the current interest rate for savings accounts?` — tense: **What are** → `What's`
    - `What's the current interest rates for savings accounts?` — tense: **interest rates** → `interest rate`

### minimize fees

- **перевод (промпт):** уменьшить комиссии
- **эталон-пример:** I want to minimize fees on my account.
- **принимаемые варианты:**
    - `reduce fees` — _то же значение_
- **дистракторы:**
    - `I want to minimize fees in my account.` — preposition: **in my account** → `on my account`
- **флаги:**
    - ⚠️ **переформулировать** — Обратный перевод даёт «reduce commissions» — ни эталон, ни варианты этого не покрывают; переформулировать.

### financial advisor

- **перевод (промпт):** финансовый консультант
- **эталон-пример:** I scheduled a meeting with my financial advisor to discuss investments.
- **принимаемые варианты:**
    - `financial consultant` — _то же значение_
- **дистракторы:**
    - `I scheduled a meeting with my financial advisor for discuss investments.` — preposition: **for discuss** → `to discuss`
    - `I scheduled a meeting in my financial advisor to discuss investments.` — preposition: **in** → `with`
    - `I schedule a meeting with my financial advisor to discuss investments.` — tense: **schedule** → `scheduled`
    - `I scheduled a meeting with the financial advisor to discuss investments.` — article: **the** → `my`

### monthly statement

- **перевод (промпт):** ежемесячная выписка
- **эталон-пример:** I review my monthly statement for any discrepancies.
- **принимаемые варианты:**
    - `monthly report` — _Синоним, используемый для описания того же понятия._
- **дистракторы:**
    - `I review my monthly statement of any discrepancies.` — preposition: **of any discrepancies** → `for any discrepancies`
    - `I reviews my monthly statement for any discrepancies.` — tense: **I reviews** → `I review`
    - `I review my the monthly statement for any discrepancies.` — article: **the monthly statement** → `monthly statement`
    - `I review my monthly statement at any discrepancies.` — preposition: **at any discrepancies** → `for any discrepancies`

## Аренда жилья

### view an apartment

- **перевод (промпт):** осмотреть квартиру
- **эталон-пример:** I'm going to view an apartment tomorrow.
- **дистракторы:**
    - `I'm going to view apartment tomorrow.` — article: **view apartment** → `view an apartment`
    - `I'm going to view for an apartment tomorrow.` — preposition: **view for an apartment** → `view an apartment`
    - `I going to view an apartment tomorrow.` — tense: **I going** → `I am going`
    - `I'm going to view an apartment in tomorrow.` — preposition: **in tomorrow** → `tomorrow`

### lease agreement

- **перевод (промпт):** договор аренды
- **эталон-пример:** Please read the lease agreement carefully before signing.
- **принимаемые варианты:**
    - `rental agreement` — _то же значение_
- **дистракторы:**
    - `Please read lease agreement carefully before signing.` — article: **lease agreement** → `the lease agreement`
    - `Please read in the lease agreement carefully before signing.` — preposition: **in the lease agreement** → `the lease agreement`
    - `Please read a lease agreement carefully before signing.` — article: **a lease agreement** → `the lease agreement`

### security deposit

- **перевод (промпт):** залог
- **эталон-пример:** You'll need to pay a security deposit before moving in.
- **принимаемые варианты:**
    - `deposit` — _синоним к слову "залог"._
- **дистракторы:**
    - `You'll need to pay security deposit before moving in.` — article: **security deposit** → `a security deposit`
    - `You'll need to pay a security deposit for moving in.` — preposition: **for** → `before`

### utilities included

- **перевод (промпт):** коммунальные платежи включены
- **эталон-пример:** Are utilities included in the rent?
- **принимаемые варианты:**
    - `utilities are included` — _взаимозаменяемый вариант_
- **дистракторы:**
    - `Are utilities include in the rent?` — tense: **include** → `included`
    - `Are utilities includes in the rent?` — tense: **includes** → `included`

### per month

- **перевод (промпт):** в месяц
- **эталон-пример:** The rent is $1000 per month.
- **дистракторы:**
    - `The rent is $1000 for month.` — preposition: **for month** → `per month`
    - `The rent are $1000 per month.` — tense: **are** → `is`

### first and last month's rent

- **перевод (промпт):** арендная плата за первый и последний месяц
- **эталон-пример:** You'll have to pay the first and last month's rent when you sign the lease.
- **дистракторы:**
    - `You'll have to pay the first and last month rent when you sign the lease.` — article: **the first and last month rent** → `the first and last month's rent`
    - `You'll have to pay the first and last month's rent when you signing the lease.` — tense: **when you signing** → `when you sign`
    - `You'll have to pay the first and last month's rent at you sign the lease.` — preposition: **at you sign** → `when you sign`

### move-in date

- **перевод (промпт):** дата заселения
- **эталон-пример:** What's your preferred move-in date?
- **дистракторы:**
    - `What's your preferred date move-in?` — word_order: **date move-in** → `move-in date`
    - `What's the preferred move-in date?` — article: **the preferred** → `your preferred`
    - `What's your preferred move-in dates?` — tense: **move-in dates** → `move-in date`

### tenant

- **перевод (промпт):** арендатор, жилец
- **эталон-пример:** The tenant is responsible for minor repairs.
- **принимаемые варианты:**
    - `lessee` — _синоним слова 'арендатор'._
    - `renter` — _то же значение_
- **дистракторы:**
    - `The tenant are responsible for minor repairs.` — tense: **are** → `is`
    - `The tenant is responsible on minor repairs.` — preposition: **on** → `for`

### landlord

- **перевод (промпт):** арендодатель
- **эталон-пример:** The landlord will show you around the apartment.
- **дистракторы:**
    - `The landlord show you around the apartment.` — tense: **show** → `will show`
    - `The landlord will show around you the apartment.` — word_order: **around you** → `you around`

### sign the lease

- **перевод (промпт):** подписать договор аренды
- **эталон-пример:** We signed the lease yesterday.
- **дистракторы:**
    - `We sign the lease yesterday.` — tense: **sign** → `signed`
    - `We signed lease yesterday.` — article: **lease** → `the lease`
    - `We signed the yesterday lease.` — word_order: **the yesterday lease** → `the lease yesterday`

### rental application

- **перевод (промпт):** заявка на аренду
- **эталон-пример:** Fill out the rental application before the viewing.
- **дистракторы:**
    - `Fill out rental application before the viewing.` — article: **rental application** → `the rental application`
    - `Fill out a rental application before the viewing.` — article: **a rental application** → `the rental application`
    - `Fill out the rental application at the viewing.` — preposition: **at the viewing** → `before the viewing`
- **флаги:**
    - ⚠️ **переформулировать** — Обратный перевод даёт «application for rent» — ни эталон, ни варианты этого не покрывают; переформулировать.

### monthly rent

- **перевод (промпт):** ежемесячная арендная плата
- **эталон-пример:** The monthly rent must be paid on the first of each month.
- **принимаемые варианты:**
    - `monthly rental fee` — _похожее значение, но с другим словом._
- **дистракторы:**
    - `The monthly rent must be paid at the first of each month.` — preposition: **at the first** → `on the first`
    - `The monthly rent must be paid on first of each month.` — article: **on first** → `on the first`
    - `The monthly rent must paid on the first of each month.` — tense: **must paid** → `must be paid`

### maintenance request

- **перевод (промпт):** заявка на ремонт
- **эталон-пример:** You can submit a maintenance request online.
- **принимаемые варианты:**
    - `repair request` — _Синоним: запрос на ремонт._
    - `service request` — _Синоним: заявка на обслуживание._
- **дистракторы:**
    - `You can submit a maintenance request in online.` — preposition: **in online** → `online`
    - `You can submit maintenance request online.` — article: **maintenance request** → `a maintenance request`

### walk-through

- **перевод (промпт):** осмотр (перед сдачей жилья)
- **эталон-пример:** We did a walk-through of the apartment before moving in.
- **принимаемые варианты:**
    - `inspection` — _Синоним, подходящий в контексте._
    - `walkthrough` — _Неформальный вариант термина._
    - `final inspection` — _Другой вариант осмотра перед арендой._
    - `property inspection` — _Синонимичный термин для осмотра недвижимости._
- **дистракторы:**
    - `We did walk-through of the apartment before moving in.` — article: **walk-through** → `a walk-through`
    - `We did a walk-through in the apartment before moving in.` — preposition: **in the apartment** → `of the apartment`
    - `We done a walk-through of the apartment before moving in.` — tense: **done** → `did`

### furnished

- **перевод (промпт):** с мебелью
- **эталон-пример:** Is the apartment furnished or unfurnished?
- **принимаемые варианты:**
    - `with furniture` — _означает то же самое_
- **дистракторы:**
    - `Is the apartment furnished and unfurnished?` — tense: **and** → `or`
    - `Is the apartment in furnished or unfurnished?` — preposition: **apartment in** → `apartment`
    - `Is apartment furnished or unfurnished?` — article: **apartment** → `the apartment`
    - `Is an apartment furnished or unfurnished?` — article: **an** → `the`

### move out

- **перевод (промпт):** съезжать
- **эталон-пример:** When do you plan to move out?
- **принимаемые варианты:**
    - `leave` — _синоним, который также означает покинуть место._
    - `vacate` — _синонимы, обозначающие переезд из жилья._
- **дистракторы:**
    - `When do you plan for to move out?` — modal_to: **for to** → `to`
    - `When you plan to move out?` — word_order: **you plan** → `do you plan`
    - `When do you planning to move out?` — tense: **planning** → `plan`

### notice period

- **перевод (промпт):** период уведомления
- **эталон-пример:** You have to give a one-month notice period before moving out.
- **дистракторы:**
    - `You have to give the one-month notice period before moving out.` — article: **the one-month** → `a one-month`
    - `You have to give a one-month notices period before moving out.` — false_friend: **notices** → `notice`
    - `You have to give one-month notice period before moving out.` — article: **one-month** → `a one-month`
    - `You have to give a notice period one-month before moving out.` — word_order: **notice period one-month** → `one-month notice period`

### shared accommodation

- **перевод (промпт):** совместное жильё
- **эталон-пример:** I'm looking for shared accommodation in the city center.
- **принимаемые варианты:**
    - `shared housing` — _то же значение_
    - `communal accommodation` — _Эквивалентное выражение._
    - `co-housing` — _Также употребляется для обозначения совместного жилья._
    - `common housing` — _Синонимы._
    - `shared living arrangement` — _Синонимы._
- **дистракторы:**
    - `I'm looking for shared accommodation at the city center.` — preposition: **at the city center** → `in the city center`
    - `I'm looking for shared accommodation in city center.` — article: **in city center** → `in the city center`

### pay the rent

- **перевод (промпт):** платить аренду
- **эталон-пример:** I pay the rent by bank transfer every month.
- **принимаемые варианты:**
    - `pay rent` — _можно использовать в этом контексте_
    - `make a rent payment` — _эквивалентная формулировка_
    - `make the rent payment` — _Эквивалентное выражение для оплаты аренды._
- **дистракторы:**
    - `I pay the rent in bank transfer every month.` — preposition: **in bank transfer** → `by bank transfer`
    - `I pay rent the by bank transfer every month.` — word_order: **rent the by** → `the rent by`
    - `I pay the rent by bank transfers every month.` — tense: **by bank transfers** → `by bank transfer`
    - `I pay the rent bank transfer every month.` — word_order: **rent** → `rent by`
    - `I pays the rent by bank transfer every month.` — tense: **pays** → `pay`

### sublet

- **перевод (промпт):** сдавать в субаренду
- **эталон-пример:** Do you have permission to sublet the apartment?
- **принимаемые варианты:**
    - `sublease` — _синоним слова "субаренда"._
    - `lease out` — _Синонимично с терминами 'sublet' и 'sublease'._
- **дистракторы:**
    - `Do you have a permission to sublet the apartment?` — article: **a permission** → `permission`
    - `Do you have permission for sublet the apartment?` — preposition: **for sublet** → `to sublet`
    - `Do you has permission to sublet the apartment?` — tense: **has** → `have`

## У врача и в аптеке

### I'd like to make an appointment.

- **перевод (промпт):** Я хотел бы записаться на приём.
- **эталон-пример:** I'd like to make an appointment with Dr. Jones.
- **дистракторы:**
    - `I'd like to make an appointment at Dr. Jones.` — preposition: **at Dr. Jones** → `with Dr. Jones`
    - `I'd like to make appointment with Dr. Jones.` — article: **appointment** → `an appointment`
    - `I like to make an appointment with Dr. Jones.` — tense: **I like** → `I'd like`

### What are your symptoms?

- **перевод (промпт):** Какие у вас симптомы?
- **эталон-пример:** What are your symptoms today?
- **дистракторы:**
    - `What today are your symptoms?` — word_order: **What today are your symptoms** → `What are your symptoms today`
    - `What are you symptoms today?` — article: **you** → `your`
    - `What is your symptoms today?` — tense: **is** → `are`
    - `What are symptoms your today?` — word_order: **symptoms your** → `your symptoms`

### I have a fever.

- **перевод (промпт):** У меня жар.
- **эталон-пример:** I have a fever and feel very weak.
- **дистракторы:**
    - `I have fever and feel very weak.` — article: **fever** → `a fever`
    - `I have a fever and feels very weak.` — tense: **feels** → `feel`

### cold

- **перевод (промпт):** простуда
- **эталон-пример:** I think I have a cold.
- **дистракторы:**
    - `I think I have cold.` — article: **cold** → `a cold`
    - `I think I have the cold.` — article: **the** → `a`
    - `I think I has a cold.` — tense: **has** → `have`

### prescription

- **перевод (промпт):** рецепт (на лекарство)
- **эталон-пример:** The doctor gave me a prescription for antibiotics.
- **дистракторы:**
    - `The doctor gave me a prescription on antibiotics.` — preposition: **on antibiotics** → `for antibiotics`
    - `The doctor gave me prescription for antibiotics.` — article: **prescription** → `a prescription`
    - `The doctor gave to me a prescription for antibiotics.` — preposition: **gave to me** → `gave me`

### pharmacy

- **перевод (промпт):** аптека
- **эталон-пример:** You can buy this medicine at any pharmacy.
- **дистракторы:**
    - `You can buy this medicine in any pharmacy.` — preposition: **in any pharmacy** → `at any pharmacy`
    - `You can buy this medicine at the any pharmacy.` — article: **the any** → `any`
    - `You can buy this medicine on any pharmacy.` — preposition: **on** → `at`
    - `You can buys this medicine at any pharmacy.` — tense: **buys** → `buy`
- **флаги:**
    - ⚠️ **переформулировать** — Обратный перевод даёт «drugstore» — ни эталон, ни варианты этого не покрывают; переформулировать.

### I have a headache.

- **перевод (промпт):** У меня болит голова.
- **эталон-пример:** I've had a headache since this morning.
- **дистракторы:**
    - `I had a headache since this morning.` — tense: **I** → `I've`
    - `I has had a headache since this morning.` — tense: **I has** → `I have`
    - `I have a headache since this morning.` — preposition: **I have** → `I've had`

### How long have you been feeling this way?

- **перевод (промпт):** Как давно вы так себя чувствуете?
- **эталон-пример:** How long have you been feeling this way before coming here?
- **дистракторы:**
    - `How long have you been feeling this way before you come here?` — tense: **you come** → `coming`

### cough

- **перевод (промпт):** кашель
- **эталон-пример:** She has a bad cough.
- **принимаемые варианты:**
    - `coughing` — _Форма 'кашлять', подходящая в контексте._
- **дистракторы:**
    - `She has bad cough.` — article: **bad cough** → `a bad cough`
    - `She has a cough bad.` — word_order: **cough bad** → `bad cough`
    - `She has a bad coughs.` — tense: **coughs** → `cough`

### I need a refill.

- **перевод (промпт):** Мне нужно продлить рецепт.
- **эталон-пример:** I need a refill on my prescription, please.
- **дистракторы:**
    - `I need a refill in my prescription, please.` — preposition: **in** → `on`
    - `I need refill on my prescription, please.` — article: **refill** → `a refill`
    - `I needs a refill on my prescription, please.` — tense: **I needs** → `I need`

### How can I help you?

- **перевод (промпт):** Чем я могу вам помочь?
- **эталон-пример:** Welcome to the pharmacy. How can I help you?
- **принимаемые варианты:**
    - `How can I assist you?` — _то же значение, чуть формальнее_
- **дистракторы:**
    - `Welcome to the pharmacy. How can I help you to?` — modal_to: **help you to** → `help you`
    - `Welcome to the pharmacy. How can I helping you?` — tense: **helping you** → `help you`
    - `Welcome to the pharmacy. How can I helps you?` — tense: **helps** → `help`

### medicine

- **перевод (промпт):** лекарство
- **эталон-пример:** Did the doctor give you any medicine?
- **дистракторы:**
    - `Did doctor give you any medicine?` — word_order: **Did doctor** → `Did the doctor`
    - `Did the doctor give you a medicine?` — article: **a medicine** → `any medicine`
    - `Did the doctor gave you any medicine?` — tense: **gave** → `give`

### run a temperature

- **перевод (промпт):** температурить, иметь температуру
- **эталон-пример:** He's been running a temperature since last night.
- **принимаемые варианты:**
    - `running a fever` — _также используется для обозначения высокой температуры._
    - `have a temperature` — _синоним с тем же значением._
    - `to have a fever` — _Синонимы._
    - `to have a high temperature` — _Синонимы._
- **дистракторы:**
    - `He's been running temperature since last night.` — article: **running temperature** → `running a temperature`

### My throat hurts.

- **перевод (промпт):** У меня болит горло.
- **эталон-пример:** My throat hurts every time I swallow.
- **принимаемые варианты:**
    - `I have a sore throat.` — _тот же смысл_
- **дистракторы:**
    - `My throat hurt every time I swallow.` — tense: **hurt** → `hurts`
    - `My throat hurts every time I swallowing.` — modal_to: **I swallowing** → `I swallow`
    - `Throat hurts every time I swallow.` — article: **Throat** → `My throat`

### pharmacist

- **перевод (промпт):** фармацевт
- **эталон-пример:** The pharmacist can help you find the right medicine.
- **принимаемые варианты:**
    - `chemist` — _Используется в некоторых странах вместо фармацевт._
    - `druggist` — _Общее слово для фармацевта._
- **дистракторы:**
    - `The pharmacist can helps you find the right medicine.` — tense: **can helps** → `can help`
    - `The pharmacist can help you to find the right medicine.` — modal_to: **to find** → `find`

### Please take a seat.

- **перевод (промпт):** Пожалуйста, присаживайтесь.
- **эталон-пример:** Please take a seat, the doctor will see you shortly.
- **дистракторы:**
    - `Please take a seat, doctor will see you shortly.` — article: **doctor** → `the doctor`
    - `Please take a seat, the doctor will sees you shortly.` — tense: **sees** → `see`
    - `Please take a seat, the doctor will see on you shortly.` — preposition: **see on you** → `see you`
    - `Please take seat, the doctor will see you shortly.` — article: **seat** → `a seat`

### fill a prescription

- **перевод (промпт):** получить лекарство по рецепту (в аптеке)
- **эталон-пример:** I need to fill a prescription for my medication.
- **дистракторы:**
    - `I need fill a prescription for my medication.` — article: **fill** → `to fill`
    - `I need to fill prescription for my medication.` — article: **fill** → `fill a`
- **флаги:**
    - ⚠️ **переформулировать** — Обратный перевод даёт «get medication by prescription» — ни эталон, ни варианты этого не покрывают; переформулировать.

### take medicine

- **перевод (промпт):** принимать лекарства
- **эталон-пример:** Please remember to take your medicine twice a day.
- **принимаемые варианты:**
    - `take your medication` — _то же самое по смыслу_
    - `take your pills` — _Эквивалентное выражение для приема лекарств._
- **дистракторы:**
    - `Please remembered to take your medicine twice a day.` — tense: **remembered** → `remember`
    - `Please remember to take medicine your twice a day.` — word_order: **medicine your** → `your medicine`

### I have an appointment at 3 PM.

- **перевод (промпт):** У меня запись на 3 часа дня.
- **эталон-пример:** I have an appointment at 3 PM with the dentist.
- **дистракторы:**
    - `I have an appointment in 3 PM with the dentist.` — preposition: **in 3 PM** → `at 3 PM`
    - `I have appointment at 3 PM with the dentist.` — article: **appointment** → `an appointment`
    - `I have an appointment on 3 PM with the dentist.` — preposition: **on 3 PM** → `at 3 PM`

### allergic reaction

- **перевод (промпт):** аллергическая реакция
- **эталон-пример:** I had an allergic reaction to the medication.
- **дистракторы:**
    - `I had an allergic reaction of the medication.` — preposition: **of the medication** → `to the medication`
    - `I had allergic reaction to the medication.` — article: **allergic reaction** → `an allergic reaction`

## Собеседование в IT

### Could you tell me more about the team?

- **перевод (промпт):** Могли бы вы рассказать мне больше о команде?
- **эталон-пример:** Could you tell me more about the team I would be working with?
- **дистракторы:**
    - `Could you tell me more about team I would be working with?` — article: **team** → `the team`
    - `Could you tell me more about the team I would working with?` — tense: **would working** → `would be working`

### What programming languages are you proficient in?

- **перевод (промпт):** Какими языками программирования вы владеете?
- **эталон-пример:** What programming languages are you proficient in?
- **дистракторы:**
    - `What programming languages is you proficient in?` — tense: **is you** → `are you`
    - `What programming languages are you proficients in?` — tense: **proficients** → `proficient`

### work experience

- **перевод (промпт):** опыт работы
- **эталон-пример:** My work experience includes five years as a software developer.
- **дистракторы:**
    - `My work experience include five years as a software developer.` — tense: **include** → `includes`
    - `My work experience includes five years as software developer.` — article: **as software developer** → `as a software developer`

### What are the main responsibilities of this role?

- **перевод (промпт):** Каковы основные обязанности в этой должности?
- **эталон-пример:** What are the main responsibilities of this role?
- **дистракторы:**
    - `What are the responsibilities main of this role?` — word_order: **responsibilities main** → `main responsibilities`
    - `What is the main responsibilities of this role?` — article: **What is** → `What are`
    - `What are the main responsibility of this role?` — article: **main responsibility** → `main responsibilities`

### I'm confident in my problem-solving skills.

- **перевод (промпт):** Я уверен в своих навыках решения проблем.
- **эталон-пример:** I'm confident in my problem-solving skills and enjoy tackling complex issues.
- **принимаемые варианты:**
    - `I am confident in my problem-solving abilities.` — _Синонимы._
- **дистракторы:**
    - `I'm confident in my problem-solving skills and enjoy tackling complex issue.` — article: **complex issue** → `complex issues`
    - `I'm confident in my problem-solving skills and enjoy to tackle complex issues.` — modal_to: **to tackle** → `tackling`
    - `I'm confident in my problem-solving skills and enjoy tackle complex issues.` — tense: **tackle** → `tackling`

### offer letter

- **перевод (промпт):** письмо с предложением о работе
- **эталон-пример:** I was thrilled to receive the offer letter for the position.
- **принимаемые варианты:**
    - `job offer letter` — _то же значение_
    - `employment offer letter` — _Синоним "письмо с предложением о работе"._
- **дистракторы:**
    - `I was thrilled to receive offer letter for the position.` — article: **offer letter** → `the offer letter`
    - `I was thrilled to receive a offer letter for the position.` — article: **a offer letter** → `the offer letter`
    - `I was thrilled receiving the offer letter for the position.` — tense: **thrilled receiving** → `thrilled to receive`
- **флаги:**
    - ⚠️ **переформулировать** — Обратный перевод даёт «job offer» — ни эталон, ни варианты этого не покрывают; переформулировать.

### collaborative environment

- **перевод (промпт):** среда, способствующая сотрудничеству
- **эталон-пример:** I thrive in a collaborative environment where teamwork is valued.
- **дистракторы:**
    - `I thrive in a collaborative environments where teamwork is valued.` — article: **a collaborative environments** → `a collaborative environment`
    - `I thrive in a collaborative environment where is valued teamwork.` — word_order: **where is valued teamwork** → `where teamwork is valued`
    - `I thrive in collaborative environment where teamwork is valued.` — article: **collaborative environment** → `a collaborative environment`

### I have experience with agile methodologies.

- **перевод (промпт):** У меня есть опыт работы с гибкими методологиями.
- **эталон-пример:** I have experience with agile methodologies, which helps in adapting to changes quickly.
- **принимаемые варианты:**
    - `I have knowledge of agile methodologies.` — _Синонимы._
    - `I have expertise in agile methodologies.` — _Синонимы._
- **дистракторы:**
    - `I has experience with agile methodologies, which helps in adapting to changes quickly.` — tense: **I has** → `I have`
    - `I have experience with agile methodologies, what helps in adapting to changes quickly.` — word_order: **what** → `which`
- **флаги:**
    - ⚠️ **переформулировать** — Обратный перевод даёт «I have experience working with flexible methodologies» — ни эталон, ни варианты этого не покрывают; переформулировать.

### job offer

- **перевод (промпт):** предложение о работе
- **эталон-пример:** The company made me a job offer after the final interview.
- **дистракторы:**
    - `The company made me job offer after the final interview.` — article: **job offer** → `a job offer`
    - `The company made me a job offer to the final interview.` — preposition: **to the final interview** → `after the final interview`
    - `The company made me a job offer in the final interview.` — preposition: **in the final interview** → `after the final interview`

### Can you tell me about the career growth opportunities?

- **перевод (промпт):** Можете рассказать о возможностях карьерного роста?
- **эталон-пример:** Can you tell me about the career growth opportunities at your company?
- **принимаемые варианты:**
    - `Could you tell me about the career growth opportunities?` — _Синоним с другим модальным глаголом._
    - `Can you tell me about the job growth opportunities?` — _Синонимично данному термину._
    - `Could you tell me about the career advancement opportunities?` — _Синонимично данному термину._
- **дистракторы:**
    - `Can you tell about the career growth opportunities at your company?` — preposition: **tell about** → `tell me about`
- **флаги:**
    - ⚠️ **переформулировать** — Обратный перевод даёт «Can you tell me about the opportunities for career growth» — ни эталон, ни варианты этого не покрывают; переформулировать.

### What are your strengths and weaknesses?

- **перевод (промпт):** Каковы ваши сильные и слабые стороны?
- **эталон-пример:** During the interview, they asked me about my strengths and weaknesses.
- **дистракторы:**
    - `During the interview, they asked about my strengths and weaknesses me.` — word_order: **asked about my strengths and weaknesses me** → `asked me about my strengths and weaknesses`
    - `During the interview, they ask me about my strengths and weaknesses.` — tense: **ask** → `asked`
    - `During the interview, they asked me about my strength and weaknesses.` — article: **my strength** → `my strengths`
    - `During the interview, they asking me about my strengths and weaknesses.` — tense: **asking** → `asked`

### long-term goals

- **перевод (промпт):** долгосрочные цели
- **эталон-пример:** In the interview, I discussed my long-term goals in the IT industry.
- **дистракторы:**
    - `In the interview, I discussed my the long-term goals in the IT industry.` — article: **my the** → `my`
    - `In the interview, I discussed my long-term goal in the IT industry.` — tense: **long-term goal** → `long-term goals`

### multitasking ability

- **перевод (промпт):** способность к многозадачности
- **эталон-пример:** I mentioned my multitasking ability as one of my strengths.
- **принимаемые варианты:**
    - `ability to multitask` — _та же мысль другой конструкцией_
- **дистракторы:**
    - `I mentioned my multitasking ability as one of strengths.` — article: **one of strengths** → `one of my strengths`
    - `I mentioned about my multitasking ability as one of my strengths.` — preposition: **mentioned about** → `mentioned`

### get along with

- **перевод (промпт):** ладить с
- **эталон-пример:** I get along with my team well, making collaboration effective.
- **дистракторы:**
    - `I get along my team well, making collaboration effective.` — preposition: **along my team** → `along with my team`
    - `I get along with my team good, making collaboration effective.` — false_friend: **good** → `well`
    - `I gets along with my team well, making collaboration effective.` — tense: **gets** → `get`
    - `I get along with my team well, make collaboration effective.` — tense: **make** → `making`

### focus on results

- **перевод (промпт):** фокусироваться на результатах
- **эталон-пример:** I focus on results, ensuring that projects meet their objectives.
- **принимаемые варианты:**
    - `concentrate on results` — _эквивалентное выражение_
    - `concentrate on outcomes` — _Вариант пересказа, эквивалентный основному термину._
- **дистракторы:**
    - `I focus in results, ensuring that projects meet their objectives.` — preposition: **in** → `on`
    - `I focuses on results, ensuring that projects meet their objectives.` — tense: **focuses** → `focus`
    - `I the focus on results, ensuring that projects meet their objectives.` — word_order: **the focus on results** → `focus on results`

### comfortable with

- **перевод (промпт):** комфортно работаю с
- **эталон-пример:** I'm comfortable with fast-paced environments and enjoy challenges.
- **дистракторы:**
    - `I'm comfortable with fast-paced environment and enjoy challenges.` — article: **fast-paced environment** → `fast-paced environments`
- **флаги:**
    - ⚠️ **переформулировать** — Обратный перевод даёт «I work well with» — ни эталон, ни варианты этого не покрывают; переформулировать.
    - ✍️ **не слово → правка** — "комфортно работаю с" should be "комфортно с" for proper usage.

### notice period

- **перевод (промпт):** период уведомления
- **эталон-пример:** You have to give a one-month notice period before moving out.
- **дистракторы:**
    - `You have to give the one-month notice period before moving out.` — article: **the one-month** → `a one-month`
    - `You have to give a one-month notices period before moving out.` — false_friend: **notices** → `notice`
    - `You have to give one-month notice period before moving out.` — article: **one-month** → `a one-month`
    - `You have to give a notice period one-month before moving out.` — word_order: **notice period one-month** → `one-month notice period`

### hands-on experience

- **перевод (промпт):** практический опыт
- **эталон-пример:** I have hands-on experience with JavaScript frameworks in previous projects.
- **принимаемые варианты:**
    - `practical experience` — _то же значение_
- **дистракторы:**
    - `I have a hands-on experience with JavaScript frameworks in previous projects.` — article: **a hands-on experience** → `hands-on experience`
    - `I have hands-on experience with JavaScript frameworks in previous project.` — article: **previous project** → `previous projects`
    - `I have hands-on experience with JavaScript frameworks at previous projects.` — preposition: **at previous projects** → `in previous projects`

### When can you start?

- **перевод (промпт):** Когда вы можете начать?
- **эталон-пример:** They asked me when I could start if offered the position.
- **дистракторы:**
    - `They asked me when I could start if to offered the position.` — modal_to: **if to offered** → `if offered`
    - `They asked me when I start if offered the position.` — tense: **when I start** → `when I could start`
    - `They asked me when I could start if offer the position.` — tense: **offer** → `offered`

### What is the expected salary range?

- **перевод (промпт):** Какой ожидаемый диапазон зарплаты?
- **эталон-пример:** What is the expected salary range for this position?
- **дистракторы:**
    - `What is expected salary range for this position?` — article: **expected salary range** → `the expected salary range`
    - `What is the expected salary range at this position?` — preposition: **at this position** → `for this position`

## Ресторан и доставка еды

### reserve a table

- **перевод (промпт):** забронировать столик
- **эталон-пример:** I would like to reserve a table for two at 7 PM.
- **принимаемые варианты:**
    - `book a table` — _британский стандарт_
- **дистракторы:**
    - `I would like to reserve table for two at 7 PM.` — article: **reserve table** → `reserve a table`
    - `I would like to reserve a table for two on 7 PM.` — preposition: **on 7 PM** → `at 7 PM`
    - `I would like to reserve a table on two at 7 PM.` — preposition: **on two** → `for two`

### What would you like to order?

- **перевод (промпт):** Что бы вы хотели заказать?
- **эталон-пример:** What would you like to order?
- **дистракторы:**
    - `What would you like to the order?` — preposition: **to the order** → `to order`
    - `What would you like order?` — modal_to: **like order** → `like to order`
    - `What would you like to ordering?` — modal_to: **to ordering** → `to order`

### I'll have the chicken salad.

- **перевод (промпт):** Я буду куриный салат.
- **эталон-пример:** I'll have the chicken salad, please.
- **принимаемые варианты:**
    - `I’ll take the chicken salad.` — _Также означает то же самое._
    - `I would like the chicken salad.` — _Эквивалентная формулировка с тем же значением._
- **дистракторы:**
    - `I'll have chicken salad, please.` — article: **chicken salad** → `the chicken salad`
    - `I'll have the chicken salads, please.` — word_order: **the chicken salads** → `the chicken salad`

### menu

- **перевод (промпт):** меню
- **эталон-пример:** Could you bring me the menu, please?
- **дистракторы:**
    - `Could you bring me menu, please?` — article: **menu** → `the menu`

### How would you like to pay?

- **перевод (промпт):** Как вы хотели бы оплатить?
- **эталон-пример:** How would you like to pay, by cash or card?
- **принимаемые варианты:**
    - `How do you want to pay?` — _То же самое значение._
    - `How would you prefer to pay?` — _Синонимичное выражение._
- **дистракторы:**
    - `How would you like to pays, by cash or card?` — tense: **pays** → `pay`
    - `How would you like to pay, by the cash or card?` — article: **the cash** → `cash`
    - `How would you like to pay, by cash or the card?` — article: **the card** → `card`

### Could I see the wine list?

- **перевод (промпт):** Могу я посмотреть винную карту?
- **эталон-пример:** Could I see the wine list, please?
- **принимаемые варианты:**
    - `Can I see the wine list?` — _Синонимичное выражение._
    - `May I see the wine list?` — _Синонимичное выражение._
    - `Can I see the wine menu?` — _Синоним._
- **дистракторы:**
    - `Could I see wine list, please?` — article: **wine list** → `the wine list`
    - `Could I see the list of wine, please?` — word_order: **the list of wine** → `the wine list`
    - `Could I saw the wine list, please?` — tense: **saw** → `see`
    - `Could I seen the wine list, please?` — tense: **seen** → `see`

### I'd like to make a reservation.

- **перевод (промпт):** Я бы хотел сделать бронь.
- **эталон-пример:** I'd like to make a reservation for Saturday night.
- **дистракторы:**
    - `I'd like to make a reservation at Saturday night.` — preposition: **at Saturday night** → `for Saturday night`
    - `I'd like to make reservation for Saturday night.` — article: **reservation** → `a reservation`
    - `I'd like to make a reservation on Saturday night.` — preposition: **on** → `for`

### tip

- **перевод (промпт):** чаевые
- **эталон-пример:** It's customary to leave a tip in restaurants here.
- **принимаемые варианты:**
    - `gratuity` — _то же значение (симметрично паре gratuity/tip)_
- **дистракторы:**
    - `It's customary to leave tip in restaurants here.` — article: **tip** → `a tip`
    - `It's customary to leave a tips in restaurants here.` — article: **tips** → `tip`

### Can I get this to go?

- **перевод (промпт):** Можно это с собой?
- **эталон-пример:** I'd like the pasta to go, please.
- **принимаемые варианты:**
    - `Can I have this to go?` — _Синоним, имеющий то же значение._
    - `Can I take this to go?` — _Синоним, имеющий то же значение._
    - `Can I get this to take away?` — _британский вариант_
    - `Can I have this taken away?` — _Синоним._
    - `Can I get this for take away?` — _Синоним._
- **дистракторы:**
    - `I'd like the pasta for go, please.` — preposition: **for go** → `to go`
- **флаги:**
    - ✍️ **не слово → правка** — Нет ошибок.
    - ⚠️ **переформулировать** — Обратный перевод даёт «Can I take this with me?» — ни эталон, ни варианты этого не покрывают; переформулировать.

### delivery

- **перевод (промпт):** доставка
- **эталон-пример:** The delivery should arrive in 30 minutes.
- **дистракторы:**
    - `The delivery should arrives in 30 minutes.` — tense: **arrives** → `arrive`
    - `The delivery should arrive in 30 minute.` — tense: **30 minute** → `30 minutes`

### starter

- **перевод (промпт):** закуска
- **эталон-пример:** For a starter, I would recommend the soup.
- **принимаемые варианты:**
    - `appetizer` — _американский вариант_
    - `first course` — _Также означает первое блюдо._
- **дистракторы:**
    - `For a starter, I would recommend soup.` — article: **soup** → `the soup`
- **флаги:**
    - ⚠️ **переформулировать** — Обратный перевод даёт «snack» — ни эталон, ни варианты этого не покрывают; переформулировать.

### main course

- **перевод (промпт):** основное блюдо
- **эталон-пример:** I'll have the steak as my main course.
- **принимаемые варианты:**
    - `main dish` — _то же значение_
- **дистракторы:**
    - `I'll have steak as my main course.` — article: **steak** → `the steak`
    - `I'll have the steak as my main courses.` — tense: **courses** → `course`

### Could we have the bill, please?

- **перевод (промпт):** Можно нам счёт, пожалуйста?
- **эталон-пример:** Could we have the bill, please?
- **принимаемые варианты:**
    - `Could we have the check, please?` — _американский вариант_
- **дистракторы:**
    - `Could we have bill, please?` — article: **bill** → `the bill`
    - `Could we has the bill, please?` — tense: **has** → `have`

### order in

- **перевод (промпт):** заказать на дом
- **эталон-пример:** Let's order in tonight instead of cooking.
- **принимаемые варианты:**
    - `order delivery` — _синоним выражения «заказать на дом»._
    - `order takeaway` — _синоним выражения «заказать на дом»._
- **дистракторы:**
    - `Let's order in tonight instead of the cooking.` — article: **the cooking** → `cooking`
    - `Let's order in tonight instead of cook.` — tense: **cook** → `cooking`
    - `Let's order in tonight instead of to cook.` — modal_to: **to cook** → `cooking`

### Are you ready to order?

- **перевод (промпт):** Вы готовы заказать?
- **эталон-пример:** Are you ready to order, or do you need more time?
- **дистракторы:**
    - `Are you ready for order, or do you need more time?` — preposition: **for order** → `to order`
    - `Are you ready to ordering, or do you need more time?` — tense: **to ordering** → `to order`
    - `Are you ready ordering, or do you need more time?` — tense: **ready ordering** → `ready to order`

### Would you like anything to drink?

- **перевод (промпт):** Вы бы хотели что-нибудь выпить?
- **эталон-пример:** Would you like anything to drink with your meal?
- **принимаемые варианты:**
    - `Do you want something to drink?` — _Это синоним._
    - `Would you like something to drink?` — _Это синоним._
    - `Would you like a drink?` — _Эквивалентная фраза._
    - `Do you want a drink?` — _Эквивалентная фраза._
- **дистракторы:**
    - `Would you like anything to drink with meal?` — article: **with meal** → `with your meal`
    - `Would you like anything drink with your meal?` — modal_to: **anything drink** → `anything to drink`
    - `Would you liked anything to drink with your meal?` — tense: **liked** → `like`
    - `Would you like anything to drink for your meal?` — preposition: **for your meal** → `with your meal`

### cutlery

- **перевод (промпт):** столовые приборы
- **эталон-пример:** The cutlery is on the table.
- **принимаемые варианты:**
    - `eating utensils` — _синоним на английском._
    - `silverware` — _синоним на английском._
- **дистракторы:**
    - `The cutlery is at the table.` — preposition: **at** → `on`
    - `The cutlery are on the table.` — tense: **are** → `is`

### Can I have some water, please?

- **перевод (промпт):** Можно мне воды, пожалуйста?
- **эталон-пример:** Can I have some water, please?
- **дистракторы:**
    - `Can I had some water, please?` — tense: **had** → `have`
    - `Can I have some water for, please?` — preposition: **water for** → `water`
    - `Can I have for some water, please?` — preposition: **have for** → `have`
    - `Can I has some water, please?` — tense: **has** → `have`

### gratuity

- **перевод (промпт):** чаевые (формально)
- **эталон-пример:** Gratuity is not included in the bill.
- **принимаемые варианты:**
    - `tip` — _Синоним, используемый в разговорной речи._
    - `service charge` — _Синоним к "чаевым"_
- **дистракторы:**
    - `Gratuity is not include in the bill.` — tense: **include** → `included`
    - `Gratuity is not included in bill.` — article: **in bill** → `in the bill`

### serve

- **перевод (промпт):** обслуживать
- **эталон-пример:** The waiter will serve your meal shortly.
- **принимаемые варианты:**
    - `to serve` — _означает 'подавать' или 'обслуживать'._
- **дистракторы:**
    - `The waiter will serves your meal shortly.` — tense: **serves** → `serve`
    - `The waiter will serve meal shortly.` — article: **serve** → `serve your`

