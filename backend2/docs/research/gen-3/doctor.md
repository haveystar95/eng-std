# GEN-3 · doctor (ru→en, средний)

Цель плана (слова ученика): «Иду к врачу с сыном: у него третий день температура и болит горло. Нужно рассказать симптомы и понять назначения»

Роль ученика в плане: Parent / Родитель. Сцена 1: «Приём у врача» (Doctor / Врач); сцена 2: «Стойка приёма» (Receptionist / Администратор).

> Каждый день — урок, каким его получил бы ученик (прошедший порог; при `failed` — ответ модели как написан). Находки — по ответу модели ДО починок, одним валидатором наряда и против одного и того же дня 1, так что «было» посчитано правилами, которых v4.5 не знал. Факты сюжета (цены, договорённости) и роли код не проверяет — их читает архитектор.

## День 1 — `lesson_day.v4.6`

Итог: **ready** · починок P2R: 2 (x3, B5) · вызовов урока: 1 · цена дня $0.1176 (без скидки кэша $0.1176) · из кэша 0 % входа · 51 с · ученик: Родитель · собеседник: Врач (женщина)

### Сценарий диалога

| # | вид обмена | кто | реплика | перевод | каркас · наполнение |
|---|---|---|---|---|---|
| 1 | ответ | Врач (собеседник) | What seems to be the problem with your son today? | Что сегодня беспокоит вашего сына? |  |
| 1 | ответ | Родитель (ученик) | He has a fever and a sore throat. | У него температура и болит горло. | p1 · a fever and a sore throat |
| 2 | ответ | Врач (собеседник) | How long has he had these symptoms? | Как долго у него эти симптомы? |  |
| 2 | ответ | Родитель (ученик) | He's been sick for three days. | Он болеет уже три дня. | p2 · for three days |
| 3 | вопрос ученика | Родитель (ученик) | Should I tell you his temperature? | Мне сказать вам его температуру? | p3 · his temperature |
| 3 | вопрос ученика | Врач (собеседник) | Yes, please tell me the highest reading. | Да, пожалуйста, скажите самый высокий показатель. |  |
| 4 | ответ | Врач (собеседник) | Did you give him any medicine for the fever? | Вы давали ему что-нибудь от температуры? |  |
| 4 | ответ | Родитель (ученик) | I gave him paracetamol. | Я дал(а) ему парацетамол. | p4 · paracetamol |
| 5 | ответ | Врач (собеседник) | His throat is red. It looks like a viral infection. | Горло у него красное. Похоже на вирусную инфекцию. |  |
| 5 | ответ | Родитель (ученик) | It looks like a viral infection. | Похоже на вирусную инфекцию. | p5 · a viral infection |
| 6 | вопрос ученика | Родитель (ученик) | What should I do at home? | Что мне делать дома? | p6 · at home |
| 6 | вопрос ученика | Врач (собеседник) | Give him fluids, let him rest, and check his temperature. | Давайте ему больше жидкости, пусть отдыхает и проверяйте температуру. |  |
| 7 | переспрос | Родитель (ученик) | Could you say that more slowly, please? | Повторите, пожалуйста, помедленнее. | — |
| 7 | переспрос | Врач (собеседник) | Fluids, rest, and check his temperature. | Жидкость, отдых и проверка температуры. |  |
| 8 | вопрос ученика | Родитель (ученик) | What warning signs should I watch for? | За какими тревожными признаками мне следить? | p7 · warning signs |
| 8 | вопрос ученика | Врач (собеседник) | Come back if he has trouble breathing or can't drink. | Приходите снова, если ему трудно дышать или он не может пить. |  |

### Каркасы

| id | вид | каркас | на родном | наполнения (в диалоге — жирным) |
|---|---|---|---|---|
| p1 | ответ | He has ___. | У него ___. | **a fever and a sore throat** / температура и болит горло · a cough and a runny nose / кашель и насморк · ear pain / боль в ухе |
| p2 | ответ | He's been sick ___. | Он болеет ___. | **for three days** / уже три дня · since yesterday / со вчерашнего дня · since Monday / с понедельника |
| p3 | вопрос ученика | Should I tell you ___? | Мне сказать вам ___? | **his temperature** / его температуру · his weight / его вес · his age / его возраст |
| p4 | ответ | I gave him ___. | Я дал(а) ему ___. | **paracetamol** / парацетамол · ibuprofen / ибупрофен · cough syrup / сироп от кашля |
| p5 | ответ | It looks like ___. | Похоже на ___. | **a viral infection** / вирусную инфекцию · strep throat / стрептококковую ангину · a cold / простуду |
| p6 | вопрос ученика | What should I do ___? | Что мне делать ___? | **at home** / дома · tonight / сегодня вечером · this weekend / в эти выходные |
| p7 | вопрос ученика | What ___ should I watch for? | За какими ___ мне следить? | **warning signs** / тревожными признаками · side effects / побочными эффектами · new symptoms / новыми симптомами |

