# Живой прогон v0.2 — 31.08.2026

Наряд PROMPT-v0.2, Ч.5. Модель `gpt-5.4`, промпты `plan_outline.v0.2` / `plan_day.v0.2`.
Всё ниже — то, что реально вернула модель, без правок.

## Прогон (a) — Иду к врачу через 30 дней, болит спина, надо объяснить и понять назначение

`ru→en, basic, 20 мин, событие через 30 дней` · план `01M1BVKQ1AF375ER40H96D4P6Z`

**К врачу со спиной** — Через 30 дней я иду к врачу, потому что у меня болит спина, и мне нужно объяснить проблему и понять назначение.

- `constraints`: через 30 дней
- `goal_terms`: (пусто)
- `entities`: врач (masculine, singular); спина (feminine, singular); назначение (neuter, singular)

| сцена | собеседник | умение | `est_terms` | чек-пойнт |
|---|---|---|---|---|
| 1. Начать приём у врача | врач-терапевт | объяснить, зачем пришёл на приём | 4 | говорит причину визита сразу после первого вопроса, и врачу не приходится самому угадывать проблему |
|  |  | сказать, где именно болит в спине | 5 | называет конкретное место в спине, а не только общую жалобу, и врачу не приходится переспрашивать |
|  |  | сказать, как давно болит | 4 | отвечает сроком или периодом, и врач понимает, когда это началось |
| 2. Объяснить боль подробнее | врач-терапевт | сказать, какая это боль | 5 | описывает характер боли так, что врач понимает, какая она по ощущению |
|  |  | сказать, от чего становится хуже | 4 | называет хотя бы один фактор, после которого боль усиливается |
|  |  | сказать, что помогает или не помогает | 4 | отвечает, помогает ли что-то, и врач понимает результат без дополнительного уточнения |
| 3. Понять назначение врача | врач-терапевт | понять, что назначил врач, и повторить своими словами | 6 | повторяет назначение: что делать или что принимать, и врач подтверждает, что всё верно |
|  |  | понять, как часто это делать или принимать, и повторить своими словами | 5 | повторяет частоту или режим, и врач подтверждает, что режим понят правильно |
|  |  | спросить, если по назначению осталось неясно | 4 | задаёт уточняющий вопрос по назначению, когда не уверен, вместо молчания |

**Арифметика сервера:** `need` = 41, `capacity` = 14, `intro_days` = **3**, `max_days` = 31, `fits` = да

### Реплики собеседников, дословно

- **1.** «What brings you in today?» — Что вас сегодня привело?
- **1.** «Where does it hurt?» — Где болит?
- **1.** «How long has it been hurting?» — Как давно это болит?
- **2.** «Is the pain constant or does it come and go?» — Боль постоянная или появляется время от времени?
- **2.** «What makes it worse?» — От чего становится хуже?
- **2.** «Does anything help?» — Что-нибудь помогает?
- **3.** «I’m going to prescribe you some pain relief.» — Я собираюсь назначить вам обезболивающее.
- **3.** «Take it twice a day after meals.» — Принимайте это два раза в день после еды.
- **3.** «Do you have any questions?» — У вас есть вопросы?

## Прогон (b) — Онлайн-собеседование PHP-разработчика, удалённо, английская команда

`ru→en, conversational, 20 мин, событие через 5 дней` · план `01M1BVQFQMS97FCCF21N6JJ7YG`

**Собеседование PHP-разработчика онлайн** — Пройти онлайн-собеседование на позицию PHP-разработчика в английской команде.

- `constraints`: удалённо, английская команда
- `goal_terms`: PHP
- `entities`: собеседование (neuter, singular); команда (feminine, singular); позиция (feminine, singular)

