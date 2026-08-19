<!-- snapshot: 2026-08-19T09:23:11+00:00 · head: 08c091f -->
# Выгрузка станка на вычитку

Снимок: **2026-08-19T09:23:11+00:00** · HEAD: `08c091f` · версия генератора: `enrich-v1-topup3`.

Снимок старше правок в базе — выгрузку надо снять заново: то, что здесь написано, было
верно на момент снимка и с тех пор могло быть починено.

Термины без вариантов, дистракторов и флагов в выгрузку не попадают — это рабочий список,
а не дамп базы. Колонка «флаги» — то, что требует решения человека.

## Знакомство и small talk

### Nice to meet you

- **перевод (промпт):** Приятно познакомиться
- **эталон-пример:** Nice to meet you, I'm Sarah.
- **принимаемые варианты:**
    - `Glad to meet you` — _Синонимично выражение._
    - `Pleased to meet you` — _Синонимично выражение._

### How is the weather?

- **перевод (промпт):** Как погода?
- **эталон-пример:** How is the weather in your city right now?
- **дистракторы:**
    - `How is weather in your city right now?` — article: **weather** → `the weather`

### Do you have any hobbies?

- **перевод (промпт):** У вас есть какие-нибудь хобби?
- **эталон-пример:** Do you have any hobbies you enjoy?
- **принимаемые варианты:**
    - `Do you have any pastimes?` — _Синонимы._
- **дистракторы:**
    - `Do you have any hobbies you enjoys?` — tense: **enjoys** → `enjoy`
    - `Do you have the hobbies you enjoy?` — article: **the hobbies** → `any hobbies`
    - `Do you have hobbies any you enjoy?` — word_order: **hobbies any you** → `any hobbies you`

### What do you like to do in your free time?

- **перевод (промпт):** Что вы любите делать в свободное время?
- **эталон-пример:** What do you like to do in your free time?
- **дистракторы:**
    - `What do you likes to do in your free time?` — tense: **likes** → `like`
    - `What do you like to do at your free time?` — preposition: **at** → `in`

### It's a bit chilly

- **перевод (промпт):** Немного прохладно
- **эталон-пример:** It's a bit chilly for a walk.
- **дистракторы:**
    - `It's bit chilly for a walk.` — article: **bit** → `a bit`

### Are you from around here?

- **перевод (промпт):** Вы местный?
- **эталон-пример:** Are you from around here, or are you visiting?
- **принимаемые варианты:**
    - `Are you local?` — _Синоним с тем же значением._
- **дистракторы:**
    - `Are you from around here, or are you visit?` — tense: **visit** → `visiting`

### How do you do?

- **перевод (промпт):** Как поживаете?
- **эталон-пример:** How do you do? Nice to meet you.
- **дистракторы:**
    - `How do you do? Nice to met you.` — tense: **met** → `meet`
    - `How do you do? The nice to meet you.` — article: **the nice** → `nice`
    - `How do you do? Nice with meet you.` — preposition: **with** → `to`

### get to know

- **перевод (промпт):** узнать (кого-то/что-то)
- **эталон-пример:** I enjoy getting to know people's stories and backgrounds.
- **дистракторы:**
    - `I enjoy getting to know the people's stories and backgrounds.` — article: **the people's** → `people's`
    - `I enjoy getting to knowing people's stories and backgrounds.` — modal_to: **to knowing** → `to know`

### start a conversation

- **перевод (промпт):** начать разговор
- **эталон-пример:** He always knows how to start a conversation.
- **принимаемые варианты:**
    - `initiate a conversation` — _Синонимы._
    - `engage in a conversation` — _Синонимы._
- **дистракторы:**
    - `He always knows how to начать a conversation.` — false_friend: **начать** → `start`
    - `He always know how to start a conversation.` — tense: **know** → `knows`

### chat

- **перевод (промпт):** беседовать, болтать
- **эталон-пример:** We sat in the coffee shop and had a nice chat.
- **принимаемые варианты:**
    - `talk` — _Синоним к слову 'болтать'._
    - `conversation` — _Синоним к слову 'беседовать'._
- **дистракторы:**
    - `We sat in the coffee shop and had nice chat.` — article: **nice chat** → `a nice chat`
    - `We sat in a coffee shop and had a nice chat.` — article: **a coffee shop** → `the coffee shop`

### Meet someone new

- **перевод (промпт):** познакомиться с кем-то новым
- **эталон-пример:** It's exciting to meet someone new at a party.
- **дистракторы:**
    - `It's exciting to meet someone new in a party.` — preposition: **in a party** → `at a party`

### Talk about myself

- **перевод (промпт):** рассказать о себе
- **эталон-пример:** In interviews, I often talk about myself and my experiences.
- **дистракторы:**
    - `In interviews, I often talk about about myself and my experiences.` — word_order: **about about** → `about`

### Sorry, I didn't catch your name

- **перевод (промпт):** Извините, я не расслышал ваше имя
- **эталон-пример:** Sorry, I didn't catch your name the first time.
- **дистракторы:**
    - `Sorry, I didn't catch your name the first times.` — tense: **the first times** → `the first time`
    - `Sorry, I didn't catch the name your first time.` — article: **the name your** → `your name the`

### break the ice

- **перевод (промпт):** растопить лёд
- **эталон-пример:** Telling a joke can help break the ice in a new environment.
- **принимаемые варианты:**
    - `make a start` — _означает то же самое_
    - `warm up the atmosphere` — _означает то же самое_
- **дистракторы:**
    - `Telling a joke can help to break the ice in a new environment.` — modal_to: **to break** → `break`
    - `Telling a joke can help break ice in a new environment.` — article: **break ice** → `break the ice`

### lovely

- **перевод (промпт):** прекрасный
- **эталон-пример:** The weather has been lovely all week.
- **дистракторы:**
    - `The weather has been the lovely all week.` — article: **the lovely** → `lovely`
    - `The weather is been lovely all week.` — tense: **is been** → `has been`

### Have you been here before?

- **перевод (промпт):** Вы были здесь раньше?
- **эталон-пример:** Have you been here before, or is this your first time?
- **дистракторы:**
    - `Have you been here before, or is the this your first time?` — article: **the this** → `this`
    - `Have you been here before, or are this your first time?` — tense: **are this** → `is this`

### weather

- **перевод (промпт):** погода
- **эталон-пример:** The weather is nice today.
- **дистракторы:**
    - `The weather is the nice today.` — article: **the nice** → `nice`
    - `The weather are nice today.` — tense: **are** → `is`

## Телефон и мессенджеры

### make a call

- **перевод (промпт):** позвонить
- **эталон-пример:** I need to make a call to confirm our meeting.
- **дистракторы:**
    - `I need to make call to confirm our meeting.` — article: **make call** → `make a call`

### leave a message

- **перевод (промпт):** оставить сообщение
- **эталон-пример:** Can you leave a message after the beep?
- **дистракторы:**
    - `Can you leave message after the beep?` — article: **leave message** → `leave a message`

### send a text

- **перевод (промпт):** отправить сообщение
- **эталон-пример:** I will send a text to let her know.
- **дистракторы:**
    - `I will send text to let her know.` — article: **text** → `a text`

### check your messages

- **перевод (промпт):** проверить свои сообщения
- **эталон-пример:** Don't forget to check your messages when you get home.
- **дистракторы:**
    - `Don't forget to check your message when you get home.` — article: **message** → `messages`
    - `Don't forget to check in your messages when you get home.` — preposition: **in your messages** → `your messages`
    - `Don't forget to checked your messages when you get home.` — tense: **checked** → `check`

### give someone a call

- **перевод (промпт):** позвонить кому-либо
- **эталон-пример:** I'll give you a call tomorrow evening.
- **дистракторы:**
    - `I give you a call tomorrow evening.` — tense: **I give** → `I'll give`
    - `I'll give you call tomorrow evening.` — article: **you call** → `you a call`
    - `I'll give to you a call tomorrow evening.` — preposition: **give to you** → `give you`

### call back

- **перевод (промпт):** перезвонить
- **эталон-пример:** Could you call me back later?
- **принимаемые варианты:**
    - `return the call` — _Синоним._
    - `ring back` — _Синоним._
- **дистракторы:**
    - `Could you call back me later?` — word_order: **call back me** → `call me back`

### mute the phone

- **перевод (промпт):** отключить звук на телефоне
- **эталон-пример:** Please mute your phone during the meeting.
- **дистракторы:**
    - `Please mute the phone during the meeting.` — article: **the** → `your`
    - `Please mute your phone in the meeting.` — preposition: **in** → `during`

### message thread

- **перевод (промпт):** цепочка сообщений
- **эталон-пример:** I need to scroll through the message thread to find that information.
- **дистракторы:**
    - `I need to scroll through the message thread for find that information.` — preposition: **for** → `to`
    - `I need to scroll through the message thread to find that informations.` — false_friend: **informations** → `information`

### save a contact

- **перевод (промпт):** сохранить контакт
- **эталон-пример:** I'll save your contact for future reference.
- **дистракторы:**
    - `I'll save contact for future reference.` — article: **contact** → `your contact`

### send a voice message

- **перевод (промпт):** отправить голосовое сообщение
- **эталон-пример:** You can send a voice message if you can't type.
- **дистракторы:**
    - `You can send voice message if you can't type.` — article: **voice message** → `a voice message`
    - `You can send a voice message at you can't type.` — preposition: **at** → `if`

### on the line

- **перевод (промпт):** на линии
- **эталон-пример:** Please hold on, she's on the line with another call.
- **дистракторы:**
    - `Please hold on, she's on line with another call.` — article: **on line** → `on the line`

### get through to

- **перевод (промпт):** дозвониться до
- **эталон-пример:** I tried calling, but I couldn't get through to him.
- **дистракторы:**
    - `I tried calling, but I couldn't get through to the him.` — article: **the him** → `him`
    - `I tried calling, but I can't get through to him.` — tense: **can't** → `couldn't`

### video call

- **перевод (промпт):** видеозвонок
- **эталон-пример:** She prefers video calls to stay in touch with family.
- **дистракторы:**
    - `She prefer video calls to stay in touch with family.` — tense: **prefer** → `prefers`
    - `She prefers video calls for stay in touch with family.` — preposition: **for stay** → `to stay`
    - `She prefers the video calls to stay in touch with family.` — article: **the video calls** → `video calls`

### voicemail

- **перевод (промпт):** голосовая почта
- **эталон-пример:** He left a voicemail about the meeting details.
- **дистракторы:**
    - `He leave a voicemail about the meeting details.` — tense: **leave** → `left`
    - `He left the voicemail about the meeting details.` — article: **the voicemail** → `a voicemail`
    - `He left a voicemail of the meeting details.` — preposition: **of** → `about`

### drop a line

- **перевод (промпт):** черкнуть пару строк
- **эталон-пример:** Feel free to drop me a line if you have any questions.
- **дистракторы:**
    - `Feel free to drop me the line if you have any questions.` — article: **the line** → `a line`
    - `Feel free to drop me a line to you have any questions.` — preposition: **to you** → `if you`

### line is busy

- **перевод (промпт):** линия занята
- **эталон-пример:** I tried to call, but the line was busy.
- **дистракторы:**
    - `I tried to call, but line was busy.` — article: **line** → `the line`

### quick chat

- **перевод (промпт):** быстрый разговор
- **эталон-пример:** Let's have a quick chat about the project.
- **принимаемые варианты:**
    - `short discussion` — _Синоним._
    - `brief conversation` — _Синоним._
- **дистракторы:**
    - `Let's have the quick chat about the project.` — article: **the quick chat** → `a quick chat`

## В банке

### I'd like to open an account.

- **перевод (промпт):** Я бы хотел открыть счёт.
- **эталон-пример:** I'd like to open an account with your bank.
- **дистракторы:**
    - `I'd like to open account with your bank.` — article: **account** → `an account`
    - `I’d liked to open an account with your bank.` — tense: **liked** → `like`

### bank account

- **перевод (промпт):** банковский счёт
- **эталон-пример:** I need a bank account to receive my salary.
- **дистракторы:**
    - `I need bank account to receive my salary.` — article: **bank account** → `a bank account`

### debit card

- **перевод (промпт):** дебетовая карта
- **эталон-пример:** I prefer using my debit card for everyday purchases.
- **дистракторы:**
    - `I prefer using my debit card for everyday purchase.` — tense: **purchase** → `purchases`

### credit card

- **перевод (промпт):** кредитная карта
- **эталон-пример:** She got a new credit card with a higher limit.
- **принимаемые варианты:**
    - `bank card` — _синоним_
- **дистракторы:**
    - `She got a new credit card with higher limit.` — article: **higher limit** → `a higher limit`

### Could you help me with this form?

- **перевод (промпт):** Не могли бы вы помочь мне с этой формой?
- **эталон-пример:** Could you help me with this form? I'm not sure how to fill it out.
- **дистракторы:**
    - `Could you help me with this form? I not sure how to fill it out.` — tense: **not** → `am not`

### fill out

- **перевод (промпт):** заполнить (форму)
- **эталон-пример:** Please fill out this application to proceed.
- **принимаемые варианты:**
    - `fill in` — _Синоним к 'fill out'._
    - `complete` — _Синоним к 'fill out'._
- **дистракторы:**
    - `Please fill this application out to proceed.` — word_order: **this application out** → `out this application`

### ATM

- **перевод (промпт):** банкомат
- **эталон-пример:** I withdrew cash from the ATM last night.
- **дистракторы:**
    - `I withdrew cash from ATM last night.` — article: **ATM** → `the ATM`