### Проверки обменов

| # | вопрос | варианты (✓ — верный) |
|---|---|---|
| 1 | What does the doctor ask about? / О чём спрашивает врач? | ✓ Why the child came in today / Почему ребёнка привели сегодня · What medicine the child took / Какие лекарства принимал ребёнок · How old the child is / Сколько лет ребёнку |
| 2 | What time period does the doctor want to know? / Какой период времени хочет узнать врач? | When the fever is highest / Когда температура самая высокая · ✓ How many days the child has been ill / Сколько дней ребёнок болеет · How long the visit will take / Сколько продлится приём |
| 3 | Which detail does the doctor want to know? / Какую деталь хочет узнать врач? | The child's usual temperature / Обычную температуру ребёнка · ✓ The peak temperature he had / Самую высокую температуру, которая у него была · The temperature in the room / Температуру в комнате |
| 4 | What does the doctor ask about giving? / О чём врач спрашивает, давали ли это? | ✓ Something to lower the fever / Что-то, чтобы сбить температуру · Food for a sore throat / Еду при боли в горле · Vitamins for energy / Витамины для энергии |
| 5 | What does the doctor think it probably is? / Что, по мнению врача, это, скорее всего? | A bacterial illness / Бактериальная инфекция · ✓ A virus / Вирусная инфекция · An allergy / Аллергия |
| 6 | Which care does the doctor recommend? / Какой уход рекомендует врач? | Keep him active outdoors / Побольше активностей на улице · ✓ Offer drinks and rest / Давать пить и обеспечить отдых · Give only solid food / Давать только твёрдую еду |
| 7 | What is one thing the doctor repeats? / Что врач повторяет как одну из рекомендаций? | ✓ Check his temperature / Проверять температуру · Start antibiotics today / Сегодня начать антибиотики · Keep him at school / Оставить его в школе |
| 8 | When does the doctor want you to come back? / Когда врач просит прийти снова? | ✓ If the child breathes with difficulty or won't drink / Если ребёнку трудно дышать или он отказывается пить · If the sore throat is gone tomorrow / Если боль в горле пройдёт завтра · If the fever drops tonight / Если температура спадёт сегодня вечером |

### Слушаю весь визит

- L1. Сколько дней сын болеет? — Один день · ✓ Три дня · Неделю
- L2. Какое лекарство родитель уже давал ребёнку? — Ибупрофен · Сироп от кашля · ✓ Парацетамол
- L3. Что врач считает наиболее вероятной причиной? — Аллергию · ✓ Вирусную инфекцию · Бактериальную инфекцию
- L4. Что врач советует делать дома? — ✓ Давать больше жидкости и отдыхать · Сразу начать антибиотики · Отправить ребёнка в школу

### Словарь

| id | слово | вид | перевод | где звучит |
|---|---|---|---|---|
| v1 | sore throat | связка | боль в горле | p1 |
| v2 | symptom | слово | симптом | A2 |
| v3 | temperature | слово | температура | p3, A6, A7 |
| v4 | paracetamol | слово | парацетамол | p4 |
| v5 | viral infection | связка | вирусная инфекция | A5, p5 |
| v6 | fluids | слово | жидкость, питьё | A6, A7 |
| v7 | rest | слово | отдых | A6, A7 |
| v8 | warning sign | связка | тревожный признак | p7 |

### Находки в ответе модели (до починок)

