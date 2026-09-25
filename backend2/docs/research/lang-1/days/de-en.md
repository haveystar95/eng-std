# LANG-1 · Deutsch→English (de→en, начальный)

Цель плана (слова ученика): «Ich mache einen Termin beim Arzt: Seit drei Tagen habe ich Halsschmerzen und Fieber. Ich muss einen passenden Termin wählen und erklären, was mir fehlt»

Роль ученика в плане: Patient / Patient. Сцена 1: «Anmeldung» (Receptionist / Empfangskraft); сцена 2: «Beim Arzt» (Doctor / Arzt).

> День 1 — урок, каким его получил бы ученик (прошедший порог, как записан в сцену; при `failed` — ответ модели как написан), прочитанный сервером: наполнение реплики найдено по её тексту. «Чтение» — как модель записала звучание целевого текста буквами родного. Находки — по ответу модели ДО починок, валидатором кода прогона в контексте боя этой пары. «Не проверено» — проверки, для которых у пакета языка нет ключей (`lang.pack_missing`, не находка). Цена — вызовы плана и дня по одному разу. Факты сюжета и роли код не проверяет.

Итог: **failed** (fatal: options.form_mismatch) · починок P2R: 2 (x1: exchange.second_question; x8.check: options.form_mismatch) · вызовов урока: 1 · план $0.0115 · 9.5 с · день $0.1095 (урок $0.0866 · починки $0.0230 · судья $0.0000) · из кэша 60 % входа · 57.0 с · всего $0.1210 · ученик: Patient · собеседник: Empfangskraft (женщина)

Промты: план `plan-builder-v2` · урок `lesson_day.v4.7` · починка `lesson_card_repair.v1.3` · судья `lesson_seam_judge.v1.1` · модель урока `gpt-5.4-2026-03-05` · попыток урока: 1 · в сцене записано $0.109519 (урок, починки и судья — сверка, не слагаемое)

### Сценарий диалога

| # | вид обмена | кто | реплика | перевод | чтение | каркас · наполнение |
|---|---|---|---|---|---|---|
| 1 | вопрос ученика | Patient (ученик) | I'd like a doctor's appointment. | Ich hätte gern einen Arzttermin. | айд лайк э докторс эпойнтмэнт | p1 · a doctor's appointment |
| 1 | вопрос ученика | Empfangskraft (собеседник) | Of course. What seems to be the problem? | Natürlich. Was ist denn das Problem? |  |  |
| 2 | ответ | Empfangskraft (собеседник) | What symptoms do you have? | Welche Beschwerden haben Sie? |  |  |
| 2 | ответ | Patient (ученик) | I have a sore throat and fever. | Ich habe Halsschmerzen und Fieber. | ай хэв э сор сроут энд фивэр | p2 · a sore throat and fever |
| 3 | ответ | Empfangskraft (собеседник) | How long have you had that? | Wie lange haben Sie das schon? |  |  |
| 3 | ответ | Patient (ученик) | I've had it for three days. | Ich habe das seit drei Tagen. | айв хэд ит фо сри дэйз | p3 · three days |
| 4 | ответ | Empfangskraft (собеседник) | We have appointments at ten or at two today. | Wir haben heute Termine um zehn oder um zwei. |  |  |
| 4 | ответ | Patient (ученик) | Two o'clock is better for me. | Zwei Uhr passt mir besser. | tu oklokk is better fo ми | p4 · two o'clock |
| 5 | вопрос ученика | Patient (ученик) | What do you need for your name? | Was brauchen Sie für den Namen? | уот ду ю нид фо йор нэйм | p5 · your name |
| 5 | вопрос ученика | Empfangskraft (собеседник) | I need your full name, please. | Ich brauche bitte Ihren vollständigen Namen. |  |  |
| 6 | ответ | Empfangskraft (собеседник) | And what is your phone number? | Und wie ist Ihre Telefonnummer? |  |  |
| 6 | ответ | Patient (ученик) | My phone number is 0176 234567. | Meine Telefonnummer ist 0176 234567. | май фоун намбэр из оу уан сэвэн сикс ту сри фор файв сикс сэвэн | p6 · 0176 234567 |
| 7 | ответ | Empfangskraft (собеседник) | Please come fifteen minutes early for the form. | Bitte kommen Sie fünfzehn Minuten früher wegen des Formulars. |  |  |
| 7 | ответ | Patient (ученик) | I'll come fifteen minutes early. | Ich komme fünfzehn Minuten früher. | айл кам фифтин минитс ёрли | p7 · fifteen minutes early |
| 8 | вопрос ученика | Patient (ученик) | Could you give me the address? | Könnten Sie mir die Adresse geben? | куд ю гив ми зи эдрэс | p8 · the address |
| 8 | вопрос ученика | Empfangskraft (собеседник) | Yes, it's 14 King Street. | Ja, das ist King Street 14. |  |  |

