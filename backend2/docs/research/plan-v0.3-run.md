# Живой прогон v0.3 — 31.08.2026

Наряд PROMPT-v0.3, Ч.4. Промпты `plan_outline.v0.2` / `plan_day.v0.3`, модель `gpt-5.4`.
План `01M1C5TE5YS3N623E53HPSA9FN`, владелец, `ru→en, conversational, 20 мин, событие через 5 дней`.
Цель дословно: «Онлайн-собеседование PHP-разработчика, удалённо, английская команда» — та же, что в
прогонах v0.2 и v0.2.1. Всё ниже — то, что реально вернула модель, без правок.

## СНАЧАЛА ГЛАВНОЕ: каркас собрался, день — нет, но промахнулся он один раз из тринадцати

**$0.135838 из капа $0.20 за этот прогон** (три вызова). На плане суммарно $0.204366 — в нём лежат
ещё два отбитых каркаса от предыдущего захода, оплаченные под старым капом.

| вызов | $ | итог |
|---|---|---|
| *(прежний заход)* P1, попытка 1 | 0.033985 | отбит: `outcome_two_actions` + `skill_count` 13 |
| *(прежний заход)* P1, попытка 2 | 0.034543 | отбит: `skill_count` 13 |
| P1 «собеседование» | 0.034105 | **ПРИНЯТ с первой попытки** — с warning `plan_outline_skill_count` |
| P2 день 1, запуск 1 | 0.049418 | отбит: **12 нарушений** |
| P2 день 1, запуск 2 (повтор со всеми нарушениями) | 0.052315 | отбит: **1 нарушение** |
| | **0.135838** (прогон) / **0.204366** (план) | |

День 1 до `ready` не дошёл: `failed`, `generation_attempts=2`, коллекция не создана, **картинок нет
ни одной** — ставить их не на что. `image_api_prompt` модель написала на всех 14 карточках в обоих
ответах.

Третьего вызова не делал и промпт не чинил — наряд даёт дню два вызова, оба израсходованы.

## Решение «числа — ориентир» окупилось в тот же день

Каркас пришёл с **13 умениями** — ровно то число, за которое его отбивали дважды час назад. Теперь
это warning, каркас принят, а хвост срезал планировщик:

```
[15:26:28] WARNING Plan answer has a shape defect
  {"counter":"plan_outline_skill_count","plan_id":"01M1C5TE5YS3N623E53HPSA9FN","day_index":null,
   "detail":"умений в плане 13, а ориентир промпта — 3–12; каркас принят, хвост режет планировщик",
   "kept":true}
```

Счётчик `plan_outline_skill_count` = 1. Арифметика сервера: `need` 78, `capacity` 14,
`intro_days` 5, `max_days` 6, `fits` = **нет**, и планировщик **сам отбросил два умения** («сказать,
когда готов выйти на работу», «понять следующие шаги удалённого процесса и повторить своими
словами») — то есть сделал ровно то, ради чего гейт перестал быть фатальным. 4 сцены, `est_terms`
5–8, все в ориентире.

## День: 12 нарушений → 1, и накопительный повтор — причина

### Попытка 1 (12 нарушений)

Механика v0.3 сработала наполовину, и промахнулась там, куда дефект переехал:

- **`___` вне `frame` — 4 раза, все в `translation`.** Модель перевела КАРКАС, а не собранную
  реплику: «Сейчас я ___ разработчиком», «У меня ___ опыта с PHP». Это тот же класс, что убивал
  v0.2.1, но переехавший: из `text` его выселила сборка, и он всплыл в соседнем поле, которое
  модель пишет сама. Гейт `day.slot_outside_frame` назвал поле и карточку;
- **клонов 6**: пять примеров слов дословно повторяли реплику дня, шестой дублировал чужой пример;
- `day.key_is_the_term [backend]` — перевод «backend» словом «backend»;
- `day.substitution_without_frame [English team]` — слово не встало в дырку ни одного каркаса.

