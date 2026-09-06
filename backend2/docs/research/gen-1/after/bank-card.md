# Позвонить в банк, узнать, почему карту заблокировали, и попросить разблокировать ее или объяснить дальнейшие шаги.

- план `01M1W3H8F4GC64T76YTEAR0EY5` · ru→en · basic · событие 2026-09-08 · статус active
- цель: «Звоню в банк: карту заблокировали, надо выяснить причину и разблокировать её»
- сводка: Позвонить в банк, узнать, почему карту заблокировали, и попросить разблокировать ее или объяснить дальнейшие шаги.

## Сцены каркаса (P1)

### Сцена 1 — Звонок в банк

Ты звонишь в банк и попадаешь на сотрудника поддержки или в отдел по картам. Нужно коротко объяснить проблему и сразу дать понять, что карта не работает и тебе нужна помощь.

- умение `s1.1`: Кратко сообщить, что карта заблокирована и нужна проверка причины. — чек: Четко формулирует проблему в начале звонка.
- умение `s1.2`: Попросить соединить с нужным отделом или помочь по вопросу карты. — чек: Просит нужную помощь без долгих объяснений.
- opening_lines: «How can I help you today?» · «Please tell me what the problem is.» · «Are you calling about your bank card?» · «I’ll connect you to the card services team.» · «Can you briefly explain the issue?»
- entities: bank card, card services, customer support

### Сцена 2 — Проверка личности

Сотрудник должен убедиться, что разговаривает с владельцем карты. Тебя попросят подтвердить личность по стандартным данным и ответить на короткие вопросы.

- умение `s2.1`: Понимать просьбу подтвердить личность и спокойно реагировать на нее. — чек: Понимает, что сотрудник начал проверку личности.
- умение `s2.2`: Назвать основные данные для проверки личности. — чек: Сообщает запрошенные личные данные в нужный момент.
- opening_lines: «Before we continue, I need to verify your identity.» · «Can you confirm your full name, please?» · «Please confirm your date of birth.» · «Can you give me your address?» · «Can you confirm the last four digits of your card?»
- entities: full name, date of birth, address, customer number, last four digits

### Сцена 3 — Причина блокировки

После проверки сотрудник объясняет, почему карта была заблокирована или что банк видит в системе. Твоя задача — понять основную причину и уточнить, что именно произошло.

- умение `s3.1`: Спросить, почему карта была заблокирована. — чек: Задает прямой вопрос о причине блокировки.
- умение `s3.2`: Уточнить, была ли проблема из-за подозрительной операции, лимита или проверки безопасности. — чек: Переспрашивает и уточняет тип проблемы.
- opening_lines: «Your card was blocked for security reasons.» · «We noticed unusual activity on your card.» · «There was a suspicious transaction attempt.» · «The card has been temporarily blocked.» · «I can see a security hold on the card.»
- entities: security hold, suspicious transaction, unusual activity

### Сцена 4 — Разблокировка карты

Теперь нужно узнать, можно ли разблокировать карту прямо сейчас. Если это возможно, ты просишь об этом; если нет, выясняешь, что нужно сделать дальше.

- умение `s4.1`: Попросить разблокировать карту, если это возможно. — чек: Прямо просит разблокировать карту.
- умение `s4.2`: Узнать, какие действия нужны для разблокировки карты. — чек: Спрашивает о следующих шагах.
- opening_lines: «I can help you unblock the card.» · «For security reasons, I need to ask a few more questions.» · «Your card can be unblocked now.» · «I’m sorry, I can’t unblock it immediately.» · «You will need to confirm a recent transaction first.»
- entities: recent transaction, security questions, mobile app

### Сцена 5 — Если не разблокируют

Иногда карту нельзя разблокировать по телефону, и тогда банк предлагает перевыпуск или визит в отделение. Здесь важно понять итог и спросить, что делать, чтобы снова пользоваться картой.

- умение `s5.1`: Понять, что карту не разблокируют сразу и предложен другой вариант. — чек: Правильно повторяет итоговое решение банка.
- умение `s5.2`: Спросить, когда будет готова новая карта или когда можно снова пользоваться счетом. — чек: Уточняет срок или доступ к деньгам.
- opening_lines: «We need to issue a new card.» · «You’ll need to visit a branch with your ID.» · «Your account is still active, but the card cannot be used.» · «The replacement card will arrive within a few business days.» · «Would you like me to order a new card for you?»
- entities: branch, ID, replacement card, business days

## День 1 — Звонок в банк (intro, ready, попыток 1, починок 1)

