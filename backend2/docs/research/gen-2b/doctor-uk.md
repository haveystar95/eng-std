# GEN-2b · «Прийом у лікаря» — doctor-uk (uk→en, средний)

Цель плана (слова ученика): «Іду до лікаря з сином: у нього третій день температура і болить горло. Треба розповісти симптоми і зрозуміти призначення»

Ученик: Мати або батько · собеседник: Лікарка (женщина)

Промт `lesson_day.v4.5` · модель `gpt-5.4-2026-03-05` · вызов урока $0.084225 · 41.3 с · токены вход/выход 7524/4361 · попыток урока: 1 · находок валидатора и судьи в ответе модели: 13 (фатальных 0) · порог в сборке: урок прошёл

> Колонка «оценка» пустая — ставит Ден: **✓** / **так не говорят** / **слишком длинно** / **не то слово**. Это ответ модели БЕЗ починок; реплики ученика — как их получит приложение (сервер собирает их из каркаса и наполнения); если модель написала иначе, её текст — в скобках. «Судья» — вердикт судьи швов о собранной фразе на родном.

## Сценарий диалога

| # | вид обмена | кто | реплика | перевод | каркас · наполнение | ключ | оценка |
|---|---|---|---|---|---|---|---|
| 1 | ответ | Лікарка (собеседник) | What seems to be the problem today? | Що вас сьогодні турбує? |  |  | |
| 1 | ответ | Мати або батько (ученик) | My son has a fever and a sore throat. | У мого сина температура і болить горло. | p1 · a fever and a sore throat | My son has | |
| 2 | ответ | Лікарка (собеседник) | How long has he had the fever? | Як довго в нього температура? |  |  | |
| 2 | ответ | Мати або батько (ученик) | He has had it for three days. | Вона в нього вже три дні. | p2 · for three days | has had it | |
| 3 | ответ | Лікарка (собеседник) | Is he drinking enough fluids? | Він п'є достатньо рідини? |  |  | |
| 3 | ответ | Мати або батько (ученик) | He is drinking a little water. | Він п'є трохи води. | p3 · a little water | is drinking | |
| 4 | ответ | Лікарка (собеседник) | It looks like a viral throat infection. | Схоже на вірусну інфекцію горла. |  |  | |
| 4 | ответ | Мати або батько (ученик) | So it is a viral infection. | Тобто це вірусна інфекція. | p4 · a viral infection | it is a | |
| 5 | вопрос ученика | Мати або батько (ученик) | What can I give him for the fever? | Що я можу дати йому від температури? | p5 · for the fever | can I give | |
| 5 | вопрос ученика | Лікарка (собеседник) | Give him paracetamol or ibuprofen for fever and throat pain. | Дайте йому парацетамол або ібупрофен від температури й болю в горлі. |  |  | |
| 6 | вопрос ученика | Мати або батько (ученик) | How often should I give it? | Як часто мені це давати? | p6 · how often | How often should | |
| 6 | вопрос ученика | Лікарка (собеседник) | Every six hours if needed, and always after food. | Кожні шість годин за потреби, і обов’язково після їжі. |  |  | |
| 7 | переспрос | Мати або батько (ученик) | Could you say that more slowly, please? | Можете сказати це повільніше, будь ласка? | — | say that more slowly | |
| 7 | переспрос | Лікарка (собеседник) | Every six hours, after food. | Кожні шість годин, після їжі. |  |  | |
| 8 | вопрос ученика | Мати або батько (ученик) | When should I come back if the fever gets worse? | Коли нам знову прийти, якщо температура підніметься? | p7 · if the fever gets worse | When should I | |
| 8 | вопрос ученика | Лікарка (собеседник) | Come back if he cannot drink, or if breathing gets hard. | Приходьте знову, якщо він не зможе пити або якщо йому стане важко дихати. |  |  | |

## Каркасы

### p1 · ответ — «My son has ___.»

На родном: «У мого сина ___.» · чтение: «май сан хез ___» · окно: «симптоми» · звучит в обменах: 1

