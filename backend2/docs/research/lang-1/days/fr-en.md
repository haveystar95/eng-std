# LANG-1 · Français→English (fr→en, начальный)

Цель плана (слова ученика): «Je prends rendez-vous chez le médecin : j'ai mal à la gorge et de la fièvre depuis trois jours. Je dois choisir un horaire qui me convient et expliquer ce qui ne va pas»

Роль ученика в плане: Patient / Patient. Сцена 1: «Prise de rendez-vous» (Receptionist / Réceptionniste); сцена 2: «Chez le médecin» (Doctor / Médecin).

> День 1 — урок, каким его получил бы ученик (прошедший порог, как записан в сцену; при `failed` — ответ модели как написан), прочитанный сервером: наполнение реплики найдено по её тексту. «Чтение» — как модель записала звучание целевого текста буквами родного. Находки — по ответу модели ДО починок, валидатором кода прогона в контексте боя этой пары. «Не проверено» — проверки, для которых у пакета языка нет ключей (`lang.pack_missing`, не находка). Цена — вызовы плана и дня по одному разу. Факты сюжета и роли код не проверяет.

Итог: **failed** (fatal: options.form_mismatch) · починок P2R: 2 (x1: exchange.second_question; x4.check: options.form_mismatch) · вызовов урока: 1 · план $0.0121 · 10.5 с · день $0.1110 (урок $0.0876 · починки $0.0234 · судья $0.0000) · из кэша 60 % входа · 55.6 с · всего $0.1231 · ученик: Patient · собеседник: Réceptionniste (женщина)

Промты: план `plan-builder-v2` · урок `lesson_day.v4.7` · починка `lesson_card_repair.v1.3` · судья `lesson_seam_judge.v1.1` · модель урока `gpt-5.4-2026-03-05` · попыток урока: 1 · в сцене записано $0.111014 (урок, починки и судья — сверка, не слагаемое)

### Сценарий диалога

| # | вид обмена | кто | реплика | перевод | чтение | каркас · наполнение |
|---|---|---|---|---|---|---|
| 1 | вопрос ученика | Patient (ученик) | I'd like to make an appointment. | Je voudrais prendre rendez-vous. | айд лайк ту мейк эн эпойнтмэнт | p1 · an appointment |
| 1 | вопрос ученика | Réceptionniste (собеседник) | Of course. What is the reason for your visit? | Bien sûr. Quelle est la raison de votre visite ? |  |  |
| 2 | ответ | Réceptionniste (собеседник) | Is it for a sore throat or something else? | C'est pour un mal de gorge ou autre chose ? |  |  |
| 2 | ответ | Patient (ученик) | It's for a sore throat. | C'est pour un mal de gorge. | итс фор э сор сроут | p2 · a sore throat |
| 3 | ответ | Réceptionniste (собеседник) | How long have you had the fever? | Depuis combien de temps avez-vous de la fièvre ? |  |  |
| 3 | ответ | Patient (ученик) | I've had it for three days. | J'ai ça depuis trois jours. | айв хэд ит фор сри дейз | p3 · three days |
| 4 | вопрос ученика | Patient (ученик) | What times are available today? | Quels horaires sont disponibles aujourd'hui ? | уот таймз ар эвейлэбл тудей | p4 · today |
| 4 | вопрос ученика | Réceptionniste (собеседник) | We have 10 a.m. and 3 p.m. today. | Nous avons 10 h et 15 h aujourd'hui. |  |  |
| 5 | вопрос ученика | Patient (ученик) | 3 p.m. works for me. | 15 h me convient. | сри пи эм уоркс фор ми | p5 · 3 p.m. |
| 5 | вопрос ученика | Réceptionniste (собеседник) | Okay, I can book you for 3 p.m. today. | D'accord, je peux vous réserver 15 h aujourd'hui. |  |  |
| 6 | ответ | Réceptionniste (собеседник) | Can I have your full name, please? | Puis-je avoir votre nom complet, s'il vous plaît ? |  |  |
| 6 | ответ | Patient (ученик) | My name is Marie Dupont. | Je m'appelle Marie Dupont. | май нейм из мари дюпон | p6 · Marie Dupont |
| 7 | ответ | Réceptionniste (собеседник) | What is your phone number? | Quel est votre numéro de téléphone ? |  |  |
| 7 | ответ | Patient (ученик) | My phone number is 06 12 34 56 78. | Mon numéro de téléphone est le 06 12 34 56 78. | май фоун намбэр из зиро сикс уан ту сёрти-фор фифти-сикс севенти-эйт | p7 · 06 12 34 56 78 |
| 8 | ответ | Réceptionniste (собеседник) | You're booked for 3 p.m. today with Dr. Lee. | Vous êtes inscrit pour 15 h aujourd'hui avec le Dr Lee. |  |  |
| 8 | ответ | Patient (ученик) | Thank you, see you then. | Merci, à tout à l'heure. | сэнк ю, си ю зэн | p8 · — |

