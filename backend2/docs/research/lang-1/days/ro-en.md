# LANG-1 · Română→English (ro→en, начальный)

Цель плана (слова ученика): «Mă programez la medic: de trei zile mă doare gâtul și am febră. Trebuie să aleg o oră potrivită și să explic ce mă supără»

Роль ученика в плане: Patient / Pacient. Сцена 1: «Programare» (Receptionist / Recepționer); сцена 2: «Consultație» (Doctor / Medic).

> День 1 — урок, каким его получил бы ученик (прошедший порог, как записан в сцену; при `failed` — ответ модели как написан), прочитанный сервером: наполнение реплики найдено по её тексту. «Чтение» — как модель записала звучание целевого текста буквами родного. Находки — по ответу модели ДО починок, валидатором кода прогона в контексте боя этой пары. «Не проверено» — проверки, для которых у пакета языка нет ключей (`lang.pack_missing`, не находка). Цена — вызовы плана и дня по одному разу. Факты сюжета и роли код не проверяет.

Итог: **failed** (fatal: options.form_mismatch) · починок P2R: 2 (p7: frame.no_end_punct, filler.ungrammatical, filler.one_in_dialogue; x1: exchange.second_question) · вызовов урока: 1 · план $0.0120 · 10.2 с · день $0.0898 (урок $0.0618 · починки $0.0279 · судья $0.0000) · из кэша 55 % входа · 42.7 с · всего $0.1017 · ученик: Pacient · собеседник: Recepționer (женщина)

Промты: план `plan-builder-v2` · урок `lesson_day.v4.7` · починка `lesson_card_repair.v1.3` · судья `lesson_seam_judge.v1.1` · модель урока `gpt-5.4-2026-03-05` · попыток урока: 1 · в сцене записано $0.089774 (урок, починки и судья — сверка, не слагаемое)

### Сценарий диалога

| # | вид обмена | кто | реплика | перевод | чтение | каркас · наполнение |
|---|---|---|---|---|---|---|
| 1 | вопрос ученика | Pacient (ученик) | I'd like to book an appointment. | Aș vrea să fac o programare. | aid laik tu buc ăn ăpointmănt | p1 · an appointment |
| 1 | вопрос ученика | Recepționer (собеседник) | Of course. What is the visit for? | Sigur. Pentru ce este consultația? |  |  |
| 2 | ответ | Recepționer (собеседник) | What seems to be the problem? | Care pare să fie problema? |  |  |
| 2 | ответ | Pacient (ученик) | I have a sore throat. | Mă doare gâtul. | ai hv ə sor throuăt | p2 · a sore throat |
| 3 | ответ | Recepționer (собеседник) | How long have you had it? | De cât timp o ai? |  |  |
| 3 | ответ | Pacient (ученик) | I've had it for three days. | O am de trei zile. | aiv had it for thrii deiz | p3 · for three days |
| 4 | ответ | Recepționer (собеседник) | We have today at four or tomorrow at nine. | Avem azi la patru sau mâine la nouă. |  |  |
| 4 | ответ | Pacient (ученик) | Today at four works for me. | Azi la patru este bine pentru mine. | tădei ăt for uărks for mi | p4 · today at four |
| 5 | вопрос ученика | Pacient (ученик) | What do I need to bring? | Ce trebuie să aduc? | uăt du ai niid tu bring | p5 · bring |
| 5 | вопрос ученика | Recepționer (собеседник) | Please bring your ID and insurance card. | Te rog să aduci actul de identitate și cardul de asigurare. |  |  |
| 6 | вопрос ученика | Pacient (ученик) | Should I come early? | Ar trebui să vin mai devreme? | șud ai cam ărli | p6 · early |
| 6 | вопрос ученика | Recepționer (собеседник) | Yes, please come fifteen minutes early. | Da, te rog să vii cu cincisprezece minute mai devreme. |  |  |
| 7 | ответ | Recepționer (собеседник) | I also need your phone number for the booking. | Mai am nevoie și de numărul tău de telefon pentru programare. |  |  |
| 7 | ответ | Pacient (ученик) | Here is my phone number. | Iată numărul meu de telefon. | hir iz mai foun nămbăr | p7 · **не каркас ни с одним наполнением** |
| 8 | ответ | Recepționer (собеседник) | You're booked for today at four with Dr. Lee. | Ești programat azi la patru la doamna doctor Lee. |  |  |
| 8 | ответ | Pacient (ученик) | Okay, see you today at four. | Bine, ne vedem azi la patru. | oukei, sii iu tădei ăt for | p8 · today at four |

