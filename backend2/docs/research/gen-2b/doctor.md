# GEN-2b · «Приём у врача» — doctor (ru→en, средний)

Цель плана (слова ученика): «Иду к врачу с сыном: у него третий день температура и болит горло. Нужно рассказать симптомы и понять назначения»

Ученик: Родитель · собеседник: Врач (женщина)

Промт `lesson_day.v4.5` · модель `gpt-5.4-2026-03-05` · вызов урока $0.082253 · 40.2 с · токены вход/выход 7491/4235 · попыток урока: 1 · находок валидатора и судьи в ответе модели: 17 (фатальных 4) · порог в сборке: урок прошёл

> Колонка «оценка» пустая — ставит Ден: **✓** / **так не говорят** / **слишком длинно** / **не то слово**. Это ответ модели БЕЗ починок; реплики ученика — как их получит приложение (сервер собирает их из каркаса и наполнения); если модель написала иначе, её текст — в скобках. «Судья» — вердикт судьи швов о собранной фразе на родном.

## Сценарий диалога

| # | вид обмена | кто | реплика | перевод | каркас · наполнение | ключ | оценка |
|---|---|---|---|---|---|---|---|
| 1 | ответ | Врач (собеседник) | What seems to be the problem today? | Что вас сегодня беспокоит? |  |  | |
| 1 | ответ | Родитель (ученик) | My son has a fever and a sore throat. | У моего сына температура и болит горло. | p1 · a fever and a sore throat | My son has | |
| 2 | ответ | Врач (собеседник) | How long has he had these symptoms? | Как давно у него эти симптомы? |  |  | |
| 2 | ответ | Родитель (ученик) | He has had them for three days. | Они у него уже три дня. | p2 · for three days | has had them | |
| 3 | ответ | Врач (собеседник) | What was his highest temperature? | Какая у него была самая высокая температура? |  |  | |
| 3 | ответ | Родитель (ученик) | His highest temperature was 39 degrees. | Самая высокая температура была 39 градусов. | p3 · 39 degrees | highest temperature was | |
| 4 | ответ | Врач (собеседник) | Is he eating and drinking normally? | Он ест и пьёт как обычно? |  |  | |
| 4 | ответ | Родитель (ученик) | He is eating less than usual. | Он ест меньше, чем обычно. | p4 · less than usual | eating less than | |
| 5 | ответ | Врач (собеседник) | It looks like a throat infection. Give him fluids, rest, and paracetamol every six hours. | Похоже на инфекцию горла. Давайте ему питьё, отдых и парацетамол каждые шесть часов. |  |  | |
| 5 | ответ | Родитель (ученик) | I'll give paracetamol every six hours. | Я буду давать парацетамол каждые шесть часов. | p5 · every six hours | give paracetamol | |
| 6 | переспрос | Родитель (ученик) | Could you say that more slowly, please? | Скажите, пожалуйста, помедленнее. | — | say that more | |
| 6 | переспрос | Врач (собеседник) | Give him fluids, rest, and paracetamol every six hours. | Давайте ему питьё, отдых и парацетамол каждые шесть часов. |  |  | |
| 7 | вопрос ученика | Родитель (ученик) | Does he need the antibiotic? | Ему нужен антибиотик? | p6 · the antibiotic | Does he need | |
| 7 | вопрос ученика | Врач (собеседник) | No, not now. This seems viral, so antibiotics won't help. | Нет, сейчас нет. Это похоже на вирусную инфекцию, поэтому антибиотики не помогут. |  |  | |
| 8 | вопрос ученика | Родитель (ученик) | When should we come back if if the fever gets worse? (модель: «When should we come back if the fever gets worse?») | Когда нам нужно прийти снова, если температура станет выше? | p7 · if the fever gets worse | When should we | |
| 8 | вопрос ученика | Врач (собеседник) | Come back tomorrow if the fever stays high or he can't drink. | Приходите завтра, если температура останется высокой или он не сможет пить. |  |  | |

## Каркасы

### p1 · ответ — «My son has ___.»

На родном: «У моего сына ___.» · чтение: «Май сан хэз ___.» · окно: «симптомы» · звучит в обменах: 1

| наполнение | перевод | чтение | в диалоге | собранная фраза | собранный перевод | судья | оценка |
|---|---|---|---|---|---|---|---|
| a fever and a sore throat | температура и болит горло | э фивер энд э сор сроут | да | My son has a fever and a sore throat. | У моего сына температура и болит горло. | читается | |
| a cough and chills | кашель и озноб | э коф энд чилз | — | My son has a cough and chills. | У моего сына кашель и озноб. | читается | |
| an earache and a fever | боль в ухе и температура | эн иэрэйк энд э фивер | — | My son has an earache and a fever. | У моего сына боль в ухе и температура. | читается | |