Зато: **`filler` не совпал с карточкой — 0 раз**, все пять наполнителей посимвольно равны `text`
карточек дня. Класс «дырка в самом тексте реплики» — **0 из 8** уже на холодном вызове, чего не
случалось ни разу за четыре ответа v0.2.1.

### Попытка 2 (1 нарушение)

Повтор получил все 12 нарушений накопительно и **починил все двенадцать**: ни одного `___` вне
`frame`, ни одного клона, ключ `backend` → «серверная часть», `English team` встало в дырку реплики 8.

Единственное новое нарушение — `day.chunk_without_frame [breaking up]`. Разбор:

```
реплика 2:  frame «Sorry, the connection is ___»   filler «breaking up»
            → text «Sorry, the connection is breaking up»
связка:     «breaking up», пример «The connection is breaking up again on my side.»
```

Пример хороший: из ситуации дня, не клон реплики (на попытке 1 он им как раз был, и гейт
`example_duplicated` это назвал). Отбило его то, что модель, убирая клон, **отбросила ведущее
«Sorry,»** — и предложение перестало содержать каркас дня целиком. Правило требует весь каркас; тут
оно сработало формально верно и по существу спорно.

## Выгрузка: попытка 2 целиком

Колонка «картинка» — `нет` у всех четырнадцати: день не дошёл до `ready`, коллекция не создана,
`AttachImagesJob` не ставился.

| термин (собранный `text`) | kind | frame | filler | speaker | перевод | чтение | пример | картинка |
|---|---|---|---|---|---|---|---|---|
| Yes, I can hear and see you clearly. | line | — (формула) | — | learner | Да, я вас хорошо слышу и вижу. | йес ай кэн хир энд си ю клирли | Yes, I can hear and see you clearly, so we can start. | нет |
| Sorry, the connection is breaking up | line | Sorry, the connection is ___ | breaking up | learner | Извините, связь прерывается. | сорри зэ кэнекшн из брейкин ап | Sorry, the connection is breaking up, so there is a short delay. | нет |
| Could you repeat the question? | line | — (формула) | — | learner | Не могли бы вы повторить вопрос? | куд ю рипит зэ квестчен | Could you repeat the question, please? | нет |
| Right now, I work as a backend developer. | line | Right now, I work as a ___ developer. | backend | learner | Сейчас я работаю как разработчик серверной части. | райт нау ай ворк эз э бэкенд девелопер | Right now, I work as a backend developer at a product company. | нет |
| I have four years of experience with PHP. | line | I have ___ of experience with PHP. | four years | learner | У меня четыре года опыта с PHP. | ай хэв фор йирз ов икспириэнс уиз пи-эйч-пи | I have four years of experience with PHP, mostly in product teams. | нет |
| I mainly work on web applications. | line | I mainly work on ___. | web applications | learner | В основном я работаю над веб-приложениями. | ай мэйнли ворк он вэб эпликейшнз | I mainly work on web applications for internal business tools. | нет |
| I am interested in this role because I want to work on more complicated products. | line | — (формула) | — | learner | Мне интересна эта позиция, потому что я хочу решать более сложные задачи. | ай эм интристид ин зис роул бикоз ай уонт ту ворк он мор компликейтид продактс | …with a bigger scope. | нет |
| I also like the idea of working in an English team. | line | I also like the idea of working in an ___. | English team | learner | Мне также нравится идея работать в английской команде. | ай олсо лайк зи айдиэ ов воркинг ин эн инглиш тим | …because I use English every day. | нет |
| backend | word | — | — | — | серверная часть | бэкенд | Right now, I work as a backend developer in an e-commerce company. | нет |
| four years | word | — | — | — | четыре года | фор йирз | I have four years of experience with PHP in two different companies. | нет |
| web applications | word | — | — | — | веб-приложения | вэб эпликейшнз | I mainly work on web applications for small business clients. | нет |
| English team | word | — | — | — | английская команда | инглиш тим | I also like the idea of working in an English team on remote projects. | нет |
| breaking up | chunk | — | — | — | прерывается | брейкин ап | The connection is breaking up again on my side. | нет |
| work on | chunk | — | — | — | работать над | ворк он | I mainly work on internal web tools at my current job. | нет |

