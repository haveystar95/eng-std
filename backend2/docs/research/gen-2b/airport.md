# GEN-2b · «Регистрация» — airport (ru→en, начальный)

Цель плана (слова ученика): «Регистрация на рейс в аэропорту: паспорт, багаж, место в самолёте. Лечу с одним чемоданом и рюкзаком»

Ученик: Пассажир · собеседник: Сотрудница регистрации (женщина)

Промт `lesson_day.v4.5` · модель `gpt-5.4-2026-03-05` · вызов урока $0.076003 · 31.9 с · токены вход/выход 7463/3823 · попыток урока: 1 · находок валидатора и судьи в ответе модели: 10 (фатальных 6) · порог в сборке: урок failed (fatal: line.ne_frame)

> Колонка «оценка» пустая — ставит Ден: **✓** / **так не говорят** / **слишком длинно** / **не то слово**. Это ответ модели БЕЗ починок; реплики ученика — как их получит приложение (сервер собирает их из каркаса и наполнения); если модель написала иначе, её текст — в скобках. «Судья» — вердикт судьи швов о собранной фразе на родном.

## Сценарий диалога

| # | вид обмена | кто | реплика | перевод | каркас · наполнение | ключ | оценка |
|---|---|---|---|---|---|---|---|
| 1 | вопрос ученика | Пассажир (ученик) | I'm checking in for this flight (модель: «I'm checking in for this flight.») | Я регистрируюсь на этот рейс. | p1 · for this flight | checking in | |
| 1 | вопрос ученика | Сотрудница регистрации (собеседник) | Sure. May I see your passport? | Конечно. Можно ваш паспорт? |  |  | |
| 2 | ответ | Сотрудница регистрации (собеседник) | Thank you. Where are you flying today? | Спасибо. Куда вы летите сегодня? |  |  | |
| 2 | ответ | Пассажир (ученик) | I'm flying to London (модель: «I'm flying to London.») | Я лечу в Лондон. | p2 · to London | flying to | |
| 3 | ответ | Сотрудница регистрации (собеседник) | Do you have any bags to check? | У вас есть багаж для сдачи? |  |  | |
| 3 | ответ | Пассажир (ученик) | I have one suitcase (модель: «I have one suitcase.») | У меня один чемодан. | p3 · one suitcase | I have | |
| 4 | вопрос ученика | Пассажир (ученик) | Can I take this backpack? | Можно взять этот рюкзак? | p4 · this backpack | Can I take | |
| 4 | вопрос ученика | Сотрудница регистрации (собеседник) | Yes, that can go as hand luggage. | Да, это можно взять как ручную кладь. |  |  | |
| 5 | вопрос ученика | Пассажир (ученик) | Can I have a window seat? | Можно место у окна? | p5 · a window seat | have a | |
| 5 | вопрос ученика | Сотрудница регистрации (собеседник) | Yes, seat 14A is available. | Да, место 14A свободно. |  |  | |
| 6 | ответ | Сотрудница регистрации (собеседник) | Here is your boarding pass. Gate 12, boarding at 18:40. | Вот ваш посадочный талон. Выход 12, посадка в 18:40. |  |  | |
| 6 | ответ | Пассажир (ученик) | My gate is Gate 12 (модель: «My gate is Gate 12.») | Мой выход — 12. | p6 · Gate 12 | gate is | |
| 7 | переспрос | Пассажир (ученик) | Could you repeat that, please? | Повторите, пожалуйста. | — | repeat that | |
| 7 | переспрос | Сотрудница регистрации (собеседник) | Gate 12. Boarding is at 18:40. | Выход 12. Посадка в 18:40. |  |  | |
| 8 | ответ | Сотрудница регистрации (собеседник) | Your suitcase is checked through to London. | Ваш чемодан зарегистрирован до Лондона. |  |  | |
| 8 | ответ | Пассажир (ученик) | My suitcase goes to London (модель: «My suitcase goes to London.») | Мой чемодан едет в Лондон. | p7 · to London | suitcase goes | |