### transfer money

- **перевод (промпт):** перевести деньги
- **эталон-пример:** I need to transfer money to my sister's account today.
- **дистракторы:**
    - `I need to transfer money for my sister's account today.` — preposition: **for** → `to`
    - `I needs to transfer money to my sister's account today.` — tense: **needs** → `need`

### standing order

- **перевод (промпт):** постоянное поручение (в банке)
- **эталон-пример:** I set up a standing order to pay my rent each month.
- **принимаемые варианты:**
    - `automatic payment` — _Синоним, обозначающий автоматические платежи._
    - `recurring payment` — _Синоним, обозначающий регулярные платежи._
- **дистракторы:**
    - `I set up standing order to pay my rent each month.` — article: **standing order** → `a standing order`
    - `I set up a standing order for pay my rent each month.` — preposition: **for pay** → `to pay`

### set up an account

- **перевод (промпт):** подключить, оформить счёт (услугу)
- **эталон-пример:** You can set up an account online in just a few minutes.
- **принимаемые варианты:**
    - `create an account` — _создать счёт_
    - `open an account` — _сделать счёт_
- **дистракторы:**
    - `You can set up account online in just a few minutes.` — article: **set up account** → `set up an account`

### currency exchange

- **перевод (промпт):** обмен валюты
- **эталон-пример:** Where is the nearest currency exchange counter?
- **дистракторы:**
    - `Where is nearest currency exchange counter?` — article: **nearest** → `the nearest`

### direct debit

- **перевод (промпт):** автосписание (прямое дебетование)
- **эталон-пример:** I pay my utility bills by direct debit.
- **дистракторы:**
    - `I pay my utility bills in direct debit.` — preposition: **in** → `by`
    - `I pay my utility bills by direct debiting.` — modal_to: **direct debiting** → `direct debit`

### withdrawal limit

- **перевод (промпт):** лимит снятия
- **эталон-пример:** My ATM has a daily withdrawal limit of $500.
- **дистракторы:**
    - `My ATM has a daily withdrawal limits of $500.` — tense: **withdrawal limits** → `withdrawal limit`
    - `My ATM have a daily withdrawal limit of $500.` — tense: **My ATM have** → `My ATM has`
    - `My ATM has daily withdrawal limit of $500.` — article: **daily withdrawal limit** → `a daily withdrawal limit`

### online banking

- **перевод (промпт):** онлайн-банкинг
- **эталон-пример:** It's convenient to use online banking to manage my accounts.
- **дистракторы:**
    - `It's convenient to use the online banking to manage my accounts.` — article: **the online banking** → `online banking`

### interest rate

- **перевод (промпт):** процентная ставка
- **эталон-пример:** What is the interest rate for savings accounts?
- **дистракторы:**
    - `What is interest rate for savings accounts?` — article: **interest rate** → `the interest rate`
    - `What is the interest rate at savings accounts?` — preposition: **at savings accounts** → `for savings accounts`
    - `What is the interest rates for savings accounts?` — article: **the interest rates** → `the interest rate`

### minimize fees

- **перевод (промпт):** уменьшить комиссии
- **эталон-пример:** I want to minimize fees on my account.
- **дистракторы:**
    - `I want to minimize fee on my account.` — article: **fee** → `fees`
    - `I want to minimize fees in my account.` — preposition: **in my account** → `on my account`

### financial advisor

- **перевод (промпт):** финансовый консультант
- **эталон-пример:** I scheduled a meeting with my financial advisor to discuss investments.
- **дистракторы:**
    - `I scheduled a meeting with my financial advisor for discuss investments.` — preposition: **for discuss** → `to discuss`
    - `I schedule a meeting with my financial advisor to discuss investments.` — tense: **schedule** → `scheduled`

### monthly statement

- **перевод (промпт):** ежемесячная выписка
- **эталон-пример:** I review my monthly statement for any discrepancies.
- **дистракторы:**
    - `I review my the monthly statement for any discrepancies.` — article: **the monthly** → `monthly`
    - `I review my monthly statements for any discrepancies.` — tense: **statements** → `statement`
    - `I review my monthly statement on any discrepancies.` — preposition: **on** → `for`

## Аренда жилья

### view an apartment

- **перевод (промпт):** осмотреть квартиру
- **эталон-пример:** I'm going to view an apartment tomorrow.
- **дистракторы:**
    - `I'm going to view apartment tomorrow.` — article: **view apartment** → `view an apartment`

### lease agreement

- **перевод (промпт):** договор аренды
- **эталон-пример:** Please read the lease agreement carefully before signing.
- **дистракторы:**
    - `Please read lease agreement carefully before signing.` — article: **lease agreement** → `the lease agreement`

### security deposit

- **перевод (промпт):** залог
- **эталон-пример:** You'll need to pay a security deposit before moving in.
- **принимаемые варианты:**
    - `deposit` — _Слово "залог" можно перевести как "deposit"._
- **дистракторы:**
    - `You'll need to pay the security deposit before moving in.` — article: **the** → `a`

### utilities included

- **перевод (промпт):** коммунальные платежи включены
- **эталон-пример:** Are utilities included in the rent?
- **дистракторы:**
    - `Are utilities included at the rent?` — preposition: **at the rent** → `in the rent`

### per month

- **перевод (промпт):** в месяц
- **эталон-пример:** The rent is $1000 per month.
- **дистракторы:**
    - `The rent is $1000 for month.` — preposition: **for month** → `per month`

### first and last month's rent

- **перевод (промпт):** арендная плата за первый и последний месяц
- **эталон-пример:** You'll have to pay the first and last month's rent when you sign the lease.
- **дистракторы:**
    - `You'll have to pay the first and last month's rent on you sign the lease.` — preposition: **on you sign the lease** → `when you sign the lease`
    - `You'll have to pay the first and last month's rent when you signs the lease.` — tense: **you signs** → `you sign`
    - `You'll have to pay a first and last month's rent when you sign the lease.` — article: **a first and last month's rent** → `the first and last month's rent`

### tenant

- **перевод (промпт):** арендатор, жилец
- **эталон-пример:** The tenant is responsible for minor repairs.
- **принимаемые варианты:**
    - `жилец` — _Синоним термина._
- **дистракторы:**
    - `The tenant are responsible for minor repairs.` — tense: **are** → `is`

### landlord

- **перевод (промпт):** арендодатель
- **эталон-пример:** The landlord will show you around the apartment.
- **дистракторы:**
    - `The landlord show you around the apartment.` — tense: **show** → `will show`

### sign the lease

- **перевод (промпт):** подписать договор аренды
- **эталон-пример:** We signed the lease yesterday.
- **дистракторы:**
    - `We signed lease yesterday.` — article: **lease** → `the lease`
    - `We signed the lease yesterdays.` — tense: **yesterdays** → `yesterday`

### monthly rent

- **перевод (промпт):** ежемесячная арендная плата
- **эталон-пример:** The monthly rent must be paid on the first of each month.
- **дистракторы:**
    - `The monthly rent must be paid at the first of each month.` — preposition: **at the** → `on the`

### maintenance request

- **перевод (промпт):** заявка на ремонт
- **эталон-пример:** You can submit a maintenance request online.
- **дистракторы:**
    - `You can submit the maintenance request online.` — article: **the** → `a`

### walk-through

- **перевод (промпт):** осмотр (перед сдачей жилья)
- **эталон-пример:** We did a walk-through of the apartment before moving in.
- **дистракторы:**
    - `We did a walk-through in the apartment before moving in.` — preposition: **in the apartment** → `of the apartment`
    - `We did walk-through of the apartment before moving in.` — article: **walk-through** → `a walk-through`

### furnished

- **перевод (промпт):** с мебелью
- **эталон-пример:** Is the apartment furnished or unfurnished?
- **принимаемые варианты:**
    - `furnished apartment` — _фраза, которая также подходит по смыслу_
    - `with furniture` — _синоним, который подходит по смыслу_
- **дистракторы:**
    - `Is the apartment furnished without unfurnished?` — preposition: **without** → `or`

### move out

- **перевод (промпт):** съезжать
- **эталон-пример:** When do you plan to move out?
- **принимаемые варианты:**
    - `leave` — _Синоним._
    - `vacate` — _Синоним._
- **дистракторы:**
    - `When do you plan for move out?` — preposition: **for move** → `to move`
    - `When do you plan to moving out?` — tense: **to moving** → `to move`

### notice period

- **перевод (промпт):** период уведомления
- **эталон-пример:** I am currently in a one-month notice period with my current employer.
- **дистракторы:**
    - `I am currently in a one-month notice periods with my current employer.` — article: **notice periods** → `notice period`
    - `I am currently in one-month notice period with my current employer.` — article: **one-month** → `a one-month`
    - `I am currently in a one-month notice period to my current employer.` — preposition: **to my** → `with my`

### shared accommodation

- **перевод (промпт):** совместное жильё
- **эталон-пример:** I'm looking for shared accommodation in the city center.
- **дистракторы:**
    - `I'm looking for shared accommodations in the city center.` — article: **shared accommodations** → `shared accommodation`

### pay the rent

- **перевод (промпт):** платить аренду
- **эталон-пример:** I pay the rent by bank transfer every month.
- **принимаемые варианты:**
    - `pay the fee` — _Синонимы._
    - `pay the lease` — _Синонимы._

### sublet

- **перевод (промпт):** сдавать в субаренду
- **эталон-пример:** Do you have permission to sublet the apartment?
- **дистракторы:**
    - `Do you have permissions to sublet the apartment?` — tense: **permissions** → `permission`

## У врача и в аптеке

### I'd like to make an appointment.

- **перевод (промпт):** Я хотел бы записаться на приём.
- **эталон-пример:** I'd like to make an appointment with Dr. Jones.
- **дистракторы:**
    - `I'd like to make appointment with Dr. Jones.` — article: **appointment** → `an appointment`

### What are your symptoms?

- **перевод (промпт):** Какие у вас симптомы?
- **эталон-пример:** What are your symptoms today?
- **дистракторы:**
    - `What are the symptoms today?` — article: **the symptoms** → `your symptoms`
    - `What are symptoms your today?` — word_order: **symptoms your** → `your symptoms`

### I have a fever.

- **перевод (промпт):** У меня жар.
- **эталон-пример:** I have a fever and feel very weak.
- **принимаемые варианты:**
    - `I have a high temperature.` — _Это также означает, что у меня жар._
- **дистракторы:**
    - `I have a fever and feel very weakly.` — tense: **weakly** → `weak`

### cold

- **перевод (промпт):** простуда
- **эталон-пример:** I think I have a cold.
- **дистракторы:**
    - `I think I have cold.` — article: **cold** → `a cold`

### prescription

- **перевод (промпт):** рецепт (на лекарство)
- **эталон-пример:** I have a prescription from my doctor.
- **принимаемые варианты:**
    - `medical prescription` — _Синоним._
    - `doctor's prescription` — _Синоним._
    - `medication prescription` — _Синоним, обозначающий тот же документ._
- **дистракторы:**
    - `I has a prescription from my doctor.` — tense: **has** → `have`
    - `I have prescription from my doctor.` — article: **prescription** → `a prescription`
    - `I have a prescription at my doctor.` — preposition: **at my doctor** → `from my doctor`

### I have a headache.

- **перевод (промпт):** У меня болит голова.
- **эталон-пример:** I've had a headache since this morning.
- **дистракторы:**
    - `I've had a headache since in the morning.` — preposition: **since in the morning** → `since this morning`
    - `I've had headache since this morning.` — article: **headache** → `a headache`

### How long have you been feeling this way?

- **перевод (промпт):** Как давно вы так себя чувствуете?
- **эталон-пример:** How long have you been feeling this way before coming here?
- **дистракторы:**
    - `How long have you been feeling this way before come here?` — tense: **come** → `coming`

### cough

- **перевод (промпт):** кашель
- **эталон-пример:** She has a bad cough.
- **дистракторы:**
    - `She has a cough bad.` — word_order: **cough bad** → `bad cough`
    - `She has bad cough.` — article: **bad cough** → `a bad cough`

### I need a refill.

- **перевод (промпт):** Мне нужно обновить рецепт.
- **эталон-пример:** I need a refill on my prescription, please.
- **дистракторы:**
    - `I need refill on my prescription, please.` — article: **refill** → `a refill`
    - `I needs a refill on my prescription, please.` — tense: **needs** → `need`
    - `I need a refill in my prescription, please.` — preposition: **in** → `on`

### How can I help you?

- **перевод (промпт):** Чем я могу вам помочь?
- **эталон-пример:** Welcome to the pharmacy. How can I help you?
- **дистракторы:**
    - `Welcome to the pharmacy. How can I help you all?` — word_order: **you all** → `you`
    - `Welcome to the pharmacy. How I can help you?` — tense: **How I can** → `How can I`

### medicine

- **перевод (промпт):** лекарство
- **эталон-пример:** Did the doctor give you any medicine?
- **дистракторы:**
    - `Did the doctor give you medicine?` — article: **medicine** → `any medicine`

### run a temperature

- **перевод (промпт):** температурить, иметь температуру
- **эталон-пример:** He's been running a temperature since last night.
- **принимаемые варианты:**
    - `have a temperature` — _Синонимы._
    - `running a fever` — _Синонимы._
- **дистракторы:**
    - `He's been running temperature since last night.` — article: **temperature** → `a temperature`
- **флаги:**
    - 🌐 **не тот язык** — Температура — некорректно, нужно "температура".

