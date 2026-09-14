# GEN-2a · «Счёт и карта» — bank (начальный)

Цель плана (слова ученика): «Открываю счёт и банковскую карту в банке. Я студент, приехал учиться на год»

Сцена: «Счёт и карта» / «Account and card» · ученик: Студент, клиент банка · собеседник: Сотрудница банка (женщина)

Промт `lesson_day.v4.4` · модель `gpt-5.4-2026-03-05` · урок $0.075450 · 28.9 с · токены вход/выход 6636/3924 · одна попытка: да · находок валидатора: 21

> Колонка «оценка» пустая — ставит Ден: **✓** / **так не говорят** / **слишком длинно** / **не то слово**. Реплики ученика — как их получит приложение (сервер собирает их из каркаса и наполнения); если модель написала иначе, её текст — в скобках.

## Сценарий диалога

| # | вид обмена | кто | реплика | перевод | каркас · наполнение | оценка |
|---|---|---|---|---|---|---|
| 1 | ответ | Сотрудница банка (собеседник) | How can I help you today? | Чем я могу вам сегодня помочь? |  | |
| 1 | ответ | Студент, клиент банка (ученик) | I want to open a bank account. | Я хочу открыть банковский счёт. | p1 · a bank account | |
| 2 | ответ | Сотрудница банка (собеседник) | Please show your passport. | Пожалуйста, покажите ваш паспорт. |  | |
| 2 | ответ | Студент, клиент банка (ученик) | Here is my my passport. (модель: «Here is my passport.») | Вот мой паспорт. | p2 · my passport | |
| 3 | ответ | Сотрудница банка (собеседник) | Do you have proof of address? | У вас есть подтверждение адреса? |  | |
| 3 | ответ | Студент, клиент банка (ученик) | Here is my my rental letter. (модель: «Here is my rental letter.») | Вот моё письмо об аренде. | p2 · my rental letter | |
| 4 | ответ | Сотрудница банка (собеседник) | Are you a student here? | Вы здесь студент? |  | |
| 4 | ответ | Студент, клиент банка (ученик) | I am a student. | Я студент. | p3 · a student | |
| 5 | вопрос ученика | Студент, клиент банка (ученик) | What is the monthly fee? | Какая ежемесячная комиссия? | p4 · the monthly fee | |
| 5 | вопрос ученика | Сотрудница банка (собеседник) | The student account has no monthly fee. | У студенческого счёта нет ежемесячной комиссии. |  | |
| 6 | вопрос ученика | Студент, клиент банка (ученик) | When will the bank card arrive? | Когда придёт банковская карта? | p5 · the bank card | |
| 6 | вопрос ученика | Сотрудница банка (собеседник) | It will arrive by post in seven days. | Она придёт по почте через семь дней. |  | |
| 7 | вопрос ученика | Студент, клиент банка (ученик) | How long will the account stay open? | Как долго счёт будет открыт? | p6 · the account | |
| 7 | вопрос ученика | Сотрудница банка (собеседник) | It stays open for one year, then you can renew it. | Он остаётся открытым один год, потом вы можете продлить его. |  | |
| 8 | переспрос | Студент, клиент банка (ученик) | Could you repeat that more slowly, please? | Повторите, пожалуйста, помедленнее. | — | |
| 8 | переспрос | Сотрудница банка (собеседник) | One year. Then you can renew it. | Один год. Потом вы можете продлить его. |  | |

## Каркасы

### p1 · ответ — «I want to open ___.»

По-русски: «Я хочу открыть ___.» · чтение: «Ай уонт ту оупэн ___.» · окно: «что вы хотите открыть» · звучит в обменах: 1

| наполнение | перевод | чтение | в диалоге | собранная фраза | собранный перевод | оценка |
|---|---|---|---|---|---|---|
| a bank account | банковский счёт | э бэнк экаунт | да | I want to open a bank account. | Я хочу открыть банковский счёт. | |
| a student account | студенческий счёт | э стьюдэнт экаунт | — | I want to open a student account. | Я хочу открыть студенческий счёт. | |
| a new account | новый счёт | э нью экаунт | — | I want to open a new account. | Я хочу открыть новый счёт. | |

### p2 · ответ — «Here is my ___.»

По-русски: «Вот мой/моё ___.» · чтение: «Хиэр из май ___.» · окно: «какой документ вы показываете» · звучит в обменах: 2, 3

| наполнение | перевод | чтение | в диалоге | собранная фраза | собранный перевод | оценка |
|---|---|---|---|---|---|---|
| my passport | мой паспорт | май пáспорт | да | Here is my my passport. | Вот мой/моё мой паспорт. | |
| my rental letter | моё письмо об аренде | май рэнтэл летэр | да | Here is my my rental letter. | Вот мой/моё моё письмо об аренде. | |
| my student letter | моё письмо из вуза | май стьюдэнт летэр | — | Here is my my student letter. | Вот мой/моё моё письмо из вуза. | |

### p3 · ответ — «I am ___.»

