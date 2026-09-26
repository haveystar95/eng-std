# LANG-1 · Polski→English (pl→en, начальный)

Цель плана (слова ученика): «Zapisuję się do lekarza: od trzech dni boli mnie gardło i mam gorączkę. Muszę wybrać dogodny termin i wyjaśnić, co mi dolega»

Роль ученика в плане: Patient / Pacjent. Сцена 1: «Rejestracja» (Receptionist / Recepcjonista); сцена 2: «U lekarza» (Doctor / Lekarz).

> День 1 — урок, каким его получил бы ученик (прошедший порог, как записан в сцену; при `failed` — ответ модели как написан), прочитанный сервером: наполнение реплики найдено по её тексту. «Чтение» — как модель записала звучание целевого текста буквами родного. Находки — по ответу модели ДО починок, валидатором кода прогона в контексте боя этой пары. «Не проверено» — проверки, для которых у пакета языка нет ключей (`lang.pack_missing`, не находка). Цена — вызовы плана и дня по одному разу. Факты сюжета и роли код не проверяет.

Итог: **ready** · починок P2R: 2 (x1: exchange.second_question; B5: line.ne_frame) · вызовов урока: 1 · план $0.0000 · 0.0 с · день $0.1154 (урок $0.0798 · починки $0.0338 · судья $0.0018) · из кэша 0 % входа · 33.2 с · всего $0.1154 · ученик: Pacjent · собеседник: Recepcjonista (женщина)

Промты: план `plan-builder-v2` · урок `lesson_day.v4.9` · починка `lesson_card_repair.v1.3` · судья `lesson_seam_judge.v1.1` · модель урока `gpt-5.4-2026-03-05` · попыток урока: 1 · в сцене записано $0.115444 (урок, починки и судья — сверка, не слагаемое)

### Сценарий диалога

| # | вид обмена | кто | реплика | перевод | чтение | каркас · наполнение |
|---|---|---|---|---|---|---|
| 1 | вопрос ученика | Pacjent (ученик) | I'd like a doctor's appointment. | Chcę umówić wizytę u lekarza. | ajd lajk e dokters epojntment | p1 · a doctor's appointment |
| 1 | вопрос ученика | Recepcjonista (собеседник) | Of course. Please tell me the problem. | Oczywiście. Proszę powiedzieć, co się dzieje. |  |  |
| 2 | ответ | Recepcjonista (собеседник) | Please tell me your symptoms. | Proszę powiedzieć, jakie są objawy. |  |  |
| 2 | ответ | Pacjent (ученик) | I have a sore throat and fever. | Boli mnie gardło i mam gorączkę. | aj haw e sor throt end fiwer | p2 · a sore throat and fever |
| 3 | ответ | Recepcjonista (собеседник) | How long have you had this? | Jak długo to trwa? |  |  |
| 3 | ответ | Pacjent (ученик) | It's been for three days. | To trwa od trzech dni. | its bin for thri dejs | p3 · for three days |
| 4 | ответ | Recepcjonista (собеседник) | I have a slot today at four and tomorrow at nine. | Mam termin dziś o czwartej i jutro o dziewiątej. |  |  |
| 4 | ответ | Pacjent (ученик) | Today at four works for me. | Dziś o czwartej mi pasuje. | tudej et for works for mi | p4 · today at four |
| 5 | вопрос ученика | Pacjent (ученик) | Do you need my name? | Czy potrzebuje pani mojego imienia i nazwiska? | du ju nid maj nejm | p5 · my name |
| 5 | вопрос ученика | Recepcjonista (собеседник) | Yes, I need your full name, please. | Tak, poproszę pani pełne imię i nazwisko. |  |  |
| 6 | ответ | Recepcjonista (собеседник) | I also need a phone number for the booking. | Potrzebuję też numeru telefonu do rezerwacji. |  |  |
| 6 | ответ | Pacjent (ученик) | Here is my phone number. | Oto mój numer telefonu. | hir iz maj foun namber | p6 · phone number |
| 7 | ответ | Recepcjonista (собеседник) | Your appointment is today at four with Dr. Brown. | Pani wizyta jest dziś o czwartej u doktora Browna. |  |  |
| 7 | ответ | Pacjent (ученик) | My appointment is today at four. | Moją wizytę mam dziś o czwartej. | maj epojntment iz tudej et for | p7 · today at four |
| 8 | вопрос ученика | Pacjent (ученик) | Do you need anything else? | Czy potrzeba jeszcze czegoś? | du ju nid enithing els | p8 · anything else |
| 8 | вопрос ученика | Recepcjonista (собеседник) | No, that's all. Please come ten minutes early. | Nie, to wszystko. Proszę przyjść dziesięć minut wcześniej. |  |  |

### Каркасы

