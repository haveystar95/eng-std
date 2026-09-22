# VOCAB-DUMP-1 — план «Тренировка в зале» (бой)

Выгрузка с боя (`wt_db`, база `wordtrainer`), только чтение, снята 2026-09-22.

| Поле | Значение |
|---|---|
| Аккаунт | `vitalnost.meditation@gmail.com` (user `01M12HTZ1QHPNDZ5J8SPKB58QP`) |
| План | `01M32DX8QCABM348XP45Z1ZD4M` — «Тренировка в зале» / «Gym Workout», статус `active` |
| Цель (как ввёл ученик) | Тренировка в тренажерном зале в новой стране |
| Языки · уровень | ru → en · intermediate |
| Дней | 3 (запрошено 3), событие 2026-09-24 |
| Модель плана · промпт | `gpt-5.4-2026-03-05` · `plan-builder-v2` |

Дни:

| День | Тип | Статус | Сцена | Промпт урока | Карточек роздано |
|---|---|---|---|---|---|
| 1 | scene | closed | «Ресепшен зала» `01M32DXHYG50H7SWQEMD33A39F` | `lesson_day.v4.7` | 85 |
| 2 | scene | locked | «С тренером» `01M32DXHYGYMK0E1DBR01PYDHA` | `lesson_day.v4.7` | 0 |
| 3 | rehearsal | locked | — | — | 0 |

## Файлы `raw/`

| Файл | Что внутри |
|---|---|
| `plan.json` | строка `plans` + все `plan_days` + `plan_scenes` без `lesson_json` |
| `day-1.json`, `day-2.json` | строка `plan_scenes` целиком: `lesson_json` — документ дня, как его отдал генератор (`lesson_day.v4.7`), плюс `plan_terms` сцены |
| `day-3.json` | строка `plan_days` дня 3: репетиция, `scene_id = null`, документа урока нет |
| `cards-day-1.json` | все 85 строк `day_cards` дня 1 (payload, response, result), по `stage`, `position` |
| `cards-day-2.json`, `cards-day-3.json` | `[]` — дни `locked`, карточки не розданы |
| `conversation-day-1.json` | разговор дня 1 (`conversations` + `conversation_turns` без аудио и токенов) — источник колонки «разговор» |

## Как считались колонки

- **Частотный ранг** — языковой пакет `config/lesson/lang/en.php` частотного списка не содержит; колонка `terms.frequency_rank` снята миграцией `2026_08_20_160000_drop_unused_term_columns`. Поэтому везде «—».
- **Где встречается** — поиск термина `term_target` без учёта регистра, по границам слов, каждое слово термина допускает окончание `-s`/`-es`:
  - *фразы* — `frame_target` и все `slot.fillers[].target` каркасов `phrases[]` (указан id каркаса);
  - *диалог* — `dialogue[].messages[].text_target` (метка `A<шаг>`/`B<шаг>`, как в `used_in`: A — партнёр, B — ученик);
  - *разговор* — реплики `conversation_turns.text_target` проведённого разговора дня (P — партнёр, L — ученик, номер хода). У дня 2 разговора не было — «не проводился».
- `used_in` — поле словаря из документа генератора, как есть.

## День 1 — «Ресепшен зала» / «Gym Reception»

Тема документа: «На ресепшене в зале» / «At the gym reception». Спросите про абонементы, цены, правила и куда идти внутри зала.
Роль ученика: Посетитель / Gym member; партнёр: Администратор / Receptionist. Статус дня: `closed`, карточек 85.

### 1.1 Слова (`vocabulary`)

| id | Слово | Вид | Перевод | Частотный ранг | Фразы | Диалог | Разговор | `used_in` |
|---|---|---|---|---|---|---|---|---|
| v1 | day pass | chunk | дневной пропуск | — | p1 | B1, A1 | P3 | p1, A1 |
| v2 | membership | word | абонемент | — | p2 | B2 | L4, P5, P7 | p2 |
| v3 | unlimited plan | chunk | безлимитный план | — | — | A2 | — | A2 |
| v4 | changing room | chunk | раздевалка | — | p5 | B5 | P9 | p5 |
| v5 | locker | word | шкафчик | — | p6 | A5, A6, B6, A7 | — | A5, p6, A6, A7 |
| v6 | towel | word | полотенце | — | p1 | A6, A7 | — | A6, A7, p1 |
| v7 | clean shoes | chunk | чистая обувь | — | — | A6, A7 | — | A6, A7 |
| v8 | training floor | chunk | тренировочная зона | — | — | A8 | — | A8 |

