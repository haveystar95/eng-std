# LANG-1 · Русский→Polski (ru→pl, начальный)

Цель плана (слова ученика): «Записываюсь к врачу: третий день болит горло и температура. Нужно выбрать удобное время и объяснить, что меня беспокоит»

Роль ученика в плане: Pacjent / Пациент. Сцена 1: «Запись к врачу» (Recepcjonistka / Администратор); сцена 2: «Приём у врача» (Lekarz / Врач).

> День 1 — урок, каким его получил бы ученик (прошедший порог, как записан в сцену; при `failed` — ответ модели как написан), прочитанный сервером: наполнение реплики найдено по её тексту. «Чтение» — как модель записала звучание целевого текста буквами родного. Находки — по ответу модели ДО починок, валидатором кода прогона в контексте боя этой пары. «Не проверено» — проверки, для которых у пакета языка нет ключей (`lang.pack_missing`, не находка). Цена — вызовы плана и дня по одному разу. Факты сюжета и роли код не проверяет.

Итог: **failed** (fatal: pronunciation.foreign_script, options.form_mismatch) · починок P2R: 2 (p1: pronunciation.foreign_script, pronunciation.script; p3: pronunciation.foreign_script, pronunciation.script) · вызовов урока: 1 · план $0.0179 · 10.3 с · день $0.1272 (урок $0.1052 · починки $0.0220 · судья $0.0000) · из кэша 16 % входа · 52.6 с · всего $0.1451 · ученик: Пациент · собеседник: Администратор (женщина)

Промты: план `plan-builder-v2` · урок `lesson_day.v4.7` · починка `lesson_card_repair.v1.3` · судья `lesson_seam_judge.v1.1` · модель урока `gpt-5.4-2026-03-05` · попыток урока: 1 · в сцене записано $0.127218 (урок, починки и судья — сверка, не слагаемое)

### Сценарий диалога

| # | вид обмена | кто | реплика | перевод | чтение | каркас · наполнение |
|---|---|---|---|---|---|---|
| 1 | вопрос ученика | Пациент (ученик) | Chcę umówić wizytę do lekarza. | Я хочу записаться к врачу. | хцэн умувичь визытэн до лека́жа | p1 · do lekarza |
| 1 | вопрос ученика | Администратор (собеседник) | Dobrze. Jaki jest powód wizyty? | Хорошо. Какая причина визита? |  |  |
| 2 | ответ | Администратор (собеседник) | Jaki jest powód wizyty? | Какая причина визита? |  |  |
| 2 | ответ | Пациент (ученик) | Mam ból gardła i gorączkę. | У меня болит горло и температура. | мам буль гардуа и горанчкэн | p2 · ból gardła i gorączkę |
| 3 | ответ | Администратор (собеседник) | Mamy wolny termin jutro o dziesiątej. | У нас есть свободное время завтра в десять. |  |  |
| 3 | ответ | Пациент (ученик) | Jutro o dziesiątej mi pasuje. | Завтра в десять мне подходит. | ютро о дженсёнтэй ми пасуе | p3 · jutro o dziesiątej |
| 4 | вопрос ученика | Пациент (ученик) | Czy jest coś po południu? | Есть что-нибудь после обеда? | чы ест цось по полу́дню | p4 · po południu |
| 4 | вопрос ученика | Администратор (собеседник) | Tak, mamy jutro o piętnastej. | Да, у нас есть завтра в пятнадцать. |  |  |
| 5 | ответ | Администратор (собеседник) | Tak, mamy jutro o piętnastej. | Да, у нас есть завтра в пятнадцать. |  |  |
| 5 | ответ | Пациент (ученик) | Jutro o piętnastej mi pasuje. | Завтра в пятнадцать мне подходит. | ютро о пентнастэй ми пасуе | p3 · jutro o piętnastej |
| 6 | вопрос ученика | Пациент (ученик) | Czy mam przynieść dokument? | Мне нужно принести документ? | чы мам пшынещь доку́мэнт | p5 · dokument |
| 6 | вопрос ученика | Администратор (собеседник) | Proszę przynieść dokument i kartę ubezpieczenia. | Пожалуйста, принесите документ и страховую карту. |  |  |
| 7 | переспрос | Пациент (ученик) | Proszę powtórzyć wolniej. | Повторите, пожалуйста, помедленнее. | про́шэ повту́жыць вольней | — |
| 7 | переспрос | Администратор (собеседник) | Dokument i karta ubezpieczenia. | Документ и страховая карта. |  |  |
| 8 | ответ | Администратор (собеседник) | Proszę przyjść dziesięć minut wcześniej. | Пожалуйста, придите на десять минут раньше. |  |  |
| 8 | ответ | Пациент (ученик) | Dobrze, przyjdę dziesięć minut wcześniej. | Хорошо, я приду на десять минут раньше. | добжэ, пшыйдэ джещень минут вчешней | p6 · dziesięć minut wcześniej |