## Числа, как их просил наряд (попытка 2, в скобках — попытка 1)

| число | значение |
|---|---|
| `___` вне `frame` | **0** (было 4, все в `translation`) |
| `filler` ≠ карточка дня | **0** (и 0 на попытке 1) |
| слов в дырке | **4 из 4** (было 3 из 4) |
| связок в каркасе дня | **2 из 2** — «breaking up» в дырке реплики 2, «work on» в неподвижной части реплик 6 и 7 |
| клонов примеров | **0** (было 6) |
| формул | **3 из 8** при потолке `ceil(8/3)` = 3 → warning не сработал |
| вопросов от юзера | **1** («Could you repeat the question?») |
| реплик-починок | **2** («Sorry, the connection is breaking up», «Could you repeat the question?») |
| реплик собеседника | **0 из 8** |
| warning-и со счётчиками | `plan_outline_skill_count` = 1 (каркас, 13 умений, принят). Дневных — ни одного: формулы в потолке, вопрос есть, починка есть, `filler` совпал |

## Глазами

Первые три реплики — лучшее, что план выдал за три версии, и ровно то, чего просила прошлая
вычитка. «Yes, I can hear and see you clearly» → «Sorry, the connection is breaking up» → «Could you
repeat the question?» — это первые тридцать секунд удалённого звонка, где русский человек и правда
запинается, и теперь у него есть чем починить разговор, а не только чем о себе рассказать. Дальше,
к сожалению, снова анкета: пять реплик подряд начинаются с «I» и все пять — утверждения о себе (я
бэкендер, у меня четыре года, я работаю над, мне интересна роль, мне нравится идея). День обещает
«начать звонок и представиться» — и представляется пятью способами. И главное, чего нет совсем:
**собеседник не произносит ни одной реплики из восьми**. Каркас дал сцене три `opening_lines`
(«Can you hear me okay?» и другие), день не процитировал ни одной — а первая реплика юзера «Yes, I
can hear and see you clearly» отвечает на вопрос, которого в дне нет. Юзер учит ответы на реплики,
которых не слышал.

Вырезал бы две вещи. Первое — «I am interested in this role because I want to work on more
complicated products» и «I also like the idea of working in an English team»: это две формулировки
одного ответа на «почему вы к нам», стоящие рядом, и обе длиной с абзац на уровне, где реплика
должна быть 7–12 слов. Одной хватит, а освободившееся место просится под реплику собеседника —
хотя бы «Can you hear me okay?», на которую реплика 1 и отвечает. Второе — связка `work on`
отдельной карточкой: она уже стоит в неподвижной части двух реплик дня, и учить её рядом с «I
mainly work on web applications» значит учить одно и то же дважды и тратить одно место из
четырнадцати. Взял бы вместо неё то, чего в дне нет: паузу на подумать («Let me think for a
second») — потому что на звонке с английской командой ломается и это тоже.

## Что решено по итогам первого захода

Все три вопроса закрыты архитектором в тот же день (DECISIONS п. 200, коммит `44fc10f`):
`chunk_without_frame` стал warning-ом `plan_day_chunk_outside_frame`; появился пол под
собеседником — `plan_day_no_role_line`; третий вопрос оставлен как есть.

---

# Второй заход: тот же день, один вызов на новых гейтах

**$0.051698 из капа $0.10.** Один платный вызов. День 1 получил обратно ОДНУ попытку через домен
(`PlanDay::reopenForRetry()`, не UPDATE), с сохранёнными нарушениями всех прошлых попыток — 13
строк, и они действительно уехали в промпт (проверено по `api_request_logs`: хвост user-сообщения
содержит все тринадцать).

## Итог: `ready` не достигнут, и это уже не про связку

День снова `failed`. Отбили **четыре** нарушения, и ни одного нового класса:

```
day.substitution_without_frame [English team]
day.slot_outside_frame [Right now, I am a backend developer.]      `translation` содержит «___»
day.slot_outside_frame [I have four years of experience with PHP.] `translation` содержит «___»
day.slot_outside_frame [I mainly work on web applications.]        `translation` содержит «___»
```

