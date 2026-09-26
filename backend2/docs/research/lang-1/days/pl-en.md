# LANG-1 · Polski→English (pl→en, начальный)

Цель плана (слова ученика): «Zapisuję się do lekarza: od trzech dni boli mnie gardło i mam gorączkę. Muszę wybrać dogodny termin i wyjaśnić, co mi dolega»

Роль ученика в плане: Patient / Pacjent. Сцена 1: «Rejestracja» (Receptionist / Recepcjonista); сцена 2: «U lekarza» (Doctor / Lekarz).

> День 1 — урок, каким его получил бы ученик (прошедший порог, как записан в сцену; при `failed` — ответ модели как написан), прочитанный сервером: наполнение реплики найдено по её тексту. «Чтение» — как модель записала звучание целевого текста буквами родного. Находки — по ответу модели ДО починок, валидатором кода прогона в контексте боя этой пары. «Не проверено» — проверки, для которых у пакета языка нет ключей (`lang.pack_missing`, не находка). Цена — вызовы плана и дня по одному разу. Факты сюжета и роли код не проверяет.

Итог: **ready** · починок P2R: 2 (x1: exchange.second_question; x7.check: options.form_mismatch) · вызовов урока: 1 · план $0.0123 · 10.6 с · день $0.1145 (урок $0.0891 · починки $0.0236 · судья $0.0018) · из кэша 56 % входа · 55.8 с · всего $0.1268 · ученик: Pacjent · собеседник: Recepcjonista (женщина)

Промты: план `plan-builder-v2` · урок `lesson_day.v4.7` · починка `lesson_card_repair.v1.3` · судья `lesson_seam_judge.v1.1` · модель урока `gpt-5.4-2026-03-05` · попыток урока: 1 · в сцене записано $0.114483 (урок, починки и судья — сверка, не слагаемое)

### Сценарий диалога

| # | вид обмена | кто | реплика | перевод | чтение | каркас · наполнение |
|---|---|---|---|---|---|---|
| 1 | вопрос ученика | Pacjent (ученик) | I'd like to make an appointment. | Chcę umówić wizytę. | ajd lajk tu mejk en apojntment | p1 · an appointment |
| 1 | вопрос ученика | Recepcjonista (собеседник) | Of course. Please tell me the problem. | Oczywiście. Proszę powiedzieć, co się dzieje. |  |  |
| 2 | ответ | Recepcjonista (собеседник) | Is it something urgent today? | Czy to coś pilnego na dziś? |  |  |
| 2 | ответ | Pacjent (ученик) | I have a sore throat and fever. | Boli mnie gardło i mam gorączkę. | aj haww e sor trołt end fiwer | p2 · a sore throat and fever |
| 3 | ответ | Recepcjonista (собеседник) | We have today at four or tomorrow at nine. | Mamy dziś o czwartej albo jutro o dziewiątej. |  |  |
| 3 | ответ | Pacjent (ученик) | It's been for three days. | To trwa od trzech dni. | its bin for srii dejz | p3 · for three days |
| 4 | вопрос ученика | Pacjent (ученик) | Can I come today at four? | Czy mogę przyjść dziś o czwartej? | ken aj kam tudej et for | p4 · today at four |
| 4 | вопрос ученика | Recepcjonista (собеседник) | Yes, that time is still free. | Tak, ten termin jest jeszcze wolny. |  |  |
| 5 | ответ | Recepcjonista (собеседник) | I need your full name for the booking. | Potrzebuję twojego imienia i nazwiska do rezerwacji. |  |  |
| 5 | ответ | Pacjent (ученик) | My name is Jan Kowalski. | Nazywam się Jan Kowalski. | maj nejm iz იან kowalski | p5 · Jan Kowalski |
| 6 | ответ | Recepcjonista (собеседник) | Please bring your ID and arrive ten minutes early. | Proszę wziąć dokument tożsamości i przyjść dziesięć minut wcześniej. |  |  |
| 6 | ответ | Pacjent (ученик) | Okay, I'll bring my ID. | Dobrze, wezmę dowód. | okej, ajl bring maj aj-di | p6 · my ID |
| 7 | вопрос ученика | Pacjent (ученик) | Can I have the address? | Czy mogę dostać adres? | ken aj haww di adres | p7 · the address |
| 7 | вопрос ученика | Recepcjonista (собеседник) | Yes. It's 12 Green Street, near the pharmacy. | Tak. To Green Street 12, obok apteki. |  |  |
| 8 | ответ | Recepcjonista (собеседник) | You're booked for today at four with Dr. Lee. | Jesteś zapisany na dziś na czwartą do doktor Lee. |  |  |
| 8 | ответ | Pacjent (ученик) | Okay, see you today at four. | Dobrze, do zobaczenia dziś o czwartej. | okej, si ju tudej et for | p8 · today at four |