### Каркасы

| id | вид | каркас | на родном | чтение | наполнения (в диалоге — жирным) |
|---|---|---|---|---|---|
| p1 | вопрос ученика | Chcę umówić wizytę ___. | Я хочу записаться ___. | хцэн умувичь визытэн ___ | **do lekarza** / к врачу · do internisty / к терапевту |
| p2 | ответ | Mam ___. | У меня ___. | мам ___ | **ból gardła i gorączkę** / болит горло и температура · kaszel / кашель · wysoką temperaturę / высокую температуру |
| p3 | ответ | ___ mi pasuje. | ___ мне подходит. | ___ ми пасуе | **jutro o dziesiątej** / завтра в десять · **jutro o piętnastej** / завтра в пятнадцать · w piątek rano / в пятницу утром |
| p4 | вопрос ученика | Czy jest coś ___? | Есть что-нибудь ___? | чы ест цось ___ | **po południu** / после обеда · rano / утром |
| p5 | вопрос ученика | Czy mam przynieść ___? | Мне нужно принести ___? | чы мам пшынещь ___ | **dokument** / документ · skierowanie / направление |
| p6 | ответ | Przyjdę ___. | Я приду ___. | пшыйдэ ___ | **dziesięć minut wcześniej** / на десять минут раньше · na czas / вовремя |

### Наполнения

| каркас | наполнение | перевод | чтение | в диалоге | собранная фраза (родной) | судья швов |
|---|---|---|---|---|---|---|
| p1 | do lekarza | к врачу | до лека́жа | да | Я хочу записаться к врачу. | — |
| p1 | do internisty | к терапевту | до интэрнисты | — | Я хочу записаться к терапевту. | — |
| p2 | ból gardła i gorączkę | болит горло и температура | буль гардуа и горанчкэн | да | У меня болит горло и температура. | — |
| p2 | kaszel | кашель | кашэль | — | У меня кашель. | — |
| p2 | wysoką temperaturę | высокую температуру | высокон темпэратурэн | — | У меня высокую температуру. | — |
| p3 | jutro o dziesiątej | завтра в десять | ютро о дженсёнтэй | да | завтра в десять мне подходит. | — |
| p3 | jutro o piętnastej | завтра в пятнадцать | ютро о пентнастэй | да | завтра в пятнадцать мне подходит. | — |
| p3 | w piątek rano | в пятницу утром | ф пёнтэк рано | — | в пятницу утром мне подходит. | — |
| p4 | po południu | после обеда | по полу́дню | да | Есть что-нибудь после обеда? | — |
| p4 | rano | утром | рано | — | Есть что-нибудь утром? | — |
| p5 | dokument | документ | докумэнт | да | Мне нужно принести документ? | — |
| p5 | skierowanie | направление | скерова́не | — | Мне нужно принести направление? | — |
| p6 | dziesięć minut wcześniej | на десять минут раньше | джещень минут вчешней | да | Я приду на десять минут раньше. | — |
| p6 | na czas | вовремя | на час | — | Я приду вовремя. | — |

### Проверки обменов

| # | вопрос | варианты (✓ — верный) |
|---|---|---|
| 1 | O co pyta recepcjonistka? / О чём спрашивает администратор? | ✓ O przyczynę wizyty / О причине визита · O numer telefonu / О номере телефона · O adres domowy / О домашнем адресе |
| 2 | O co pyta recepcjonistka? / О чём спрашивает администратор? | O wolny termin / О свободном времени · O dokument tożsamości / Об удостоверении личности · ✓ O przyczynę przyjścia / О причине обращения |
| 3 | Na kiedy jest wolny termin? / На когда есть свободное время? | Dziś po południu / Сегодня днём · ✓ Jutro rano o dziesiątej / Завтра утром в десять · W piątek wieczorem / В пятницу вечером |
| 4 | Którą godzinę proponuje recepcjonistka? / Какое время предлагает администратор? | ✓ Piętnastą / Пятнадцать часов · Dziewiątą / Девять часов · Osiemnastą / Восемнадцать часов |
| 5 | Na kiedy jest ten termin? / На когда этот приём? | ✓ Jutro o piętnastej / Завтра в пятнадцать · Dziś o piętnastej / Сегодня в пятнадцать · Jutro o jedenastej / Завтра в одиннадцать |
| 6 | Co trzeba przynieść? / Что нужно принести? | Tylko receptę / Только рецепт · ✓ Dokument i kartę ubezpieczenia / Документ и страховую карту · Paszport i wyniki badań / Паспорт и результаты анализов |
| 7 | Jaki drugi dokument wymienia recepcjonistka? / Какой второй документ называет администратор? | Kartę pacjenta / Карту пациента · ✓ Kartę ubezpieczenia / Страховую карту · Prawo jazdy / Водительские права |
| 8 | Kiedy trzeba przyjść? / Когда нужно прийти? | Chwilę po czasie / Немного позже · Pół godziny wcześniej / На полчаса раньше · ✓ Dziesięć minut przed wizytą / За десять минут до приёма |

