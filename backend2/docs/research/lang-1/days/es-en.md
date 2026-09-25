# LANG-1 · Español→English (es→en, начальный)

Цель плана (слова ученика): «Pido cita con el médico: llevo tres días con dolor de garganta y fiebre. Tengo que elegir una hora que me venga bien y explicar qué me pasa»

Роль ученика в плане: Patient / Paciente. Сцена 1: «Recepción» (Receptionist / Recepcionista); сцена 2: «Consulta» (Doctor / Médico).

> День 1 — урок, каким его получил бы ученик (прошедший порог, как записан в сцену; при `failed` — ответ модели как написан), прочитанный сервером: наполнение реплики найдено по её тексту. «Чтение» — как модель записала звучание целевого текста буквами родного. Находки — по ответу модели ДО починок, валидатором кода прогона в контексте боя этой пары. «Не проверено» — проверки, для которых у пакета языка нет ключей (`lang.pack_missing`, не находка). Цена — вызовы плана и дня по одному разу. Факты сюжета и роли код не проверяет.

Итог: **ready** · починок P2R: 2 (x1: exchange.second_question; x1.check: options.form_mismatch) · вызовов урока: 1 · план $0.0115 · 8.6 с · день $0.0835 (урок $0.0591 · починки $0.0226 · судья $0.0018) · из кэша 57 % входа · 47.8 с · всего $0.0950 · ученик: Paciente · собеседник: Recepcionista (женщина)

Промты: план `plan-builder-v2` · урок `lesson_day.v4.7` · починка `lesson_card_repair.v1.3` · судья `lesson_seam_judge.v1.1` · модель урока `gpt-5.4-2026-03-05` · попыток урока: 1 · в сцене записано $0.083487 (урок, починки и судья — сверка, не слагаемое)

### Сценарий диалога

| # | вид обмена | кто | реплика | перевод | чтение | каркас · наполнение |
|---|---|---|---|---|---|---|
| 1 | вопрос ученика | Paciente (ученик) | I'd like a doctor's appointment. | Quisiera una cita con el médico. | айд лайк э дóкторз эпóйнтмэнт | p1 · a doctor's appointment |
| 1 | вопрос ученика | Recepcionista (собеседник) | Of course. | Claro. |  |  |
| 2 | ответ | Recepcionista (собеседник) | What seems to be the problem? | ¿Qué problema tienes? |  |  |
| 2 | ответ | Paciente (ученик) | I have a sore throat and fever. | Tengo dolor de garganta y fiebre. | ай хэв э сор сроут энд ֆíвер | p2 · a sore throat and fever |
| 3 | ответ | Recepcionista (собеседник) | How long have you had that? | ¿Cuánto tiempo llevas con eso? |  |  |
| 3 | ответ | Paciente (ученик) | I've had it for three days. | Llevo tres días con eso. | айв хад ит фор срí дейз | p3 · for three days |
| 4 | ответ | Recepcionista (собеседник) | We have appointments at ten and at two today. | Tenemos citas hoy a las diez y a las dos. |  |  |
| 4 | ответ | Paciente (ученик) | Ten is not good for me. | Las diez no me vienen bien. | тэн из нот гуд фор ми | p4 · ten |
| 5 | вопрос ученика | Paciente (ученик) | Is two available? | ¿Están libres las dos? | из ту эвéйлэбл | p5 · two |
| 5 | вопрос ученика | Recepcionista (собеседник) | Yes, two o'clock is available. | Sí, las dos están libres. |  |  |
| 6 | вопрос ученика | Paciente (ученик) | Do you have anything tomorrow morning? | ¿Tienen algo mañana por la mañana? | ду ю хэв éнисин томóроу морнин | p6 · tomorrow morning |
| 6 | вопрос ученика | Recepcionista (собеседник) | No, but two o'clock today is still free. | No, pero las dos de hoy siguen libres. |  |  |
| 7 | ответ | Recepcionista (собеседник) | Can I have your name, please? | ¿Me dices tu nombre, por favor? |  |  |
| 7 | ответ | Paciente (ученик) | My name is Carlos Ruiz. | Me llamo Carlos Ruiz. | май нейм из карлос руис | p7 · Carlos Ruiz |
| 8 | ответ | Recepcionista (собеседник) | You're booked for two o'clock today. Please arrive ten minutes early. | Tienes cita hoy a las dos. Por favor, llega diez minutos antes. |  |  |
| 8 | ответ | Paciente (ученик) | I'll be there ten minutes early. | Estaré allí diez minutos antes. | айл би зэр тэн mínитс ёрли | p8 · there ten minutes early |

### Каркасы

