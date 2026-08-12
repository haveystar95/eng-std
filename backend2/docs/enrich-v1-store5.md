# Выгрузка станка на вычитку

Версия генератора: `enrich-v1`.

Термины без вариантов, дистракторов и флагов в выгрузку не попадают — это рабочий список,
а не дамп базы. Колонка «флаги» — то, что требует решения человека.

## В банке

### I'd like to open an account.

- **перевод (промпт):** Я бы хотел открыть счёт.
- **эталон-пример:** I'd like to open an account with your bank.
- **дистракторы:**
    - `I'd like to open account with your bank.` — article: **account** → `an account`
    - `I'd like to open an account in your bank.` — preposition: **in** → `with`

### bank account

- **перевод (промпт):** банковский счёт
- **эталон-пример:** I need a bank account to receive my salary.
- **принимаемые варианты:**
    - `savings account` — _Синоним, также используется в данном контексте._
    - `checking account` — _Синоним, означающий то же самое._
- **дистракторы:**
    - `I need bank account to receive my salary.` — article: **bank account** → `a bank account`
    - `I need a bank accounts to receive my salary.` — article: **a bank accounts** → `a bank account`

### debit card

- **перевод (промпт):** дебетовая карта
- **эталон-пример:** I prefer using my debit card for everyday purchases.
- **дистракторы:**
    - `I prefer using the debit card for everyday purchases.` — article: **the debit card** → `my debit card`
    - `I prefer to use my debit card for everyday purchases.` — modal_to: **to use** → `using`

### credit card

- **перевод (промпт):** кредитная карта
- **эталон-пример:** She got a new credit card with a higher limit.
- **дистракторы:**
    - `She got new credit card with a higher limit.` — article: **new credit card** → `a new credit card`
    - `She got a new credit card for a higher limit.` — preposition: **for a higher limit** → `with a higher limit`

### Could you help me with this form?

- **перевод (промпт):** Не могли бы вы помочь мне с этой формой?
- **эталон-пример:** Could you help me with this form? I'm not sure how to fill it out.
- **дистракторы:**
    - `Could you help me with this forms? I'm not sure how to fill it out.` — tense: **this forms** → `this form`
    - `Could you help me with this form? I not sure how to fill it out.` — tense: **I not sure** → `I'm not sure`
    - `Could you help me with form? I'm not sure how to fill it out.` — article: **with form** → `with the form`

### fill out

- **перевод (промпт):** заполнить (форму)
- **эталон-пример:** Please fill out this application to proceed.
- **дистракторы:**
    - `Please fill out this application for proceed.` — preposition: **for proceed** → `to proceed`
    - `Please fill out a application to proceed.` — article: **a application** → `the application`
- **флаги:**
    - ⚠️ **переформулировать** — Обратный перевод даёт «to fill in» — ни эталон, ни варианты этого не покрывают; переформулировать.

### ATM

- **перевод (промпт):** банкомат
- **эталон-пример:** I withdrew cash from the ATM last night.
- **принимаемые варианты:**
    - `automated teller machine` — _другое название для банкомата._
- **дистракторы:**
    - `I withdrew cash at the ATM last night.` — preposition: **at** → `from`
    - `I withdrawn cash from the ATM last night.` — tense: **withdrawn** → `withdrew`
- **флаги:**
    - ✍️ **не слово → правка** — банкомат не является ошибкой.

### transfer money

- **перевод (промпт):** перевести деньги
- **эталон-пример:** I need to transfer money to my sister's account today.
- **дистракторы:**
    - `I need to transfer the money to my sister's account today.` — article: **the money** → `money`
    - `I needed to transfer money to my sister's account today.` — tense: **needed** → `need`
    - `I need to transfer money in my sister's account today.` — preposition: **in my sister's account** → `to my sister's account`

### standing order

- **перевод (промпт):** постоянное поручение (в банке)
- **эталон-пример:** I set up a standing order to pay my rent each month.
- **дистракторы:**
    - `I set up a standing order for pay my rent each month.` — preposition: **for pay** → `to pay`
    - `I set up a the standing order to pay my rent each month.` — article: **the standing order** → `standing order`
- **флаги:**
    - ⚠️ **переформулировать** — Обратный перевод даёт «permanent order» — ни эталон, ни варианты этого не покрывают; переформулировать.

### set up an account

- **перевод (промпт):** подключить, оформить счёт (услугу)
- **эталон-пример:** You can set up an account online in just a few minutes.
- **принимаемые варианты:**
    - `create an account` — _синоним оформления счёта._
    - `register an account` — _синоним оформления счёта._
- **дистракторы:**
    - `You can set up an account in just few minutes.` — article: **just few minutes** → `just a few minutes`
    - `You can set up an account online in just few minute.` — tense: **few minute** → `few minutes`
    - `You can set up account online in just a few minutes.` — article: **set up account** → `set up an account`
- **флаги:**
    - ⚠️ **переформулировать** — Обратный перевод даёт «connect, arrange an account (service)» — ни эталон, ни варианты этого не покрывают; переформулировать.

### currency exchange

- **перевод (промпт):** обмен валюты
- **эталон-пример:** Where is the nearest currency exchange counter?
- **дистракторы:**
    - `Where is nearest currency exchange counter?` — word_order: **nearest currency exchange counter** → `the nearest currency exchange counter`
    - `Where is the nearest the currency exchange counter?` — article: **the currency exchange** → `currency exchange`

### direct debit

- **перевод (промпт):** автосписание (прямое дебетование)
- **эталон-пример:** I pay my utility bills by direct debit.
- **дистракторы:**
    - `I pay my utility bills for direct debit.` — preposition: **for direct debit** → `by direct debit`
    - `I pay my utility bills by the direct debit.` — article: **the direct debit** → `direct debit`
    - `I paid my utility bills by direct debit.` — tense: **paid** → `pay`

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
    - `I opened a savings account for the future expenses.` — article: **the future expenses** → `future expenses`
    - `I opened a savings account for future expense.` — tense: **future expense** → `future expenses`

### online banking

- **перевод (промпт):** онлайн-банкинг
- **эталон-пример:** Do you offer online banking services?
- **дистракторы:**
    - `Do you offer online the banking services?` — article: **the banking** → `banking`
    - `Do you offers online banking services?` — tense: **offers** → `offer`

### Could you explain the fees?

- **перевод (промпт):** Не могли бы вы объяснить комиссии?
- **эталон-пример:** Could you explain the fees for maintaining this account?
- **дистракторы:**
    - `Could you explained the fees for maintaining this account?` — tense: **explained** → `explain`
    - `Could you explain the fees to maintaining this account?` — preposition: **to maintaining** → `for maintaining`
    - `Could you explain fees for maintaining this account?` — article: **fees** → `the fees`
- **флаги:**
    - ⚠️ **переформулировать** — Обратный перевод даёт «Could you explain the commissions» — ни эталон, ни варианты этого не покрывают; переформулировать.

### interest rate

- **перевод (промпт):** процентная ставка
- **эталон-пример:** What's the current interest rate for savings accounts?
- **дистракторы:**
    - `What are the current interest rate for savings accounts?` — tense: **are the current interest rate** → `is the current interest rate`
    - `What's the current interest rate on savings accounts?` — preposition: **on** → `for`

