# LANG-1 · Español→English (es→en, начальный)

Цель плана (слова ученика): «Pido cita con el médico: llevo tres días con dolor de garganta y fiebre. Tengo que elegir una hora que me venga bien y explicar qué me pasa»

Роль ученика в плане: Patient / Paciente. Сцена 1: «Consulta médica» (Doctor / Médico); сцена 2: «Pedir la cita» (Receptionist / Recepcionista).

> День 1 — урок, каким его получил бы ученик (прошедший порог, как записан в сцену; при `failed` — ответ модели как написан), прочитанный сервером: наполнение реплики найдено по её тексту. «Чтение» — как модель записала звучание целевого текста буквами родного. Находки — по ответу модели ДО починок, валидатором кода прогона в контексте боя этой пары. «Не проверено» — проверки, для которых у пакета языка нет ключей (`lang.pack_missing`, не находка). Цена — вызовы плана и дня по одному разу. Факты сюжета и роли код не проверяет.

Итог: **failed** (fatal: pronunciation.foreign_script, options.form_mismatch) · починок P2R: 4 (p1: pronunciation.foreign_script, pronunciation.script; p2: pronunciation.foreign_script, pronunciation.script; p1: pronunciation.foreign_script, pronunciation.script; p2: pronunciation.foreign_script, pronunciation.script) · вызовов урока: 2 · план $0.0112 · 6.2 с · день $0.1540 (урок $0.1147 · починки $0.0393 · судья $0.0000) · из кэша 66 % входа · 53.2 с · всего $0.1652 · ученик: Paciente · собеседник: Médico (женщина)

Промты: план `plan-builder-v2` · урок `lesson_day.v4.9` · починка `lesson_card_repair.v1.3` · судья `lesson_seam_judge.v1.1` · модель урока `gpt-5.4-2026-03-05` · попыток урока: 2 · в сцене записано $0.154021 (урок, починки и судья — сверка, не слагаемое)

### Сценарий диалога

| # | вид обмена | кто | реплика | перевод | чтение | каркас · наполнение |
|---|---|---|---|---|---|---|
| 1 | ответ | Médico (собеседник) | What seems to be the problem today? | ¿Qué te pasa hoy? |  |  |
| 1 | ответ | Paciente (ученик) | I have a sore throat. | Tengo dolor de garganta. | Ай хав э сор сроут. | p1 · a sore throat |
| 2 | ответ | Médico (собеседник) | How long have you had it? | ¿Cuánto tiempo llevas con eso? |  |  |
| 2 | ответ | Paciente (ученик) | It's been for three days. | Llevo tres días con esto. | Итс бин фор сри дейз. | p2 · for three days |
| 3 | ответ | Médico (собеседник) | Do you also have a fever? | ¿También tienes fiebre? |  |  |
| 3 | ответ | Paciente (ученик) | I have a fever. | Tengo fiebre. | Ай хав э фивер. | p1 · a fever |
| 4 | вопрос ученика | Paciente (ученик) | Can you check my throat? | ¿Puede mirarme la garganta? | Кэн ю чек май сроут? | p3 · my throat |
| 4 | вопрос ученика | Médico (собеседник) | Yes. Open your mouth, please. | Sí. Abre la boca, por favor. |  |  |
| 5 | ответ | Médico (собеседник) | Your throat is red, but your lungs sound clear. | Tienes la garganta roja, pero los pulmones suenan bien. |  |  |
| 5 | ответ | Paciente (ученик) | My throat feels red. | Siento la garganta irritada. | Май сроут филз ред. | p4 · red |
| 6 | вопрос ученика | Paciente (ученик) | Do I need medicine? | ¿Necesito medicina? | Ду ай нийд медисин? | p5 · medicine |
| 6 | вопрос ученика | Médico (собеседник) | Yes, take paracetamol and drink plenty of water. | Sí, toma paracetamol y bebe mucha agua. |  |  |
| 7 | переспрос | Paciente (ученик) | Could you say that more slowly, please? | ¿Puede decirlo más despacio, por favor? | Куд ю сей зат мор слоули, плиз? | — |
| 7 | переспрос | Médico (собеседник) | Take paracetamol and drink lots of water. | Toma paracetamol y bebe mucha agua. |  |  |
| 8 | вопрос ученика | Paciente (ученик) | Can I go to work? | ¿Puedo ir a trabajar? | Кэн ай гоу ту уорк? | p6 · to work |
| 8 | вопрос ученика | Médico (собеседник) | Rest at home tomorrow if the fever continues. | Descansa en casa mañana si la fiebre continúa. |  |  |

