# LANG-1 · Русский→Deutsch (ru→de, начальный)

Цель плана (слова ученика): «Записываюсь к врачу: третий день болит горло и температура. Нужно выбрать удобное время и объяснить, что меня беспокоит»

Роль ученика в плане: Patient / Пациент. Сцена 1: «Запись к врачу» (Receptionist / Администратор); сцена 2: «Приём у врача» (Doctor / Врач).

> День 1 — урок, каким его получил бы ученик (прошедший порог, как записан в сцену; при `failed` — ответ модели как написан), прочитанный сервером: наполнение реплики найдено по её тексту. «Чтение» — как модель записала звучание целевого текста буквами родного. Находки — по ответу модели ДО починок, валидатором кода прогона в контексте боя этой пары. «Не проверено» — проверки, для которых у пакета языка нет ключей (`lang.pack_missing`, не находка). Цена — вызовы плана и дня по одному разу. Факты сюжета и роли код не проверяет.

Итог: **failed** (fatal: options.form_mismatch) · починок P2R: 2 (x5.check: options.form_mismatch; x7.check: options.form_mismatch) · вызовов урока: 1 · план $0.0123 · 9.0 с · день $0.0799 (урок $0.0615 · починки $0.0184 · судья $0.0000) · из кэша 55 % входа · 43.1 с · всего $0.0921 · ученик: Пациент · собеседник: Администратор (женщина)

Промты: план `plan-builder-v2` · урок `lesson_day.v4.7` · починка `lesson_card_repair.v1.3` · судья `lesson_seam_judge.v1.1` · модель урока `gpt-5.4-2026-03-05` · попыток урока: 1 · в сцене записано $0.079874 (урок, починки и судья — сверка, не слагаемое)

### Сценарий диалога

| # | вид обмена | кто | реплика | перевод | чтение | каркас · наполнение |
|---|---|---|---|---|---|---|
| 1 | вопрос ученика | Пациент (ученик) | Ich brauche einen Termin. | Мне нужен приём. | их браухэ айнэн тэрмин | p1 · einen Termin |
| 1 | вопрос ученика | Администратор (собеседник) | Gern. Worum geht es? | Конечно. Что случилось? |  |  |
| 2 | ответ | Администратор (собеседник) | Was haben Sie? | Что у вас? |  |  |
| 2 | ответ | Пациент (ученик) | Ich habe Halsschmerzen. | У меня болит горло. | их хабэ хальсшмерцэн | p2 · Halsschmerzen |
| 3 | ответ | Администратор (собеседник) | Seit wann haben Sie das? | Как давно это у вас? |  |  |
| 3 | ответ | Пациент (ученик) | Das habe ich seit drei Tagen. | Это у меня уже три дня. | дас хабэ их зайт драй тагэн | p3 · seit drei Tagen |
| 4 | ответ | Администратор (собеседник) | Wir haben morgen um neun oder um elf frei. | У нас завтра свободно в девять или в одиннадцать. |  |  |
| 4 | ответ | Пациент (ученик) | Um elf passt besser. | В одиннадцать мне удобнее. | ум эльф паст бэсэр | p4 · um elf |
| 5 | вопрос ученика | Пациент (ученик) | Haben Sie etwas am Nachmittag? | У вас есть что-нибудь на вторую половину дня? | хабэн зи этвас ам нахмиттаг | p5 · am Nachmittag |
| 5 | вопрос ученика | Администратор (собеседник) | Ja, um vier Uhr ist noch ein Termin frei. | Да, в четыре часа ещё есть свободный приём. |  |  |
| 6 | ответ | Администратор (собеседник) | Gut, dann trage ich Sie morgen um vier ein. | Хорошо, тогда я запишу вас на завтра на четыре. |  |  |
| 6 | ответ | Пациент (ученик) | Das ist gut, morgen um vier. | Хорошо, завтра в четыре. | дас ист гут, моргэн ум фир | p6 · morgen um vier |
| 7 | ответ | Администратор (собеседник) | Bitte bringen Sie Ihre Versicherungskarte mit. | Пожалуйста, возьмите с собой страховую карту. |  |  |
| 7 | ответ | Пациент (ученик) | Ich bringe meine Versicherungskarte mit. | Я возьму с собой страховую карту. | их брингэ майнэ фэрзихерунгскартэ мит | p7 · meine Versicherungskarte |
| 8 | вопрос ученика | Пациент (ученик) | Können Sie mir die Adresse sagen? | Можете сказать мне адрес? | кёнэн зи мир ди адрэсэ загэн | p8 · die Adresse |
| 8 | вопрос ученика | Администратор (собеседник) | Ja, die Klinik ist in der Bahnhofstraße zwölf. | Да, клиника находится на Банхофштрассе, 12. |  |  |

### Каркасы