### My throat hurts.

- **перевод (промпт):** У меня болит горло.
- **эталон-пример:** My throat hurts every time I swallow.
- **дистракторы:**
    - `My throat hurts every times I swallow.` — tense: **every times** → `every time`
    - `My throat hurt every time I swallow.` — tense: **hurt** → `hurts`

### pharmacist

- **перевод (промпт):** фармацевт
- **эталон-пример:** The pharmacist can help you find the right medicine.
- **дистракторы:**
    - `The pharmacists can help you find the right medicine.` — tense: **pharmacists** → `pharmacist`
    - `The pharmacist can help you find a right medicine.` — article: **a right** → `the right`
    - `The pharmacist can help you finding the right medicine.` — modal_to: **finding** → `find`

### Please take a seat.

- **перевод (промпт):** Пожалуйста, присаживайтесь.
- **эталон-пример:** Please take a seat, the doctor will see you shortly.
- **дистракторы:**
    - `Please take seat, the doctor will see you shortly.` — article: **seat** → `a seat`
    - `Please take a seat, the doctor will sees you shortly.` — tense: **sees** → `see`

### fill a prescription

- **перевод (промпт):** выполнить рецепт (в аптеке)
- **эталон-пример:** I need to fill a prescription for my medication.
- **дистракторы:**
    - `I need to fill prescription for my medication.` — article: **fill prescription** → `fill a prescription`
    - `I need to fill a prescription in my medication.` — preposition: **in my medication** → `for my medication`

### I have an appointment at 3 PM.

- **перевод (промпт):** У меня запись на 3 часа дня.
- **эталон-пример:** I have an appointment at 3 PM with the dentist.
- **дистракторы:**
    - `I have appointment at 3 PM with the dentist.` — article: **appointment** → `an appointment`

### allergic reaction

- **перевод (промпт):** аллергическая реакция
- **эталон-пример:** Watch for any allergic reactions to the medication.
- **дистракторы:**
    - `Watch for any allergic reaction to the medication.` — article: **allergic reaction** → `allergic reactions`

## Ресторан и доставка еды

### reserve a table

- **перевод (промпт):** забронировать столик
- **эталон-пример:** I would like to reserve a table for two at 7 PM.
- **дистракторы:**
    - `I would like to reserve table for two at 7 PM.` — article: **reserve table** → `reserve a table`

### What would you like to order?

- **перевод (промпт):** Что бы вы хотели заказать?
- **эталон-пример:** What would you like to order?
- **принимаемые варианты:**
    - `What do you want to order?` — _Это тоже вариант, который означает то же самое._
    - `What would you want to order?` — _Этот вариант тоже приемлем._

### I'll have the chicken salad.

- **перевод (промпт):** Я буду куриный салат.
- **эталон-пример:** I'll have the chicken salad, please.
- **дистракторы:**
    - `I'll have chicken salad, please.` — article: **chicken salad** → `the chicken salad`

### menu

- **перевод (промпт):** меню
- **эталон-пример:** Could you bring me the menu, please?
- **дистракторы:**
    - `Could you bring me menu, please?` — article: **menu** → `the menu`

### How would you like to pay?

- **перевод (промпт):** Как вы хотели бы оплатить?
- **эталон-пример:** The cashier asked, 'How would you like to pay?'
- **дистракторы:**
    - `The cashier asked, 'How would you like for pay?'` — preposition: **for pay** → `to pay`
    - `The cashier asked, 'How would you liked to pay?'` — tense: **liked** → `like`

### Could I see the wine list?

- **перевод (промпт):** Могу я посмотреть винную карту?
- **эталон-пример:** Could I see the wine list, please?
- **принимаемые варианты:**
    - `Can I see the wine list?` — _Синоним с другим модальным глаголом._
    - `May I see the wine list?` — _Синоним с другим модальным глаголом._
- **дистракторы:**
    - `Could I see the list wine, please?` — word_order: **the list wine** → `the wine list`
    - `Could I see wine list, please?` — article: **wine list** → `the wine list`

### I'd like to make a reservation.

- **перевод (промпт):** Я бы хотел сделать бронь.
- **эталон-пример:** I'd like to make a reservation for Saturday night.
- **дистракторы:**
    - `I'd like to make reservation for Saturday night.` — article: **make reservation** → `make a reservation`

### Can I get this to go?

- **перевод (промпт):** Можно это с собой?
- **эталон-пример:** I'd like the pasta to go, please.
- **дистракторы:**
    - `I’d like the pastas to go, please.` — article: **the pastas** → `the pasta`
- **флаги:**
    - ✍️ **не слово → правка** — пасту (паста) это не с собой

### delivery

- **перевод (промпт):** доставка
- **эталон-пример:** The delivery should arrive in 30 minutes.
- **дистракторы:**
    - `The delivery should arrives in 30 minutes.` — tense: **arrives** → `arrive`

### starter

- **перевод (промпт):** закуска
- **эталон-пример:** For a starter, I would recommend the soup.
- **дистракторы:**
    - `For a starter, I would recommend soup.` — article: **soup** → `the soup`

### main course

- **перевод (промпт):** основное блюдо
- **эталон-пример:** I'll have the steak as my main course.
- **принимаемые варианты:**
    - `primary dish` — _Синоним_
- **дистракторы:**
    - `I'll had the steak as my main course.` — tense: **had** → `have`
    - `I'll have steak as my main course.` — article: **steak** → `the steak`

### Could we have the bill, please?

- **перевод (промпт):** Можно нам счёт, пожалуйста?
- **эталон-пример:** Could we have the bill, please?
- **дистракторы:**
    - `Could we has the bill, please?` — tense: **has** → `have`
    - `Could we have bill, please?` — article: **bill** → `the bill`

### order in

- **перевод (промпт):** заказать на дом
- **эталон-пример:** Let's order in tonight instead of cooking.
- **принимаемые варианты:**
    - `order delivery` — _Это также обозначает 'заказать на дом'._
    - `get takeout` — _Это синонимично с 'заказать на дом'._
- **дистракторы:**
    - `Let's order in tonight instead of to cook.` — modal_to: **to cook** → `cooking`
    - `Let's order tonight instead of cooking.` — word_order: **order tonight** → `order in tonight`
    - `Let's order in tonight instead cooking.` — preposition: **instead cooking** → `instead of cooking`

### Are you ready to order?

- **перевод (промпт):** Вы готовы заказать?
- **эталон-пример:** Are you ready to order, or do you need more time?
- **дистракторы:**
    - `Are you ready to order, or do you needs more time?` — tense: **needs** → `need`
    - `Are you ready for order, or do you need more time?` — preposition: **for order** → `to order`

### Would you like anything to drink?

- **перевод (промпт):** Вы бы хотели что-нибудь выпить?
- **эталон-пример:** Would you like anything to drink with your meal?
- **дистракторы:**
    - `Would you like anything to drink with your meals?` — tense: **meals** → `meal`

### cutlery

- **перевод (промпт):** столовые приборы
- **эталон-пример:** The cutlery is on the table.
- **дистракторы:**
    - `The cutlery are on the table.` — tense: **are** → `is`

### Can I have some water, please?

- **перевод (промпт):** Можно мне воды, пожалуйста?
- **эталон-пример:** Can I have some water, please?
- **дистракторы:**
    - `Can I has some water, please?` — tense: **has** → `have`

### gratuity

- **перевод (промпт):** чаевые (формально)
- **эталон-пример:** Gratuity is not included in the bill.
- **дистракторы:**
    - `Gratuity is not included in bill.` — article: **in bill** → `in the bill`

### serve

- **перевод (промпт):** обслуживать
- **эталон-пример:** The waiter will serve your meal shortly.
- **дистракторы:**
    - `The waiter will serve your meal soon.` — word_order: **soon** → `shortly`

## Покупки в супермаркете

### Where can I find the dairy section?

- **перевод (промпт):** Где я могу найти отдел молочной продукции?
- **эталон-пример:** Where can I find the dairy section?
- **дистракторы:**
    - `Where can I find dairy section?` — article: **dairy section** → `the dairy section`

### shopping cart

- **перевод (промпт):** тележка для покупок
- **эталон-пример:** I grabbed a shopping cart and started my grocery shopping.
- **дистракторы:**
    - `I grabbed a shopping cart and started the grocery shopping.` — article: **the** → `my`

### checkout counter

- **перевод (промпт):** кассовая стойка
- **эталон-пример:** I paid for my groceries at the checkout counter.
- **дистракторы:**
    - `I paid for my groceries in the checkout counter.` — preposition: **in** → `at`
    - `I pay for my groceries at the checkout counter.` — tense: **pay** → `paid`

### grocery list

- **перевод (промпт):** список продуктов
- **эталон-пример:** I made a grocery list before going to the store.
- **дистракторы:**
    - `I made a grocery list before to going to the store.` — modal_to: **to going** → `going`
    - `I made grocery list before going to the store.` — article: **grocery list** → `a grocery list`

### self-checkout

- **перевод (промпт):** самостоятельная касса
- **эталон-пример:** I prefer using the self-checkout for small purchases.
- **дистракторы:**
    - `I prefer using a self-checkout for small purchases.` — article: **a self-checkout** → `the self-checkout`
    - `I prefer using the self-checkout in small purchases.` — preposition: **in small purchases** → `for small purchases`

### fresh produce

- **перевод (промпт):** свежие продукты
- **эталон-пример:** The fresh produce section is on the left.
- **дистракторы:**
    - `The fresh produce section are on the left.` — tense: **are** → `is`

### How much does this cost?

- **перевод (промпт):** Сколько это стоит?
- **эталон-пример:** I asked the clerk, 'How much does this cost?'
- **дистракторы:**
    - `I asked the clerk, 'How much does this costs?'` — tense: **costs** → `cost`

### Can I pay by card?

- **перевод (промпт):** Могу я оплатить картой?
- **эталон-пример:** When I reached the cashier, I asked, 'Can I pay by card?'
- **дистракторы:**
    - `When I reached the cashier, I asked, 'Can I pay with card?'` — preposition: **pay with card** → `pay by card`

### on sale

- **перевод (промпт):** на распродаже
- **эталон-пример:** These shoes are on sale for 50% off.
- **дистракторы:**
    - `These shoes is on sale for 50% off.` — tense: **is** → `are`
    - `These shoes are at sale for 50% off.` — preposition: **at sale** → `on sale`

### cash register

- **перевод (промпт):** касса
- **эталон-пример:** The cash register was down, so we had to wait.
- **дистракторы:**
    - `The cash register was down, so we has to wait.` — tense: **has** → `had`

### carry out

- **перевод (промпт):** выполнить
- **эталон-пример:** Please carry out all items from your grocery list.
- **дистракторы:**
    - `Please carry out the all items from your grocery list.` — article: **the all** → `all`
    - `Please carried out all items from your grocery list.` — tense: **carried** → `carry`
    - `Please carry all out items from your grocery list.` — word_order: **all out** → `out all`

### I need a bag, please.

- **перевод (промпт):** Мне нужен пакет, пожалуйста.
- **эталон-пример:** I told the cashier, 'I need a bag, please.'
- **дистракторы:**
    - `I told the cashier, 'I need bag, please.'` — article: **bag** → `a bag`

### pay in cash

- **перевод (промпт):** оплатить наличными
- **эталон-пример:** I decided to pay in cash this time.
- **принимаемые варианты:**
    - `pay with cash` — _Это также правильный вариант._
    - `make payment in cash` — _Это альтернативный способ сказать то же самое._
- **дистракторы:**
    - `I decided to pay in the cash this time.` — article: **the cash** → `cash`
    - `I decided to pay by cash this time.` — preposition: **by cash** → `in cash`
    - `I decide to pay in cash this time.` — tense: **decide** → `decided`

### running low

- **перевод (промпт):** заканчиваться
- **эталон-пример:** We are running low on milk, I must buy more.
- **дистракторы:**
    - `We are running low milk, I must buy more.` — word_order: **low milk** → `low on milk`

### express lane

- **перевод (промпт):** полоса экспресс-обслуживания
- **эталон-пример:** I used the express lane because I only had a few items.
- **принимаемые варианты:**
    - `fast lane` — _Синонимично._
    - `priority lane` — _Синонимично._
- **дистракторы:**
    - `I used express lane because I only had a few items.` — article: **express lane** → `the express lane`

### checkout assistant

- **перевод (промпт):** кассир
- **эталон-пример:** The checkout assistant helped me with my transaction.
- **дистракторы:**
    - `The checkout assistant help me with my transaction.` — tense: **help** → `helped`

### How would you like to pay?

- **перевод (промпт):** Как вы хотели бы оплатить?
- **эталон-пример:** The cashier asked, 'How would you like to pay?'
- **дистракторы:**
    - `The cashier asked, 'How would you like for pay?'` — preposition: **for pay** → `to pay`
    - `The cashier asked, 'How would you liked to pay?'` — tense: **liked** → `like`

### aisle

- **перевод (промпт):** проход
- **эталон-пример:** The cereal is on aisle four.
- **дистракторы:**
    - `The cereal is on aisle fourth.` — word_order: **aisle fourth** → `aisle four`

### scan an item

- **перевод (промпт):** сканировать товар
- **эталон-пример:** I need to scan each item at the checkout.
- **дистракторы:**
    - `I need to scan every item at the checkout.` — word_order: **every item** → `each item`

## Магазин одежды и размеры

### What size are you looking for?