По-русски: «Я ___.» · чтение: «Ай эм ___.» · окно: «кто вы по статусу» · звучит в обменах: 4

| наполнение | перевод | чтение | в диалоге | собранная фраза | собранный перевод | оценка |
|---|---|---|---|---|---|---|
| a student | студент | э стьюдэнт | да | I am a student. | Я студент. | |
| an exchange student | студент по обмену | эн иксчейндж стьюдэнт | — | I am an exchange student. | Я студент по обмену. | |
| a first-year student | студент первого курса | э фёрст-йир стьюдэнт | — | I am a first-year student. | Я студент первого курса. | |

### p4 · вопрос ученика — «What is ___?»

По-русски: «Какая/какой ___?» · чтение: «Уот из ___?» · окно: «о какой комиссии или сумме вы спрашиваете» · звучит в обменах: 5

| наполнение | перевод | чтение | в диалоге | собранная фраза | собранный перевод | оценка |
|---|---|---|---|---|---|---|
| the monthly fee | ежемесячная комиссия | зэ мансли фи | да | What is the monthly fee? | Какая/какой ежемесячная комиссия? | |
| the card fee | комиссия за карту | зэ кард фи | — | What is the card fee? | Какая/какой комиссия за карту? | |
| the opening fee | комиссия за открытие | зи оупэнинг фи | — | What is the opening fee? | Какая/какой комиссия за открытие? | |

### p5 · вопрос ученика — «When will ___ arrive?»

По-русски: «Когда придёт ___?» · чтение: «Уэн уилл ___ эрайв?» · окно: «что должно прийти» · звучит в обменах: 6

| наполнение | перевод | чтение | в диалоге | собранная фраза | собранный перевод | оценка |
|---|---|---|---|---|---|---|
| the bank card | банковская карта | зэ бэнк кард | да | When will the bank card arrive? | Когда придёт банковская карта? | |
| the PIN letter | письмо с ПИН-кодом | зэ пин летэр | — | When will the PIN letter arrive? | Когда придёт письмо с ПИН-кодом? | |
| the account letter | письмо по счёту | зи экаунт летэр | — | When will the account letter arrive? | Когда придёт письмо по счёту? | |

### p6 · вопрос ученика — «How long will ___ stay open?»

По-русски: «Как долго ___ будет открыт?» · чтение: «Хау лонг уилл ___ стэй оупэн?» · окно: «что будет открыто» · звучит в обменах: 7

| наполнение | перевод | чтение | в диалоге | собранная фраза | собранный перевод | оценка |
|---|---|---|---|---|---|---|
| the account | счёт | зи экаунт | да | How long will the account stay open? | Как долго счёт будет открыт? | |
| the student account | студенческий счёт | зэ стьюдэнт экаунт | — | How long will the student account stay open? | Как долго студенческий счёт будет открыт? | |
| this account | этот счёт | зис экаунт | — | How long will this account stay open? | Как долго этот счёт будет открыт? | |

## Проверки обменов

| # | вопрос | варианты (✓ — верный) | пояснение | оценка |
|---|---|---|---|---|
| 1 | What does the clerk ask the learner? / О чём сотрудница банка спрашивает ученика? | How much money is in the account / Сколько денег на счёте · Which card was lost / Какую карту потеряли · ✓ What help is needed today / Какая помощь нужна сегодня | Сотрудница банка спрашивает, чем может помочь сегодня. | |
| 2 | Which document does the clerk ask for? / Какой документ просит сотрудница банка? | ✓ An identity booklet for travel / Паспорт · A rent contract / Договор аренды · A student card / Студенческий билет | Она просит показать паспорт. | |
| 3 | What extra paper does the clerk ask for? / Какую дополнительную бумагу просит сотрудница банка? | A paper about health insurance / Документ о медицинской страховке · ✓ A paper showing where the learner lives / Документ с адресом проживания · A paper from the university library / Документ из университетской библиотеки | Она спрашивает подтверждение адреса. | |
| 4 | What status does the clerk ask about? / О каком статусе спрашивает сотрудница банка? | Work status / Статус работника · Family status / Семейное положение · ✓ Student status / Статус студента | Она спрашивает, является ли клиент студентом. | |
| 5 | What does the clerk say about the account cost each month? / Что сотрудница говорит о ежемесячной стоимости счёта? | It costs ten pounds monthly / Он стоит десять фунтов в месяц · ✓ It is free each month / Он бесплатный каждый месяц · It depends on card use / Это зависит от использования карты | Она говорит, что ежемесячной комиссии нет. | |
| 6 | How does the clerk say the card will come? / Как, по словам сотрудницы, придёт карта? | ✓ It will come through the mail / Она придёт по почте · It must be collected at the branch / Её нужно забрать в отделении · It will be sent to the learner's home address / Её отправят на домашний адрес | Она говорит, что карта придёт по почте. | |
| 7 | What period does the clerk give for the account? / На какой срок, по словам сотрудницы, открыт счёт? | For two years / На два года · For one school term / На один учебный семестр · ✓ For twelve months / На двенадцать месяцев | Она говорит, что счёт открыт на один год. | |
| 8 | What can the learner do after one year? / Что ученик может сделать через год? | Change the branch address / Изменить адрес отделения · Close the card by phone / Закрыть карту по телефону · ✓ Extend the account / Продлить счёт | После года счёт можно продлить. | |