### Каркасы

| id | вид | каркас | на родном | чтение | наполнения (в диалоге — жирным) |
|---|---|---|---|---|---|
| p1 | вопрос ученика | I'd like to make ___. | Je voudrais prendre ___. | айд лайк ту мейк ___ | **an appointment** / rendez-vous · a same-day appointment / un rendez-vous aujourd'hui |
| p2 | ответ | It's for ___. | C'est pour ___. | итс фор ___ | **a sore throat** / un mal de gorge · a fever / de la fièvre · a cough / une toux |
| p3 | ответ | I've had it for ___. | J'ai ça depuis ___. | айв хэд ит фор ___ | **three days** / trois jours · two days / deux jours · a week / une semaine |
| p4 | вопрос ученика | What times are available ___? | Quels horaires sont disponibles ___? | уот таймз ар эвейлэбл ___ | **today** / aujourd'hui · tomorrow / demain |
| p5 | вопрос ученика | ___ works for me. | ___ me convient. | ___ уоркс фор ми | **3 p.m.** / 15 h · 10 a.m. / 10 h |
| p6 | ответ | My name is ___. | Je m'appelle ___. | май нейм из ___ | **Marie Dupont** / Marie Dupont · Paul Martin / Paul Martin |
| p7 | ответ | My phone number is ___. | Mon numéro de téléphone est ___. | май фоун намбэр из ___ | **06 12 34 56 78** / le 06 12 34 56 78 · 07 45 11 22 33 / le 07 45 11 22 33 |
| p8 | ответ | Thank you, see you then. | Merci, à tout à l'heure. | сэнк ю, си ю зэн | — |

### Наполнения

| каркас | наполнение | перевод | чтение | в диалоге | собранная фраза (родной) | судья швов |
|---|---|---|---|---|---|---|
| p1 | an appointment | rendez-vous | эн эпойнтмэнт | да | Je voudrais prendre rendez-vous. | — |
| p1 | a same-day appointment | un rendez-vous aujourd'hui | э сейм-дей эпойнтмэнт | — | Je voudrais prendre un rendez-vous aujourd'hui. | — |
| p2 | a sore throat | un mal de gorge | э сор сроут | да | C'est pour un mal de gorge. | — |
| p2 | a fever | de la fièvre | э фивэр | — | C'est pour de la fièvre. | — |
| p2 | a cough | une toux | э коф | — | C'est pour une toux. | — |
| p3 | three days | trois jours | сри дейз | да | J'ai ça depuis trois jours. | — |
| p3 | two days | deux jours | ту дейз | — | J'ai ça depuis deux jours. | — |
| p3 | a week | une semaine | э уик | — | J'ai ça depuis une semaine. | — |
| p4 | today | aujourd'hui | тудей | да | Quels horaires sont disponibles aujourd'hui? | — |
| p4 | tomorrow | demain | тумороу | — | Quels horaires sont disponibles demain? | — |
| p5 | 3 p.m. | 15 h | сри пи эм | да | 15 h me convient. | — |
| p5 | 10 a.m. | 10 h | тен эй эм | — | 10 h me convient. | — |
| p6 | Marie Dupont | Marie Dupont | мари дюпон | да | Je m'appelle Marie Dupont. | — |
| p6 | Paul Martin | Paul Martin | пол мартен | — | Je m'appelle Paul Martin. | — |
| p7 | 06 12 34 56 78 | le 06 12 34 56 78 | зиро сикс уан ту сёрти-фор фифти-сикс севенти-эйт | да | Mon numéro de téléphone est le 06 12 34 56 78. | — |
| p7 | 07 45 11 22 33 | le 07 45 11 22 33 | зиро севен форти-файв илевен тенти-ту сёрти-сри | — | Mon numéro de téléphone est le 07 45 11 22 33. | — |