- **перевод (промпт):** Какой размер вы ищете?
- **эталон-пример:** What size are you looking for?
- **дистракторы:**
    - `What size are looking for?` — tense: **are looking** → `are you looking`

### fitting room

- **перевод (промпт):** примерочная
- **эталон-пример:** The fitting room is at the back of the store.
- **дистракторы:**
    - `The fitting room are at the back of the store.` — tense: **are** → `is`
    - `The fitting room is at back of the store.` — article: **at back** → `at the back`

### Can I try this on?

- **перевод (промпт):** Могу я это примерить?
- **эталон-пример:** Can I try this on in the fitting room?
- **принимаемые варианты:**
    - `Can I test this on?` — _Это альтернативная формулировка._
    - `Can I fit this?` — _Это также означает 'примерять'._
- **дистракторы:**
    - `Can I try this on in fitting room?` — article: **in fitting room** → `in the fitting room`

### larger size

- **перевод (промпт):** больший размер
- **эталон-пример:** Do you have this in a larger size?
- **дистракторы:**
    - `Do you have this in the larger size?` — article: **the larger size** → `a larger size`
    - `Do you have this in a larger sizes?` — article: **larger sizes** → `larger size`

### smaller size

- **перевод (промпт):** меньший размер
- **эталон-пример:** I need this in a smaller size.
- **дистракторы:**
    - `I need this at a smaller size.` — preposition: **at** → `in`

### How much does it cost?

- **перевод (промпт):** Сколько это стоит?
- **эталон-пример:** How much does the pain reliever cost?
- **принимаемые варианты:**
    - `What is the price?` — _Это эквивалентно вопросу о стоимости._
- **дистракторы:**
    - `How much does the pain reliever costs?` — tense: **costs** → `cost`
    - `How much does a pain reliever cost?` — article: **a pain reliever** → `the pain reliever`
    - `How much does pain reliever cost?` — article: **pain reliever** → `the pain reliever`

### Do you accept credit cards?

- **перевод (промпт):** Вы принимаете кредитные карты?
- **эталон-пример:** Do you accept credit cards for payment?
- **принимаемые варианты:**
    - `Will you accept credit cards?` — _Синонимично._
    - `Can you accept credit cards?` — _Синонимично._
- **дистракторы:**
    - `Do you accepts credit cards for payment?` — tense: **accepts** → `accept`
    - `Do you accept credit cards in payment?` — preposition: **in payment** → `for payment`
    - `Do you accept credit card for payment?` — article: **credit card** → `credit cards`

### Can I get a refund?

- **перевод (промпт):** Могу я получить возврат?
- **эталон-пример:** Can I get a refund if it doesn't fit?
- **дистракторы:**
    - `Can I gets a refund if it doesn't fit?` — tense: **gets** → `get`
    - `Can I get refund if it doesn't fit?` — article: **refund** → `a refund`
    - `Can I get a refund at it doesn't fit?` — preposition: **at** → `if`

### exchange policy

- **перевод (промпт):** политика обмена
- **эталон-пример:** The exchange policy allows you to return items within 30 days.
- **дистракторы:**
    - `The exchange policy allow you to return items within 30 days.` — tense: **allow** → `allows`
    - `The exchange policy allows you return items within 30 days.` — modal_to: **you return** → `you to return`
    - `The exchange policy allows you to return the items within 30 days.` — article: **the items** → `items`

### return receipt

- **перевод (промпт):** чек для возврата
- **эталон-пример:** Keep your return receipt in case you need to return the item.
- **дистракторы:**
    - `Keep your return receipt in case you needs to return the item.` — tense: **needs** → `need`
    - `Keep your return receipt at case you need to return the item.` — preposition: **at case** → `in case`

### Does this come in different colors?

- **перевод (промпт):** Это бывает в разных цветах?
- **эталон-пример:** Does this shirt come in different colors?
- **дистракторы:**
    - `Does in different colors come this shirt?` — word_order: **in different colors come this shirt** → `this shirt come in different colors`

### out of stock

- **перевод (промпт):** нет в наличии
- **эталон-пример:** These jeans are currently out of stock.
- **дистракторы:**
    - `These jeans was currently out of stock.` — tense: **was** → `are`
    - `These jeans are currently out in stock.` — preposition: **in** → `of`

### cash register

- **перевод (промпт):** касса
- **эталон-пример:** The cash register was down, so we had to wait.
- **дистракторы:**
    - `The cash register was down, so we has to wait.` — tense: **has** → `had`

### What's your return policy?

- **перевод (промпт):** Какие у вас правила возврата?
- **эталон-пример:** What's your return policy on sale items?
- **дистракторы:**
    - `What's your return policy for sale items?` — preposition: **for** → `on`

### customer service desk

- **перевод (промпт):** стойка обслуживания клиентов
- **эталон-пример:** If you have any issues, please visit the customer service desk.
- **дистракторы:**
    - `If you have any issues, please visit the customer service desks.` — tense: **desks** → `desk`

### on sale

- **перевод (промпт):** на распродаже
- **эталон-пример:** These shoes are on sale for 50% off.
- **дистракторы:**
    - `These shoes is on sale for 50% off.` — tense: **is** → `are`
    - `These shoes are at sale for 50% off.` — preposition: **at sale** → `on sale`

### fitting

- **перевод (промпт):** примерка
- **эталон-пример:** The fitting went well, and everything fits perfectly.
- **принимаемые варианты:**
    - `trial fitting` — _Это синоним._
    - `measurement fitting` — _Это синоним._
- **дистракторы:**
    - `The fitting went well, and everything fit perfectly.` — tense: **fit** → `fits`
    - `The fitting went good, and everything fits perfectly.` — tense: **good** → `well`

### sale sign

- **перевод (промпт):** знак распродажи
- **эталон-пример:** Look for the sale sign to find great discounts.
- **дистракторы:**
    - `Look for the sale sign to find great discount.` — tense: **discount** → `discounts`

## Спортзал и здоровье

### join a gym

- **перевод (промпт):** записаться в спортзал
- **эталон-пример:** I want to join a gym to improve my fitness.
- **дистракторы:**
    - `I want to join gym to improve my fitness.` — article: **join gym** → `join a gym`

### work out

- **перевод (промпт):** тренироваться
- **эталон-пример:** I work out three times a week.
- **принимаемые варианты:**
    - `exercise` — _Синоним._
    - `train` — _Синоним._
- **дистракторы:**
    - `I work out three time a week.` — tense: **three time** → `three times`

### membership card

- **перевод (промпт):** членская карта
- **эталон-пример:** Don't forget to bring your membership card to the gym.
- **дистракторы:**
    - `Don't forget to bring your membership cards to the gym.` — tense: **membership cards** → `membership card`
    - `Don't forget to bring your membership card for the gym.` — preposition: **for** → `to`
    - `Don't forget to bring the membership card to the gym.` — article: **the membership card** → `your membership card`

### exercise routine

- **перевод (промпт):** комплекс упражнений
- **эталон-пример:** My exercise routine includes running and weightlifting.
- **дистракторы:**
    - `My the exercise routine includes running and weightlifting.` — article: **the exercise routine** → `exercise routine`
    - `My exercise routine include running and weightlifting.` — tense: **include** → `includes`

### stay in shape

- **перевод (промпт):** оставаться в форме
- **эталон-пример:** I try to stay in shape by jogging every morning.
- **дистракторы:**
    - `I try to stay in shape by jog every morning.` — tense: **jog** → `jogging`

### cardio workout

- **перевод (промпт):** кардиотренировка
- **эталон-пример:** Cardio workouts are great for heart health.
- **дистракторы:**
    - `Cardio workouts is great for heart health.` — tense: **Cardio workouts is** → `Cardio workouts are`
    - `Cardio workouts are great in heart health.` — preposition: **in heart health** → `for heart health`
    - `Cardio workouts are great for the heart health.` — article: **the heart health** → `heart health`

### strength training

- **перевод (промпт):** силовая тренировка
- **эталон-пример:** Strength training can help build muscle mass.
- **дистракторы:**
    - `Strength training can helps build muscle mass.` — tense: **helps** → `help`

### healthy lifestyle

- **перевод (промпт):** здоровый образ жизни
- **эталон-пример:** A healthy lifestyle includes a balanced diet and regular exercise.
- **принимаемые варианты:**
    - `wholesome lifestyle` — _Синоним для "здоровый образ жизни"._
    - `fit lifestyle` — _Синоним для "здоровый образ жизни"._
- **дистракторы:**
    - `A healthy lifestyle include a balanced diet and regular exercise.` — tense: **include** → `includes`
    - `A healthy lifestyle includes balanced diet and regular exercise.` — article: **balanced diet** → `a balanced diet`

### personal trainer

- **перевод (промпт):** персональный тренер
- **эталон-пример:** My personal trainer helped me develop a workout plan.
- **принимаемые варианты:**
    - `fitness coach` — _Эквивалентное выражение для тренера._
    - `personal coach` — _Синоним, также означающий личного тренера._
- **дистракторы:**
    - `My personal trainer helped me to develop a workout plan.` — modal_to: **to develop** → `develop`

### fitness level

- **перевод (промпт):** уровень физической подготовки
- **эталон-пример:** Your fitness level affects how you should train.
- **дистракторы:**
    - `Your fitness level affect how you should train.` — tense: **affect** → `affects`
    - `Your fitness level affects how you should training.` — modal_to: **should training** → `should train`

### warm up

- **перевод (промпт):** разминаться
- **эталон-пример:** It's important to warm up before exercising.
- **дистракторы:**
    - `It's important to warm up at exercising.` — preposition: **at** → `before`
    - `It's important to the warm up before exercising.` — article: **the warm up** → `warm up`
    - `It's important to warm up before exercise.` — tense: **exercise** → `exercising`

### cool down

- **перевод (промпт):** завершать тренировку
- **эталон-пример:** Always cool down after a workout to prevent injury.
- **дистракторы:**
    - `Always cool down after workout to prevent injury.` — article: **after workout** → `after a workout`
- **флаги:**
    - ✍️ **не слово → правка** — Слово 'завершать' не совсем уместно в этом контексте. Правильный вариант — 'окончать'.

### get in shape

- **перевод (промпт):** приходить в форму
- **эталон-пример:** I've started running to get in shape for the summer.
- **дистракторы:**
    - `I have started running for get in shape for the summer.` — modal_to: **for get** → `to get`
    - `I've start running to get in shape for the summer.` — tense: **start** → `started`

### diet plan

- **перевод (промпт):** план питания
- **эталон-пример:** She follows a strict diet plan to lose weight.
- **дистракторы:**
    - `She follows a strict diet plan for lose weight.` — preposition: **for** → `to`
    - `She follow a strict diet plan to lose weight.` — tense: **follow** → `follows`

### feel energized

- **перевод (промпт):** чувствовать себя энергичным
- **эталон-пример:** After a workout, I always feel energized.
- **дистракторы:**
    - `After a workout, I always feels energized.` — tense: **feels** → `feel`

### flexibility exercises

- **перевод (промпт):** упражнения на гибкость
- **эталон-пример:** Flexibility exercises help prevent injuries.
- **дистракторы:**
    - `Flexibility exercises help to prevent injuries.` — modal_to: **to prevent** → `prevent`
    - `Flexibility exercises helps prevent injuries.` — tense: **helps** → `help`

### lose weight

- **перевод (промпт):** худеть
- **эталон-пример:** I'm trying to lose weight by eating healthier.
- **дистракторы:**
    - `I'm trying to lose weight on eating healthier.` — preposition: **on eating** → `by eating`
    - `I trying to lose weight by eating healthier.` — tense: **I trying** → `I'm trying`

### gain muscle

- **перевод (промпт):** наращивать мышцы
- **эталон-пример:** He wants to gain muscle before the competition season.
- **принимаемые варианты:**
    - `increase muscle mass` — _Синонимы._
    - `build muscle` — _Синонимы._
- **дистракторы:**
    - `He wants gaining muscle before the competition season.` — modal_to: **gaining** → `to gain`

### feel sore

- **перевод (промпт):** чувствовать боль
- **эталон-пример:** I feel sore after yesterday's workout.
- **принимаемые варианты:**
    - `feel achy` — _Синонимы._
- **дистракторы:**
    - `I feel sore after yesterday workout.` — article: **yesterday workout** → `yesterday's workout`
    - `I feels sore after yesterday's workout.` — tense: **feels** → `feel`

## Экстренные ситуации

### Call the police

- **перевод (промпт):** Вызвать полицию
- **эталон-пример:** If you see a break-in, call the police immediately.
- **дистракторы:**
    - `If you see a break-in, call police immediately.` — article: **call police** → `call the police`

### I need help

- **перевод (промпт):** Мне нужна помощь
- **эталон-пример:** I need help, please call an ambulance.
- **дистракторы:**
    - `I need help, please call the ambulance.` — article: **the ambulance** → `an ambulance`
    - `I need help, please call help.` — false_friend: **call help** → `call an ambulance`

### ambulance

- **перевод (промпт):** Скорая помощь
- **эталон-пример:** We called an ambulance after the accident.
- **принимаемые варианты:**
    - `emergency vehicle` — _Синонимично_
    - `paramedic service` — _Синонимично_
- **дистракторы:**
    - `We called an ambulance about the accident.` — preposition: **about** → `after`
    - `We called the ambulance after the accident.` — article: **the ambulance** → `an ambulance`

### I lost my wallet

- **перевод (промпт):** Я потерял(а) кошелёк
- **эталон-пример:** I lost my wallet at the station.
- **дистракторы:**
    - `I lost my wallet in the station.` — preposition: **in the station** → `at the station`