### 1.2 Фразы (`phrases`, «Скажи целиком»)

| id | Вид | Каркас | Перевод каркаса | Наполнения (target — перевод; ● = в диалоге) | Подсказка окна |
|---|---|---|---|---|---|
| p1 | ask | Do you have ___? | У вас есть ___? | ● a day pass — дневной пропуск<br>○ a weekly pass — недельный пропуск<br>○ towel rental — аренда полотенца | что есть в наличии |
| p2 | ask | What ___ do you have? | Какие ___ у вас есть? | ● monthly memberships — месячные абонементы<br>○ payment options — способы оплаты | какие варианты вас интересуют |
| p3 | answer | This is ___. | Это ___. | ● my first visit — мой первый визит<br>○ my trial day — мой пробный день | что это за визит или ситуация |
| p4 | answer | That works for me on ___. | Мне это подходит по ___. | ● weekdays — будням<br>○ weekends — выходным | в какие дни это подходит |
| p5 | ask | Where are ___? | Где ___? | ● the changing rooms — раздевалки<br>○ the showers — душевые<br>○ the cardio area — кардиозона | какое место внутри зала |
| p6 | answer | I'll return ___. | Я верну ___. | ● the locker key — ключ от шкафчика<br>○ the access card — карту доступа | что нужно вернуть |
| p7 | ask | Can I pay ___? | Я могу оплатить ___? | ● by card — картой<br>○ in cash — наличными | как вы хотите оплатить |

Карточки «Скажи целиком» (`phrase_other_slot`) дня 1, как розданы:

| Каркас | Позиция | Круги (`rounds`: expected_text — task_native) | Своё окно (`own_round.task_native`) | Результат |
|---|---|---|---|---|
| p1 | 10 | Do you have a day pass? — У вас есть дневной пропуск?<br>Do you have a weekly pass? — У вас есть недельный пропуск? | У вас есть ___? | passed |
| p2 | 14 | What monthly memberships do you have? — Какие у вас есть месячные абонементы? | Какие ___ у вас есть? | passed |
| p3 | 18 | This is my first visit. — Это мой первый визит. | Это ___. | passed |
| p4 | 23 | That works for me on weekdays. — Мне это подходит по будням. | Мне это подходит по ___. | passed |
| p5 | 26 | Where are the changing rooms? — Где раздевалки? | Где ___? | passed |
| p6 | 27 | I'll return the locker key. — Я верну ключ от шкафчика. | Я верну ___. | passed |
| p7 | 28 | Can I pay by card? — Я могу оплатить картой? | Я могу оплатить ___? | passed |

### 1.3 Диалог (`dialogue`) и цели разговора

| Метка | Шаг | Роль | Реплика | Перевод | Каркас · наполнение |
|---|---|---|---|---|---|
| B1 | 1 | Gym member | Do you have a day pass? | У вас есть дневной пропуск? | p1 · a day pass |
| A1 | 1 | Receptionist | Yes, a day pass is fifteen dollars. | Да, дневной пропуск стоит пятнадцать долларов. | — |
| B2 | 2 | Gym member | What monthly memberships do you have? | Какие у вас есть месячные абонементы? | p2 · monthly memberships |
| A2 | 2 | Receptionist | We have a basic plan and an unlimited plan. | У нас есть базовый план и безлимитный план. | — |
| A3 | 3 | Receptionist | Is this your first visit here? | Это ваш первый визит сюда? | — |
| B3 | 3 | Gym member | Yes, this is my first visit. | Да, это мой первый визит. | p3 · my first visit |
| A4 | 4 | Receptionist | We open at six and close at ten on weekdays. | В будни мы открываемся в шесть и закрываемся в десять. | — |
| B4 | 4 | Gym member | That works for me on weekdays. | Мне это подходит по будням. | p4 · weekdays |
| B5 | 5 | Gym member | Where are the changing rooms? | Где раздевалки? | p5 · the changing rooms |
| A5 | 5 | Receptionist | They are downstairs, next to the lockers. | Они внизу, рядом со шкафчиками. | — |
| A6 | 6 | Receptionist | Please bring a towel, use clean shoes, and return the locker key after training. | Пожалуйста, возьмите полотенце, используйте чистую обувь и верните ключ от шкафчика после тренировки. | — |
| B6 | 6 | Gym member | Okay, I'll return the locker key. | Хорошо, я верну ключ от шкафчика. | p6 · the locker key |
| B7 | 7 | Gym member | Could you say that more slowly, please? | Не могли бы вы сказать это помедленнее, пожалуйста? | — |
| A7 | 7 | Receptionist | Bring a towel, wear clean shoes, and return the locker key. | Возьмите полотенце, наденьте чистую обувь и верните ключ от шкафчика. | — |
| B8 | 8 | Gym member | Can I pay by card? | Я могу оплатить картой? | p7 · by card |
| A8 | 8 | Receptionist | Yes, card is fine, and the training floor is upstairs. | Да, картой можно, а тренировочная зона наверху. | — |

