# Позвонить в банк, выяснить, почему карта заблокирована, и попросить разблокировать ее

- план `01M1VTFKZ4N3HXVY5X8EYASNX7` · ru→en · basic · событие 2026-09-08 · статус active
- цель: «Звоню в банк: карту заблокировали, надо выяснить причину и разблокировать её»
- сводка: Позвонить в банк, выяснить, почему карта заблокирована, и попросить разблокировать ее

## Сцены каркаса (P1)

### Сцена 1 — Звонок в банк

Вы звоните в банк по поводу заблокированной карты. Нужно сразу назвать проблему, пройти к нужному сотруднику и коротко объяснить, что произошло. Успех в этой сцене — вас правильно поняли и начали проверку.

- умение ``: сможете сообщить, что карта заблокирована — чек: может сказать, что карта не работает и ее заблокировали
- умение ``: сможете попросить соединить с нужным отделом — чек: может попросить перевести звонок к сотруднику по картам
- opening_lines: «Hello, thank you for calling the bank. How can I help you today?» · «Please tell me what the problem is.» · «Are you calling about your debit card or credit card?» · «I will connect you to our card services team.» · «Please hold for a moment.»
- entities: debit card, credit card, card services

### Сцена 2 — Проверка личности

Сотрудник банка задаст вопросы, чтобы убедиться, что это вы. Нужно спокойно отвечать на стандартные запросы и, если нужно, просить повторить. Успех в этой сцене — вы проходите проверку и разговор идет дальше.

- умение ``: сможете пройти базовую проверку личности по телефону — чек: может ответить на запрос имени, даты рождения и последних цифр карты
- умение ``: сможете попросить повторить вопрос медленнее — чек: может вежливо попросить повторить или говорить медленнее
- opening_lines: «Before I can help you, I need to verify your identity.» · «Can you confirm your full name, please?» · «Can you confirm your date of birth?» · «Please provide the last four digits of your card.» · «Could you answer your security question?» · «I am sorry, could you repeat that?»
- entities: last four digits, security question, date of birth

### Сцена 3 — Причина блокировки

После проверки сотрудник объяснит, почему карту заблокировали, или задаст вопросы о последних операциях. Нужно понять основную причину и сказать, узнаете ли вы эти операции. Успех в этой сцене — причина блокировки ясна.

- умение ``: сможете спросить, почему карту заблокировали — чек: может прямо спросить причину блокировки
- умение ``: сможете сказать, что операция ваша или не ваша — чек: может подтвердить знакомую операцию или сказать, что не делал ее
- opening_lines: «Your card was blocked because of unusual activity.» · «We noticed a transaction that looked suspicious.» · «Did you make a payment at this store?» · «Did you try to use your card abroad?» · «Do you recognize this transaction?» · «For your security, we temporarily blocked the card.»
- entities: transaction, store, abroad, unusual activity

### Сцена 4 — Разблокировка карты

Когда причина понятна, вы просите разблокировать карту или узнаете, что делать дальше. Иногда карту могут разблокировать сразу, а иногда предложат перевыпуск. Успех в этой сцене — вы понимаете следующий шаг и можете его подтвердить.

- умение ``: сможете попросить разблокировать карту — чек: может попросить снять блокировку с карты
- умение ``: сможете уточнить, когда карта снова будет работать — чек: может спросить, когда картой можно будет пользоваться
- умение ``: сможете понять, что карту нужно перевыпустить — чек: может понять, что старая карта не заработает и нужна новая
- opening_lines: «I can unblock your card now.» · «Your card should work again in a few minutes.» · «I am afraid we cannot unblock this card.» · «We need to issue a new card.» · «Would you like us to send a replacement card?» · «You should receive the new card within five to seven business days.»
- entities: replacement card, business days

## День 1 — Звонок в банк (intro, ready, попыток 1, починок 1)

### Пары (по цепочке)

**1. [answer]**
- role: «Hello, how can I help you?» — Здравствуйте, чем я могу помочь?
- you: «My card is blocked.» — Моя карта заблокирована. · ключ: `blocked` · s1.1

**2. [answer]**
- role: «Please tell me what the problem is.» — Скажите, в чем проблема.
- you: «It is not working.» — Она не работает. · ключ: `working` · s1.1

**3. [answer]**
- role: «Is it a debit card or credit card?» — Это дебетовая карта или кредитная карта?
- you: «It is a debit card.» — Это дебетовая карта. · ключ: `debit card` · s1.2

**4. [ask]**
- role: «Can I help with anything else?» — Я могу еще чем-нибудь помочь?
- you: «Please connect me to card services.» — Соедините меня, пожалуйста, с отделом карт. · ключ: `card services` · s1.2

**5. [answer]**
- role: «Please hold for a moment.» — Пожалуйста, подождите немного.
- you: «Sure, I'll hold.» — Конечно, я подожду. · ключ: _вся фраза_ · s1.1

### Слова и связки

- [words] **blocked** — заблокирована · пример: «The app says my card is blocked.» — В приложении написано, что моя карта заблокирована.
- [words] **working** — работает · пример: «My card was working this morning.» — Моя карта работала сегодня утром.
- [words] **team** — отдел · пример: «The team for cards is checking my account now.» — Отдел по картам сейчас проверяет мой счет.
- [chunks] **debit card** — дебетовая карта · пример: «I use my debit card every day.» — Я пользуюсь своей дебетовой картой каждый день.
- [chunks] **card services** — отдел карт · пример: «Card services can check why it was blocked.» — Отдел карт может проверить, почему ее заблокировали.

