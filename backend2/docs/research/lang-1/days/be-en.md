# LANG-1 · Беларуская→English (be→en, начальный)

Цель плана (слова ученика): «Запісваюся да лекара: трэці дзень баліць горла і тэмпература. Трэба выбраць зручны час і растлумачыць, што мяне турбуе»

Роль ученика в плане: Patient / Пацыент. Сцена 1: «Запіс на прыём» (Receptionist / Рэгістратар); сцена 2: «Прыём у лекара» (Doctor / Лекар).

> День 1 — урок, каким его получил бы ученик (прошедший порог, как записан в сцену; при `failed` — ответ модели как написан), прочитанный сервером: наполнение реплики найдено по её тексту. «Чтение» — как модель записала звучание целевого текста буквами родного. Находки — по ответу модели ДО починок, валидатором кода прогона в контексте боя этой пары. «Не проверено» — проверки, для которых у пакета языка нет ключей (`lang.pack_missing`, не находка). Цена — вызовы плана и дня по одному разу. Факты сюжета и роли код не проверяет.

Итог: **ready** · починок P2R: 2 (x1: exchange.second_question; x4.check: options.form_mismatch) · вызовов урока: 1 · план $0.0123 · 10.7 с · день $0.0906 (урок $0.0652 · починки $0.0237 · судья $0.0018) · из кэша 57 % входа · 60.5 с · всего $0.1029 · ученик: Пацыент · собеседник: Рэгістратар (женщина)

Промты: план `plan-builder-v2` · урок `lesson_day.v4.7` · починка `lesson_card_repair.v1.3` · судья `lesson_seam_judge.v1.1` · модель урока `gpt-5.4-2026-03-05` · попыток урока: 1 · в сцене записано $0.090637 (урок, починки и судья — сверка, не слагаемое)

### Сценарий диалога

| # | вид обмена | кто | реплика | перевод | чтение | каркас · наполнение |
|---|---|---|---|---|---|---|
| 1 | вопрос ученика | Пацыент (ученик) | I'd like to book a doctor's appointment. | Я хачу запісацца на прыём да лекара. | айд лайк ту бук э докторз эпойнтмэнт | p1 · a doctor's appointment |
| 1 | вопрос ученика | Рэгістратар (собеседник) | Of course. Please tell me the problem. | Вядома. Скажыце, калі ласка, у чым праблема. |  |  |
| 2 | ответ | Рэгістратар (собеседник) | What symptoms do you have? | Якія ў вас сімптомы? |  |  |
| 2 | ответ | Пацыент (ученик) | I have a sore throat. | У мяне баліць горла. | ай хэв э сор сроут | p2 · a sore throat |
| 3 | ответ | Рэгістратар (собеседник) | How long have you had it? | Як даўно гэта ў вас? |  |  |
| 3 | ответ | Пацыент (ученик) | For three days. | Ужо тры дні. | фор сры дэйз | p3 · three days |
| 4 | ответ | Рэгістратар (собеседник) | Do you also have a fever? | У вас таксама ёсць тэмпература? |  |  |
| 4 | ответ | Пацыент (ученик) | Yes, I also have a fever. | Так, у мяне таксама ёсць тэмпература. | йес, ай олсоу хэв э фівер | p4 · a fever |
| 5 | вопрос ученика | Пацыент (ученик) | What is the nearest available time? | Які бліжэйшы вольны час? | уот из зэ нірыст эвэйлэбл тайм | p5 · the nearest available time |
| 5 | вопрос ученика | Рэгістратар (собеседник) | We have today at four and tomorrow at nine. | У нас ёсць сёння а чацвёртай і заўтра а дзявятай. |  |  |
| 6 | переспрос | Пацыент (ученик) | Could you say that more slowly, please? | Скажыце, калі ласка, павольней. | куд ю сэй зэт мор слоўлі, пліз | — |
| 6 | переспрос | Рэгістратар (собеседник) | Today at four, or tomorrow at nine. | Сёння а чацвёртай або заўтра а дзявятай. |  |  |
| 7 | ответ | Рэгістратар (собеседник) | The four o'clock slot is still free. | Час а чацвёртай яшчэ вольны. |  |  |
| 7 | ответ | Пацыент (ученик) | Four o'clock works for me. | Чацвёртая мне падыходзіць. | фор эклок уоркс фор мі | p6 · four o'clock |
| 8 | вопрос ученика | Пацыент (ученик) | Please book me for today at four. | Калі ласка, запішыце мяне на сёння а чацвёртай. | пліз бук мі фор тудэй эт фор | p7 · today at four |
| 8 | вопрос ученика | Рэгістратар (собеседник) | Done. You are booked for today at four. | Гатова. Вы запісаны на сёння а чацвёртай. |  |  |

### Каркасы