Цели разговора (`plan_scenes.goals_native`):

- спросить про разовый вход
- уточнить цену и оплату
- узнать правила и часы
- спросить, где раздевалка

Чему учит сцена (`teaches_native`): уточнить тариф, правила и доступ

Проведённый разговор дня 1 (`01M32FJ5FQNRNSQH6PNC7DP5E0`, `ended_reason = natural`):

| Ход | Кто | Реплика | Перевод | Фразы плана |
|---|---|---|---|---|
| 1 | partner | Hello! Welcome to the gym. How can I help you today? | Здравствуйте! Добро пожаловать в зал. Чем я могу вам помочь сегодня? | — |
| 2 | learner | Hello I need daily training |  | — |
| 3 | partner | Do you have a day pass? | У вас есть дневной пропуск? | — |
| 4 | learner | No I want to buy monthly membership |  | — |
| 5 | partner | We have a few monthly memberships. This is your first visit, right? | У нас есть несколько месячных абонементов. Это ваш первый визит, верно? | — |
| 6 | learner | Yes it is my first visit |  | — |
| 7 | partner | Great, then I can show you the monthly options. What monthly memberships do you have in mind, and do you need weekdays only? | Хорошо, тогда я могу показать вам месячные варианты. Какие месячные абонементы вам нужны, и вам нужны только будни? | — |
| 8 | learner | Weekdays works for me |  | — |
| 9 | partner | Great, that works. Where are the changing rooms? | Отлично, это подходит. Где раздевалки? | — |

## День 2 — «С тренером» / «With a Trainer»

Тема документа: «Разговор с тренером» / «Talking to a trainer». Научитесь говорить о своих целях, опыте и понимать инструкции тренера во время тренировки.
Роль ученика: Посетитель / Gym member; партнёр: Тренер / Trainer. Статус дня: `locked`, карточек 0.

### 2.1 Слова (`vocabulary`)

| id | Слово | Вид | Перевод | Частотный ранг | Фразы | Диалог | Разговор | `used_in` |
|---|---|---|---|---|---|---|---|---|
| v1 | general fitness | chunk | общая физическая форма | — | p1 | B1 | не проводился | p1 |
| v2 | experience | word | опыт | — | p2 | A2, B2 | не проводился | p2, A2 |
| v3 | shoulder pain | chunk | боль в плече | — | p3 | B3 | не проводился | p3 |
| v4 | machine | word | тренажёр | — | p4 | B4 | не проводился | p4, A4 |
| v5 | heels | word | пятки | — | — | A4, A5 | не проводился | A4, A5 |
| v6 | good form | chunk | хорошая техника | — | — | A6 | не проводился | A6 |
| v7 | set | word | подход | — | — | A7 | не проводился | A7 |
| v8 | shoulders down | chunk | плечи опущены | — | — | B8 | не проводился | p7, A8 |

### 2.2 Фразы (`phrases`, «Скажи целиком»)

