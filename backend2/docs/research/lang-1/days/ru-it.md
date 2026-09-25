# LANG-1 · Русский→Italiano (ru→it, начальный)

Цель плана (слова ученика): «Записываюсь к врачу: третий день болит горло и температура. Нужно выбрать удобное время и объяснить, что меня беспокоит»

Роль ученика в плане: Patient / Пациент. Сцена 1: «Запись к врачу» (Receptionist / Администратор); сцена 2: «Приём у врача» (Doctor / Врач).

> День 1 — урок, каким его получил бы ученик (прошедший порог, как записан в сцену; при `failed` — ответ модели как написан), прочитанный сервером: наполнение реплики найдено по её тексту. «Чтение» — как модель записала звучание целевого текста буквами родного. Находки — по ответу модели ДО починок, валидатором кода прогона в контексте боя этой пары. «Не проверено» — проверки, для которых у пакета языка нет ключей (`lang.pack_missing`, не находка). Цена — вызовы плана и дня по одному разу. Факты сюжета и роли код не проверяет.

Итог: **failed** (fatal: options.form_mismatch) · починок P2R: 2 (p3: frame.no_end_punct, filler.ungrammatical, filler.one_in_dialogue; B3: line.ne_frame, variant.longer) · вызовов урока: 1 · план $0.0119 · 8.9 с · день $0.0786 (урок $0.0589 · починки $0.0197 · судья $0.0000) · из кэша 61 % входа · 42.0 с · всего $0.0905 · ученик: Пациент · собеседник: Администратор (женщина)

Промты: план `plan-builder-v2` · урок `lesson_day.v4.7` · починка `lesson_card_repair.v1.3` · судья `lesson_seam_judge.v1.1` · модель урока `gpt-5.4-2026-03-05` · попыток урока: 1 · в сцене записано $0.078616 (урок, починки и судья — сверка, не слагаемое)

### Сценарий диалога

| # | вид обмена | кто | реплика | перевод | чтение | каркас · наполнение |
|---|---|---|---|---|---|---|
| 1 | вопрос ученика | Пациент (ученик) | I'd like to make a doctor's appointment. | Я хочу записаться к врачу. | Айд лайк ту мейк э докторз эпойнтмэнт. | p1 · a doctor's appointment |
| 1 | вопрос ученика | Администратор (собеседник) | Certo. Qual è il problema? | Конечно. В чём проблема? |  |  |
| 2 | ответ | Администратор (собеседник) | Mi dica pure. | Скажите, пожалуйста. |  |  |
| 2 | ответ | Пациент (ученик) | I have a sore throat and fever. | У меня болит горло и температура. | Ай хэв э сор сроут энд фивэр. | p2 · a sore throat and fever |
| 3 | ответ | Администратор (собеседник) | Da quanti giorni? | Сколько дней это длится? |  |  |
| 3 | ответ | Пациент (ученик) | For three days. | Уже три дня. | Фор сри дэйз. | p3 · **не каркас ни с одним наполнением** |
| 4 | ответ | Администратор (собеседник) | Abbiamo posto oggi alle tre o domani alle nove. | У нас есть место сегодня в три или завтра в девять. |  |  |
| 4 | ответ | Пациент (ученик) | Tomorrow at nine is better. | Лучше завтра в девять. | Тумороу эт найн из бэтэр. | p4 · tomorrow at nine |
| 5 | вопрос ученика | Пациент (ученик) | How does the appointment work? | Как проходит приём? | Хау даз зи эпойнтмэнт уорк? | p5 · the appointment |
| 5 | вопрос ученика | Администратор (собеседник) | Prima fa l'accettazione, poi aspetta il medico. | Сначала вы оформляетесь, потом ждёте врача. |  |  |
| 6 | переспрос | Пациент (ученик) | Could you say that more slowly, please? | Скажите, пожалуйста, помедленнее. | Куд ю сэй зэт мор слоули, плиз? | — |
| 6 | переспрос | Администратор (собеседник) | Prima l'accettazione. Poi aspetta il medico. | Сначала оформление. Потом ждёте врача. |  |  |
| 7 | вопрос ученика | Пациент (ученик) | What time should I come? | К которому часу мне прийти? | Уот тайм шуд ай кам? | p6 · I come |
| 7 | вопрос ученика | Администратор (собеседник) | Venga quindici minuti prima. | Приходите за пятнадцать минут до приёма. |  |  |
| 8 | ответ | Администратор (собеседник) | Porti la tessera sanitaria e un documento. | Возьмите медицинскую карту и документ. |  |  |
| 8 | ответ | Пациент (ученик) | I'll bring my health card. | Я возьму с собой медицинскую карту. | Айл бринг май хэлс кард. | p7 · my health card |

### Каркасы