### Каркасы

| id | вид | каркас | на родном | чтение | наполнения (в диалоге — жирным) |
|---|---|---|---|---|---|
| p1 | вопрос ученика | I'd like to make ___ | Chcę umówić ___ | ajd lajk tu mejk ___ | **an appointment** / wizytę · a check-up / badanie kontrolne |
| p2 | ответ | I have ___ | Mam ___ | aj haww ___ | **a sore throat and fever** / ból gardła i gorączkę · a bad cough / silny kaszel · ear pain / ból ucha |
| p3 | ответ | It's been ___ | To trwa ___ | its bin ___ | **for three days** / od trzech dni · since yesterday / od wczoraj |
| p4 | вопрос ученика | Can I come ___ | Czy mogę przyjść ___ | ken aj kam ___ | **today at four** / dziś o czwartej · tomorrow at nine / jutro o dziewiątej |
| p5 | ответ | My name is ___ | Nazywam się ___ | maj nejm iz ___ | **Jan Kowalski** / Jan Kowalski · Anna Nowak / Anna Nowak |
| p6 | ответ | I'll bring ___ | Wezmę ___ | ajl bring ___ | **my ID** / dowód · my insurance card / kartę ubezpieczenia |
| p7 | вопрос ученика | Can I have ___ | Czy mogę dostać ___ | ken aj haww ___ | **the address** / adres · the phone number / numer telefonu |
| p8 | ответ | See you ___ | Do zobaczenia ___ | si ju ___ | **today at four** / dziś o czwartej · tomorrow morning / jutro rano |

### Наполнения

| каркас | наполнение | перевод | чтение | в диалоге | собранная фраза (родной) | судья швов |
|---|---|---|---|---|---|---|
| p1 | an appointment | wizytę | en apojntment | да | Chcę umówić wizytę | да |
| p1 | a check-up | badanie kontrolne | e czek-ap | — | Chcę umówić badanie kontrolne | да |
| p2 | a sore throat and fever | ból gardła i gorączkę | e sor trołt end fiwer | да | Mam ból gardła i gorączkę | да |
| p2 | a bad cough | silny kaszel | e bad kof | — | Mam silny kaszel | да |
| p2 | ear pain | ból ucha | ir pejn | — | Mam ból ucha | да |
| p3 | for three days | od trzech dni | for srii dejz | да | To trwa od trzech dni | да |
| p3 | since yesterday | od wczoraj | sins jesterdej | — | To trwa od wczoraj | да |
| p4 | today at four | dziś o czwartej | tudej et for | да | Czy mogę przyjść dziś o czwartej | да |
| p4 | tomorrow at nine | jutro o dziewiątej | tomerou et najn | — | Czy mogę przyjść jutro o dziewiątej | да |
| p5 | Jan Kowalski | Jan Kowalski | jan kowalski | да | Nazywam się Jan Kowalski | да |
| p5 | Anna Nowak | Anna Nowak | anna nowak | — | Nazywam się Anna Nowak | да |
| p6 | my ID | dowód | maj aj-di | да | Wezmę dowód | да |
| p6 | my insurance card | kartę ubezpieczenia | maj inszurens kard | — | Wezmę kartę ubezpieczenia | да |
| p7 | the address | adres | di adres | да | Czy mogę dostać adres | да |
| p7 | the phone number | numer telefonu | di foun namber | — | Czy mogę dostać numer telefonu | да |
| p8 | today at four | dziś o czwartej | tudej et for | да | Do zobaczenia dziś o czwartej | да |
| p8 | tomorrow morning | jutro rano | tomerou morning | — | Do zobaczenia jutro rano | да |

### Проверки обменов