| код | порог | адрес | что |
|---|---|---|---|
| `exchange.second_question` | **фатальная** | x3 | the closing message of A «Yes. What was the highest temperature?» ends with a question mark |
| `frame.native_agreement` | предупреждение | p1 | «У него ___.»: «него» agrees with the slot — it changes with the filler |
| `frame.native_alternatives` | предупреждение | p4 | «Я дал(а) ему ___.» writes alternatives inside the frame |
| `filler.one_in_dialogue` | предупреждение | p5.f1 | «a viral infection» is marked in_dialogue, but no line says it |
| `line.ne_frame` | **фатальная** | B5 | «So it looks like a viral infection.» is not «It looks like ___.» with any of its fillers («a viral infection», «strep throat», «a cold») |
| `learner.restates_partner` | предупреждение | B5 | «So it looks like a viral infection.» repeats 6 of 10 words of the partner's «His throat is red. It looks like a viral infection.» (it, looks, like, a, viral, infection) |
| `check.verbatim` | предупреждение | x3.check | the right option «The highest reading» repeats «the highest» of the partner's line |
| `check.verbatim` | предупреждение | x7.check | the right option «Check his temperature» repeats «check his» of the partner's line |
| `vocab.used_in_wrong` | предупреждение | v8 | «warning sign» is not in frame p7 or its fillers |

## День 2 — БЫЛО: `lesson_day.v4.5`, код до наряда (без ролей и без EARLIER_DAYS)

Итог: **ready** · починок P2R: 0 · вызовов урока: 1 · цена дня $0.0650 (без скидки кэша $0.0805) · из кэша 78 % входа · 43 с · ученик: Родитель · собеседник: Администратор (женщина)

### Сценарий диалога

| # | вид обмена | кто | реплика | перевод | каркас · наполнение |
|---|---|---|---|---|---|
| 1 | ответ | Администратор (собеседник) | Good morning. Who is the appointment for? | Доброе утро. На кого запись? |  |
| 1 | ответ | Родитель (ученик) | It's for my son. | Это для моего сына. | p1 · my son |
| 2 | ответ | Администратор (собеседник) | What seems to be the problem today? | Что случилось сегодня? |  |
| 2 | ответ | Родитель (ученик) | He has a fever and a sore throat. | У него температура и болит горло. | p2 · a fever and a sore throat |
| 3 | ответ | Администратор (собеседник) | Can I have your son's full name? | Можно полное имя вашего сына? |  |
| 3 | ответ | Родитель (ученик) | His name is Ivan Petrov. | Его зовут Иван Петров. | p3 · Ivan Petrov |
| 4 | ответ | Администратор (собеседник) | His appointment is at 10:30. Please go to room 12. | Его запись на 10:30. Пожалуйста, идите в кабинет 12. |  |
| 4 | ответ | Родитель (ученик) | Okay, we'll go to room 12. | Хорошо, мы пойдём в кабинет 12. | p4 · room 12 |
| 5 | вопрос ученика | Родитель (ученик) | How long is the wait? | Сколько ждать? | p5 · the wait |
| 5 | вопрос ученика | Администратор (собеседник) | About fifteen minutes. The doctor is finishing another patient. | Примерно пятнадцать минут. Врач заканчивает с другим пациентом. |  |
| 6 | вопрос ученика | Родитель (ученик) | Do you need any documents? | Вам нужны какие-нибудь документы? | p6 · any documents |
| 6 | вопрос ученика | Администратор (собеседник) | Yes, I need his ID and your insurance card. | Да, мне нужен его документ и ваша страховая карта. |  |
| 7 | переспрос | Родитель (ученик) | Could you repeat that more slowly, please? | Повторите, пожалуйста, помедленнее. | — |
| 7 | переспрос | Администратор (собеседник) | His ID and your insurance card, please. | Пожалуйста, его документ и вашу страховую карту. |  |
| 8 | вопрос ученика | Родитель (ученик) | Do I need to pay anything now? | Мне нужно сейчас что-нибудь платить? | p7 · anything now |
| 8 | вопрос ученика | Администратор (собеседник) | No, payment is after the visit at the front desk. | Нет, оплата после приёма на стойке регистрации. |  |

### Каркасы

| id | вид | каркас | на родном | наполнения (в диалоге — жирным) |
|---|---|---|---|---|
| p1 | ответ | It's for ___. | Это для ___. | **my son** / моего сына · my daughter / моей дочери · my wife / моей жены |
| p2 | ответ | He has ___. | У него ___. | **a fever and a sore throat** / температура и болит горло · a bad cough / сильный кашель · ear pain / болит ухо |
| p3 | ответ | His name is ___. | Его зовут ___. | **Ivan Petrov** / Иван Петров · Maksim Sokolov / Максим Соколов · Nikita Orlov / Никита Орлов |
| p4 | ответ | We'll go to ___. | Мы пойдём в ___. | **room 12** / кабинет 12 · room 8 / кабинет 8 · the waiting area / зону ожидания |
| p5 | вопрос ученика | How long is ___? | Сколько ждать ___? | **the wait** / ждать · the line / очередь · the delay / задержка |
| p6 | вопрос ученика | Do you need ___? | Вам нужны ___? | **any documents** / какие-нибудь документы · a referral / направление · a phone number / номер телефона |
| p7 | вопрос ученика | Do I need to pay ___? | Мне нужно платить ___? | **anything now** / что-нибудь сейчас · in cash / наличными · today / сегодня |

