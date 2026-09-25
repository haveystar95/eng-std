# LANG-1 · Русский→Română (ru→ro, начальный)

Цель плана (слова ученика): «Записываюсь к врачу: третий день болит горло и температура. Нужно выбрать удобное время и объяснить, что меня беспокоит»

Роль ученика в плане: Pacient / Пациент. Сцена 1: «Запись к врачу» (Recepționer / Администратор); сцена 2: «Приём у врача» (Medic / Врач).

> День 1 — урок, каким его получил бы ученик (прошедший порог, как записан в сцену; при `failed` — ответ модели как написан), прочитанный сервером: наполнение реплики найдено по её тексту. «Чтение» — как модель записала звучание целевого текста буквами родного. Находки — по ответу модели ДО починок, валидатором кода прогона в контексте боя этой пары. «Не проверено» — проверки, для которых у пакета языка нет ключей (`lang.pack_missing`, не находка). Цена — вызовы плана и дня по одному разу. Факты сюжета и роли код не проверяет.

Итог: **ready** · починок P2R: 2 (p5: filler.ungrammatical, filler.one_in_dialogue; x8.check: options.form_mismatch) · вызовов урока: 1 · план $0.0121 · 9.7 с · день $0.1021 (урок $0.0844 · починки $0.0161 · судья $0.0015) · из кэша 63 % входа · 57.0 с · всего $0.1142 · ученик: Пациент · собеседник: Администратор (женщина)

Промты: план `plan-builder-v2` · урок `lesson_day.v4.7` · починка `lesson_card_repair.v1.3` · судья `lesson_seam_judge.v1.1` · модель урока `gpt-5.4-2026-03-05` · попыток урока: 1 · в сцене записано $0.102133 (урок, починки и судья — сверка, не слагаемое)

### Сценарий диалога

| # | вид обмена | кто | реплика | перевод | чтение | каркас · наполнение |
|---|---|---|---|---|---|---|
| 1 | вопрос ученика | Пациент (ученик) | Vreau o programare. | Я хочу записаться на приём. | Вряу о програмаре. | p1 · o programare |
| 1 | вопрос ученика | Администратор (собеседник) | Sigur. Pentru ce specialitate? | Конечно. К какому специалисту? |  |  |
| 2 | ответ | Администратор (собеседник) | La medicul de familie avem loc mâine. | К терапевту есть место завтра. |  |  |
| 2 | ответ | Пациент (ученик) | Am nevoie de medicul de familie. | Мне нужен терапевт. | Ам невоее дэ медикул дэ фамилие. | p2 · medicul de familie |
| 3 | ответ | Администратор (собеседник) | Care este problema principală? | Какая у вас основная проблема? |  |  |
| 3 | ответ | Пациент (ученик) | Mă doare gâtul. | У меня болит горло. | Мэ доаре гытул. | p3 · gâtul |
| 4 | ответ | Администратор (собеседник) | De când aveți simptomele? | Как долго у вас эти симптомы? |  |  |
| 4 | ответ | Пациент (ученик) | Am simptome de trei zile. | У меня симптомы уже три дня. | Ам симптоме дэ трей зиле. | p4 · de trei zile |
| 5 | ответ | Администратор (собеседник) | Avem mâine la zece sau la două. Mai veniți cu actul și ajungeți cu zece minute înainte. | У нас есть завтра в десять или в два. Возьмите документ и приходите за десять минут раньше. |  |  |
| 5 | ответ | Пациент (ученик) | Pot mâine la zece. | Я могу завтра в десять. | Пот мыйне ла зече. | p5 · la zece |
| 6 | переспрос | Пациент (ученик) | Puteți repeta mai rar, vă rog? | Повторите, пожалуйста, помедленнее. | Путець репета май рар, вэ рог? | — |
| 6 | переспрос | Администратор (собеседник) | La zece sau la două. Aduceți actul și veniți cu zece minute înainte. | В десять или в два. Возьмите документ и приходите за десять минут раньше. |  |  |
| 7 | вопрос ученика | Пациент (ученик) | Ce documente trebuie să aduc? | Какие документы мне нужно принести? | Че докумЕнте трэбуе сэ адУк? | p6 · Ce documente |
| 7 | вопрос ученика | Администратор (собеседник) | Doar buletinul este suficient. | Достаточно только удостоверения личности. |  |  |
| 8 | вопрос ученика | Пациент (ученик) | Cât costă consultația? | Сколько стоит консультация? | Кыт костэ консултация? | p7 · consultația |
| 8 | вопрос ученика | Администратор (собеседник) | Consultația costă două sute de lei. | Консультация стоит двести леев. |  |  |

### Каркасы