| id | вид | каркас | на родном | чтение | наполнения (в диалоге — жирным) |
|---|---|---|---|---|---|
| p1 | вопрос ученика | I'd like to book ___ | Я хачу запісацца на ___ | айд лайк ту бук ___ | **a doctor's appointment** / прыём да лекара · a checkup / агляд |
| p2 | ответ | I have ___ | У мяне ___ | ай хэв ___ | **a sore throat** / баліць горла · a cough / кашаль · a headache / баліць галава |
| p3 | ответ | For ___ | Ужо ___ | фор ___ | **three days** / тры дні · two days / два дні · a week / тыдзень |
| p4 | ответ | I also have ___ | У мяне таксама ___ | ай олсоу хэв ___ | **a fever** / ёсць тэмпература · ear pain / боль у вуху |
| p5 | вопрос ученика | What is ___? | Які ___? | уот из ___ | **the nearest available time** / бліжэйшы вольны час · the earliest appointment / самы ранні прыём |
| p6 | ответ | ___ works for me | Мне падыходзіць ___ | ___ уоркс фор мі | **four o'clock** / чацвёртая · nine o'clock / дзявятая |
| p7 | вопрос ученика | Please book me for ___ | Калі ласка, запішыце мяне на ___ | пліз бук мі фор ___ | **today at four** / сёння а чацвёртай · tomorrow at nine / заўтра а дзявятай |

### Наполнения

| каркас | наполнение | перевод | чтение | в диалоге | собранная фраза (родной) | судья швов |
|---|---|---|---|---|---|---|
| p1 | a doctor's appointment | прыём да лекара | э докторз эпойнтмэнт | да | Я хачу запісацца на прыём да лекара | да |
| p1 | a checkup | агляд | э чэк-ап | — | Я хачу запісацца на агляд | да |
| p2 | a sore throat | баліць горла | э сор сроут | да | У мяне баліць горла | да |
| p2 | a cough | кашаль | э коф | — | У мяне кашаль | да |
| p2 | a headache | баліць галава | э хэдэйк | — | У мяне баліць галава | да |
| p3 | three days | тры дні | сры дэйз | да | Ужо тры дні | да |
| p3 | two days | два дні | ту дэйз | — | Ужо два дні | да |
| p3 | a week | тыдзень | э уік | — | Ужо тыдзень | да |
| p4 | a fever | ёсць тэмпература | э фівер | да | У мяне таксама ёсць тэмпература | да |
| p4 | ear pain | боль у вуху | ір пэйн | — | У мяне таксама боль у вуху | да |
| p5 | the nearest available time | бліжэйшы вольны час | зэ нірыст эвэйлэбл тайм | да | Які бліжэйшы вольны час? | да |
| p5 | the earliest appointment | самы ранні прыём | зэ ёрліэст эпойнтмэнт | — | Які самы ранні прыём? | да |
| p6 | four o'clock | чацвёртая | фор эклок | да | Мне падыходзіць чацвёртая | да |
| p6 | nine o'clock | дзявятая | найн эклок | — | Мне падыходзіць дзявятая | да |
| p7 | today at four | сёння а чацвёртай | тудэй эт фор | да | Калі ласка, запішыце мяне на сёння а чацвёртай | да |
| p7 | tomorrow at nine | заўтра а дзявятай | туморау эт найн | — | Калі ласка, запішыце мяне на заўтра а дзявятай | да |

### Проверки обменов

| # | вопрос | варианты (✓ — верный) |
|---|---|---|
| 1 | What does the receptionist ask about? / Пра што пытаецца рэгістратар? | ✓ The reason for the visit / Прычына візіту · The patient's address / Адрас пацыента · The doctor's surname / Прозвішча лекара |
| 2 | What does the receptionist want to know? / Што хоча даведацца рэгістратар? | ✓ Which symptoms the patient has / Якія сімптомы ў пацыента · Which medicine the patient takes / Якія лекі прымае пацыент · Which doctor the patient saw before / Да якога лекара пацыент хадзіў раней |
| 3 | What time period does the receptionist ask about? / Пра які перыяд часу пытаецца рэгістратар? | ✓ How long the problem has lasted / Колькі доўжыцца праблема · How long the visit will take / Колькі будзе доўжыцца прыём · How long the patient waited outside / Колькі пацыент чакаў на вуліцы |
| 4 | What extra symptom does the receptionist mention? / Які дадатковы сімптом называе рэгістратар? | ✓ A high temperature / Высокая тэмпература · A strong cough / Моцны кашаль · Ear pain / Боль у вуху |
| 5 | Which times does the receptionist offer? / Які час прапануе рэгістратар? | ✓ Today in the late afternoon and tomorrow morning / Сёння пад вечар і заўтра раніцай · Today in the morning and tomorrow at noon / Сёння раніцай і заўтра апоўдні · This evening and the day after tomorrow / Сёння ўвечары і паслязаўтра |
| 6 | Which appointment is on the next day? / Які прыём на наступны дзень? | ✓ The one at nine in the morning / Той, што а дзявятай раніцы · The one at four in the afternoon / Той, што а чацвёртай дня · The one in the evening / Той, што ўвечары |
| 7 | What does the receptionist say about four o'clock? / Што рэгістратар кажа пра чацвёртую? | ✓ It is still available / Гэты час яшчэ вольны · It was just cancelled / Яго толькі што адмянілі · It is only for children / Гэты час толькі для дзяцей |
| 8 | What booking does the receptionist confirm? / Які запіс пацвярджае рэгістратар? | ✓ An appointment this afternoon at four / Прыём сёння днём а чацвёртай · A visit tomorrow morning at nine / Прыём заўтра раніцай а дзявятай · A phone call this evening / Тэлефонны званок сёння ўвечары |