### Слушаю весь визит

- L1. Зачем пациент записывается на приём? — Чтобы сдать анализы · ✓ Потому что болит горло и есть температура · Чтобы продлить рецепт
- L2. Какое время в итоге выбрал пациент? — Завтра в десять · ✓ Завтра в пятнадцать · В пятницу утром
- L3. Что нужно принести на приём? — ✓ Документ и страховую карту · Только паспорт · Паспорт и результаты анализов
- L4. Когда нужно прийти в клинику? — Ровно ко времени · ✓ На десять минут раньше · После приёма врача

### Словарь

| id | слово | вид | перевод | чтение | где звучит |
|---|---|---|---|---|---|
| v1 | umówić wizytę | связка | записаться на приём | умувичь визытэн | p1 |
| v2 | powód wizyty | связка | причина визита | повуд визыты | A1, A2 |
| v3 | ból gardła | связка | боль в горле | буль гардуа | p2 |
| v4 | gorączka | слово | температура | горанчка | p2 |
| v5 | wolny termin | связка | свободное время для записи | вольны тэрмин | A3 |
| v6 | po południu | связка | после обеда | по полу́дню | p4 |
| v7 | karta ubezpieczenia | связка | страховая карта | карта убэспечэня | A6, A7 |
| v8 | wcześniej | слово | раньше | вчешней | p6, A8 |

### Находки в ответе модели (до починок)

| код | порог | адрес | что |
|---|---|---|---|
| `pronunciation.foreign_script` | **фатальная** | p1.f1 | the reading «до лекáжа» is spelled with letters of another writing: «á» |
| `pronunciation.script` | предупреждение | p1.f1 | the reading «до лекáжа» leaves the native script |
| `pronunciation.foreign_script` | **фатальная** | p3 | the reading «___ ми пасуe» is spelled with letters of another writing: «e» |
| `pronunciation.script` | предупреждение | p3 | the reading «___ ми пасуe» leaves the native script |
| `pronunciation.foreign_script` | **фатальная** | p5.f2 | the reading «скеровáне» is spelled with letters of another writing: «á» |
| `pronunciation.script` | предупреждение | p5.f2 | the reading «скеровáне» leaves the native script |
| `pronunciation.foreign_script` | **фатальная** | B1 | the reading «хцэн умувичь визытэн до лекáжа» is spelled with letters of another writing: «á» |
| `pronunciation.script` | предупреждение | B1 | the reading «хцэн умувичь визытэн до лекáжа» leaves the native script |
| `pronunciation.foreign_script` | **фатальная** | B3 | the reading «ютро о дженсёнтэй ми пасуe» is spelled with letters of another writing: «e» |
| `pronunciation.script` | предупреждение | B3 | the reading «ютро о дженсёнтэй ми пасуe» leaves the native script |
| `pronunciation.foreign_script` | **фатальная** | B5 | the reading «ютро о пентнастэй ми пасуe» is spelled with letters of another writing: «e» |
| `pronunciation.script` | предупреждение | B5 | the reading «ютро о пентнастэй ми пасуe» leaves the native script |
| `options.form_mismatch` | **фатальная** | x5.check | the option «Завтра в пятнадцать» is a piece of the partner's line «Да, у нас есть завтра в пятнадцать.» |
| `options.form_mismatch` | **фатальная** | x6.check | the option «Документ и страховую карту» is a piece of the partner's line «Пожалуйста, принесите документ и страховую карту.» |
| `listening.distractor_not_filler` | предупреждение | L4 | the question asks p3's slot («завтра в десять»): the right option «На десять минут раньше» is a number or a time, the wrong option «Ровно ко времени» is neither a number nor a time |
| `vocab.used_in_wrong` | предупреждение | v7 | «karta ubezpieczenia» is not in the partner's line of exchange 6 |