### Числа на слух

- «Please stay on the line for two minutes.» — Пожалуйста, оставайтесь на линии две минуты. · value `2`
- «Are the last four digits four eight two one?» — Последние четыре цифры — четыре восемь два один? · value `four eight two one`

### Спасатели (сервер)

- «Could you speak more slowly, please?» — Помедленнее, пожалуйста.
- «Could you write it down, please?» — Напишите, пожалуйста.
- «Could you repeat that, please?» — Повторите ещё раз, пожалуйста.
- «How much is it?» — Сколько это стоит?
- «One moment, let me check.» — Секунду, я проверю.

## День 2 — Проверка личности (intro, failed, попыток 2, починок 2)

**Отбой `card.kind_size`:** День не прошёл валидатор: card.kind_size [My last four digits are four eight two one.]: в карточке 9 слов(а), а полка «say» держит 3–8

_материала нет_

## День 3 — Прогон перед событием (final, pending, попыток 0, починок 0)

_материала нет_

## Реестр трат

| вызов | статус | версия | $ |
|---|---|---|---|
| outline: Звоню в банк: карту заблокировали, надо выяснить причину и разблокировать её | succeeded | plan_outline.v0.4.1 | 0.018923 |
| pair_judge: пара 0 — Hello, how can I help you? | succeeded | plan_pair_judge.v0.1 | 0.001198 |
| pair_judge: пара 1 — Please tell me what the problem is. | succeeded | plan_pair_judge.v0.1 | 0.001138 |
| pair_judge: пара 2 — Is it a debit card or credit card? | succeeded | plan_pair_judge.v0.1 | 0.001173 |
| pair_judge: пара 3 — Can I help with anything else? | succeeded | plan_pair_judge.v0.1 | 0.001155 |
| pair_judge: пара 4 — Please hold for a moment. | succeeded | plan_pair_judge.v0.1 | 0.001148 |
| pair_rewrite: пара 4 — Please hold for a moment. | succeeded | plan_pair_rewrite.v0.1 | 0.001885 |
| pair_judge: пара 4 — Please hold for a moment. | succeeded | plan_pair_judge.v0.1 | 0.001163 |
| day: день 1 — Звонок в банк | failed | plan_day.v0.6 | 0.023468 |
| day_repair: починка дня 1 — карточек 2 | succeeded | plan_day_repair.v0.2 | 0.010023 |
| pair_judge: пара 0 — Before I can help you, I need to verify your identity. | succeeded | plan_pair_judge.v0.1 | 0.001215 |
| pair_judge: пара 1 — Can you confirm your full name, please? | succeeded | plan_pair_judge.v0.1 | 0.001178 |
| pair_judge: пара 2 — Can you confirm your date of birth? | succeeded | plan_pair_judge.v0.1 | 0.001138 |
| pair_rewrite: пара 2 — Can you confirm your date of birth? | succeeded | plan_pair_rewrite.v0.1 | 0.001818 |
| pair_judge: пара 2 — Can you confirm your date of birth? | succeeded | plan_pair_judge.v0.1 | 0.001173 |
| pair_rewrite: пара 2 — Can you confirm your date of birth? | succeeded | plan_pair_rewrite.v0.1 | 0.002033 |
| pair_judge: пара 2 — Can you confirm your date of birth? | succeeded | plan_pair_judge.v0.1 | 0.001208 |
| pair_judge: пара 3 — Please provide the last four digits of your card. | succeeded | plan_pair_judge.v0.1 | 0.001185 |
| pair_judge: пара 4 — Is there anything you want to ask before we continue? | succeeded | plan_pair_judge.v0.1 | 0.001193 |
| day: день 2 — Проверка личности | failed | plan_day.v0.6 | 0.026760 |
| day_repair: починка дня 2 — карточек 3 | succeeded | plan_day_repair.v0.2 | 0.012453 |
| pair_judge: пара 0 — Before I can help you, I need to verify your identity. | succeeded | plan_pair_judge.v0.1 | 0.001163 |
| pair_judge: пара 1 — Can you confirm your full name, please? | succeeded | plan_pair_judge.v0.1 | 0.001178 |
| pair_judge: пара 2 — Can you confirm your date of birth? | succeeded | plan_pair_judge.v0.1 | 0.001138 |
| pair_judge: пара 3 — Please provide the last four digits of your card. | succeeded | plan_pair_judge.v0.1 | 0.001185 |
| pair_judge: пара 4 — Is there anything you want to ask? | succeeded | plan_pair_judge.v0.1 | 0.001213 |
| pair_rewrite: пара 4 — Is there anything you want to ask? | succeeded | plan_pair_rewrite.v0.1 | 0.001805 |
| pair_judge: пара 4 — Is there anything you want to ask? | succeeded | plan_pair_judge.v0.1 | 0.001183 |
| day: день 2 — Проверка личности | failed | plan_day.v0.6 | 0.027250 |
| day_repair: починка дня 2 — карточек 4 | succeeded | plan_day_repair.v0.2 | 0.014333 |
| **итого** | | | **0.163076** |