### minimize fees

- **перевод (промпт):** уменьшить комиссии
- **эталон-пример:** I want to minimize fees on my account.
- **дистракторы:**
    - `I want to minimize fees in my account.` — preposition: **in my account** → `on my account`
    - `I wanted to minimize fees on my account.` — tense: **wanted** → `want`
    - `I want to minimize the fees on my account.` — article: **the fees** → `fees`
- **флаги:**
    - ⚠️ **переформулировать** — Обратный перевод даёт «reduce commissions» — ни эталон, ни варианты этого не покрывают; переформулировать.

### financial advisor

- **перевод (промпт):** финансовый консультант
- **эталон-пример:** I scheduled a meeting with my financial advisor to discuss investments.
- **дистракторы:**
    - `I scheduled a meeting with a financial advisor to discuss investments.` — article: **a financial advisor** → `my financial advisor`
    - `I scheduled a meeting with my financial advisor for discuss investments.` — preposition: **for discuss** → `to discuss`
- **флаги:**
    - ⚠️ **переформулировать** — Обратный перевод даёт «financial consultant» — ни эталон, ни варианты этого не покрывают; переформулировать.

### monthly statement

- **перевод (промпт):** ежемесячная выписка
- **эталон-пример:** I review my monthly statement for any discrepancies.
- **дистракторы:**
    - `I review my monthly statement of any discrepancies.` — preposition: **of any discrepancies** → `for any discrepancies`
    - `I review my monthly statement for the discrepancies.` — article: **the discrepancies** → `any discrepancies`

## Аренда жилья

### view an apartment

- **перевод (промпт):** осмотреть квартиру
- **эталон-пример:** I'm going to view an apartment tomorrow.
- **дистракторы:**
    - `I'm going to view apartment tomorrow.` — article: **view apartment** → `view an apartment`
    - `I was going to view an apartment tomorrow.` — tense: **was going to view** → `am going to view`
    - `I'm going to view for an apartment tomorrow.` — preposition: **view for an apartment** → `view an apartment`

### lease agreement

- **перевод (промпт):** договор аренды
- **эталон-пример:** Please read the lease agreement carefully before signing.
- **дистракторы:**
    - `Please read lease agreement carefully before signing.` — article: **lease agreement** → `the lease agreement`
    - `Please read in the lease agreement carefully before signing.` — preposition: **in the lease agreement** → `the lease agreement`
    - `Please read the lease agreement carefully after signing.` — tense: **after signing** → `before signing`
- **флаги:**
    - ⚠️ **переформулировать** — Обратный перевод даёт «rental agreement» — ни эталон, ни варианты этого не покрывают; переформулировать.

### security deposit

- **перевод (промпт):** залог
- **эталон-пример:** You'll need to pay a security deposit before moving in.
- **принимаемые варианты:**
    - `deposit` — _синоним к слову "залог"._
- **дистракторы:**
    - `You'll need to pay a security deposit after moving in.` — tense: **after moving in** → `before moving in`
    - `You'll need to pay security deposit before moving in.` — article: **security deposit** → `a security deposit`
- **флаги:**
    - ✍️ **не слово → правка** — залога, залог

### utilities included

- **перевод (промпт):** коммунальные платежи включены
- **эталон-пример:** Are utilities included in the rent?
- **принимаемые варианты:**
    - `utilities are included` — _взаимозаменяемый вариант_
- **дистракторы:**
    - `Are utilities include in the rent?` — tense: **include** → `included`
    - `Are the utilities included in the rent?` — article: **the utilities** → `utilities`

### per month

- **перевод (промпт):** в месяц
- **эталон-пример:** The rent is $1000 per month.
- **дистракторы:**
    - `The rent is $1000 for month.` — preposition: **for month** → `per month`
    - `The rent is $1000 each month.` — false_friend: **each month** → `per month`

### first and last month's rent

- **перевод (промпт):** арендная плата за первый и последний месяц
- **эталон-пример:** You'll have to pay the first and last month's rent when you sign the lease.
- **флаги:**
    - ⚠️ **переформулировать** — Обратный перевод даёт «rent for the first and last month» — ни эталон, ни варианты этого не покрывают; переформулировать.