### Проверки обменов

| # | вопрос | варианты (✓ — верный) |
|---|---|---|
| 1 | What does the receptionist ask for? / Que demande la réceptionniste ? | ✓ The patient's symptoms and visit reason / Les symptômes du patient et la raison de la visite · The patient's home address / L'adresse du domicile du patient · The patient's insurance company / La compagnie d'assurance du patient |
| 2 | Which problem does the receptionist mention? / Quel problème la réceptionniste mentionne-t-elle ? | ✓ A pain in the throat / Une douleur à la gorge · A stomach problem / Un problème d'estomac · Back pain / Un mal de dos |
| 3 | What time period does the receptionist ask about? / Sur quelle durée la réceptionniste pose-t-elle une question ? | ✓ How many days the fever has lasted / Depuis combien de jours la fièvre dure · What time the clinic opens / À quelle heure la clinique ouvre · How long the appointment will be / Combien de temps durera le rendez-vous |
| 4 | Which times does the receptionist offer? / Quels horaires la réceptionniste propose-t-elle ? | Nine in the morning and noon / 9 h du matin et midi · ✓ Ten in the morning and mid-afternoon / 10 h du matin et le milieu d'après-midi · Eleven in the morning and four o'clock / 11 h du matin et 16 h |
| 5 | What appointment time does the receptionist confirm? / Quelle heure de rendez-vous la réceptionniste confirme-t-elle ? | ✓ Mid-afternoon today / Le milieu d'après-midi aujourd'hui · Early morning today / Tôt le matin aujourd'hui · Tomorrow at noon / Demain à midi |
| 6 | What personal detail does the receptionist ask for? / Quel renseignement personnel la réceptionniste demande-t-elle ? | A contact number / Un numéro de contact · ✓ The patient's full name / Le nom complet du patient · The date of birth / La date de naissance |
| 7 | What information does the receptionist ask for here? / Quelle information la réceptionniste demande-t-elle ici ? | ✓ A mobile contact number / Un numéro de téléphone portable · An email address / Une adresse e-mail · A street address / Une adresse postale |
| 8 | Who is the appointment with? / Avec qui est le rendez-vous ? | With the nurse / Avec l'infirmière · ✓ With Dr. Lee / Avec le Dr Lee · With the lab technician / Avec le technicien de laboratoire |

### Слушаю весь визит

- L1. Pourquoi le patient prend-il rendez-vous ? — ✓ Pour un mal de gorge · Pour un contrôle des yeux · Pour un mal de ventre
- L2. Depuis combien de temps le patient a-t-il ce problème ? — Depuis deux jours · ✓ Depuis trois jours · Depuis une semaine
- L3. Quel horaire est finalement choisi ? — 10 h aujourd'hui · ✓ 15 h aujourd'hui · Demain à 15 h
- L4. Avec qui est le rendez-vous ? — ✓ Avec le Dr Lee · Avec une infirmière · Avec un spécialiste de laboratoire

### Словарь

