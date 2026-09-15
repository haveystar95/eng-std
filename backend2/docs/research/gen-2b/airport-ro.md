# GEN-2b · «Check-in aeroport» — airport-ro (ro→en, начальный)

Цель плана (слова ученика): «Check-in la zborul din aeroport: pașaport, bagaj, loc în avion. Zbor cu o valiză și un rucsac»

Ученик: pasager · собеседник: agentă de check-in (женщина)

Промт `lesson_day.v4.5` · модель `gpt-5.4-2026-03-05` · вызов урока $0.077212 · 35.0 с · токены вход/выход 7485/3900 · попыток урока: 1 · находок валидатора и судьи в ответе модели: 10 (фатальных 1) · порог в сборке: урок прошёл

> Колонка «оценка» пустая — ставит Ден: **✓** / **так не говорят** / **слишком длинно** / **не то слово**. Это ответ модели БЕЗ починок; реплики ученика — как их получит приложение (сервер собирает их из каркаса и наполнения); если модель написала иначе, её текст — в скобках. «Судья» — вердикт судьи швов о собранной фразе на родном.

## Сценарий диалога

| # | вид обмена | кто | реплика | перевод | каркас · наполнение | ключ | оценка |
|---|---|---|---|---|---|---|---|
| 1 | вопрос ученика | pasager (ученик) | I'm checking in for this flight. | Fac check-in pentru acest zbor. | p1 · this flight | checking in for | |
| 1 | вопрос ученика | agentă de check-in (собеседник) | Sure. May I see your passport? | Sigur. Pot să văd pașaportul? |  |  | |
| 2 | ответ | agentă de check-in (собеседник) | Thank you. Where are you flying today? | Mulțumesc. Unde zburați azi? |  |  | |
| 2 | ответ | pasager (ученик) | I'm flying to London. | Zbor la Londra. | p2 · to London | flying to | |
| 3 | ответ | agentă de check-in (собеседник) | How many bags do you have? | Câte bagaje aveți? |  |  | |
| 3 | ответ | pasager (ученик) | I have one suitcase and one backpack. | Am o valiză și un rucsac. | p3 · one suitcase and one backpack | I have | |
| 4 | ответ | agentă de check-in (собеседник) | Which bag are you checking? | Care bagaj îl dați la cală? |  |  | |
| 4 | ответ | pasager (ученик) | I'm checking this suitcase. | Dau la cală această valiză. | p4 · this suitcase | checking this | |
| 5 | вопрос ученика | pasager (ученик) | What is my seat? | Care este locul meu? | p5 · my seat | What is my | |
| 5 | вопрос ученика | agentă de check-in (собеседник) | Your seat is 14A, by the window. | Locul dumneavoastră este 14A, la geam. |  |  | |
| 6 | вопрос ученика | pasager (ученик) | Can I keep my backpack? | Pot să păstrez rucsacul meu? | p6 · my backpack | Can I keep | |
| 6 | вопрос ученика | agentă de check-in (собеседник) | Yes, take it as carry-on. | Da, luați-l ca bagaj de mână. |  |  | |
| 7 | ответ | agentă de check-in (собеседник) | Here is your boarding pass and baggage tag. | Iată cartea de îmbarcare și eticheta de bagaj. |  |  | |
| 7 | ответ | pasager (ученик) | Thank you for the baggage tag. | Mulțumesc pentru eticheta de bagaj. | p7 · the baggage tag | for the | |
| 8 | ответ | agentă de check-in (собеседник) | Boarding starts at gate 12 in forty minutes. | Îmbarcarea începe la poarta 12 în patruzeci de minute. |  |  | |
| 8 | ответ | pasager (ученик) | Okay, I'll go to gate 12. | Bine, merg la poarta 12. | p8 · gate 12 | I'll go to | |

## Каркасы

### p1 · вопрос ученика — «I'm checking in for ___.»

На родном: «Fac check-in pentru ___.» · чтение: «aim cec-king in for ___» · окно: «zborul sau cursa» · звучит в обменах: 1