## Каркасы

### p1 · вопрос ученика — «I'm checking in ___»

На родном: «Я регистрируюсь ___» · чтение: «Айм чекин ин ___» · окно: «на какой рейс» · звучит в обменах: 1

| наполнение | перевод | чтение | в диалоге | собранная фраза | собранный перевод | судья | оценка |
|---|---|---|---|---|---|---|---|
| for this flight | на этот рейс | фор зис флайт | да | I'm checking in for this flight | Я регистрируюсь на этот рейс | читается | |
| for the morning flight | на утренний рейс | фор зе морнин флайт | — | I'm checking in for the morning flight | Я регистрируюсь на утренний рейс | читается | |

### p2 · ответ — «I'm flying ___»

На родном: «Я лечу ___» · чтение: «Айм флайинг ___» · окно: «куда» · звучит в обменах: 2

| наполнение | перевод | чтение | в диалоге | собранная фраза | собранный перевод | судья | оценка |
|---|---|---|---|---|---|---|---|
| to London | в Лондон | ту Ландан | да | I'm flying to London | Я лечу в Лондон | читается | |
| to Paris | в Париж | ту Пэрис | — | I'm flying to Paris | Я лечу в Париж | читается | |
| to Rome | в Рим | ту Роум | — | I'm flying to Rome | Я лечу в Рим | читается | |

### p3 · ответ — «I have ___»

На родном: «У меня ___» · чтение: «Ай хэв ___» · окно: «какой багаж есть» · звучит в обменах: 3

| наполнение | перевод | чтение | в диалоге | собранная фраза | собранный перевод | судья | оценка |
|---|---|---|---|---|---|---|---|
| one suitcase | один чемодан | уан суиткейс | да | I have one suitcase | У меня один чемодан | читается | |
| two bags | две сумки | ту бэгз | — | I have two bags | У меня две сумки | читается | |
| one backpack | один рюкзак | уан бэкпэк | — | I have one backpack | У меня один рюкзак | читается | |

### p4 · вопрос ученика — «Can I take ___?»

На родном: «Можно взять ___?» · чтение: «Кэн ай тэйк ___?» · окно: «что взять с собой» · звучит в обменах: 4

| наполнение | перевод | чтение | в диалоге | собранная фраза | собранный перевод | судья | оценка |
|---|---|---|---|---|---|---|---|
| this backpack | этот рюкзак | зис бэкпэк | да | Can I take this backpack? | Можно взять этот рюкзак? | читается | |
| this small bag | эту маленькую сумку | зис смол бэг | — | Can I take this small bag? | Можно взять эту маленькую сумку? | читается | |

### p5 · вопрос ученика — «Can I have ___?»

На родном: «Можно ___?» · чтение: «Кэн ай хэв ___?» · окно: «какое место» · звучит в обменах: 5

| наполнение | перевод | чтение | в диалоге | собранная фраза | собранный перевод | судья | оценка |
|---|---|---|---|---|---|---|---|
| a window seat | место у окна | э уиндоу сит | да | Can I have a window seat? | Можно место у окна? | читается | |
| an aisle seat | место у прохода | эн айл сит | — | Can I have an aisle seat? | Можно место у прохода? | читается | |
| a front seat | место впереди | э франт сит | — | Can I have a front seat? | Можно место впереди? | читается | |

### p6 · ответ — «My gate is ___»

На родном: «Мой выход — ___» · чтение: «Май гейт из ___» · окно: «номер выхода» · звучит в обменах: 6

| наполнение | перевод | чтение | в диалоге | собранная фраза | собранный перевод | судья | оценка |
|---|---|---|---|---|---|---|---|
| Gate 12 | 12 | гейт твэлв | да | My gate is Gate 12 | Мой выход — 12 | читается | |
| Gate 8 | 8 | гейт эйт | — | My gate is Gate 8 | Мой выход — 8 | читается | |
| Gate 15 | 15 | гейт фифтин | — | My gate is Gate 15 | Мой выход — 15 | читается | |