### Проверки обменов

| # | вопрос | варианты (✓ — верный) |
|---|---|---|
| 1 | Who does the receptionist ask about? / О ком спрашивает администратор? | ✓ The learner's child / О ребёнке ученика · The family doctor / О семейном враче · The learner's spouse / О супруге ученика |
| 2 | What does the receptionist ask the learner to explain? / Что администратор просит объяснить? | ✓ Why they came in today / Почему они пришли сегодня · Which medicine helped before / Какие лекарства помогали раньше · How the child slept last night / Как ребёнок спал прошлой ночью |
| 3 | What information does the receptionist need? / Какая информация нужна администратору? | ✓ The child's complete name / Полное имя ребёнка · The child's school address / Адрес школы ребёнка · The parent's phone number / Номер телефона родителя |
| 4 | Which room does the receptionist send them to? / В какой кабинет администратор их направляет? | The room next to the lab / В кабинет рядом с лабораторией · ✓ Room twelve / В двенадцатый кабинет · The children's waiting area / В детскую зону ожидания |
| 5 | How much time does the receptionist mention? / Какое время называет администратор? | ✓ Roughly a quarter of an hour / Около четверти часа · About half an hour / Около получаса · Just a few minutes / Всего несколько минут |
| 6 | Which two things does the receptionist ask for? / Какие две вещи просит администратор? | A passport and a payment receipt / Паспорт и квитанцию об оплате · ✓ The child's ID and the parent's insurance card / Документ ребёнка и страховую карту родителя · A referral and a bank card / Направление и банковскую карту |
| 7 | What should the parent show besides the child's ID? / Что родителю нужно показать кроме документа ребёнка? | A clinic form / Бланк клиники · ✓ An insurance card / Страховую карту · A birth certificate / Свидетельство о рождении |
| 8 | When does the receptionist say payment happens? / Когда, по словам администратора, происходит оплата? | Before seeing the doctor / До приёма у врача · Right after check-in / Сразу после регистрации · ✓ After the appointment ends / После окончания приёма |

### Слушаю весь визит

- L1. Для кого был записан приём? — ✓ Для сына · Для самого родителя · Для бабушки
- L2. Сколько времени нужно подождать? — ✓ Около пятнадцати минут · Около сорока минут · Ждать не нужно
- L3. Куда администратор направила их? — В лабораторию · ✓ В кабинет 12 · В аптеку
- L4. Когда нужно платить за приём? — До визита · Во время разговора с врачом · ✓ После приёма

### Словарь

| id | слово | вид | перевод | где звучит |
|---|---|---|---|---|
| v1 | appointment | слово | запись на приём | A1, A4 |
| v2 | sore throat | связка | боль в горле | p2 |
| v3 | full name | связка | полное имя | A3 |
| v4 | waiting time | связка | время ожидания | p5 |
| v5 | insurance card | связка | страховая карта | A6, A7 |
| v6 | front desk | связка | стойка регистрации | A8 |
| v7 | room | слово | кабинет | p4, A4 |
| v8 | documents | слово | документы | p6 |

### Находки в ответе модели (до починок)

| код | порог | адрес | что |
|---|---|---|---|
| `frame.native_agreement` | предупреждение | p2 | «У него ___.»: «него» agrees with the slot — it changes with the filler |
| `frame.native_agreement` | предупреждение | p6 | «Вам нужны ___?»: «нужны» agrees with the slot — it changes with the filler |
| `variant.longer` | предупреждение | B1 | the variant «The appointment is for my son.» has 6 words, the line 4 |
| `variant.longer` | предупреждение | B5 | the variant «How long is the waiting time?» has 6 words, the line 5 |
| `vocab.used_in_wrong` | предупреждение | v4 | «waiting time» is not in frame p5 or its fillers |
| `vocab.everyday_word` | предупреждение | v7 | «room» is a plain everyday word or a word of the STOP LIST |
| `vocab.learner_share` | предупреждение | lesson | 3 of 8 items are in the learner's frames or fillers (at least half) |
| `vocab.known_repeat` | **фатальная** | v2 | «sore throat» is a word the learner already learned on day 1 |
| `frame.known_repeat` | **фатальная** | p2 | «He has ___.» is the frame «He has ___.» the learner already learned on day 1 |

