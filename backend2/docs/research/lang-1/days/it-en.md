# LANG-1 · Italiano→English (it→en, начальный)

Цель плана (слова ученика): «Prendo un appuntamento dal medico: da tre giorni ho mal di gola e la febbre. Devo scegliere un orario comodo e spiegare cosa ho»

Роль ученика в плане: Patient / Paziente. Сцена 1: «Prenotazione» (Receptionist / Addetto alla reception); сцена 2: «Visita medica» (Doctor / Medico).

> День 1 — урок, каким его получил бы ученик (прошедший порог, как записан в сцену; при `failed` — ответ модели как написан), прочитанный сервером: наполнение реплики найдено по её тексту. «Чтение» — как модель записала звучание целевого текста буквами родного. Находки — по ответу модели ДО починок, валидатором кода прогона в контексте боя этой пары. «Не проверено» — проверки, для которых у пакета языка нет ключей (`lang.pack_missing`, не находка). Цена — вызовы плана и дня по одному разу. Факты сюжета и роли код не проверяет.

Итог: **ready** · починок P2R: 1 (x1: exchange.second_question) · вызовов урока: 1 · план $0.0122 · 10.2 с · день $0.1023 (урок $0.0867 · починки $0.0139 · судья $0.0016) · из кэша 68 % входа · 55.3 с · всего $0.1145 · ученик: Paziente · собеседник: Addetto alla reception (женщина)

Промты: план `plan-builder-v2` · урок `lesson_day.v4.7` · починка `lesson_card_repair.v1.3` · судья `lesson_seam_judge.v1.1` · модель урока `gpt-5.4-2026-03-05` · попыток урока: 1 · в сцене записано $0.102270 (урок, починки и судья — сверка, не слагаемое)

### Сценарий диалога

| # | вид обмена | кто | реплика | перевод | чтение | каркас · наполнение |
|---|---|---|---|---|---|---|
| 1 | вопрос ученика | Paziente (ученик) | I need a doctor's appointment. | Ho bisogno di un appuntamento dal medico. | ai niid a dottorz appòintment | p1 · a doctor's appointment |
| 1 | вопрос ученика | Addetto alla reception (собеседник) | Of course. Please tell me the problem. | Certo. Mi dica il problema. |  |  |
| 2 | ответ | Addetto alla reception (собеседник) | Can you tell me your symptoms briefly? | Può dirmi brevemente i sintomi? |  |  |
| 2 | ответ | Paziente (ученик) | I have a sore throat and fever. | Ho mal di gola e la febbre. | ai hav a sor thròut end fìiver | p2 · a sore throat and fever |
| 3 | ответ | Addetto alla reception (собеседник) | How long have you had these symptoms? | Da quanto tempo ha questi sintomi? |  |  |
| 3 | ответ | Paziente (ученик) | I have had them for three days. | Li ho da tre giorni. | ai hav hed dem for thrii deiz | p3 · for three days |
| 4 | вопрос ученика | Paziente (ученик) | What available times do you have? | Che orari disponibili avete? | uòt avèilabol taimz du iu hav | p4 · available times |
| 4 | вопрос ученика | Addetto alla reception (собеседник) | We have 10 a.m. today or 3 p.m. tomorrow. | Abbiamo le 10 di oggi o le 3 di domani. |  |  |
| 5 | вопрос ученика | Paziente (ученик) | Can I take 3 p.m. tomorrow? | Posso prendere le 3 di domani? | ken ai teik thrii pii em tomòrou | p5 · 3 p.m. tomorrow |
| 5 | вопрос ученика | Addetto alla reception (собеседник) | Yes, that slot is still free. | Sì, quell'orario è ancora libero. |  |  |
| 6 | ответ | Addetto alla reception (собеседник) | Please arrive ten minutes early for registration. | Per favore, arrivi dieci minuti prima per la registrazione. |  |  |
| 6 | ответ | Paziente (ученик) | I'll arrive ten minutes early. | Arriverò dieci minuti prima. | ail aràiv ten mìnits èrli | p6 · ten minutes early |
| 7 | переспрос | Paziente (ученик) | Could you repeat that, please? | Può ripeterlo, per favore? | kud iu ripìit det pliiz | — |
| 7 | переспрос | Addetto alla reception (собеседник) | Please come ten minutes before your appointment. | Per favore, venga dieci minuti prima del suo appuntamento. |  |  |
| 8 | ответ | Addetto alla reception (собеседник) | You're booked for tomorrow at 3 p.m. | È prenotato per domani alle 3. |  |  |
| 8 | ответ | Paziente (ученик) | Okay, tomorrow at 3 p.m. works. | Va bene, domani alle 3 va bene. | okèi, tomòrou at thrii pii em uorks | p7 · tomorrow at 3 p.m. |

### Каркасы