| # | вопрос | варианты (✓ — верный) |
|---|---|---|
| 1 | What does the receptionist ask about? / O co pyta recepcjonista? | ✓ The reason for the visit / Powód wizyty · The patient's address / Adres pacjenta · The doctor's name / Nazwisko lekarza |
| 2 | What does the receptionist want to know? / Czego chce się dowiedzieć recepcjonista? | ✓ Whether it needs same-day help / Czy potrzebna jest pomoc jeszcze dziś · Whether the patient has insurance / Czy pacjent ma ubezpieczenie · Whether the patient came before / Czy pacjent był tu wcześniej |
| 3 | Which time is offered for tomorrow? / Jaki termin jest proponowany na jutro? | ✓ In the morning at nine / Rano o dziewiątej · At noon / W południe · In the evening at seven / Wieczorem o siódmej |
| 4 | What does the receptionist say about four o'clock? / Co recepcjonista mówi o czwartej? | It has already been taken / Ten termin jest już zajęty · ✓ It is available / Ten termin jest dostępny · It may change later / Ten termin może się później zmienić |
| 5 | Which detail does the receptionist ask for? / O jaki szczegół pyta recepcjonista? | The patient's phone number / Numer telefonu pacjenta · ✓ The patient's full name / Imię i nazwisko pacjenta · The patient's date of birth / Data urodzenia pacjenta |
| 6 | How early should the patient arrive? / Jak wcześnie pacjent ma przyjść? | A quarter of an hour before / Piętnaście minut wcześniej · ✓ Ten minutes before the visit / Dziesięć minut przed wizytą · Right at the appointment time / Dokładnie na godzinę wizyty |
| 7 | What landmark does the receptionist mention? / Jaki punkt orientacyjny podaje recepcjonista? | ✓ A place close to a drugstore / Miejsce blisko apteki · Across from a bank / Naprzeciwko banku · Beside a bus station / Przy dworcu autobusowym |
| 8 | Who is the appointment with? / Z kim jest umówiona wizyta? | With Nurse Brown / Z pielęgniarką Brown · ✓ With Dr. Lee / Z doktor Lee · With Dr. Smith / Z doktorem Smithem |

### Слушаю весь визит

- L1. Po co pacjent dzwoni lub podchodzi do recepcji? — Żeby odebrać wyniki badań · ✓ Żeby umówić wizytę · Żeby zapytać o godziny otwarcia
- L2. Jakie objawy podaje pacjent? — ✓ Ból gardła i gorączkę · Ból brzucha i nudności · Kaszel i katar
- L3. Który termin wybiera pacjent? — ✓ Dziś o czwartej · Jutro o dziewiątej · W piątek po południu
- L4. Co recepcjonista prosi zabrać? — Receptę · ✓ Dokument tożsamości · Wyniki badań

### Словарь

| id | слово | вид | перевод | чтение | где звучит |
|---|---|---|---|---|---|
| v1 | appointment | слово | wizyta | apojntment | p1 |
| v2 | sore throat | связка | ból gardła | sor trołt | p2 |
| v3 | fever | слово | gorączka | fiwer | p2 |
| v4 | urgent | слово | pilny | erdżent | A2 |
| v5 | full name | связка | imię i nazwisko | ful nejm | A5 |
| v6 | ID | слово | dowód tożsamości | aj-di | A6, p6 |
| v7 | arrive early | связка | przyjść wcześniej | erajw erli | A6 |
| v8 | address | слово | adres | adres | p7, A7 |

### Находки в ответе модели (до починок)

| код | порог | адрес | что |
|---|---|---|---|
| `exchange.second_question` | **фатальная** | x1 | the closing message of A «Of course. What seems to be the problem?» ends with a question mark |
| `frame.no_end_punct` | предупреждение | p1 | «I'd like to make ___» ends with no mark |
| `frame.no_end_punct` | предупреждение | p2 | «I have ___» ends with no mark |
| `frame.no_end_punct` | предупреждение | p3 | «It's been ___» ends with no mark |
| `frame.no_end_punct` | предупреждение | p4 | «Can I come ___» ends with no mark |
| `frame.no_end_punct` | предупреждение | p5 | «My name is ___» ends with no mark |
| `frame.no_end_punct` | предупреждение | p6 | «I'll bring ___» ends with no mark |
| `frame.no_end_punct` | предупреждение | p7 | «Can I have ___» ends with no mark |
| `frame.no_end_punct` | предупреждение | p8 | «See you ___» ends with no mark |
| `options.form_mismatch` | **фатальная** | x7.check | the option «Obok apteki» is a piece of the partner's line «Tak. To Green Street 12, obok apteki.» |
| `vocab.abbreviation` | предупреждение | v6 | «ID» is an abbreviation or an acronym — a word of the day only when the learner's language has an everyday word for it |
| `vocab.used_in_wrong` | предупреждение | v7 | «arrive early» is not in the partner's line of exchange 6 |
| `vocab.used_in_wrong` | предупреждение | v8 | «address» is not in the partner's line of exchange 7 |