### p2 · ответ — «He has had them ___.»

На родном: «Они у него уже ___.» · чтение: «Хи хэз хед зэм ___.» · окно: «как долго» · звучит в обменах: 2

| наполнение | перевод | чтение | в диалоге | собранная фраза | собранный перевод | судья | оценка |
|---|---|---|---|---|---|---|---|
| for three days | три дня | фор сри дэйз | да | He has had them for three days. | Они у него уже три дня. | читается | |
| since yesterday | со вчерашнего дня | синс естэрдэй | — | He has had them since yesterday. | Они у него уже со вчерашнего дня. | читается | |
| for a week | неделю | фор э уик | — | He has had them for a week. | Они у него уже неделю. | читается | |

### p3 · ответ — «His highest temperature was ___.»

На родном: «Самая высокая температура была ___.» · чтение: «Хиз хайэст тэмпрэчер уоз ___.» · окно: «температура» · звучит в обменах: 3

| наполнение | перевод | чтение | в диалоге | собранная фраза | собранный перевод | судья | оценка |
|---|---|---|---|---|---|---|---|
| 39 degrees | 39 градусов | сёрти-найн дигриз | да | His highest temperature was 39 degrees. | Самая высокая температура была 39 градусов. | читается | |
| 38.5 degrees | 38,5 градусов | сёрти-эйт пойнт файв дигриз | — | His highest temperature was 38.5 degrees. | Самая высокая температура была 38,5 градусов. | читается | |
| 40 degrees | 40 градусов | форти дигриз | — | His highest temperature was 40 degrees. | Самая высокая температура была 40 градусов. | читается | |

### p4 · ответ — «He is eating ___.»

На родном: «Он ест ___.» · чтение: «Хи из итинг ___.» · окно: «как он ест» · звучит в обменах: 4

| наполнение | перевод | чтение | в диалоге | собранная фраза | собранный перевод | судья | оценка |
|---|---|---|---|---|---|---|---|
| less than usual | меньше, чем обычно | лэс зэн южуэл | да | He is eating less than usual. | Он ест меньше, чем обычно. | читается | |
| almost nothing | почти ничего | олмоуст насинг | — | He is eating almost nothing. | Он ест почти ничего. | **не читается** | |
| normally now | уже нормально | нормэли нау | — | He is eating normally now. | Он ест уже нормально. | читается | |

### p5 · ответ — «I'll give paracetamol ___.»

На родном: «Я буду давать парацетамол ___.» · чтение: «Айл гив пэрэситэмол ___.» · окно: «как часто» · звучит в обменах: 5

| наполнение | перевод | чтение | в диалоге | собранная фраза | собранный перевод | судья | оценка |
|---|---|---|---|---|---|---|---|
| every six hours | каждые шесть часов | эври сикс ауэрз | да | I'll give paracetamol every six hours. | Я буду давать парацетамол каждые шесть часов. | читается | |
| twice a day | два раза в день | твайс э дэй | — | I'll give paracetamol twice a day. | Я буду давать парацетамол два раза в день. | читается | |
| before bed | перед сном | бифор бед | — | I'll give paracetamol before bed. | Я буду давать парацетамол перед сном. | читается | |

### p6 · вопрос ученика — «Does he need ___?»

На родном: «Ему нужен ___?» · чтение: «Даз хи нид ___?» · окно: «лекарство или помощь» · звучит в обменах: 7

| наполнение | перевод | чтение | в диалоге | собранная фраза | собранный перевод | судья | оценка |
|---|---|---|---|---|---|---|---|
| the antibiotic | антибиотик | зи энтибайотик | да | Does he need the antibiotic? | Ему нужен антибиотик? | читается | |
| a test | анализ | э тест | — | Does he need a test? | Ему нужен анализ? | читается | |
| a prescription | рецепт | э прискрипшн | — | Does he need a prescription? | Ему нужен рецепт? | читается | |

### p7 · вопрос ученика — «When should we come back if ___?»

На родном: «Когда нам нужно прийти снова, если ___?» · чтение: «Уэн шуд ви кам бэк иф ___?» · окно: «что ухудшится» · звучит в обменах: 8

| наполнение | перевод | чтение | в диалоге | собранная фраза | собранный перевод | судья | оценка |
|---|---|---|---|---|---|---|---|
| if the fever gets worse | если температура станет выше | иф зэ фивер гетс уорс | да | When should we come back if if the fever gets worse? | Когда нам нужно прийти снова, если если температура станет выше? | **не читается** | |
| if he starts coughing | если у него начнётся кашель | иф хи стартс кофинг | — | When should we come back if if he starts coughing? | Когда нам нужно прийти снова, если если у него начнётся кашель? | **не читается** | |
| if he won't eat | если он не будет есть | иф хи воунт ит | — | When should we come back if if he won't eat? | Когда нам нужно прийти снова, если если он не будет есть? | **не читается** | |