## День 2 — СТАЛО: `lesson_day.v4.6` (роли плана, EARLIER_DAYS = день 1)

Итог: **ready** · починок P2R: 1 (p4) · вызовов урока: 1 · цена дня $0.0716 (без скидки кэша $0.0958) · из кэша 74 % входа · 50 с · ученик: Родитель · собеседник: Администратор (женщина)

### Сценарий диалога

| # | вид обмена | кто | реплика | перевод | каркас · наполнение |
|---|---|---|---|---|---|
| 1 | ответ | Администратор (собеседник) | Good morning. Who is the appointment for? | Доброе утро. На кого запись? |  |
| 1 | ответ | Родитель (ученик) | It's for my son. | Это для моего сына. | p1 · my son |
| 2 | ответ | Администратор (собеседник) | What is his full name? | Как его полное имя? |  |
| 2 | ответ | Родитель (ученик) | His name is Ivan Petrov. | Его зовут Иван Петров. | p2 · Ivan Petrov |
| 3 | ответ | Администратор (собеседник) | What time is the appointment? | На какое время запись? |  |
| 3 | ответ | Родитель (ученик) | We are booked for ten thirty. | Мы записаны на десять тридцать. | p3 · for ten thirty |
| 4 | вопрос ученика | Родитель (ученик) | He's got a fever and a sore throat. | У него температура и болит горло. | p4 · a fever and a sore throat |
| 4 | вопрос ученика | Администратор (собеседник) | I understand. The doctor will see him soon. | Понимаю. Врач скоро его примет. |  |
| 5 | вопрос ученика | Родитель (ученик) | Where is the doctor's room? | Где кабинет врача? | p5 · the doctor's room |
| 5 | вопрос ученика | Администратор (собеседник) | Room twelve, at the end of the hall. | Кабинет двенадцать, в конце коридора. |  |
| 6 | вопрос ученика | Родитель (ученик) | Will we wait long? | Нам долго ждать? | p6 · long |
| 6 | вопрос ученика | Администратор (собеседник) | About fifteen minutes. There is one patient before you. | Около пятнадцати минут. Перед вами ещё один пациент. |  |
| 7 | вопрос ученика | Родитель (ученик) | Do you need any ID? | Вам нужен какой-нибудь документ? | p7 · any ID |
| 7 | вопрос ученика | Администратор (собеседник) | Yes, your son's ID and the insurance card, please. | Да, пожалуйста, документ сына и страховую карту. |  |
| 8 | вопрос ученика | Родитель (ученик) | Do I need to pay now? | Мне нужно платить сейчас? | p8 · pay now |
| 8 | вопрос ученика | Администратор (собеседник) | No, payment is after the consultation. | Нет, оплата после консультации. |  |

### Каркасы

| id | вид | каркас | на родном | наполнения (в диалоге — жирным) |
|---|---|---|---|---|
| p1 | ответ | It's for ___. | Это для ___. | **my son** / моего сына · my daughter / моей дочери |
| p2 | ответ | His name is ___. | Его зовут ___. | **Ivan Petrov** / Иван Петров · Maksim Sokolov / Максим Соколов |
| p3 | ответ | We are booked ___. | Мы записаны ___. | **for ten thirty** / на десять тридцать · for eleven / на одиннадцать |
| p4 | вопрос ученика | He's got ___. | Что с ним? — ___. | **a fever and a sore throat** / температура и болит горло · a bad cough / сильный кашель |
| p5 | вопрос ученика | Where is ___? | Где ___? | **the doctor's room** / кабинет врача · the lab / лаборатория |
| p6 | вопрос ученика | Will we wait ___? | Нам ждать ___? | **long** / долго · outside / снаружи |
| p7 | вопрос ученика | Do you need ___? | Вам нужен ___? | **any ID** / какой-нибудь документ · the appointment code / код записи |
| p8 | вопрос ученика | Do I need to ___? | Мне нужно ___? | **pay now** / платить сейчас · fill this out / заполнить это |