### Каркасы

| id | вид | каркас | на родном | чтение | наполнения (в диалоге — жирным) |
|---|---|---|---|---|---|
| p1 | вопрос ученика | I'd like to book ___ | Aș vrea să fac ___ | aid laik tu buc ___ | **an appointment** / o programare · a checkup / un control |
| p2 | ответ | I have ___ | Am ___ | ai hv ___ | **a sore throat** / durere în gât · a fever / febră · a cough / tuse |
| p3 | ответ | I've had it ___ | O am ___ | aiv had it ___ | **for three days** / de trei zile · since Monday / de luni |
| p4 | ответ | ___ works for me | ___ este bine pentru mine | ___ uărks for mi | **today at four** / azi la patru · tomorrow at nine / mâine la nouă |
| p5 | вопрос ученика | What do I need to ___? | Ce trebuie să ___? | uăt du ai niid tu ___ | **bring** / aduc · fill out / completez |
| p6 | вопрос ученика | Should I come ___? | Ar trebui să vin ___? | șud ai cam ___ | **early** / mai devreme · tomorrow / mâine |
| p7 | ответ | Here is my ___ | Iată ___ | hir iz mai ___ | my phone number / numărul meu de telefon · my insurance card / cardul meu de asigurare |
| p8 | ответ | See you ___ | Ne vedem ___ | sii iu ___ | **today at four** / azi la patru · tomorrow morning / mâine dimineață |

### Наполнения

| каркас | наполнение | перевод | чтение | в диалоге | собранная фраза (родной) | судья швов |
|---|---|---|---|---|---|---|
| p1 | an appointment | o programare | ăn ăpointmănt | да | Aș vrea să fac o programare | — |
| p1 | a checkup | un control | ă cek-ăp | — | Aș vrea să fac un control | — |
| p2 | a sore throat | durere în gât | ă sor throuăt | да | Am durere în gât | — |
| p2 | a fever | febră | ă fiivăr | — | Am febră | — |
| p2 | a cough | tuse | ă cof | — | Am tuse | — |
| p3 | for three days | de trei zile | for thrii deiz | да | O am de trei zile | — |
| p3 | since Monday | de luni | sins mandei | — | O am de luni | — |
| p4 | today at four | azi la patru | tădei ăt for | да | azi la patru este bine pentru mine | — |
| p4 | tomorrow at nine | mâine la nouă | tămorou ăt nain | — | mâine la nouă este bine pentru mine | — |
| p5 | bring | aduc | bring | да | Ce trebuie să aduc? | — |
| p5 | fill out | completez | fil aut | — | Ce trebuie să completez? | — |
| p6 | early | mai devreme | ărli | да | Ar trebui să vin mai devreme? | — |
| p6 | tomorrow | mâine | tămorou | — | Ar trebui să vin mâine? | — |
| p7 | my phone number | numărul meu de telefon | mai foun nămbăr | — | Iată numărul meu de telefon | — |
| p7 | my insurance card | cardul meu de asigurare | mai inșurănț card | — | Iată cardul meu de asigurare | — |
| p8 | today at four | azi la patru | tădei ăt for | да | Ne vedem azi la patru | — |
| p8 | tomorrow morning | mâine dimineață | tămorou mornin | — | Ne vedem mâine dimineață | — |

### Проверки обменов

