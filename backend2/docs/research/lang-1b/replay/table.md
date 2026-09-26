# LANG-1b §1 · переигровка ворот на 53 сохранённых днях: было (код main) / стало (код ветки)

Моделей не звали: сырой ответ урока каждого дня прочитан валидатором стороны, ворота переиграны её `LessonGateKeeper`,
P2R ответила ЗАПИСАННАЯ починка той же карточки. «без записи» — карточка, которую запись не чинила (переигровка вернула
её как была — пессимистично). Инструменты — `tools/replay-gates.php` (кодом main и кодом ветки), `tools/compare-replay.py`.


## ru→en (baseline LANG-1)

| день | пара | промт | было: фат. карт. | было: исход | было: фатальные | было: form (подпункты) | стало: фат. карт. | стало: исход | стало: фатальные | стало: partner_fragment | без записи |
|---|---|---|---|---|---|---|---|---|---|---|---|
| gen-3/doctor-day1 | ru-en | lesson_day.v4.6 | 2 | failed | 2nd_q, ne_frame | — | 2 | failed | 2nd_q, ne_frame | 0 | — |
| gen-3/doctor-day2-v4.5 | ru-en | lesson_day.v4.5 | 3 | failed | form, known_word, known_frame | piece | 2 | failed | known_word, known_frame | 1 | p2, v2 |
| gen-3/doctor-day2-v4.6 | ru-en | lesson_day.v4.6 | 1 | ready | known_frame | — | 1 | ready | known_frame | 0 | — |
| gen-3/bank-day1 | ru-en | lesson_day.v4.6 | 3 | failed | form×3 | piece×2, length | 1 | failed | form | 3 | x4.check |
| gen-3/bank-day2-v4.5 | ru-en | lesson_day.v4.5 | 3 | failed | form×3 | piece×2, length | 1 | failed | form | 1 | x4.check |
| gen-3/bank-day2-v4.6 | ru-en | lesson_day.v4.6 | 2 | failed | form×2 | piece×2 | 1 | failed | form | 1 | x2.check |
| gen-3/airport-day1 | ru-en | lesson_day.v4.6 | 7 | failed | form×7 | piece×5, length×2 | 2 | failed | form×2 | 5 | x1.check, x3.check |
| gen-3/airport-day2-v4.5 | ru-en | lesson_day.v4.5 | 6 | failed | form×5, known_word | piece×4, length | 2 | failed | form, known_word | 2 | x5.check, v4 |
| gen-3/airport-day2-v4.6 | ru-en | lesson_day.v4.6 | 3 | failed | form×3 | piece×3 | 0 | ready | — | 1 | — |
| gen-3/restaurant-day1 | ru-en | lesson_day.v4.6 | 4 | failed | form×4 | length×2, piece×2 | 2 | failed | form×2 | 2 | x1.check, x8.check |
| gen-3/restaurant-day2-v4.5 | ru-en | lesson_day.v4.5 | 2 | failed | form×2 | piece×2 | 0 | ready | — | 0 | — |
| gen-3/restaurant-day2-v4.6 | ru-en | lesson_day.v4.6 | 4 | failed | form×3, known_frame | piece×2, length | 2 | failed | form, known_frame | 1 | x5.check |
| gen-3/rent-day1 | ru-en | lesson_day.v4.6 | 3 | failed | form×3 | piece×3 | 0 | ready | — | 2 | — |
| gen-3/rent-day2-v4.5 | ru-en | lesson_day.v4.5 | 4 | failed | form×3, known_word | piece×2, length | 2 | failed | form, known_word | 2 | x2.check, v2 |
| gen-3/rent-day2-v4.6 | ru-en | lesson_day.v4.6 | 1 | failed | form | piece | 0 | ready | — | 1 | — |
| gen-3/interview-day1 | ru-en | lesson_day.v4.6 | 2 | failed | form×2 | piece×2 | 0 | ready | — | 2 | — |
| gen-3/interview-day2-v4.5 | ru-en | lesson_day.v4.5 | 1 | failed | form | piece | 0 | ready | — | 0 | — |
| gen-3/interview-day2-v4.6 | ru-en | lesson_day.v4.6 | 2 | failed | script, form | length | 2 | failed | script, form | 0 | p1, x2.check |
| gen-2b/interview | ru-en | lesson_day.v4.5 | 2 | failed | script, form | length | 2 | failed | script, form | 0 | p4, x4.check |
| gen-2b/rent | ru-en | lesson_day.v4.5 | 3 | failed | form×3 | piece×2, length | 1 | failed | form | 1 | x3.check |
| gen-2b/bank | ru-en | lesson_day.v4.5 | 3 | failed | form×2, 2nd_q | piece, length | 3 | failed | form×2, 2nd_q | 1 | x1.check |
| gen-2b/restaurant | ru-en | lesson_day.v4.5 | 5 | failed | form×5 | length×3, piece×2 | 3 | failed | form×3 | 2 | x1.check, x6.check |
| gen-2b/airport | ru-en | lesson_day.v4.5 | 6 | failed | form×5, 2nd_q | piece×4, length | 3 | failed | form×2, 2nd_q | 1 | x1.check |
| gen-2b/doctor | ru-en | lesson_day.v4.5 | 3 | failed | filler×3, ne_frame, form | length | 3 | failed | filler×3, ne_frame, form | 0 | x4.check |
| check-1/vet-day1-attempt2 | ru-en | lesson_day.v4.7 | 2 | failed | form×2 | length, piece | 1 | failed | form | 1 | x5.check |
| gen-2b/cases/den-interview-v4.4 | ru-en | lesson_day.v4.4 | 8 | failed | script×5, filler×2, 2nd_q, form | length | 8 | failed | script×5, filler×2, 2nd_q, form | 0 | p1, p3 |