| id | слово | вид | перевод | чтение | где звучит |
|---|---|---|---|---|---|
| v1 | appointment | слово | rendez-vous | эпойнтмэнт | p1 |
| v2 | sore throat | связка | mal de gorge | сор сроут | p2, A2 |
| v3 | fever | слово | fièvre | фивэр | p2, A3 |
| v4 | available | слово | disponible | эвейлэбл | p4 |
| v5 | works for me | связка | me convient | уоркс фор ми | p5 |
| v6 | full name | связка | nom complet | фул нейм | A6 |
| v7 | phone number | связка | numéro de téléphone | фоун намбэр | p7, A7 |
| v8 | booked | слово | réservé | букт | A5, A8 |

### Находки в ответе модели (до починок)

| код | порог | адрес | что |
|---|---|---|---|
| `exchange.second_question` | **фатальная** | x1 | the closing message of A «Of course. What is the reason for your visit?» ends with a question mark |
| `frame.unresolved_pronoun` | предупреждение | p3 | «I've had it for ___.» leans on «it», and nothing in the frame is what it stands for |
| `options.form_mismatch` | **фатальная** | x4.check | the option «9 h du matin et midi» starts lower-case |
| `options.form_mismatch` | **фатальная** | x5.check | the option «Demain à midi» is 11 letters against 28 of the right «Le milieu d'après-midi aujourd'hui» |
| `options.form_mismatch` | **фатальная** | x8.check | the option «Avec le Dr Lee» is a piece of the partner's line «Vous êtes inscrit pour 15 h aujourd'hui avec le Dr Lee.» |

### Не проверено — нет ключей пакета (lang.pack_missing, не находка)

Кодов не проверено: родной 9, целевой 0. Контекст проверки: родной `fr`, целевой `en`.

| код | сторона | язык | недостающие ключи |
|---|---|---|---|
| `pronunciation.script` | родной | fr | script |
| `pronunciation.foreign_script` | родной | fr | script_letters |
| `frame.no_end_punct` | родной | fr | sentence_ends |
| `frame.native_punct` | родной | fr | sentence_ends |
| `frame.native_agreement` | родной | fr | agreement |
| `listening.same_exchange` | родной | fr | function_words, word_forms |
| `listening.no_learner_value` | родной | fr | function_words, word_forms |
| `listening.distractor_not_filler` | родной | fr | function_words, word_forms, number_pattern, time_pattern |
| `native.gendered_past` | родной | fr | gendered_past_pattern |

### Судья швов

Судья не звался: урок не прошёл порог.

### Находки с пакетами LANG-1 (повторная проверка, без вызовов)

Фатальных: 4 → 43 · предупреждений: 1 → 41 · не проверено кодов (родной/целевой): 9/0 → 0/0 · пакеты сейчас: be, de, en, es, fr, it, pl, ro, ru, uk

Коды: появились pronunciation.foreign_script×40, pronunciation.script×40; ушли options.form_mismatch×1.