| id | вид | каркас | на родном | чтение | наполнения (в диалоге — жирным) |
|---|---|---|---|---|---|
| p1 | вопрос ученика | I'd like to make ___ | Я хочу записаться на ___ | Айд лайк ту мейк ___ | **a doctor's appointment** / приём к врачу · an eye exam / осмотр у окулиста |
| p2 | ответ | I have ___ | У меня ___ | Ай хэв ___ | **a sore throat and fever** / болит горло и температура · a bad cough / сильный кашель · ear pain / боль в ухе |
| p3 | ответ | For ___ | Уже ___ | Фор ___ | for three days / три дня · for two days / два дня |
| p4 | ответ | ___ is better | Лучше ___ | ___ из бэтэр | **tomorrow at nine** / завтра в девять · today at three / сегодня в три |
| p5 | вопрос ученика | How does ___ work? | Как проходит ___? | Хау даз ___ уорк | **the appointment** / приём · the check-in / оформление |
| p6 | вопрос ученика | What time should ___? | К которому часу ___? | Уот тайм шуд ___ | **I come** / мне прийти · I arrive / мне подойти |
| p7 | ответ | I'll bring ___ | Я возьму с собой ___ | Айл бринг ___ | **my health card** / медицинскую карту · my passport / паспорт |

### Наполнения

| каркас | наполнение | перевод | чтение | в диалоге | собранная фраза (родной) | судья швов |
|---|---|---|---|---|---|---|
| p1 | a doctor's appointment | приём к врачу | э докторз эпойнтмэнт | да | Я хочу записаться на приём к врачу | — |
| p1 | an eye exam | осмотр у окулиста | эн ай игзэм | — | Я хочу записаться на осмотр у окулиста | — |
| p2 | a sore throat and fever | болит горло и температура | э сор сроут энд фивэр | да | У меня болит горло и температура | — |
| p2 | a bad cough | сильный кашель | э бэд коф | — | У меня сильный кашель | — |
| p2 | ear pain | боль в ухе | ир пэйн | — | У меня боль в ухе | — |
| p3 | for three days | три дня | фор сри дэйз | — | Уже три дня | — |
| p3 | for two days | два дня | фор ту дэйз | — | Уже два дня | — |
| p4 | tomorrow at nine | завтра в девять | тумороу эт найн | да | Лучше завтра в девять | — |
| p4 | today at three | сегодня в три | тудэй эт сри | — | Лучше сегодня в три | — |
| p5 | the appointment | приём | зи эпойнтмэнт | да | Как проходит приём? | — |
| p5 | the check-in | оформление | зи чек-ин | — | Как проходит оформление? | — |
| p6 | I come | мне прийти | ай кам | да | К которому часу мне прийти? | — |
| p6 | I arrive | мне подойти | ай эрайв | — | К которому часу мне подойти? | — |
| p7 | my health card | медицинскую карту | май хэлс кард | да | Я возьму с собой медицинскую карту | — |
| p7 | my passport | паспорт | май пасспорт | — | Я возьму с собой паспорт | — |

### Проверки обменов

| # | вопрос | варианты (✓ — верный) |
|---|---|---|
| 1 | What does the receptionist ask about? / О чём спрашивает администратор? | ✓ The reason for the visit / О причине визита · The patient's address / Об адресе пациента · The payment method / О способе оплаты |
| 2 | What does the receptionist ask the patient to do? / Что администратор просит пациента сделать? | ✓ Say what is wrong / Сказать, что беспокоит · Show an ID / Показать документ · Wait outside / Подождать снаружи |
| 3 | What time period does the receptionist ask about? / О каком периоде времени спрашивает администратор? | ✓ How long the problem has lasted / Как долго это продолжается · When lunch break is / Когда обеденный перерыв · How long the visit will cost / Сколько будет стоить приём |
| 4 | Which two appointment times does the receptionist offer? / Какие два времени предлагает администратор? | ✓ Today at three and tomorrow at nine / Сегодня в три и завтра в девять · Today at nine and tomorrow at three / Сегодня в девять и завтра в три · This evening and early Monday / Сегодня вечером и рано в понедельник |
| 5 | What happens before seeing the doctor? / Что происходит до приёма у врача? | ✓ Registration at the desk / Оформление на стойке · Blood test in the lab / Анализ крови в лаборатории · Payment at the pharmacy / Оплата в аптеке |
| 6 | What is the second step after registration? / Какой второй шаг после оформления? | ✓ Wait for the doctor / Ждать врача · Go home immediately / Сразу идти домой · Call the clinic again / Снова позвонить в клинику |
| 7 | How early should the patient arrive? / Насколько заранее нужно прийти? | ✓ A quarter of an hour earlier / На пятнадцать минут раньше · Exactly on time / Ровно ко времени · Half an hour later / На полчаса позже |
| 8 | Which document does the receptionist mention? / Какой документ упоминает администратор? | ✓ A health insurance card / Медицинскую карту · A train ticket / Билет на поезд · A hotel booking / Бронь отеля |

### Слушаю весь визит

- L1. Что беспокоит пациента? — ✓ Болит горло и температура · Болит спина · Болит живот
- L2. Какое время пациент выбрал? — Сегодня в три · ✓ Завтра в девять · Послезавтра утром
- L3. Насколько заранее администратор просит прийти? — ✓ За 15 минут · Точно ко времени · За час
- L4. Что нужно взять с собой? — Только воду · ✓ Медицинскую карту и документ · Результаты рентгена