### move-in date

- **перевод (промпт):** дата заселения
- **эталон-пример:** What's your preferred move-in date?
- **дистракторы:**
    - `What's your preferred date move-in?` — word_order: **date move-in** → `move-in date`
    - `What's the preferred move-in date?` — article: **the preferred** → `your preferred`
- **флаги:**
    - ⚠️ **переформулировать** — Обратный перевод даёт «date of moving in» — ни эталон, ни варианты этого не покрывают; переформулировать.

### tenant

- **перевод (промпт):** арендатор, жилец
- **эталон-пример:** The tenant is responsible for minor repairs.
- **принимаемые варианты:**
    - `lessee` — _синоним слова 'арендатор'._
    - `inhabitant` — _синоним слова 'жилец'._
- **дистракторы:**
    - `The tenant are responsible for minor repairs.` — tense: **are** → `is`
    - `The tenant is responsible on minor repairs.` — preposition: **on** → `for`
- **флаги:**
    - ⚠️ **переформулировать** — Обратный перевод даёт «renter» — ни эталон, ни варианты этого не покрывают; переформулировать.

### landlord

- **перевод (промпт):** арендодатель
- **эталон-пример:** The landlord will show you around the apartment.
- **дистракторы:**
    - `The landlord show you around the apartment.` — tense: **show** → `will show`
    - `The landlord will show around you the apartment.` — word_order: **around you** → `you around`
- **флаги:**
    - ⚠️ **переформулировать** — Обратный перевод даёт «lessor» — ни эталон, ни варианты этого не покрывают; переформулировать.

### sign the lease

- **перевод (промпт):** подписать договор аренды
- **эталон-пример:** We signed the lease yesterday.
- **дистракторы:**
    - `We sign the lease yesterday.` — tense: **sign** → `signed`
    - `We signed lease yesterday.` — article: **lease** → `the lease`

### rental application

- **перевод (промпт):** заявка на аренду
- **эталон-пример:** Fill out the rental application before the viewing.
- **дистракторы:**
    - `Fill out rental application before the viewing.` — article: **rental application** → `the rental application`
    - `Fill out the rental application after the viewing.` — tense: **after the viewing** → `before the viewing`
- **флаги:**
    - ⚠️ **переформулировать** — Обратный перевод даёт «application for rent» — ни эталон, ни варианты этого не покрывают; переформулировать.

### monthly rent

- **перевод (промпт):** ежемесячная арендная плата
- **эталон-пример:** The monthly rent must be paid on the first of each month.
- **принимаемые варианты:**
    - `rent paid monthly` — _это аналогичное выражение._
    - `monthly rental fee` — _похожее значение, но с другим словом._
- **дистракторы:**
    - `The monthly rent must be paid at the first of each month.` — preposition: **at the first** → `on the first`
    - `The monthly rent must be paid on first of each month.` — article: **on first** → `on the first`

### maintenance request

- **перевод (промпт):** заявка на ремонт
- **эталон-пример:** You can submit a maintenance request online.
- **принимаемые варианты:**
    - `repair request` — _Синоним: запрос на ремонт._
    - `service request` — _Синоним: заявка на обслуживание._
- **дистракторы:**
    - `You could submit a maintenance request online.` — tense: **could submit** → `can submit`
    - `You can submit a maintenance request in online.` — preposition: **in online** → `online`
    - `You can submit maintenance request online.` — article: **maintenance request** → `a maintenance request`
- **флаги:**
    - ⚠️ **переформулировать** — Обратный перевод даёт «request for repair» — ни эталон, ни варианты этого не покрывают; переформулировать.

### walk-through

- **перевод (промпт):** осмотр (перед сдачей жилья)
- **эталон-пример:** We did a walk-through of the apartment before moving in.
- **принимаемые варианты:**
    - `inspection` — _Синоним, подходящий в контексте._
    - `walkthrough` — _Неформальный вариант термина._
- **дистракторы:**
    - `We did walk-through of the apartment before moving in.` — article: **walk-through** → `a walk-through`
    - `We did a walk-through in the apartment before moving in.` — preposition: **in the apartment** → `of the apartment`

### furnished

- **перевод (промпт):** с мебелью
- **эталон-пример:** Is the apartment furnished or unfurnished?
- **принимаемые варианты:**
    - `equipped` — _сходное значение, также используется для обозначения наличия мебели_
    - `with furniture` — _означает то же самое_
- **дистракторы:**
    - `Is the apartment furnished and unfurnished?` — tense: **and** → `or`
    - `Is the apartment in furnished or unfurnished?` — preposition: **in furnished** → `furnished or unfurnished`
    - `Is apartment furnished or unfurnished?` — article: **apartment** → `the apartment`

### move out

- **перевод (промпт):** съезжать
- **эталон-пример:** When do you plan to move out?
- **дистракторы:**
    - `When do you plan for to move out?` — modal_to: **for to** → `to`
- **флаги:**
    - ⚠️ **переформулировать** — Обратный перевод даёт «to leave» — ни эталон, ни варианты этого не покрывают; переформулировать.

### notice period

- **перевод (промпт):** период уведомления
- **эталон-пример:** You have to give a one-month notice period before moving out.
- **дистракторы:**
    - `You have to give the one-month notice period before moving out.` — article: **the one-month** → `a one-month`
    - `You have to give a one-month notices period before moving out.` — false_friend: **notices** → `notice`

### shared accommodation