| id | вид | каркас | на родном | чтение | наполнения (в диалоге — жирным) |
|---|---|---|---|---|---|
| p1 | вопрос ученика | I need ___. | Ho bisogno di ___. | ai niid ___ | **a doctor's appointment** / un appuntamento dal medico · an urgent visit / una visita urgente |
| p2 | ответ | I have ___. | Ho ___. | ai hav ___ | **a sore throat and fever** / mal di gola e la febbre · a bad cough / una brutta tosse · an earache / mal d'orecchio |
| p3 | ответ | I have had them ___. | Li ho ___. | ai hav hed dem ___ | **for three days** / da tre giorni · since yesterday / da ieri |
| p4 | вопрос ученика | What ___ do you have? | Che ___ avete? | uòt ___ du iu hav | **available times** / orari disponibili · appointments this week / appuntamenti questa settimana |
| p5 | вопрос ученика | Can I take ___? | Posso prendere ___? | ken ai teik ___ | **3 p.m. tomorrow** / le 3 di domani · 10 a.m. today / le 10 di oggi |
| p6 | ответ | I'll arrive ___. | Arriverò ___. | ail aràiv ___ | **ten minutes early** / dieci minuti prima · on time / puntuale |
| p7 | ответ | ___ works. | ___ va bene. | ___ uorks | **tomorrow at 3 p.m.** / domani alle 3 · Friday morning / venerdì mattina |

### Наполнения

| каркас | наполнение | перевод | чтение | в диалоге | собранная фраза (родной) | судья швов |
|---|---|---|---|---|---|---|
| p1 | a doctor's appointment | un appuntamento dal medico | a dottorz appòintment | да | Ho bisogno di un appuntamento dal medico. | да |
| p1 | an urgent visit | una visita urgente | an èrdgent vìzit | — | Ho bisogno di una visita urgente. | да |
| p2 | a sore throat and fever | mal di gola e la febbre | a sor thròut end fìiver | да | Ho mal di gola e la febbre. | да |
| p2 | a bad cough | una brutta tosse | a bed kof | — | Ho una brutta tosse. | да |
| p2 | an earache | mal d'orecchio | an ììreik | — | Ho mal d'orecchio. | да |
| p3 | for three days | da tre giorni | for thrii deiz | да | Li ho da tre giorni. | да |
| p3 | since yesterday | da ieri | sins ièsterdei | — | Li ho da ieri. | да |
| p4 | available times | orari disponibili | avèilabol taimz | да | Che orari disponibili avete? | **нет** |
| p4 | appointments this week | appuntamenti questa settimana | appòintments dhis uiik | — | Che appuntamenti questa settimana avete? | **нет** |
| p5 | 3 p.m. tomorrow | le 3 di domani | thrii pii em tomòrou | да | Posso prendere le 3 di domani? | да |
| p5 | 10 a.m. today | le 10 di oggi | ten ei em tudei | — | Posso prendere le 10 di oggi? | да |
| p6 | ten minutes early | dieci minuti prima | ten mìnits èrli | да | Arriverò dieci minuti prima. | да |
| p6 | on time | puntuale | on taim | — | Arriverò puntuale. | да |
| p7 | tomorrow at 3 p.m. | domani alle 3 | tomòrou at thrii pii em | да | domani alle 3 va bene. | да |
| p7 | Friday morning | venerdì mattina | fràidei mòrning | — | venerdì mattina va bene. | да |

### Проверки обменов

| # | вопрос | варианты (✓ — верный) |
|---|---|---|
| 1 | What does the receptionist ask about? / Che cosa chiede l'addetta? | ✓ The reason for the visit / Il motivo della visita · The patient's address / L'indirizzo del paziente · The doctor's name / Il nome del medico |
| 2 | What does the receptionist want the patient to give? / Che cosa vuole che dica il paziente? | ✓ A short description of the symptoms / Una breve descrizione dei sintomi · A list of medicines / Un elenco di medicine · The insurance number / Il numero dell'assicurazione |
| 3 | What time period does the receptionist ask about? / Su quale periodo di tempo chiede l'addetta? | ✓ How long the symptoms have lasted / Da quanto durano i sintomi · How long the visit will take / Quanto durerà la visita · How long the patient waited outside / Da quanto il paziente aspetta fuori |
| 4 | Which two appointment options does the receptionist offer? / Quali due opzioni di appuntamento offre l'addetta? | ✓ This morning at ten or tomorrow afternoon at three / Stamattina alle dieci oppure domani pomeriggio alle tre · Today at noon or tomorrow at five / Oggi a mezzogiorno oppure domani alle cinque · Tomorrow morning at ten or Friday at three / Domani mattina alle dieci oppure venerdì alle tre |
| 5 | What does the receptionist say about that time? / Che cosa dice l'addetta su quell'orario? | It has already been taken / È già stato preso · ✓ It is still available / È ancora disponibile · It is only for emergencies / È solo per le urgenze |
| 6 | How early should the patient come? / Con quanto anticipo deve arrivare il paziente? | A quarter of an hour before / Un quarto d'ora prima · ✓ Ten minutes before the visit / Dieci minuti prima della visita · Right at the appointment time / Esattamente all'ora dell'appuntamento |
| 7 | Before what should the patient arrive early? / Prima di che cosa deve arrivare in anticipo il paziente? | Before the clinic opens / Prima che apra la clinica · Before the doctor's lunch break / Prima della pausa pranzo del medico · ✓ Before the scheduled visit / Prima della visita fissata |
| 8 | When is the appointment confirmed for? / Per quando viene confermato l'appuntamento? | ✓ Tomorrow afternoon at three / Domani pomeriggio alle tre · Today morning at ten / Oggi mattina alle dieci · The day after tomorrow at three / Dopodomani alle tre |