### Каркасы

| id | вид | каркас | на родном | чтение | наполнения (в диалоге — жирным) |
|---|---|---|---|---|---|
| p1 | вопрос ученика | I'd like ___. | Ich hätte gern ___. | айд лайк ___ | **a doctor's appointment** / einen Arzttermin · an appointment today / einen Termin heute |
| p2 | ответ | I have ___. | Ich habe ___. | ай хэв ___ | **a sore throat and fever** / Halsschmerzen und Fieber · a cough / Husten · a bad headache / starke Kopfschmerzen |
| p3 | ответ | I've had it for ___. | Ich habe das seit ___. | айв хэд ит фо ___ | **three days** / drei Tagen · two days / zwei Tagen · a week / einer Woche |
| p4 | ответ | ___ is better for me. | ___ passt mir besser. | ___ из better фо ми | **two o'clock** / Zwei Uhr · ten o'clock / Zehn Uhr |
| p5 | вопрос ученика | What do you need for ___? | Was brauchen Sie für ___? | уот ду ю нид фо ___ | **your name** / den Namen · the number / die Nummer |
| p6 | ответ | My phone number is ___. | Meine Telefonnummer ist ___. | май фоун намбэр из ___ | **0176 234567** / 0176 234567 · 030 445566 / 030 445566 |
| p7 | ответ | I'll come ___. | Ich komme ___. | айл кам ___ | **fifteen minutes early** / fünfzehn Minuten früher · ten minutes early / zehn Minuten früher |
| p8 | вопрос ученика | Could you give me ___? | Könnten Sie mir ___ geben? | куд ю гив ми ___ | **the address** / die Adresse · the phone number / die Telefonnummer |

### Наполнения

| каркас | наполнение | перевод | чтение | в диалоге | собранная фраза (родной) | судья швов |
|---|---|---|---|---|---|---|
| p1 | a doctor's appointment | einen Arzttermin | э докторс эпойнтмэнт | да | Ich hätte gern einen Arzttermin. | — |
| p1 | an appointment today | einen Termin heute | эн эпойнтмэнт тудэй | — | Ich hätte gern einen Termin heute. | — |
| p2 | a sore throat and fever | Halsschmerzen und Fieber | э сор сроут энд фивэр | да | Ich habe Halsschmerzen und Fieber. | — |
| p2 | a cough | Husten | э коф | — | Ich habe Husten. | — |
| p2 | a bad headache | starke Kopfschmerzen | э бэд хэдэйк | — | Ich habe starke Kopfschmerzen. | — |
| p3 | three days | drei Tagen | сри дэйз | да | Ich habe das seit drei Tagen. | — |
| p3 | two days | zwei Tagen | ту дэйз | — | Ich habe das seit zwei Tagen. | — |
| p3 | a week | einer Woche | э уик | — | Ich habe das seit einer Woche. | — |
| p4 | two o'clock | Zwei Uhr | ту оклок | да | Zwei Uhr passt mir besser. | — |
| p4 | ten o'clock | Zehn Uhr | тэн оклок | — | Zehn Uhr passt mir besser. | — |
| p5 | your name | den Namen | йор нэйм | да | Was brauchen Sie für den Namen? | — |
| p5 | the number | die Nummer | зе намбэр | — | Was brauchen Sie für die Nummer? | — |
| p6 | 0176 234567 | 0176 234567 | оу уан сэвэн сикс ту сри фор файв сикс сэвэн | да | Meine Telefonnummer ist 0176 234567. | — |
| p6 | 030 445566 | 030 445566 | зиро сри зиро фор фор файв файв сикс сикс | — | Meine Telefonnummer ist 030 445566. | — |
| p7 | fifteen minutes early | fünfzehn Minuten früher | фифтин минитс ёрли | да | Ich komme fünfzehn Minuten früher. | — |
| p7 | ten minutes early | zehn Minuten früher | тэн минитс ёрли | — | Ich komme zehn Minuten früher. | — |
| p8 | the address | die Adresse | зи эдрэс | да | Könnten Sie mir die Adresse geben? | — |
| p8 | the phone number | die Telefonnummer | зи фоун намбэр | — | Könnten Sie mir die Telefonnummer geben? | — |

### Проверки обменов