## разведка LANG-1

| день | пара | промт | было: фат. карт. | было: исход | было: фатальные | было: form (подпункты) | стало: фат. карт. | стало: исход | стало: фатальные | стало: partner_fragment | без записи |
|---|---|---|---|---|---|---|---|---|---|---|---|
| lang-1/be-en | be-en | lesson_day.v4.7 | 2 | ready | 2nd_q, form | length | 2 | ready | 2nd_q, form | 0 | — |
| lang-1/de-en | de-en | lesson_day.v4.7 | 26 | failed | script×42, 2nd_q, form | piece | 25 | failed | script×42, 2nd_q | 0 | p1, p2 |
| lang-1/es-en | es-en | lesson_day.v4.7 | 25 | failed | script×41, 2nd_q | — | 25 | failed | script×41, 2nd_q | 0 | p1, p2 |
| lang-1/fr-en | fr-en | lesson_day.v4.7 | 27 | failed | script×40, form×2, 2nd_q | length, piece | 27 | failed | script×40, form×2, 2nd_q | 0 | p1, p2 |
| lang-1/it-en | it-en | lesson_day.v4.7 | 1 | ready | 2nd_q | — | 1 | ready | 2nd_q | 0 | — |
| lang-1/pl-en | pl-en | lesson_day.v4.7 | 3 | failed | 2nd_q, script, form | piece | 3 | failed | 2nd_q, script, form | 1 | B5 |
| lang-1/ro-en | ro-en | lesson_day.v4.7 | 4 | failed | filler×2, 2nd_q, ne_frame, form | length | 4 | failed | filler×2, 2nd_q, ne_frame, form | 0 | — |
| lang-1/ru-de | ru-de | lesson_day.v4.7 | 4 | failed | form×3, 2nd_q | piece×2, length | 2 | failed | 2nd_q, form | 1 | x1 |
| lang-1/ru-es | ru-es | lesson_day.v4.7 | 3 | failed | form×2, 2nd_q | piece×2 | 1 | failed | 2nd_q | 1 | x1 |
| lang-1/ru-fr | ru-fr | lesson_day.v4.7 | 3 | failed | form×2, 2nd_q | piece×2 | 1 | failed | 2nd_q | 1 | x1 |
| lang-1/ru-it | ru-it | lesson_day.v4.7 | 5 | failed | filler×2, form×2, 2nd_q, ne_frame | length, piece | 4 | failed | filler×2, 2nd_q, ne_frame, form | 1 | x1 |
| lang-1/ru-pl | ru-pl | lesson_day.v4.7 | 3 | failed | form×2, 2nd_q | piece×2 | 1 | failed | 2nd_q | 1 | x1 |
| lang-1/ru-ro | ru-ro | lesson_day.v4.7 | 4 | failed | filler×2, 2nd_q, ne_frame, form | piece | 3 | failed | filler×2, 2nd_q, ne_frame | 0 | x1 |
| lang-1/uk-en | uk-en | lesson_day.v4.7 | 3 | failed | form×2, 2nd_q | piece, length | 2 | failed | 2nd_q, form | 0 | x8.check |

## часть D LANG-1 (живые сборки)