### p7 · ответ — «My suitcase goes ___»

На родном: «Мой чемодан едет ___» · чтение: «Май суиткейс гоуз ___» · окно: «куда отправят чемодан» · звучит в обменах: 8

| наполнение | перевод | чтение | в диалоге | собранная фраза | собранный перевод | судья | оценка |
|---|---|---|---|---|---|---|---|
| to London | в Лондон | ту Ландан | да | My suitcase goes to London | Мой чемодан едет в Лондон | читается | |
| to Madrid | в Мадрид | ту Мадрид | — | My suitcase goes to Madrid | Мой чемодан едет в Мадрид | читается | |

## Проверки обменов

| # | вопрос | варианты (✓ — верный) | пояснение | оценка |
|---|---|---|---|---|
| 1 | What does the agent ask to see? / Что сотрудница просит показать? | ✓ An ID document / Документ, удостоверяющий личность · A suitcase tag / Багажную бирку · A boarding pass / Посадочный талон | Сотрудница просит показать паспорт, то есть документ, удостоверяющий личность. | |
| 2 | What does the agent want to know? / Что сотрудница хочет узнать? | ✓ The passenger's destination / Пункт назначения пассажира · The passenger's seat number / Номер места пассажира · The passenger's bag weight / Вес багажа пассажира | Сотрудница спрашивает, куда летит пассажир. | |
| 3 | What kind of bag does the agent ask about? / О каком багаже спрашивает сотрудница? | A duty-free bag / Пакет из duty free · A small personal item / Маленькая личная вещь · ✓ A bag for the hold / Багаж, который сдают | Сотрудница спрашивает про багаж, который нужно сдать. | |
| 4 | How can the backpack travel? / Как можно провезти рюкзак? | Only under the seat check / Только на отдельную проверку у места · In oversized baggage / Как негабаритный багаж · ✓ As cabin baggage / Как ручную кладь | Сотрудница говорит, что рюкзак можно взять как ручную кладь. | |
| 5 | Which seat does the agent offer? / Какое место предлагает сотрудница? | 12C / 12C · ✓ 14A / 14A · 16F / 16F | Сотрудница предлагает место 14A. | |
| 6 | When does boarding start? / Во сколько начинается посадка? | At seven o'clock / В семь часов · ✓ At twenty to seven / Без двадцати семь · At half past six / В половине седьмого | 18:40 — это без двадцати семь вечера. | |
| 7 | What gate number does the agent repeat? / Какой номер выхода повторяет сотрудница? | Gate 20 / Выход 20 · Gate 10 / Выход 10 · ✓ Gate 12 / Выход 12 | Сотрудница повторяет, что выход номер 12. | |
| 8 | Where is the suitcase checked through to? / До какого города зарегистрирован чемодан? | To Paris / До Парижа · To Berlin / До Берлина · ✓ To London / До Лондона | Сотрудница говорит, что чемодан зарегистрирован до Лондона. | |

## Слушаю весь визит (listening)

| # | вопрос | варианты (✓ — верный) | пояснение | оценка |
|---|---|---|---|---|
| L1 | Куда летит пассажир? | В Париж · В Рим · ✓ В Лондон | В разговоре пассажир говорит, что летит в Лондон. | |
| L2 | Какой багаж пассажир сдаёт? | Две сумки · ✓ Один чемодан · Один рюкзак | Пассажир говорит, что у него один чемодан для сдачи. | |
| L3 | Какое место получает пассажир? | В хвосте самолёта · У прохода · ✓ У окна | Сотрудница говорит, что место 14A свободно, это место у окна. | |
| L4 | Во сколько посадка? | ✓ 18:40 · 18:20 · 19:00 | Сотрудница сообщает, что посадка начинается в 18:40. | |

## Словарь