**Это ровно те классы, которые попытка 2 уже починила, и которые стояли в списке из 13, показанном
этому вызову.** Связка `breaking up` на этот раз прошла — новый warning сработал как задумано; на
её месте вернулись дефекты, которых на попытке 2 не было.

| попытка | `___` вне frame | filler ≠ карточка | слов в дырке | формул | клонов | вопрос | починка | реплик role |
|---|---|---|---|---|---|---|---|---|
| 1 (холодная) | 4 | 0 | 3 из 4 | 3 из 8 | 6 | 1 | 2 | 0 |
| 2 (повтор, 12 нарушений) | **0** | 0 | **4 из 4** | 3 из 8 | **0** | 1 | 2 | 0 |
| 3 (повтор, 13 нарушений) | **3** | 0 | 3 из 4 | **4 из 8** | 0 | 1 | 2 | 0 |

## Главная находка: накопительный список сам стал шаблоном неправильного ответа

Гипотеза, ради которой накопительность вводилась, — «модель, которой сказали только про последнее,
ломает то, что починила раньше» — на попытке 2 подтвердилась блестяще (12 → 1). На попытке 3 она
обернулась против себя.

Список из 13 нарушений **цитирует тексты ПЕРВОЙ попытки**: «Right now, I am a backend developer.»,
«I have four years of experience with PHP.», «I mainly work on web applications.». Третий ответ
вернул ровно эти реплики — включая `Right now, I am a **___** developer.` с дыркой в переводе,
которую попытка 2 уже писала правильно. Похоже, что подробный список чужих ошибок с процитированными
предложениями работает как ОБРАЗЕЦ: модель воспроизводит разобранный ответ, а не избегает его.

Это не доказано одним прогоном, но это первое наблюдение, которое противоречит п. 199, и оно стоило
$0.05. Возможные развилки (решение архитектора, здесь ничего не чинилось):

- накапливать **коды** нарушений без процитированных текстов карточек;
- накапливать только те, что повторились дважды;
- не накапливать вовсе после попытки 2 — то есть считать, что накопительность окупается ровно на
  один повтор.

## Два новых warning-а сработали на живом ответе

И ровно так, как задумано — в лог на отбитой попытке, в счётчик не попали:

```
[15:50:51] WARNING Plan answer has a shape defect
  {"counter":"plan_day_formula_cap","plan_id":"01M1C5TE…","day_index":1,
   "detail":"реплик без дырки 4 из 8, а треть с округлением вверх — это 3","kept":false}
[15:50:51] WARNING Plan answer has a shape defect
  {"counter":"plan_day_no_role_line","plan_id":"01M1C5TE…","day_index":1,
   "detail":"ни одной реплики собеседника: у сцены есть opening_lines, но день не процитировал
             ни одной — юзер учит ответы на то, чего не слышал","kept":false}
```

Счётчики `plan_day_formula_cap` и `plan_day_no_role_line` = 0. Форма отбитого дня видна, и при этом
ни один счётчик не растёт от того, что машина верно сказала «нет», — обе половины решения по
доработке 2 подтверждены живым ответом.

`plan_day_chunk_outside_frame` не сработал ни разу: связка на попытке 3 стояла в каркасе
(«Sorry, the connection is breaking up on my side today.» содержит каркас реплики 2 целиком).

## Выгрузка попытки 3

Дня в `ready` нет, коллекции нет, **картинок нет ни одной** — третий раз подряд. `image_api_prompt`
на всех 14 карточках написан.