| ± код | порог | адрес | что |
|---|---|---|---|
| + `pronunciation.foreign_script` | **фатальная** | p1 | the reading «айд лайк ту мейк ___» is spelled with letters of another writing: «а», «й», «д», «л», «к», «т», «у», «м», «е» |
| + `pronunciation.script` | предупреждение | p1 | the reading «айд лайк ту мейк ___» leaves the native script |
| + `pronunciation.foreign_script` | **фатальная** | p1.f1 | the reading «эн эпойнтмэнт» is spelled with letters of another writing: «э», «н», «п», «о», «й», «т», «м» |
| + `pronunciation.script` | предупреждение | p1.f1 | the reading «эн эпойнтмэнт» leaves the native script |
| + `pronunciation.foreign_script` | **фатальная** | p1.f2 | the reading «э сейм-дей эпойнтмэнт» is spelled with letters of another writing: «э», «с», «е», «й», «м», «д», «п», «о», «н», «т» |
| + `pronunciation.script` | предупреждение | p1.f2 | the reading «э сейм-дей эпойнтмэнт» leaves the native script |
| + `pronunciation.foreign_script` | **фатальная** | p2 | the reading «итс фор ___» is spelled with letters of another writing: «и», «т», «с», «ф», «о», «р» |
| + `pronunciation.script` | предупреждение | p2 | the reading «итс фор ___» leaves the native script |
| + `pronunciation.foreign_script` | **фатальная** | p2.f1 | the reading «э сор сроут» is spelled with letters of another writing: «э», «с», «о», «р», «у», «т» |
| + `pronunciation.script` | предупреждение | p2.f1 | the reading «э сор сроут» leaves the native script |
| + `pronunciation.foreign_script` | **фатальная** | p2.f2 | the reading «э фивэр» is spelled with letters of another writing: «э», «ф», «и», «в», «р» |
| + `pronunciation.script` | предупреждение | p2.f2 | the reading «э фивэр» leaves the native script |
| + `pronunciation.foreign_script` | **фатальная** | p2.f3 | the reading «э коф» is spelled with letters of another writing: «э», «к», «о», «ф» |
| + `pronunciation.script` | предупреждение | p2.f3 | the reading «э коф» leaves the native script |
| + `pronunciation.foreign_script` | **фатальная** | p3 | the reading «айв хэд ит фор ___» is spelled with letters of another writing: «а», «й», «в», «х», «э», «д», «и», «т», «ф», «о», «р» |
| + `pronunciation.script` | предупреждение | p3 | the reading «айв хэд ит фор ___» leaves the native script |
| + `pronunciation.foreign_script` | **фатальная** | p3.f1 | the reading «сри дейз» is spelled with letters of another writing: «с», «р», «и», «д», «е», «й», «з» |
| + `pronunciation.script` | предупреждение | p3.f1 | the reading «сри дейз» leaves the native script |
| + `pronunciation.foreign_script` | **фатальная** | p3.f2 | the reading «ту дейз» is spelled with letters of another writing: «т», «у», «д», «е», «й», «з» |
| + `pronunciation.script` | предупреждение | p3.f2 | the reading «ту дейз» leaves the native script |
| + `pronunciation.foreign_script` | **фатальная** | p3.f3 | the reading «э уик» is spelled with letters of another writing: «э», «у», «и», «к» |
| + `pronunciation.script` | предупреждение | p3.f3 | the reading «э уик» leaves the native script |
| + `pronunciation.foreign_script` | **фатальная** | p4 | the reading «уот таймз ар эвейлэбл ___» is spelled with letters of another writing: «у», «о», «т», «а», «й», «м», «з», «р», «э», «в», «е», «л», «б» |
| + `pronunciation.script` | предупреждение | p4 | the reading «уот таймз ар эвейлэбл ___» leaves the native script |
| + `pronunciation.foreign_script` | **фатальная** | p4.f1 | the reading «тудей» is spelled with letters of another writing: «т», «у», «д», «е», «й» |
| + `pronunciation.script` | предупреждение | p4.f1 | the reading «тудей» leaves the native script |
| + `pronunciation.foreign_script` | **фатальная** | p4.f2 | the reading «тумороу» is spelled with letters of another writing: «т», «у», «м», «о», «р» |
| + `pronunciation.script` | предупреждение | p4.f2 | the reading «тумороу» leaves the native script |
| + `pronunciation.foreign_script` | **фатальная** | p5 | the reading «___ уоркс фор ми» is spelled with letters of another writing: «у», «о», «р», «к», «с», «ф», «м», «и» |
| + `pronunciation.script` | предупреждение | p5 | the reading «___ уоркс фор ми» leaves the native script |
| + `pronunciation.foreign_script` | **фатальная** | p5.f1 | the reading «сри пи эм» is spelled with letters of another writing: «с», «р», «и», «п», «э», «м» |
| + `pronunciation.script` | предупреждение | p5.f1 | the reading «сри пи эм» leaves the native script |
| + `pronunciation.foreign_script` | **фатальная** | p5.f2 | the reading «тен эй эм» is spelled with letters of another writing: «т», «е», «н», «э», «й», «м» |
| + `pronunciation.script` | предупреждение | p5.f2 | the reading «тен эй эм» leaves the native script |
| + `pronunciation.foreign_script` | **фатальная** | p6 | the reading «май нейм из ___» is spelled with letters of another writing: «м», «а», «й», «н», «е», «и», «з» |
| + `pronunciation.script` | предупреждение | p6 | the reading «май нейм из ___» leaves the native script |
| + `pronunciation.foreign_script` | **фатальная** | p6.f1 | the reading «мари дюпон» is spelled with letters of another writing: «м», «а», «р», «и», «д», «ю», «п», «о», «н» |
| + `pronunciation.script` | предупреждение | p6.f1 | the reading «мари дюпон» leaves the native script |
| + `pronunciation.foreign_script` | **фатальная** | p6.f2 | the reading «пол мартен» is spelled with letters of another writing: «п», «о», «л», «м», «а», «р», «т», «е», «н» |
| + `pronunciation.script` | предупреждение | p6.f2 | the reading «пол мартен» leaves the native script |
| + `pronunciation.foreign_script` | **фатальная** | p7 | the reading «май фоун намбэр из ___» is spelled with letters of another writing: «м», «а», «й», «ф», «о», «у», «н», «б», «э», «р», «и», «з» |
| + `pronunciation.script` | предупреждение | p7 | the reading «май фоун намбэр из ___» leaves the native script |
| + `pronunciation.foreign_script` | **фатальная** | p7.f1 | the reading «зиро сикс уан ту сёрти-фор фифти-сикс севенти-эйт» is spelled with letters of another writing: «з», «и», «р», «о», «с», «к», «у», «а», «н», «т», «ё», «ф», «е», «в», «э», «й» |
| + `pronunciation.script` | предупреждение | p7.f1 | the reading «зиро сикс уан ту сёрти-фор фифти-сикс севенти-эйт» leaves the native script |
| + `pronunciation.foreign_script` | **фатальная** | p7.f2 | the reading «зиро севен форти-файв илевен тенти-ту сёрти-сри» is spelled with letters of another writing: «з», «и», «р», «о», «с», «е», «в», «н», «ф», «т», «а», «й», «л», «у», «ё» |
| + `pronunciation.script` | предупреждение | p7.f2 | the reading «зиро севен форти-файв илевен тенти-ту сёрти-сри» leaves the native script |
| + `pronunciation.foreign_script` | **фатальная** | p8 | the reading «сэнк ю, си ю зэн» is spelled with letters of another writing: «с», «э», «н», «к», «ю», «и», «з» |
| + `pronunciation.script` | предупреждение | p8 | the reading «сэнк ю, си ю зэн» leaves the native script |
| + `pronunciation.foreign_script` | **фатальная** | v1 | the reading «эпойнтмэнт» is spelled with letters of another writing: «э», «п», «о», «й», «н», «т», «м» |
| + `pronunciation.script` | предупреждение | v1 | the reading «эпойнтмэнт» leaves the native script |
| + `pronunciation.foreign_script` | **фатальная** | v2 | the reading «сор сроут» is spelled with letters of another writing: «с», «о», «р», «у», «т» |
| + `pronunciation.script` | предупреждение | v2 | the reading «сор сроут» leaves the native script |
| + `pronunciation.foreign_script` | **фатальная** | v3 | the reading «фивэр» is spelled with letters of another writing: «ф», «и», «в», «э», «р» |
| + `pronunciation.script` | предупреждение | v3 | the reading «фивэр» leaves the native script |
| + `pronunciation.foreign_script` | **фатальная** | v4 | the reading «эвейлэбл» is spelled with letters of another writing: «э», «в», «е», «й», «л», «б» |
| + `pronunciation.script` | предупреждение | v4 | the reading «эвейлэбл» leaves the native script |
| + `pronunciation.foreign_script` | **фатальная** | v5 | the reading «уоркс фор ми» is spelled with letters of another writing: «у», «о», «р», «к», «с», «ф», «м», «и» |
| + `pronunciation.script` | предупреждение | v5 | the reading «уоркс фор ми» leaves the native script |
| + `pronunciation.foreign_script` | **фатальная** | v6 | the reading «фул нейм» is spelled with letters of another writing: «ф», «у», «л», «н», «е», «й», «м» |
| + `pronunciation.script` | предупреждение | v6 | the reading «фул нейм» leaves the native script |
| + `pronunciation.foreign_script` | **фатальная** | v7 | the reading «фоун намбэр» is spelled with letters of another writing: «ф», «о», «у», «н», «а», «м», «б», «э», «р» |
| + `pronunciation.script` | предупреждение | v7 | the reading «фоун намбэр» leaves the native script |
| + `pronunciation.foreign_script` | **фатальная** | v8 | the reading «букт» is spelled with letters of another writing: «б», «у», «к», «т» |
| + `pronunciation.script` | предупреждение | v8 | the reading «букт» leaves the native script |
| + `pronunciation.foreign_script` | **фатальная** | B1 | the reading «айд лайк ту мейк эн эпойнтмэнт» is spelled with letters of another writing: «а», «й», «д», «л», «к», «т», «у», «м», «е», «э», «н», «п», «о» |
| + `pronunciation.script` | предупреждение | B1 | the reading «айд лайк ту мейк эн эпойнтмэнт» leaves the native script |
| + `pronunciation.foreign_script` | **фатальная** | B2 | the reading «итс фор э сор сроут» is spelled with letters of another writing: «и», «т», «с», «ф», «о», «р», «э», «у» |
| + `pronunciation.script` | предупреждение | B2 | the reading «итс фор э сор сроут» leaves the native script |
| + `pronunciation.foreign_script` | **фатальная** | B3 | the reading «айв хэд ит фор сри дейз» is spelled with letters of another writing: «а», «й», «в», «х», «э», «д», «и», «т», «ф», «о», «р», «с», «е», «з» |
| + `pronunciation.script` | предупреждение | B3 | the reading «айв хэд ит фор сри дейз» leaves the native script |
| + `pronunciation.foreign_script` | **фатальная** | B4 | the reading «уот таймз ар эвейлэбл тудей» is spelled with letters of another writing: «у», «о», «т», «а», «й», «м», «з», «р», «э», «в», «е», «л», «б», «д» |
| + `pronunciation.script` | предупреждение | B4 | the reading «уот таймз ар эвейлэбл тудей» leaves the native script |
| + `pronunciation.foreign_script` | **фатальная** | B5 | the reading «сри пи эм уоркс фор ми» is spelled with letters of another writing: «с», «р», «и», «п», «э», «м», «у», «о», «к», «ф» |
| + `pronunciation.script` | предупреждение | B5 | the reading «сри пи эм уоркс фор ми» leaves the native script |
| + `pronunciation.foreign_script` | **фатальная** | B6 | the reading «май нейм из мари дюпон» is spelled with letters of another writing: «м», «а», «й», «н», «е», «и», «з», «р», «д», «ю», «п», «о» |
| + `pronunciation.script` | предупреждение | B6 | the reading «май нейм из мари дюпон» leaves the native script |
| + `pronunciation.foreign_script` | **фатальная** | B7 | the reading «май фоун намбэр из зиро сикс уан ту сёрти-фор фифти-сикс севенти-эйт» is spelled with letters of another writing: «м», «а», «й», «ф», «о», «у», «н», «б», «э», «р», «и», «з», «с», «к», «т», «ё», «е», «в» |
| + `pronunciation.script` | предупреждение | B7 | the reading «май фоун намбэр из зиро сикс уан ту сёрти-фор фифти-сикс севенти-эйт» leaves the native script |
| + `pronunciation.foreign_script` | **фатальная** | B8 | the reading «сэнк ю, си ю зэн» is spelled with letters of another writing: «с», «э», «н», «к», «ю», «и», «з» |
| + `pronunciation.script` | предупреждение | B8 | the reading «сэнк ю, си ю зэн» leaves the native script |
| − `options.form_mismatch` | **фатальная** | x4.check | the option «9 h du matin et midi» starts lower-case |