| id | вид | каркас | на родном | чтение | наполнения (в диалоге — жирным) |
|---|---|---|---|---|---|
| p1 | вопрос ученика | I'd like ___ | Quisiera ___ | айд лайк ___ | **a doctor's appointment** / una cita con el médico · an appointment today / una cita hoy |
| p2 | ответ | I have ___ | Tengo ___ | ай хэв ___ | **a sore throat and fever** / dolor de garganta y fiebre · a bad cough / mucha tos · ear pain / dolor de oído |
| p3 | ответ | I've had it ___ | Llevo ___ con eso | айв хад ит ___ | **for three days** / tres días · since Monday / desde el lunes |
| p4 | ответ | ___ is not good for me | ___ no me viene bien | ___ из нот гуд фор ми | **ten** / las diez · Friday afternoon / el viernes por la tarde |
| p5 | вопрос ученика | Is ___ available? | ¿Está libre ___? | из ___ эвéйлэбл | **two** / las dos · five thirty / las cinco y media |
| p6 | вопрос ученика | Do you have anything ___? | ¿Tienen algo ___? | ду ю хэв éнисин ___ | **tomorrow morning** / mañana por la mañana · this evening / esta tarde |
| p7 | ответ | My name is ___ | Me llamo ___ | май нейм из ___ | **Carlos Ruiz** / Carlos Ruiz · Ana Torres / Ana Torres |
| p8 | ответ | I'll be ___ | Estaré ___ | айл би ___ | **there ten minutes early** / allí diez minutos antes · back this afternoon / de vuelta esta tarde |

### Наполнения

| каркас | наполнение | перевод | чтение | в диалоге | собранная фраза (родной) | судья швов |
|---|---|---|---|---|---|---|
| p1 | a doctor's appointment | una cita con el médico | э дóкторз эпóйнтмэнт | да | Quisiera una cita con el médico | да |
| p1 | an appointment today | una cita hoy | эн эпóйнтмэнт тудéй | — | Quisiera una cita hoy | да |
| p2 | a sore throat and fever | dolor de garganta y fiebre | э сор сроут энд ֆíвер | да | Tengo dolor de garganta y fiebre | да |
| p2 | a bad cough | mucha tos | э бэд коф | — | Tengo mucha tos | да |
| p2 | ear pain | dolor de oído | ир пэйн | — | Tengo dolor de oído | да |
| p3 | for three days | tres días | фор срí дейз | да | Llevo tres días con eso | да |
| p3 | since Monday | desde el lunes | синс мáндей | — | Llevo desde el lunes con eso | **нет** |
| p4 | ten | las diez | тэн | да | las diez no me viene bien | **нет** |
| p4 | Friday afternoon | el viernes por la tarde | фрáйдей афтэрнун | — | el viernes por la tarde no me viene bien | да |
| p5 | two | las dos | ту | да | ¿Está libre las dos? | **нет** |
| p5 | five thirty | las cinco y media | файв сёрти | — | ¿Está libre las cinco y media? | **нет** |
| p6 | tomorrow morning | mañana por la mañana | томóроу морнин | да | ¿Tienen algo mañana por la mañana? | да |
| p6 | this evening | esta tarde | зис íвнин | — | ¿Tienen algo esta tarde? | да |
| p7 | Carlos Ruiz | Carlos Ruiz | карлос руис | да | Me llamo Carlos Ruiz | да |
| p7 | Ana Torres | Ana Torres | ана торрес | — | Me llamo Ana Torres | да |
| p8 | there ten minutes early | allí diez minutos antes | зэр тэн mínитс ёрли | да | Estaré allí diez minutos antes | да |
| p8 | back this afternoon | de vuelta esta tarde | бэк зис афтэрнун | — | Estaré de vuelta esta tarde | да |

### Проверки обменов

| # | вопрос | варианты (✓ — верный) |
|---|---|---|
| 1 | What does the receptionist say? / ¿Qué dice la recepcionista? | ✓ She agrees to help / Acepta ayudar · She asks for the patient's name / Pide el nombre del paciente · She says there are no appointments / Dice que no hay citas |
| 2 | What does the receptionist want to know? / ¿Qué quiere saber la recepcionista? | How the patient will pay / Cómo va a pagar el paciente · ✓ What health problem the patient has / Qué problema de salud tiene el paciente · Which doctor the patient saw before / Qué médico vio antes el paciente |
| 3 | How long does the receptionist ask about? / ¿Qué duración pregunta la recepcionista? | When it started today / Si empezó hoy · ✓ The length of time / Cuánto tiempo lleva así · How often it happens / Con qué frecuencia pasa |
| 4 | Which two times does the receptionist offer? / ¿Qué dos horas ofrece la recepcionista? | Nine and one / Las nueve y la una · ✓ Ten and two / Las diez y las dos · Eleven and three / Las once y las tres |
| 5 | What does the receptionist confirm? / ¿Qué confirma la recepcionista? | ✓ The later time is free / La hora más tarde está libre · Both times are full / Las dos horas están ocupadas · Only the morning slot is open / Solo está libre la hora de la mañana |
| 6 | What time does the receptionist say is still open? / ¿Qué hora dice la recepcionista que sigue libre? | The early morning slot / La primera hora de la mañana · The midday visit / La cita de las doce · ✓ The two o'clock appointment / La cita de las dos |
| 7 | What detail does the receptionist ask for? / ¿Qué dato pide la recepcionista? | ✓ The patient's name / El nombre del paciente · The patient's insurance number / El número de seguro del paciente · The patient's email / El correo del paciente |
| 8 | When should the patient arrive? / ¿Cuándo debe llegar el paciente? | ✓ A little before the appointment / Un poco antes de la cita · Exactly at the appointment time / Exactamente a la hora de la cita · Half an hour before / Media hora antes |