| наполнение | перевод | чтение | в диалоге | собранная фраза | собранный перевод | судья | оценка |
|---|---|---|---|---|---|---|---|
| a fever and a sore throat | температура і болить горло | э фівер енд э сор сроут | да | My son has a fever and a sore throat. | У мого сина температура і болить горло. | читается | |
| a cough and a runny nose | кашель і нежить | э коф енд э ранні ноуз | — | My son has a cough and a runny nose. | У мого сина кашель і нежить. | читается | |
| stomach pain and nausea | біль у животі й нудота | стамек пейн енд нозіа | — | My son has stomach pain and nausea. | У мого сина біль у животі й нудота. | читается | |

### p2 · ответ — «He has had it ___.»

На родном: «Вона в нього вже ___.» · чтение: «хі хез хед іт ___» · окно: «тривалість» · звучит в обменах: 2

| наполнение | перевод | чтение | в диалоге | собранная фраза | собранный перевод | судья | оценка |
|---|---|---|---|---|---|---|---|
| for three days | три дні | фор срі дейз | да | He has had it for three days. | Вона в нього вже три дні. | читается | |
| since yesterday | від учора | сінс єстердей | — | He has had it since yesterday. | Вона в нього вже від учора. | читается | |
| since Monday | із понеділка | сінс мандей | — | He has had it since Monday. | Вона в нього вже із понеділка. | читается | |

### p3 · ответ — «He is drinking ___.»

На родном: «Він п'є ___.» · чтение: «хі із дрінкінг ___» · окно: «що і скільки п'є» · звучит в обменах: 3

| наполнение | перевод | чтение | в диалоге | собранная фраза | собранный перевод | судья | оценка |
|---|---|---|---|---|---|---|---|
| a little water | трохи води | э літл вотер | да | He is drinking a little water. | Він п'є трохи води. | читается | |
| warm tea | теплий чай | ворм ті | — | He is drinking warm tea. | Він п'є теплий чай. | читается | |
| almost nothing | майже нічого | олмоуст насінг | — | He is drinking almost nothing. | Він п'є майже нічого. | читается | |

### p4 · ответ — «So it is ___.»

На родном: «Тобто це ___.» · чтение: «соу іт із ___» · окно: «що це за проблема» · звучит в обменах: 4

| наполнение | перевод | чтение | в диалоге | собранная фраза | собранный перевод | судья | оценка |
|---|---|---|---|---|---|---|---|
| a viral infection | вірусна інфекція | э вайрэл інфекшн | да | So it is a viral infection. | Тобто це вірусна інфекція. | читается | |
| just a cold | просто застуда | джаст э коулд | — | So it is just a cold. | Тобто це просто застуда. | читается | |

### p5 · вопрос ученика — «What can I give him ___?»

На родном: «Що я можу дати йому ___?» · чтение: «вот кен ай гів хім ___» · окно: «для чого» · звучит в обменах: 5

| наполнение | перевод | чтение | в диалоге | собранная фраза | собранный перевод | судья | оценка |
|---|---|---|---|---|---|---|---|
| for the fever | від температури | фор зе фівер | да | What can I give him for the fever? | Що я можу дати йому від температури? | читается | |
| for the pain | від болю | фор зе пейн | — | What can I give him for the pain? | Що я можу дати йому від болю? | читается | |
| for the cough | від кашлю | фор зе коф | — | What can I give him for the cough? | Що я можу дати йому від кашлю? | читается | |

### p6 · вопрос ученика — «How often should I give it?»

На родном: «Як часто мені це давати?» · чтение: «хау офен шуд ай гів іт» · окно: нет · звучит в обменах: 6

| фраза | оценка |
|---|---|
| How often should I give it? | |

### p7 · вопрос ученика — «When should I come back ___?»

На родном: «Коли нам знову прийти ___?» · чтение: «вен шуд ай кам бек ___» · окно: «за якої умови» · звучит в обменах: 8

| наполнение | перевод | чтение | в диалоге | собранная фраза | собранный перевод | судья | оценка |
|---|---|---|---|---|---|---|---|
| if the fever gets worse | якщо температура підніметься | іф зе фівер гетс ворс | да | When should I come back if the fever gets worse? | Коли нам знову прийти якщо температура підніметься? | читается | |
| if he still cannot eat | якщо він і далі не зможе їсти | іф хі стіл кенот іт | — | When should I come back if he still cannot eat? | Коли нам знову прийти якщо він і далі не зможе їсти? | читается | |
| if the pain lasts longer | якщо біль триватиме довше | іф зе пейн ластс лонгер | — | When should I come back if the pain lasts longer? | Коли нам знову прийти якщо біль триватиме довше? | читается | |