| наполнение | перевод | чтение | в диалоге | собранная фраза | собранный перевод | судья | оценка |
|---|---|---|---|---|---|---|---|
| this flight | acest zbor | dhis flait | да | I'm checking in for this flight. | Fac check-in pentru acest zbor. | читается | |
| the morning flight | zborul de dimineață | dhă mor-ning flait | — | I'm checking in for the morning flight. | Fac check-in pentru zborul de dimineață. | читается | |

### p2 · ответ — «I'm flying ___.»

На родном: «Zbor ___.» · чтение: «aim flai-ing ___» · окно: «destinația» · звучит в обменах: 2

| наполнение | перевод | чтение | в диалоге | собранная фраза | собранный перевод | судья | оценка |
|---|---|---|---|---|---|---|---|
| to London | la Londra | tu lan-dăn | да | I'm flying to London. | Zbor la Londra. | читается | |
| to Madrid | la Madrid | tu mă-drid | — | I'm flying to Madrid. | Zbor la Madrid. | читается | |

### p3 · ответ — «I have ___.»

На родном: «Am ___.» · чтение: «ai hev ___» · окно: «bagajele» · звучит в обменах: 3

| наполнение | перевод | чтение | в диалоге | собранная фраза | собранный перевод | судья | оценка |
|---|---|---|---|---|---|---|---|
| one suitcase and one backpack | o valiză și un rucsac | uan suit-ceis ænd uan bec-pac | да | I have one suitcase and one backpack. | Am o valiză și un rucsac. | читается | |
| one suitcase and one carry-on | o valiză și un bagaj de mână | uan suit-ceis ænd uan ce-ri on | — | I have one suitcase and one carry-on. | Am o valiză și un bagaj de mână. | читается | |

### p4 · ответ — «I'm checking ___.»

На родном: «Dau la cală ___.» · чтение: «aim cec-king ___» · окно: «bagajul» · звучит в обменах: 4

| наполнение | перевод | чтение | в диалоге | собранная фраза | собранный перевод | судья | оценка |
|---|---|---|---|---|---|---|---|
| this suitcase | această valiză | dhis suit-ceis | да | I'm checking this suitcase. | Dau la cală această valiză. | читается | |
| the large bag | geanta mare | dhă larj beg | — | I'm checking the large bag. | Dau la cală geanta mare. | читается | |

### p5 · вопрос ученика — «What is ___?»

На родном: «Care este ___?» · чтение: «uat iz ___» · окно: «detaliul dorit» · звучит в обменах: 5

| наполнение | перевод | чтение | в диалоге | собранная фраза | собранный перевод | судья | оценка |
|---|---|---|---|---|---|---|---|
| my seat | locul meu | mai siit | да | What is my seat? | Care este locul meu? | читается | |
| my gate | poarta mea | mai geit | — | What is my gate? | Care este poarta mea? | читается | |

### p6 · вопрос ученика — «Can I keep ___?»

На родном: «Pot să păstrez ___?» · чтение: «cen ai kiip ___» · окно: «obiectul sau bagajul» · звучит в обменах: 6

| наполнение | перевод | чтение | в диалоге | собранная фраза | собранный перевод | судья | оценка |
|---|---|---|---|---|---|---|---|
| my backpack | rucsacul meu | mai bec-pac | да | Can I keep my backpack? | Pot să păstrez rucsacul meu? | читается | |
| this small bag | această geantă mică | dhis smol beg | — | Can I keep this small bag? | Pot să păstrez această geantă mică? | читается | |

### p7 · ответ — «Thank you for ___.»

На родном: «Mulțumesc pentru ___.» · чтение: «thenc iu for ___» · окно: «lucrul primit» · звучит в обменах: 7

| наполнение | перевод | чтение | в диалоге | собранная фраза | собранный перевод | судья | оценка |
|---|---|---|---|---|---|---|---|
| the baggage tag | eticheta de bagaj | dhă beg-ij teg | да | Thank you for the baggage tag. | Mulțumesc pentru eticheta de bagaj. | читается | |
| the boarding pass | cartea de îmbarcare | dhă bor-ding pas | — | Thank you for the boarding pass. | Mulțumesc pentru cartea de îmbarcare. | **не читается** | |