| # | вопрос | варианты (✓ — верный) |
|---|---|---|
| 1 | What does the receptionist ask about? / Despre ce întreabă recepționera? | ✓ The reason for the appointment / Motivul programării · The patient's address / Adresa pacientului · The payment method / Metoda de plată |
| 2 | What problem does the receptionist ask about? / Despre ce problemă întreabă recepționera? | ✓ The patient's symptoms / Simptomele pacientului · The doctor's schedule / Programul medicului · The clinic address / Adresa clinicii |
| 3 | What time period does the receptionist ask about? / Despre ce perioadă întreabă recepționera? | ✓ How long the problem has lasted / Cât timp a durat problema · How long the visit will take / Cât va dura consultația · How long the patient waited / Cât a așteptat pacientul |
| 4 | Which time is available today? / Ce oră este disponibilă azi? | ✓ Four in the afternoon / Patru după-amiaza · Nine in the morning / Nouă dimineața · Six in the evening / Șase seara |
| 5 | Which two things should the patient bring? / Ce două lucruri trebuie să aducă pacientul? | ✓ An ID and a health card / Un act de identitate și un card de asigurare · A passport and cash / Un pașaport și bani cash · Test results and medicine / Rezultate de analize și medicamente |
| 6 | How much earlier should the patient arrive? / Cu cât mai devreme trebuie să ajungă pacientul? | ✓ A quarter of an hour earlier / Cu un sfert de oră mai devreme · Half an hour earlier / Cu o jumătate de oră mai devreme · Exactly on time / Exact la timp |
| 7 | What extra detail does the receptionist need? / Ce detaliu suplimentar are nevoie recepționera? | ✓ A contact number / Un număr de contact · A home address / O adresă de acasă · An email password / O parolă de email |
| 8 | When is the appointment confirmed for? / Pentru când este confirmată programarea? | ✓ This afternoon at four / Azi după-amiază la patru · Tomorrow morning at nine / Mâine dimineață la nouă · Today at noon / Azi la prânz |

### Слушаю весь визит

- L1. Pentru ce problemă vrea pacientul programarea? — ✓ Pentru o durere în gât · Pentru dureri de spate · Pentru o rețetă nouă
- L2. Ce oră a ales pacientul? — ✓ Azi la patru · Mâine la nouă · Azi la șase
- L3. Ce trebuie să aducă pacientul la clinică? — ✓ Actul de identitate și cardul de asigurare · Pașaportul și bani cash · Rezultate medicale vechi
- L4. Cu cât mai devreme trebuie să vină pacientul? — ✓ Cu 15 minute · Cu 30 de minute · Exact la oră

### Словарь

| id | слово | вид | перевод | чтение | где звучит |
|---|---|---|---|---|---|
| v1 | appointment | слово | programare | ăpointmănt | p1 |
| v2 | sore throat | связка | durere în gât | sor throuăt | p2 |
| v3 | fever | слово | febră | fiivăr | p2 |
| v4 | insurance card | связка | card de asigurare | inșurănț card | A5, p7 |
| v5 | phone number | связка | număr de telefon | foun nămbăr | p7, A7 |
| v6 | book | слово | a programa | buc | p1, A8 |
| v7 | checkup | слово | control | cek-ăp | p1 |
| v8 | arrive early | связка | a veni mai devreme | ăraiv ărli | p6, A6 |

### Находки в ответе модели (до починок)