### Слушаю весь визит

- L1. ¿Qué problema dice el paciente que tiene? — ✓ Dolor de garganta y fiebre · Dolor de espalda y tos · Dolor de oído y mareo
- L2. ¿Qué hora no le viene bien al paciente? — Las dos · ✓ Las diez · Mañana por la mañana
- L3. ¿Qué dato personal pide la recepcionista? — El correo electrónico · El número de seguro · ✓ El nombre
- L4. ¿Cuándo tiene que llegar el paciente? — ✓ Diez minutos antes · Justo a las dos · Treinta minutos antes

### Словарь

| id | слово | вид | перевод | чтение | где звучит |
|---|---|---|---|---|---|
| v1 | appointment | слово | cita | эпóйнтмэнт | p1, A4 |
| v2 | sore throat | связка | dolor de garganta | сор сроут | p2 |
| v3 | fever | слово | fiebre | ֆíвер | p2 |
| v4 | available | слово | libre | эвéйлэбл | p5, A5 |
| v5 | tomorrow morning | связка | mañana por la mañana | томóроу морнин | p6 |
| v6 | name | слово | nombre | нейм | p7, A7 |
| v7 | booked | слово | con cita confirmada | букт | A8 |
| v8 | arrive early | связка | llegar antes | эрáйв ёрли | p8, A8 |

### Находки в ответе модели (до починок)

| код | порог | адрес | что |
|---|---|---|---|
| `exchange.second_question` | **фатальная** | x1 | the closing message of A «Of course. What seems to be the problem?» ends with a question mark |
| `frame.no_end_punct` | предупреждение | p1 | «I'd like ___» ends with no mark |
| `frame.no_end_punct` | предупреждение | p2 | «I have ___» ends with no mark |
| `frame.no_end_punct` | предупреждение | p3 | «I've had it ___» ends with no mark |
| `frame.unresolved_pronoun` | предупреждение | p3 | «I've had it ___» leans on «it», and nothing in the frame is what it stands for |
| `frame.no_end_punct` | предупреждение | p4 | «___ is not good for me» ends with no mark |
| `frame.no_end_punct` | предупреждение | p7 | «My name is ___» ends with no mark |
| `frame.no_end_punct` | предупреждение | p8 | «I'll be ___» ends with no mark |
| `variant.longer` | предупреждение | B5 | the variant «Can I come at two?» has 5 words, the line 3 |
| `vocab.free_combination` | предупреждение | v5 | «tomorrow morning» is a free combination of ordinary words |
| `vocab.everyday_word` | предупреждение | v6 | «name» is a plain everyday word or a word of the STOP LIST |
| `vocab.used_in_wrong` | предупреждение | v8 | «arrive early» is not in frame p8 or its fillers |
| `vocab.used_in_wrong` | предупреждение | v8 | «arrive early» is not in the partner's line of exchange 8 |

### Не проверено — нет ключей пакета (lang.pack_missing, не находка)

Кодов не проверено: родной 9, целевой 0. Контекст проверки: родной `es`, целевой `en`.

| код | сторона | язык | недостающие ключи |
|---|---|---|---|
| `pronunciation.script` | родной | es | script |
| `pronunciation.foreign_script` | родной | es | script_letters |
| `frame.no_end_punct` | родной | es | sentence_ends |
| `frame.native_punct` | родной | es | sentence_ends |
| `frame.native_agreement` | родной | es | agreement |
| `listening.same_exchange` | родной | es | function_words, word_forms |
| `listening.no_learner_value` | родной | es | function_words, word_forms |
| `listening.distractor_not_filler` | родной | es | function_words, word_forms, number_pattern, time_pattern |
| `native.gendered_past` | родной | es | gendered_past_pattern |

### Судья швов

Предложений: 17 · с вердиктом: 17 · «нет»: 4 · `lesson_seam_judge.v1.1` · $0.0018

- p3.f2: «Llevo desde el lunes con eso» — «Llevo ___ con eso» + «desde el lunes»
- p4.f1: «las diez no me viene bien» — «___ no me viene bien» + «las diez»
- p5.f1: «¿Está libre las dos?» — «¿Está libre ___?» + «las dos»
- p5.f2: «¿Está libre las cinco y media?» — «¿Está libre ___?» + «las cinco y media»