## Проверки обменов

| # | вопрос | варианты (✓ — верный) | пояснение | оценка |
|---|---|---|---|---|
| 1 | What does the doctor ask about first? / О чём врач спрашивает в первую очередь? | Why the child is absent from school / Почему ребёнок не ходит в школу · ✓ What problem brought them in today / С какой проблемой они пришли сегодня · Which medicine the child took yesterday / Какое лекарство ребёнок принимал вчера | Врач сначала спрашивает, с какой проблемой вы пришли сегодня. | |
| 2 | What does the doctor want to know? / Что хочет узнать врач? | How severe the throat pain is / Насколько сильно болит горло · When the fever is highest / Когда температура самая высокая · ✓ How many days the symptoms have lasted / Сколько дней длятся симптомы | Врач спрашивает о продолжительности симптомов. | |
| 3 | Which detail does the doctor ask for? / Какую именно деталь спрашивает врач? | The child's normal temperature / Обычную температуру ребёнка · The room temperature at home / Температуру в комнате дома · ✓ The child's peak temperature / Максимальную температуру ребёнка | Врач спрашивает о самой высокой температуре у ребёнка. | |
| 4 | What does the doctor ask about? / О чём спрашивает врач? | School and homework / О школе и домашнем задании · ✓ Food and fluids / О еде и питье · Sleep and exercise / О сне и физической активности | Врач спрашивает, ест и пьёт ли ребёнок как обычно. | |
| 5 | How often does the doctor say to give paracetamol? / Как часто врач говорит давать парацетамол? | ✓ At six-hour intervals / С интервалом в шесть часов · Three times a day with meals / Три раза в день во время еды · Once in the morning / Один раз утром | Врач говорит давать парацетамол каждые шесть часов. | |
| 6 | What should the child get besides medicine? / Что ребёнку нужно, кроме лекарства? | Soup and a hot bath / Суп и горячая ванна · ✓ More sleep and something to drink / Больше отдыха и питьё · Warm clothes and fresh air / Тёплая одежда и свежий воздух | Кроме лекарства врач советует питьё и отдых. | |
| 7 | Why doesn't the doctor recommend antibiotics now? / Почему врач сейчас не рекомендует антибиотики? | The pharmacy is closed today / Аптека сегодня закрыта · The child is allergic to that medicine / У ребёнка аллергия на это лекарство · ✓ The illness is probably caused by a virus / Болезнь, скорее всего, вызвана вирусом | Врач считает, что это вирусная инфекция, а антибиотики при ней не помогают. | |
| 8 | When does the doctor want them to return? / Когда врач просит прийти снова? | ✓ Tomorrow, if he is still doing badly / Завтра, если ему по-прежнему плохо · This evening after dinner / Сегодня вечером после ужина · Next week after school / На следующей неделе после школы | Врач говорит прийти завтра, если высокая температура сохранится или ребёнок не сможет пить. | |

## Слушаю весь визит (listening)

| # | вопрос | варианты (✓ — верный) | пояснение | оценка |
|---|---|---|---|---|
| L1 | Сколько дней у ребёнка уже есть симптомы? | Один день · ✓ Три дня · Неделю | Родитель говорит, что симптомы длятся три дня. | |
| L2 | Какая самая высокая температура была у ребёнка? | 40 градусов · ✓ 39 градусов · 38,5 градусов | Родитель сообщает, что самая высокая температура была 39 градусов. | |
| L3 | Что врач советует давать ребёнку? | Только антибиотик · Сироп от кашля и витамины · ✓ Питьё, отдых и парацетамол | Врач рекомендует питьё, отдых и парацетамол. | |
| L4 | Когда врач просит прийти снова? | Сегодня вечером, если он устанет · Через неделю, если горло всё ещё красное · ✓ Завтра, если температура останется высокой или он не сможет пить | Врач говорит прийти завтра при высокой температуре или если ребёнок не сможет пить. | |

## Словарь