### Каркасы

| id | вид | каркас | на родном | чтение | наполнения (в диалоге — жирным) |
|---|---|---|---|---|---|
| p1 | ответ | I have ___. | Tengo ___. | Ай хав ___. | **a sore throat** / dolor de garganta · **a fever** / fiebre · a cough / tos |
| p2 | ответ | It's been ___. | Llevo ___. | Итс бин ___. | **for three days** / tres días · since Monday / desde el lunes · since yesterday / desde ayer |
| p3 | вопрос ученика | Can you check ___? | ¿Puede mirarme ___? | Кэн ю чек ___? | **my throat** / la garganta · my ear / el oído · my neck / el cuello |
| p4 | ответ | My throat feels ___. | Siento la garganta ___. | Май сроут филз ___. | **red** / irritada · dry / seca · swollen / hinchada |
| p5 | вопрос ученика | Do I need ___? | ¿Necesito ___? | Ду ай нийд ___? | **medicine** / medicina · a test / una prueba · antibiotics / antibióticos |
| p6 | вопрос ученика | Can I go ___? | ¿Puedo ir ___? | Кэн ай гоу ___? | **to work** / a trabajar · to school / a clase · to the gym / al gimnasio |

### Наполнения

| каркас | наполнение | перевод | чтение | в диалоге | собранная фраза (родной) | судья швов |
|---|---|---|---|---|---|---|
| p1 | a sore throat | dolor de garganta | э сор сроут | да | Tengo dolor de garganta. | — |
| p1 | a fever | fiebre | э фивер | да | Tengo fiebre. | — |
| p1 | a cough | tos | э коф | — | Tengo tos. | — |
| p2 | for three days | tres días | фор сри дейз | да | Llevo tres días. | — |
| p2 | since Monday | desde el lunes | синс мандей | — | Llevo desde el lunes. | — |
| p2 | since yesterday | desde ayer | синс йестердей | — | Llevo desde ayer. | — |
| p3 | my throat | la garganta | май сроут | да | ¿Puede mirarme la garganta? | — |
| p3 | my ear | el oído | май ир | — | ¿Puede mirarme el oído? | — |
| p3 | my neck | el cuello | май нек | — | ¿Puede mirarme el cuello? | — |
| p4 | red | irritada | ред | да | Siento la garganta irritada. | — |
| p4 | dry | seca | драй | — | Siento la garganta seca. | — |
| p4 | swollen | hinchada | своулин | — | Siento la garganta hinchada. | — |
| p5 | medicine | medicina | медисин | да | ¿Necesito medicina? | — |
| p5 | a test | una prueba | э тест | — | ¿Necesito una prueba? | — |
| p5 | antibiotics | antibióticos | антибайотикс | — | ¿Necesito antibióticos? | — |
| p6 | to work | a trabajar | ту уорк | да | ¿Puedo ir a trabajar? | — |
| p6 | to school | a clase | ту скул | — | ¿Puedo ir a clase? | — |
| p6 | to the gym | al gimnasio | ту зе йим | — | ¿Puedo ir al gimnasio? | — |

### Проверки обменов

