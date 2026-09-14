# GEN-2a · «Регистрация» — airport (начальный)

Цель плана (слова ученика): «Регистрация на рейс в аэропорту: паспорт, багаж, место в самолёте. Лечу с одним чемоданом и рюкзаком»

Сцена: «Регистрация» / «Check-in» · ученик: Пассажир · собеседник: Сотрудница регистрации (женщина)

Промт `lesson_day.v4.4` · модель `gpt-5.4-2026-03-05` · урок $0.075988 · 25.5 с · токены вход/выход 6611/3964 · одна попытка: да · находок валидатора: 13

> Колонка «оценка» пустая — ставит Ден: **✓** / **так не говорят** / **слишком длинно** / **не то слово**. Реплики ученика — как их получит приложение (сервер собирает их из каркаса и наполнения); если модель написала иначе, её текст — в скобках.

## Сценарий диалога

| # | вид обмена | кто | реплика | перевод | каркас · наполнение | оценка |
|---|---|---|---|---|---|---|
| 1 | ответ | Сотрудница регистрации (собеседник) | Good morning. May I see your passport? | Доброе утро. Можно ваш паспорт? |  | |
| 1 | ответ | Пассажир (ученик) | Here is my passport. | Вот мой паспорт. | p1 · my passport | |
| 2 | ответ | Сотрудница регистрации (собеседник) | What is your destination today? | Куда вы летите сегодня? |  | |
| 2 | ответ | Пассажир (ученик) | I'm flying to London. | Я лечу в Лондон. | p2 · London | |
| 3 | ответ | Сотрудница регистрации (собеседник) | How many bags do you have? | Сколько у вас сумок? |  | |
| 3 | ответ | Пассажир (ученик) | I have one suitcase and one backpack. | У меня один чемодан и один рюкзак. | p3 · one suitcase and one backpack | |
| 4 | ответ | Сотрудница регистрации (собеседник) | Which bag are you checking in? | Какую сумку вы сдаёте в багаж? |  | |
| 4 | ответ | Пассажир (ученик) | I'm checking in the suitcase. | Я сдаю в багаж чемодан. | p4 · the suitcase | |
| 5 | вопрос ученика | Пассажир (ученик) | Can I take the backpack onboard? | Можно взять рюкзак в салон? | p5 · the backpack | |
| 5 | вопрос ученика | Сотрудница регистрации (собеседник) | Yes, the backpack can go as hand luggage. | Да, рюкзак можно взять как ручную кладь. |  | |
| 6 | вопрос ученика | Пассажир (ученик) | I'd like a window seat. | Я бы хотел место у окна. | p6 · a window seat | |
| 6 | вопрос ученика | Сотрудница регистрации (собеседник) | I can offer seat 14A by the window. | Могу предложить место 14A у окна. |  | |
| 7 | вопрос ученика | Пассажир (ученик) | Where is the gate? | Где выход на посадку? | p7 · the gate | |
| 7 | вопрос ученика | Сотрудница регистрации (собеседник) | Your gate is A12, and boarding starts at 9:20. | Ваш выход A12, посадка начинается в 9:20. |  | |
| 8 | переспрос | Пассажир (ученик) | Could you repeat that more slowly, please? | Повторите, пожалуйста, помедленнее. | — | |
| 8 | переспрос | Сотрудница регистрации (собеседник) | Gate A12. Boarding starts at 9:20. | Выход A12. Посадка начинается в 9:20. |  | |

## Каркасы

### p1 · ответ — «Here is ___.»

По-русски: «Вот ___.» · чтение: «Хиэр из ___.» · окно: «что вы показываете» · звучит в обменах: 1

| наполнение | перевод | чтение | в диалоге | собранная фраза | собранный перевод | оценка |
|---|---|---|---|---|---|---|
| my passport | мой паспорт | май пасспорт | да | Here is my passport. | Вот мой паспорт. | |
| my boarding pass | мой посадочный талон | май бординг пас | — | Here is my boarding pass. | Вот мой посадочный талон. | |
| my ticket | мой билет | май тикит | — | Here is my ticket. | Вот мой билет. | |

### p2 · ответ — «I'm flying to ___.»

По-русски: «Я лечу в ___.» · чтение: «Айм флайинг ту ___.» · окно: «город назначения» · звучит в обменах: 2

| наполнение | перевод | чтение | в диалоге | собранная фраза | собранный перевод | оценка |
|---|---|---|---|---|---|---|
| London | Лондон | Ландэн | да | I'm flying to London. | Я лечу в Лондон. | |
| Paris | Париж | Пэрис | — | I'm flying to Paris. | Я лечу в Париж. | |
| Rome | Рим | Роум | — | I'm flying to Rome. | Я лечу в Рим. | |

### p3 · ответ — «I have ___.»

