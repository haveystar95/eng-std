# LANG-1 · Deutsch→English (de→en, начальный)

Цель плана (слова ученика): «Ich mache einen Termin beim Arzt: Seit drei Tagen habe ich Halsschmerzen und Fieber. Ich muss einen passenden Termin wählen und erklären, was mir fehlt»

Роль ученика в плане: Patient / Patient. Сцена 1: «Anmeldung» (Receptionist / Empfangskraft); сцена 2: «Beim Arzt» (Doctor / Arzt).

> День 1 — урок, каким его получил бы ученик (прошедший порог, как записан в сцену; при `failed` — ответ модели как написан), прочитанный сервером: наполнение реплики найдено по её тексту. «Чтение» — как модель записала звучание целевого текста буквами родного. Находки — по ответу модели ДО починок, валидатором кода прогона в контексте боя этой пары. «Не проверено» — проверки, для которых у пакета языка нет ключей (`lang.pack_missing`, не находка). Цена — вызовы плана и дня по одному разу. Факты сюжета и роли код не проверяет.

Итог: **ready** · починок P2R: 1 (x1: exchange.second_question, pronunciation.script) · вызовов урока: 1 · план $0.0177 · 6.3 с · день $0.0802 (урок $0.0636 · починки $0.0143 · судья $0.0023) · из кэша 65 % входа · 34.1 с · всего $0.0979 · ученик: Patient · собеседник: Empfangskraft (женщина)

Промты: план `plan-builder-v2` · урок `lesson_day.v4.9` · починка `lesson_card_repair.v1.3` · судья `lesson_seam_judge.v1.1` · модель урока `gpt-5.4-2026-03-05` · попыток урока: 1 · в сцене записано $0.080205 (урок, починки и судья — сверка, не слагаемое)

### Сценарий диалога

| # | вид обмена | кто | реплика | перевод | чтение | каркас · наполнение |
|---|---|---|---|---|---|---|
| 1 | вопрос ученика | Patient (ученик) | I need a doctor's appointment. | Ich brauche einen Arzttermin. | Ai niid ə dokters əpointment. | p1 · a doctor's appointment |
| 1 | вопрос ученика | Empfangskraft (собеседник) | Of course. Please tell me the problem. | Natürlich. Bitte sagen Sie mir, worum es geht. |  |  |
| 2 | ответ | Empfangskraft (собеседник) | What symptoms do you have? | Welche Beschwerden haben Sie? |  |  |
| 2 | ответ | Patient (ученик) | I have a sore throat and fever. | Ich habe Halsschmerzen und Fieber. | Ai häv ə sor throht änd fiewer. | p2 · a sore throat and fever |
| 3 | ответ | Empfangskraft (собеседник) | How long have you had that? | Wie lange haben Sie das schon? |  |  |
| 3 | ответ | Patient (ученик) | I've had it for three days. | Ich habe das seit drei Tagen. | Aiw häd it for srii dehs. | p3 · for three days |
| 4 | ответ | Empfangskraft (собеседник) | We have appointments at ten and at two today. | Wir haben heute Termine um zehn und um zwei. |  |  |
| 4 | ответ | Patient (ученик) | Two o'clock works for me. | Zwei Uhr passt mir. | Tuu əklokk works for mi. | p4 · two o'clock |
| 5 | вопрос ученика | Patient (ученик) | Is there anything this afternoon? | Gibt es etwas heute Nachmittag? | Is sser änißing ssis afternuun? | p5 · this afternoon |
| 5 | вопрос ученика | Empfangskraft (собеседник) | Yes, the two o'clock appointment is still free. | Ja, der Termin um zwei ist noch frei. |  |  |
| 6 | ответ | Empfangskraft (собеседник) | Can I have your full name, please? | Wie ist bitte Ihr vollständiger Name? |  |  |
| 6 | ответ | Patient (ученик) | My name is Anna Becker. | Ich heiße Anna Becker. | Mai neim is Änna Bäcker. | p6 · Anna Becker |
| 7 | ответ | Empfangskraft (собеседник) | Please bring your insurance card and arrive ten minutes early. | Bitte bringen Sie Ihre Versicherungskarte mit und kommen Sie zehn Minuten früher. |  |  |
| 7 | ответ | Patient (ученик) | I'll bring my insurance card. | Ich bringe meine Versicherungskarte mit. | Ail bring mai inschuränz kard. | p7 · my insurance card |
| 8 | вопрос ученика | Patient (ученик) | Do you need my phone number? | Brauchen Sie meine Telefonnummer? | Du ju niid mai foun namba? | p8 · my phone number |
| 8 | вопрос ученика | Empfangskraft (собеседник) | Yes, please. We may need to call you. | Ja, bitte. Wir müssen Sie vielleicht anrufen. |  |  |