### p8 · ответ — «I'll go to ___.»

На родном: «Merg la ___.» · чтение: «ail gou tu ___» · окно: «poarta sau locul» · звучит в обменах: 8

| наполнение | перевод | чтение | в диалоге | собранная фраза | собранный перевод | судья | оценка |
|---|---|---|---|---|---|---|---|
| gate 12 | poarta 12 | geit tuelv | да | I'll go to gate 12. | Merg la poarta 12. | читается | |
| gate 8 | poarta 8 | geit eit | — | I'll go to gate 8. | Merg la poarta 8. | читается | |

## Проверки обменов

| # | вопрос | варианты (✓ — верный) | пояснение | оценка |
|---|---|---|---|---|
| 1 | What document does the agent ask for? / Ce document cere agenta? | A visa form / Un formular de viză · ✓ A passport / Un pașaport · A boarding pass / Un bilet de îmbarcare | Agenta cere pașaportul, nu alt document. | |
| 2 | What does the agent want to know? / Ce vrea să știe agenta? | Your seat number / Numărul locului tău · Your bag weight / Greutatea bagajului tău · ✓ Your destination / Destinația ta | Agenta întreabă unde zbori, adică destinația. | |
| 3 | What is the agent asking about? / Despre ce întreabă agenta? | The boarding time / Ora îmbarcării · ✓ The number of bags / Numărul de bagaje · The flight price / Prețul zborului | Agenta întreabă câte bagaje ai. | |
| 4 | What does the agent want to identify? / Ce vrea să identifice agenta? | The flight on the screen / Zborul de pe ecran · The passport photo / Fotografia din pașaport · ✓ The bag for the hold / Bagajul pentru cală | Agenta întreabă ce bagaj mergе la cală. | |
| 5 | Where is the seat? / Unde este locul? | ✓ By the window / La geam · Next to the aisle / Lângă culoar · In the middle / La mijloc | Agenta spune că locul este la geam. | |
| 6 | How can the passenger take the backpack? / Cum poate pasagerul să ia rucsacul? | ✓ As hand luggage / Ca bagaj de mână · In the checked bag / În bagajul de cală · With special cargo / La cargo special | Agenta spune că rucsacul poate fi luat ca bagaj de mână. | |
| 7 | What two items does the agent give? / Ce două lucruri dă agenta? | A passport and a receipt / Un pașaport și o chitanță · ✓ A boarding pass and a bag label / O carte de îmbarcare și o etichetă de bagaj · A ticket and a map / Un bilet și o hartă | Agenta oferă cartea de îmbarcare și eticheta de bagaj. | |
| 8 | When does boarding begin? / Când începe îmbarcarea? | Right now / Chiar acum · ✓ In about forty minutes / Peste aproximativ patruzeci de minute · In two hours / Peste două ore | Agenta spune că îmbarcarea începe peste patruzeci de minute. | |

## Слушаю весь визит (listening)

| # | вопрос | варианты (✓ — верный) | пояснение | оценка |
|---|---|---|---|---|
| L1 | Unde zboară pasagerul? | ✓ La Londra · La Madrid · La Roma | Pasagerul spune că zboară la Londra. | |
| L2 | Ce bagaj dă pasagerul la cală? | ✓ Valiza · Rucsacul · Ambele bagaje | Pasagerul spune că dă la cală valiza. | |
| L3 | Ce loc primește pasagerul? | 14C la culoar · ✓ 14A la geam · 12B la mijloc | Agenta spune că locul este 14A, la geam. | |
| L4 | Cum poate lua pasagerul rucsacul? | Ca bagaj de cală · ✓ Ca bagaj de mână · Nu îl poate lua | Agenta spune că rucsacul poate fi luat ca bagaj de mână. | |

## Словарь