### Слушаю весь визит

- L1. Perché il paziente vuole vedere il medico? — ✓ Ha mal di gola e la febbre · Ha male a una gamba · Deve ritirare una ricetta
- L2. Da quanto tempo il paziente ha questi sintomi? — Da ieri · ✓ Da tre giorni · Da una settimana
- L3. Quale orario sceglie il paziente? — Oggi alle 10 · ✓ Domani alle 3 · Venerdì mattina
- L4. Con quanto anticipo deve arrivare il paziente? — ✓ Dieci minuti prima · Mezz'ora prima · All'ora esatta

### Словарь

| id | слово | вид | перевод | чтение | где звучит |
|---|---|---|---|---|---|
| v1 | appointment | слово | appuntamento | appòintment | p1 |
| v2 | sore throat | связка | mal di gola | sor thròut | p2 |
| v3 | fever | слово | febbre | fìiver | p2 |
| v4 | symptoms | слово | sintomi | sìmptoms | A2, A3 |
| v5 | available | слово | disponibile | avèilabol | p4 |
| v6 | slot | слово | orario disponibile | slot | A5 |
| v7 | registration | слово | registrazione | redgistrèishon | A6 |
| v8 | arrive early | связка | arrivare prima | aràiv èrli | p6 |

### Находки в ответе модели (до починок)

| код | порог | адрес | что |
|---|---|---|---|
| `exchange.second_question` | **фатальная** | x1 | the closing message of A «Of course. What seems to be the problem?» ends with a question mark |
| `learner.restates_partner` | предупреждение | B8 | «Okay, tomorrow at 3 p.m. works.» repeats 5 of 8 words of the partner's «You're booked for tomorrow at 3 p.m.» (tomorrow, at, 3, p, m) |
| `rescue.new_fact` | предупреждение | A7 | the repeat says «come», «appointment», which exchange 6 did not |
| `check.about_learner` | предупреждение | x2.check | «What does the receptionist want the patient to give?» → «A short description of the symptoms» is about the learner's line, not the partner's |
| `check.verbatim` | предупреждение | x3.check | the right option «How long the symptoms have lasted» repeats «how long» of the partner's line |
| `check.verbatim` | предупреждение | x5.check | the right option «It is still available» repeats «is still» of the partner's line |
| `vocab.used_in_wrong` | предупреждение | v8 | «arrive early» is not in frame p6 or its fillers |

### Не проверено — нет ключей пакета (lang.pack_missing, не находка)

Кодов не проверено: родной 9, целевой 0. Контекст проверки: родной `it`, целевой `en`.

| код | сторона | язык | недостающие ключи |
|---|---|---|---|
| `pronunciation.script` | родной | it | script |
| `pronunciation.foreign_script` | родной | it | script_letters |
| `frame.no_end_punct` | родной | it | sentence_ends |
| `frame.native_punct` | родной | it | sentence_ends |
| `frame.native_agreement` | родной | it | agreement |
| `listening.same_exchange` | родной | it | function_words, word_forms |
| `listening.no_learner_value` | родной | it | function_words, word_forms |
| `listening.distractor_not_filler` | родной | it | function_words, word_forms, number_pattern, time_pattern |
| `native.gendered_past` | родной | it | gendered_past_pattern |

### Судья швов

Предложений: 15 · с вердиктом: 15 · «нет»: 2 · `lesson_seam_judge.v1.1` · $0.0016

- p4.f1: «Che orari disponibili avete?» — «Che ___ avete?» + «orari disponibili»
- p4.f2: «Che appuntamenti questa settimana avete?» — «Che ___ avete?» + «appuntamenti questa settimana»

### Находки с пакетами LANG-1 (повторная проверка, без вызовов)

Фатальных: 1 → 1 · предупреждений: 6 → 6 · не проверено кодов (родной/целевой): 9/0 → 0/0 · пакеты сейчас: be, de, en, es, fr, it, pl, ro, ru, uk

Коды: без изменений.