| сцена | собеседник | умение | `est_terms` | чек-пойнт |
|---|---|---|---|---|
| 1. Подключиться и начать разговор | HR-менеджер на видеозвонке | подтвердить, что связь в удалённом разговоре работает | 5 | отвечает, что слышит собеседника и готов продолжать, без перехода на другой язык |
|  |  | кратко представить себя как PHP-разработчика | 7 | называет свою роль, опыт или основной профиль, и собеседнику не приходится заново спрашивать, кто он по специальности |
|  |  | сказать, что готов пройти собеседование на английском | 4 | подтверждает готовность говорить на английском в разговоре с английской командой |
| 2. Рассказать об опыте и задачах | HR-менеджер или нанимающий менеджер | рассказать о недавнем опыте работы | 7 | описывает последнее или текущее место работы через период, роль и общий характер задач |
|  |  | описать проект, над которым работал | 8 | называет тип проекта, его назначение и свой вклад в него |
|  |  | объяснить свои основные обязанности как PHP-разработчика | 6 | перечисляет регулярные обязанности по работе, а не только название должности |
| 3. Ответить на вопросы о вакансии | нанимающий менеджер английской команды | объяснить, почему интересна эта позиция | 6 | называет одну или две конкретные причины интереса к роли, а не отвечает общими словами |
|  |  | объяснить, почему хочет работать в английской команде | 5 | связывает свой интерес именно с работой в английской команде, а не только с компанией в целом |
|  |  | описать, как работает удалённо | 6 | объясняет, как организует работу и общение в удалённом формате |
| 4. Понять условия и завершить звонок | HR-менеджер в конце интервью | задать вопрос о следующих шагах после собеседования | 5 | спрашивает, что будет дальше после интервью и когда ждать ответ |
|  |  | назвать свои ожидания по условиям выхода на работу | 6 | отвечает про уровень ожиданий и срок до начала работы без долгой паузы и ухода от вопроса |
|  |  | понять объяснение следующих шагов и повторить своими словами | 7 | повторяет порядок дальнейших этапов и ожидаемый срок обратной связи, и собеседник подтверждает |

**Арифметика сервера:** `need` = 72, `capacity` = 14, `intro_days` = **5**, `max_days` = 6, `fits` = нет, отброшено умений: 1

Отброшено с хвоста (карточка «срок мал» построена из этого):
- понять объяснение следующих шагов и повторить своими словами (`est_terms` 7)

### Реплики собеседников, дословно

- **1.** «Hi, can you hear me clearly?» — Здравствуйте, вы меня хорошо слышите?
- **1.** «Could you briefly introduce yourself?» — Не могли бы вы коротко рассказать о себе?
- **1.** «Are you comfortable speaking in English?» — Вам комфортно говорить по-английски?
- **2.** «Can you tell me about your recent experience?» — Можете рассказать о своём недавнем опыте?
- **2.** «What kind of projects have you worked on?» — Над какими проектами вы работали?
- **2.** «What were your main responsibilities?» — Какие у вас были основные обязанности?
- **3.** «Why are you interested in this role?» — Почему вас интересует эта роль?
- **3.** «Why do you want to join our team?» — Почему вы хотите присоединиться к нашей команде?
- **3.** «How do you usually work with remote teams?» — Как вы обычно работаете с удалёнными командами?
- **4.** «Do you have any questions for us?» — У вас есть к нам вопросы?
- **4.** «What are your salary expectations?» — Какие у вас ожидания по зарплате?
- **4.** «What would your notice period be?» — Какой у вас срок до выхода на работу?
- **4.** «Let me explain the next steps in the process.» — Давайте я объясню следующие шаги процесса.

## Прогон (b), день 1 — ОБЕ попытки отбиты валидатором

Статус дня: `pending`, попыток: 1. Коллекция не создана, картинки не ставились — день до `ready` не дошёл.

Причины, как их назвал `PlanDayValidator` (обрезано на 500 символах — так их пишет реестр трат):

**Попытка 1** ($0.045013):

- `day.frame_share: реплик без каркаса 4 из 8, а формул должно быть не больше трети`
- `day.substitution_without_frame [work with]: ни один каркас дня не принимает это слово в дырку — его пример не собирается ни из чего`
- `day.substitution_without_frame [work on]: ни один каркас дня не принимает это слово в дырку — его пример не собирается ни из чего`
- `day.example_duplicated [work with]: этот же пример уже стоит у «web»`
- `day.example_duplicated [work on]: этот же пример уже стоит у «remote»`

**Попытка 2** ($0.047088):

