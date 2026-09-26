# LANG-1 · Русский→Français (ru→fr, начальный)

Цель плана (слова ученика): «Записываюсь к врачу: третий день болит горло и температура. Нужно выбрать удобное время и объяснить, что меня беспокоит»

Роль ученика в плане: Patient / Пациент. Сцена 1: «Запись к врачу» (Receptionist / Администратор); сцена 2: «Приём у врача» (Doctor / Врач).

> День 1 — урок, каким его получил бы ученик (прошедший порог, как записан в сцену; при `failed` — ответ модели как написан), прочитанный сервером: наполнение реплики найдено по её тексту. «Чтение» — как модель записала звучание целевого текста буквами родного. Находки — по ответу модели ДО починок, валидатором кода прогона в контексте боя этой пары. «Не проверено» — проверки, для которых у пакета языка нет ключей (`lang.pack_missing`, не находка). Цена — вызовы плана и дня по одному разу. Факты сюжета и роли код не проверяет.

Итог: **failed** (fatal: pronunciation.foreign_script) · починок P2R: 2 (x4.check: options.form_mismatch; x6.check: options.form_mismatch) · вызовов урока: 1 · план $0.0116 · 7.8 с · день $0.0796 (урок $0.0614 · починки $0.0182 · судья $0.0000) · из кэша 56 % входа · 43.3 с · всего $0.0911 · ученик: Пациент · собеседник: Администратор (женщина)

Промты: план `plan-builder-v2` · урок `lesson_day.v4.7` · починка `lesson_card_repair.v1.3` · судья `lesson_seam_judge.v1.1` · модель урока `gpt-5.4-2026-03-05` · попыток урока: 1 · в сцене записано $0.079564 (урок, починки и судья — сверка, не слагаемое)

### Сценарий диалога

| # | вид обмена | кто | реплика | перевод | чтение | каркас · наполнение |
|---|---|---|---|---|---|---|
| 1 | вопрос ученика | Пациент (ученик) | Je voudrais un rendez-vous. | Я хотел(а) бы записаться на приём. | Жё вудрэ эн ранде-ву. | p1 · un rendez-vous |
| 1 | вопрос ученика | Администратор (собеседник) | D'accord. C'est pour quel problème ? | Хорошо. По какому вопросу вы хотите записаться? |  |  |
| 2 | ответ | Администратор (собеседник) | D'accord. C'est pour quel problème ? | Хорошо. По какому вопросу вы хотите записаться? |  |  |
| 2 | ответ | Пациент (ученик) | J'ai mal à la gorge. | У меня болит горло. | Жэ маль а ла горж. | p2 · mal à la gorge |
| 3 | ответ | Администратор (собеседник) | Depuis combien de jours ? | Сколько дней это уже длится? |  |  |
| 3 | ответ | Пациент (ученик) | Depuis trois jours. | Уже три дня. | Дёпюи труа жур. | p3 · trois jours |
| 4 | ответ | Администратор (собеседник) | Nous avons une place demain à dix heures. | У нас есть место завтра в десять часов. |  |  |
| 4 | ответ | Пациент (ученик) | À dix heures, c'est bon. | В десять часов мне подходит. | А дис ёр, сэ бон. | p4 · à dix heures |
| 5 | вопрос ученика | Пациент (ученик) | Il faut quoi comme informations ? | Какая информация нужна? | Иль фо куа ком энформасьон ? | p5 · informations |
| 5 | вопрос ученика | Администратор (собеседник) | J'ai besoin de votre nom et de votre date de naissance. | Мне нужны ваши имя и дата рождения. |  |  |
| 6 | переспрос | Пациент (ученик) | Pardon, plus lentement, s'il vous plaît. | Извините, помедленнее, пожалуйста. | Пардон, плю лантман, силь ву плэ. | — |
| 6 | переспрос | Администратор (собеседник) | Votre nom et votre date de naissance. | Ваше имя и ваша дата рождения. |  |  |
| 7 | ответ | Администратор (собеседник) | Et votre numéro de téléphone ? | И ваш номер телефона? |  |  |
| 7 | ответ | Пациент (ученик) | Mon numéro, c'est 06 12 34 56 78. | Мой номер — 06 12 34 56 78. | Мон нюмэро, сэ зеро сис, дуз, трант-катр, сант-кат, сисант-дизуит. | p6 · 06 12 34 56 78 |
| 8 | ответ | Администратор (собеседник) | C'est noté. Rendez-vous demain à dix heures. Arrivez dix minutes avant. | Я записала. Приём завтра в десять. Приходите за десять минут до начала. |  |  |
| 8 | ответ | Пациент (ученик) | D'accord, j'arrive dix minutes avant. | Хорошо, я приду за десять минут до начала. | Дакор, жаррив диз минут аван. | p7 · dix minutes avant |

