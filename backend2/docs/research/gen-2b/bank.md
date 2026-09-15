# GEN-2b · «Счёт и карта» — bank (ru→en, начальный)

Цель плана (слова ученика): «Открываю счёт и банковскую карту в банке. Я студент, приехал учиться на год»

Ученик: Клиент · собеседник: Сотрудница банка (женщина)

Промт `lesson_day.v4.5` · модель `gpt-5.4-2026-03-05` · вызов урока $0.080070 · 36.9 с · токены вход/выход 7488/4090 · попыток урока: 1 · находок валидатора и судьи в ответе модели: 20 (фатальных 3) · порог в сборке: урок failed (fatal: line.ne_frame)

> Колонка «оценка» пустая — ставит Ден: **✓** / **так не говорят** / **слишком длинно** / **не то слово**. Это ответ модели БЕЗ починок; реплики ученика — как их получит приложение (сервер собирает их из каркаса и наполнения); если модель написала иначе, её текст — в скобках. «Судья» — вердикт судьи швов о собранной фразе на родном.

## Сценарий диалога

| # | вид обмена | кто | реплика | перевод | каркас · наполнение | ключ | оценка |
|---|---|---|---|---|---|---|---|
| 1 | вопрос ученика | Клиент (ученик) | I'd like to open a bank account. | Я хочу открыть банковский счёт. | p1 · a bank account | I'd like to open | |
| 1 | вопрос ученика | Сотрудница банка (собеседник) | Sure. Can I see your passport first? | Конечно. Можно сначала ваш паспорт? |  |  | |
| 2 | ответ | Сотрудница банка (собеседник) | I also need proof of address. | Мне ещё нужно подтверждение адреса. |  |  | |
| 2 | ответ | Клиент (ученик) | Here is my my rental contract. (модель: «Here is my rental contract.») | Вот мой договор аренды. | p2 · my rental contract | Here is my | |
| 3 | ответ | Сотрудница банка (собеседник) | Are you a student here? | Вы здесь студент? |  |  | |
| 3 | ответ | Клиент (ученик) | I'm a student. | Я студент. | p3 · a student | I'm a | |
| 4 | ответ | Сотрудница банка (собеседник) | What is your phone number? | Какой у вас номер телефона? |  |  | |
| 4 | ответ | Клиент (ученик) | My phone number is 07911 456 230. | Мой номер телефона 07911 456 230. | p4 · 07911 456 230 | phone number is | |
| 5 | вопрос ученика | Клиент (ученик) | What is the the monthly fee? (модель: «What is the monthly fee?») | Какая ежемесячная комиссия? | p5 · the monthly fee | monthly fee | |
| 5 | вопрос ученика | Сотрудница банка (собеседник) | For students, the account has no monthly fee. | Для студентов у этого счёта нет ежемесячной комиссии. |  |  | |
| 6 | вопрос ученика | Клиент (ученик) | When will the card be ready? | Когда карта будет готова? | p6 · the card | will the card | |
| 6 | вопрос ученика | Сотрудница банка (собеседник) | It will arrive in five to seven business days. | Она придёт через пять-семь рабочих дней. |  |  | |
| 7 | вопрос ученика | Клиент (ученик) | Can I keep it for one year? | Я могу оставить его на один год? | p7 · one year | keep it for | |
| 7 | вопрос ученика | Сотрудница банка (собеседник) | Yes. The account can stay open for your one-year course. | Да. Счёт может оставаться открытым на время вашего годичного курса. |  |  | |
| 8 | ответ | Сотрудница банка (собеседник) | I'll open the account today, and your card will come by post. | Я открою счёт сегодня, а ваша карта придёт по почте. |  |  | |
| 8 | ответ | Клиент (ученик) | The account opens today. | Счёт откроется сегодня. | p8 · today | opens today | |

## Каркасы

### p1 · вопрос ученика — «I'd like to open ___.»

На родном: «Я хочу открыть ___.» · чтение: «айд лайк ту оупэн ___» · окно: «что открыть» · звучит в обменах: 1

| наполнение | перевод | чтение | в диалоге | собранная фраза | собранный перевод | судья | оценка |
|---|---|---|---|---|---|---|---|
| a bank account | банковский счёт | э бэнк экаунт | да | I'd like to open a bank account. | Я хочу открыть банковский счёт. | читается | |
| a student account | студенческий счёт | э стьюдэнт экаунт | — | I'd like to open a student account. | Я хочу открыть студенческий счёт. | читается | |
| a joint account | совместный счёт | э джойнт экаунт | — | I'd like to open a joint account. | Я хочу открыть совместный счёт. | читается | |