### Каркасы

| id | вид | каркас | на родном | чтение | наполнения (в диалоге — жирным) |
|---|---|---|---|---|---|
| p1 | вопрос ученика | I need ___. | Ich brauche ___. | Ai niid ___. | **a doctor's appointment** / einen Arzttermin · an appointment today / einen Termin heute · an earlier appointment / einen früheren Termin |
| p2 | ответ | I have ___. | Ich habe ___. | Ai häv ___. | **a sore throat and fever** / Halsschmerzen und Fieber · a bad cough / starken Husten · ear pain / Ohrenschmerzen |
| p3 | ответ | I've had it ___. | Ich habe das ___. | Aiw häd it ___. | **for three days** / seit drei Tagen · since yesterday / seit gestern · for a week / seit einer Woche |
| p4 | ответ | ___ works for me. | ___ passt mir. | ___ works for mi. | **two o'clock** / zwei Uhr · ten o'clock / zehn Uhr · tomorrow morning / morgen früh |
| p5 | вопрос ученика | Is there anything ___? | Gibt es etwas ___? | Is sser änißing ___? | **this afternoon** / heute Nachmittag · tomorrow morning / morgen früh · after work / nach der Arbeit |
| p6 | ответ | My name is ___. | Ich heiße ___. | Mai neim is ___. | **Anna Becker** / Anna Becker · Mia Hoffmann / Mia Hoffmann · Lea Wagner / Lea Wagner |
| p7 | ответ | I'll bring ___. | Ich bringe ___ mit. | Ail bring ___ . | **my insurance card** / meine Versicherungskarte · my ID / meinen Ausweis · my old prescription / mein altes Rezept |
| p8 | вопрос ученика | Do you need ___? | Brauchen Sie ___? | Du ju niid ___? | **my phone number** / meine Telefonnummer · my email address / meine E-Mail-Adresse · my insurance number / meine Versicherungsnummer |

### Наполнения

| каркас | наполнение | перевод | чтение | в диалоге | собранная фраза (родной) | судья швов |
|---|---|---|---|---|---|---|
| p1 | a doctor's appointment | einen Arzttermin | ə doktors əpointment | да | Ich brauche einen Arzttermin. | да |
| p1 | an appointment today | einen Termin heute | än əpointment tədei | — | Ich brauche einen Termin heute. | да |
| p1 | an earlier appointment | einen früheren Termin | än örlier əpointment | — | Ich brauche einen früheren Termin. | да |
| p2 | a sore throat and fever | Halsschmerzen und Fieber | ə sor throht änd fiewer | да | Ich habe Halsschmerzen und Fieber. | да |
| p2 | a bad cough | starken Husten | ə bäd koff | — | Ich habe starken Husten. | да |
| p2 | ear pain | Ohrenschmerzen | ier pein | — | Ich habe Ohrenschmerzen. | да |
| p3 | for three days | seit drei Tagen | for srii dehs | да | Ich habe das seit drei Tagen. | да |
| p3 | since yesterday | seit gestern | sins jesterdei | — | Ich habe das seit gestern. | да |
| p3 | for a week | seit einer Woche | for ə wiik | — | Ich habe das seit einer Woche. | да |
| p4 | two o'clock | zwei Uhr | tuu əklokk | да | zwei Uhr passt mir. | да |
| p4 | ten o'clock | zehn Uhr | ten əklokk | — | zehn Uhr passt mir. | да |
| p4 | tomorrow morning | morgen früh | təmoro morniŋ | — | morgen früh passt mir. | да |
| p5 | this afternoon | heute Nachmittag | ssis afternuun | да | Gibt es etwas heute Nachmittag? | да |
| p5 | tomorrow morning | morgen früh | təmoro morniŋ | — | Gibt es etwas morgen früh? | да |
| p5 | after work | nach der Arbeit | after wörk | — | Gibt es etwas nach der Arbeit? | да |
| p6 | Anna Becker | Anna Becker | Änna Bäcker | да | Ich heiße Anna Becker. | да |
| p6 | Mia Hoffmann | Mia Hoffmann | Mia Hoffmän | — | Ich heiße Mia Hoffmann. | да |
| p6 | Lea Wagner | Lea Wagner | Liia Wägner | — | Ich heiße Lea Wagner. | да |
| p7 | my insurance card | meine Versicherungskarte | mai inschuränz kard | да | Ich bringe meine Versicherungskarte mit. | да |
| p7 | my ID | meinen Ausweis | mai ai dii | — | Ich bringe meinen Ausweis mit. | да |
| p7 | my old prescription | mein altes Rezept | mai ould priscripschen | — | Ich bringe mein altes Rezept mit. | да |
| p8 | my phone number | meine Telefonnummer | mai foun namba | да | Brauchen Sie meine Telefonnummer? | да |
| p8 | my email address | meine E-Mail-Adresse | mai imeil ädress | — | Brauchen Sie meine E-Mail-Adresse? | да |
| p8 | my insurance number | meine Versicherungsnummer | mai inschuränz namba | — | Brauchen Sie meine Versicherungsnummer? | да |