## Слушаю весь визит (listening)

| # | вопрос | варианты (✓ — верный) | пояснение | оценка |
|---|---|---|---|---|
| L1 | Что ученик хотел открыть в банке? | ✓ Банковский счёт · Страховой полис · Кредит на учёбу | В начале разговора ученик говорит, что хочет открыть банковский счёт. | |
| L2 | Что сотрудница сказала о ежемесячной комиссии? | ✓ Её нет · Она есть только летом · Она зависит от адреса | Сотрудница объясняет, что у студенческого счёта нет ежемесячной комиссии. | |
| L3 | Как ученик подтвердил адрес? | Банковской картой · ✓ Письмом об аренде · Студенческим билетом | На вопрос о подтверждении адреса ученик показывает письмо об аренде. | |
| L4 | Когда придёт банковская карта? | ✓ Через семь дней · В тот же день · Через месяц | Сотрудница говорит, что карта придёт по почте через семь дней. | |

## Словарь

| id | слово | вид | перевод | чтение | определение | где звучит | картинка (запрос) | оценка |
|---|---|---|---|---|---|---|---|---|
| v1 | bank account | связка | банковский счёт | бэнк экаунт | an account at a bank for keeping and using money | p1 | bank clerk and student at a desk opening a bank account | |
| v2 | passport | слово | паспорт | пáспорт | an official document that shows your identity and nationality | p2, A2 | passport on a bank counter next to application papers | |
| v3 | proof of address | связка | подтверждение адреса | пруф ов эдрэс | a document that shows where you live | A3 | rental letter with address details on a desk | |
| v4 | rental letter | связка | письмо об аренде | рэнтэл летэр | a letter that confirms a rental address | p2 | printed rental confirmation letter on a table | |
| v5 | student account | связка | студенческий счёт | стьюдэнт экаунт | a bank account for students, often with special conditions | p1, A5, p6 | student filling out bank forms at a branch desk | |
| v6 | monthly fee | связка | ежемесячная комиссия | мансли фи | money paid every month for a service | p4, A5 | — | |
| v7 | by post | связка | по почте | бай поуст | sent or delivered through the mail | A6 | sealed bank envelope in a home mailbox | |
| v8 | renew | слово | продлить | ринью | to continue something for a new period | A7, A8 | — | |

## Находки валидатора (режим наблюдения — день вышел)

| код | адрес | что |
|---|---|---|
| `pronunciation.script` | p2.f1 | the reading «май пáспорт» leaves the native script |
| `pronunciation.script` | v2 | the reading «пáспорт» leaves the native script |
| `pronunciation.script` | B2 | the reading «Хиэр из май пáспорт.» leaves the native script |
| `frame.native_alternatives` | p2 | «Вот мой/моё ___.» writes alternatives inside the frame |
| `frame.native_alternatives` | p4 | «Какая/какой ___?» writes alternatives inside the frame |
| `filler.ungrammatical` | p2.f1 | «Here is my my passport.»: a word is doubled at the seam |
| `filler.ungrammatical` | p2.f2 | «Here is my my rental letter.»: a word is doubled at the seam |
| `filler.ungrammatical` | p2.f3 | «Here is my my student letter.»: a word is doubled at the seam |
| `line.ne_frame` | B2 | «Here is my passport.» is not «Here is my ___.» with «my passport»; served as «Here is my my passport.» |
| `key.no_content_word` | B2 | the key «Here is my» has no content word |
| `line.ne_frame` | B3 | «Here is my rental letter.» is not «Here is my ___.» with «my rental letter»; served as «Here is my my rental letter.» |
| `key.no_content_word` | B3 | the key «Here is my» has no content word |
| `key.no_content_word` | B4 | the key «am a» has no content word |
| `key.contains_filler` | B4 | the key «am a» takes words of the filler «a student» |
| `key.no_content_word` | B5 | the key «What is the» has no content word |
| `key.contains_filler` | B5 | the key «What is the» takes words of the filler «the monthly fee» |
| `variant.longer` | B5 | the variant «How much is the monthly fee?» has 6 words, the line 5 |
| `key.no_content_word` | B6 | the key «When will the» has no content word |
| `key.contains_filler` | B6 | the key «When will the» takes words of the filler «the bank card» |
| `listening.distractor_not_filler` | L1 | the question asks p1's slot («банковский счёт»), but 0 of its wrong options are p1's other fillers (expected 2) |
| `listening.distractor_not_filler` | L3 | the question asks p2's slot («моё письмо об аренде»), but 0 of its wrong options are p2's other fillers (expected 2) |