## Проверки обменов

| # | вопрос | варианты (✓ — верный) | пояснение | оценка |
|---|---|---|---|---|
| 1 | What does the doctor ask first? / Про що лікарка питає спочатку? | Why your son stayed home / Чому ваш син залишився вдома · ✓ What is wrong today / Що сталося сьогодні · Which medicine he took / Які ліки він приймав | Лікарка спочатку просить коротко сказати, у чому проблема сьогодні. | |
| 2 | What does the doctor want to know? / Що хоче дізнатися лікарка? | How high the fever was this morning / Яка була температура сьогодні зранку · ✓ How long the fever has lasted / Скільки часу тримається температура · How often he gets fever / Як часто в нього буває температура | Лікарка питає саме про тривалість температури. | |
| 3 | What is the doctor asking about? / Про що питає лікарка? | Whether he is taking medicine / Чи він приймає ліки · ✓ Whether he is getting enough to drink / Чи він достатньо п'є · Whether he is sleeping well / Чи він добре спить | Лікарка уточнює, чи дитина п'є достатньо рідини. | |
| 4 | What does the doctor think it is? / Що, на думку лікарки, це може бути? | An allergy to food / Алергія на їжу · A bacterial ear problem / Бактеріальна проблема з вухом · ✓ A virus affecting the throat / Вірус, що вразив горло | Лікарка каже, що це схоже на вірусну інфекцію горла. | |
| 5 | Which medicines does the doctor recommend? / Які ліки радить лікарка? | ✓ Paracetamol or ibuprofen / Парацетамол або ібупрофен · Cough syrup and vitamins / Сироп від кашлю та вітаміни · An antibiotic and nasal drops / Антибіотик і краплі в ніс | Лікарка радить парацетамол або ібупрофен. | |
| 6 | When should the medicine be given? / Коли треба давати ліки? | Every two hours, with juice / Кожні дві години, із соком · Once each morning, before breakfast / Раз щоранку, перед сніданком · ✓ Every six hours, after eating / Кожні шість годин, після їжі | Лікарка сказала давати ліки кожні шість годин за потреби й після їжі. | |
| 7 | What detail does the doctor repeat? / Яку деталь лікарка повторює? | ✓ Use it after meals / Давати після їжі · Use it before school / Давати перед школою · Use it only at bedtime / Давати тільки перед сном | У повторі лікарка ще раз каже, що ліки треба давати після їжі. | |
| 8 | When does the doctor want to see him again? / Коли лікарка хоче знову його побачити? | ✓ If he stops drinking or struggles to breathe / Якщо він не зможе пити або йому буде важко дихати · If he gets sleepy after medicine / Якщо він стане сонним після ліків · If his throat still hurts tonight / Якщо горло й далі болітиме сьогодні ввечері | Лікарка просить повернутися, якщо дитина не зможе пити або матиме утруднене дихання. | |

## Слушаю весь визит (listening)

| # | вопрос | варианты (✓ — верный) | пояснение | оценка |
|---|---|---|---|---|
| L1 | Скільки днів у сина температура? | Тиждень · Один день · ✓ Три дні | Батько або мати сказали, що температура триває три дні. | |
| L2 | Що лікарка вважає найбільш імовірною причиною? | Алергію · Вушну інфекцію · ✓ Вірусну інфекцію горла | Лікарка сказала, що це схоже на вірусну інфекцію горла. | |
| L3 | Які ліки лікарка порадила дати дитині? | Тільки антибіотик · ✓ Парацетамол або ібупрофен · Сироп від кашлю | Лікарка рекомендувала парацетамол або ібупрофен. | |
| L4 | Коли треба знову прийти до лікарки? | Якщо ввечері знову заболить горло · ✓ Якщо дитина не може пити або важко дихає · Якщо захоче спати вдень | Лікарка попросила повернутися, якщо дитина не зможе пити або матиме утруднене дихання. | |

## Словарь