| день | пара | промт | было: фат. карт. | было: исход | было: фатальные | было: form (подпункты) | стало: фат. карт. | стало: исход | стало: фатальные | стало: partner_fragment | без записи |
|---|---|---|---|---|---|---|---|---|---|---|---|
| lang-1-live/ru-de-1 | ru-de | lesson_day.v4.8 | 4 | failed | form×3, 2nd_q | piece×3 | 1 | ready | 2nd_q | 1 | — |
| lang-1-live/ru-de-2 | ru-de | lesson_day.v4.8 | 3 | failed | form×2, 2nd_q | piece×2 | 1 | ready | 2nd_q | 0 | — |
| lang-1-live/ru-de-3 | ru-de | lesson_day.v4.8 | 3 | failed | form×2, 2nd_q | length, piece | 2 | ready | 2nd_q, form | 0 | — |
| lang-1-live/ru-de-4 | ru-de | lesson_day.v4.8 | 3 | failed | form×2, 2nd_q | piece×2 | 1 | ready | 2nd_q | 1 | — |
| lang-1-live/pl-en-1 | pl-en | lesson_day.v4.8 | 4 | failed | form×2, 2nd_q, script | piece, length | 3 | failed | 2nd_q, script, form | 1 | — |
| lang-1-live/be-en-1 | be-en | lesson_day.v4.8 | 1 | ready | form | length | 1 | ready | form | 0 | — |
| lang-1-live/pl-en-2 | pl-en | lesson_day.v4.8 | 7 | failed | form×3, 2nd_q×2, script×2 | piece×2, length | 5 | failed | 2nd_q×2, script×2, form | 2 | — |
| lang-1-live/ru-de-5 | ru-de | lesson_day.v4.8 | 1 | ready | 2nd_q | — | 1 | ready | 2nd_q | 0 | — |
| lang-1-live/pl-en-3 | pl-en | lesson_day.v4.8 | 3 | failed | 2nd_q, script, form | piece | 2 | ready | 2nd_q, script | 1 | — |
| lang-1-live/pl-en-4 | pl-en | lesson_day.v4.8 | 4 | failed | form×2, 2nd_q, script | piece, length | 3 | failed | 2nd_q, script, form | 1 | — |
| lang-1-live/pl-en-5 | pl-en | lesson_day.v4.8 | 9 | failed | script×9, form×2, 2nd_q | piece×2 | 7 | failed | script×9, 2nd_q | 0 | — |
| lang-1-live/pl-en-6 | pl-en | lesson_day.v4.8 | 3 | failed | 2nd_q, script, form | piece | 2 | ready | 2nd_q, script | 0 | — |
| lang-1-live/pl-en-7 | pl-en | lesson_day.v4.8 | 7 | failed | script×2, filler×2, form×2, 2nd_q, ne_frame | length, piece | 6 | failed | script×2, filler×2, 2nd_q, ne_frame, form | 1 | — |

## Итоги

| группа | дней | было: карточек ≥ 3 | стало: карточек ≥ 3 | было: переигровка failed | стало: переигровка failed | стало: точно (все починки в записи) — failed | стало с автопересборкой, оценка p² по «карточек ≥ 3» |
|---|---|---|---|---|---|---|---|
| ru→en (baseline LANG-1) | 26 | 16/26 (62 %) | 5/26 (19 %) | 25/26 (96 %) | 19/26 (73 %) | 1 из 8 | ≈ 4 % |
| разведка LANG-1 | 14 | 12/14 (86 %) | 7/14 (50 %) | 12/14 (86 %) | 12/14 (86 %) | 1 из 3 | ≈ 25 % |
| часть D LANG-1 (живые сборки) | 13 | 11/13 (85 %) | 5/13 (38 %) | 11/13 (85 %) | 5/13 (38 %) | 5 из 13 | ≈ 15 % |
| все 53 | 53 | 39/53 (74 %) | 17/53 (32 %) | 48/53 (91 %) | 36/53 (68 %) | 7 из 24 | ≈ 10 % |

`options.form_mismatch` (фатальный) на сырых ответах: было 107 находок (из них «кусок» 75), стало 37 («кусок» — 0: подпункта больше нет). Предупреждение `options.partner_fragment` стало: 44 находок.
Подпункты form_mismatch — было: {'piece': 75, 'length': 32}; стало: {'length': 37}.

| фатальный код | было (находок на сырых ответах) | стало |
|---|---|---|
| `pronunciation.foreign_script` | 148 | 148 |
| `options.form_mismatch` | 107 | 37 |
| `exchange.second_question` | 31 | 31 |
| `filler.ungrammatical` | 13 | 13 |
| `line.ne_frame` | 6 | 6 |
| `frame.known_repeat` | 3 | 3 |
| `vocab.known_repeat` | 3 | 3 |

## Часть D: цепочки сборок с автопересборкой (сборка n упала → пересборка = записанная сборка n+1)