### Слушаю весь визит

- L1. З якой праблемай пацыент запісваецца да лекара? — ✓ З болем у горле · З болем у жываце · З болем у спіне
- L2. Як доўга гэта працягваецца? — ✓ Тры дні · Адзін дзень · Тыдзень
- L3. Якія два часы прапануе рэгістратар? — ✓ Сёння а чацвёртай і заўтра а дзявятай · Сёння а дзявятай і заўтра а чацвёртай · Заўтра а чацвёртай і паслязаўтра а дзявятай
- L4. На які час у выніку запісалі пацыента? — ✓ На сёння а чацвёртай · На заўтра а дзявятай · На сёння а шостай

### Словарь

| id | слово | вид | перевод | чтение | где звучит |
|---|---|---|---|---|---|
| v1 | book | слово | запісацца | бук | p1, p7 |
| v2 | appointment | слово | прыём | эпойнтмэнт | p1 |
| v3 | sore throat | связка | боль у горле | сор сроут | p2 |
| v4 | fever | слово | тэмпература | фівер | p4, A4 |
| v5 | symptom | слово | сімптом | сімптом | A2 |
| v6 | available | слово | вольны | эвэйлэбл | p5, A7 |
| v7 | slot | слово | час | слот | A7 |
| v8 | works for me | связка | мне падыходзіць | уоркс фор мі | p6 |

### Находки в ответе модели (до починок)

| код | порог | адрес | что |
|---|---|---|---|
| `exchange.second_question` | **фатальная** | x1 | the closing message of A «Of course. What seems to be the problem?» ends with a question mark |
| `frame.no_end_punct` | предупреждение | p1 | «I'd like to book ___» ends with no mark |
| `frame.no_end_punct` | предупреждение | p2 | «I have ___» ends with no mark |
| `frame.no_end_punct` | предупреждение | p3 | «For ___» ends with no mark |
| `frame.no_end_punct` | предупреждение | p4 | «I also have ___» ends with no mark |
| `frame.no_end_punct` | предупреждение | p6 | «___ works for me» ends with no mark |
| `frame.no_end_punct` | предупреждение | p7 | «Please book me for ___» ends with no mark |
| `check.verbatim` | предупреждение | x3.check | the right option «How long the problem has lasted» repeats «how long» of the partner's line |
| `options.form_mismatch` | **фатальная** | x4.check | the option «Боль у вуху» is 9 letters against 20 of the right «Павышаная тэмпература» |
| `check.verbatim` | предупреждение | x7.check | the right option «It is still available» repeats «is still» of the partner's line |
| `vocab.everyday_word` | предупреждение | v1 | «book» is a plain everyday word or a word of the STOP LIST |
| `vocab.used_in_wrong` | предупреждение | v6 | «available» is not in the partner's line of exchange 7 |

### Не проверено — нет ключей пакета (lang.pack_missing, не находка)

Кодов не проверено: родной 9, целевой 0. Контекст проверки: родной `be`, целевой `en`.

| код | сторона | язык | недостающие ключи |
|---|---|---|---|
| `pronunciation.script` | родной | be | script |
| `pronunciation.foreign_script` | родной | be | script_letters |
| `frame.no_end_punct` | родной | be | sentence_ends |
| `frame.native_punct` | родной | be | sentence_ends |
| `frame.native_agreement` | родной | be | agreement |
| `listening.same_exchange` | родной | be | function_words, word_forms |
| `listening.no_learner_value` | родной | be | function_words, word_forms |
| `listening.distractor_not_filler` | родной | be | function_words, word_forms, number_pattern, time_pattern |
| `native.gendered_past` | родной | be | gendered_past_pattern |

### Судья швов

Предложений: 16 · с вердиктом: 16 · «нет»: 0 · `lesson_seam_judge.v1.1` · $0.0018