### It's an emergency

- **перевод (промпт):** Это чрезвычайная ситуация
- **эталон-пример:** Please hurry, it's an emergency!
- **дистракторы:**
    - `Please hurry, it's the emergency!` — article: **the** → `an`
    - `Please hurry, it are an emergency!` — tense: **are** → `is`

### My passport was stolen

- **перевод (промпт):** Мой паспорт украли
- **эталон-пример:** My passport was stolen while I was at the cafe.
- **дистракторы:**
    - `My passport was stolen while I was at cafe.` — article: **at cafe** → `at the cafe`
    - `My passport was stolen while I was in the cafe.` — preposition: **in the cafe** → `at the cafe`
    - `My passport were stolen while I was at the cafe.` — tense: **My passport were** → `My passport was`

### contact the authorities

- **перевод (промпт):** Связаться с властями
- **эталон-пример:** You should contact the authorities about the missing person.
- **дистракторы:**
    - `You should contact the authorities for the missing person.` — preposition: **for** → `about`

### emergency services

- **перевод (промпт):** Аварийные службы
- **эталон-пример:** Dial 911 to reach emergency services.
- **дистракторы:**
    - `Dial 911 to reach the emergency services.` — article: **the emergency services** → `emergency services`

### file a report

- **перевод (промпт):** Подать заявление
- **эталон-пример:** You need to file a report at the police station.
- **дистракторы:**
    - `You need to file report at the police station.` — article: **file report** → `file a report`

### break-in

- **перевод (промпт):** Взлом
- **эталон-пример:** There was a break-in at my neighbor's last night.
- **дистракторы:**
    - `There was break-in at my neighbor's last night.` — article: **break-in** → `a break-in`

### stay calm

- **перевод (промпт):** Оставайтесь спокойны
- **эталон-пример:** Stay calm and tell me what’s wrong.
- **принимаемые варианты:**
    - `remain calm` — _Синоним._
    - `keep calm` — _Синоним._
- **дистракторы:**
    - `Stay calm and tell me what is wrong.` — tense: **what is wrong** → `what’s wrong`

### urgent

- **перевод (промпт):** Срочный
- **эталон-пример:** This matter is urgent, please respond quickly.
- **дистракторы:**
    - `This matter is urgent, please respond quick.` — word_order: **quick** → `quickly`
    - `This matter is urgent, please responded quickly.` — tense: **responded** → `respond`
    - `This matter is urgency, please respond quickly.` — false_friend: **urgency** → `urgent`

### get in touch with

- **перевод (промпт):** Связаться с
- **эталон-пример:** I need to get in touch with the embassy immediately.
- **дистракторы:**
    - `I need to get in touch the embassy immediately.` — preposition: **in touch** → `in touch with`

### locksmith

- **перевод (промпт):** Слесарь (по замкам)
- **эталон-пример:** I need a locksmith because I lost my keys.
- **дистракторы:**
    - `I need the locksmith because I lost my keys.` — article: **the** → `a`

### lost and found

- **перевод (промпт):** Бюро находок
- **эталон-пример:** Check the lost and found for your bag.
- **дистракторы:**
    - `Check lost and found for your bag.` — article: **lost and found** → `the lost and found`

### crime

- **перевод (промпт):** Преступление
- **эталон-пример:** Reporting a crime can help others stay safe.
- **дистракторы:**
    - `Reporting crime can help others stay safe.` — article: **Reporting crime** → `Reporting a crime`

### pickpocket

- **перевод (промпт):** Карманник
- **эталон-пример:** Beware of pickpockets in crowded places.
- **дистракторы:**
    - `Beware of the pickpockets in crowded places.` — article: **the pickpockets** → `pickpockets`
    - `Beware of pickpockets at crowded places.` — preposition: **at crowded places** → `in crowded places`

### Can you describe it?

- **перевод (промпт):** Можете это описать?
- **эталон-пример:** Can you describe it to the police officer?
- **дистракторы:**
    - `Can you describe it to police officer?` — article: **to police officer** → `to the police officer`
- **флаги:**
    - ✍️ **не слово → правка** — полицейскому, полицейскому

## В аэропорту и на рейсе

### check-in desk

- **перевод (промпт):** стойка регистрации
- **эталон-пример:** Please proceed to the check-in desk to collect your boarding pass.
- **дистракторы:**
    - `Please proceed to the check-in desk for collect your boarding pass.` — preposition: **for collect** → `to collect`
    - `Please proceed to check-in desk to collect your boarding pass.` — article: **check-in desk** → `the check-in desk`

### boarding pass

- **перевод (промпт):** посадочный талон
- **эталон-пример:** You need your boarding pass to enter the boarding area.
- **дистракторы:**
    - `You need your boarding pass for enter the boarding area.` — preposition: **for enter** → `to enter`
    - `You need your boarding pass to entering the boarding area.` — tense: **entering** → `enter`
    - `You need a boarding pass to enter the boarding area.` — article: **a boarding pass** → `your boarding pass`

### security check

- **перевод (промпт):** досмотр безопасности
- **эталон-пример:** Please remove your shoes at the security check.
- **дистракторы:**
    - `Please remove your shoes at security check.` — article: **security check** → `the security check`

### baggage claim

- **перевод (промпт):** выдача багажа
- **эталон-пример:** After landing, proceed to baggage claim to collect your luggage.
- **дистракторы:**
    - `After landing, proceed to baggage claim to collected your luggage.` — tense: **collected** → `collect`
    - `After landing, proceed to baggage claim for collect your luggage.` — preposition: **for** → `to`

### customs

- **перевод (промпт):** таможня
- **эталон-пример:** You have to declare goods at customs if they exceed the limit.
- **дистракторы:**
    - `You have to declare goods in customs if they exceed the limit.` — preposition: **in customs** → `at customs`
    - `You have to declare goods at the customs if they exceed the limit.` — article: **the customs** → `customs`

### gate number

- **перевод (промпт):** номер выхода на посадку
- **эталон-пример:** Check your gate number on the display board.
- **дистракторы:**
    - `Check your gate number in the display board.` — preposition: **in** → `on`

### boarding

- **перевод (промпт):** посадка на борт
- **эталон-пример:** Boarding will begin 30 minutes before departure.
- **дистракторы:**
    - `Boarding will begin at 30 minutes before departure.` — preposition: **at 30 minutes** → `30 minutes`
    - `Boarding begins 30 minutes before departure.` — tense: **begins** → `will begin`
    - `Boarding will begin 30 minutes after departure.` — word_order: **after departure** → `before departure`

### passport control

- **перевод (промпт):** паспортный контроль
- **эталон-пример:** There was a long queue at passport control.
- **дистракторы:**
    - `There was a long queue at the passport control.` — article: **the passport control** → `passport control`

### aisle seat

- **перевод (промпт):** место у прохода
- **эталон-пример:** I prefer an aisle seat for more legroom.
- **дистракторы:**
    - `I prefer aisle seat for more legroom.` — article: **aisle seat** → `an aisle seat`

### window seat

- **перевод (промпт):** место у окна
- **эталон-пример:** Would you like a window seat or an aisle seat?
- **дистракторы:**
    - `Would you like a window seat about an aisle seat?` — preposition: **about** → `or`
    - `Would you like the window seat or an aisle seat?` — article: **the window seat** → `a window seat`

### carry-on bag

- **перевод (промпт):** ручная кладь
- **эталон-пример:** Your carry-on bag must fit in the overhead compartment.
- **дистракторы:**
    - `Your carry-on bag must fit in overhead compartment.` — article: **in overhead compartment** → `in the overhead compartment`
    - `Your carry-on bag must fits in the overhead compartment.` — tense: **fits** → `fit`

### fasten your seatbelt

- **перевод (промпт):** пристегните ремень безопасности
- **эталон-пример:** Please fasten your seatbelt as we prepare for takeoff.
- **дистракторы:**
    - `Please fasten the seatbelt as we prepare for takeoff.` — article: **the** → `your`

### overhead compartment

- **перевод (промпт):** верхний багажный отсек
- **эталон-пример:** Please place your bags in the overhead compartment.
- **дистракторы:**
    - `Please place your bags in overhead compartment.` — article: **overhead compartment** → `the overhead compartment`

### tray table

- **перевод (промпт):** откидной столик
- **эталон-пример:** Please fold up your tray table before landing.
- **принимаемые варианты:**
    - `foldable table` — _синоним откидного столика_
- **дистракторы:**
    - `Please fold up your tray table for landing.` — preposition: **for landing** → `before landing`
    - `Please fold up your tray tables before landing.` — article: **tray tables** → `tray table`
- **флаги:**
    - ✍️ **не слово → правка** — откидной - откидывающийся

### in-flight meal

- **перевод (промпт):** едa в самолёте
- **эталон-пример:** The in-flight meal will be served shortly after takeoff.
- **дистракторы:**
    - `The in-flight meal will served shortly after takeoff.` — tense: **will served** → `will be served`
    - `The in-flight meal will be served shortly after the takeoff.` — article: **the takeoff** → `takeoff`

### flight attendant

- **перевод (промпт):** бортпроводник
- **эталон-пример:** If you need any assistance, ask a flight attendant.
- **принимаемые варианты:**
    - `air steward` — _Синоним, обозначающий ту же профессию._
    - `flight steward` — _Альтернативный термин с тем же значением._
- **дистракторы:**
    - `If you need any assistance, ask the flight attendant.` — article: **the** → `a`

### takeoff

- **перевод (промпт):** взлет
- **эталон-пример:** The takeoff was smooth and on time.
- **дистракторы:**
    - `The takeoff were smooth and on time.` — tense: **were** → `was`
    - `The takeoff was smooth and at time.` — preposition: **at time** → `on time`

### landing

- **перевод (промпт):** посадка
- **эталон-пример:** We are now beginning our final approach for landing.
- **дистракторы:**
    - `We are now beginning our final approach on landing.` — preposition: **on** → `for`

### on time

- **перевод (промпт):** вовремя
- **эталон-пример:** The flight departed on time without any delays.
- **дистракторы:**
    - `The flight departed at time without any delays.` — preposition: **at time** → `on time`
    - `The flight departed on time without any delay.` — tense: **any delay** → `any delays`

### luggage

- **перевод (промпт):** багаж
- **эталон-пример:** Make sure your luggage is tagged correctly.
- **принимаемые варианты:**
    - `baggage` — _Синоним по значению._
- **дистракторы:**
    - `Make sure a luggage is tagged correctly.` — article: **a luggage** → `your luggage`
    - `Make sure your luggage are tagged correctly.` — tense: **luggage are** → `luggage is`

## Отель: бронь и заселение

### I'd like to book a room.

- **перевод (промпт):** Я бы хотел забронировать номер.
- **эталон-пример:** I'd like to book a room with a sea view.
- **дистракторы:**
    - `I'd like to book a room on a sea view.` — preposition: **on** → `with`
    - `I'd like to book the room with a sea view.` — article: **the** → `a`

### check in

- **перевод (промпт):** заселяться
- **эталон-пример:** We can check in after 2 PM.
- **дистракторы:**
    - `We can check in after the 2 PM.` — article: **the 2 PM** → `2 PM`
    - `We can check in after 2 PMs.` — tense: **2 PMs** → `2 PM`

### Could I have your ID, please?

- **перевод (промпт):** Пожалуйста, предъявите ваше удостоверение личности.
- **эталон-пример:** When you arrive, the receptionist will say, 'Could I have your ID, please?'
- **дистракторы:**
    - `When you arrive, the receptionist will say, 'Could I have the ID, please?'` — article: **the ID** → `your ID`
    - `When you arrive, the receptionist will say, 'Could I has your ID, please?'` — tense: **has** → `have`

### key card

- **перевод (промпт):** ключ-карта
- **эталон-пример:** Here's your key card; your room is on the third floor.
- **дистракторы:**
    - `Here's your key card; your room is on third floor.` — article: **on third floor** → `on the third floor`
    - `Here's your key card; your room are on the third floor.` — tense: **are** → `is`

### suite

- **перевод (промпт):** люкс
- **эталон-пример:** We decided to stay in a suite for our anniversary.
- **дистракторы:**
    - `We decided to stay in suite for our anniversary.` — article: **in suite** → `in a suite`

### Where is the elevator?

- **перевод (промпт):** Где находится лифт?
- **эталон-пример:** Can you tell me where the elevator is?
- **дистракторы:**
    - `Can you tell me where elevator is?` — article: **elevator** → `the elevator`

### complimentary breakfast

- **перевод (промпт):** бесплатный завтрак
- **эталон-пример:** The hotel offers a complimentary breakfast every morning.
- **дистракторы:**
    - `The hotel offer a complimentary breakfast every morning.` — tense: **offer** → `offers`

### What time is check-out?

- **перевод (промпт):** Во сколько выезд?
- **эталон-пример:** Excuse me, what time is check-out tomorrow?
- **принимаемые варианты:**
    - `What time do we check out?` — _Синоним, но сохраняет значение._
    - `What time is the check-out?` — _Синоним, но с другим порядком слов._
- **дистракторы:**
    - `Excuse me, what time are check-out tomorrow?` — tense: **are check-out** → `is check-out`

### Do you offer room service?

- **перевод (промпт):** Есть ли у вас обслуживание номеров?
- **эталон-пример:** After a long day, I ordered dinner through room service.
- **дистракторы:**
    - `After a long day, I ordered dinner by room service.` — preposition: **by room service** → `through room service`

### Wi-Fi password