| пара | сборки по порядку: было | стало (одна сборка) | стало с автопересборкой: день — нажатий «ещё раз» до ready |
|---|---|---|---|
| ru-de | ✗ ✗ ✗ ✗ ✓ | ✓ ✓ ✓ ✓ ✓ | ready со сборки 1; нажатий «ещё раз»: 0 |
| pl-en | ✗ ✗ ✗ ✗ ✗ ✗ ✗ | ✗ ✗ ✓ ✗ ✗ ✓ ✗ | ready со сборки 3; нажатий «ещё раз»: 1 |
| be-en | ✓ | ✓ | ready со сборки 1; нажатий «ещё раз»: 0 |

## Все находки `options.partner_fragment` (стало) — на проверку «ложных»

| день | адрес | находка |
|---|---|---|
| gen-3/doctor-day2-v4.5 | x7.check | the option «Страховую карту» is a piece of the partner's line «Пожалуйста, его документ и вашу страховую карту.» |
| gen-3/bank-day1 | x4.check | the option «Паспорт» is a piece of the partner's line «Пожалуйста, покажите мне ваш паспорт.» |
| gen-3/bank-day1 | x5.check | the option «Виза и подтверждение адреса» is a piece of the partner's line «Мне также нужны ваша виза и подтверждение адреса.» |
| gen-3/bank-day1 | x7.check | the option «Дебетовая карта» is a piece of the partner's line «Да. К этому счёту идёт дебетовая карта.» |
| gen-3/bank-day2-v4.5 | x5.check | the option «Номер карты.» is a piece of the partner's line «Откройте приложение, введите номер карты и подтвердите код, который мы отправим.» |
| gen-3/bank-day2-v4.6 | x2.check | the option «На ваш адрес» is a piece of the partner's line «Да, мы отправляем её по почте на ваш адрес.» |
| gen-3/airport-day1 | x1.check | the option «Паспорт» is a piece of the partner's line «Доброе утро. Можно ваш паспорт, пожалуйста?» |
| gen-3/airport-day1 | x2.check | the option «Подтверждение бронирования» is a piece of the partner's line «Спасибо. У вас ещё есть подтверждение бронирования?» |
| gen-3/airport-day1 | x3.check | the option «Сумок» is a piece of the partner's line «Сколько у вас сегодня сумок?» |
| gen-3/airport-day1 | x4.check | the option «Рюкзак» is a piece of the partner's line «Конечно. Рюкзак может остаться у вас.» |
| gen-3/airport-day1 | x8.check | the option «Только места у окна» is a piece of the partner's line «Извините, сейчас доступны только места у окна.» |
| gen-3/airport-day2-v4.5 | x4.check | the option «Одежду и книги» is a piece of the partner's line «Одежду и книги можно взять в салон.» |
| gen-3/airport-day2-v4.5 | x8.check | the option «Надеть её или нести» is a piece of the partner's line «Да, вы можете надеть её или нести в руках.» |
| gen-3/airport-day2-v4.6 | x6.check | the option «Ноутбук и зарядку.» is a piece of the partner's line «Оставьте ноутбук и зарядку в рюкзаке.» |
| gen-3/restaurant-day1 | x3.check | the option «Томатный соус, сыр и базилик» is a piece of the partner's line «В ней томатный соус, сыр и базилик.» |
| gen-3/restaurant-day1 | x6.check | the option «С картофелем и овощами» is a piece of the partner's line «Тогда рыба безопаснее. Она подаётся с картофелем и овощами.» |
| gen-3/restaurant-day2-v4.6 | x4.check | the option «За столом.» is a piece of the partner's line «Да, наличные тоже принимаем. Можете оплатить за столом.» |
| gen-3/rent-day1 | x3.check | the option «Электричество и интернет» is a piece of the partner's line «Вода и отопление включены. Электричество и интернет оплачиваются отдельно.» |
| gen-3/rent-day1 | x8.check | the option «Банковским переводом» is a piece of the partner's line «Да, и аренда, и депозит оплачиваются банковским переводом.» |
| gen-3/rent-day2-v4.5 | x3.check | the option «Электричество и интернет» is a piece of the partner's line «Вода и отопление включены, а электричество и интернет оплачиваются отдельно.» |
| gen-3/rent-day2-v4.5 | x4.check | the option «Электричество и интернет» is a piece of the partner's line «Отопление и вода включены. Электричество и интернет отдельно.» |
| gen-3/rent-day2-v4.6 | x5.check | the option «Электричество и интернет» is a piece of the partner's line «Да, электричество и интернет оплачиваются отдельно, и вы сами всё оформляете.» |
| gen-3/interview-day1 | x6.check | the option «С продуктовой и инженерной командами» is a piece of the partner's line «Эта роль включает руководство поставкой и координацию с продуктовой и инженерной командами.» |
| gen-3/interview-day1 | x7.check | the option «С нанимающим менеджером» is a piece of the partner's line «Дальше вы встретитесь с нанимающим менеджером, а потом будет интервью с командой.» |
| gen-2b/rent | x4.check | the option «Электричество и интернет» is a piece of the partner's line «Вода и отопление включены, но электричество и интернет оплачиваются отдельно.» |
| gen-2b/bank | x1.check | the option «Паспорт» is a piece of the partner's line «Конечно. Можно сначала ваш паспорт?» |
| gen-2b/restaurant | x2.check | the option «Грибы» is a piece of the partner's line «В ней курица, сливочный соус и грибы.» |
| gen-2b/restaurant | x7.check | the option «Без орехов» is a piece of the partner's line «Вашей дочке можно рыбу на гриле. Мы готовим её без орехов.» |
| gen-2b/airport | x4.check | the option «Как ручную кладь» is a piece of the partner's line «Да, это можно взять как ручную кладь.» |
| check-1/vet-day1-attempt2 | x7.check | the option «Документ и карту прививок» is a piece of the partner's line «Пожалуйста, принесите ваш документ и карту прививок собаки.» |
| lang-1/pl-en | x7.check | the option «Obok apteki» is a piece of the partner's line «Tak. To Green Street 12, obok apteki.» |
| lang-1/ru-de | x7.check | the option «Страховую карту» is a piece of the partner's line «Пожалуйста, возьмите с собой страховую карту.» |
| lang-1/ru-es | x6.check | the option «Номер телефона» is a piece of the partner's line «Спасибо. Какой у вас номер телефона?» |
| lang-1/ru-fr | x6.check | the option «Ваша дата рождения» is a piece of the partner's line «Ваше имя и ваша дата рождения.» |
| lang-1/ru-it | x8.check | the option «Медицинскую карту» is a piece of the partner's line «Возьмите медицинскую карту и документ.» |
| lang-1/ru-pl | x6.check | the option «Документ и страховую карту» is a piece of the partner's line «Пожалуйста, принесите документ и страховую карту.» |
| lang-1-live/ru-de-1 | x3.check | the option «Температура» is a piece of the partner's line «У вас ещё и температура?» |
| lang-1-live/ru-de-4 | x8.check | the option «Номер телефона» is a piece of the partner's line «Да. Мне нужны, пожалуйста, ваше имя и ваш номер телефона.» |
| lang-1-live/pl-en-1 | x7.check | the option «Numer telefonu» is a piece of the partner's line «A numer telefonu?» |
| lang-1-live/pl-en-2 | x6.check | the option «Imię i nazwisko» is a piece of the partner's line «Dobrze. Poproszę imię i nazwisko.» |
| lang-1-live/pl-en-2 | x7.check | the option «Imię i nazwisko» is a piece of the partner's line «Poproszę imię i nazwisko.» |
| lang-1-live/pl-en-3 | x3.check | the option «Jak długo to trwa» is a piece of the partner's line «Jak długo to trwa?» |
| lang-1-live/pl-en-4 | x3.check | the option «Jak długo to trwa» is a piece of the partner's line «Jak długo to trwa?» |
| lang-1-live/pl-en-7 | x7.check | the option «Imię i nazwisko» is a piece of the partner's line «Proszę podać imię i nazwisko.» |