### p2 · ответ — «Here is my ___.»

На родном: «Вот ___.» · чтение: «хир из май ___» · окно: «какой документ показать» · звучит в обменах: 2

| наполнение | перевод | чтение | в диалоге | собранная фраза | собранный перевод | судья | оценка |
|---|---|---|---|---|---|---|---|
| rental contract | мой договор аренды | рентэл контракт | да | Here is my rental contract. | Вот мой договор аренды. | читается | |
| passport | мой паспорт | паспорт | — | Here is my passport. | Вот мой паспорт. | читается | |
| student letter | моя справка из вуза | стьюдэнт летэр | — | Here is my student letter. | Вот моя справка из вуза. | читается | |

### p3 · ответ — «I'm ___.»

На родном: «Я ___.» · чтение: «айм ___» · окно: «кто вы» · звучит в обменах: 3

| наполнение | перевод | чтение | в диалоге | собранная фраза | собранный перевод | судья | оценка |
|---|---|---|---|---|---|---|---|
| a student | студент | э стьюдэнт | да | I'm a student. | Я студент. | читается | |
| an exchange student | студент по обмену | эн иксчейндж стьюдэнт | — | I'm an exchange student. | Я студент по обмену. | читается | |
| a first-year student | студент первого курса | э фёрст йир стьюдэнт | — | I'm a first-year student. | Я студент первого курса. | читается | |

### p4 · ответ — «My phone number is ___.»

На родном: «Мой номер телефона ___.» · чтение: «май фоун намбэр из ___» · окно: «номер телефона» · звучит в обменах: 4

| наполнение | перевод | чтение | в диалоге | собранная фраза | собранный перевод | судья | оценка |
|---|---|---|---|---|---|---|---|
| 07911 456 230 | 07911 456 230 | зироу сэвэн найн уан уан фор файв сикс ту сри зироу | да | My phone number is 07911 456 230. | Мой номер телефона 07911 456 230. | читается | |
| 07740 221 908 | 07740 221 908 | зироу сэвэн сэвэн фор зироу ту ту уан найн зироу эйт | — | My phone number is 07740 221 908. | Мой номер телефона 07740 221 908. | читается | |
| 07863 110 542 | 07863 110 542 | зироу сэвэн эйт сикс сри уан уан зироу файв фор ту | — | My phone number is 07863 110 542. | Мой номер телефона 07863 110 542. | читается | |

### p5 · вопрос ученика — «What is the ___?»

На родном: «Какая ___?» · чтение: «уот из зэ ___» · окно: «что узнать» · звучит в обменах: 5

| наполнение | перевод | чтение | в диалоге | собранная фраза | собранный перевод | судья | оценка |
|---|---|---|---|---|---|---|---|
| monthly fee | ежемесячная комиссия | мансли фи | да | What is the monthly fee? | Какая ежемесячная комиссия? | читается | |
| card fee | комиссия за карту | кард фи | — | What is the card fee? | Какая комиссия за карту? | читается | |
| closing process | процедура закрытия | клоузинг процесс | — | What is the closing process? | Какая процедура закрытия? | читается | |

### p6 · вопрос ученика — «When will ___ be ready?»

На родном: «Когда ___ будет готово?» · чтение: «уэн вил ___ би рэди» · окно: «что будет готово» · звучит в обменах: 6

| наполнение | перевод | чтение | в диалоге | собранная фраза | собранный перевод | судья | оценка |
|---|---|---|---|---|---|---|---|
| the card | карта | зэ кард | да | When will the card be ready? | Когда карта будет готово? | **не читается** | |
| the account | открытие счёта | зи экаунт | — | When will the account be ready? | Когда открытие счёта будет готово? | читается | |
| the online access | онлайн-доступ | зи онлайн эксэс | — | When will the online access be ready? | Когда онлайн-доступ будет готово? | **не читается** | |

### p7 · вопрос ученика — «Can I keep it for ___?»

На родном: «Я могу оставить его на ___?» · чтение: «кэн ай кип ит фор ___» · окно: «на какой срок» · звучит в обменах: 7

| наполнение | перевод | чтение | в диалоге | собранная фраза | собранный перевод | судья | оценка |
|---|---|---|---|---|---|---|---|
| one year | один год | уан йир | да | Can I keep it for one year? | Я могу оставить его на один год? | читается | |
| six months | шесть месяцев | сикс манс | — | Can I keep it for six months? | Я могу оставить его на шесть месяцев? | читается | |
| two years | два года | ту йирз | — | Can I keep it for two years? | Я могу оставить его на два года? | читается | |