### Проверки обменов

| # | вопрос | варианты (✓ — верный) |
|---|---|---|
| 1 | What does the receptionist ask about? / Wonach fragt die Empfangskraft? | ✓ The reason for the visit / Nach dem Grund für den Termin · The patient's insurance card / Nach der Versicherungskarte der Patientin · The clinic address / Nach der Adresse der Praxis |
| 2 | What is the receptionist asking for? / Worum bittet die Empfangskraft? | A preferred appointment day / Um einen Wunschtermin · ✓ The patient's symptoms / Um die Beschwerden der Patientin · A home address / Um eine Wohnadresse |
| 3 | What detail does the receptionist want to know? / Welches Detail möchte die Empfangskraft wissen? | How severe the fever is / Wie stark das Fieber ist · ✓ How long the problem has lasted / Wie lange das Problem schon dauert · Whether the patient can come today / Ob die Patientin heute kommen kann |
| 4 | Which times does the receptionist offer? / Welche Uhrzeiten bietet die Empfangskraft an? | Nine and one / Neun Uhr und eins · ✓ Ten and two / Zehn Uhr und zwei · Eleven and three / Elf Uhr und drei |
| 5 | What does the receptionist say about two o'clock? / Was sagt die Empfangskraft zu zwei Uhr? | It has already been taken / Er ist schon vergeben · ✓ It is still available / Er ist noch frei · It starts a bit later / Er beginnt etwas später |
| 6 | What information does the receptionist ask for? / Nach welcher Angabe fragt die Empfangskraft? | ✓ The patient's full name / Nach dem vollständigen Namen der Patientin · The patient's birth date / Nach dem Geburtsdatum der Patientin · The patient's street address / Nach der Adresse der Patientin |
| 7 | What should the patient bring? / Was soll die Patientin mitbringen? | A list of medicines / Eine Liste mit Medikamenten · ✓ Her insurance card / Ihre Versicherungskarte · A passport photo / Ein Passfoto |
| 8 | Why does the receptionist want the number? / Warum möchte die Empfangskraft die Nummer? | To send the bill / Um die Rechnung zu schicken · ✓ To call the patient if needed / Um die Patientin bei Bedarf anzurufen · To confirm the home address / Um die Wohnadresse zu bestätigen |

### Слушаю весь визит

- L1. Was fehlt der Patientin? — ✓ Halsschmerzen und Fieber · Bauchschmerzen und Übelkeit · Rückenschmerzen und Husten
- L2. Welche Uhrzeit wählt die Patientin? — zehn Uhr · ✓ zwei Uhr · morgen früh
- L3. Was soll die Patientin zum Termin mitbringen? — ihren Ausweis · ✓ ihre Versicherungskarte · ein Rezept
- L4. Welche Angabe möchte die Empfangskraft von der Patientin haben? — ✓ den vollständigen Namen · die Arbeitsadresse · den Beruf