| код | порог | адрес | что |
|---|---|---|---|
| `exchange.second_question` | **фатальная** | x1 | the closing message of A «Of course. What is the visit for?» ends with a question mark |
| `frame.no_end_punct` | предупреждение | p1 | «I'd like to book ___» ends with no mark |
| `frame.no_end_punct` | предупреждение | p2 | «I have ___» ends with no mark |
| `frame.no_end_punct` | предупреждение | p3 | «I've had it ___» ends with no mark |
| `frame.unresolved_pronoun` | предупреждение | p3 | «I've had it ___» leans on «it», and nothing in the frame is what it stands for |
| `frame.no_end_punct` | предупреждение | p4 | «___ works for me» ends with no mark |
| `frame.no_end_punct` | предупреждение | p7 | «Here is my ___» ends with no mark |
| `frame.no_end_punct` | предупреждение | p8 | «See you ___» ends with no mark |
| `filler.ungrammatical` | **фатальная** | p7.f1 | «Here is my my phone number»: a word is doubled at the seam |
| `filler.ungrammatical` | **фатальная** | p7.f2 | «Here is my my insurance card»: a word is doubled at the seam |
| `filler.one_in_dialogue` | предупреждение | p7.f1 | «my phone number» is marked in_dialogue, but no line says it |
| `variant.longer` | предупреждение | B6 | the variant «Do I need to come early?» has 6 words, the line 4 |
| `line.ne_frame` | **фатальная** | B7 | «Here is my phone number.» is not «Here is my ___» with any of its fillers («my phone number», «my insurance card») |
| `check.verbatim` | предупреждение | x3.check | the right option «How long the problem has lasted» repeats «how long» of the partner's line |
| `options.form_mismatch` | **фатальная** | x6.check | the option «Exact la timp» is 11 letters against 24 of the right «Cu un sfert de oră mai devreme» |
| `vocab.everyday_word` | предупреждение | v6 | «book» is a plain everyday word or a word of the STOP LIST |
| `vocab.used_in_wrong` | предупреждение | v8 | «arrive early» is not in frame p6 or its fillers |
| `vocab.used_in_wrong` | предупреждение | v8 | «arrive early» is not in the partner's line of exchange 6 |

### Не проверено — нет ключей пакета (lang.pack_missing, не находка)

Кодов не проверено: родной 9, целевой 0. Контекст проверки: родной `ro`, целевой `en`.

| код | сторона | язык | недостающие ключи |
|---|---|---|---|
| `pronunciation.script` | родной | ro | script |
| `pronunciation.foreign_script` | родной | ro | script_letters |
| `frame.no_end_punct` | родной | ro | sentence_ends |
| `frame.native_punct` | родной | ro | sentence_ends |
| `frame.native_agreement` | родной | ro | agreement |
| `listening.same_exchange` | родной | ro | function_words, word_forms |
| `listening.no_learner_value` | родной | ro | function_words, word_forms |
| `listening.distractor_not_filler` | родной | ro | function_words, word_forms, number_pattern, time_pattern |
| `native.gendered_past` | родной | ro | gendered_past_pattern |

### Судья швов

Судья не звался: урок не прошёл порог.

### Находки с пакетами LANG-1 (повторная проверка, без вызовов)

Фатальных: 5 → 5 · предупреждений: 13 → 14 · не проверено кодов (родной/целевой): 9/0 → 0/0 · пакеты сейчас: be, de, en, es, fr, it, pl, ro, ru, uk

Коды: появились pronunciation.script×1.

| ± код | порог | адрес | что |
|---|---|---|---|
| + `pronunciation.script` | предупреждение | B2 | the reading «ai hv ə sor throuăt» leaves the native script |
| + `frame.no_end_punct` | предупреждение | p1 | «I'd like to book ___» and the native «Aș vrea să fac ___» end with no mark |
| + `frame.no_end_punct` | предупреждение | p2 | «I have ___» and the native «Am ___» end with no mark |
| + `frame.no_end_punct` | предупреждение | p3 | «I've had it ___» and the native «O am ___» end with no mark |
| + `frame.no_end_punct` | предупреждение | p4 | «___ works for me» and the native «___ este bine pentru mine» end with no mark |
| + `frame.no_end_punct` | предупреждение | p7 | «Here is my ___» and the native «Iată ___» end with no mark |
| + `frame.no_end_punct` | предупреждение | p8 | «See you ___» and the native «Ne vedem ___» end with no mark |
| − `frame.no_end_punct` | предупреждение | p1 | «I'd like to book ___» ends with no mark |
| − `frame.no_end_punct` | предупреждение | p2 | «I have ___» ends with no mark |
| − `frame.no_end_punct` | предупреждение | p3 | «I've had it ___» ends with no mark |
| − `frame.no_end_punct` | предупреждение | p4 | «___ works for me» ends with no mark |
| − `frame.no_end_punct` | предупреждение | p7 | «Here is my ___» ends with no mark |
| − `frame.no_end_punct` | предупреждение | p8 | «See you ___» ends with no mark |
