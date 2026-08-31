# Живой прогон v0.2.1 — 31.08.2026

Наряд PROMPT-v0.2.1, Ч.4. Модель `gpt-5.4`, промпты `plan_outline.v0.2` / `plan_day.v0.2.1`.
Всё ниже — то, что реально вернула модель, без правок.

План `01M1BYE1657BZG99HB6TZEPRT9`, владелец, `ru→en, conversational, 20 мин, событие через 5 дней`.
Цель дословно: «Онлайн-собеседование PHP-разработчика, удалённо, английская команда» — та же, что в
прогоне (b) наряда PROMPT-v0.2; старый план остался `abandoned`, этот создан заново.

## СНАЧАЛА ГЛАВНОЕ: кап пробит, день не собрался

**$0.229798 при капе $0.15.** Пять платных вызовов вместо ожидаемых трёх, и лишние два — не
случайность, а арифметика, которую наряд (и я) посчитали неверно:

**одна «попытка дня» — это ДВА платных вызова.** `PlanDayComposer::compose()` внутри себя делает
вызов и один повтор с названными нарушениями; `learning_plan_days.generation_attempts` считает
запуски ЗАДАЧИ, а `FinishPlanDayHandler` при неудаче с оставшейся попыткой ставит задачу заново
немедленно. Итого бюджет дня — 2 запуска × 2 вызова = **4 платных вызова**, и все четыре ушли
подряд, за 55 секунд, прежде чем я мог что-то остановить. Прогон v0.2 показал две попытки только
потому, что там день остался на одном запуске задачи (`pending`, `generation_attempts=1`).

Это стоит записать как находку: **строка «максимум две попытки P2» и код означают разные числа**, и
кап наряда был построен на первом значении.

День 1 до `ready` не дошёл: статус `failed`, `generation_attempts=2`, коллекция не создана,
`AttachImagesJob` не ставился ни разу — **картинок нет ни одной**, потому что ставить их не на что.

| вызов | $ | итог |
|---|---|---|
| P1 «собеседование» | 0.032335 | **принят с первой попытки** |
| P2 день 1, запуск задачи 1, вызов 1 | 0.048470 | отбит |
| P2 день 1, запуск задачи 1, вызов 2 (повтор с нарушениями) | 0.050490 | отбит |
| P2 день 1, запуск задачи 2, вызов 1 | 0.047645 | отбит |
| P2 день 1, запуск задачи 2, вызов 2 (повтор с нарушениями) | 0.050858 | отбит |
| | **0.229798** | |

## P1 — каркас принят с первой попытки ($0.032335)

Второй попытки, которую Ч.2 этого наряда только что выдала каркасу, не понадобилось.

**Собеседование на PHP удалённо** — Пройти онлайн-собеседование на позицию PHP-разработчика в
английской команде.

- `constraints`: удалённо; английская команда
- `goal_terms`: PHP
- `entities`: собеседование (neuter, singular, онлайн на позицию разработчика); команда (feminine,
  singular, английская); позиция (feminine, singular, PHP-разработчика)