- `day.frame_share: реплик без каркаса 3 из 8, а формул должно быть не больше трети`
- `day.substitution_without_frame [work with]: ни один каркас дня не принимает это слово в дырку — его пример не собирается ни из чего`
- `day.substitution_without_frame [be ready to]: ни один каркас дня не принимает это слово в дырку — его пример не собирается ни из чего`
- `day.example_is_a_term [start]: пример — это дословно термин «Yes, I can hear you clearly, and I'm ready to start.», а не предложение с ним внутри`
- `day`

### Вторая попытка целиком

| термин | kind | frame | speaker | перевод | чтение | пример | image_api_prompt |
|---|---|---|---|---|---|---|---|
| Hi, can you hear me clearly? | line |  | role | Здравствуйте, вы меня хорошо слышите? | хай кэн ю хир ми клирли | Hi, can you hear me clearly, or should I adjust my microphone? | a recruiter on a video call asking an opening question, laptop camera view, home office |
| Yes, I can hear you clearly, and I'm ready to start. | line | Yes, I can hear you clearly, and I'm ready to ___. | learner | Да, я вас хорошо слышу и готов уже начать. | йес ай кэн хир ю клирли энд айм рэди ту старт | Yes, I can hear you clearly, and I'm ready to start if you are. | a job candidate speaking confidently on a video interview with headphones and laptop, home desk |
| I'm a ___ developer with three years of experience. | line | I'm a ___ developer with three years of experience. | learner | Я ___ разработчик с опытом работы три года. | айм э дэвэлопэр уиз сри йирз ов икспириэнс | I'm a backend developer with three years of experience in product teams. | a software developer introducing himself during a video interview, speaking to a laptop camera |
| My main focus has been ___ development. | line | My main focus has been ___ development. | learner | Моим основным направлением уже была ___ разработка. | май мейн фоукэс хэз бин дивэлопмэнт | My main focus has been web development, mostly on internal products. | a candidate explaining his technical background on a video call, laptop open on a desk |
| I mainly work with ___ in backend projects. | line | I mainly work with ___ in backend projects. | learner | Я в основном работаю с ___ в backend проектах. | ай мейнли уорк уиз ин бэкэнд проджектс | I mainly work with PHP in backend projects for web platforms. | a remote job candidate talking about his programming stack during an online interview |
| I'm comfortable speaking in ___ during interviews. | line | I'm comfortable speaking in ___ during interviews. | learner | Мне комфортно говорить на ___ во время собеседований. | айм камфэртэбл спикин ин дьюринг интервьюз | I'm comfortable speaking in English during interviews with international teams. | a developer answering a language question on a video interview, calm expression, laptop on desk |
| I'm happy to continue in English with your team. | line |  | learner | Я с удовольствием готов продолжить на английском с вашей командой. | айм хэпи ту континью ин инглиш уиз ё тим | I'm happy to continue in English with your team for the rest of the call. | a candidate smiling and agreeing to continue an interview in English on a video call |
| Could you briefly introduce yourself? | line |  | role | Не могли бы вы коротко рассказать о себе? | куд ю брифли интрэдьюс ёселф | Could you briefly introduce yourself and tell us about your background? | a recruiter on a video call asking the candidate to introduce himself, simple office background |
| backend | word | — | — | backend | бэкэнд | I'm a backend developer with three years of experience. | a close-up of code on a laptop screen during a remote developer interview, no visible text |
| web | word | — | — | веб | вэб | My main focus has been web development. | a developer discussing website projects on a video call, laptop and headset on desk |
| English | word | — | — | английский | инглиш | I'm comfortable speaking in English during interviews. | a candidate in an online interview speaking confidently to an international team, home office |
| start | word | — | — | начать | старт | Yes, I can hear you clearly, and I'm ready to start. | a candidate ready to begin a video interview, hands near keyboard, attentive posture |
| work with | chunk | — | — | работать с | уорк уиз | I mainly work with PHP in backend projects. | a software developer discussing his main programming language on a remote interview call |
| be ready to | chunk | — | — | быть готовым | би рэди ту | Yes, I can hear you clearly, and I'm ready to start. | a job candidate prepared to begin an online interview, sitting upright in front of a laptop |