| id | слово | вид | перевод | чтение | определение | где звучит | картинка (запрос) | оценка |
|---|---|---|---|---|---|---|---|---|
| v1 | check in | связка | регистрироваться на рейс | чек ин | to arrive at the airport desk and register for your flight | p1 | airport passenger at a check-in counter with a suitcase and passport | |
| v2 | passport | слово | паспорт | паспорт | an official document for travel and identity | A1 | passport in a traveler's hand at an airport counter | |
| v3 | suitcase | слово | чемодан | суиткейс | a large travel bag with a hard or soft case | p3, p7, A8 | closed suitcase standing beside an airport check-in desk | |
| v4 | backpack | слово | рюкзак | бэкпэк | a bag carried on your back with two straps | p4 | small backpack on an airport floor near a passenger | |
| v5 | hand luggage | связка | ручная кладь | хэнд лагидж | bags you take with you into the plane cabin | A4 | small bag placed in an airplane overhead bin | |
| v6 | window seat | связка | место у окна | уиндоу сит | a seat next to the window on a plane | p5 | airplane seat next to a window with clouds outside | |
| v7 | boarding pass | связка | посадочный талон | бординг пас | the document that shows your flight and seat details | A6 | boarding pass in a passenger's hand at the airport | |
| v8 | gate | слово | выход на посадку | гейт | the airport area where passengers go to board the plane | p6, A6, A7 | airport departure gate sign with passengers waiting nearby | |

## Находки (ответ модели без починок; фатальные держат день до P2R)

| код | порог | адрес | что |
|---|---|---|---|
| `exchange.second_question` | **фатально** | x1 | the closing message of A «Sure. May I see your passport?» ends with a question mark |
| `line.ne_frame` | **фатально** | B1 | «I'm checking in for this flight.» is not «I'm checking in ___» with «for this flight»; served as «I'm checking in for this flight» |
| `line.ne_frame` | **фатально** | B2 | «I'm flying to London.» is not «I'm flying ___» with «to London»; served as «I'm flying to London» |
| `key.contains_filler` | предупреждение | B2 | the key «flying to» takes words of the filler «to London» |
| `line.ne_frame` | **фатально** | B3 | «I have one suitcase.» is not «I have ___» with «one suitcase»; served as «I have one suitcase» |
| `key.no_content_word` | предупреждение | B5 | «Can I have ___?» has no content word outside the slot: the key is «Can I have», the frame up to the slot, not «have a» |
| `key.contains_filler` | предупреждение | B5 | the key «have a» takes words of the filler «a window seat» |
| `line.ne_frame` | **фатально** | B6 | «My gate is Gate 12.» is not «My gate is ___» with «Gate 12»; served as «My gate is Gate 12» |
| `line.ne_frame` | **фатально** | B8 | «My suitcase goes to London.» is not «My suitcase goes ___» with «to London»; served as «My suitcase goes to London» |
| `vocab.used_in_wrong` | предупреждение | v1 | «check in» is not in frame p1 or its fillers |

## Не проверено — у языка нет пакета (`lang.pack_missing`, не находка)

Всё проверено: пакеты обоих языков пары полные.

## Порог (фатальные коды → P2R, не больше двух карточек)

- **Живая сборка** (валидатор до двух уточнений отчёта §3, P2R на `gpt-5.4-mini`): P2R x1 (exchange, $0.005973, 2770 мс: exchange.second_question, line.ne_frame); B1 (line, $0.003389, 1268 мс: line.ne_frame); итог: failed — fatal: line.ne_frame.
- **Порог на валидаторе сдачи, P2R на `gpt-5.4`**: failed — fatal: line.ne_frame · карточки x1, B1 · $0.031385 · 5854 мс.
- **Порог на валидаторе сдачи, P2R на `gpt-5.4-mini`**: failed — fatal: exchange.second_question, line.ne_frame · карточки x1, B1 · $0.009385 · 4913 мс.