| # | вопрос | варианты (✓ — верный) |
|---|---|---|
| 1 | What does the doctor ask about? / ¿Qué pregunta la doctora? | ✓ Your main problem today / Tu problema principal de hoy · Your usual medicine / Tu medicina habitual · Your next appointment / Tu próxima cita |
| 2 | What time period does the doctor ask about? / ¿Qué periodo de tiempo pregunta la doctora? | When you last ate / Cuándo comiste por última vez · ✓ How long the problem has lasted / Cuánto tiempo ha durado el problema · How often you work / Con qué frecuencia trabajas |
| 3 | Which extra symptom does the doctor ask about? / ¿Qué síntoma adicional pregunta la doctora? | ✓ A high temperature / Temperatura alta · A cough / Tos · Ear pain / Dolor de oído |
| 4 | What does the doctor ask you to do? / ¿Qué te pide la doctora que hagas? | Take off your coat / Quitarte el abrigo · ✓ Open your mouth / Abrir la boca · Sit near the window / Sentarte cerca de la ventana |
| 5 | What does the doctor say about your lungs? / ¿Qué dice la doctora sobre tus pulmones? | ✓ They seem fine / Parecen estar bien · They sound blocked / Suenan congestionados · They need an X-ray / Necesitan una radiografía |
| 6 | What does the doctor tell you to drink? / ¿Qué te dice la doctora que bebas? | Warm milk / Leche caliente · ✓ A lot of water / Mucha agua · Orange juice / Zumo de naranja |
| 7 | Which medicine does the doctor repeat? / ¿Qué medicina repite la doctora? | Ibuprofen / Ibuprofeno · Cough syrup / Jarabe para la tos · ✓ Paracetamol / Paracetamol |
| 8 | When should you stay home? / ¿Cuándo debes quedarte en casa? | ✓ If the fever is still there tomorrow / Si mañana sigues con fiebre · Only this evening / Solo esta tarde · After your next visit / Después de tu próxima visita |

### Слушаю весь визит

- L1. ¿Cuánto tiempo lleva la paciente con el problema? — Desde ayer · ✓ Tres días · Una semana
- L2. ¿Qué le dice la doctora sobre los pulmones? — ✓ Que suenan bien · Que están infectados · Que necesita una radiografía
- L3. ¿Qué medicina recomienda la doctora? — Ibuprofeno · Jarabe para la tos · ✓ Paracetamol
- L4. ¿A dónde pregunta la paciente si puede ir? — ✓ A trabajar · A clase · Al gimnasio

### Словарь

| id | слово | вид | перевод | чтение | где звучит |
|---|---|---|---|---|---|
| v1 | sore throat | связка | dolor de garganta | сор сроут | p1 |
| v2 | fever | слово | fiebre | фивер | A3, p1, A8 |
| v3 | for three days | связка | durante tres días | фор сри дейз | p2 |
| v4 | open your mouth | связка | abre la boca | оупен йор маус | A4 |
| v5 | lungs | слово | pulmones | лангз | A5 |
| v6 | paracetamol | слово | paracetamol | параситамол | A6, A7 |
| v7 | plenty of water | связка | mucha agua | пленти ов уотер | A6 |
| v8 | rest at home | связка | descansar en casa | рест эт хоум | A8 |

### Находки в ответе модели (до починок)