### Проверки обменов

| # | вопрос | варианты (✓ — верный) |
|---|---|---|
| 1 | Who does the receptionist ask about? / О ком спрашивает администратор? | ✓ About the child who has the visit / О ребёнке, который пришёл на приём · About the doctor on duty / О враче, который сегодня дежурит · About the parent’s insurance company / О страховой компании родителя |
| 2 | What exact detail does the receptionist need? / Какая именно информация нужна администратору? | ✓ The child's complete name / Полное имя ребёнка · The child's home address / Домашний адрес ребёнка · The child's school name / Название школы ребёнка |
| 3 | Which detail does the receptionist ask for? / Какую деталь спрашивает администратор? | ✓ The scheduled hour of the visit / Назначенное время приёма · The doctor's lunch break / Время обеденного перерыва врача · The child's bedtime / Во сколько ребёнок ложится спать |
| 4 | What does the receptionist say about timing? / Что администратор говорит о времени? | ✓ The doctor should see him shortly / Врач должен принять его в ближайшее время · The doctor is finished for today / Врач на сегодня уже закончил приём · The visit has been moved to tomorrow / Приём перенесли на завтра |
| 5 | Where does the receptionist direct them? / Куда их направляет администратор? | ✓ To room 12 at the end of the corridor / В кабинет 12 в конце коридора · To room 2 near the entrance / В кабинет 2 у входа · To the lab on the second floor / В лабораторию на втором этаже |
| 6 | How long is the expected wait? / Сколько примерно придётся ждать? | ✓ Around a quarter of an hour / Около четверти часа · About forty-five minutes / Около сорока пяти минут · Less than five minutes / Меньше пяти минут |
| 7 | Which documents does the receptionist ask for? / Какие документы просит администратор? | ✓ The child's ID and insurance card / Документ ребёнка и страховую карту · The parent's passport and bank card / Паспорт родителя и банковскую карту · The child's school pass and notebook / Школьный пропуск ребёнка и тетрадь |
| 8 | When does the receptionist say payment happens? / Когда, по словам администратора, происходит оплата? | ✓ After the doctor visit / После приёма у врача · Before going to the room / До того, как идти в кабинет · When booking by phone / Во время записи по телефону |

### Слушаю весь визит

- L1. Для кого записан приём? — ✓ Для сына · Для самого родителя · Для бабушки
- L2. Куда администратор направляет их? — ✓ В кабинет 12 в конце коридора · В кабинет 2 рядом со входом · В лабораторию на втором этаже
- L3. Сколько примерно ждать? — ✓ Около пятнадцати минут · Около часа · Сразу без ожидания
- L4. Когда нужно платить? — ✓ После консультации · Сразу на стойке · Завтра онлайн

### Словарь

| id | слово | вид | перевод | где звучит |
|---|---|---|---|---|
| v1 | appointment | слово | запись на приём | A1, A3, p3 |
| v2 | full name | связка | полное имя | A2 |
| v3 | booked | слово | записан | p3 |
| v4 | doctor's room | связка | кабинет врача | p5 |
| v5 | hall | слово | коридор | A5 |
| v6 | patient | слово | пациент | A6 |
| v7 | insurance card | связка | страховая карта | A7 |
| v8 | consultation | слово | консультация | A8 |

### Находки в ответе модели (до починок)

| код | порог | адрес | что |
|---|---|---|---|
| `frame.native_agreement` | предупреждение | p4 | «У него ___.»: «него» agrees with the slot — it changes with the filler |
| `frame.native_agreement` | предупреждение | p7 | «Вам нужен ___?»: «нужен» agrees with the slot — it changes with the filler |
| `variant.longer` | предупреждение | B1 | the variant «The appointment is for my son.» has 6 words, the line 4 |
| `check.verbatim` | предупреждение | x4.check | the right option «The doctor should see him shortly» repeats «see him» of the partner's line |
| `check.verbatim` | предупреждение | x5.check | the right option «To room 12 at the end of the corridor» repeats «the end» of the partner's line |
| `vocab.used_in_wrong` | предупреждение | v1 | «appointment» is not in frame p3 or its fillers |
| `vocab.learner_share` | предупреждение | lesson | 2 of 8 items are in the learner's frames or fillers (at least half) |
| `frame.known_repeat` | **фатальная** | p4 | «He has ___.» is the frame «He has ___.» the learner already learned on day 1 |