- **перевод (промпт):** совместное жильё
- **эталон-пример:** I'm looking for shared accommodation in the city center.
- **дистракторы:**
    - `I'm looking for the shared accommodation in the city center.` — article: **the shared accommodation** → `shared accommodation`
    - `I'm looking for shared accommodation at the city center.` — preposition: **at the city center** → `in the city center`
- **флаги:**
    - ⚠️ **переформулировать** — Обратный перевод даёт «shared housing» — ни эталон, ни варианты этого не покрывают; переформулировать.

### pay the rent

- **перевод (промпт):** платить аренду
- **эталон-пример:** I pay the rent by bank transfer every month.
- **принимаемые варианты:**
    - `pay rent` — _можно использовать в этом контексте_
    - `make a rent payment` — _эквивалентная формулировка_
    - `pay for the rent` — _синоним, подразумевающий то же самое_
- **дистракторы:**
    - `I pay the rent in bank transfer every month.` — preposition: **in bank transfer** → `by bank transfer`
    - `I pay rent the by bank transfer every month.` — word_order: **rent the by** → `the rent by`
    - `I pay the rent by bank transfers every month.` — tense: **by bank transfers** → `by bank transfer`

### sublet

- **перевод (промпт):** сдавать в субаренду
- **эталон-пример:** Do you have permission to sublet the apartment?
- **принимаемые варианты:**
    - `sublease` — _синоним слова "субаренда"._
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
- **флаги:**
    - ⚠️ **переформулировать** — Обратный перевод даёт «I would like to sign up for a reception» — ни эталон, ни варианты этого не покрывают; переформулировать.

### What are your symptoms?

- **перевод (промпт):** Какие у вас симптомы?
- **эталон-пример:** What are your symptoms today?
- **дистракторы:**
    - `What today are your symptoms?` — word_order: **today** → `your symptoms today?`
    - `What are you symptoms today?` — article: **you** → `your`
- **флаги:**
    - ⚠️ **переформулировать** — Обратный перевод даёт «What symptoms do you have?» — ни эталон, ни варианты этого не покрывают; переформулировать.

### I have a fever.

- **перевод (промпт):** У меня жар.
- **эталон-пример:** I have a fever and feel very weak.
- **дистракторы:**
    - `I have fever and feel very weak.` — article: **fever** → `a fever`
    - `I have a fever and feels very weak.` — tense: **feels** → `feel`
    - `I have a fever and I very weak.` — word_order: **I very weak** → `I am very weak`

### cold

- **перевод (промпт):** простуда
- **эталон-пример:** I think I have a cold.
- **дистракторы:**
    - `I think I have cold.` — article: **cold** → `a cold`
    - `I think I have the cold.` — article: **the cold** → `cold`

### prescription

- **перевод (промпт):** рецепт (на лекарство)
- **эталон-пример:** The doctor gave me a prescription for antibiotics.
- **дистракторы:**
    - `The doctor gave me a prescription on antibiotics.` — preposition: **on antibiotics** → `for antibiotics`
    - `The doctor gave me prescription for antibiotics.` — article: **prescription** → `a prescription`
- **флаги:**
    - ⚠️ **переформулировать** — Обратный перевод даёт «recipe» — ни эталон, ни варианты этого не покрывают; переформулировать.

### pharmacy

- **перевод (промпт):** аптека
- **эталон-пример:** You can buy this medicine at any pharmacy.
- **дистракторы:**
    - `You can buy this medicine in any pharmacy.` — preposition: **in any pharmacy** → `at any pharmacy`
    - `You can buy this medicine at the pharmacy.` — article: **the pharmacy** → `any pharmacy`

### I have a headache.

- **перевод (промпт):** У меня болит голова.
- **эталон-пример:** I've had a headache since this morning.
- **дистракторы:**
    - `I had a headache since this morning.` — tense: **had** → `have`

### How long have you been feeling this way?

- **перевод (промпт):** Как давно вы так себя чувствуете?
- **эталон-пример:** How long have you been feeling this way before coming here?
- **дистракторы:**
    - `How long have you been feeling this way before you came here?` — tense: **before you came here** → `before coming here`

### cough

- **перевод (промпт):** кашель
- **эталон-пример:** She has a bad cough.
- **принимаемые варианты:**
    - `coughing` — _Форма 'кашлять', подходящая в контексте._
    - `coughs` — _Форма множественного числа для 'кашель'._
- **дистракторы:**
    - `She has bad cough.` — article: **bad cough** → `a bad cough`
    - `She has a cough bad.` — word_order: **cough bad** → `bad cough`

### I need a refill.

- **перевод (промпт):** Мне нужно обновить рецепт.
- **эталон-пример:** I need a refill on my prescription, please.
- **дистракторы:**
    - `I need a refill in my prescription, please.` — preposition: **in** → `on`
    - `I need refill on my prescription, please.` — article: **refill** → `a refill`
- **флаги:**
    - ⚠️ **переформулировать** — Обратный перевод даёт «I need to update the prescription» — ни эталон, ни варианты этого не покрывают; переформулировать.
    - ✍️ **не слово → правка** — обновить — исправное слово, но в этом контексте использовано неправильно; должно быть "выписать" или "обновить рецепт".

### How can I help you?

- **перевод (промпт):** Чем я могу вам помочь?
- **эталон-пример:** Welcome to the pharmacy. How can I help you?
- **дистракторы:**
    - `Welcome to the pharmacy. How can I help you to?` — modal_to: **help you to** → `help you`
    - `Welcome to the pharmacy. How can I helping you?` — tense: **helping you** → `help you`
- **флаги:**
    - ⚠️ **переформулировать** — Обратный перевод даёт «How can I assist you» — ни эталон, ни варианты этого не покрывают; переформулировать.

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
- **дистракторы:**
    - `He's been running temperature since last night.` — article: **running temperature** → `running a temperature`
    - `He has been running a temperature since last night.` — tense: **has been running** → `has been running`