### p8 · ответ — «The account opens ___.»

На родном: «Счёт откроется ___.» · чтение: «зи экаунт оупэнз ___» · окно: «когда» · звучит в обменах: 8

| наполнение | перевод | чтение | в диалоге | собранная фраза | собранный перевод | судья | оценка |
|---|---|---|---|---|---|---|---|
| today | сегодня | тудэй | да | The account opens today. | Счёт откроется сегодня. | читается | |
| tomorrow | завтра | тумороу | — | The account opens tomorrow. | Счёт откроется завтра. | читается | |
| this week | на этой неделе | зис уик | — | The account opens this week. | Счёт откроется на этой неделе. | читается | |

## Проверки обменов

| # | вопрос | варианты (✓ — верный) | пояснение | оценка |
|---|---|---|---|---|
| 1 | Which document does the clerk ask for first? / Какой документ сотрудница просит сначала? | A bank card / Банковскую карту · A student letter / Справку из вуза · ✓ A passport / Паспорт | Сотрудница сначала просит паспорт. | |
| 2 | What else does the clerk need? / Что ещё нужно сотруднице? | ✓ Something that shows where you live / Документ с адресом проживания · A photo for the card / Фотографию для карты · A cash deposit / Наличный взнос | Сотруднице нужно подтверждение адреса. | |
| 3 | What does the clerk ask about? / О чём спрашивает сотрудница? | Your age / О вашем возрасте · ✓ Your student status / О том, студент ли вы · Your job / О вашей работе | Сотрудница спрашивает, студент ли клиент. | |
| 4 | What information does the clerk ask for? / Какую информацию спрашивает сотрудница? | Your date of birth / Вашу дату рождения · ✓ Your phone contact / Ваш номер телефона · Your local address / Ваш местный адрес | Сотрудница спрашивает номер телефона. | |
| 5 | What does the clerk say about the fee? / Что сотрудница говорит о комиссии? | The fee starts next year / Комиссия начнётся в следующем году · ✓ It is free for students / Для студентов это бесплатно · Students pay every month / Студенты платят каждый месяц | Сотрудница говорит, что для студентов ежемесячной комиссии нет. | |
| 6 | When should the card arrive? / Когда должна прийти карта? | Later today / Позже сегодня · ✓ In about one week of working days / Примерно через неделю рабочих дней · In one month / Через месяц | Сотрудница говорит, что карта придёт через пять-семь рабочих дней. | |
| 7 | How long can the account stay open? / На какой срок счёт может оставаться открытым? | For five years automatically / Автоматически на пять лет · Only for this month / Только на этот месяц · ✓ For the student's one-year program / На время годичной учёбы | Сотрудница говорит, что счёт может быть открыт на время годичного курса. | |
| 8 | What happens today? / Что произойдёт сегодня? | The student pays a yearly fee / Студент заплатит годовую комиссию · ✓ The account is set up now / Счёт оформят сейчас · The card is used in a shop / Картой воспользуются в магазине | Сегодня сотрудница откроет счёт. | |

## Слушаю весь визит (listening)

| # | вопрос | варианты (✓ — верный) | пояснение | оценка |
|---|---|---|---|---|
| L1 | Какой документ сотрудница попросила сначала? | Справку из вуза · ✓ Паспорт · Банковскую карту | Сначала сотрудница попросила паспорт. | |
| L2 | Что клиент показал как подтверждение адреса? | Студенческий билет · Счёт за телефон · ✓ Договор аренды | Клиент показал договор аренды. | |
| L3 | Что сотрудница сказала про ежемесячную комиссию? | ✓ Для студентов её нет · Она есть у всех · Она будет через месяц | Для студентов ежемесячной комиссии нет. | |
| L4 | Когда должна прийти карта? | ✓ Через пять-семь рабочих дней · Сегодня вечером · Через два месяца | Карта должна прийти через пять-семь рабочих дней. | |

## Словарь