| код | порог | адрес | что |
|---|---|---|---|
| `pronunciation.foreign_script` | **фатальная** | p1 | the reading «Ай хав ___.» is spelled with letters of another writing: «А», «й», «х», «а», «в» |
| `pronunciation.script` | предупреждение | p1 | the reading «Ай хав ___.» leaves the native script |
| `pronunciation.foreign_script` | **фатальная** | p1.f1 | the reading «э сор сроут» is spelled with letters of another writing: «э», «с», «о», «р», «у», «т» |
| `pronunciation.script` | предупреждение | p1.f1 | the reading «э сор сроут» leaves the native script |
| `pronunciation.foreign_script` | **фатальная** | p1.f2 | the reading «э фивер» is spelled with letters of another writing: «э», «ф», «и», «в», «е», «р» |
| `pronunciation.script` | предупреждение | p1.f2 | the reading «э фивер» leaves the native script |
| `pronunciation.foreign_script` | **фатальная** | p1.f3 | the reading «э коф» is spelled with letters of another writing: «э», «к», «о», «ф» |
| `pronunciation.script` | предупреждение | p1.f3 | the reading «э коф» leaves the native script |
| `pronunciation.foreign_script` | **фатальная** | p2 | the reading «Итс бин ___.» is spelled with letters of another writing: «И», «т», «с», «б», «и», «н» |
| `pronunciation.script` | предупреждение | p2 | the reading «Итс бин ___.» leaves the native script |
| `pronunciation.foreign_script` | **фатальная** | p2.f1 | the reading «фор сри дейз» is spelled with letters of another writing: «ф», «о», «р», «с», «и», «д», «е», «й», «з» |
| `pronunciation.script` | предупреждение | p2.f1 | the reading «фор сри дейз» leaves the native script |
| `pronunciation.foreign_script` | **фатальная** | p2.f2 | the reading «синс мандей» is spelled with letters of another writing: «с», «и», «н», «м», «а», «д», «е», «й» |
| `pronunciation.script` | предупреждение | p2.f2 | the reading «синс мандей» leaves the native script |
| `pronunciation.foreign_script` | **фатальная** | p2.f3 | the reading «синс йестердей» is spelled with letters of another writing: «с», «и», «н», «й», «е», «т», «р», «д» |
| `pronunciation.script` | предупреждение | p2.f3 | the reading «синс йестердей» leaves the native script |
| `pronunciation.foreign_script` | **фатальная** | p3 | the reading «Кэн ю чек ___?» is spelled with letters of another writing: «К», «э», «н», «ю», «ч», «е», «к» |
| `pronunciation.script` | предупреждение | p3 | the reading «Кэн ю чек ___?» leaves the native script |
| `pronunciation.foreign_script` | **фатальная** | p3.f1 | the reading «май сроут» is spelled with letters of another writing: «м», «а», «й», «с», «р», «о», «у», «т» |
| `pronunciation.script` | предупреждение | p3.f1 | the reading «май сроут» leaves the native script |
| `pronunciation.foreign_script` | **фатальная** | p3.f2 | the reading «май ир» is spelled with letters of another writing: «м», «а», «й», «и», «р» |
| `pronunciation.script` | предупреждение | p3.f2 | the reading «май ир» leaves the native script |
| `pronunciation.foreign_script` | **фатальная** | p3.f3 | the reading «май нек» is spelled with letters of another writing: «м», «а», «й», «н», «е», «к» |
| `pronunciation.script` | предупреждение | p3.f3 | the reading «май нек» leaves the native script |
| `pronunciation.foreign_script` | **фатальная** | p4 | the reading «Май сроут филз ___.» is spelled with letters of another writing: «М», «а», «й», «с», «р», «о», «у», «т», «ф», «и», «л», «з» |
| `pronunciation.script` | предупреждение | p4 | the reading «Май сроут филз ___.» leaves the native script |
| `pronunciation.foreign_script` | **фатальная** | p4.f1 | the reading «ред» is spelled with letters of another writing: «р», «е», «д» |
| `pronunciation.script` | предупреждение | p4.f1 | the reading «ред» leaves the native script |
| `pronunciation.foreign_script` | **фатальная** | p4.f2 | the reading «драй» is spelled with letters of another writing: «д», «р», «а», «й» |
| `pronunciation.script` | предупреждение | p4.f2 | the reading «драй» leaves the native script |
| `pronunciation.foreign_script` | **фатальная** | p4.f3 | the reading «своулин» is spelled with letters of another writing: «с», «в», «о», «у», «л», «и», «н» |
| `pronunciation.script` | предупреждение | p4.f3 | the reading «своулин» leaves the native script |
| `pronunciation.foreign_script` | **фатальная** | p5 | the reading «Ду ай нийд ___?» is spelled with letters of another writing: «Д», «у», «а», «й», «н», «и», «д» |
| `pronunciation.script` | предупреждение | p5 | the reading «Ду ай нийд ___?» leaves the native script |
| `pronunciation.foreign_script` | **фатальная** | p5.f1 | the reading «медисин» is spelled with letters of another writing: «м», «е», «д», «и», «с», «н» |
| `pronunciation.script` | предупреждение | p5.f1 | the reading «медисин» leaves the native script |
| `pronunciation.foreign_script` | **фатальная** | p5.f2 | the reading «э тест» is spelled with letters of another writing: «э», «т», «е», «с» |
| `pronunciation.script` | предупреждение | p5.f2 | the reading «э тест» leaves the native script |
| `pronunciation.foreign_script` | **фатальная** | p5.f3 | the reading «антибайотикс» is spelled with letters of another writing: «а», «н», «т», «и», «б», «й», «о», «к», «с» |
| `pronunciation.script` | предупреждение | p5.f3 | the reading «антибайотикс» leaves the native script |
| `pronunciation.foreign_script` | **фатальная** | p6 | the reading «Кэн ай гоу ___?» is spelled with letters of another writing: «К», «э», «н», «а», «й», «г», «о», «у» |
| `pronunciation.script` | предупреждение | p6 | the reading «Кэн ай гоу ___?» leaves the native script |
| `pronunciation.foreign_script` | **фатальная** | p6.f1 | the reading «ту уорк» is spelled with letters of another writing: «т», «у», «о», «р», «к» |
| `pronunciation.script` | предупреждение | p6.f1 | the reading «ту уорк» leaves the native script |
| `pronunciation.foreign_script` | **фатальная** | p6.f2 | the reading «ту скул» is spelled with letters of another writing: «т», «у», «с», «к», «л» |
| `pronunciation.script` | предупреждение | p6.f2 | the reading «ту скул» leaves the native script |
| `pronunciation.foreign_script` | **фатальная** | p6.f3 | the reading «ту зе йим» is spelled with letters of another writing: «т», «у», «з», «е», «й», «и», «м» |
| `pronunciation.script` | предупреждение | p6.f3 | the reading «ту зе йим» leaves the native script |
| `pronunciation.foreign_script` | **фатальная** | v1 | the reading «сор сроут» is spelled with letters of another writing: «с», «о», «р», «у», «т» |
| `pronunciation.script` | предупреждение | v1 | the reading «сор сроут» leaves the native script |
| `pronunciation.foreign_script` | **фатальная** | v2 | the reading «фивер» is spelled with letters of another writing: «ф», «и», «в», «е», «р» |
| `pronunciation.script` | предупреждение | v2 | the reading «фивер» leaves the native script |
| `pronunciation.foreign_script` | **фатальная** | v3 | the reading «фор сри дейз» is spelled with letters of another writing: «ф», «о», «р», «с», «и», «д», «е», «й», «з» |
| `pronunciation.script` | предупреждение | v3 | the reading «фор сри дейз» leaves the native script |
| `pronunciation.foreign_script` | **фатальная** | v4 | the reading «оупен йор маус» is spelled with letters of another writing: «о», «у», «п», «е», «н», «й», «р», «м», «а», «с» |
| `pronunciation.script` | предупреждение | v4 | the reading «оупен йор маус» leaves the native script |
| `pronunciation.foreign_script` | **фатальная** | v5 | the reading «лангз» is spelled with letters of another writing: «л», «а», «н», «г», «з» |
| `pronunciation.script` | предупреждение | v5 | the reading «лангз» leaves the native script |
| `pronunciation.foreign_script` | **фатальная** | v6 | the reading «параситамол» is spelled with letters of another writing: «п», «а», «р», «с», «и», «т», «м», «о», «л» |
| `pronunciation.script` | предупреждение | v6 | the reading «параситамол» leaves the native script |
| `pronunciation.foreign_script` | **фатальная** | v7 | the reading «пленти ов уотер» is spelled with letters of another writing: «п», «л», «е», «н», «т», «и», «о», «в», «у», «р» |
| `pronunciation.script` | предупреждение | v7 | the reading «пленти ов уотер» leaves the native script |
| `pronunciation.foreign_script` | **фатальная** | v8 | the reading «рест эт хоум» is spelled with letters of another writing: «р», «е», «с», «т», «э», «х», «о», «у», «м» |
| `pronunciation.script` | предупреждение | v8 | the reading «рест эт хоум» leaves the native script |
| `pronunciation.foreign_script` | **фатальная** | B1 | the reading «Ай хав э сор сроут.» is spelled with letters of another writing: «А», «й», «х», «а», «в», «э», «с», «о», «р», «у», «т» |
| `pronunciation.script` | предупреждение | B1 | the reading «Ай хав э сор сроут.» leaves the native script |
| `pronunciation.foreign_script` | **фатальная** | B2 | the reading «Итс бин фор сри дейз.» is spelled with letters of another writing: «И», «т», «с», «б», «и», «н», «ф», «о», «р», «д», «е», «й», «з» |
| `pronunciation.script` | предупреждение | B2 | the reading «Итс бин фор сри дейз.» leaves the native script |
| `pronunciation.foreign_script` | **фатальная** | B3 | the reading «Ай хав э фивер.» is spelled with letters of another writing: «А», «й», «х», «а», «в», «э», «ф», «и», «е», «р» |
| `pronunciation.script` | предупреждение | B3 | the reading «Ай хав э фивер.» leaves the native script |
| `pronunciation.foreign_script` | **фатальная** | B4 | the reading «Кэн ю чек май сроут?» is spelled with letters of another writing: «К», «э», «н», «ю», «ч», «е», «к», «м», «а», «й», «с», «р», «о», «у», «т» |
| `pronunciation.script` | предупреждение | B4 | the reading «Кэн ю чек май сроут?» leaves the native script |
| `pronunciation.foreign_script` | **фатальная** | B5 | the reading «Май сроут филз ред.» is spelled with letters of another writing: «М», «а», «й», «с», «р», «о», «у», «т», «ф», «и», «л», «з», «е», «д» |
| `pronunciation.script` | предупреждение | B5 | the reading «Май сроут филз ред.» leaves the native script |
| `pronunciation.foreign_script` | **фатальная** | B6 | the reading «Ду ай нийд медисин?» is spelled with letters of another writing: «Д», «у», «а», «й», «н», «и», «д», «м», «е», «с» |
| `pronunciation.script` | предупреждение | B6 | the reading «Ду ай нийд медисин?» leaves the native script |
| `pronunciation.foreign_script` | **фатальная** | B7 | the reading «Куд ю сей зат мор слоули, плиз?» is spelled with letters of another writing: «К», «у», «д», «ю», «с», «е», «й», «з», «а», «т», «м», «о», «р», «л», «и», «п» |
| `pronunciation.script` | предупреждение | B7 | the reading «Куд ю сей зат мор слоули, плиз?» leaves the native script |
| `pronunciation.foreign_script` | **фатальная** | B8 | the reading «Кэн ай гоу ту уорк?» is spelled with letters of another writing: «К», «э», «н», «а», «й», «г», «о», «у», «т», «р», «к» |
| `pronunciation.script` | предупреждение | B8 | the reading «Кэн ай гоу ту уорк?» leaves the native script |
| `variant.longer` | предупреждение | B3 | the variant «Yes, I have a fever.» has 5 words, the line 4 |
| `variant.longer` | предупреждение | B6 | the variant «Do I need any medicine?» has 5 words, the line 4 |
| `check.verbatim` | предупреждение | x1.check | the right option «Your main problem today» repeats «problem today» of the partner's line |
| `check.verbatim` | предупреждение | x2.check | the right option «How long the problem has lasted» repeats «how long» of the partner's line |
| `options.form_mismatch` | **фатальная** | x3.check | the option «Tos» is 3 letters against 15 of the right «Temperatura alta» |
| `options.form_mismatch` | **фатальная** | x4.check | the option «Sentarte cerca de la ventana» is 24 letters against 11 of the right «Abrir la boca» |
| `options.partner_fragment` | предупреждение | x6.check | the option «Mucha agua» is a piece of the partner's line «Sí, toma paracetamol y bebe mucha agua.» |
| `options.partner_fragment` | предупреждение | x7.check | the option «Paracetamol» is a piece of the partner's line «Toma paracetamol y bebe mucha agua.» |
| `vocab.free_combination` | предупреждение | v3 | «for three days» is a free combination of ordinary words |
| `vocab.learner_share` | предупреждение | lesson | 3 of 8 items are in the learner's frames or fillers (at least half) |

### Не проверено — нет ключей пакета (lang.pack_missing, не находка)

Всё проверено: у пакетов обоих языков пары есть все нужные ключи. Контекст проверки: родной `es`, целевой `en`.

### Судья швов

Судья не звался: урок не прошёл порог.