- **перевод (промпт):** пароль от Wi-Fi
- **эталон-пример:** Can you give me the Wi-Fi password, please?
- **дистракторы:**
    - `Can you give me a Wi-Fi password, please?` — article: **a Wi-Fi password** → `the Wi-Fi password`
    - `Can you give me the Wi-Fi password, pleases?` — tense: **pleases** → `please`

### twin room

- **перевод (промпт):** номер с двумя раздельными кроватями
- **эталон-пример:** We booked a twin room for our stay.
- **принимаемые варианты:**
    - `double room` — _Также подходит для обозначения номера с двумя кроватями._
- **дистракторы:**
    - `We booked a twin room for our stays.` — tense: **our stays** → `our stay`
    - `We booked a twin rooms for our stay.` — article: **twin rooms** → `twin room`
    - `We booked twin room for our stay.` — article: **twin room** → `a twin room`

### concierge

- **перевод (промпт):** консьерж
- **эталон-пример:** You can ask the concierge for dinner recommendations.
- **принимаемые варианты:**
    - `doorman` — _другое слово, обозначающее "консьерж"._
    - `front desk attendant` — _синоним к слову "консьерж"._
- **дистракторы:**
    - `You can ask a concierge for dinner recommendations.` — article: **a concierge** → `the concierge`

### There's an issue with my room.

- **перевод (промпт):** Проблема с моим номером.
- **эталон-пример:** Excuse me, there's an issue with my room; the air conditioning isn't working.
- **дистракторы:**
    - `Excuse me, there is issue with my room; the air conditioning isn't working.` — article: **issue** → `an issue`

### Can I have extra towels?

- **перевод (промпт):** Можно мне дополнительные полотенца?
- **эталон-пример:** After a swim, I asked the front desk, 'Can I have extra towels?'
- **принимаемые варианты:**
    - `Could I get extra towels?` — _Это эквивалентный вопрос о том же._
    - `May I have extra towels?` — _Это вежливая форма просьбы._

### Could you change my reservation?

- **перевод (промпт):** Можете изменить моё бронирование?
- **эталон-пример:** Could you change my reservation to include an extra night?
- **дистракторы:**
    - `Could you change reservation to include an extra night?` — article: **reservation** → `my reservation`
    - `Could you change my reservation to includes an extra night?` — tense: **includes** → `include`

### front desk

- **перевод (промпт):** стойка регистрации
- **эталон-пример:** If you need any help, the front desk is available 24/7.
- **дистракторы:**
    - `If you need any help, the front desk is available at 24/7.` — preposition: **at 24/7** → `24/7`
    - `If you need any help, the front desk are available 24/7.` — tense: **are** → `is`
    - `If you need any help, a front desk is available 24/7.` — article: **a front desk** → `the front desk`

### housekeeping

- **перевод (промпт):** уборка
- **эталон-пример:** I asked housekeeping to clean my room while I was out.
- **дистракторы:**
    - `I asked housekeeping to clean a room while I was out.` — article: **a room** → `my room`
    - `I asked housekeeping to clean my room while I is out.` — tense: **is** → `was`
    - `I asked housekeeping to clean my room for I was out.` — preposition: **for I was out** → `while I was out`

### luggage storage

- **перевод (промпт):** хранение багажа
- **эталон-пример:** The hotel offers luggage storage for early arrivals.
- **дистракторы:**
    - `The hotel offer luggage storage for early arrivals.` — tense: **offer** → `offers`
    - `The hotel offers luggage storage at early arrivals.` — preposition: **at early arrivals** → `for early arrivals`
    - `The hotel offers the luggage storage for early arrivals.` — article: **the luggage storage** → `luggage storage`

### wake-up call

- **перевод (промпт):** звонок-будильник
- **эталон-пример:** I requested a wake-up call for 6 AM.
- **принимаемые варианты:**
    - `wakeup call` — _Производное написание._
    - `alarm call` — _Синоним._
- **дистракторы:**
    - `I requested a wake-up call at 6 AM.` — preposition: **at** → `for`

## Городской транспорт и такси

### Where can I buy a ticket?

- **перевод (промпт):** Где я могу купить билет?
- **эталон-пример:** Where can I buy a ticket for the subway?
- **дистракторы:**
    - `Where can I buy a ticket in the subway?` — preposition: **in the subway** → `for the subway`
    - `Where can I buy ticket for the subway?` — article: **ticket** → `a ticket`

### I'm looking for the bus stop.

- **перевод (промпт):** Я ищу автобусную остановку.
- **эталон-пример:** I'm looking for the bus stop on Main Street.
- **принимаемые варианты:**
    - `I'm searching for the bus stop.` — _Синонимы._
- **дистракторы:**
    - `I'm looking for the bus stop in Main Street.` — preposition: **in Main Street** → `on Main Street`
    - `I'm looking for bus stop on Main Street.` — article: **bus stop** → `the bus stop`

### How much is a ticket?

- **перевод (промпт):** Сколько стоит билет?
- **эталон-пример:** How much is a ticket to downtown?
- **принимаемые варианты:**
    - `What is the price of a ticket?` — _Это синоним._
    - `How much does a ticket cost?` — _Это синоним._
- **дистракторы:**
    - `How much is ticket to downtown?` — article: **ticket** → `a ticket`

### get on

- **перевод (промпт):** сесть (в транспорт)
- **эталон-пример:** We need to get on the next bus to the city center.
- **дистракторы:**
    - `We need to get on a next bus to the city center.` — article: **a next** → `the next`

### get off

- **перевод (промпт):** выйти (из транспорта)
- **эталон-пример:** You'll get off at the next station.
- **принимаемые варианты:**
    - `alight` — _синоним_
- **дистракторы:**
    - `You'll get off in the next station.` — preposition: **in** → `at`

### How can I get to...?

- **перевод (промпт):** Как добраться до...?
- **эталон-пример:** How can I get to the museum from here?
- **дистракторы:**
    - `How can I got to the museum from here?` — tense: **got** → `get`
    - `How can I get to museum from here?` — article: **museum** → `the museum`
    - `How can I get to the museum at here?` — preposition: **at here** → `from here`

### I'd like a ticket to...

- **перевод (промпт):** Я бы хотел(а) билет до...
- **эталон-пример:** I'd like a ticket to the airport, please.
- **дистракторы:**
    - `I'd like a ticket to airport, please.` — article: **a ticket to airport** → `a ticket to the airport`

### bus schedule

- **перевод (промпт):** расписание автобусов
- **эталон-пример:** Check the bus schedule to see the next departure.
- **дистракторы:**
    - `Check the bus schedule to see a next departure.` — article: **a next** → `the next`
    - `Check the bus schedule to see the next departures.` — tense: **departures** → `departure`

### I'd like to book a taxi.

- **перевод (промпт):** Я хотел(а) бы заказать такси.
- **эталон-пример:** I'd like to book a taxi for 7pm.
- **дистракторы:**
    - `I'd like to book a taxi at 7pm.` — preposition: **at 7pm** → `for 7pm`

### Could you tell me the way to...?

- **перевод (промпт):** Не могли бы вы подсказать путь до...?
- **эталон-пример:** Could you tell me the way to the nearest subway station?
- **дистракторы:**
    - `Could you tell me the way at the nearest subway station?` — preposition: **at** → `to`
    - `Could you tell me way to the nearest subway station?` — article: **way** → `the way`

### change trains

- **перевод (промпт):** пересесть на другой поезд
- **эталон-пример:** We need to change trains at the next station.
- **дистракторы:**
    - `We need to change trains in the next station.` — preposition: **in the next station** → `at the next station`

### Excuse me, is this seat taken?

- **перевод (промпт):** Извините, это место занято?
- **эталон-пример:** Excuse me, is this seat taken, or can I sit here?
- **принимаемые варианты:**
    - `Excuse me, is this chair taken?` — _Синоним для места._
    - `Pardon me, is this seat taken?` — _Синоним._
- **дистракторы:**
    - `Excuse me, is this seat the taken, or can I sit here?` — article: **the taken** → `taken`
    - `Excuse me, is this seat taken, or can I sits here?` — tense: **sits** → `sit`

### mind the gap

- **перевод (промпт):** осторожно, промежуток (между вагоном и платформой)
- **эталон-пример:** Please mind the gap when exiting the train.
- **дистракторы:**
    - `Please mind gaps when exiting the train.` — tense: **gaps** → `the gap`
    - `Please mind the gap when exits the train.` — tense: **exits** → `exiting`

### pay by card

- **перевод (промпт):** оплатить картой
- **эталон-пример:** Can I pay by card for the taxi ride?
- **принимаемые варианты:**
    - `pay using a card` — _Такой вариант тоже допустим._
    - `pay with a card` — _Такой вариант тоже допустим._

### Is this the line for the bus?

- **перевод (промпт):** Это очередь на автобус?
- **эталон-пример:** Is this the line for the bus to the city center?
- **принимаемые варианты:**
    - `Is this the line to the bus?` — _Синонимы: линия для автобуса._
    - `Is this the queue for the bus?` — _Синонимы: очередь и линия._
- **дистракторы:**
    - `Is this line for the bus to the city center?` — article: **line** → `the line`

### public transport

- **перевод (промпт):** общественный транспорт
- **эталон-пример:** Public transport is very efficient in this city.
- **принимаемые варианты:**
    - `public transportation` — _Синонимы._
    - `mass transit` — _Синонимы._
- **дистракторы:**
    - `Public transport are very efficient in this city.` — tense: **are** → `is`
    - `Public transport is very efficiently in this city.` — false_friend: **efficiently** → `efficient`

### Please take me to...

- **перевод (промпт):** Отвезите меня, пожалуйста, в...
- **эталон-пример:** Please take me to the hotel on Elm Street.
- **дистракторы:**
    - `Please takes me to the hotel on Elm Street.` — tense: **takes** → `take`
    - `Please take me to the hotel in Elm Street.` — preposition: **in Elm Street** → `on Elm Street`
    - `Please take me to hotel on Elm Street.` — article: **hotel** → `the hotel`

## Собеседование в IT

### Could you tell me more about the team?

- **перевод (промпт):** Могли бы вы рассказать мне больше о команде?
- **эталон-пример:** Could you tell me more about the team I would be working with?
- **принимаемые варианты:**
    - `Can you tell me more about the team?` — _Это так же правильно._
    - `Would you mind telling me more about the team?` — _Это так же правильно._
- **дистракторы:**
    - `Could you tell me more about the team I would working with?` — tense: **would working** → `would be working`
    - `Could you tell more me about the team I would be working with?` — word_order: **more me** → `me more`
    - `Could you tell me more about team I would be working with?` — article: **team** → `the team`
- **флаги:**
    - ✍️ **не слово → правка** — Все слова в языке корректны.

### What programming languages are you proficient in?

- **перевод (промпт):** Какими языками программирования вы владеете?
- **эталон-пример:** What programming languages are you proficient in?
- **дистракторы:**
    - `What programming language are you proficient in?` — tense: **language** → `languages`

### work experience

- **перевод (промпт):** опыт работы
- **эталон-пример:** My work experience includes five years as a software developer.
- **принимаемые варианты:**
    - `professional experience` — _синоним_
- **дистракторы:**
    - `My work experience include five years as a software developer.` — tense: **include** → `includes`

### What are the main responsibilities of this role?

- **перевод (промпт):** Каковы основные обязанности в этой должности?
- **эталон-пример:** What are the main responsibilities of this role?
- **принимаемые варианты:**
    - `What are the key responsibilities of this role?` — _Синонимы._
    - `What are the primary responsibilities of this role?` — _Синонимы._
- **дистракторы:**
    - `What are main responsibilities of this role?` — article: **main responsibilities** → `the main responsibilities`
    - `What is the main responsibilities of this role?` — tense: **is the main responsibilities** → `are the main responsibilities`

### I'm confident in my problem-solving skills.

- **перевод (промпт):** Я уверен в своих навыках решения проблем.
- **эталон-пример:** I'm confident in my problem-solving skills and enjoy tackling complex issues.
- **дистракторы:**
    - `I'm confident in problem-solving skills and enjoy tackling complex issues.` — article: **in problem-solving skills** → `in my problem-solving skills`
    - `I'm confident in my problem-solving skills and enjoy to tackle complex issues.` — modal_to: **enjoy to tackle** → `enjoy tackling`

### offer letter

- **перевод (промпт):** письмо с предложением о работе
- **эталон-пример:** I was thrilled to receive the offer letter for the position.
- **дистракторы:**
    - `I was thrilled to receive offer letter for the position.` — article: **offer letter** → `the offer letter`

### collaborative environment

- **перевод (промпт):** среда, способствующая сотрудничеству
- **эталон-пример:** I thrive in a collaborative environment where teamwork is valued.
- **дистракторы:**
    - `I thrive in a collaborative environments where teamwork is valued.` — article: **a collaborative environments** → `a collaborative environment`

### I have experience with agile methodologies.

- **перевод (промпт):** У меня есть опыт работы с гибкими методологиями.
- **эталон-пример:** I have experience with agile methodologies, which helps in adapting to changes quickly.
- **дистракторы:**
    - `I have experience with a agile methodologies, which helps in adapting to changes quickly.` — article: **a agile** → `agile`
    - `I has experience with agile methodologies, which helps in adapting to changes quickly.` — tense: **I has** → `I have`
    - `I have experience on agile methodologies, which helps in adapting to changes quickly.` — preposition: **on agile** → `with agile`

### job offer