- **флаги:**
    - ✍️ **не слово → правка** — температурить; правильный вариант: иметь температуру.

### My throat hurts.

- **перевод (промпт):** У меня болит горло.
- **эталон-пример:** My throat hurts every time I swallow.
- **дистракторы:**
    - `My throat hurt every time I swallow.` — tense: **hurt** → `hurts`
    - `My throat hurts every time I swallowing.` — modal_to: **I swallowing** → `I swallow`
- **флаги:**
    - ⚠️ **переформулировать** — Обратный перевод даёт «I have a sore throat» — ни эталон, ни варианты этого не покрывают; переформулировать.

### pharmacist

- **перевод (промпт):** фармацевт
- **эталон-пример:** The pharmacist can help you find the right medicine.
- **принимаемые варианты:**
    - `chemist` — _Используется в некоторых странах вместо фармацевт._
    - `druggist` — _Общее слово для фармацевта._
- **дистракторы:**
    - `The pharmacist helps you find the right medicine.` — tense: **helps** → `can help`
    - `The pharmacist can help find the right medicine.` — article: **help find** → `help you find`

### Please take a seat.

- **перевод (промпт):** Пожалуйста, присаживайтесь.
- **эталон-пример:** Please take a seat, the doctor will see you shortly.
- **дистракторы:**
    - `Please take a seat, doctor will see you shortly.` — article: **doctor** → `the doctor`
    - `Please take a seat, the doctor will sees you shortly.` — tense: **sees** → `see`

### fill a prescription

- **перевод (промпт):** выполнить рецепт (в аптеке)
- **эталон-пример:** I need to fill a prescription for my medication.
- **принимаемые варианты:**
    - `obtain a prescription` — _похожий смысл, также означает получение рецепта._
    - `get a prescription` — _другое выражение для получения рецепта._
- **дистракторы:**
    - `I need fill a prescription for my medication.` — article: **fill** → `to fill`
    - `I need to fill prescription for my medication.` — article: **fill prescription** → `to fill a prescription`
- **флаги:**
    - ⚠️ **переформулировать** — Обратный перевод даёт «to perform a prescription» — ни эталон, ни варианты этого не покрывают; переформулировать.

### take medicine

- **перевод (промпт):** принимать лекарства
- **эталон-пример:** Please remember to take your medicine twice a day.
- **принимаемые варианты:**
    - `take your medication` — _то же самое по смыслу_
    - `take your drugs` — _также обозначает прием лекарств_
- **дистракторы:**
    - `Please remember to take the medicine twice a day.` — article: **the medicine** → `your medicine`
    - `Please remember to take your medicine two times a day.` — false_friend: **two times** → `twice`
    - `Please remembered to take your medicine twice a day.` — tense: **remembered** → `remember`

### I have an appointment at 3 PM.

- **перевод (промпт):** У меня запись на 3 часа дня.
- **эталон-пример:** I have an appointment at 3 PM with the dentist.
- **дистракторы:**
    - `I have an appointment in 3 PM with the dentist.` — preposition: **in 3 PM** → `at 3 PM`
    - `I have appointment at 3 PM with the dentist.` — article: **appointment** → `an appointment`
- **флаги:**
    - ⚠️ **переформулировать** — Обратный перевод даёт «I have an appointment at 3 o'clock in the afternoon» — ни эталон, ни варианты этого не покрывают; переформулировать.

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
    - `Could you tell me more about the team with which I would be working?` — preposition: **with which** → `with`
    - `Could you tell me more about team I would be working with?` — article: **team** → `the team`

### What programming languages are you proficient in?

- **перевод (промпт):** Какими языками программирования вы владеете?
- **эталон-пример:** What programming languages are you proficient in?
- **дистракторы:**
    - `What programming languages are you proficient at?` — preposition: **proficient at** → `proficient in`
    - `What programming languages is you proficient in?` — tense: **is you** → `are you`
- **флаги:**
    - ⚠️ **переформулировать** — Обратный перевод даёт «What programming languages do you have mastery over?» — ни эталон, ни варианты этого не покрывают; переформулировать.

### work experience

- **перевод (промпт):** опыт работы
- **эталон-пример:** My work experience includes five years as a software developer.
- **дистракторы:**
    - `My work experience include five years as a software developer.` — tense: **include** → `includes`
    - `My work experience includes five years as software developer.` — article: **as software developer** → `as a software developer`
- **флаги:**
    - ⚠️ **переформулировать** — Обратный перевод даёт «experience of work» — ни эталон, ни варианты этого не покрывают; переформулировать.

### What are the main responsibilities of this role?

- **перевод (промпт):** Каковы основные обязанности в этой должности?
- **эталон-пример:** What are the main responsibilities of this role?
- **дистракторы:**
    - `What are the responsibilities main of this role?` — word_order: **responsibilities main** → `main responsibilities`
    - `What were the main responsibilities of this role?` — tense: **were** → `are`
    - `What is the main responsibilities of this role?` — article: **the main responsibilities** → `the main responsibility`
- **флаги:**
    - ⚠️ **переформулировать** — Обратный перевод даёт «What are the main duties in this position?» — ни эталон, ни варианты этого не покрывают; переформулировать.

### I'm confident in my problem-solving skills.

- **перевод (промпт):** Я уверен в своих навыках решения проблем.
- **эталон-пример:** I'm confident in my problem-solving skills and enjoy tackling complex issues.
- **принимаемые варианты:**
    - `I am confident in my problem-solving abilities.` — _Синонимы."},{_