| id | вид | каркас | на родном | чтение | наполнения (в диалоге — жирным) |
|---|---|---|---|---|---|
| p1 | вопрос ученика | Vreau ___. | Я хочу ___. | Вряу ___. | **o programare** / записаться на приём · o consultație / консультацию |
| p2 | ответ | Am nevoie de ___. | Мне нужен ___. | Ам невоее дэ ___. | **medicul de familie** / терапевт · un ORL / лор |
| p3 | ответ | Mă doare ___. | У меня болит ___. | Мэ доаре ___. | **gâtul** / горло · capul / голова |
| p4 | ответ | Am simptome ___. | У меня симптомы ___. | Ам симптоме ___. | **de trei zile** / уже три дня · de ieri / со вчерашнего дня |
| p5 | ответ | Pot mâine ___. | Я могу завтра ___. | Пот мыйне ___. | **la zece** / в десять · la două / в два |
| p6 | вопрос ученика | ___ trebuie să aduc? | ___ мне нужно принести? | ___ трэбуе сэ адУк? | **Ce documente** / Какие документы · Ce acte / Какие документы |
| p7 | вопрос ученика | Cât costă ___? | Сколько стоит ___? | Кыт костэ ___? | **consultația** / консультация · analiza / анализ |

### Наполнения

| каркас | наполнение | перевод | чтение | в диалоге | собранная фраза (родной) | судья швов |
|---|---|---|---|---|---|---|
| p1 | o programare | записаться на приём | о програмаре | да | Я хочу записаться на приём. | да |
| p1 | o consultație | консультацию | о консултацие | — | Я хочу консультацию. | да |
| p2 | medicul de familie | терапевт | медикул дэ фамилие | да | Мне нужен терапевт. | да |
| p2 | un ORL | лор | ун орээл | — | Мне нужен лор. | да |
| p3 | gâtul | горло | гытул | да | У меня болит горло. | да |
| p3 | capul | голова | капул | — | У меня болит голова. | да |
| p4 | de trei zile | уже три дня | дэ трей зиле | да | У меня симптомы уже три дня. | да |
| p4 | de ieri | со вчерашнего дня | дэ йерь | — | У меня симптомы со вчерашнего дня. | да |
| p5 | la zece | в десять | ла зече | да | Я могу завтра в десять. | да |
| p5 | la două | в два | ла доуэ | — | Я могу завтра в два. | да |
| p6 | Ce documente | Какие документы | че докумЕнте | да | Какие документы мне нужно принести? | да |
| p6 | Ce acte | Какие документы | че акте | — | Какие документы мне нужно принести? | да |
| p7 | consultația | консультация | консултация | да | Сколько стоит консультация? | да |
| p7 | analiza | анализ | анализа | — | Сколько стоит анализ? | да |

### Проверки обменов

| # | вопрос | варианты (✓ — верный) |
|---|---|---|
| 1 | What kind of doctor does the receptionist ask about? / О каком враче спрашивает администратор? | ✓ About the medical field needed / О нужной специальности врача · About the clinic address / Об адресе клиники · About the insurance company / О страховой компании |
| 2 | When is the available appointment? / Когда есть свободная запись? | ✓ The next day / На следующий день · This evening / Сегодня вечером · In three days / Через три дня |
| 3 | What does the receptionist want to know? / Что хочет узнать администратор? | ✓ The main reason for the visit / Главную причину визита · The patient's home address / Домашний адрес пациента · The doctor's last name / Фамилию врача |
| 4 | What time detail does the receptionist ask for? / Какую информацию о времени спрашивает администратор? | ✓ How long the problem has lasted / Сколько времени длится проблема · What hour the fever starts / Во сколько начинается температура · Which day the patient works / В какой день пациент работает |
| 5 | Which two appointment times does the receptionist offer? / Какие два времени предлагает администратор? | ✓ Ten in the morning and two in the afternoon / Десять утра и два часа дня · Nine in the morning and one in the afternoon / Девять утра и час дня · Eleven in the morning and three in the afternoon / Одиннадцать утра и три часа дня |
| 6 | How early should the patient come? / Насколько раньше нужно прийти? | A quarter of an hour early / На пятнадцать минут раньше · ✓ Ten minutes before the visit / За десять минут до приёма · Right at the appointment time / Ровно ко времени приёма |
| 7 | Which document does the receptionist say is enough? / Какой документ, по словам администратора, достаточно принести? | A passport-sized photo / Фотографию на документы · ✓ An ID card / Удостоверение личности · A medical test result / Результат анализа |
| 8 | What price does the receptionist give? / Какую цену называет администратор? | One hundred fifty lei / Сто пятьдесят леев · ✓ It is 200 lei / Это 200 леев · Two hundred fifty lei / Двести пятьдесят леев |