| id | Вид | Каркас | Перевод каркаса | Наполнения (target — перевод; ● = в диалоге) | Подсказка окна |
|---|---|---|---|---|---|
| p1 | answer | I'm working on ___. | Я работаю над ___. | ● general fitness — общей формой<br>○ strength — силой<br>○ weight loss — снижением веса | цель тренировки |
| p2 | answer | I have ___ of experience. | У меня ___ опыта. | ● about a year — около года<br>○ six months — шесть месяцев<br>○ a few years — несколько лет | срок опыта |
| p3 | answer | I have some ___. | У меня есть ___. | ● shoulder pain — боль в плече<br>○ knee pain — боль в колене<br>○ lower back pain — боль в пояснице | боль или ограничение |
| p4 | ask | How do I use ___? | Как пользоваться ___? | ● this machine — этим тренажёром<br>○ the cable machine — блочным тренажёром<br>○ the rowing machine — гребным тренажёром | оборудование |
| p5 | ask | How heavy should ___ be? | Насколько тяжёлым должен быть ___? | ● the weight — вес<br>○ the dumbbell — гантель<br>○ the bar — штанга | что выбрать по весу |
| p6 | answer | I'll rest for ___. | Я буду отдыхать ___. | ● forty-five seconds — сорок пять секунд<br>○ thirty seconds — тридцать секунд<br>○ one minute — минуту | время отдыха |
| p7 | ask | Should I keep ___ down? | Мне держать ___ опущенными? | ● my shoulders — плечи<br>○ my elbows — локти<br>○ my chest — грудь | часть тела |

Карточки «Скажи целиком» дня 2 не розданы (день `locked`).

### 2.3 Диалог (`dialogue`) и цели разговора

| Метка | Шаг | Роль | Реплика | Перевод | Каркас · наполнение |
|---|---|---|---|---|---|
| A1 | 1 | Trainer | What are you training for today? | Над чем вы сегодня хотите поработать? | — |
| B1 | 1 | Gym member | I'm working on general fitness. | Я работаю над общей формой. | p1 · general fitness |
| A2 | 2 | Trainer | How much gym experience do you have? | Какой у вас опыт тренировок в зале? | — |
| B2 | 2 | Gym member | I have about a year of experience. | У меня около года опыта. | p2 · about a year |
| A3 | 3 | Trainer | Do you have any injuries or pain right now? | Есть ли у вас сейчас травмы или боль? | — |
| B3 | 3 | Gym member | I have some shoulder pain. | У меня немного болит плечо. | p3 · shoulder pain |
| B4 | 4 | Gym member | How do I use this machine? | Как пользоваться этим тренажёром? | p4 · this machine |
| A4 | 4 | Trainer | Sit tall, keep your back flat, and push through your heels. | Сядьте ровно, держите спину прямой и отталкивайтесь пятками. | — |
| B5 | 5 | Gym member | Could you say that more slowly, please? | Не могли бы вы сказать это помедленнее? | — |
| A5 | 5 | Trainer | Keep your back flat and push through your heels. | Держите спину прямой и отталкивайтесь пятками. | — |
| B6 | 6 | Gym member | How heavy should the weight be? | Насколько тяжёлым должен быть вес? | p5 · the weight |
| A6 | 6 | Trainer | Use a weight you can lift twelve times with good form. | Возьмите вес, который сможете поднять двенадцать раз с хорошей техникой. | — |
| A7 | 7 | Trainer | Do three sets, and rest forty-five seconds between them. | Сделайте три подхода и отдыхайте сорок пять секунд между ними. | — |
| B7 | 7 | Gym member | I'll rest for forty-five seconds. | Я буду отдыхать сорок пять секунд. | p6 · forty-five seconds |
| B8 | 8 | Gym member | Should I keep my shoulders down? | Мне держать плечи опущенными? | p7 · my shoulders |
| A8 | 8 | Trainer | Yes, keep them down and don't lift them toward your ears. | Да, держите их опущенными и не поднимайте к ушам. | — |

Цели разговора (`plan_scenes.goals_native`):

- объяснить цель тренировки
- сказать про опыт и форму
- уточнить технику упражнения
- понять правки и нагрузку

Чему учит сцена (`teaches_native`): обсудить цель и технику

Разговор дня 2 не проводился.

## День 3 — репетиция

`type = rehearsal`, `scene_id = null`: собственного документа урока, словаря, фраз и диалога у дня нет; день `locked`, карточки не розданы, разговора не было.

## Слова, которые есть только в разделе «Слова»

Ни во фразах (каркас и наполнения), ни в диалоге, ни в разговоре своего дня.

Таких слов нет: каждое слово словаря дней 1 и 2 встречается хотя бы в одном из трёх мест своего дня.