- **дистракторы:**
    - `I'm confident in my problem-solving skills and enjoy tackling complex issue.` — article: **complex issue** → `complex issues`
    - `I'm confident in my problem-solving skills and enjoy to tackle complex issues.` — modal_to: **to tackle** → `tackling`
- **флаги:**
    - ⚠️ **переформулировать** — Обратный перевод даёт «I am confident in my skills of solving problems» — ни эталон, ни варианты этого не покрывают; переформулировать.

### offer letter

- **перевод (промпт):** письмо с предложением о работе
- **эталон-пример:** I was thrilled to receive the offer letter for the position.
- **дистракторы:**
    - `I was thrilled to receive the offer letters for the position.` — tense: **offer letters** → `offer letter`
    - `I was thrilled to receive offer letter for the position.` — article: **offer letter** → `the offer letter`
- **флаги:**
    - ⚠️ **переформулировать** — Обратный перевод даёт «job offer letter» — ни эталон, ни варианты этого не покрывают; переформулировать.

### collaborative environment

- **перевод (промпт):** среда, способствующая сотрудничеству
- **эталон-пример:** I thrive in a collaborative environment where teamwork is valued.
- **дистракторы:**
    - `I thrive in a collaborative environments where teamwork is valued.` — article: **a collaborative environments** → `a collaborative environment`
- **флаги:**
    - ⚠️ **переформулировать** — Обратный перевод даёт «an environment that promotes cooperation» — ни эталон, ни варианты этого не покрывают; переформулировать.

### I have experience with agile methodologies.

- **перевод (промпт):** У меня есть опыт работы с гибкими методологиями.
- **эталон-пример:** I have experience with agile methodologies, which helps in adapting to changes quickly.
- **дистракторы:**
    - `I has experience with agile methodologies, which helps in adapting to changes quickly.` — tense: **I has** → `I have`
    - `I have experience with agile methodologies, which help in adapting to changes quickly.` — tense: **help** → `helps`
- **флаги:**
    - ⚠️ **переформулировать** — Обратный перевод даёт «I have experience» — ни эталон, ни варианты этого не покрывают; переформулировать.

### job offer

- **перевод (промпт):** предложение о работе
- **эталон-пример:** The company made me a job offer after the final interview.
- **дистракторы:**
    - `The company made me job offer after the final interview.` — article: **job offer** → `a job offer`
    - `The company makes me a job offer after the final interview.` — tense: **makes** → `made`
    - `The company made me a job offer to the final interview.` — preposition: **to the final interview** → `after the final interview`

### Can you tell me about the career growth opportunities?

- **перевод (промпт):** Можете рассказать о возможностях карьерного роста?
- **эталон-пример:** Can you tell me about the career growth opportunities at your company?
- **принимаемые варианты:**
    - `Could you tell me about the career growth opportunities?` — _Синоним с другим модальным глаголом._
    - `Can you explain about the career growth opportunities?` — _Синоним, передающий тот же смысл._
- **дистракторы:**
    - `Can you tell me about the career growth opportunity at your company?` — article: **the career growth opportunity** → `the career growth opportunities`

### What are your strengths and weaknesses?

- **перевод (промпт):** Каковы ваши сильные и слабые стороны?
- **эталон-пример:** During the interview, they asked me about my strengths and weaknesses.
- **дистракторы:**
    - `During the interview, they asked about my strengths and weaknesses me.` — word_order: **me.** → `me about`
    - `During the interview, they ask me about my strengths and weaknesses.` — tense: **ask** → `asked`
    - `During the interview, they asked me about the strengths and weaknesses.` — article: **the strengths and weaknesses** → `my strengths and weaknesses`
- **флаги:**
    - ⚠️ **переформулировать** — Обратный перевод даёт «What are your strong and weak sides?» — ни эталон, ни варианты этого не покрывают; переформулировать.

### long-term goals

- **перевод (промпт):** долгосрочные цели
- **эталон-пример:** In the interview, I discussed my long-term goals in the IT industry.
- **дистракторы:**
    - `In the interview, I discussed my the long-term goals in the IT industry.` — article: **my the** → `my long-term`

### multitasking ability

- **перевод (промпт):** способность к многозадачности
- **эталон-пример:** I mentioned my multitasking ability as one of my strengths.
- **дистракторы:**
    - `I mentioned my multitasking ability as one of strengths.` — article: **one of strengths** → `one of my strengths`
    - `I mention my multitasking ability as one of my strengths.` — tense: **I mention** → `I mentioned`
    - `I mentioned about my multitasking ability as one of my strengths.` — preposition: **mentioned about** → `mentioned`
- **флаги:**
    - ⚠️ **переформулировать** — Обратный перевод даёт «ability to multitask» — ни эталон, ни варианты этого не покрывают; переформулировать.

### get along with

- **перевод (промпт):** ладити з
- **эталон-пример:** I get along with my team well, making collaboration effective.
- **дистракторы:**
    - `I get along my team well, making collaboration effective.` — preposition: **along my team** → `along with my team`
    - `I get along with my team good, making collaboration effective.` — false_friend: **good** → `well`
    - `I gets along with my team well, making collaboration effective.` — tense: **gets** → `get`
- **флаги:**
    - 🇺🇦 **украинизм → переген** — Украинские буквы в русском поле (і є): «Я добре лажу зі своєю командою, що робить співпрацю ефективною.».

### focus on results

- **перевод (промпт):** фокусироваться на результатах
- **эталон-пример:** I focus on results, ensuring that projects meet their objectives.
- **принимаемые варианты:**
    - `concentrate on results` — _эквивалентное выражение_