### Не проверено — нет ключей пакета (lang.pack_missing, не находка)

Кодов не проверено: родной 9, целевой 0. Контекст проверки: родной `pl`, целевой `en`.

| код | сторона | язык | недостающие ключи |
|---|---|---|---|
| `pronunciation.script` | родной | pl | script |
| `pronunciation.foreign_script` | родной | pl | script_letters |
| `frame.no_end_punct` | родной | pl | sentence_ends |
| `frame.native_punct` | родной | pl | sentence_ends |
| `frame.native_agreement` | родной | pl | agreement |
| `listening.same_exchange` | родной | pl | function_words, word_forms |
| `listening.no_learner_value` | родной | pl | function_words, word_forms |
| `listening.distractor_not_filler` | родной | pl | function_words, word_forms, number_pattern, time_pattern |
| `native.gendered_past` | родной | pl | gendered_past_pattern |

### Судья швов

Предложений: 17 · с вердиктом: 17 · «нет»: 0 · `lesson_seam_judge.v1.1` · $0.0018

### Находки с пакетами LANG-1 (повторная проверка, без вызовов)

Фатальных: 2 → 3 · предупреждений: 11 → 12 · не проверено кодов (родной/целевой): 9/0 → 0/0 · пакеты сейчас: be, de, en, es, fr, it, pl, ro, ru, uk

Коды: появились pronunciation.foreign_script×1, pronunciation.script×1.

| ± код | порог | адрес | что |
|---|---|---|---|
| + `pronunciation.foreign_script` | **фатальная** | B5 | the reading «maj nejm iz იან kowalski» is spelled with letters of another writing: «ი», «ა», «ნ» |
| + `pronunciation.script` | предупреждение | B5 | the reading «maj nejm iz იან kowalski» leaves the native script |
| + `frame.no_end_punct` | предупреждение | p1 | «I'd like to make ___» and the native «Chcę umówić ___» end with no mark |
| + `frame.no_end_punct` | предупреждение | p2 | «I have ___» and the native «Mam ___» end with no mark |
| + `frame.no_end_punct` | предупреждение | p3 | «It's been ___» and the native «To trwa ___» end with no mark |
| + `frame.no_end_punct` | предупреждение | p4 | «Can I come ___» and the native «Czy mogę przyjść ___» end with no mark |
| + `frame.no_end_punct` | предупреждение | p5 | «My name is ___» and the native «Nazywam się ___» end with no mark |
| + `frame.no_end_punct` | предупреждение | p6 | «I'll bring ___» and the native «Wezmę ___» end with no mark |
| + `frame.no_end_punct` | предупреждение | p7 | «Can I have ___» and the native «Czy mogę dostać ___» end with no mark |
| + `frame.no_end_punct` | предупреждение | p8 | «See you ___» and the native «Do zobaczenia ___» end with no mark |
| − `frame.no_end_punct` | предупреждение | p1 | «I'd like to make ___» ends with no mark |
| − `frame.no_end_punct` | предупреждение | p2 | «I have ___» ends with no mark |
| − `frame.no_end_punct` | предупреждение | p3 | «It's been ___» ends with no mark |
| − `frame.no_end_punct` | предупреждение | p4 | «Can I come ___» ends with no mark |
| − `frame.no_end_punct` | предупреждение | p5 | «My name is ___» ends with no mark |
| − `frame.no_end_punct` | предупреждение | p6 | «I'll bring ___» ends with no mark |
| − `frame.no_end_punct` | предупреждение | p7 | «Can I have ___» ends with no mark |
| − `frame.no_end_punct` | предупреждение | p8 | «See you ___» ends with no mark |