| # | вопрос | варианты (✓ — верный) |
|---|---|---|
| 1 | What does the receptionist want to know? / Was möchte die Empfangskraft wissen? | ✓ Your health issue / Welches gesundheitliche Problem du hast · Your home address / Wie deine Adresse ist · Your insurance company / Bei welcher Versicherung du bist |
| 2 | What does the receptionist ask about? / Wonach fragt die Empfangskraft? | ✓ Your current complaints / Nach deinen aktuellen Beschwerden · Your next vacation / Nach deinem nächsten Urlaub · Your previous doctor / Nach deinem früheren Arzt |
| 3 | What information does the receptionist ask for? / Welche Information fragt die Empfangskraft ab? | ✓ How long it has lasted / Wie lange es schon dauert · How often it happens / Wie oft es passiert · How strong it feels / Wie stark es ist |
| 4 | Which two times does the receptionist offer? / Welche zwei Uhrzeiten bietet die Empfangskraft an? | Nine and one / Neun Uhr und ein Uhr · ✓ Ten and two / Zehn Uhr und zwei Uhr · Eleven and three / Elf Uhr und drei Uhr |
| 5 | What does the receptionist need? / Was braucht die Empfangskraft? | ✓ Your complete name / Deinen vollständigen Namen · Your date of birth / Dein Geburtsdatum · Your street name / Deinen Straßennamen |
| 6 | What personal detail does the receptionist ask for? / Nach welcher persönlichen Angabe fragt die Empfangskraft? | ✓ A contact number / Nach einer Telefonnummer · An email address / Nach einer E-Mail-Adresse · A passport number / Nach einer Passnummer |
| 7 | When should the patient arrive? / Wann soll der Patient kommen? | ✓ A quarter hour before / Eine Viertelstunde vorher · Exactly on time / Genau pünktlich · Half an hour later / Eine halbe Stunde später |
| 8 | What address does the receptionist give? / Welche Adresse nennt die Empfangskraft? | ✓ 14 King Street / King Street 14 · 40 Queen Street / Queen Street 40 · 18 Market Road / Market Road 18 |

### Слушаю весь визит

- L1. Warum möchte der Patient einen Termin? — ✓ Wegen Halsschmerzen und Fieber · Wegen Rückenschmerzen · Wegen Bauchschmerzen
- L2. Seit wann hat der Patient die Beschwerden? — Seit einem Tag · ✓ Seit drei Tagen · Seit einer Woche
- L3. Welche Uhrzeit wählt der Patient? — Zehn Uhr · Zwölf Uhr · ✓ Zwei Uhr
- L4. Wann soll der Patient kommen? — ✓ Fünfzehn Minuten früher · Genau pünktlich · Zwanzig Minuten später

### Словарь

| id | слово | вид | перевод | чтение | где звучит |
|---|---|---|---|---|---|
| v1 | doctor's appointment | связка | Arzttermin | докторс эпойнтмэнт | p1 |
| v2 | sore throat | связка | Halsschmerzen | сор сроут | p2 |
| v3 | fever | слово | Fieber | фивэр | p2 |
| v4 | symptoms | слово | Beschwerden | симптомз | A2 |
| v5 | full name | связка | vollständiger Name | фул нэйм | A5 |
| v6 | phone number | связка | Telefonnummer | фоун намбэр | p6, p8 |
| v7 | form | слово | Formular | форм | A7 |
| v8 | address | слово | Adresse | эдрэс | p8, A8 |

### Находки в ответе модели (до починок)

| код | порог | адрес | что |
|---|---|---|---|
| `exchange.second_question` | **фатальная** | x1 | the closing message of A «Of course. What seems to be the problem?» ends with a question mark |
| `frame.unresolved_pronoun` | предупреждение | p3 | «I've had it for ___.» leans on «it», and nothing in the frame is what it stands for |
| `check.verbatim` | предупреждение | x3.check | the right option «How long it has lasted» repeats «how long» of the partner's line |
| `options.form_mismatch` | **фатальная** | x8.check | the option «King Street 14» is a piece of the partner's line «Ja, das ist King Street 14.» |
| `vocab.used_in_wrong` | предупреждение | v8 | «address» is not in the partner's line of exchange 8 |

### Не проверено — нет ключей пакета (lang.pack_missing, не находка)

Кодов не проверено: родной 9, целевой 0. Контекст проверки: родной `de`, целевой `en`.

| код | сторона | язык | недостающие ключи |
|---|---|---|---|
| `pronunciation.script` | родной | de | script |
| `pronunciation.foreign_script` | родной | de | script_letters |
| `frame.no_end_punct` | родной | de | sentence_ends |
| `frame.native_punct` | родной | de | sentence_ends |
| `frame.native_agreement` | родной | de | agreement |
| `listening.same_exchange` | родной | de | function_words, word_forms |
| `listening.no_learner_value` | родной | de | function_words, word_forms |
| `listening.distractor_not_filler` | родной | de | function_words, word_forms, number_pattern, time_pattern |
| `native.gendered_past` | родной | de | gendered_past_pattern |

### Судья швов

Судья не звался: урок не прошёл порог.