| сцена | собеседник | умение | `est_terms` | чек-пойнт |
|---|---|---|---|---|
| 1. Начать онлайн-разговор уверенно | HR-менеджер на видеозвонке | подтвердить, что связь на удалённом звонке работает | 4 | отвечает о слышимости и качестве связи так, что собеседнику не приходится проверять это второй раз |
|  |  | кратко представить себя как PHP-разработчика | 6 | называет свой профиль, опыт и текущее направление работы в одном связном ответе |
|  |  | объяснить, почему хочет работать в английской команде | 5 | называет понятную причину интереса к такой команде без ухода в общие слова |
| 2. Обсудить свой недавний опыт | нанимающий менеджер | описать недавний проект через задачу и контекст | 6 | рассказывает, что это был за проект, для кого он был и какую задачу решал |
|  |  | объяснить свою личную зону ответственности в проекте | 6 | называет свои задачи и отделяет их от общей работы команды |
|  |  | объяснить, как решал сложную рабочую ситуацию | 7 | описывает одну проблему, свои действия и рабочий результат в понятной последовательности |
| 3. Ответить на типовые уточнения | технический руководитель | объяснить, как работает с коллегами в английской команде | 6 | описывает рабочее взаимодействие с коллегами так, что понятно, как он общается и согласует работу |
|  |  | объяснить, как принимает замечания по работе | 5 | отвечает, что делает после замечаний, и показывает рабочий подход без защитной реакции |
|  |  | рассказать о своём опыте удалённой работы | 6 | называет конкретные особенности удалённой работы и показывает, как поддерживает рабочий процесс |
| 4. Понять условия и завершить | HR-менеджер в конце звонка | задать уместный вопрос о команде или работе | 5 | спрашивает о рабочем процессе, роли или команде так, что вопрос звучит по делу |
|  |  | понять следующие шаги после собеседования и повторить своими словами | 6 | повторяет, что будет дальше и в каком порядке, а собеседник подтверждает |
|  |  | назвать свою доступность по срокам выхода | 4 | отвечает, когда сможет начать, без долгих поисков формулировки |

**Арифметика сервера:** `need` = 66, `capacity` = 14, `intro_days` = **5**, `max_days` = 6,
`fits` = да, отброшено умений: 0. Двенадцать умений разложились по пяти дням знакомства плюс
прогон; день 4 собран из хвоста третьей сцены и головы четвёртой.

### Реплики собеседников, дословно

- **1.** «Hi, can you hear me clearly?» — Здравствуйте, вы меня хорошо слышите?
- **1.** «Could you briefly introduce yourself?» — Не могли бы вы коротко рассказать о себе?
- **1.** «Why are you interested in this role?» — Почему вас заинтересовала эта роль?
- **2.** «Can you tell me about your most recent project?» — Можете рассказать о своём последнем проекте?
- **2.** «What exactly was your responsibility there?» — За что именно вы там отвечали?
- **2.** «What was the biggest challenge for you?» — Что было для вас самым большим вызовом?
- **3.** «How do you usually work with other developers?» — Как вы обычно работаете с другими разработчиками?
- **3.** «How do you handle feedback on your code?» — Как вы воспринимаете замечания по своему коду?
- **3.** «Do you have experience working remotely?» — У вас есть опыт удалённой работы?
- **4.** «Do you have any questions for us?» — У вас есть к нам вопросы?
- **4.** «Let me explain the next steps in the process.» — Позвольте объяснить следующие шаги в процессе.
- **4.** «When would you be available to start?» — Когда вы могли бы начать работу?

## P2 — четыре ответа, четыре отбоя

Все четыре — один и тот же день: 8 реплик, 4 слова, 2 связки, 3 чек-пойнта, `phrase_count` 8,
`word_count` 4, `chunk_count` 2.

### Четыре числа, по попыткам

| попытка | что это | `___` в `text` | формул (потолок 2) | слов реально в дырке | примеров-клонов |
|---|---|---|---|---|---|
| 1 | холодный вызов | **5 из 8** | **3** | 4 из 6 | 1 (дубль примера) |
| 2 | повтор с нарушениями | 0 | 2 ✔ | 4 из 6 | **5** (пример = чужая реплика) + 1 дубль |
| 3 | холодный вызов (запуск 2) | **5 из 8** | **3** | 4 из 6 | 1 (дубль примера) |
| 4 | повтор с нарушениями | 0 | **3** | 4 из 6 | **4** (пример = чужая реплика) |

Числа последней попытки, как их просил наряд: **реплик с `___` в `text` — 0**, **формул — 3 при
потолке 2**, **слов реально в дырке — 4 из 6**, **клонов примеров — 4**.

### Что это значит