### Пары (по цепочке)

**1. [answer]**
- role: «How can I help you today?» — Чем я могу вам помочь?
- you: «My card is blocked.» — Моя карта заблокирована. · ключ: `blocked` · ещё: `Card blocked` / `My card blocked` · s1.1

**2. [answer]**
- role: «Please tell me what the problem is.» — Скажите, в чем проблема.
- you: «My bank card is blocked.» — Моя банковская карта заблокирована. · ключ: `blocked` · ещё: `My card is blocked` / `It's blocked` · s1.1

**3. [answer]**
- role: «Are you calling about your bank card?» — Вы звоните насчет вашей банковской карты?
- you: «Yes, it does not work.» — Да, она не работает. · ключ: `work` · ещё: `It doesn't work` / `Yes not working` · s1.2

**4. [answer]**
- role: «I'll connect you to card services.» — Я соединю вас с отделом по картам.
- you: «Thanks, I need help with my card.» — Спасибо, мне нужна помощь с картой. · ключ: `help with` · ещё: `Need help` / `Help with my card` · s1.2

**5. [ask]**
- role: «Is there anything else you'd like to ask?» — Есть ли что-то еще, что вы хотели бы спросить?
- you: «Can you help with my card?» — Вы можете помочь с моей картой? · ключ: `help with` · ещё: `Can you help` / `Help with my card?` · s1.2

### Слова и связки

- [words] **blocked** — заблокирована · пример: «The app says my card is blocked.» — Приложение говорит, что моя карта заблокирована.
- [words] **work** — работать · пример: «My bank card does not work in stores.» — Моя банковская карта не работает в магазинах.
- [chunks] **card services** — отдел карт · пример: «Please transfer me to card services.» — Пожалуйста, переведите меня в отдел по картам.
- [chunks] **bank card** — банковская карта · пример: «I am calling about my bank card.» — Я звоню насчет моей банковской карты.
- [chunks] **help with** — помочь с · пример: «Can you help with this problem?» — Вы можете помочь с этой проблемой?

### Числа на слух

- «The last four digits are 4821?» — Последние четыре цифры — 4821? · value `4821`
- «I'll connect you in two minutes.» — Я соединю вас через 2 минуты. · value `2`

### Спасатели (сервер)

- «Could you speak more slowly, please?» — Помедленнее, пожалуйста.
- «Could you write it down, please?» — Напишите, пожалуйста.
- «Could you repeat that, please?» — Повторите ещё раз, пожалуйста.
- «How much is it?» — Сколько это стоит?
- «One moment, let me check.» — Секунду, я проверю.

## День 2 — Проверка личности (intro, ready, попыток 2, починок 2)

### Пары (по цепочке)

**1. [answer]**
- role: «Before we continue, I need to verify your identity.» — Прежде чем мы продолжим, мне нужно подтвердить вашу личность.
- you: «Yes, of course.» — Да, конечно. · ключ: `course` · ещё: `yes sure` / `okay` · s2.1

**2. [answer]**
- role: «Can you confirm your full name, please?» — Вы можете подтвердить свое полное имя, пожалуйста?
- you: «My full name is Ivan Petrov.» — Мое полное имя Иван Петров. · ключ: `full name` · ещё: `ivan petrov` / `my name is ivan` · s2.2

**3. [answer]**
- role: «Please confirm your date of birth.» — Пожалуйста, подтвердите дату вашего рождения.
- you: «It is 14 May 1990.» — Это 14 мая 1990 года. · ключ: `14 May 1990` · ещё: `may fourteenth` / `fourteen may nineteen ninety` · s2.2

**4. [answer]**
- role: «Can you give me your address?» — Вы можете назвать мне свой адрес?
- you: «My address is 12 Green Street.» — Мой адрес 12 Green Street. · ключ: `address` · ещё: `twelve green street` / `my address is green street` · s2.2

**5. [ask]**
- role: «Do you need anything else from me?» — Вам еще что-нибудь нужно от меня?
- you: «Why is my card blocked?» — Почему моя карта заблокирована? · ключ: `blocked` · ещё: `why blocked` / `why is it blocked` · s2.1

### Слова и связки