### Не проверено — нет ключей пакета (lang.pack_missing, не находка)

Кодов не проверено: родной 0, целевой 17. Контекст проверки: родной `ru`, целевой `pl`.

| код | сторона | язык | недостающие ключи |
|---|---|---|---|
| `exchange.second_question` | целевой | pl | sentence_ends |
| `frame.no_end_punct` | целевой | pl | sentence_ends |
| `frame.native_punct` | целевой | pl | sentence_ends |
| `frame.unresolved_pronoun` | целевой | pl | unresolved_pronouns, function_words |
| `filler.ungrammatical` | целевой | pl | sentence_ends, articles, article_sound, clause, seam_repeatable_words |
| `filler.is_clause` | целевой | pl | clause |
| `filler.article_seam` | целевой | pl | article_sound |
| `learner.restates_partner` | целевой | pl | sentence_ends |
| `rescue.new_fact` | целевой | pl | function_words, word_forms |
| `partner.two_questions` | целевой | pl | sentence_ends, second_question_pattern |
| `partner.too_long` | целевой | pl | sentence_ends |
| `partner.closer` | целевой | pl | closers |
| `check.verbatim` | целевой | pl | function_words, word_forms, number_pattern, sentence_ends |
| `check.about_learner` | целевой | pl | saying_verbs, function_words, word_forms |
| `check.listed_alternative_as_wrong` | целевой | pl | alternative_words, function_words, word_forms |
| `vocab.free_combination` | целевой | pl | ordinary_heads, everyday_words, function_words, word_forms |
| `vocab.everyday_word` | целевой | pl | everyday_words |

### Судья швов

Судья не звался: урок не прошёл порог.

### Находки с пакетами LANG-1 (повторная проверка, без вызовов)

Фатальных: 8 → 3 · предупреждений: 8 → 4 · не проверено кодов (родной/целевой): 0/17 → 0/0 · пакеты сейчас: be, de, en, es, fr, it, pl, ro, ru, uk

Коды: появились learner.restates_partner×2, exchange.second_question×1; ушли pronunciation.foreign_script×6, pronunciation.script×6.

| ± код | порог | адрес | что |
|---|---|---|---|
| + `exchange.second_question` | **фатальная** | x1 | the closing message of A «Dobrze. Jaki jest powód wizyty?» ends with a question mark |
| + `learner.restates_partner` | предупреждение | B5 | «Jutro o piętnastej mi pasuje.» repeats 3 of 5 words of the partner's «Tak, mamy jutro o piętnastej.» (jutro, o, piętnastej) |
| + `learner.restates_partner` | предупреждение | B8 | «Dobrze, przyjdę dziesięć minut wcześniej.» repeats 3 of 5 words of the partner's «Proszę przyjść dziesięć minut wcześniej.» (dziesięć, minut, wcześniej) |
| − `pronunciation.foreign_script` | **фатальная** | p1.f1 | the reading «до лекáжа» is spelled with letters of another writing: «á» |
| − `pronunciation.script` | предупреждение | p1.f1 | the reading «до лекáжа» leaves the native script |
| − `pronunciation.foreign_script` | **фатальная** | p3 | the reading «___ ми пасуe» is spelled with letters of another writing: «e» |
| − `pronunciation.script` | предупреждение | p3 | the reading «___ ми пасуe» leaves the native script |
| − `pronunciation.foreign_script` | **фатальная** | p5.f2 | the reading «скеровáне» is spelled with letters of another writing: «á» |
| − `pronunciation.script` | предупреждение | p5.f2 | the reading «скеровáне» leaves the native script |
| − `pronunciation.foreign_script` | **фатальная** | B1 | the reading «хцэн умувичь визытэн до лекáжа» is spelled with letters of another writing: «á» |
| − `pronunciation.script` | предупреждение | B1 | the reading «хцэн умувичь визытэн до лекáжа» leaves the native script |
| − `pronunciation.foreign_script` | **фатальная** | B3 | the reading «ютро о дженсёнтэй ми пасуe» is spelled with letters of another writing: «e» |
| − `pronunciation.script` | предупреждение | B3 | the reading «ютро о дженсёнтэй ми пасуe» leaves the native script |
| − `pronunciation.foreign_script` | **фатальная** | B5 | the reading «ютро о пентнастэй ми пасуe» is spelled with letters of another writing: «e» |
| − `pronunciation.script` | предупреждение | B5 | the reading «ютро о пентнастэй ми пасуe» leaves the native script |