### Словарь

| id | слово | вид | перевод | чтение | где звучит |
|---|---|---|---|---|---|
| v1 | appointment | слово | приём | эпойнтмэнт | p1, p5 |
| v2 | sore throat | связка | боль в горле | сор сроут | p2 |
| v3 | fever | слово | температура | фивэр | p2 |
| v4 | three days | связка | три дня | сри дэйз | p3 |
| v5 | registration | слово | оформление | рэджистрейшн | A5, A6 |
| v6 | wait for the doctor | связка | ждать врача | уэйт фор зэ доктор | A5, A6 |
| v7 | fifteen minutes early | связка | на пятнадцать минут раньше | фифтин минутс ёрли | A7 |
| v8 | health card | связка | медицинская карта | хэлс кард | p7, A8 |

### Находки в ответе модели (до починок)

| код | порог | адрес | что |
|---|---|---|---|
| `frame.no_end_punct` | предупреждение | p1 | the native «Я хочу записаться на ___» ends with no mark |
| `frame.no_end_punct` | предупреждение | p2 | the native «У меня ___» ends with no mark |
| `frame.no_end_punct` | предупреждение | p3 | the native «Уже ___» ends with no mark |
| `frame.no_end_punct` | предупреждение | p4 | the native «Лучше ___» ends with no mark |
| `frame.no_end_punct` | предупреждение | p7 | the native «Я возьму с собой ___» ends with no mark |
| `frame.native_agreement` | предупреждение | p7 | «Я возьму с собой ___»: «собой» agrees with the slot — it changes with the filler |
| `filler.ungrammatical` | **фатальная** | p3.f1 | «For for three days»: a word is doubled at the seam |
| `filler.ungrammatical` | **фатальная** | p3.f2 | «For for two days»: a word is doubled at the seam |
| `filler.one_in_dialogue` | предупреждение | p3.f1 | «for three days» is marked in_dialogue, but no line says it |
| `line.ne_frame` | **фатальная** | B3 | «For three days.» is not «For ___» with any of its fillers («for three days», «for two days») |
| `variant.longer` | предупреждение | B3 | the variant «It's been three days.» has 4 words, the line 3 |
| `options.form_mismatch` | **фатальная** | x6.check | the option «Снова позвонить в клинику» is 22 letters against 10 of the right «Ждать врача» |
| `options.form_mismatch` | **фатальная** | x8.check | the option «Медицинскую карту» is a piece of the partner's line «Возьмите медицинскую карту и документ.» |
| `vocab.used_in_wrong` | предупреждение | v5 | «registration» is not in the partner's line of exchange 5 |
| `vocab.used_in_wrong` | предупреждение | v5 | «registration» is not in the partner's line of exchange 6 |
| `vocab.used_in_wrong` | предупреждение | v6 | «wait for the doctor» is not in the partner's line of exchange 5 |
| `vocab.used_in_wrong` | предупреждение | v6 | «wait for the doctor» is not in the partner's line of exchange 6 |
| `vocab.used_in_wrong` | предупреждение | v7 | «fifteen minutes early» is not in the partner's line of exchange 7 |
| `vocab.used_in_wrong` | предупреждение | v8 | «health card» is not in the partner's line of exchange 8 |

### Не проверено — нет ключей пакета (lang.pack_missing, не находка)

Кодов не проверено: родной 0, целевой 17. Контекст проверки: родной `ru`, целевой `it`.

| код | сторона | язык | недостающие ключи |
|---|---|---|---|
| `exchange.second_question` | целевой | it | sentence_ends |
| `frame.no_end_punct` | целевой | it | sentence_ends |
| `frame.native_punct` | целевой | it | sentence_ends |
| `frame.unresolved_pronoun` | целевой | it | unresolved_pronouns, function_words |
| `filler.ungrammatical` | целевой | it | sentence_ends, articles, article_sound, clause, seam_repeatable_words |
| `filler.is_clause` | целевой | it | clause |
| `filler.article_seam` | целевой | it | article_sound |
| `learner.restates_partner` | целевой | it | sentence_ends |
| `rescue.new_fact` | целевой | it | function_words, word_forms |
| `partner.two_questions` | целевой | it | sentence_ends, second_question_pattern |
| `partner.too_long` | целевой | it | sentence_ends |
| `partner.closer` | целевой | it | closers |
| `check.verbatim` | целевой | it | function_words, word_forms, number_pattern, sentence_ends |
| `check.about_learner` | целевой | it | saying_verbs, function_words, word_forms |
| `check.listed_alternative_as_wrong` | целевой | it | alternative_words, function_words, word_forms |
| `vocab.free_combination` | целевой | it | ordinary_heads, everyday_words, function_words, word_forms |
| `vocab.everyday_word` | целевой | it | everyday_words |

### Судья швов

Судья не звался: урок не прошёл порог.