### Слушаю весь визит

- L1. К какому врачу записывается пациент? — ✓ К терапевту · К стоматологу · К хирургу
- L2. Сколько дней у пациента симптомы? — Один день · ✓ Три дня · Неделю
- L3. Какое время выбирает пациент? — ✓ Завтра в десять · Завтра в два · Сегодня вечером
- L4. Что нужно принести на приём? — Результаты анализов · Полис и фотографию · ✓ Удостоверение личности

### Словарь

| id | слово | вид | перевод | чтение | где звучит |
|---|---|---|---|---|---|
| v1 | programare | слово | запись на приём | програмаре | p1 |
| v2 | medicul de familie | связка | терапевт | медикул дэ фамилие | p2, A2 |
| v3 | gât | слово | горло | гыт | p3 |
| v4 | simptome | слово | симптомы | симптоме | p4, A4 |
| v5 | act | слово | документ | акт | A5, A6, p6 |
| v6 | buletin | слово | удостоверение личности | булетин | A7 |
| v7 | consultație | слово | консультация | консултацие | p1, p7, A8 |
| v8 | înainte | слово | заранее | ынынте | A5, A6 |

### Находки в ответе модели (до починок)

| код | порог | адрес | что |
|---|---|---|---|
| `frame.native_agreement` | предупреждение | p2 | «Мне нужен ___.»: «нужен» agrees with the slot — it changes with the filler |
| `filler.ungrammatical` | **фатальная** | p5.f1 | «Pot mâine la la zece.»: a word is doubled at the seam |
| `filler.ungrammatical` | **фатальная** | p5.f2 | «Pot mâine la la două.»: a word is doubled at the seam |
| `filler.one_in_dialogue` | предупреждение | p5.f1 | «la zece» is marked in_dialogue, but no line says it |
| `variant.longer` | предупреждение | B1 | the variant «Am nevoie de o programare.» has 5 words, the line 3 |
| `variant.longer` | предупреждение | B3 | the variant «Am durere în gât.» has 4 words, the line 3 |
| `line.ne_frame` | **фатальная** | B5 | «Pot mâine la zece.» is not «Pot mâine la ___.» with any of its fillers («la zece», «la două») |
| `options.form_mismatch` | **фатальная** | x8.check | the option «Двести леев» is a piece of the partner's line «Консультация стоит двести леев.» |
| `vocab.used_in_wrong` | предупреждение | v3 | «gât» is not in frame p3 or its fillers |
| `vocab.used_in_wrong` | предупреждение | v5 | «act» is not in the partner's line of exchange 5 |
| `vocab.used_in_wrong` | предупреждение | v5 | «act» is not in the partner's line of exchange 6 |
| `vocab.used_in_wrong` | предупреждение | v5 | «act» is not in frame p6 or its fillers |

### Не проверено — нет ключей пакета (lang.pack_missing, не находка)

Кодов не проверено: родной 0, целевой 17. Контекст проверки: родной `ru`, целевой `ro`.

| код | сторона | язык | недостающие ключи |
|---|---|---|---|
| `exchange.second_question` | целевой | ro | sentence_ends |
| `frame.no_end_punct` | целевой | ro | sentence_ends |
| `frame.native_punct` | целевой | ro | sentence_ends |
| `frame.unresolved_pronoun` | целевой | ro | unresolved_pronouns, function_words |
| `filler.ungrammatical` | целевой | ro | sentence_ends, articles, article_sound, clause, seam_repeatable_words |
| `filler.is_clause` | целевой | ro | clause |
| `filler.article_seam` | целевой | ro | article_sound |
| `learner.restates_partner` | целевой | ro | sentence_ends |
| `rescue.new_fact` | целевой | ro | function_words, word_forms |
| `partner.two_questions` | целевой | ro | sentence_ends, second_question_pattern |
| `partner.too_long` | целевой | ro | sentence_ends |
| `partner.closer` | целевой | ro | closers |
| `check.verbatim` | целевой | ro | function_words, word_forms, number_pattern, sentence_ends |
| `check.about_learner` | целевой | ro | saying_verbs, function_words, word_forms |
| `check.listed_alternative_as_wrong` | целевой | ro | alternative_words, function_words, word_forms |
| `vocab.free_combination` | целевой | ro | ordinary_heads, everyday_words, function_words, word_forms |
| `vocab.everyday_word` | целевой | ro | everyday_words |

### Судья швов

Предложений: 14 · с вердиктом: 14 · «нет»: 0 · `lesson_seam_judge.v1.1` · $0.0015