По-русски: «У меня ___.» · чтение: «Ай хэв ___.» · окно: «какие у вас сумки» · звучит в обменах: 3

| наполнение | перевод | чтение | в диалоге | собранная фраза | собранный перевод | оценка |
|---|---|---|---|---|---|---|
| one suitcase and one backpack | один чемодан и один рюкзак | уан сьюткейс энд уан бэкпэк | да | I have one suitcase and one backpack. | У меня один чемодан и один рюкзак. | |
| one suitcase | один чемодан | уан сьюткейс | — | I have one suitcase. | У меня один чемодан. | |
| two backpacks | два рюкзака | ту бэкпэкс | — | I have two backpacks. | У меня два рюкзака. | |

### p4 · ответ — «I'm checking in ___.»

По-русски: «Я сдаю в багаж ___.» · чтение: «Айм чекин ин ___.» · окно: «что вы сдаёте в багаж» · звучит в обменах: 4

| наполнение | перевод | чтение | в диалоге | собранная фраза | собранный перевод | оценка |
|---|---|---|---|---|---|---|
| the suitcase | чемодан | зэ сьюткейс | да | I'm checking in the suitcase. | Я сдаю в багаж чемодан. | |
| the large bag | большую сумку | зэ лардж бэг | — | I'm checking in the large bag. | Я сдаю в багаж большую сумку. | |
| this bag | эту сумку | зис бэг | — | I'm checking in this bag. | Я сдаю в багаж эту сумку. | |

### p5 · вопрос ученика — «Can I take ___ onboard?»

По-русски: «Можно взять ___ в салон?» · чтение: «Кэн ай тэйк ___ онборд?» · окно: «что вы хотите взять с собой» · звучит в обменах: 5

| наполнение | перевод | чтение | в диалоге | собранная фраза | собранный перевод | оценка |
|---|---|---|---|---|---|---|
| the backpack | рюкзак | зэ бэкпэк | да | Can I take the backpack onboard? | Можно взять рюкзак в салон? | |
| this bag | эту сумку | зис бэг | — | Can I take this bag onboard? | Можно взять эту сумку в салон? | |
| my laptop | мой ноутбук | май лэптоп | — | Can I take my laptop onboard? | Можно взять мой ноутбук в салон? | |

### p6 · вопрос ученика — «I'd like ___.»

По-русски: «Я бы хотел ___.» · чтение: «Айд лайк ___.» · окно: «какое место вы хотите» · звучит в обменах: 6

| наполнение | перевод | чтение | в диалоге | собранная фраза | собранный перевод | оценка |
|---|---|---|---|---|---|---|
| a window seat | место у окна | э уиндоу сит | да | I'd like a window seat. | Я бы хотел место у окна. | |
| an aisle seat | место у прохода | эн айл сит | — | I'd like an aisle seat. | Я бы хотел место у прохода. | |
| an extra-legroom seat | место с дополнительным местом для ног | эн экстра легрум сит | — | I'd like an extra-legroom seat. | Я бы хотел место с дополнительным местом для ног. | |

### p7 · вопрос ученика — «Where is ___?»

По-русски: «Где ___?» · чтение: «Уэр из ___?» · окно: «что вы ищете» · звучит в обменах: 7

| наполнение | перевод | чтение | в диалоге | собранная фраза | собранный перевод | оценка |
|---|---|---|---|---|---|---|
| the gate | выход на посадку | зэ гейт | да | Where is the gate? | Где выход на посадку? | |
| the check-in desk | стойка регистрации | зэ чек-ин деск | — | Where is the check-in desk? | Где стойка регистрации? | |
| the restroom | туалет | зэ реструм | — | Where is the restroom? | Где туалет? | |

## Проверки обменов