1. **Новое правило v0.2.1 про `___` работает — но только через повтор.** На ХОЛОДНОМ вызове модель
   оба раза (попытки 1 и 3) положила `___` в сам `text` пяти реплик из восьми, и туда же — в
   `translation` («Я ___ разработчик с опытом в три года»). Промпт говорит это прямо, отдельным
   абзацем, и показывает в разобранном фрагменте как ✘. На повторе, где то же самое сказано
   нарушением («`text` содержит «___»»), дырка исчезает мгновенно и полностью — 0 из 8, оба раза.
   Гейт `day.slot_outside_frame`, добавленный в Ч.1, поймал ровно этот дефект и назвал поле.
2. **Слово в неподвижной части каркаса не вылечилось ничем.** 4 из 6 подстановок встают в дырку во
   всех четырёх ответах; не встают всегда СВЯЗКИ — `work on`, `work with`, `be interested in`.
   Модель строит каркас ВОКРУГ связки («Right now, I mainly work on ___»), а потом выдаёт саму
   связку карточкой. Это буквально тот пример, который v0.2.1 показывает как ✘ («`frame: "I mainly
   work with ___"`, `words: "work with"`»), с теми же словами. Промпт назвал дефект дословно и не
   предотвратил его.
3. **Повтор чинит одно и ломает другое.** Убрав дырки из `text`, модель на обеих вторых попытках
   начала писать примеры слов дословной репликой дня: `web applications` → «Right now, I mainly work
   on web applications.» — это `text` реплики №4. Пять таких на попытке 2, четыре на попытке 4.
   На холодных вызовах этого дефекта почти нет (по одному дублю). Похоже на бюджет внимания: пункты
   самопроверки, которые модель выполняет, вытесняют те, которые она выполняла до этого.
4. **Формулы: 3 при потолке 2, три раза из четырёх.** Названное число («формул можно не больше 2»)
   помогло ровно один раз — на попытке 2. Первая реплика-формула здесь неизбежна («Yes, I can hear
   you clearly»), вторая — реплика собеседника из `opening_lines`, третья — «I'm a PHP developer
   with three years of experience», которую модель отказывается делать каркасом, потому что
   `goal_terms` держит `PHP` на месте.

### Полные вердикты валидатора