- **перевод (промпт):** предложение о работе
- **эталон-пример:** The company made me a job offer after the final interview.
- **дистракторы:**
    - `The company made me job offer after the final interview.` — article: **job offer** → `a job offer`

### What are your strengths and weaknesses?

- **перевод (промпт):** Каковы ваши сильные и слабые стороны?
- **эталон-пример:** During the interview, they asked me about my strengths and weaknesses.
- **дистракторы:**
    - `During the interview, they ask me about my strengths and weaknesses.` — tense: **ask** → `asked`

### multitasking ability

- **перевод (промпт):** способность к многозадачности
- **эталон-пример:** I mentioned my multitasking ability as one of my strengths.
- **дистракторы:**
    - `I mentioned the multitasking ability as one of my strengths.` — article: **the multitasking ability** → `my multitasking ability`

### get along with

- **перевод (промпт):** ладить с
- **эталон-пример:** I get along with my team well, making collaboration effective.
- **дистракторы:**
    - `I get along with team well, making collaboration effective.` — article: **team** → `my team`
    - `I get along with my team good, making collaboration effective.` — tense: **good** → `well`

### focus on results

- **перевод (промпт):** фокусироваться на результатах
- **эталон-пример:** I focus on results, ensuring that projects meet their objectives.
- **дистракторы:**
    - `I focuses on results, ensuring that projects meet their objectives.` — tense: **focuses** → `focus`
    - `I focus in results, ensuring that projects meet their objectives.` — preposition: **in** → `on`

### comfortable with

- **перевод (промпт):** уверенно работаю с
- **эталон-пример:** I'm comfortable with fast-paced environments and enjoy challenges.
- **принимаемые варианты:**
    - `at ease with` — _с темой, о которой говорится в контексте_
- **дистракторы:**
    - `I'm comfortable with fast-paced environments in enjoy challenges.` — preposition: **in enjoy** → `and enjoy`
- **флаги:**
    - ✍️ **не слово → правка** — "уверенно работаю с" должно быть "комфортно с"

### notice period

- **перевод (промпт):** период уведомления
- **эталон-пример:** I am currently in a one-month notice period with my current employer.
- **дистракторы:**
    - `I am currently in a one-month notice periods with my current employer.` — article: **notice periods** → `notice period`
    - `I am currently in one-month notice period with my current employer.` — article: **one-month** → `a one-month`
    - `I am currently in a one-month notice period to my current employer.` — preposition: **to my** → `with my`

### hands-on experience

- **перевод (промпт):** практический опыт
- **эталон-пример:** I have hands-on experience with JavaScript frameworks in previous projects.
- **дистракторы:**
    - `I have hands-on experience with the JavaScript frameworks in previous projects.` — article: **the JavaScript** → `JavaScript`
    - `I has hands-on experience with JavaScript frameworks in previous projects.` — tense: **I has** → `I have`

### When can you start?

- **перевод (промпт):** Когда вы можете начать?
- **эталон-пример:** They asked me when I could start if offered the position.
- **дистракторы:**
    - `They asked me when I could start if I offered the position.` — word_order: **if I offered** → `if offered`
    - `They asked me when I could start if the position offered.` — word_order: **the position offered** → `offered the position`
    - `They asked me when I can start if offered the position.` — tense: **can** → `could`

### What is the expected salary range?

- **перевод (промпт):** Какой ожидаемый диапазон зарплаты?
- **эталон-пример:** What is the expected salary range for this position?
- **дистракторы:**
    - `What is expected salary range for this position?` — article: **expected salary range** → `the expected salary range`

## Собеседование в IT: продвинутый уровень

### Tell me about a time you faced a challenge at work.

- **перевод (промпт):** Расскажите мне о случае, когда вы столкнулись с трудностью на работе.
- **эталон-пример:** Tell me about a time you faced a challenge at work and how you overcame it.
- **дистракторы:**
    - `Tell me about a time you faced challenge at work and how you overcame it.` — article: **challenge** → `a challenge`
    - `Tell me about the time you faced a challenge at work and how you overcame it.` — article: **the time** → `a time`

### scalability

- **перевод (промпт):** масштабируемость
- **эталон-пример:** Scalability is crucial for the system to handle a growing number of requests.
- **дистракторы:**
    - `Scalability are crucial for the system to handle a growing number of requests.` — tense: **are** → `is`

### Can you walk me through your design process?

- **перевод (промпт):** Можете рассказать о вашем процессе проектирования?
- **эталон-пример:** Can you walk me through your design process for this task?
- **принимаемые варианты:**
    - `Can you explain your design process to me?` — _Синоним выражения, указывающий на ту же идею._
    - `Can you guide me through your design process?` — _Синоним выражения, указывающий на ту же идею._
- **дистракторы:**
    - `Can you walk me through your design processes for this task?` — tense: **design processes** → `design process`
    - `Can you walk me through your design process at this task?` — preposition: **at this task** → `for this task`
- **флаги:**
    - 🌐 **не тот язык** — Фраза "Can you walk me through your design process?" использует неверный язык в английском.

### bottleneck

- **перевод (промпт):** узкое место
- **эталон-пример:** Identifying bottlenecks is key to optimizing system performance.
- **дистракторы:**
    - `Identifying bottlenecks are key to optimizing system performance.` — tense: **are** → `is`

### What are your salary expectations?

- **перевод (промпт):** Каковы ваши ожидания по зарплате?
- **эталон-пример:** What are your salary expectations for this role?
- **принимаемые варианты:**
    - `What are your salary requirements?` — _Это синонимично вопросу о зарплате._
- **дистракторы:**
    - `What are your salary expectations in this role?` — preposition: **in** → `for`

### trade-off

- **перевод (промпт):** компромисс
- **эталон-пример:** There is always a trade-off between performance and cost.
- **принимаемые варианты:**
    - `compromise` — _Синоним._
- **дистракторы:**
    - `There is always a trade-off between the performance and cost.` — article: **the performance** → `performance`
    - `There is always a trade-off on performance and cost.` — preposition: **on** → `between`
    - `There is always a trade-off between performance and the cost.` — article: **the cost** → `cost`

### How do you handle stress and pressure?

- **перевод (промпт):** Как вы справляетесь со стрессом и давлением?
- **эталон-пример:** How do you handle stress and pressure during tight deadlines?
- **дистракторы:**
    - `How do you handle stress and pressure at tight deadlines?` — preposition: **at tight** → `during tight`

### streamline

- **перевод (промпт):** упростить, оптимизировать
- **эталон-пример:** We need to streamline the process to improve efficiency.
- **принимаемые варианты:**
    - `optimize` — _Синоним термина._
    - `simplify` — _Синоним термина._
- **дистракторы:**
    - `We need to streamline process to improve efficiency.` — article: **streamline process** → `streamline the process`
    - `We need to streamline the process for improve efficiency.` — preposition: **for improve** → `to improve`

### Could you elaborate on that?

- **перевод (промпт):** Можете уточнить этот момент?
- **эталон-пример:** Could you elaborate on how you handled that situation?
- **дистракторы:**
    - `Could you elaborate on how you handle that situation?` — tense: **handle** → `handled`

### How do you prioritize your tasks?

- **перевод (промпт):** Как вы расставляете приоритеты в задачах?
- **эталон-пример:** How do you prioritize your tasks in a fast-paced environment?
- **дистракторы:**
    - `How do you prioritize your tasks in the fast-paced environment?` — article: **the fast-paced** → `a fast-paced`
    - `How do you prioritize your tasks at a fast-paced environment?` — preposition: **at** → `in`

### cloud architecture

- **перевод (промпт):** облачная архитектура
- **эталон-пример:** Optimizing cloud architecture can reduce costs significantly.
- **дистракторы:**
    - `Optimizing cloud architecture can reduce cost significantly.` — tense: **cost** → `costs`
    - `Optimizing the cloud architecture can reduce costs significantly.` — article: **the cloud architecture** → `cloud architecture`

### Can you give an example of a successful team project?

- **перевод (промпт):** Можете привести пример успешного командного проекта?
- **эталон-пример:** Can you give an example of a successful team project you led?
- **принимаемые варианты:**
    - `Could you provide an example of a successful team project?` — _Синонимичное выражение._
    - `Can you cite an example of a successful team project?` — _Синонимичное выражение._
- **дистракторы:**
    - `Can you give an example on a successful team project you led?` — preposition: **on** → `of`
    - `Can you give example of a successful team project you led?` — article: **example** → `an example`
    - `Can you gives an example of a successful team project you led?` — tense: **gives** → `give`

### negotiate a salary

- **перевод (промпт):** обсуждать зарплату
- **эталон-пример:** During the final round, she was ready to negotiate a salary that matched her experience.
- **принимаемые варианты:**
    - `negotiate a pay` — _Синоним обсуждать деньги._
    - `discuss a salary` — _Синоним обсуждать зарплату._
- **дистракторы:**
    - `During the final round, she was ready to negotiate salary that matched her experience.` — article: **negotiate salary** → `negotiate a salary`
    - `During the final round, she was ready to negotiate a salary which matched her experience.` — word_order: **which matched her experience** → `that matched her experience`

### resilient

- **перевод (промпт):** устойчивый
- **эталон-пример:** A resilient system can recover quickly from failures.
- **принимаемые варианты:**
    - `tough` — _Синоним._
    - `durable` — _Синоним._
- **дистракторы:**
    - `A resilient systems can recover quickly from failures.` — tense: **systems** → `system`
    - `A resilient system can recover quickly from the failures.` — article: **the failures** → `failures`

### Could you describe your leadership style?

- **перевод (промпт):** Можете описать ваш стиль лидерства?
- **эталон-пример:** Could you describe your leadership style in managing diverse teams?
- **дистракторы:**
    - `Could you describe your leadership style in manage diverse teams?` — tense: **manage** → `managing`
    - `Could you describe the leadership style in managing diverse teams?` — article: **the leadership style** → `your leadership style`

### bump up

- **перевод (промпт):** увеличить, повысить
- **эталон-пример:** They decided to bump up the offer after the negotiations.
- **дистракторы:**
    - `They decided to bump up offer after the negotiations.` — article: **offer** → `the offer`
    - `They decided to bump up the offer after negotiations.` — article: **negotiations** → `the negotiations`

### fault tolerance

- **перевод (промпт):** отказоустойчивость
- **эталон-пример:** Building fault tolerance into the system is a top priority.
- **дистракторы:**
    - `Building fault tolerance into the system are a top priority.` — tense: **are** → `is`

### What do you consider your greatest strength?

- **перевод (промпт):** Что вы считаете своей сильнейшей стороной?
- **эталон-пример:** What do you consider your greatest strength in professional setting?
- **принимаемые варианты:**
    - `What do you think is your greatest strength?` — _Это синонимично._
    - `What do you view as your greatest strength?` — _Это синонимично._
- **дистракторы:**
    - `What do you consider your greatest strength at professional setting?` — preposition: **at** → `in`
    - `What do you consider your greatest strengths in professional setting?` — tense: **strengths** → `strength`

### data redundancy

- **перевод (промпт):** избыточность данных
- **эталон-пример:** Ensuring data redundancy is important to prevent data loss.
- **дистракторы:**
    - `Ensuring the data redundancy is important to prevent data loss.` — article: **the data redundancy** → `data redundancy`
    - `Ensuring data redundancy is important for prevent data loss.` — preposition: **for prevent** → `to prevent`

### burning the candle at both ends

- **перевод (промпт):** работать на износ
- **эталон-пример:** During the project, we were burning the candle at both ends.
- **дистракторы:**
    - `During the project, we was burning the candle at both ends.` — tense: **was** → `were`

### How do you define success?

- **перевод (промпт):** Как вы определяете успех?
- **эталон-пример:** How do you define success in a collaborative project?
- **дистракторы:**
    - `How do you define a success in a collaborative project?` — article: **a success** → `success`

### iterate

- **перевод (промпт):** итерировать, дорабатывать итерациями
- **эталон-пример:** We must iterate several times to refine the design.
- **принимаемые варианты:**
    - `improve with iterations` — _Синонимично итерировать._
    - `refine through iterations` — _Синонимично итерировать._
- **дистракторы:**
    - `We must iterate several time to refine the design.` — tense: **time** → `times`
    - `We must iterate for several times to refine the design.` — preposition: **for several** → `several`

### a good fit for

- **перевод (промпт):** хорошо подходит для
- **эталон-пример:** With your skills, you would be a good fit for our team.
- **дистракторы:**
    - `With your skills, you would be good fit for our team.` — article: **good fit** → `a good fit`
    - `With your skills, you would be a good fits for our team.` — tense: **good fits** → `good fit`

### What motivates you?

- **перевод (промпт):** Что вас мотивирует?
- **эталон-пример:** What motivates you to achieve your goals?
- **принимаемые варианты:**
    - `What inspires you?` — _Синоним._
    - `What drives you?` — _Синоним._
- **дистракторы:**
    - `What motivates you for achieve your goals?` — preposition: **for** → `to`

### load balancing

- **перевод (промпт):** распределение нагрузки
- **эталон-пример:** Effective load balancing is crucial for system scalability.
- **дистракторы:**
    - `Effective load balancing are crucial for system scalability.` — tense: **are** → `is`

## Деловые созвоны и переписка

### schedule a meeting

- **перевод (промпт):** назначить встречу
- **эталон-пример:** Let's schedule a meeting for next week to discuss the project details.
- **дистракторы:**
    - `Let's schedule meeting for next week to discuss the project details.` — article: **schedule meeting** → `schedule a meeting`

### set up a conference call