| # | вопрос | варианты (✓ — верный) | пояснение | оценка |
|---|---|---|---|---|
| 1 | What does the agent ask to see? / Что сотрудница просит показать? | A suitcase tag / Багажную бирку · ✓ An ID document for travel / Документ для поездки · A boarding pass / Посадочный талон | Сотрудница просит показать паспорт. | |
| 2 | What does the agent want to know? / Что сотрудница хочет узнать? | ✓ The city on the ticket / Город в билете · The passenger's arrival time / Время прилёта пассажира · The passenger's seat number / Номер места пассажира | Сотрудница спрашивает, в какой пункт назначения летит пассажир. | |
| 3 | What is the agent asking about? / О чём спрашивает сотрудница? | The boarding gate / Выход на посадку · ✓ The number of bags / Количество сумок · The passenger's passport / Паспорт пассажира | Сотрудница спрашивает, сколько у пассажира сумок. | |
| 4 | What does the agent ask the passenger to identify? / Какую вещь сотрудница просит уточнить? | The passport photo / Фото в паспорте · The flight number / Номер рейса · ✓ The bag for the cargo hold / Сумку для багажного отделения | Сотрудница спрашивает, какую сумку пассажир сдаёт в багаж. | |
| 5 | What does the agent say about the backpack? / Что сотрудница говорит о рюкзаке? | It is too heavy for the flight / Он слишком тяжёлый для рейса · It must be checked with the suitcase / Его нужно сдать вместе с чемоданом · ✓ It can stay with the passenger in the cabin / Его можно оставить с собой в салоне | Сотрудница говорит, что рюкзак можно взять как ручную кладь. | |
| 6 | Which seat does the agent offer? / Какое место предлагает сотрудница? | 15C near the aisle / 15C у прохода · 12B in the middle / 12B посередине · ✓ 14A next to the window / 14A у окна | Сотрудница предлагает место 14A у окна. | |
| 7 | When does boarding begin? / Когда начинается посадка? | ✓ At twenty past nine / В 9:20 · At nine o'clock / В 9:00 · At ten to nine / В 8:50 | Сотрудница говорит, что посадка начинается в 9:20. | |
| 8 | Which gate number does the agent repeat? / Какой номер выхода повторяет сотрудница? | B21 / B21 · A21 / A21 · ✓ A12 / A12 | В повторе сотрудница говорит, что выход A12. | |

## Слушаю весь визит (listening)

| # | вопрос | варианты (✓ — верный) | пояснение | оценка |
|---|---|---|---|---|
| L1 | Куда летит пассажир? | ✓ В Лондон · В Рим · В Париж | Пассажир говорит, что летит в Лондон. | |
| L2 | Какую сумку пассажир сдаёт в багаж? | Рюкзак · ✓ Чемодан · Никакую | Пассажир говорит, что сдаёт в багаж чемодан. | |
| L3 | Какое место предлагает сотрудница? | 15C у прохода · 12B посередине · ✓ 14A у окна | Сотрудница предлагает место 14A у окна. | |
| L4 | Во сколько начинается посадка? | В 8:50 · В 10:00 · ✓ В 9:20 | Сотрудница говорит, что посадка начинается в 9:20. | |

## Словарь

| id | слово | вид | перевод | чтение | определение | где звучит | картинка (запрос) | оценка |
|---|---|---|---|---|---|---|---|---|
| v1 | passport | слово | паспорт | пасспорт | an official document used for international travel | p1, A1 | passport on an airport check-in counter | |
| v2 | destination | слово | пункт назначения | дестинейшн | the place you are traveling to | A2 | — | |
| v3 | suitcase | слово | чемодан | сьюткейс | a large travel bag with a handle | p3, p4 | suitcase beside an airport check-in desk | |
| v4 | backpack | слово | рюкзак | бэкпэк | a bag carried on your back | p3, p5, A5 | backpack on the floor at an airport counter | |
| v5 | check in | связка | регистрировать, сдавать | чек ин | to register for a flight or give a bag to the airline | p4 | airline agent tagging a suitcase at check-in | |
| v6 | hand luggage | связка | ручная кладь | хэнд лагидж | bags you take with you into the plane cabin | A5 | small travel bag in an airplane overhead bin | |
| v7 | window seat | связка | место у окна | уиндоу сит | a seat next to the window on a plane | p6, A6 | airplane seat beside a window | |
| v8 | boarding | слово | посадка | бординг | the process of getting onto the plane | A7, A8 | airport gate screen showing boarding time | |

## Находки валидатора (режим наблюдения — день вышел)

| код | адрес | что |
|---|---|---|
| `key.no_content_word` | B1 | the key «Here is my» has no content word |
| `key.contains_filler` | B1 | the key «Here is my» takes words of the filler «my passport» |
| `variant.longer` | B2 | the variant «My flight is to London.» has 5 words, the line 4 |
| `key.no_content_word` | B3 | the key «I have» has no content word |
| `key.contains_filler` | B5 | the key «take the» takes words of the filler «the backpack» |
| `key.contains_filler` | B6 | the key «like a» takes words of the filler «a window seat» |
| `key.no_content_word` | B7 | the key «Where is the» has no content word |
| `key.contains_filler` | B7 | the key «Where is the» takes words of the filler «the gate» |
| `check.verbatim` | x6.check | the right option «14A next to the window» repeats «the window» of the partner's line |
| `listening.distractor_not_filler` | L2 | the question asks p3's slot («один чемодан и один рюкзак»), but 1 of its wrong options are p3's other fillers (expected 2) |
| `listening.distractor_not_filler` | L3 | the question asks p6's slot («место у окна»), but 1 of its wrong options are p6's other fillers (expected 2) |
| `vocab.used_in_wrong` | v5 | «check in» is not in frame p4 or its fillers |
| `vocab.used_in_wrong` | v7 | «window seat» is not in the partner's line of exchange 6 |