- **дистракторы:**
    - `I focus in results, ensuring that projects meet their objectives.` — preposition: **in** → `on`
    - `I focuses on results, ensuring that projects meet their objectives.` — tense: **focuses** → `focus`

### comfortable with

- **перевод (промпт):** уверенно работаю с
- **эталон-пример:** I'm comfortable with fast-paced environments and enjoy challenges.
- **дистракторы:**
    - `I'm comfortable with the fast-paced environments and enjoy challenges.` — article: **the fast-paced** → `fast-paced`
    - `I'm comfortable with fast-paced environment and enjoy challenges.` — article: **fast-paced environment** → `fast-paced environments`
- **флаги:**
    - ⚠️ **переформулировать** — Обратный перевод даёт «I work confidently with» — ни эталон, ни варианты этого не покрывают; переформулировать.
    - ✍️ **не слово → правка** — «уверенно работаю с» — правильный вариант «комфортно работать с».

### notice period

- **перевод (промпт):** период уведомления
- **эталон-пример:** You have to give a one-month notice period before moving out.
- **дистракторы:**
    - `You have to give the one-month notice period before moving out.` — article: **the one-month** → `a one-month`
    - `You have to give a one-month notices period before moving out.` — false_friend: **notices** → `notice`

### hands-on experience

- **перевод (промпт):** практический опыт
- **эталон-пример:** I have hands-on experience with JavaScript frameworks in previous projects.
- **дистракторы:**
    - `I have a hands-on experience with JavaScript frameworks in previous projects.` — article: **a hands-on experience** → `hands-on experience`
    - `I have hands-on experience in JavaScript frameworks in previous projects.` — preposition: **in JavaScript frameworks** → `with JavaScript frameworks`
- **флаги:**
    - ⚠️ **переформулировать** — Обратный перевод даёт «practical experience» — ни эталон, ни варианты этого не покрывают; переформулировать.

### When can you start?

- **перевод (промпт):** Когда вы можете начать?
- **эталон-пример:** They asked me when I could start if offered the position.
- **дистракторы:**
    - `They asked me when I could start if to offered the position.` — modal_to: **if to offered** → `if offered`
    - `They asked me when I start if offered the position.` — tense: **when I start** → `when I could start`
- **флаги:**
    - ⚠️ **переформулировать** — Обратный перевод даёт «When can you begin?» — ни эталон, ни варианты этого не покрывают; переформулировать.

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
- **дистракторы:**
    - `I would like to reserve table for two at 7 PM.` — article: **reserve table** → `reserve a table`
    - `I would like to reserve a table for two on 7 PM.` — preposition: **on 7 PM** → `at 7 PM`
- **флаги:**
    - ⚠️ **переформулировать** — Обратный перевод даёт «to book a table» — ни эталон, ни варианты этого не покрывают; переформулировать.

### What would you like to order?

- **перевод (промпт):** Что бы вы хотели заказать?
- **эталон-пример:** What would you like to order?
- **дистракторы:**
    - `What would you like to the order?` — preposition: **to the order** → `to order`
    - `What would you like order?` — modal_to: **like order** → `like to order`

### I'll have the chicken salad.

- **перевод (промпт):** Я буду куриный салат.
- **эталон-пример:** I'll have the chicken salad, please.
- **принимаемые варианты:**
    - `I’ll take the chicken salad.` — _Также означает то же самое._
    - `I would like the chicken salad.` — _Эквивалентная формулировка с тем же значением._
- **дистракторы:**
    - `I'll have chicken salad, please.` — article: **chicken salad** → `the chicken salad`
    - `I'll have the chicken salads, please.` — word_order: **the chicken salads** → `the chicken salad`
- **флаги:**
    - ✍️ **не слово → правка** — «куриный» должно быть «куриный» в контексте, но это корректно.

### menu

- **перевод (промпт):** меню
- **эталон-пример:** Could you bring me the menu, please?
- **дистракторы:**
    - `Could you bring me a menu, please?` — article: **a menu** → `the menu`

### How would you like to pay?

- **перевод (промпт):** Как вы хотели бы оплатить?
- **эталон-пример:** How would you like to pay, by cash or card?
- **принимаемые варианты:**
    - `How do you want to pay?` — _То же самое значение._
    - `How would you prefer to pay?` — _Синонимичное выражение._
- **дистракторы:**
    - `How would you like to pays, by cash or card?` — tense: **pays** → `pay`
    - `How would you like to pay, in cash or card?` — preposition: **in cash** → `by cash`

### Could I see the wine list?

- **перевод (промпт):** Могу я посмотреть винную карту?
- **эталон-пример:** Could I see the wine list, please?
- **принимаемые варианты:**
    - `Can I see the wine list?` — _Синонимичное выражение._
    - `May I see the wine list?` — _Синонимичное выражение._
- **дистракторы:**
    - `Could I see wine list, please?` — article: **wine list** → `the wine list`
    - `Could I see the list of wine, please?` — word_order: **the list of wine** → `the wine list`
    - `Could I saw the wine list, please?` — tense: **saw** → `see`
- **флаги:**
    - ⚠️ **переформулировать** — Обратный перевод даёт «Can I look at the wine menu?» — ни эталон, ни варианты этого не покрывают; переформулировать.
    - ✍️ **не слово → правка** — «винную карту» — «винная карта».

### I'd like to make a reservation.

- **перевод (промпт):** Я бы хотел сделать бронь.
- **эталон-пример:** I'd like to make a reservation for Saturday night.
- **дистракторы:**
    - `I'd like to make a reservation at Saturday night.` — preposition: **at Saturday night** → `for Saturday night`
    - `I'd like to make reservation for Saturday night.` — article: **reservation** → `a reservation`