| id | вид | каркас | на родном | чтение | наполнения (в диалоге — жирным) |
|---|---|---|---|---|---|
| p1 | вопрос ученика | I'd like ___ | Chcę ___ | ajd lajk ___ | **a doctor's appointment** / umówić wizytę u lekarza · an appointment this week / umówić wizytę w tym tygodniu |
| p2 | ответ | I have ___ | Mam ___ | aj haw ___ | **a sore throat and fever** / ból gardła i gorączkę · a cough and fever / kaszel i gorączkę · a headache / ból głowy |
| p3 | ответ | It's been ___ | To trwa ___ | its bin ___ | **for three days** / od trzech dni · since yesterday / od wczoraj |
| p4 | ответ | ___ works for me | ___ mi pasuje | ___ works for mi | **today at four** / dziś o czwartej · tomorrow at nine / jutro o dziewiątej |
| p5 | вопрос ученика | Do you need ___? | Czy potrzebuje pani ___? | du ju nid ___ | **my name** / mojego imienia i nazwiska · my ID / mojego dowodu |
| p6 | ответ | Here is my ___ | Oto ___ | hir iz maj ___ | **phone number** / mój numer telefonu · ID card / mój dowód |
| p7 | ответ | My appointment is ___ | Wizytę mam ___ | maj epojntment iz ___ | **today at four** / dziś o czwartej · tomorrow at nine / jutro o dziewiątej |
| p8 | вопрос ученика | Do you need ___? | Czy potrzeba ___? | du ju nid ___ | **anything else** / jeszcze czegoś · my email / mojego maila |

### Наполнения

| каркас | наполнение | перевод | чтение | в диалоге | собранная фраза (родной) | судья швов |
|---|---|---|---|---|---|---|
| p1 | a doctor's appointment | umówić wizytę u lekarza | e dokters epojntment | да | Chcę umówić wizytę u lekarza | да |
| p1 | an appointment this week | umówić wizytę w tym tygodniu | en epojntment dys łik | — | Chcę umówić wizytę w tym tygodniu | да |
| p2 | a sore throat and fever | ból gardła i gorączkę | e sor throt end fiwer | да | Mam ból gardła i gorączkę | да |
| p2 | a cough and fever | kaszel i gorączkę | e kof end fiwer | — | Mam kaszel i gorączkę | да |
| p2 | a headache | ból głowy | e hedejk | — | Mam ból głowy | да |
| p3 | for three days | od trzech dni | for thri dejs | да | To trwa od trzech dni | да |
| p3 | since yesterday | od wczoraj | sins jesterdej | — | To trwa od wczoraj | да |
| p4 | today at four | dziś o czwartej | tudej et for | да | dziś o czwartej mi pasuje | да |
| p4 | tomorrow at nine | jutro o dziewiątej | tumoro et nayn | — | jutro o dziewiątej mi pasuje | да |
| p5 | my name | mojego imienia i nazwiska | maj nejm | да | Czy potrzebuje pani mojego imienia i nazwiska? | да |
| p5 | my ID | mojego dowodu | maj aj di | — | Czy potrzebuje pani mojego dowodu? | да |
| p6 | phone number | mój numer telefonu | foun namber | да | Oto mój numer telefonu | да |
| p6 | ID card | mój dowód | aj di kard | — | Oto mój dowód | да |
| p7 | today at four | dziś o czwartej | tudej et for | да | Wizytę mam dziś o czwartej | да |
| p7 | tomorrow at nine | jutro o dziewiątej | tumoro et nayn | — | Wizytę mam jutro o dziewiątej | да |
| p8 | anything else | jeszcze czegoś | enithing els | да | Czy potrzeba jeszcze czegoś? | да |
| p8 | my email | mojego maila | maj imejl | — | Czy potrzeba mojego maila? | да |

### Проверки обменов

| # | вопрос | варианты (✓ — верный) |
|---|---|---|
| 1 | What does the receptionist ask about? / O co pyta recepcjonistka? | ✓ The reason for the visit / Powód wizyty · The patient's address / Adres pacjenta · The payment method / Sposób płatności |
| 2 | What does the receptionist ask the patient to describe? / Co recepcjonistka prosi opisać? | Past medicines / Leki brane wcześniej · ✓ Current symptoms / Obecne objawy · Work schedule / Grafik pracy |
| 3 | What does the receptionist want to know? / Czego chce się dowiedzieć recepcjonistka? | How severe it is / Jak bardzo to jest nasilone · ✓ How long it has lasted / Jak długo to trwa · Who the doctor is / Kim jest lekarz |
| 4 | Which time is available today? / Jaki termin jest dostępny dzisiaj? | At six in the evening / O szóstej wieczorem · ✓ At four in the afternoon / O czwartej po południu · At nine in the morning / O dziewiątej rano |
| 5 | What personal detail does the receptionist ask for? / O jaką daną osobową prosi recepcjonistka? | ✓ Full name / Pełne imię i nazwisko · Home address / Adres domowy · Date of birth / Data urodzenia |
| 6 | Why does the receptionist need the phone number? / Po co recepcjonistce numer telefonu? | ✓ For the booking record / Do rezerwacji · For insurance only / Tylko do ubezpieczenia · For the pharmacy / Dla apteki |
| 7 | Who is the appointment with? / Do kogo jest ta wizyta? | With Dr. Green / Do doktora Greena · ✓ With Dr. Brown / Do doktora Browna · With a nurse / Do pielęgniarki |
| 8 | When should the patient arrive? / Kiedy pacjent powinien przyjść? | A quarter of an hour before / Piętnaście minut wcześniej · Exactly on time / Dokładnie na czas · ✓ Ten minutes before / Dziesięć minut wcześniej |