### Каркасы

| id | вид | каркас | на родном | чтение | наполнения (в диалоге — жирным) |
|---|---|---|---|---|---|
| p1 | вопрос ученика | Je voudrais ___. | Я хотел(а) бы ___ | Жё вудрэ ___. | **un rendez-vous** / записаться на приём · un autre horaire / другое время |
| p2 | ответ | J'ai ___ | У меня ___ | Жэ ___ | **mal à la gorge** / болит горло · de la fièvre / температура · mal à la tête / болит голова |
| p3 | ответ | Depuis ___. | Уже ___ | Дёпюи ___. | **trois jours** / три дня · deux jours / два дня · une semaine / неделю |
| p4 | ответ | ___, c'est bon. | ___ мне подходит | ___, сэ бон. | **à dix heures** / в десять часов · demain matin / завтра утром |
| p5 | вопрос ученика | Il faut quoi comme ___? | Какая ___ нужна? | Иль фо куа ком ___ ? | **informations** / информация · documents / документы |
| p6 | ответ | Mon numéro, c'est ___. | Мой номер — ___ | Мон нюмэро, сэ ___. | **06 12 34 56 78** / 06 12 34 56 78 · 07 45 11 22 30 / 07 45 11 22 30 |
| p7 | ответ | J'arrive ___. | Я приду ___ | Жаррив ___. | **dix minutes avant** / за десять минут до начала · un peu plus tôt / немного пораньше |

### Наполнения

| каркас | наполнение | перевод | чтение | в диалоге | собранная фраза (родной) | судья швов |
|---|---|---|---|---|---|---|
| p1 | un rendez-vous | записаться на приём | эн ранде-ву | да | Я хотел(а) бы записаться на приём | — |
| p1 | un autre horaire | другое время | эн отр о рэр | — | Я хотел(а) бы другое время | — |
| p2 | mal à la gorge | болит горло | маль а ла горж | да | У меня болит горло | — |
| p2 | de la fièvre | температура | дё ла фьевр | — | У меня температура | — |
| p2 | mal à la tête | болит голова | маль а ла тэт | — | У меня болит голова | — |
| p3 | trois jours | три дня | труа жур | да | Уже три дня | — |
| p3 | deux jours | два дня | дё жур | — | Уже два дня | — |
| p3 | une semaine | неделю | юн сэмен | — | Уже неделю | — |
| p4 | à dix heures | в десять часов | а дис ёр | да | в десять часов мне подходит | — |
| p4 | demain matin | завтра утром | дёман матен | — | завтра утром мне подходит | — |
| p5 | informations | информация | энформасьон | да | Какая информация нужна? | — |
| p5 | documents | документы | докюман | — | Какая документы нужна? | — |
| p6 | 06 12 34 56 78 | 06 12 34 56 78 | зеро сис, дуз, трант-катр, сант-кат, сисант-дизуит | да | Мой номер — 06 12 34 56 78 | — |
| p6 | 07 45 11 22 30 | 07 45 11 22 30 | зеро сет, карант-сэнк, онз, ван-дё, трант | — | Мой номер — 07 45 11 22 30 | — |
| p7 | dix minutes avant | за десять минут до начала | диз минут аван | да | Я приду за десять минут до начала | — |
| p7 | un peu plus tôt | немного пораньше | эн пё плю то | — | Я приду немного пораньше | — |

### Проверки обменов