| id | вид | каркас | на родном | чтение | наполнения (в диалоге — жирным) |
|---|---|---|---|---|---|
| p1 | вопрос ученика | Ich brauche ___. | Мне нужен ___. | их браухэ ___ | **einen Termin** / приём · einen Arzt / врач |
| p2 | ответ | Ich habe ___. | У меня ___. | их хабэ ___ | **Halsschmerzen** / болит горло · Fieber / температура · Husten / кашель |
| p3 | ответ | Das habe ich ___. | Это у меня ___. | дас хабэ их ___ | **seit drei Tagen** / уже три дня · seit gestern / со вчера · seit heute Morgen / с сегодняшнего утра |
| p4 | ответ | ___ passt besser. | ___ мне удобнее. | ___ паст бэсэр | **um elf** / в одиннадцать · am Morgen / утром · am Abend / вечером |
| p5 | вопрос ученика | Haben Sie etwas ___? | У вас есть что-нибудь ___? | хабэн зи этвас ___ | **am Nachmittag** / на вторую половину дня · für heute / на сегодня · am Freitag / на пятницу |
| p6 | ответ | Das ist gut, ___. | Хорошо, ___. | дас ист гут, ___ | **morgen um vier** / завтра в четыре · heute um fünf / сегодня в пять |
| p7 | ответ | Ich bringe ___ mit. | Я возьму с собой ___. | их брингэ ___ мит | **meine Versicherungskarte** / страховую карту · meinen Ausweis / удостоверение личности |
| p8 | вопрос ученика | Können Sie mir ___ sagen? | Можете сказать мне ___? | кёнэн зи мир ___ загэн | **die Adresse** / адрес · den Namen des Arztes / имя врача |

### Наполнения

| каркас | наполнение | перевод | чтение | в диалоге | собранная фраза (родной) | судья швов |
|---|---|---|---|---|---|---|
| p1 | einen Termin | приём | айнэн тэрмин | да | Мне нужен приём. | — |
| p1 | einen Arzt | врач | айнэн арцт | — | Мне нужен врач. | — |
| p2 | Halsschmerzen | болит горло | хальсшмерцэн | да | У меня болит горло. | — |
| p2 | Fieber | температура | фибер | — | У меня температура. | — |
| p2 | Husten | кашель | хустэрн | — | У меня кашель. | — |
| p3 | seit drei Tagen | уже три дня | зайт драй тагэн | да | Это у меня уже три дня. | — |
| p3 | seit gestern | со вчера | зайт гэстэрн | — | Это у меня со вчера. | — |
| p3 | seit heute Morgen | с сегодняшнего утра | зайт хойтэ моргэн | — | Это у меня с сегодняшнего утра. | — |
| p4 | um elf | в одиннадцать | ум эльф | да | в одиннадцать мне удобнее. | — |
| p4 | am Morgen | утром | ам моргэн | — | утром мне удобнее. | — |
| p4 | am Abend | вечером | ам абэнт | — | вечером мне удобнее. | — |
| p5 | am Nachmittag | на вторую половину дня | ам нахмиттаг | да | У вас есть что-нибудь на вторую половину дня? | — |
| p5 | für heute | на сегодня | фюр хойтэ | — | У вас есть что-нибудь на сегодня? | — |
| p5 | am Freitag | на пятницу | ам фрайтаг | — | У вас есть что-нибудь на пятницу? | — |
| p6 | morgen um vier | завтра в четыре | моргэн ум фир | да | Хорошо, завтра в четыре. | — |
| p6 | heute um fünf | сегодня в пять | хойтэ ум фюнф | — | Хорошо, сегодня в пять. | — |
| p7 | meine Versicherungskarte | страховую карту | майнэ фэрзихерунгскартэ | да | Я возьму с собой страховую карту. | — |
| p7 | meinen Ausweis | удостоверение личности | майнэн аусвайс | — | Я возьму с собой удостоверение личности. | — |
| p8 | die Adresse | адрес | ди адрэсэ | да | Можете сказать мне адрес? | — |
| p8 | den Namen des Arztes | имя врача | дэн намэн дэс арцтэс | — | Можете сказать мне имя врача? | — |

### Проверки обменов