| id | слово | вид | перевод | чтение | определение | где звучит | картинка (запрос) | оценка |
|---|---|---|---|---|---|---|---|---|
| v1 | check in | связка | a face check-in | cec in | to register for a flight before departure | p1, p4 | airport check-in counter with a passenger and agent | |
| v2 | passport | слово | pașaport | pas-port | an official travel document for international trips | A1 | passport on an airport counter | |
| v3 | suitcase | слово | valiză | suit-ceis | a travel case for clothes and personal items | p3, p4 | medium suitcase beside an airport check-in desk | |
| v4 | backpack | слово | rucsac | bec-pac | a bag carried on your back | p3, p6 | black backpack on the floor at an airport counter | |
| v5 | window | слово | geam | uin-dou | the side seat next to the plane window | A5 | airplane window seat with the window visible | |
| v6 | carry-on | слово | bagaj de mână | ce-ri on | a bag you take with you onto the plane | A6, p3 | small carry-on bag under an airport seat area | |
| v7 | boarding pass | связка | carte de îmbarcare | bor-ding pas | the document that lets you board the plane | A7, p7 | boarding pass in a passenger's hand at the airport | |
| v8 | baggage tag | связка | etichetă de bagaj | beg-ij teg | a label attached to checked luggage | A7, p7 | baggage tag attached to a suitcase handle | |

## Находки (ответ модели без починок; фатальные держат день до P2R)

| код | порог | адрес | что |
|---|---|---|---|
| `exchange.second_question` | **фатально** | x1 | the closing message of A «Sure. May I see your passport?» ends with a question mark |
| `key.contains_filler` | предупреждение | B2 | the key «flying to» takes words of the filler «to London» |
| `key.contains_filler` | предупреждение | B4 | the key «checking this» takes words of the filler «this suitcase» |
| `key.no_content_word` | предупреждение | B5 | «What is ___?» has no content word outside the slot: the key is «What is», the frame up to the slot, not «What is my» |
| `key.contains_filler` | предупреждение | B5 | the key «What is my» takes words of the filler «my seat» |
| `key.no_content_word` | предупреждение | B7 | «Thank you for ___.» has no content word outside the slot: the key is «Thank you for», the frame up to the slot, not «for the» |
| `key.contains_filler` | предупреждение | B7 | the key «for the» takes words of the filler «the baggage tag» |
| `vocab.used_in_wrong` | предупреждение | v1 | «check in» is not in frame p1 or its fillers |
| `vocab.used_in_wrong` | предупреждение | v1 | «check in» is not in frame p4 or its fillers |
| `filler.native_seam` | предупреждение | p7.f2 | «Mulțumesc pentru cartea de îmbarcare.» («Mulțumesc pentru ___.» with «cartea de îmbarcare») does not read as Romanian, the seam judge says |

## Не проверено — у языка нет пакета (`lang.pack_missing`, не находка)

| код | сторона пары | язык | чего нет в пакете |
|---|---|---|---|
| `pronunciation.script` | родной | ro | script |
| `frame.native_punct` | родной | ro | sentence_ends |
| `frame.native_agreement` | родной | ro | agreement |
| `listening.same_exchange` | родной | ro | function_words, word_forms |
| `listening.no_learner_value` | родной | ro | function_words, word_forms |
| `listening.distractor_not_filler` | родной | ro | function_words, word_forms, number_pattern, time_pattern |
| `native.gendered_past` | родной | ro | gendered_past_pattern |

## Порог (фатальные коды → P2R, не больше двух карточек)

- **Живая сборка** (валидатор до двух уточнений отчёта §3, P2R на `gpt-5.4-mini`): P2R x1 (exchange, $0.005413, 2027 мс: exchange.second_question); итог: ready.
- **Порог на валидаторе сдачи, P2R на `gpt-5.4`**: passes after repair · карточки x1 · $0.018103 · 2988 мс.
- **Порог на валидаторе сдачи, P2R на `gpt-5.4-mini`**: failed — fatal: exchange.second_question · карточки x1 · $0.005408 · 2661 мс.