| # | вопрос | варианты (✓ — верный) |
|---|---|---|
| 1 | What does the receptionist ask about? / О чём спрашивает администратор? | ✓ The reason for the visit / О причине визита · Your home address / О вашем домашнем адресе · Your insurance company / О вашей страховой компании |
| 2 | What does the receptionist want to know? / Что хочет узнать администратор? | Which doctor you prefer / К какому врачу вы хотите · ✓ Why you need the appointment / Зачем вам нужен приём · When you were last at the clinic / Когда вы были в клинике в прошлый раз |
| 3 | What time period does the receptionist ask about? / О каком периоде времени спрашивает администратор? | ✓ How long the problem has lasted / Сколько времени длится проблема · How long the visit will take / Сколько продлится приём · How long the trip to the clinic is / Сколько времени ехать до клиники |
| 4 | Which appointment time does the receptionist offer? / Какое время предлагает администратор? | Tomorrow at nine / Завтра в девять · ✓ Tomorrow at ten / Завтра в десять · Today at ten / Сегодня в десять |
| 5 | Which details does the receptionist need? / Какие данные нужны администратору? | ✓ Your name and birth date / Ваше имя и дата рождения · Your address and email / Ваш адрес и электронная почта · Your passport number and phone / Номер паспорта и телефон |
| 6 | What is the second detail the receptionist repeats? / Какую вторую деталь повторяет администратор? | Your phone number / Ваш номер телефона · ✓ Your birth date / Ваша дата рождения · Your postal address / Ваш почтовый адрес |
| 7 | What does the receptionist ask for here? / Что здесь просит администратор? | ✓ A contact number / Контактный номер · A social security number / Номер страховки · A street number / Номер дома |
| 8 | When should the patient arrive? / Когда пациенту нужно прийти? | ✓ A little earlier than the appointment / Немного раньше приёма · Exactly at the appointment time / Точно ко времени приёма · Half an hour early / За полчаса до приёма |

### Слушаю весь визит

- L1. Зачем пациент звонит в клинику? — ✓ Чтобы записаться на приём · Чтобы отменить приём · Чтобы узнать адрес клиники
- L2. Какая проблема у пациента? — ✓ Болит горло · Болит спина · Болит живот
- L3. На какое время записали пациента? — Сегодня в десять · ✓ Завтра в десять · Завтра в девять
- L4. Какие данные попросил администратор для записи? — ✓ Имя и дата рождения · Адрес и место работы · Номер паспорта и почта

### Словарь

| id | слово | вид | перевод | чтение | где звучит |
|---|---|---|---|---|---|
| v1 | rendez-vous | слово | приём; запись | ранде-ву | p1, A8 |
| v2 | mal à la gorge | связка | боль в горле | маль а ла горж | p2 |
| v3 | fièvre | слово | температура | фьевр | p2 |
| v4 | place | слово | свободное место | пляс | A4 |
| v5 | date de naissance | связка | дата рождения | дат дё нэсанс | A5, A6 |
| v6 | numéro de téléphone | связка | номер телефона | нюмэро дё телефон | A7, p6 |
| v7 | arriver | слово | приходить | арривэ | A8, p7 |
| v8 | plus lentement | связка | помедленнее | плю лантман | A6 |

### Находки в ответе модели (до починок)