### tip

- **перевод (промпт):** чаевые
- **эталон-пример:** It's customary to leave a tip in restaurants here.
- **дистракторы:**
    - `It's customary to leave a tip at restaurants here.` — preposition: **at restaurants** → `in restaurants`
    - `It's customary to leave the tip in restaurants here.` — article: **the tip** → `a tip`
- **флаги:**
    - ⚠️ **переформулировать** — Обратный перевод даёт «gratuity» — ни эталон, ни варианты этого не покрывают; переформулировать.

### Can I get this to go?

- **перевод (промпт):** Можно это с собой?
- **эталон-пример:** I'd like the pasta to go, please.
- **принимаемые варианты:**
    - `Can I have this to go?` — _Синоним, имеющий то же значение._
    - `Can I take this to go?` — _Синоним, имеющий то же значение._
- **дистракторы:**
    - `I like the pasta to go, please.` — tense: **like the pasta** → `would like the pasta`
    - `I'd like the pasta for go, please.` — preposition: **for go** → `to go`
- **флаги:**
    - ⚠️ **переформулировать** — Обратный перевод даёт «Can I have this to take away?» — ни эталон, ни варианты этого не покрывают; переформулировать.

### delivery

- **перевод (промпт):** доставка
- **эталон-пример:** The delivery should arrive in 30 minutes.
- **дистракторы:**
    - `The delivery must arrive in 30 minutes.` — modal_to: **must** → `should`
    - `The delivery should arrives in 30 minutes.` — tense: **arrives** → `arrive`

### starter

- **перевод (промпт):** закуска
- **эталон-пример:** For a starter, I would recommend the soup.
- **флаги:**
    - ⚠️ **переформулировать** — Обратный перевод даёт «appetizer» — ни эталон, ни варианты этого не покрывают; переформулировать.

### main course

- **перевод (промпт):** основное блюдо
- **эталон-пример:** I'll have the steak as my main course.
- **дистракторы:**
    - `I'll have steak as my main course.` — article: **steak** → `the steak`
    - `I'll have the steak as my main courses.` — tense: **courses** → `course`
- **флаги:**
    - ⚠️ **переформулировать** — Обратный перевод даёт «main dish» — ни эталон, ни варианты этого не покрывают; переформулировать.

### Could we have the bill, please?

- **перевод (промпт):** Можно нам счёт, пожалуйста?
- **эталон-пример:** Could we have the bill, please?
- **дистракторы:**
    - `Could we have bill, please?` — article: **bill** → `the bill`
    - `Could we has the bill, please?` — tense: **has** → `have`
- **флаги:**
    - ⚠️ **переформулировать** — Обратный перевод даёт «Can we have the check, please?» — ни эталон, ни варианты этого не покрывают; переформулировать.

### order in

- **перевод (промпт):** заказать на дом
- **эталон-пример:** Let's order in tonight instead of cooking.
- **дистракторы:**
    - `Let's order in tonight instead of the cooking.` — article: **the cooking** → `cooking`
    - `Let's order in tonight instead of cook.` — tense: **cook** → `cooking`
    - `Let's order in tonight instead of to cook.` — modal_to: **to cook** → `cooking`
- **флаги:**
    - ⚠️ **переформулировать** — Обратный перевод даёт «to order food at home» — ни эталон, ни варианты этого не покрывают; переформулировать.

### Are you ready to order?

- **перевод (промпт):** Вы готовы заказать?
- **эталон-пример:** Are you ready to order, or do you need more time?
- **дистракторы:**
    - `Are you ready for order, or do you need more time?` — preposition: **for order** → `to order`
    - `Are you ready to ordering, or do you need more time?` — tense: **to ordering** → `to order`

### Would you like anything to drink?

- **перевод (промпт):** Вы бы хотели что-нибудь выпить?
- **эталон-пример:** Would you like anything to drink with your meal?
- **принимаемые варианты:**
    - `Do you want something to drink?` — _Это синоним._
    - `Would you like something to drink?` — _Это синоним._
- **дистракторы:**
    - `Would you like anything to drink with meal?` — article: **with meal** → `with your meal`
    - `Would you like anything drink with your meal?` — modal_to: **anything drink** → `anything to drink`
- **флаги:**
    - ⚠️ **переформулировать** — Обратный перевод даёт «Would you like to drink something?» — ни эталон, ни варианты этого не покрывают; переформулировать.

### cutlery

- **перевод (промпт):** столовые приборы
- **эталон-пример:** The cutlery is on the table.
- **принимаемые варианты:**
    - `eating utensils` — _синоним на английском._
    - `silverware` — _синоним на английском._
- **дистракторы:**
    - `The cutlery is at the table.` — preposition: **at** → `on`
    - `The cutlery are on the table.` — tense: **are** → `is`
- **флаги:**
    - ⚠️ **переформулировать** — Обратный перевод даёт «tableware» — ни эталон, ни варианты этого не покрывают; переформулировать.

### Can I have some water, please?

- **перевод (промпт):** Можно мне воды, пожалуйста?
- **эталон-пример:** Can I have some water, please?
- **дистракторы:**
    - `Can I had some water, please?` — tense: **had** → `have`
    - `Can I have some water for, please?` — preposition: **for** → `please`
    - `Can I have water, please?` — article: **water** → `some water`

### gratuity

- **перевод (промпт):** чаевые (формально)
- **эталон-пример:** Gratuity is not included in the bill.
- **принимаемые варианты:**
    - `tip` — _Синоним, используемый в разговорной речи._
    - `service charge` — _Часто употребляется в ресторанах._
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
    - `The waiter will serve the meal shortly.` — article: **the meal** → `your meal`