### Слушаю весь визит

- L1. Na kiedy pacjent wybiera termin wizyty? — ✓ Dziś na czwartą · Jutro na dziewiątą · Dziś na szóstą
- L2. Jakie objawy podaje pacjent? — Kaszel i ból głowy · ✓ Ból gardła i gorączkę · Ból pleców i katar
- L3. Jakiej informacji potrzebuje recepcjonistka do rezerwacji? — ✓ Numeru telefonu · Adresu domowego · Numeru paszportu
- L4. Co jeszcze mówi recepcjonistka na końcu? — ✓ Żeby przyjść dziesięć minut wcześniej · Żeby nic nie jeść przed wizytą · Żeby zabrać wyniki badań

### Словарь

| id | слово | вид | перевод | чтение | где звучит |
|---|---|---|---|---|---|
| v1 | appointment | слово | wizyta | epojntment | p1, p7 |
| v2 | sore throat | связка | ból gardła | sor throt | p2 |
| v3 | fever | слово | gorączka | fiwer | p2 |
| v4 | symptoms | слово | objawy | simptoms | A2 |
| v5 | slot | слово | wolny termin | slot | A4 |
| v6 | full name | связка | pełne imię i nazwisko | ful nejm | A5 |
| v7 | phone number | связка | numer telefonu | foun namber | p6, A6 |
| v8 | early | слово | wcześniej | erli | A8 |

### Находки в ответе модели (до починок)

| код | порог | адрес | что |
|---|---|---|---|
| `exchange.second_question` | **фатальная** | x1 | the closing message of A «Of course. What seems to be the problem?» ends with a question mark |
| `frame.no_end_punct` | предупреждение | p1 | «I'd like ___» and the native «Chcę ___» end with no mark |
| `frame.no_end_punct` | предупреждение | p2 | «I have ___» and the native «Mam ___» end with no mark |
| `frame.no_end_punct` | предупреждение | p3 | «It's been ___» and the native «To trwa ___» end with no mark |
| `frame.no_end_punct` | предупреждение | p4 | «___ works for me» and the native «___ mi pasuje» end with no mark |
| `frame.no_end_punct` | предупреждение | p6 | «Here is my ___» and the native «Oto ___» end with no mark |
| `frame.no_end_punct` | предупреждение | p7 | «My appointment is ___» and the native «Wizytę mam ___» end with no mark |
| `frame.twin` | предупреждение | p8 | «Do you need ___?» / «Czy potrzeba ___?» is the target pattern of p5 |
| `filler.one_in_dialogue` | предупреждение | p5.f1 | «my name» is marked in_dialogue, but no line says it |
| `line.ne_frame` | **фатальная** | B5 | «Do I need your name?» is not «Do you need ___?» with any of its fillers («my name», «my ID») |
| `check.verbatim` | предупреждение | x3.check | the right option «How long it has lasted» repeats «how long» of the partner's line |
| `options.partner_fragment` | предупреждение | x3.check | the option «Jak długo to trwa» is a piece of the partner's line «Jak długo to trwa?» |
| `options.partner_fragment` | предупреждение | x5.check | the option «Pełne imię i nazwisko» is a piece of the partner's line «Tak, poproszę pani pełne imię i nazwisko.» |
| `check.verbatim` | предупреждение | x6.check | the right option «For the booking record» repeats «the booking» of the partner's line |
| `options.partner_fragment` | предупреждение | x6.check | the option «Do rezerwacji» is a piece of the partner's line «Potrzebuję też numeru telefonu do rezerwacji.» |

### Не проверено — нет ключей пакета (lang.pack_missing, не находка)

Всё проверено: у пакетов обоих языков пары есть все нужные ключи. Контекст проверки: родной `pl`, целевой `en`.

### Судья швов

Предложений: 17 · с вердиктом: 17 · «нет»: 0 · `lesson_seam_judge.v1.1` · $0.0018