| код | порог | адрес | что |
|---|---|---|---|
| `pronunciation.foreign_script` | **фатальная** | v6 | the reading «нюмэро дё телефoн» is spelled with letters of another writing: «o» |
| `pronunciation.script` | предупреждение | v6 | the reading «нюмэро дё телефoн» leaves the native script |
| `frame.native_alternatives` | предупреждение | p1 | «Я хотел(а) бы ___» writes alternatives inside the frame |
| `frame.no_end_punct` | предупреждение | p1 | the native «Я хотел(а) бы ___» ends with no mark |
| `frame.no_end_punct` | предупреждение | p2 | the native «У меня ___» ends with no mark |
| `frame.no_end_punct` | предупреждение | p3 | the native «Уже ___» ends with no mark |
| `frame.no_end_punct` | предупреждение | p4 | the native «___ мне подходит» ends with no mark |
| `frame.native_agreement` | предупреждение | p5 | «Какая ___ нужна?»: «какая», «нужна» agrees with the slot — it changes with the filler |
| `frame.no_end_punct` | предупреждение | p6 | the native «Мой номер — ___» ends with no mark |
| `frame.no_end_punct` | предупреждение | p7 | the native «Я приду ___» ends with no mark |
| `variant.longer` | предупреждение | B3 | the variant «Ça fait trois jours.» has 4 words, the line 3 |
| `options.form_mismatch` | **фатальная** | x4.check | the option «Завтра в десять» is a piece of the partner's line «У нас есть место завтра в десять часов.» |
| `options.form_mismatch` | **фатальная** | x6.check | the option «Ваша дата рождения» is a piece of the partner's line «Ваше имя и ваша дата рождения.» |
| `vocab.used_in_wrong` | предупреждение | v6 | «numéro de téléphone» is not in frame p6 or its fillers |
| `vocab.used_in_wrong` | предупреждение | v7 | «arriver» is not in frame p7 or its fillers |
| `vocab.used_in_wrong` | предупреждение | v8 | «plus lentement» is not in the partner's line of exchange 6 |
| `vocab.learner_share` | предупреждение | lesson | 3 of 8 items are in the learner's frames or fillers (at least half) |
| `native.gendered_past` | предупреждение | B1 | «Я хотел(а) бы записаться на приём.» says «хотел» about the learner while the learner's gender is unknown |
| `native.gendered_past` | предупреждение | p1 | «Я хотел(а) бы ___» says «хотел» about the learner while the learner's gender is unknown |

### Не проверено — нет ключей пакета (lang.pack_missing, не находка)

Кодов не проверено: родной 0, целевой 17. Контекст проверки: родной `ru`, целевой `fr`.

| код | сторона | язык | недостающие ключи |
|---|---|---|---|
| `exchange.second_question` | целевой | fr | sentence_ends |
| `frame.no_end_punct` | целевой | fr | sentence_ends |
| `frame.native_punct` | целевой | fr | sentence_ends |
| `frame.unresolved_pronoun` | целевой | fr | unresolved_pronouns, function_words |
| `filler.ungrammatical` | целевой | fr | sentence_ends, articles, article_sound, clause, seam_repeatable_words |
| `filler.is_clause` | целевой | fr | clause |
| `filler.article_seam` | целевой | fr | article_sound |
| `learner.restates_partner` | целевой | fr | sentence_ends |
| `rescue.new_fact` | целевой | fr | function_words, word_forms |
| `partner.two_questions` | целевой | fr | sentence_ends, second_question_pattern |
| `partner.too_long` | целевой | fr | sentence_ends |
| `partner.closer` | целевой | fr | closers |
| `check.verbatim` | целевой | fr | function_words, word_forms, number_pattern, sentence_ends |
| `check.about_learner` | целевой | fr | saying_verbs, function_words, word_forms |
| `check.listed_alternative_as_wrong` | целевой | fr | alternative_words, function_words, word_forms |
| `vocab.free_combination` | целевой | fr | ordinary_heads, everyday_words, function_words, word_forms |
| `vocab.everyday_word` | целевой | fr | everyday_words |

### Судья швов

Судья не звался: урок не прошёл порог.

### Находки с пакетами LANG-1 (повторная проверка, без вызовов)

Фатальных: 3 → 3 · предупреждений: 16 → 16 · не проверено кодов (родной/целевой): 0/17 → 0/0 · пакеты сейчас: be, de, en, es, fr, it, pl, ro, ru, uk

Коды: появились exchange.second_question×1, partner.too_long×1; ушли pronunciation.foreign_script×1, pronunciation.script×1.

| ± код | порог | адрес | что |
|---|---|---|---|
| + `exchange.second_question` | **фатальная** | x1 | the closing message of A «D'accord. C'est pour quel problème ?» ends with a question mark |
| + `frame.no_end_punct` | предупреждение | p2 | «J'ai ___» and the native «У меня ___» end with no mark |
| + `partner.too_long` | предупреждение | A8 | «C'est noté. Rendez-vous demain à dix heures. Arrivez dix minutes avant.» has 3 sentences (max 2) |
| − `pronunciation.foreign_script` | **фатальная** | v6 | the reading «нюмэро дё телефoн» is spelled with letters of another writing: «o» |
| − `pronunciation.script` | предупреждение | v6 | the reading «нюмэро дё телефoн» leaves the native script |
| − `frame.no_end_punct` | предупреждение | p2 | the native «У меня ___» ends with no mark |