- **перевод (промпт):** организовать конференц-звонок
- **эталон-пример:** I'll set up a conference call with the team for Friday afternoon.
- **дистракторы:**
    - `I set up a conference call with the team for Friday afternoon.` — tense: **I set up** → `I'll set up`
    - `I'll set up conference call with the team for Friday afternoon.` — article: **conference call** → `a conference call`
    - `I'll set up a conference call to the team for Friday afternoon.` — preposition: **to the team** → `with the team`

### agenda

- **перевод (промпт):** повестка дня
- **эталон-пример:** Please send the meeting agenda by tomorrow morning.
- **дистракторы:**
    - `Please send the meeting agenda at tomorrow morning.` — preposition: **at tomorrow** → `by tomorrow`
    - `Please send meeting agenda by tomorrow morning.` — article: **meeting agenda** → `the meeting agenda`
    - `Please send the meeting agendas by tomorrow morning.` — tense: **agendas** → `agenda`

### take minutes

- **перевод (промпт):** вести протокол встречи
- **эталон-пример:** Could you take minutes during the meeting?
- **дистракторы:**
    - `Could you take the minutes during the meeting?` — article: **the minutes** → `minutes`

### conference call

- **перевод (промпт):** конференц-звонок
- **эталон-пример:** We have a conference call scheduled for 3 PM.
- **дистракторы:**
    - `We have a conference call scheduled to 3 PM.` — preposition: **to 3 PM** → `for 3 PM`
    - `We have a conference call schedule for 3 PM.` — tense: **schedule** → `scheduled`
    - `We have conference call scheduled for 3 PM.` — article: **conference call** → `a conference call`

### follow up

- **перевод (промпт):** следить за выполнением
- **эталон-пример:** I'll follow up with you after the meeting to ensure everything is clear.
- **дистракторы:**
    - `I'll follow up with you after the meeting to ensure all is clear.` — article: **all** → `everything`
    - `I'll follow up you after the meeting to ensure everything is clear.` — preposition: **follow up you** → `follow up with you`

### make a call

- **перевод (промпт):** позвонить
- **эталон-пример:** I need to make a call to confirm our meeting.
- **дистракторы:**
    - `I need to make call to confirm our meeting.` — article: **make call** → `make a call`

### send an email

- **перевод (промпт):** отправить электронное письмо
- **эталон-пример:** Please send an email with the revised document attached.
- **дистракторы:**
    - `Please send email with the revised document attached.` — article: **email** → `an email`
    - `Please send an email by the revised document attached.` — preposition: **by the** → `with the`

### put someone on hold

- **перевод (промпт):** поставить на удержание
- **эталон-пример:** I'll put you on hold while I transfer your call to the manager.
- **дистракторы:**
    - `I'll put you on the hold while I transfer your call to the manager.` — article: **the hold** → `hold`

### mark as urgent

- **перевод (промпт):** отметить как срочное
- **эталон-пример:** Please mark the email as urgent for immediate attention.
- **дистракторы:**
    - `Please marks the email as urgent for immediate attention.` — tense: **marks** → `mark`
    - `Please mark email as urgent for immediate attention.` — article: **email** → `the email`
    - `Please mark the email for urgent for immediate attention.` — preposition: **for urgent** → `as urgent`

### at your earliest convenience

- **перевод (промпт):** в ближайшее удобное для вас время
- **эталон-пример:** Please review the document at your earliest convenience.
- **дистракторы:**
    - `Please review the document in your earliest convenience.` — preposition: **in** → `at`

### in the loop

- **перевод (промпт):** быть в курсе дел
- **эталон-пример:** Make sure to keep me in the loop with any updates on the project.
- **дистракторы:**
    - `Make sure to keep me in loop with any updates on the project.` — article: **in loop** → `in the loop`
    - `Make sure to keep me on the loop with any updates on the project.` — preposition: **on the loop** → `in the loop`

### as per our conversation

- **перевод (промпт):** как мы обсуждали
- **эталон-пример:** As per our conversation, I've attached the report for your review.
- **дистракторы:**
    - `As per our conversation, I've attached the report in your review.` — preposition: **in** → `for`
    - `As per our conversation, I've attach the report for your review.` — tense: **attach** → `attached`

### on the same page

- **перевод (промпт):** быть на одной волне
- **эталон-пример:** It's important for the team to be on the same page about the project goals.
- **дистракторы:**
    - `It's important for the team to be on same page about the project goals.` — article: **on same page** → `on the same page`

### attached

- **перевод (промпт):** приложенный (о файле)
- **эталон-пример:** Please see the attached document for further details.
- **принимаемые варианты:**
    - `included` — _синоним слова "attached"._
    - `enclosed` — _синоним слова "attached"._

### meeting request

- **перевод (промпт):** запрос на встречу
- **эталон-пример:** I have sent you a meeting request for next Tuesday.
- **дистракторы:**
    - `I have sent you meeting request for next Tuesday.` — article: **meeting request** → `a meeting request`
    - `I have send you a meeting request for next Tuesday.` — tense: **send** → `sent`

### follow-up email

- **перевод (промпт):** последующее письмо
- **эталон-пример:** I will send a follow-up email to confirm the details discussed.
- **дистракторы:**
    - `I will send a follow-up email for confirm the details discussed.` — preposition: **for confirm** → `to confirm`
    - `I will send follow-up email to confirm the details discussed.` — article: **follow-up email** → `a follow-up email`

### in touch

- **перевод (промпт):** быть на связи
- **эталон-пример:** We'll be in touch with more information next week.
- **дистракторы:**
    - `We'll be in touch with the more information next week.` — article: **the more** → `more`
    - `We'll be in touch for more information next week.` — preposition: **for** → `with`

### get back on track

- **перевод (промпт):** вернуться в рабочее русло
- **эталон-пример:** We need to get back on track with our deadlines after the delay.
- **дистракторы:**
    - `We needs to get back on track with our deadlines after the delay.` — tense: **needs** → `need`
    - `We need to get back in track with our deadlines after the delay.` — preposition: **in track** → `on track`

### clarify

- **перевод (промпт):** уточнить
- **эталон-пример:** Could you clarify what you mean by 'additional resources'?
- **принимаемые варианты:**
    - `explain` — _Синоним уточнить._
    - `elucidate` — _Синоним уточнить._
- **дистракторы:**
    - `Could you clarify what you mean about 'additional resources'?` — preposition: **about** → `by`
    - `Could you clarify what mean by 'additional resources'?` — tense: **mean** → `you mean`

### to whom it may concern

- **перевод (промпт):** всем, кого это касается
- **эталон-пример:** The letter was addressed 'To whom it may concern' at the top.
- **дистракторы:**
    - `The letter was addressed 'To whom it may concern' on the top.` — preposition: **on the top** → `at the top`
    - `The letter was addressed 'To whom it may concerns' at the top.` — tense: **concerns** → `concern`

### reach out

- **перевод (промпт):** связаться
- **эталон-пример:** Please reach out if you have any questions or concerns.
- **принимаемые варианты:**
    - `contact` — _Синоним выражения._
    - `get in touch` — _Синоним выражения._
- **дистракторы:**
    - `Please reachs out if you have any questions or concerns.` — tense: **reachs** → `reach`

## Компьютер и интернет

### set up a new account

- **перевод (промпт):** создать новый аккаунт
- **эталон-пример:** I need to set up a new account for this service.
- **принимаемые варианты:**
    - `create a new account` — _Синоним "set up" в этом контексте._
    - `open a new account` — _Синоним "set up" в этом контексте._
- **дистракторы:**
    - `I need to set up the new account for this service.` — article: **the new account** → `a new account`

### connect to Wi-Fi

- **перевод (промпт):** подключиться к Wi-Fi
- **эталон-пример:** Make sure you connect to Wi-Fi to save on data usage.
- **дистракторы:**
    - `Make sure you connect at Wi-Fi to save on data usage.` — preposition: **at Wi-Fi** → `to Wi-Fi`
    - `Make sure you connects to Wi-Fi to save on data usage.` — tense: **connects** → `connect`

### download a file

- **перевод (промпт):** загрузить файл
- **эталон-пример:** Can you download the file from the website?
- **принимаемые варианты:**
    - `download the file` — _Синоним "загрузить файл"._
    - `obtain a file` — _Синоним "загрузить файл"._
- **дистракторы:**
    - `Can you download the file in the website?` — preposition: **in** → `from`

### troubleshoot a problem

- **перевод (промпт):** устранять неполадку
- **эталон-пример:** You might need to troubleshoot a problem with the printer.
- **дистракторы:**
    - `You might need to troubleshoot problems with the printer.` — tense: **problems** → `a problem`

### log in

- **перевод (промпт):** войти (в систему)
- **эталон-пример:** Please log in to access your account.
- **дистракторы:**
    - `Please logs in to access your account.` — tense: **logs** → `log`
    - `Please log in to access the account.` — article: **the account** → `your account`
    - `Please log in for access your account.` — preposition: **for access** → `to access`

### reset your password

- **перевод (промпт):** сбросить свой пароль
- **эталон-пример:** If you've forgotten your password, reset it via email.
- **дистракторы:**
    - `If you forgotten your password, reset it via email.` — tense: **you forgotten** → `you've forgotten`

### device

- **перевод (промпт):** устройство
- **эталон-пример:** This is a new device that connects to the internet.
- **принимаемые варианты:**
    - `tool` — _Это синоним слова "устройство"._
    - `gadget` — _Это синоним слова "устройство"._
- **дистракторы:**
    - `This is a new device which connects to the internet.` — false_friend: **which** → `that`
    - `This is new device that connects to the internet.` — article: **new device** → `a new device`

### file

- **перевод (промпт):** файл
- **эталон-пример:** Save the document as a PDF file.
- **дистракторы:**
    - `Save the document as PDF file.` — article: **PDF file** → `a PDF file`

### Wi-Fi network

- **перевод (промпт):** сеть Wi-Fi
- **эталон-пример:** Choose the correct Wi-Fi network from the list.
- **дистракторы:**
    - `Choose the correct Wi-Fi network of the list.` — preposition: **of the list** → `from the list`

### upload a document

- **перевод (промпт):** загрузить документ
- **эталон-пример:** Please upload your document to the portal.
- **принимаемые варианты:**
    - `upload a file` — _Синоним по смыслу._
    - `submit a document` — _Синоним по смыслу._
- **дистракторы:**
    - `Please upload your document in the portal.` — preposition: **in the portal** → `to the portal`
    - `Please upload the document to the portal.` — article: **the document** → `your document`

### connectivity issues

- **перевод (промпт):** проблемы с подключением
- **эталон-пример:** We are experiencing connectivity issues with the internet.
- **дистракторы:**
    - `We are experiencing connectivity issues on the internet.` — preposition: **on the internet** → `with the internet`
    - `We are experiencing connectivity issue with the internet.` — article: **connectivity issue** → `connectivity issues`
    - `We are experience connectivity issues with the internet.` — tense: **are experience** → `are experiencing`

### user-friendly

- **перевод (промпт):** удобный для пользователя
- **эталон-пример:** This software is very user-friendly.
- **дистракторы:**
    - `This software is very the user-friendly.` — article: **the user-friendly** → `user-friendly`

### reboot the system

- **перевод (промпт):** перезагрузить систему
- **эталон-пример:** Try to reboot the system to fix the issue.
- **дистракторы:**
    - `Try to reboots the system to fix the issue.` — tense: **reboots** → `reboot`
    - `Try to reboot in the system to fix the issue.` — preposition: **in the system** → `the system`

### create a backup

- **перевод (промпт):** создать резервную копию
- **эталон-пример:** It's important to create a backup of your files regularly.
- **дистракторы:**
    - `It's important to create backup of your files regularly.` — article: **backup** → `a backup`

### desktop

- **перевод (промпт):** настольный компьютер
- **эталон-пример:** I prefer using a desktop for complex tasks.
- **дистракторы:**
    - `I prefer using the desktop for complex tasks.` — article: **the desktop** → `a desktop`

### run a program

- **перевод (промпт):** запустить программу
- **эталон-пример:** Click this icon to run the program.
- **дистракторы:**
    - `Click this icon to running the program.` — tense: **running** → `run`
    - `Click the icon to run the program.` — article: **the** → `this`

### update software

- **перевод (промпт):** обновить программное обеспечение
- **эталон-пример:** You should update your software to the latest version.
- **дистракторы:**
    - `You should updates your software to the latest version.` — tense: **updates** → `update`
    - `You should update the software to the latest version.` — article: **the software** → `your software`
    - `You should update your software at the latest version.` — preposition: **at the latest version** → `to the latest version`

### secure your account

- **перевод (промпт):** обезопасить свой аккаунт
- **эталон-пример:** Enable two-factor authentication to secure your account.
- **дистракторы:**
    - `Enable two-factor authentication for secure your account.` — preposition: **for secure** → `to secure`
    - `Enable two-factor authentication to secure the account.` — article: **the account** → `your account`
    - `Enable two-factor authentication to secure you account.` — false_friend: **you account** → `your account`

### internet browser

- **перевод (промпт):** интернет-браузер
- **эталон-пример:** Which internet browser do you use the most?
- **дистракторы:**
    - `Which internet browsers do you use the most?` — tense: **browsers** → `browser`

### hardware

- **перевод (промпт):** аппаратное обеспечение
- **эталон-пример:** The hardware needs an upgrade to run the newest software.
- **дистракторы:**
    - `The hardware need an upgrade to run the newest software.` — tense: **need** → `needs`