```
### попытка 1
- day.frame_mismatch [Right now, I mainly work on ___.]: реплика не является своим каркасом «Right now, I mainly work on ___.» с реальным словом в дырке
- day.frame_mismatch [I'm interested in this role because of the ___.]: реплика не является своим каркасом «I'm interested in this role because of the ___.» с реальным словом в дырке
- day.frame_mismatch [I want to work in an English-speaking ___.]: реплика не является своим каркасом «I want to work in an English-speaking ___.» с реальным словом в дырке
- day.frame_mismatch [I also enjoy working with ___.]: реплика не является своим каркасом «I also enjoy working with ___.» с реальным словом в дырке
- day.frame_share: реплик без каркаса 3 из 8, а формул можно не больше 2 — это треть с округлением вниз
- day.substitution_without_frame [work on]: ни один каркас дня не принимает это слово в дырку — его пример не собирается ни из чего
- day.substitution_without_frame [working with]: ни один каркас дня не принимает это слово в дырку — его пример не собирается ни из чего
- day.example_duplicated [work on]: этот же пример уже стоит у «backend systems»
- day.slot_outside_frame [I'm a PHP developer with ___ years of experience.]: `text` содержит «___» — дырка живёт только в `frame`, а поле с дыркой юзеру не произнести
- day.slot_outside_frame [I'm a PHP developer with ___ years of experience.]: `translation` содержит «___» — дырка живёт только в `frame`, а поле с дыркой юзеру не произнести
- day.slot_outside_frame [Right now, I mainly work on ___.]: `text` содержит «___» — дырка живёт только в `frame`, а поле с дыркой юзеру не произнести
- day.slot_outside_frame [Right now, I mainly work on ___.]: `translation` содержит «___» — дырка живёт только в `frame`, а поле с дыркой юзеру не произнести
- day.slot_outside_frame [I'm interested in this role because of the ___.]: `text` содержит «___» — дырка живёт только в `frame`, а поле с дыркой юзеру не произнести
- day.slot_outside_frame [I'm interested in this role because of the ___.]: `translation` содержит «___» — дырка живёт только в `frame`, а поле с дыркой юзеру не произнести
- day.slot_outside_frame [I want to work in an English-speaking ___.]: `text` содержит «___» — дырка живёт только в `frame`, а поле с дыркой юзеру не произнести
- day.slot_outside_frame [I want to work in an English-speaking ___.]: `translation` содержит «___» — дырка живёт только в `frame`, а поле с дыркой юзеру не произнести
- day.slot_outside_frame [I also enjoy working with ___.]: `text` содержит «___» — дырка живёт только в `frame`, а поле с дыркой юзеру не произнести
- day.slot_outside_frame [I also enjoy working with ___.]: `translation` содержит «___» — дырка живёт только в `frame`, а поле с дыркой юзеру не произнести

### попытка 2
- day.substitution_without_frame [work on]: ни один каркас дня не принимает это слово в дырку — его пример не собирается ни из чего
- day.substitution_without_frame [work with]: ни один каркас дня не принимает это слово в дырку — его пример не собирается ни из чего
- day.example_is_a_term [five years]: пример — это дословно термин «I'm a PHP developer with five years of experience.», а не предложение с ним внутри
- day.example_is_a_term [APIs]: пример — это дословно термин «I also work with APIs a lot.», а не предложение с ним внутри
- day.example_is_a_term [team]: пример — это дословно термин «I want to work in an English-speaking team.», а не предложение с ним внутри
- day.example_is_a_term [work on]: пример — это дословно термин «Right now, I mainly work on backend systems.», а не предложение с ним внутри
- day.example_is_a_term [work with]: пример — это дословно термин «I also work with APIs a lot.», а не предложение с ним внутри
- day.example_duplicated [work with]: этот же пример уже стоит у «APIs»

### попытка 3
- day.frame_mismatch [Right now, I mainly work on ___.]: реплика не является своим каркасом «Right now, I mainly work on ___.» с реальным словом в дырке
- day.frame_mismatch [I'm interested in this role because of the ___.]: реплика не является своим каркасом «I'm interested in this role because of the ___.» с реальным словом в дырке
- day.frame_mismatch [I want to improve my English in a real ___.]: реплика не является своим каркасом «I want to improve my English in a real ___.» с реальным словом в дырке
- day.frame_mismatch [I also like working with an English-speaking ___.]: реплика не является своим каркасом «I also like working with an English-speaking ___.» с реальным словом в дырке
- day.frame_share: реплик без каркаса 3 из 8, а формул можно не больше 2 — это треть с округлением вниз
- day.substitution_without_frame [work on]: ни один каркас дня не принимает это слово в дырку — его пример не собирается ни из чего
- day.substitution_without_frame [be interested in]: ни один каркас дня не принимает это слово в дырку — его пример не собирается ни из чего
- day.example_duplicated [be interested in]: этот же пример уже стоит у «environment»
- day.key_is_the_term [backend]: ключ совпадает с термином — карточка спрашивает то, на что уже ответила
- day.slot_outside_frame [I'm a ___ developer with three years of experience.]: `text` содержит «___» — дырка живёт только в `frame`, а поле с дыркой юзеру не произнести
- day.slot_outside_frame [I'm a ___ developer with three years of experience.]: `translation` содержит «___» — дырка живёт только в `frame`, а поле с дыркой юзеру не произнести
- day.slot_outside_frame [Right now, I mainly work on ___.]: `text` содержит «___» — дырка живёт только в `frame`, а поле с дыркой юзеру не произнести
- day.slot_outside_frame [Right now, I mainly work on ___.]: `translation` содержит «___» — дырка живёт только в `frame`, а поле с дыркой юзеру не произнести
- day.slot_outside_frame [I'm interested in this role because of the ___.]: `text` содержит «___» — дырка живёт только в `frame`, а поле с дыркой юзеру не произнести
- day.slot_outside_frame [I'm interested in this role because of the ___.]: `translation` содержит «___» — дырка живёт только в `frame`, а поле с дыркой юзеру не произнести
- day.slot_outside_frame [I want to improve my English in a real ___.]: `text` содержит «___» — дырка живёт только в `frame`, а поле с дыркой юзеру не произнести
- day.slot_outside_frame [I want to improve my English in a real ___.]: `translation` содержит «___» — дырка живёт только в `frame`, а поле с дыркой юзеру не произнести
- day.slot_outside_frame [I also like working with an English-speaking ___.]: `text` содержит «___» — дырка живёт только в `frame`, а поле с дыркой юзеру не произнести
- day.slot_outside_frame [I also like working with an English-speaking ___.]: `translation` содержит «___» — дырка живёт только в `frame`, а поле с дыркой юзеру не произнести

### попытка 4
- day.frame_share: реплик без каркаса 3 из 8, а формул можно не больше 2 — это треть с округлением вниз
- day.substitution_without_frame [work on]: ни один каркас дня не принимает это слово в дырку — его пример не собирается ни из чего
- day.substitution_without_frame [be interested in]: ни один каркас дня не принимает это слово в дырку — его пример не собирается ни из чего
- day.example_is_a_term [web applications]: пример — это дословно термин «Right now, I mainly work on web applications.», а не предложение с ним внутри
- day.example_is_a_term [remote teams]: пример — это дословно термин «I also work with remote teams.», а не предложение с ним внутри
- day.example_is_a_term [the team]: пример — это дословно термин «I'm interested in this role because of the team.», а не предложение с ним внутри
- day.example_is_a_term [work environment]: пример — это дословно термин «I want to improve my English in a real work environment.», а не предложение с ним внутри

```