| id | слово | вид | перевод | чтение | определение | где звучит | картинка (запрос) | оценка |
|---|---|---|---|---|---|---|---|---|
| v1 | sore throat | связка | біль у горлі | сор сроут | pain or irritation in the throat | p1 | child holding his throat while sitting in a doctor's office | |
| v2 | fluids | слово | рідина | флуїдз | drinks that help the body stay hydrated | A3 | glass of water and tea on a table near a sick child | |
| v3 | viral infection | связка | вірусна інфекція | вайрэл інфекшн | an illness caused by a virus | A4, p4 | — | |
| v4 | paracetamol | слово | парацетамол | пересітемол | a medicine used to reduce fever and pain | A5 | paracetamol tablets beside a glass of water | |
| v5 | ibuprofen | слово | ібупрофен | айбьюпроуфен | a medicine used for pain, swelling, and fever | A5 | ibuprofen tablets on a kitchen table | |
| v6 | after food | связка | після їжі | афтэр фуд | taken after eating a meal or snack | A6, A7 | medicine spoon next to a child's plate after a meal | |
| v7 | come back | связка | прийти знову | кам бек | return for another visit | p7, A8 | parent and child entering a clinic door again | |
| v8 | breathing | слово | дихання | брізінг | the act of taking air in and out of the lungs | A8 | doctor listening to a child's breathing with a stethoscope | |

## Находки (ответ модели без починок; фатальные держат день до P2R)

| код | порог | адрес | что |
|---|---|---|---|
| `frame.unresolved_pronoun` | предупреждение | p2 | «He has had it ___.» leans on «it», and nothing in the frame is what it stands for |
| `frame.unresolved_pronoun` | предупреждение | p4 | «So it is ___.» leans on «it», and nothing in the frame is what it stands for |
| `frame.unresolved_pronoun` | предупреждение | p6 | «How often should I give it?» leans on «it», and nothing in the frame is what it stands for |
| `filler.is_clause` | предупреждение | p7.f1 | «if the fever gets worse» is a clause, not a value — the frame should carry the clause and the slot the value |
| `filler.is_clause` | предупреждение | p7.f2 | «if he still cannot eat» is a clause, not a value — the frame should carry the clause and the slot the value |
| `filler.is_clause` | предупреждение | p7.f3 | «if the pain lasts longer» is a clause, not a value — the frame should carry the clause and the slot the value |
| `key.no_content_word` | предупреждение | B2 | «He has had it ___.» has no content word outside the slot: the key is «He has had it», the frame up to the slot, not «has had it» |
| `key.no_content_word` | предупреждение | B4 | «So it is ___.» has no content word outside the slot: the key is «So it is», the frame up to the slot, not «it is a» |
| `key.contains_filler` | предупреждение | B4 | the key «it is a» takes words of the filler «a viral infection» |
| `key.no_content_word` | предупреждение | B8 | the key «When should I» has no content word, and «When should I come back ___?» has one |
| `vocab.used_in_wrong` | предупреждение | v3 | «viral infection» is not in the partner's line of exchange 4 |
| `vocab.free_combination` | предупреждение | v6 | «after food» is a free combination of ordinary words |
| `vocab.learner_share` | предупреждение | lesson | 3 of 8 items are in the learner's frames or fillers (at least half) |

## Не проверено — у языка нет пакета (`lang.pack_missing`, не находка)

| код | сторона пары | язык | чего нет в пакете |
|---|---|---|---|
| `pronunciation.script` | родной | uk | script |
| `frame.native_punct` | родной | uk | sentence_ends |
| `frame.native_agreement` | родной | uk | agreement |
| `listening.same_exchange` | родной | uk | function_words, word_forms |
| `listening.no_learner_value` | родной | uk | function_words, word_forms |
| `listening.distractor_not_filler` | родной | uk | function_words, word_forms, number_pattern, time_pattern |
| `native.gendered_past` | родной | uk | gendered_past_pattern |

## Порог (фатальные коды → P2R, не больше двух карточек)

- **Живая сборка** (валидатор до двух уточнений отчёта §3, P2R на `gpt-5.4-mini`): фатальных нет — P2R не звался; итог: ready.
- **Порог на валидаторе сдачи, P2R на `gpt-5.4`**: passes.
- **Порог на валидаторе сдачи, P2R на `gpt-5.4-mini`**: passes.