- [words] **verify** — подтверждать · пример: «The bank must verify my identity first.» — Банк должен сначала подтвердить мою личность.
- [words] **course** — конечно · пример: «Yes, of course, I can answer that.» — Да, конечно, я могу на это ответить.
- [words] **address** — адрес · пример: «The address on my account is correct.» — Адрес в моем счете указан верно.
- [chunks] **full name** — полное имя · пример: «Please say your full name clearly.» — Пожалуйста, назовите свое полное имя четко.
- [chunks] **date of birth** — дата рождения · пример: «I can confirm my date of birth now.» — Я могу сейчас подтвердить свою дату рождения.
- [chunks] **confirm your** — подтвердить свое · пример: «They need to confirm your details first.» — Им нужно сначала подтвердить ваши данные.

### Числа на слух

- «Is it 14 May 1990?» — Это 14 мая 1990 года? · value `1990-05-14`
- «Is your address 12 Green Street?» — Ваш адрес 12 Green Street? · value `12`
- «Are the last four digits 4821?» — Последние четыре цифры 4821? · value `4821`

## День 3 — Прогон перед событием (final, pending, попыток 0, починок 0)

_материала нет_

## Реестр трат

| вызов | статус | версия | $ |
|---|---|---|---|
| outline: Звоню в банк: карту заблокировали, надо выяснить причину и разблокировать её | succeeded | plan_outline.v0.4.2 | 0.021680 |
| pair_judge: пара 0 — How can I help you today? | succeeded | plan_pair_judge.v0.2 | 0.002883 |
| pair_judge: пара 1 — Please tell me what the problem is. | succeeded | plan_pair_judge.v0.2 | 0.003093 |
| pair_rewrite: пара 1 — Please tell me what the problem is. | succeeded | plan_pair_rewrite.v0.2 | 0.002925 |
| pair_judge: пара 1 — Please tell me what the problem is. | succeeded | plan_pair_judge.v0.2 | 0.002902 |
| pair_judge: пара 2 — Are you calling about your bank card? | succeeded | plan_pair_judge.v0.2 | 0.002895 |
| pair_judge: пара 3 — I'll connect you to card services. | succeeded | plan_pair_judge.v0.2 | 0.002908 |
| pair_judge: пара 4 — Is there anything else you'd like to ask? | succeeded | plan_pair_judge.v0.2 | 0.002908 |
| day: день 1 — Звонок в банк | failed | plan_day.v0.7 | 0.027760 |
| day_repair: починка дня 1 — карточек 2 | succeeded | plan_day_repair.v0.3 | 0.011495 |
| pair_judge: пара 0 — Before we continue, I need to verify your identity. | succeeded | plan_pair_judge.v0.2 | 0.002905 |
| pair_judge: пара 1 — Can you confirm your full name, please? | succeeded | plan_pair_judge.v0.2 | 0.002908 |
| pair_judge: пара 2 — Please confirm your date of birth. | succeeded | plan_pair_judge.v0.2 | 0.002922 |
| pair_judge: пара 3 — Can you give me your address? | succeeded | plan_pair_judge.v0.2 | 0.002905 |
| pair_judge: пара 4 — Can you confirm the last four digits of your card? | succeeded | plan_pair_judge.v0.2 | 0.002900 |
| pair_judge: пара 5 — Is there anything else you'd like to ask? | succeeded | plan_pair_judge.v0.2 | 0.002912 |
| day: день 2 — Проверка личности | failed | plan_day.v0.7 | 0.032195 |
| day_repair: починка дня 2 — карточек 4 | succeeded | plan_day_repair.v0.3 | 0.015920 |
| pair_judge: пара 5 — Do you need any more help? | succeeded | plan_pair_judge.v0.2 | 0.002893 |
| pair_judge: пара 0 — Before we continue, I need to verify your identity. | succeeded | plan_pair_judge.v0.2 | 0.002905 |
| pair_judge: пара 1 — Can you confirm your full name, please? | succeeded | plan_pair_judge.v0.2 | 0.002910 |
| pair_judge: пара 2 — Please confirm your date of birth. | succeeded | plan_pair_judge.v0.2 | 0.002905 |
| pair_judge: пара 3 — Can you give me your address? | succeeded | plan_pair_judge.v0.2 | 0.002893 |
| pair_judge: пара 4 — Is there anything else you'd like to ask? | succeeded | plan_pair_judge.v0.2 | 0.002900 |
| day: день 2 — Проверка личности | failed | plan_day.v0.7 | 0.029735 |
| day_repair: починка дня 2 — карточек 1 | succeeded | plan_day_repair.v0.3 | 0.009583 |
| pair_judge: пара 4 — Do you need anything else from me? | succeeded | plan_pair_judge.v0.2 | 0.002893 |
| **итого** | | | **0.206633** |