| id | слово | вид | перевод | чтение | определение | где звучит | картинка (запрос) | оценка |
|---|---|---|---|---|---|---|---|---|
| v1 | bank account | связка | банковский счёт | бэнк экаунт | an account you use at a bank to keep money | p1 | bank clerk and customer opening an account at a desk | |
| v2 | proof of address | связка | подтверждение адреса | пруф ов эдрэс | a document that shows where you live | A2 | rental contract and utility letter on a desk | |
| v3 | rental contract | связка | договор аренды | рентэл контракт | a document for renting a place to live | p2 | signed apartment rental contract on a table | |
| v4 | student status | связка | статус студента | стьюдэнт стейтэс | the fact that you are officially a student | A3 | — | |
| v5 | phone number | связка | номер телефона | фоун намбэр | the number people use to call you | p4, A4 | mobile phone screen with a contact number | |
| v6 | monthly fee | связка | ежемесячная комиссия | мансли фи | money you pay each month for a service | p5, A5 | bank fee information on a paper at a desk | |
| v7 | business days | связка | рабочие дни | бизнис дэйз | weekdays when banks and offices are open | A6 | desk calendar open on weekdays | |
| v8 | by post | связка | по почте | бай поуст | sent through the mail | A8 | bank card envelope in a mailbox | |

## Находки (ответ модели без починок; фатальные держат день до P2R)

| код | порог | адрес | что |
|---|---|---|---|
| `exchange.second_question` | **фатально** | x1 | the closing message of A «Sure. Can I see your passport first?» ends with a question mark |
| `frame.native_agreement` | предупреждение | p5 | «Какая ___?»: «какая» agrees with the slot — it changes with the filler |
| `frame.native_agreement` | предупреждение | p6 | «Когда ___ будет готово?»: «готово» agrees with the slot — it changes with the filler |
| `frame.unresolved_pronoun` | предупреждение | p7 | «Can I keep it for ___?» leans on «it», and nothing in the frame is what it stands for |
| `filler.one_in_dialogue` | предупреждение | B2 | «my rental contract» is not one of p2's fillers |
| `filler.one_in_dialogue` | предупреждение | p2.f1 | «rental contract» is marked in_dialogue, but no line says it |
| `filler.one_in_dialogue` | предупреждение | B5 | «the monthly fee» is not one of p5's fillers |
| `filler.one_in_dialogue` | предупреждение | p5.f1 | «monthly fee» is marked in_dialogue, but no line says it |
| `line.ne_frame` | **фатально** | B2 | «Here is my rental contract.» is not «Here is my ___.» with «my rental contract»; served as «Here is my my rental contract.» |
| `key.no_content_word` | предупреждение | B3 | «I'm ___.» has no content word outside the slot: the key is «I'm», the frame up to the slot, not «I'm a» |
| `key.contains_filler` | предупреждение | B3 | the key «I'm a» takes words of the filler «a student» |
| `variant.longer` | предупреждение | B3 | the variant «I am a student.» has 4 words, the line 3 |
| `line.ne_frame` | **фатально** | B5 | «What is the monthly fee?» is not «What is the ___?» with «the monthly fee»; served as «What is the the monthly fee?» |
| `key.contains_filler` | предупреждение | B5 | the key «monthly fee» takes words of the filler «the monthly fee» |
| `key.contains_filler` | предупреждение | B6 | the key «will the card» takes words of the filler «the card» |
| `key.contains_filler` | предупреждение | B8 | the key «opens today» takes words of the filler «today» |
| `listening.distractor_not_filler` | предупреждение | L3 | the question asks p3's slot («студент»): the right option «Для студентов её нет» is neither a number nor a time, the wrong option «Она будет через месяц» is a number or a time |
| `vocab.used_in_wrong` | предупреждение | v4 | «student status» is not in the partner's line of exchange 3 |
| `filler.native_seam` | предупреждение | p6.f1 | «Когда карта будет готово?» («Когда ___ будет готово?» with «карта») does not read as Russian, the seam judge says |
| `filler.native_seam` | предупреждение | p6.f3 | «Когда онлайн-доступ будет готово?» («Когда ___ будет готово?» with «онлайн-доступ») does not read as Russian, the seam judge says |

## Не проверено — у языка нет пакета (`lang.pack_missing`, не находка)

Всё проверено: пакеты обоих языков пары полные.

## Порог (фатальные коды → P2R, не больше двух карточек)

- **Живая сборка** (валидатор до двух уточнений отчёта §3, P2R на `gpt-5.4-mini`): P2R x1 (exchange, $0.005488, 1876 мс: exchange.second_question); B2 (line, $0.003517, 1772 мс: filler.one_in_dialogue, line.ne_frame); итог: failed — fatal: line.ne_frame.
- **Порог на валидаторе сдачи, P2R на `gpt-5.4`**: failed — fatal: line.ne_frame · карточки x1, B2 · $0.030086 · 4871 мс.
- **Порог на валидаторе сдачи, P2R на `gpt-5.4-mini`**: failed — fatal: exchange.second_question, line.ne_frame · карточки x1, B2 · $0.009032 · 4601 мс.