| id | слово | вид | перевод | чтение | определение | где звучит | картинка (запрос) | оценка |
|---|---|---|---|---|---|---|---|---|
| v1 | sore throat | связка | боль в горле | сор сроут | pain or irritation in the throat | p1 | child holding his throat while sitting in a clinic exam room | |
| v2 | symptoms | слово | симптомы | симптомс | signs of illness that a person has | A2 | — | |
| v3 | highest temperature | связка | самая высокая температура | хайэст тэмпрэчер | the maximum body temperature measured | p3, A3 | digital thermometer showing a high reading on a table | |
| v4 | less than usual | связка | меньше, чем обычно | лэс зэн южуэл | in a smaller amount than normal | p4 | — | |
| v5 | throat infection | связка | инфекция горла | сроут инфекшн | an infection affecting the throat | A5 | doctor examining a child's throat with a light | |
| v6 | paracetamol | слово | парацетамол | пэрэситэмол | a medicine used to reduce pain and fever | p5, A5, A6 | paracetamol tablets and a glass of water on a kitchen table | |
| v7 | antibiotic | слово | антибиотик | энтибайотик | a medicine that treats bacterial infections | p6, A7 | box of antibiotic capsules on a clinic desk | |
| v8 | come back | связка | прийти снова | кам бэк | return for another visit | p7, A8 | parent and child walking back into a clinic entrance | |

## Находки (ответ модели без починок; фатальные держат день до P2R)

| код | порог | адрес | что |
|---|---|---|---|
| `frame.native_agreement` | предупреждение | p6 | «Ему нужен ___?»: «нужен» agrees with the slot — it changes with the filler |
| `filler.ungrammatical` | **фатально** | p7.f1 | «When should we come back if if the fever gets worse?»: a word is doubled at the seam |
| `filler.is_clause` | предупреждение | p7.f1 | «if the fever gets worse» is a clause, not a value — the frame should carry the clause and the slot the value |
| `filler.ungrammatical` | **фатально** | p7.f2 | «When should we come back if if he starts coughing?»: a word is doubled at the seam |
| `filler.is_clause` | предупреждение | p7.f2 | «if he starts coughing» is a clause, not a value — the frame should carry the clause and the slot the value |
| `filler.ungrammatical` | **фатально** | p7.f3 | «When should we come back if if he won't eat?»: a word is doubled at the seam |
| `filler.is_clause` | предупреждение | p7.f3 | «if he won't eat» is a clause, not a value — the frame should carry the clause and the slot the value |
| `key.no_content_word` | предупреждение | B2 | «He has had them ___.» has no content word outside the slot: the key is «He has had them», the frame up to the slot, not «has had them» |
| `key.contains_filler` | предупреждение | B4 | the key «eating less than» takes words of the filler «less than usual» |
| `line.ne_frame` | **фатально** | B8 | «When should we come back if the fever gets worse?» is not «When should we come back if ___?» with «if the fever gets worse»; served as «When should we come back if if the fever gets worse?» |
| `line.too_long` | предупреждение | B8 | «When should we come back if if the fever gets worse?» has 11 words without the glue (max 10) |
| `key.no_content_word` | предупреждение | B8 | the key «When should we» has no content word, and «When should we come back if ___?» has one |
| `check.verbatim` | предупреждение | x8.check | the right option «Tomorrow, if he is still doing badly» repeats «tomorrow if» of the partner's line |
| `filler.native_seam` | предупреждение | p4.f2 | «Он ест почти ничего.» («Он ест ___.» with «почти ничего») does not read as Russian, the seam judge says |
| `filler.native_seam` | предупреждение | p7.f1 | «Когда нам нужно прийти снова, если если температура станет выше?» («Когда нам нужно прийти снова, если ___?» with «если температура станет выше») does not read as Russian, the seam judge says |
| `filler.native_seam` | предупреждение | p7.f2 | «Когда нам нужно прийти снова, если если у него начнётся кашель?» («Когда нам нужно прийти снова, если ___?» with «если у него начнётся кашель») does not read as Russian, the seam judge says |
| `filler.native_seam` | предупреждение | p7.f3 | «Когда нам нужно прийти снова, если если он не будет есть?» («Когда нам нужно прийти снова, если ___?» with «если он не будет есть») does not read as Russian, the seam judge says |

## Не проверено — у языка нет пакета (`lang.pack_missing`, не находка)

Всё проверено: пакеты обоих языков пары полные.

## Порог (фатальные коды → P2R, не больше двух карточек)

- **Живая сборка** (валидатор до двух уточнений отчёта §3, P2R на `gpt-5.4-mini`): P2R p7 (frame, $0.004007, 1680 мс: filler.ungrammatical, filler.is_clause); B8 (line, $0.003693, 1956 мс: filler.one_in_dialogue, line.ne_frame, line.too_long, key.no_content_word); итог: ready.
- **Порог на валидаторе сдачи, P2R на `gpt-5.4`**: passes after repair · карточки p7, B8 · $0.025183 · 4579 мс.
- **Порог на валидаторе сдачи, P2R на `gpt-5.4-mini`**: passes after repair · карточки p7, B8 · $0.007669 · 4438 мс.