### Словарь

| id | слово | вид | перевод | чтение | где звучит |
|---|---|---|---|---|---|
| v1 | doctor's appointment | связка | Arzttermin | doktors əpointment | p1 |
| v2 | sore throat | связка | Halsschmerzen | sor throht | p2 |
| v3 | fever | слово | Fieber | fiewer | p2 |
| v4 | for three days | связка | seit drei Tagen | for srii dehs | p3 |
| v5 | full name | связка | vollständiger Name | full neim | A6 |
| v6 | insurance card | связка | Versicherungskarte | inschuränz kard | A7, p7 |
| v7 | arrive early | связка | früher kommen | əraiw örli | A7 |
| v8 | phone number | связка | Telefonnummer | foun namba | p8 |

### Находки в ответе модели (до починок)

| код | порог | адрес | что |
|---|---|---|---|
| `exchange.second_question` | **фатальная** | x1 | the closing message of A «Of course. What seems to be the problem?» ends with a question mark |
| `pronunciation.script` | предупреждение | p1.f1 | the reading «ə doktors əpointment» leaves the native script |
| `pronunciation.script` | предупреждение | p1.f2 | the reading «än əpointment tədei» leaves the native script |
| `pronunciation.script` | предупреждение | p1.f3 | the reading «än örlier əpointment» leaves the native script |
| `pronunciation.script` | предупреждение | p2.f1 | the reading «ə sor throht änd fiewer» leaves the native script |
| `pronunciation.script` | предупреждение | p2.f2 | the reading «ə bäd koff» leaves the native script |
| `pronunciation.script` | предупреждение | p3.f3 | the reading «for ə wiik» leaves the native script |
| `pronunciation.script` | предупреждение | p4.f1 | the reading «tuu əklokk» leaves the native script |
| `pronunciation.script` | предупреждение | p4.f2 | the reading «ten əklokk» leaves the native script |
| `pronunciation.script` | предупреждение | p4.f3 | the reading «təmoro morniŋ» leaves the native script |
| `pronunciation.script` | предупреждение | p5.f2 | the reading «təmoro morniŋ» leaves the native script |
| `pronunciation.script` | предупреждение | v1 | the reading «doktors əpointment» leaves the native script |
| `pronunciation.script` | предупреждение | v7 | the reading «əraiw örli» leaves the native script |
| `pronunciation.script` | предупреждение | B1 | the reading «Ai niid ə doktors əpointment.» leaves the native script |
| `pronunciation.script` | предупреждение | B2 | the reading «Ai häv ə sor throht änd fiewer.» leaves the native script |
| `pronunciation.script` | предупреждение | B4 | the reading «Tuu əklokk works for mi.» leaves the native script |
| `frame.unresolved_pronoun` | предупреждение | p3 | «I've had it ___.» leans on «it», and nothing in the frame is what it stands for |
| `frame.native_agreement` | предупреждение | p3 | «Ich habe das ___.»: «das» agrees with the slot — it changes with the filler |
| `check.verbatim` | предупреждение | x3.check | the right option «How long the problem has lasted» repeats «how long» of the partner's line |
| `check.verbatim` | предупреждение | x5.check | the right option «It is still available» repeats «is still» of the partner's line |
| `check.verbatim` | предупреждение | x8.check | the right option «To call the patient if needed» repeats «to call» of the partner's line |
| `listening.same_exchange` | предупреждение | L3 | L2 and this question are both about exchange 4 |
| `vocab.free_combination` | предупреждение | v4 | «for three days» is a free combination of ordinary words |
| `vocab.used_in_wrong` | предупреждение | v7 | «arrive early» is not in the partner's line of exchange 7 |

### Не проверено — нет ключей пакета (lang.pack_missing, не находка)

Всё проверено: у пакетов обоих языков пары есть все нужные ключи. Контекст проверки: родной `de`, целевой `en`.

### Судья швов

Предложений: 24 · с вердиктом: 24 · «нет»: 0 · `lesson_seam_judge.v1.1` · $0.0023