## «Кусок» было — что из него ушло в исключения (число, время, имя, сосчитанное) и что стало предупреждением

| день | адрес | было (фатально) | стало |
|---|---|---|---|
| gen-3/doctor-day2-v4.5 | x7.check | the option «Страховую карту» is a piece of the partner's line «Пожалуйста, его документ и вашу страховую карту.» | предупреждение `options.partner_fragment` |
| gen-3/bank-day1 | x5.check | the option «Виза и подтверждение адреса» is a piece of the partner's line «Мне также нужны ваша виза и подтверждение адреса.» | предупреждение `options.partner_fragment` |
| gen-3/bank-day1 | x7.check | the option «Дебетовая карта» is a piece of the partner's line «Да. К этому счёту идёт дебетовая карта.» | предупреждение `options.partner_fragment` |
| gen-3/bank-day2-v4.5 | x5.check | the option «Номер карты.» is a piece of the partner's line «Откройте приложение, введите номер карты и подтвердите код, который мы отправим.» | предупреждение `options.partner_fragment` |
| gen-3/bank-day2-v4.5 | x7.check | the option «Триста евро.» is a piece of the partner's line «Да, в любом банкомате банка. Дневной лимит — триста евро.» | не кусок (исключение) |
| gen-3/bank-day2-v4.6 | x2.check | the option «На ваш адрес» is a piece of the partner's line «Да, мы отправляем её по почте на ваш адрес.» | предупреждение `options.partner_fragment` |
| gen-3/bank-day2-v4.6 | x8.check | the option «Триста евро» is a piece of the partner's line «Дневной лимит снятия наличных — триста евро.» | не кусок (исключение) |
| gen-3/airport-day1 | x2.check | the option «Подтверждение бронирования» is a piece of the partner's line «Спасибо. У вас ещё есть подтверждение бронирования?» | предупреждение `options.partner_fragment` |
| gen-3/airport-day1 | x4.check | the option «Рюкзак» is a piece of the partner's line «Конечно. Рюкзак может остаться у вас.» | предупреждение `options.partner_fragment` |
| gen-3/airport-day1 | x5.check | the option «14A» is a piece of the partner's line «Да, место 14A у окна.» | не кусок (исключение) |
| gen-3/airport-day1 | x7.check | the option «Выход 22» is a piece of the partner's line «Выход 22. Посадка начинается в 10:15.» | не кусок (исключение) |
| gen-3/airport-day1 | x8.check | the option «Только места у окна» is a piece of the partner's line «Извините, сейчас доступны только места у окна.» | предупреждение `options.partner_fragment` |
| gen-3/airport-day2-v4.5 | x1.check | the option «На три килограмма» is a piece of the partner's line «Ваш чемодан на три килограмма тяжелее нормы.» | не кусок (исключение) |
| gen-3/airport-day2-v4.5 | x2.check | the option «Сорок долларов» is a piece of the partner's line «Это сорок долларов за лишний вес.» | не кусок (исключение) |
| gen-3/airport-day2-v4.5 | x4.check | the option «Одежду и книги» is a piece of the partner's line «Одежду и книги можно взять в салон.» | предупреждение `options.partner_fragment` |
| gen-3/airport-day2-v4.5 | x8.check | the option «Надеть её или нести» is a piece of the partner's line «Да, вы можете надеть её или нести в руках.» | предупреждение `options.partner_fragment` |
| gen-3/airport-day2-v4.6 | x1.check | the option «На три килограмма.» is a piece of the partner's line «Ваш чемодан на три килограмма тяжелее нормы.» | не кусок (исключение) |
| gen-3/airport-day2-v4.6 | x2.check | the option «Тридцать долларов.» is a piece of the partner's line «Это тридцать долларов за перевес до пяти килограммов.» | не кусок (исключение) |
| gen-3/airport-day2-v4.6 | x6.check | the option «Ноутбук и зарядку.» is a piece of the partner's line «Оставьте ноутбук и зарядку в рюкзаке.» | предупреждение `options.partner_fragment` |
| gen-3/restaurant-day1 | x3.check | the option «Томатный соус, сыр и базилик» is a piece of the partner's line «В ней томатный соус, сыр и базилик.» | предупреждение `options.partner_fragment` |
| gen-3/restaurant-day1 | x6.check | the option «С картофелем и овощами» is a piece of the partner's line «Тогда рыба безопаснее. Она подаётся с картофелем и овощами.» | предупреждение `options.partner_fragment` |
| gen-3/restaurant-day2-v4.5 | x2.check | the option «Сорок восемь фунтов» is a piece of the partner's line «Вот, пожалуйста. Итого сорок восемь фунтов.» | не кусок (исключение) |
| gen-3/restaurant-day2-v4.5 | x7.check | the option «Сейчас» is a piece of the partner's line «Вы хотите оплатить сейчас?» | не кусок (исключение) |
| gen-3/restaurant-day2-v4.6 | x2.check | the option «Сорок восемь фунтов.» is a piece of the partner's line «Вот ваш счёт. Итого сорок восемь фунтов.» | не кусок (исключение) |
| gen-3/restaurant-day2-v4.6 | x4.check | the option «За столом.» is a piece of the partner's line «Да, наличные тоже принимаем. Можете оплатить за столом.» | предупреждение `options.partner_fragment` |
| gen-3/rent-day1 | x1.check | the option «1200 евро» is a piece of the partner's line «Это гостиная. Аренда — 1200 евро в месяц.» | не кусок (исключение) |
| gen-3/rent-day1 | x3.check | the option «Электричество и интернет» is a piece of the partner's line «Вода и отопление включены. Электричество и интернет оплачиваются отдельно.» | предупреждение `options.partner_fragment` |
| gen-3/rent-day1 | x8.check | the option «Банковским переводом» is a piece of the partner's line «Да, и аренда, и депозит оплачиваются банковским переводом.» | предупреждение `options.partner_fragment` |
| gen-3/rent-day2-v4.5 | x3.check | the option «Электричество и интернет» is a piece of the partner's line «Вода и отопление включены, а электричество и интернет оплачиваются отдельно.» | предупреждение `options.partner_fragment` |
| gen-3/rent-day2-v4.5 | x4.check | the option «Электричество и интернет» is a piece of the partner's line «Отопление и вода включены. Электричество и интернет отдельно.» | предупреждение `options.partner_fragment` |
| gen-3/rent-day2-v4.6 | x5.check | the option «Электричество и интернет» is a piece of the partner's line «Да, электричество и интернет оплачиваются отдельно, и вы сами всё оформляете.» | предупреждение `options.partner_fragment` |
| gen-3/interview-day1 | x6.check | the option «С продуктовой и инженерной командами» is a piece of the partner's line «Эта роль включает руководство поставкой и координацию с продуктовой и инженерной командами.» | предупреждение `options.partner_fragment` |
| gen-3/interview-day1 | x7.check | the option «С нанимающим менеджером» is a piece of the partner's line «Дальше вы встретитесь с нанимающим менеджером, а потом будет интервью с командой.» | предупреждение `options.partner_fragment` |
| gen-3/interview-day2-v4.5 | x6.check | the option «Каждую пятницу» is a piece of the partner's line «Мы планируем двухнедельными спринтами и проверяем прогресс каждую пятницу.» | не кусок (исключение) |
| gen-2b/rent | x2.check | the option «Аренда за два месяца» is a piece of the partner's line «Депозит — это аренда за два месяца.» | не кусок (исключение) |
| gen-2b/rent | x4.check | the option «Электричество и интернет» is a piece of the partner's line «Вода и отопление включены, но электричество и интернет оплачиваются отдельно.» | предупреждение `options.partner_fragment` |
| gen-2b/bank | x1.check | the option «Паспорт» is a piece of the partner's line «Конечно. Можно сначала ваш паспорт?» | предупреждение `options.partner_fragment` |
| gen-2b/restaurant | x2.check | the option «Грибы» is a piece of the partner's line «В ней курица, сливочный соус и грибы.» | предупреждение `options.partner_fragment` |
| gen-2b/restaurant | x8.check | the option «Сейчас» is a piece of the partner's line «Конечно. Сейчас принесу.» | не кусок (исключение) |
| gen-2b/airport | x4.check | the option «Как ручную кладь» is a piece of the partner's line «Да, это можно взять как ручную кладь.» | предупреждение `options.partner_fragment` |
| gen-2b/airport | x5.check | the option «14A» is a piece of the partner's line «Да, место 14A свободно.» | не кусок (исключение) |
| gen-2b/airport | x7.check | the option «Выход 12» is a piece of the partner's line «Выход 12. Посадка в 18:40.» | не кусок (исключение) |
| gen-2b/airport | x8.check | the option «До Лондона» is a piece of the partner's line «Ваш чемодан зарегистрирован до Лондона.» | не кусок (исключение) |
| check-1/vet-day1-attempt2 | x7.check | the option «Документ и карту прививок» is a piece of the partner's line «Пожалуйста, принесите ваш документ и карту прививок собаки.» | предупреждение `options.partner_fragment` |
| lang-1/de-en | x8.check | the option «King Street 14» is a piece of the partner's line «Ja, das ist King Street 14.» | не кусок (исключение) |
| lang-1/fr-en | x8.check | the option «Avec le Dr Lee» is a piece of the partner's line «Vous êtes inscrit pour 15 h aujourd'hui avec le Dr Lee.» | не кусок (исключение) |
| lang-1/pl-en | x7.check | the option «Obok apteki» is a piece of the partner's line «Tak. To Green Street 12, obok apteki.» | предупреждение `options.partner_fragment` |
| lang-1/ru-de | x5.check | the option «В четыре часа» is a piece of the partner's line «Да, в четыре часа ещё есть свободный приём.» | не кусок (исключение) |
| lang-1/ru-de | x8.check | the option «На Банхофштрассе, 12» is a piece of the partner's line «Да, клиника находится на Банхофштрассе, 12.» | не кусок (исключение) |
| lang-1/ru-es | x6.check | the option «Номер телефона» is a piece of the partner's line «Спасибо. Какой у вас номер телефона?» | предупреждение `options.partner_fragment` |
| lang-1/ru-es | x7.check | the option «Сегодня в четыре часа дня» is a piece of the partner's line «Да, сегодня в четыре часа дня.» | не кусок (исключение) |
| lang-1/ru-fr | x4.check | the option «Завтра в десять» is a piece of the partner's line «У нас есть место завтра в десять часов.» | не кусок (исключение) |
| lang-1/ru-fr | x6.check | the option «Ваша дата рождения» is a piece of the partner's line «Ваше имя и ваша дата рождения.» | предупреждение `options.partner_fragment` |
| lang-1/ru-it | x8.check | the option «Медицинскую карту» is a piece of the partner's line «Возьмите медицинскую карту и документ.» | предупреждение `options.partner_fragment` |
| lang-1/ru-pl | x5.check | the option «Завтра в пятнадцать» is a piece of the partner's line «Да, у нас есть завтра в пятнадцать.» | не кусок (исключение) |
| lang-1/ru-pl | x6.check | the option «Документ и страховую карту» is a piece of the partner's line «Пожалуйста, принесите документ и страховую карту.» | предупреждение `options.partner_fragment` |
| lang-1/ru-ro | x8.check | the option «Двести леев» is a piece of the partner's line «Консультация стоит двести леев.» | не кусок (исключение) |
| lang-1/uk-en | x4.check | the option «Грін-стріт, 18» is a piece of the partner's line «Так. Це Грін-стріт, 18.» | не кусок (исключение) |
| lang-1-live/ru-de-1 | x3.check | the option «Температура» is a piece of the partner's line «У вас ещё и температура?» | предупреждение `options.partner_fragment` |
| lang-1-live/ru-de-1 | x6.check | the option «Завтра» is a piece of the partner's line «Завтра в десять или в одиннадцать.» | не кусок (исключение) |
| lang-1-live/ru-de-1 | x8.check | the option «На десять минут раньше» is a piece of the partner's line «Пожалуйста, придите завтра на десять минут раньше.» | не кусок (исключение) |
| lang-1-live/ru-de-2 | x7.check | the option «Четыре часа» is a piece of the partner's line «Да, в четыре часа ещё есть свободная запись.» | не кусок (исключение) |
| lang-1-live/ru-de-2 | x8.check | the option «Завтра в четыре» is a piece of the partner's line «Хорошо. Ваша запись завтра в четыре часа.» | не кусок (исключение) |
| lang-1-live/ru-de-3 | x8.check | the option «Завтра в одиннадцать» is a piece of the partner's line «Хорошо, запись на завтра в одиннадцать.» | не кусок (исключение) |
| lang-1-live/ru-de-4 | x5.check | the option «Завтра в десять или в одиннадцать» is a piece of the partner's line «Завтра в десять или в одиннадцать можно.» | не кусок (исключение) |
| lang-1-live/ru-de-4 | x8.check | the option «Номер телефона» is a piece of the partner's line «Да. Мне нужны, пожалуйста, ваше имя и ваш номер телефона.» | предупреждение `options.partner_fragment` |
| lang-1-live/pl-en-1 | x7.check | the option «Numer telefonu» is a piece of the partner's line «A numer telefonu?» | предупреждение `options.partner_fragment` |
| lang-1-live/pl-en-2 | x6.check | the option «Imię i nazwisko» is a piece of the partner's line «Dobrze. Poproszę imię i nazwisko.» | предупреждение `options.partner_fragment` |
| lang-1-live/pl-en-2 | x7.check | the option «Imię i nazwisko» is a piece of the partner's line «Poproszę imię i nazwisko.» | предупреждение `options.partner_fragment` |
| lang-1-live/pl-en-3 | x3.check | the option «Jak długo to trwa» is a piece of the partner's line «Jak długo to trwa?» | предупреждение `options.partner_fragment` |
| lang-1-live/pl-en-4 | x3.check | the option «Jak długo to trwa» is a piece of the partner's line «Jak długo to trwa?» | предупреждение `options.partner_fragment` |
| lang-1-live/pl-en-5 | x5.check | the option «Jutro o jedenastej» is a piece of the partner's line «Tak, jutro o jedenastej też jest wolne.» | не кусок (исключение) |
| lang-1-live/pl-en-5 | x6.check | the option «Jutro o jedenastej» is a piece of the partner's line «Tak, jutro o jedenastej też jest wolne.» | не кусок (исключение) |
| lang-1-live/pl-en-6 | x5.check | the option «Jutro o dziesiątej trzydzieści» is a piece of the partner's line «Tak, mamy jutro o dziesiątej trzydzieści.» | не кусок (исключение) |
| lang-1-live/pl-en-7 | x7.check | the option «Imię i nazwisko» is a piece of the partner's line «Proszę podać imię i nazwisko.» | предупреждение `options.partner_fragment` |