## Выгрузка: последняя попытка целиком

Колонка «картинка» — `нет` у всех четырнадцати, и не потому, что Pexels не нашёл: день не дошёл до
`ready`, коллекция не создана, `AttachImagesJob` не ставился. `image_api_prompt` модель написала на
каждой карточке (гейт `day.image_prompt_missing` не сработал ни разу за четыре ответа) — искать
было бы по чему.

| термин | kind | frame | speaker | перевод | чтение | пример | image_api_prompt | картинка |
|---|---|---|---|---|---|---|---|---|
| Yes, I can hear you clearly. | line | — | learner | Да, я вас хорошо слышу. | йес ай кэн хиэ ю клирли | Yes, I can hear you clearly, and the connection is stable. | a job candidate on a laptop video call at home, speaking and wearing headphones, clear remote interview scene | нет |
| Hi, can you hear me clearly? | line | — | role | Здравствуйте, вы меня хорошо слышите? | хай кэн ю хиэ ми клирли | Hi, can you hear me clearly, or should I use my headphones? | an HR manager on a laptop video call speaking to a candidate, home office background, remote interview | нет |
| I'm a PHP developer with three years of experience. | line | — | learner | Я PHP-разработчик с опытом в три года. | айм эй пи-эйч-пи дивелопер уиз сри йирз ов икспириэнс | I'm a PHP developer with three years of experience, mostly in web products. | a software developer introducing himself on a video interview, laptop open on a desk, calm home workspace | нет |
| Right now, I mainly work on web applications. | line | Right now, I mainly work on ___. | learner | Сейчас я в основном работаю над веб-приложениями. | райт нау ай мейнли уорк он вэб эпликейшнз | Right now, I mainly work on web applications for internal tools. | a developer talking during a video interview, laptop camera on, coding workspace in the background | нет |
| I also work with remote teams. | line | I also work with ___. | learner | Я также работаю с удалёнными командами. | ай олсоу уорк уиз римоут тимз | I also work with remote teams across different time zones. | a remote developer in a home office speaking on a video call, laptop and headset visible | нет |
| I'm interested in this role because of the team. | line | I'm interested in this role because of ___. | learner | Меня интересует эта позиция из-за команды. | айм интристид ин зис роул бикоз ов зэ тим | I'm interested in this role because of the team and its international setup. | a job candidate explaining motivation on a video interview, speaking confidently to the camera | нет |
| I want to improve my English in a real work environment. | line | I want to improve my English in a real ___. | learner | Я хочу улучшать свой английский в реальной рабочей среде. | ай уонт ту импрув май инглиш ин э риэл уорк инвайрэнмэнт | I want to improve my English in a real work environment, not only in courses. | a software developer speaking thoughtfully during an online interview, home office, laptop camera view | нет |
| I like working with an English-speaking team. | line | I like working with an English-speaking ___. | learner | Мне нравится работать с англоязычной командой. | ай лайк уоркинг уиз эн инглиш-спикинг тим | I like working with an English-speaking team because communication stays active every day. | a candidate smiling while talking on a remote interview with a laptop at home, simple professional setting | нет |
| web applications | word | — | — | веб-приложения | вэб эпликейшнз | Right now, I mainly work on web applications. | a laptop screen with a browser-based business app open, developer desk, home office | нет |
| remote teams | word | — | — | удалённые команды | римоут тимз | I also work with remote teams. | two people on separate laptop video windows collaborating from different home offices | нет |
| the team | word | — | — | команда | зэ тим | I'm interested in this role because of the team. | a small professional team in a virtual meeting on laptop screens, remote work setting | нет |
| work environment | word | — | — | рабочая среда | уорк инвайрэнмэнт | I want to improve my English in a real work environment. | a clean home office desk with laptop, headset, notebook, and calm working atmosphere | нет |
| work on | chunk | — | — | работать над | уорк он | Right now, I mainly work on internal tools. | a software developer focused on coding at a laptop during a remote interview day, home office | нет |
| be interested in | chunk | — | — | интересоваться | би интристид ин | I'm interested in this role because of growth opportunities. | a job candidate speaking with interest during an online interview, laptop camera view, home office | нет |