| термин (собранный `text`) | kind | frame | filler | speaker | перевод | чтение | пример | картинка |
|---|---|---|---|---|---|---|---|---|
| Yes, I can hear and see you clearly. | line | — | — | learner | Да, я вас хорошо слышу и вижу. | йес ай кэн хир энд си ю клирли | Yes, I can hear and see you clearly now. | нет |
| Sorry, the connection is breaking up | line | Sorry, the connection is ___ | breaking up | learner | Извините, связь прерывается. | сори зэ кэнекшн из брейкин ап | Sorry, the connection is breaking up again on my side. | нет |
| Could you repeat the question? | line | — | — | learner | Не могли бы вы повторить вопрос? | куд ю рипит зэ квесчн | Could you repeat the question, please, if the sound is low? | нет |
| Right now, I am a backend developer. | line | Right now, I am a ___ developer. | backend | learner | **Сейчас я работаю ___ разработчиком.** ✘ | райт нау ай эм э бэкенд дивелэпэр | Right now, I am a backend developer at a small product company. | нет |
| I have four years of experience with PHP. | line | I have ___ of experience with PHP. | four years | learner | **У меня ___ опыта с PHP.** ✘ | ай хэв фор йирз ов икспириэнс уиз пи-эйч-пи | I have four years of experience with PHP, mostly in product teams. | нет |
| I mainly work on web applications. | line | I mainly work on ___. | web applications | learner | **В основном я работаю над ___.** ✘ | ай мэйнли ворк он вэб эпликейшнз | I mainly work on web applications for internal business tools. | нет |
| I am interested in this role because I want to work on more complicated products. | line | — (формула) | — | learner | Мне интересна эта роль, потому что я хочу работать над более сложными продуктами. | ай эм интристид… | …in the future. | нет |
| I also like the idea of working in an English team. | line | — (формула) | — | learner | Мне также нравится идея работать в английской команде. | ай олсо лайк зи айдиэ… | …because I use English every day. | нет |
| backend | word | — | — | — | серверная часть | бэкенд | Right now, I am a backend developer in a remote product team. | нет |
| four years | word | — | — | — | четыре года | фор йирз | I have four years of experience with PHP in commercial projects. | нет |
| web applications | word | — | — | — | веб-приложения | вэб эпликейшнз | I mainly work on web applications for business clients. | нет |
| English team | word | — | — | — | английская команда | инглиш тим | **I want to work in an English team to improve my communication.** ✘ не каркас дня | нет |
| breaking up | chunk | — | — | — | прерывается | брейкин ап | Sorry, the connection is breaking up on my side today. | нет |
| work on | chunk | — | — | — | работать над | ворк он | I mainly work on web applications for product companies. | нет |

## Глазами (попытка 3)

Как разговор это шаг назад от попытки 2 при том же наборе слов. Начало держится: «Yes, I can hear
and see you clearly» → «Sorry, the connection is breaking up» → «Could you repeat the question,
please, if the sound is low?» — три реплики, которые на удалённом звонке действительно спасают, и
третья даже стала конкретнее. Но две последние реплики потеряли дырку и превратились в заученные
абзацы: «I am interested in this role because I want to work on more complicated products» — это
пятнадцать слов, которые юзер должен произнести целиком и без вариантов, при уровне, где реплика
рассчитана на 7–12. Из восьми реплик каркас остался у четырёх, и день из «набора, который
комбинируется» наполовину вернулся в «восемь предложений, которые надо запомнить».

Вырезал бы обе эти реплики и обе поставил бы обратно как каркасы: «I am interested in this role
because of ___» и «I also like ___» — слова для дырок в дне уже есть («English team»), и именно
`English team` сейчас висит без каркаса и роняет день. И, как и в прошлый раз, забрал бы одно место
под реплику собеседника: каркас дал сцене три `opening_lines`, день снова не процитировал ни одной,
а первая реплика юзера по-прежнему отвечает на вопрос, которого в дне нет. Новый warning
`plan_day_no_role_line` это теперь говорит вслух — но только в лог, и день с молчащим собеседником
всё ещё пишется.

## Итоги по деньгам

| заход | вызовов | $ |
|---|---|---|
| прежний (кап $0.15) | 2 отбитых каркаса | 0.068528 |
| первый (кап $0.20) | каркас + 2 дня | 0.135838 |
| второй (кап $0.10) | 1 день | 0.051698 |
| **по плану `01M1C5TE5YS3N623E53HPSA9FN`** | **6** | **0.256064** |

День 1 снова `failed`, `generation_attempts=2`. **С v0.1 ни один день плана не доходил до `ready` на
боевых промптах**, и картинок у планов владелец по-прежнему не видел.