| # | вопрос | варианты (✓ — верный) |
|---|---|---|
| 1 | What does the receptionist ask about? / О чём спрашивает администратор? | ✓ About the problem / О проблеме · About the insurance card / О страховой карте · About the home address / О домашнем адресе |
| 2 | What does the receptionist want to know? / Что хочет узнать администратор? | How long it lasts / Как долго это длится · ✓ What the illness is / Что именно беспокоит · Which doctor is needed / Какой врач нужен |
| 3 | What time period does the receptionist ask about? / О каком сроке спрашивает администратор? | How often it happens / Как часто это бывает · When the clinic opens / Когда открывается клиника · ✓ How long the problem has lasted / Как долго длится проблема |
| 4 | Which two times does the receptionist offer? / Какие два времени предлагает администратор? | ✓ Nine and eleven / Девять и одиннадцать · Ten and twelve / Десять и двенадцать · Eight and eleven / Восемь и одиннадцать |
| 5 | What afternoon time is still available? / Какое время во второй половине дня ещё свободно? | At three o'clock / В три часа · ✓ At four o'clock / В четыре часа · At five o'clock / В пять часов |
| 6 | For when does the receptionist enter the appointment? / На когда администратор записывает приём? | Today at four / Сегодня в четыре · ✓ Tomorrow at four / Завтра в четыре · Tomorrow at eleven / Завтра в одиннадцать |
| 7 | What should the patient bring? / Что пациенту нужно принести? | A passport / Паспорт · A prescription / Рецепт · ✓ An insurance card / Страховую карту |
| 8 | Where is the clinic? / Где находится клиника? | On Park Street, 12 / На Паркштрассе, 12 · ✓ On Bahnhofstraße, 12 / На Банхофштрассе, 12 · On Marktstraße, 21 / На Марктштрассе, 21 |

### Слушаю весь визит

- L1. Что беспокоит пациента? — ✓ Болит горло · Болит живот · Болит спина
- L2. Как долго у пациента эта проблема? — Со вчера · ✓ Три дня · Неделю
- L3. На какое время в итоге записали пациента? — Завтра в одиннадцать · Сегодня в четыре · ✓ Завтра в четыре
- L4. Что нужно взять с собой на приём? — ✓ Страховую карту · Результаты анализов · Паспорт

### Словарь

| id | слово | вид | перевод | чтение | где звучит |
|---|---|---|---|---|---|
| v1 | Termin | слово | приём | тэрмин | p1, A5 |
| v2 | Halsschmerzen | слово | боль в горле | хальсшмерцэн | p2 |
| v3 | Fieber | слово | температура | фибер | p2 |
| v4 | seit drei Tagen | связка | уже три дня | зайт драй тагэн | p3 |
| v5 | am Nachmittag | связка | во второй половине дня | ам нахмиттаг | p5 |
| v6 | Versicherungskarte | слово | страховая карта | фэрзихерунгскартэ | p7, A7 |
| v7 | eintragen | слово | записывать | айнтрагэн | A6 |
| v8 | Adresse | слово | адрес | адрэсэ | p8 |

### Находки в ответе модели (до починок)

| код | порог | адрес | что |
|---|---|---|---|
| `frame.native_agreement` | предупреждение | p1 | «Мне нужен ___.»: «нужен» agrees with the slot — it changes with the filler |
| `frame.native_agreement` | предупреждение | p7 | «Я возьму с собой ___.»: «собой» agrees with the slot — it changes with the filler |
| `variant.longer` | предупреждение | B2 | the variant «Ich habe Schmerzen im Hals.» has 5 words, the line 3 |
| `options.form_mismatch` | **фатальная** | x5.check | the option «В четыре часа» is a piece of the partner's line «Да, в четыре часа ещё есть свободный приём.» |
| `options.form_mismatch` | **фатальная** | x7.check | the option «Рецепт» is 6 letters against 14 of the right «Страховую карту» |
| `options.form_mismatch` | **фатальная** | x8.check | the option «На Банхофштрассе, 12» is a piece of the partner's line «Да, клиника находится на Банхофштрассе, 12.» |
| `vocab.used_in_wrong` | предупреждение | v7 | «eintragen» is not in the partner's line of exchange 6 |

### Не проверено — нет ключей пакета (lang.pack_missing, не находка)

Кодов не проверено: родной 0, целевой 17. Контекст проверки: родной `ru`, целевой `de`.

| код | сторона | язык | недостающие ключи |
|---|---|---|---|
| `exchange.second_question` | целевой | de | sentence_ends |
| `frame.no_end_punct` | целевой | de | sentence_ends |
| `frame.native_punct` | целевой | de | sentence_ends |
| `frame.unresolved_pronoun` | целевой | de | unresolved_pronouns, function_words |
| `filler.ungrammatical` | целевой | de | sentence_ends, articles, article_sound, clause, seam_repeatable_words |
| `filler.is_clause` | целевой | de | clause |
| `filler.article_seam` | целевой | de | article_sound |
| `learner.restates_partner` | целевой | de | sentence_ends |
| `rescue.new_fact` | целевой | de | function_words, word_forms |
| `partner.two_questions` | целевой | de | sentence_ends, second_question_pattern |
| `partner.too_long` | целевой | de | sentence_ends |
| `partner.closer` | целевой | de | closers |
| `check.verbatim` | целевой | de | function_words, word_forms, number_pattern, sentence_ends |
| `check.about_learner` | целевой | de | saying_verbs, function_words, word_forms |
| `check.listed_alternative_as_wrong` | целевой | de | alternative_words, function_words, word_forms |
| `vocab.free_combination` | целевой | de | ordinary_heads, everyday_words, function_words, word_forms |
| `vocab.everyday_word` | целевой | de | everyday_words |

### Судья швов

Судья не звался: урок не прошёл порог.