## Глазами

Как разговор с рекрутером это звучит правдоподобно ровно до третьей реплики. Начало живое: «Hi, can
you hear me clearly?» — «Yes, I can hear you clearly, and the connection is stable» — это те первые
двадцать секунд звонка, которые действительно происходят, и русский человек действительно на них
запинается. Дальше день превращается в анкету. Восемь реплик подряд начинаются с «I» и все восемь —
утверждения о себе: я разработчик, я работаю над, я работаю с, меня интересует, я хочу, мне
нравится. Ни одного вопроса от кандидата, ни одного «Sorry, could you repeat that?», ни одного
живого сбоя связи, хотя первое умение дня — ровно про связь. Собеседник произносит одну реплику из
восьми, и та стоит первой; остальные семь — монолог в пустоту. День обещает «начать онлайн-разговор
уверенно», а учит представляться, и делает это четырьмя способами подряд.

Вырезал бы три вещи. Первое — «I want to improve my English in a real work environment» и «I like
working with an English-speaking team»: это две формулировки одной мысли, стоящие рядом, и обе
звучат как ответ из шаблона «почему вы хотите у нас работать», который рекрутер слышал двести раз;
одной хватило бы, а на освободившееся место просится вопрос кандидата. Второе — связки `work on` и
`be interested in` как отдельные карточки: они не подстановки, а куски реплик, в которых уже стоят;
учить «work on» рядом с «Right now, I mainly work on web applications» — это учить одно и то же
дважды и тратить два места из четырнадцати. Третье — `the team` как слово дня: артикль в термине,
перевод «команда», пример дословно повторяющий реплику. Вместо этих трёх я бы взял то, чего в дне
нет совсем: переспрос, паузу и признание незнания — «Sorry, you're breaking up», «Could you repeat
the question?», «I'm not sure I understood» — потому что на настоящем звонке с английской командой
ломается именно это, а не рассказ о себе.
