<!-- snapshot: 2026-08-13T12:27:26+00:00 · head: 99637a1d1baa -->
# Выгрузка станка на вычитку

Снимок: **2026-08-13T12:27:26+00:00** · HEAD: `99637a1d1baa` · версия генератора: `enrich-v1`.

Снимок старше правок в базе — выгрузку надо снять заново: то, что здесь написано, было
верно на момент снимка и с тех пор могло быть починено.

Термины без вариантов, дистракторов и флагов в выгрузку не попадают — это рабочий список,
а не дамп базы. Колонка «флаги» — то, что требует решения человека.

## Boxing Practice Essentials

### boxing gloves

- **перевод (промпт):** боксерские перчатки
- **эталон-пример:** Don't forget to bring your boxing gloves to practice.
- **дистракторы:**
    - `Don't forget to bring boxing gloves to practice.` — article: **boxing gloves** → `your boxing gloves`

### throw a punch

- **перевод (промпт):** наносить удар
- **эталон-пример:** Learn how to throw a punch correctly to avoid injury.
- **дистракторы:**
    - `Learn how to throw punch correctly to avoid injury.` — article: **throw punch** → `throw a punch`

### sparring session

- **перевод (промпт):** тренировочный спарринг
- **эталон-пример:** We have a sparring session scheduled for tomorrow.
- **принимаемые варианты:**
    - `practice sparring` — _Это тоже альтернативное выражение для тренировочного спарринга._
    - `training sparring` — _Это синоним для тренировочного спарринга._
- **дистракторы:**
    - `We have sparring session scheduled for tomorrow.` — article: **sparring session** → `a sparring session`
    - `We have a sparring sessions scheduled for tomorrow.` — tense: **sparring sessions** → `sparring session`

### footwork

- **перевод (промпт):** работа ног
- **эталон-пример:** Good footwork is essential for any boxer.
- **дистракторы:**
    - `Good footwork are essential for any boxer.` — tense: **are** → `is`
    - `Good footwork is essential for the any boxer.` — article: **the any** → `any`

### corner

- **перевод (промпт):** угол ринга
- **эталон-пример:** Return to your corner and listen to your coach.
- **дистракторы:**
    - `Return to your corner and listen for your coach.` — preposition: **for** → `to`
    - `Return to your corner and listen to your coachs.` — tense: **coachs** → `coach.`

### uppercut

- **перевод (промпт):** удар снизу
- **эталон-пример:** He knocked his opponent out with a quick uppercut.
- **дистракторы:**
    - `He knocked his opponent out with quick uppercut.` — article: **quick uppercut** → `a quick uppercut`
    - `He knocked out his opponent with a quick uppercut.` — word_order: **knocked out his opponent** → `knocked his opponent out`
    - `He knocked his opponent out with a quick uppercuts.` — article: **a quick uppercuts** → `a quick uppercut`

### heavy bag

- **перевод (промпт):** тяжелая груша
- **эталон-пример:** Practice your punches on the heavy bag.
- **дистракторы:**
    - `Practice your punches on the heavy bags.` — article: **the heavy bags** → `the heavy bag`
    - `Practice your punches at the heavy bag.` — preposition: **at the heavy bag** → `on the heavy bag`

### jab

- **перевод (промпт):** прямой удар
- **эталон-пример:** A quick jab can keep your opponent at bay.
- **принимаемые варианты:**
    - `straight punch` — _Это синоним термина._
    - `straight hit` — _Это синоним термина._
- **дистракторы:**
    - `A quick jab can keep your opponents at bay.` — tense: **opponents** → `opponent`

### keep your guard up

- **перевод (промпт):** держи защиту
- **эталон-пример:** Always keep your guard up in the ring.
- **дистракторы:**
    - `Always keep guard up in the ring.` — article: **guard up** → `your guard up`
    - `Always keep your guard on in the ring.` — preposition: **on** → `up`
    - `Always keeps your guard up in the ring.` — tense: **keeps** → `keep`

### combination

- **перевод (промпт):** комбинация ударов
- **эталон-пример:** Practice your combination punches with a partner.
- **дистракторы:**
    - `Practice your combination punches with partner.` — article: **with partner** → `with a partner`

### focus mitts

- **перевод (промпт):** ударные лапы
- **эталон-пример:** We'll use focus mitts to work on your accuracy.
- **дистракторы:**
    - `We'll use focus mitt to work on your accuracy.` — article: **mitt** → `mitts`
    - `We'll use focus mitts for work on your accuracy.` — preposition: **for work** → `to work`

### ring

- **перевод (промпт):** ринг
- **эталон-пример:** The fighters entered the ring for the match.
- **дистракторы:**
    - `The fighters entered a ring for the match.` — article: **a** → `the`

### tape your hands

- **перевод (промпт):** бинтовать руки
- **эталон-пример:** Make sure to tape your hands before putting on gloves.
- **дистракторы:**
    - `Make sure to tape your hands before to putting on gloves.` — modal_to: **to putting** → `putting`
- **флаги:**
    - ✍️ **не слово → правка** — бинтовать - перебинтовать

### cross

- **перевод (промпт):** кросс (удар)
- **эталон-пример:** The boxer delivered a powerful cross to his opponent's jaw.
- **дистракторы:**
    - `The boxer deliver a powerful cross to his opponent's jaw.` — tense: **deliver** → `delivered`
    - `The boxer delivered a powerful cross in his opponent's jaw.` — preposition: **in** → `to`

### work on your speed

- **перевод (промпт):** работать над скоростью
- **эталон-пример:** You need to work on your speed to improve your performance.
- **дистракторы:**
    - `You need to works on your speed to improve your performance.` — tense: **works** → `work`
    - `You need to work in your speed to improve your performance.` — preposition: **in** → `on`

## Drawing Essentials

### pencil

- **перевод (промпт):** карандаш
- **эталон-пример:** I always start my drawings with a pencil sketch.
- **принимаемые варианты:**
    - `colored pencil` — _цветной карандаш_
    - `graphite pencil` — _графитный карандаш_
- **дистракторы:**
    - `I always start my drawings with pencil sketch.` — article: **pencil** → `a pencil`

### erase

- **перевод (промпт):** стирать
- **эталон-пример:** I had to erase the mistake and start again.
- **дистракторы:**
    - `I had to erase на mistake and start again.` — preposition: **на mistake** → `the mistake`
    - `I had to erase the mistakes and start again.` — tense: **the mistakes** → `the mistake`
    - `I had to erase mistake and start again.` — article: **erase mistake** → `erase the mistake`

### shading

- **перевод (промпт):** затенение
- **эталон-пример:** Good shading gives depth to a drawing.
- **дистракторы:**
    - `Good shading gives a depth to a drawing.` — article: **a depth** → `depth`
    - `Good shading give depth to a drawing.` — tense: **give** → `gives`

### add color

- **перевод (промпт):** добавить цвет
- **эталон-пример:** You can add color to your drawing with markers or paint.
- **дистракторы:**
    - `You can add color to your drawing with marker or paint.` — false_friend: **with marker** → `with markers`
    - `You can add the color to your drawing with markers or paint.` — article: **the color** → `color`
    - `You can add color in your drawing with markers or paint.` — preposition: **in your drawing** → `to your drawing`

### still life

- **перевод (промпт):** натюрморт
- **эталон-пример:** I chose a bowl of fruit for my still life drawing.
- **дистракторы:**
    - `I chose a bowl of fruits for my still life drawing.` — false_friend: **fruits** → `fruit`

### sketch

- **перевод (промпт):** набросок
- **эталон-пример:** He quickly made a sketch of the landscape.
- **дистракторы:**
    - `He quickly made the sketch of the landscape.` — article: **the sketch** → `a sketch`

### life drawing

- **перевод (промпт):** живой рисунок
- **эталон-пример:** Life drawing classes help improve observation skills.
- **дистракторы:**
    - `Life drawing classes help to improve observation skills.` — modal_to: **to improve** → `improve`

### brush

- **перевод (промпт):** кисть
- **эталон-пример:** I use a fine brush for detailed work.
- **дистракторы:**
    - `I use a fine brush for detailed works.` — tense: **works** → `work`
    - `I use a fine for detailed work.` — preposition: **for** → `brush for`
    - `I use the fine brush for detailed work.` — article: **the** → `a`

### layer

- **перевод (промпт):** слой
- **эталон-пример:** Applying layer upon layer creates texture in the artwork.
- **принимаемые варианты:**
    - `coat` — _Синоним._
    - `stratum` — _Синоним._
- **дистракторы:**
    - `Applying layer upon layer create texture in the artwork.` — tense: **create** → `creates`
    - `Applying the layer upon layer creates texture in the artwork.` — article: **the layer** → `layer`

### blend colors

- **перевод (промпт):** смешивать цвета
- **эталон-пример:** Artists often blend colors to achieve the right shade.
- **дистракторы:**
    - `Artists often blend colors for achieve the right shade.` — preposition: **for achieve** → `to achieve`
    - `Artists often blend the colors to achieve the right shade.` — article: **the colors** → `colors`

### fixative

- **перевод (промпт):** фиксатив
- **эталон-пример:** I always apply a fixative to charcoal drawings to prevent smudging.
- **дистракторы:**
    - `I always apply a fixative in charcoal drawings to prevent smudging.` — preposition: **in** → `to`

### perspective

- **перевод (промпт):** перспектива
- **эталон-пример:** Understanding perspective is key to creating realistic art.
- **дистракторы:**
    - `Understanding the perspective is key to creating realistic art.` — article: **the perspective** → `perspective`
    - `Understanding perspective are key to creating realistic art.` — tense: **are** → `is`

### light and shadow

- **перевод (промпт):** свет и тень
- **эталон-пример:** The interplay of light and shadow adds drama to the picture.
- **дистракторы:**
    - `The interplay of the light and shadow adds drama to the picture.` — article: **the light and shadow** → `light and shadow`
    - `The interplay of light and shadow adds drama for the picture.` — preposition: **for the** → `to the`
    - `The interplay of light and shadow add drama to the picture.` — tense: **add** → `adds`

### cross-hatching

- **перевод (промпт):** штриховка
- **эталон-пример:** Cross-hatching is a technique to create texture and depth.
- **дистракторы:**
    - `Cross-hatching is technique to create texture and depth.` — article: **technique** → `a technique`

## Job Interview Essentials

### What are your strengths?

- **перевод (промпт):** Каковы ваши сильные стороны?
- **эталон-пример:** What are your strengths that relate to this IT position?
- **дистракторы:**
    - `What are your strengths relate to this IT position?` — tense: **strengths relate** → `strengths that relate`
    - `What are your strengths in relate to this IT position?` — preposition: **in relate** → `that relate`
    - `What are strengths that relate to this IT position?` — article: **strengths** → `your strengths`

### I have experience in...

- **перевод (промпт):** У меня есть опыт в...
- **эталон-пример:** I have experience in customer service.
- **дистракторы:**
    - `I have experience at customer service.` — preposition: **at** → `in`
    - `I has experience in customer service.` — tense: **has** → `have`

### salary

- **перевод (промпт):** зарплата
- **эталон-пример:** What are your salary expectations?
- **дистракторы:**
    - `What are the salary expectations?` — article: **the salary** → `your salary`
    - `What are your salary expectation?` — tense: **expectation** → `expectations`

### What are your weaknesses?

- **перевод (промпт):** Каковы ваши слабые стороны?
- **эталон-пример:** Could you tell us about one of your weaknesses?
- **дистракторы:**
    - `Could you tell us about your weakness?` — article: **your weakness** → `one of your weaknesses`
    - `Could you tells us about one of your weaknesses?` — tense: **tells** → `tell`

### Could you give an example?

- **перевод (промпт):** Могли бы вы дать пример?
- **эталон-пример:** Could you give an example of a successful IT project you led?
- **дистракторы:**
    - `Could you give example of a successful IT project you led?` — article: **give example** → `give an example`

### team player

- **перевод (промпт):** командный игрок
- **эталон-пример:** I consider myself a team player, always collaborating with others.
- **дистракторы:**
    - `I consider myself a team player, always collaborating in others.` — preposition: **collaborating in** → `collaborating with`
    - `I consider myself team player, always collaborating with others.` — article: **team player** → `a team player`

### Thank you for your time.

- **перевод (промпт):** Спасибо за ваше время.
- **эталон-пример:** Thank you for your time and consideration.
- **дистракторы:**
    - `Thank you for time and consideration.` — article: **time** → `your time`
    - `Thank you for your time at consideration.` — preposition: **at** → `and`

### I am a quick learner.

- **перевод (промпт):** Я быстро учусь.
- **эталон-пример:** I am a quick learner, which helps me adapt easily to new roles.
- **дистракторы:**
    - `I am a quick learners, which helps me adapt easily to new roles.` — tense: **quick learners** → `quick learner`
    - `I am quick learner, which helps me adapt easily to new roles.` — article: **quick learner** → `a quick learner`

## Most Common Phrasal Verbs

### give up

- **перевод (промпт):** сдаваться, бросать
- **эталон-пример:** I won't give up until I've achieved my goals.
- **принимаемые варианты:**
    - `surrender` — _Синонім до 'здаватися'._
    - `quit` — _Синонім до 'здаватися'._
- **дистракторы:**
    - `I won't give up until I achieved my goals.` — tense: **achieved** → `have achieved`
    - `I won't give ups until I've achieved my goals.` — tense: **give ups** → `give up`
    - `I won't give up until I've achieve my goals.` — tense: **achieve** → `achieved`
- **флаги:**
    - 🇺🇦 **украинизм → переген** — Украинские буквы в русском поле (ї і): «Я не здамся, поки не досягну своїх цілей.».

### look after

- **перевод (промпт):** ухаживать, присматривать
- **эталон-пример:** Can you look after my cat while I'm away?
- **принимаемые варианты:**
    - `take care of` — _синонімічний вираз_
- **дистракторы:**
    - `Can you look after the my cat while I'm away?` — article: **the my** → `my`
    - `Can you look after my cat while I was away?` — tense: **was** → `am`
- **флаги:**
    - 🇺🇦 **украинизм → переген** — Украинские буквы в русском поле (ї є): «Можеш доглянути за моїм котом, поки мене немає?».

### find out

- **перевод (промпт):** узнавать, выяснять
- **эталон-пример:** She found out the truth about the situation.
- **принимаемые варианты:**
    - `discover` — _синоним_
    - `learn` — _синоним_
- **дистракторы:**
    - `She find out the truth about the situation.` — tense: **find** → `found`

### come up with

- **перевод (промпт):** придумывать, предлагать
- **эталон-пример:** He came up with a brilliant idea for the campaign.
- **дистракторы:**
    - `He come up with a brilliant idea for the campaign.` — tense: **come** → `came`
- **флаги:**
    - ✍️ **не слово → правка** — придумывать, предлагать are acceptable but a more specific translation for campaign is 'кампания', not 'кампании'. The word 'кампании' is in genitive case instead of accusative, causing ambiguity.

### look forward to

- **перевод (промпт):** ждать с нетерпением
- **эталон-пример:** I'm looking forward to my vacation next month.
- **принимаемые варианты:**
    - `await` — _Синоним, обозначающий ожидание._
    - `anticipate` — _Синоним, обозначающий ожидание._
- **дистракторы:**
    - `I look forward to my vacation next month.` — tense: **look** → `am looking`
    - `I'm looking forward on my vacation next month.` — preposition: **forward on** → `forward to`
- **флаги:**
    - 🇺🇦 **украинизм → переген** — Украинские буквы в русском поле (і): «Я з нетерпінням чекаю на відпустку наступного місяця.».

### turn up

- **перевод (промпт):** появляться, приходить
- **эталон-пример:** She turned up at the party unexpectedly.
- **дистракторы:**
    - `She turned up in the party unexpectedly.` — preposition: **in the party** → `at the party`
    - `She turn up at the party unexpectedly.` — tense: **turn up** → `turned up`
- **флаги:**
    - 🇺🇦 **украинизм → переген** — Украинские буквы в русском поле (і): «Вона несподівано з'явилася на вечірці.».

### pick up

- **перевод (промпт):** подбирать, забирать
- **эталон-пример:** I will pick you up at the airport at 3 PM.
- **дистракторы:**
    - `I will pick up you at the airport at 3 PM.` — word_order: **pick up you** → `pick you up`

### give in

- **перевод (промпт):** уступать, сдаваться
- **эталон-пример:** He finally gave in to their demands.
- **принимаемые варианты:**
    - `yield` — _синоним уступать_
    - `surrender` — _синоним сдаваться_
- **дистракторы:**
    - `He finally gave in at their demands.` — preposition: **at** → `to`
    - `He finally give in to their demands.` — tense: **give** → `gave`

### break up

- **перевод (промпт):** расставаться, разводиться
- **эталон-пример:** They decided to break up after five years together.
- **принимаемые варианты:**
    - `separate` — _Альтернативный перевод._
    - `split up` — _Синонимичное выражение._
- **дистракторы:**
    - `They decided to break up after five year together.` — tense: **year** → `years`

### carry on

- **перевод (промпт):** продолжать
- **эталон-пример:** She told him to carry on with his work.
- **дистракторы:**
    - `She told him to carry on with work.` — article: **work** → `his work`
    - `She told him to carry on at his work.` — preposition: **at** → `with`

### set up

- **перевод (промпт):** основывать, устраивать
- **эталон-пример:** They set up the conference room for the meeting.
- **дистракторы:**
    - `They set up conference room for the meeting.` — article: **conference room** → `the conference room`

### run out of

- **перевод (промпт):** заканчиваться, исчерпываться
- **эталон-пример:** We've run out of milk, can you buy some more?
- **дистракторы:**
    - `We've run out milk, can you buy some more?` — article: **out milk** → `out of milk`

### make up

- **перевод (промпт):** создавать, придумывать
- **эталон-пример:** She made up a story about why she was late.
- **принимаемые варианты:**
    - `invent` — _Синоним слова "make up"._
    - `create` — _Синоним слова "make up"._
- **дистракторы:**
    - `She make up a story about why she was late.` — tense: **make** → `made`
    - `She made up story about why she was late.` — article: **story** → `a story`
    - `She made up in story about why she was late.` — preposition: **in story** → `a story`

### take off

- **перевод (промпт):** взлетать, снимать
- **эталон-пример:** The plane took off on time.
- **дистракторы:**
    - `The plane take off on time.` — tense: **take off** → `took off`
    - `The plane took off in time.` — preposition: **in time** → `on time`
- **флаги:**
    - 🇺🇦 **украинизм → переген** — Украинские буквы в русском поле (і): «Літак взлетів вчасно.».
    - 🇺🇦 **украинизм → переген** — Украинские буквы в русском поле (і): «взлітати, знімати».

### put off

- **перевод (промпт):** откладывать
- **эталон-пример:** He was tired, so he put off the meeting until tomorrow.
- **принимаемые варианты:**
    - `defer` — _синонім до 'put off'_
    - `postpone` — _синонім до 'put off'_
- **дистракторы:**
    - `He was tired, so he put off the meeting until tommorrow.` — false_friend: **tommorrow** → `tomorrow`
- **флаги:**
    - 🇺🇦 **украинизм → переген** — Украинские буквы в русском поле (і): «відкладати».
    - 🇺🇦 **украинизм → переген** — Украинские буквы в русском поле (і): «Він був втомлений, тож він відклав зустріч на завтра.».

### turn down

- **перевод (промпт):** отказываться, убавлять
- **эталон-пример:** She turned down the job offer because the salary was too low.
- **принимаемые варианты:**
    - `reject` — _Синонім слова «відхилити»._
    - `decline` — _Синонім слова «відхилити»._
- **дистракторы:**
    - `She turn down the job offer because the salary was too low.` — tense: **turn** → `turned`
    - `She turned down for the job offer because the salary was too low.` — preposition: **down for** → `down`
- **флаги:**
    - ✍️ **не слово → правка** — Слово «відхилити» написано правильно.
    - 🇺🇦 **украинизм → переген** — Украинские буквы в русском поле (і): «відхилити».
    - 🇺🇦 **украинизм → переген** — Украинские буквы в русском поле (і): «Вона відхилила пропозицію роботи, тому що зарплата була занизькою.».

### get on with

- **перевод (промпт):** ладить, продолжать
- **эталон-пример:** Tom gets on well with all his classmates.
- **дистракторы:**
    - `Tom get on well with all his classmates.` — tense: **get** → `gets`

### take up

- **перевод (промпт):** заниматься, увлекаться
- **эталон-пример:** She decided to take up yoga to relax.
- **дистракторы:**
    - `She decide to take up yoga to relax.` — tense: **decide** → `decided`
    - `She decided to take up yoga for relax.` — preposition: **for** → `to`

### come across

- **перевод (промпт):** натолкнуться, случайно встретить
- **эталон-пример:** I came across an old photograph of us yesterday.
- **принимаемые варианты:**
    - `find by chance` — _Має схоже значення._
    - `stumble upon` — _Синонімічний вислів._
- **дистракторы:**
    - `I came across an old photograph of us at yesterday.` — preposition: **at yesterday** → `yesterday.`
    - `I come across an old photograph of us yesterday.` — tense: **come across** → `came across`
- **флаги:**
    - 🇺🇦 **украинизм → переген** — Украинские буквы в русском поле (і): «Я натрапив на стару нашу фотографію вчора.».

### make out

- **перевод (промпт):** различать, разобрать
- **эталон-пример:** I couldn't make out the road sign in the fog.
- **дистракторы:**
    - `I couldn't make out the road sign on the fog.` — preposition: **on** → `in`

### take care of

- **перевод (промпт):** заботиться о
- **эталон-пример:** She takes care of her younger brother after school.
- **дистракторы:**
    - `She takes care to her younger brother after school.` — preposition: **to** → `of`
    - `She take care of her younger brother after school.` — tense: **take** → `takes`

### bring up

- **перевод (промпт):** воспитывать, поднимать вопрос
- **эталон-пример:** She always brings up politics at dinner.
- **принимаемые варианты:**
    - `raise` — _Синонимы, которые здесь взаимозаменяемы._
    - `bring up an issue` — _Также может означать поднимать вопрос._
- **дистракторы:**
    - `She always bring up politics at dinner.` — tense: **bring** → `brings`

## В банке

### I'd like to open an account.

- **перевод (промпт):** Я бы хотел открыть счёт.
- **эталон-пример:** I'd like to open an account with your bank.
- **дистракторы:**
    - `I'd like to open account with your bank.` — article: **account** → `an account`

### bank account

- **перевод (промпт):** банковский счёт
- **эталон-пример:** I need a bank account to receive my salary.
- **дистракторы:**
    - `I need bank account to receive my salary.` — article: **bank account** → `a bank account`
    - `I need a bank accounts to receive my salary.` — article: **a bank accounts** → `a bank account`
    - `I needs a bank account to receive my salary.` — tense: **needs** → `need`
    - `I need a bank account for receive my salary.` — preposition: **for receive** → `to receive`

### debit card

- **перевод (промпт):** дебетовая карта
- **эталон-пример:** I prefer using my debit card for everyday purchases.
- **дистракторы:**
    - `I prefer using my debit cards for everyday purchases.` — article: **my debit cards** → `my debit card`

### credit card

- **перевод (промпт):** кредитная карта
- **эталон-пример:** She got a new credit card with a higher limit.
- **дистракторы:**
    - `She got new credit card with a higher limit.` — article: **new credit card** → `a new credit card`
    - `She got a new credit card at a higher limit.` — preposition: **at a higher limit** → `with a higher limit`

### Could you help me with this form?

- **перевод (промпт):** Не могли бы вы помочь мне с этой формой?
- **эталон-пример:** Could you help me with this form? I'm not sure how to fill it out.
- **дистракторы:**
    - `Could you help me with this forms? I'm not sure how to fill it out.` — tense: **this forms** → `this form`
    - `Could you help me with this form? I not sure how to fill it out.` — tense: **I not sure** → `I'm not sure`
    - `Could you help me with form? I'm not sure how to fill it out.` — article: **with form** → `with this form`
    - `Could you help me with this form? I'm not sure to fill it out.` — modal_to: **sure** → `sure how`
    - `Could you help me with this form? Not sure how to fill it out.` — word_order: **Not sure** → `I'm not sure`

### fill out

- **перевод (промпт):** заполнять (форму, анкету)
- **эталон-пример:** Please fill out this application to proceed.
- **принимаемые варианты:**
    - `fill in` — _британский вариант_
    - `complete` — _Это синоним в данном контексте._
- **дистракторы:**
    - `Please fill out this application for proceed.` — preposition: **for proceed** → `to proceed`
    - `Please fill out a application to proceed.` — article: **a application** → `this application`

### ATM

- **перевод (промпт):** банкомат
- **эталон-пример:** I withdrew cash from the ATM last night.
- **принимаемые варианты:**
    - `automated teller machine` — _другое название для банкомата._
    - `cash dispenser` — _Другой способ обозначить банкомат._
    - `cash machine` — _Эквивалентный термин для банкомата._
- **дистракторы:**
    - `I withdrawn cash from the ATM last night.` — tense: **withdrawn** → `withdrew`
    - `I withdrew cash from ATM last night.` — article: **ATM** → `the ATM`

### transfer money

- **перевод (промпт):** перевести деньги
- **эталон-пример:** I need to transfer money to my sister's account today.
- **дистракторы:**
    - `I need to transfer money in my sister's account today.` — preposition: **in my sister's account** → `to my sister's account`
    - `I need to transferred money to my sister's account today.` — tense: **transferred** → `transfer`

### standing order

- **перевод (промпт):** постоянное поручение (в банке)
- **эталон-пример:** I set up a standing order to pay my rent each month.
- **дистракторы:**
    - `I set up a standing order for pay my rent each month.` — preposition: **for pay** → `to pay`
    - `I set up a the standing order to pay my rent each month.` — article: **the standing order** → `standing order`
    - `I set up a standing order to pays my rent each month.` — tense: **to pays** → `to pay`

### set up an account

- **перевод (промпт):** подключить, оформить счёт (услугу)
- **эталон-пример:** You can set up an account online in just a few minutes.
- **принимаемые варианты:**
    - `create an account` — _синоним оформления счёта._
    - `register an account` — _синоним оформления счёта._
- **дистракторы:**
    - `You can set up account online in just a few minutes.` — article: **set up account** → `set up an account`
    - `You can set up an account in just a few minutes online.` — word_order: **in just a few minutes online** → `online in just a few minutes`
    - `You can set ups an account online in just a few minutes.` — tense: **set ups** → `set up`

### currency exchange

- **перевод (промпт):** обмен валюты
- **эталон-пример:** Where is the nearest currency exchange counter?
- **дистракторы:**
    - `Where is nearest currency exchange counter?` — word_order: **nearest currency exchange counter** → `the nearest currency exchange counter`
    - `Where is the nearest the currency exchange counter?` — article: **the currency exchange** → `currency exchange`

### direct debit

- **перевод (промпт):** автосписание (прямое дебетование)
- **эталон-пример:** I pay my utility bills by direct debit.
- **принимаемые варианты:**
    - `automatic payment` — _Синоним автосписания._
- **дистракторы:**
    - `I pay my utility bills for direct debit.` — preposition: **for direct debit** → `by direct debit`
    - `I pay my utility bills by the direct debit.` — article: **the direct debit** → `direct debit`
    - `I pays my utility bills by direct debit.` — tense: **pays** → `pay`

### withdrawal limit

- **перевод (промпт):** лимит снятия
- **эталон-пример:** My ATM has a daily withdrawal limit of $500.
- **дистракторы:**
    - `My ATM has a daily withdrawal limits of $500.` — word_order: **withdrawal limits** → `withdrawal limit`
    - `My ATM has daily withdrawal limit of $500.` — article: **daily withdrawal limit** → `a daily withdrawal limit`
    - `My ATM have a daily withdrawal limit of $500.` — tense: **have** → `has`

### savings account

- **перевод (промпт):** сберегательный счёт
- **эталон-пример:** I opened a savings account for future expenses.
- **дистракторы:**
    - `I opened savings account for future expenses.` — article: **savings account** → `a savings account`
    - `I opened a savings account for future expense.` — tense: **future expense** → `future expenses`

### online banking

- **перевод (промпт):** онлайн-банкинг
- **эталон-пример:** Do you offer online banking services?
- **дистракторы:**
    - `Do you offer online the banking services?` — article: **the banking** → `banking`
    - `Do you offers online banking services?` — tense: **offers** → `offer`
    - `Do you offer online banking at services?` — preposition: **at services** → `services`

### Could you explain the fees?

- **перевод (промпт):** Не могли бы вы объяснить комиссии?
- **эталон-пример:** Could you explain the fees for maintaining this account?
- **принимаемые варианты:**
    - `Can you explain the fees?` — _Это то же самое выражение._
    - `Would you explain the fees?` — _Это синонимичное выражение._
- **дистракторы:**
    - `Could you explained the fees for maintaining this account?` — tense: **explained** → `explain`
    - `Could you explain the fees to maintaining this account?` — preposition: **to maintaining** → `for maintaining`
    - `Could you explain the fees for maintain this account?` — tense: **maintain** → `maintaining`

### interest rate

- **перевод (промпт):** процентная ставка
- **эталон-пример:** What's the current interest rate for savings accounts?
- **дистракторы:**
    - `What are the current interest rate for savings accounts?` — tense: **What are** → `What's`
    - `What's the current interest rates for savings accounts?` — tense: **interest rates** → `interest rate`

### minimize fees

- **перевод (промпт):** уменьшить комиссии
- **эталон-пример:** I want to minimize fees on my account.
- **принимаемые варианты:**
    - `reduce fees` — _то же значение_
- **дистракторы:**
    - `I want to minimize fees in my account.` — preposition: **in my account** → `on my account`

### financial advisor

- **перевод (промпт):** финансовый консультант
- **эталон-пример:** I scheduled a meeting with my financial advisor to discuss investments.
- **принимаемые варианты:**
    - `financial consultant` — _то же значение_
- **дистракторы:**
    - `I scheduled a meeting with my financial advisor for discuss investments.` — preposition: **for discuss** → `to discuss`
    - `I scheduled a meeting in my financial advisor to discuss investments.` — preposition: **in** → `with`
    - `I schedule a meeting with my financial advisor to discuss investments.` — tense: **schedule** → `scheduled`

### monthly statement

- **перевод (промпт):** ежемесячная выписка
- **эталон-пример:** I review my monthly statement for any discrepancies.
- **принимаемые варианты:**
    - `monthly report` — _Синоним, используемый для описания того же понятия._
- **дистракторы:**
    - `I review my monthly statement of any discrepancies.` — preposition: **of any discrepancies** → `for any discrepancies`
    - `I reviews my monthly statement for any discrepancies.` — tense: **I reviews** → `I review`
    - `I review my the monthly statement for any discrepancies.` — article: **the monthly statement** → `monthly statement`
    - `I review my monthly statement at any discrepancies.` — preposition: **at any discrepancies** → `for any discrepancies`

## Аренда жилья

### view an apartment

- **перевод (промпт):** осмотреть квартиру
- **эталон-пример:** I'm going to view an apartment tomorrow.
- **дистракторы:**
    - `I'm going to view apartment tomorrow.` — article: **view apartment** → `view an apartment`
    - `I'm going to view for an apartment tomorrow.` — preposition: **view for an apartment** → `view an apartment`
    - `I going to view an apartment tomorrow.` — tense: **I going** → `I am going`
    - `I'm going to view an apartment in tomorrow.` — preposition: **in tomorrow** → `tomorrow`

### lease agreement

- **перевод (промпт):** договор аренды
- **эталон-пример:** Please read the lease agreement carefully before signing.
- **принимаемые варианты:**
    - `rental agreement` — _то же значение_
- **дистракторы:**
    - `Please read lease agreement carefully before signing.` — article: **lease agreement** → `the lease agreement`
    - `Please read in the lease agreement carefully before signing.` — preposition: **in the lease agreement** → `the lease agreement`

### security deposit

- **перевод (промпт):** залог
- **эталон-пример:** You'll need to pay a security deposit before moving in.
- **принимаемые варианты:**
    - `deposit` — _синоним к слову "залог"._
- **дистракторы:**
    - `You'll need to pay security deposit before moving in.` — article: **security deposit** → `a security deposit`
    - `You'll need to pay a security deposit for moving in.` — preposition: **for** → `before`

### utilities included

- **перевод (промпт):** коммунальные платежи включены
- **эталон-пример:** Are utilities included in the rent?
- **принимаемые варианты:**
    - `utilities are included` — _взаимозаменяемый вариант_
- **дистракторы:**
    - `Are utilities include in the rent?` — tense: **include** → `included`
    - `Are utilities includes in the rent?` — tense: **includes** → `included`

### per month

- **перевод (промпт):** в месяц
- **эталон-пример:** The rent is $1000 per month.
- **дистракторы:**
    - `The rent is $1000 for month.` — preposition: **for month** → `per month`
    - `The rent are $1000 per month.` — tense: **are** → `is`

### first and last month's rent

- **перевод (промпт):** арендная плата за первый и последний месяц
- **эталон-пример:** You'll have to pay the first and last month's rent when you sign the lease.
- **дистракторы:**
    - `You'll have to pay the first and last month rent when you sign the lease.` — article: **the first and last month rent** → `the first and last month's rent`
    - `You'll have to pay the first and last month's rent when you signing the lease.` — tense: **when you signing** → `when you sign`
    - `You'll have to pay the first and last month's rent at you sign the lease.` — preposition: **at you sign** → `when you sign`

### move-in date

- **перевод (промпт):** дата заселения
- **эталон-пример:** What's your preferred move-in date?
- **дистракторы:**
    - `What's your preferred date move-in?` — word_order: **date move-in** → `move-in date`
    - `What's your preferred move-in dates?` — tense: **move-in dates** → `move-in date`

### tenant

- **перевод (промпт):** арендатор, жилец
- **эталон-пример:** The tenant is responsible for minor repairs.
- **принимаемые варианты:**
    - `lessee` — _синоним слова 'арендатор'._
    - `renter` — _то же значение_
- **дистракторы:**
    - `The tenant are responsible for minor repairs.` — tense: **are** → `is`
    - `The tenant is responsible on minor repairs.` — preposition: **on** → `for`

### landlord

- **перевод (промпт):** арендодатель
- **эталон-пример:** The landlord will show you around the apartment.
- **дистракторы:**
    - `The landlord show you around the apartment.` — tense: **show** → `will show`
    - `The landlord will show around you the apartment.` — word_order: **around you** → `you around`

### sign the lease

- **перевод (промпт):** подписать договор аренды
- **эталон-пример:** We signed the lease yesterday.
- **дистракторы:**
    - `We sign the lease yesterday.` — tense: **sign** → `signed`
    - `We signed lease yesterday.` — article: **lease** → `the lease`
    - `We signed the yesterday lease.` — word_order: **the yesterday lease** → `the lease yesterday`

### rental application

- **перевод (промпт):** заявка на аренду
- **эталон-пример:** Fill out the rental application before the viewing.
- **дистракторы:**
    - `Fill out rental application before the viewing.` — article: **rental application** → `the rental application`
    - `Fill out a rental application before the viewing.` — article: **a rental application** → `the rental application`
    - `Fill out the rental application at the viewing.` — preposition: **at the viewing** → `before the viewing`

### monthly rent

- **перевод (промпт):** ежемесячная арендная плата
- **эталон-пример:** The monthly rent must be paid on the first of each month.
- **принимаемые варианты:**
    - `monthly rental fee` — _похожее значение, но с другим словом._
- **дистракторы:**
    - `The monthly rent must be paid at the first of each month.` — preposition: **at the first** → `on the first`
    - `The monthly rent must be paid on first of each month.` — article: **on first** → `on the first`
    - `The monthly rent must paid on the first of each month.` — tense: **must paid** → `must be paid`

### maintenance request

- **перевод (промпт):** заявка на ремонт
- **эталон-пример:** You can submit a maintenance request online.
- **принимаемые варианты:**
    - `repair request` — _Синоним: запрос на ремонт._
    - `service request` — _Синоним: заявка на обслуживание._
- **дистракторы:**
    - `You can submit a maintenance request in online.` — preposition: **in online** → `online`
    - `You can submit maintenance request online.` — article: **maintenance request** → `a maintenance request`

### walk-through

- **перевод (промпт):** осмотр (перед сдачей жилья)
- **эталон-пример:** We did a walk-through of the apartment before moving in.
- **принимаемые варианты:**
    - `inspection` — _Синоним, подходящий в контексте._
    - `walkthrough` — _Неформальный вариант термина._
    - `final inspection` — _Другой вариант осмотра перед арендой._
    - `property inspection` — _Синонимичный термин для осмотра недвижимости._
- **дистракторы:**
    - `We did walk-through of the apartment before moving in.` — article: **walk-through** → `a walk-through`
    - `We did a walk-through in the apartment before moving in.` — preposition: **in the apartment** → `of the apartment`
    - `We done a walk-through of the apartment before moving in.` — tense: **done** → `did`

### furnished

- **перевод (промпт):** с мебелью
- **эталон-пример:** Is the apartment furnished or unfurnished?
- **принимаемые варианты:**
    - `with furniture` — _означает то же самое_
- **дистракторы:**
    - `Is the apartment furnished and unfurnished?` — tense: **and** → `or`
    - `Is the apartment in furnished or unfurnished?` — preposition: **apartment in** → `apartment`
    - `Is apartment furnished or unfurnished?` — article: **apartment** → `the apartment`
    - `Is an apartment furnished or unfurnished?` — article: **an** → `the`

### move out

- **перевод (промпт):** съезжать
- **эталон-пример:** When do you plan to move out?
- **принимаемые варианты:**
    - `leave` — _синоним, который также означает покинуть место._
    - `vacate` — _синонимы, обозначающие переезд из жилья._
- **дистракторы:**
    - `When do you plan for to move out?` — modal_to: **for to** → `to`
    - `When you plan to move out?` — word_order: **you plan** → `do you plan`
    - `When do you planning to move out?` — tense: **planning** → `plan`

### notice period

- **перевод (промпт):** период уведомления
- **эталон-пример:** You have to give a one-month notice period before moving out.
- **дистракторы:**
    - `You have to give a one-month notices period before moving out.` — false_friend: **notices** → `notice`
    - `You have to give one-month notice period before moving out.` — article: **one-month** → `a one-month`
    - `You have to give a notice period one-month before moving out.` — word_order: **notice period one-month** → `one-month notice period`

### shared accommodation

- **перевод (промпт):** совместное жильё
- **эталон-пример:** I'm looking for shared accommodation in the city center.
- **принимаемые варианты:**
    - `shared housing` — _то же значение_
    - `communal accommodation` — _Эквивалентное выражение._
    - `co-housing` — _Также употребляется для обозначения совместного жилья._
    - `common housing` — _Синонимы._
    - `shared living arrangement` — _Синонимы._
- **дистракторы:**
    - `I'm looking for shared accommodation at the city center.` — preposition: **at the city center** → `in the city center`
    - `I'm looking for shared accommodation in city center.` — article: **in city center** → `in the city center`

### pay the rent

- **перевод (промпт):** платить аренду
- **эталон-пример:** I pay the rent by bank transfer every month.
- **принимаемые варианты:**
    - `pay rent` — _можно использовать в этом контексте_
    - `make a rent payment` — _эквивалентная формулировка_
    - `make the rent payment` — _Эквивалентное выражение для оплаты аренды._
- **дистракторы:**
    - `I pay the rent in bank transfer every month.` — preposition: **in bank transfer** → `by bank transfer`
    - `I pay rent the by bank transfer every month.` — word_order: **rent the by** → `the rent by`
    - `I pay the rent by bank transfers every month.` — tense: **by bank transfers** → `by bank transfer`
    - `I pay the rent bank transfer every month.` — word_order: **rent** → `rent by`
    - `I pays the rent by bank transfer every month.` — tense: **pays** → `pay`

### sublet

- **перевод (промпт):** сдавать в субаренду
- **эталон-пример:** Do you have permission to sublet the apartment?
- **принимаемые варианты:**
    - `sublease` — _синоним слова "субаренда"._
    - `lease out` — _Синонимично с терминами 'sublet' и 'sublease'._
- **дистракторы:**
    - `Do you have a permission to sublet the apartment?` — article: **a permission** → `permission`
    - `Do you have permission for sublet the apartment?` — preposition: **for sublet** → `to sublet`
    - `Do you has permission to sublet the apartment?` — tense: **has** → `have`

## У врача и в аптеке

### I'd like to make an appointment.

- **перевод (промпт):** Я хотел бы записаться на приём.
- **эталон-пример:** I'd like to make an appointment with Dr. Jones.
- **дистракторы:**
    - `I'd like to make an appointment at Dr. Jones.` — preposition: **at Dr. Jones** → `with Dr. Jones`
    - `I'd like to make appointment with Dr. Jones.` — article: **appointment** → `an appointment`
    - `I like to make an appointment with Dr. Jones.` — tense: **I like** → `I'd like`

### What are your symptoms?

- **перевод (промпт):** Какие у вас симптомы?
- **эталон-пример:** What are your symptoms today?
- **дистракторы:**
    - `What today are your symptoms?` — word_order: **What today are your symptoms** → `What are your symptoms today`
    - `What are you symptoms today?` — article: **you** → `your`
    - `What is your symptoms today?` — tense: **is** → `are`
    - `What are symptoms your today?` — word_order: **symptoms your** → `your symptoms`

### I have a fever.

- **перевод (промпт):** У меня жар.
- **эталон-пример:** I have a fever and feel very weak.
- **дистракторы:**
    - `I have fever and feel very weak.` — article: **fever** → `a fever`
    - `I have a fever and feels very weak.` — tense: **feels** → `feel`

### cold

- **перевод (промпт):** простуда
- **эталон-пример:** I think I have a cold.
- **дистракторы:**
    - `I think I have cold.` — article: **cold** → `a cold`
    - `I think I have the cold.` — article: **the** → `a`
    - `I think I has a cold.` — tense: **has** → `have`

### prescription

- **перевод (промпт):** рецепт (на лекарство)
- **эталон-пример:** The doctor gave me a prescription for antibiotics.
- **дистракторы:**
    - `The doctor gave me a prescription on antibiotics.` — preposition: **on antibiotics** → `for antibiotics`
    - `The doctor gave me prescription for antibiotics.` — article: **prescription** → `a prescription`
    - `The doctor gave to me a prescription for antibiotics.` — preposition: **gave to me** → `gave me`

### pharmacy

- **перевод (промпт):** аптека
- **эталон-пример:** You can buy this medicine at any pharmacy.
- **дистракторы:**
    - `You can buy this medicine in any pharmacy.` — preposition: **in any pharmacy** → `at any pharmacy`
    - `You can buy this medicine at the any pharmacy.` — article: **the any** → `any`
    - `You can buy this medicine on any pharmacy.` — preposition: **on** → `at`
    - `You can buys this medicine at any pharmacy.` — tense: **buys** → `buy`

### I have a headache.

- **перевод (промпт):** У меня болит голова.
- **эталон-пример:** I've had a headache since this morning.
- **дистракторы:**
    - `I had a headache since this morning.` — tense: **I** → `I've`
    - `I has had a headache since this morning.` — tense: **I has** → `I have`
    - `I have a headache since this morning.` — preposition: **I have** → `I've had`

### How long have you been feeling this way?

- **перевод (промпт):** Как давно вы так себя чувствуете?
- **эталон-пример:** How long have you been feeling this way before coming here?
- **дистракторы:**
    - `How long have you been feeling this way before you come here?` — tense: **you come** → `coming`

### cough

- **перевод (промпт):** кашель
- **эталон-пример:** She has a bad cough.
- **принимаемые варианты:**
    - `coughing` — _Форма 'кашлять', подходящая в контексте._
- **дистракторы:**
    - `She has bad cough.` — article: **bad cough** → `a bad cough`
    - `She has a cough bad.` — word_order: **cough bad** → `bad cough`
    - `She has a bad coughs.` — tense: **coughs** → `cough`

### I need a refill.

- **перевод (промпт):** Мне нужно продлить рецепт.
- **эталон-пример:** I need a refill on my prescription, please.
- **дистракторы:**
    - `I need a refill in my prescription, please.` — preposition: **in** → `on`
    - `I need refill on my prescription, please.` — article: **refill** → `a refill`
    - `I needs a refill on my prescription, please.` — tense: **I needs** → `I need`

### How can I help you?

- **перевод (промпт):** Чем я могу вам помочь?
- **эталон-пример:** Welcome to the pharmacy. How can I help you?
- **принимаемые варианты:**
    - `How can I assist you?` — _то же значение, чуть формальнее_
- **дистракторы:**
    - `Welcome to the pharmacy. How can I help you to?` — modal_to: **help you to** → `help you`
    - `Welcome to the pharmacy. How can I helping you?` — tense: **helping you** → `help you`
    - `Welcome to the pharmacy. How can I helps you?` — tense: **helps** → `help`

### medicine

- **перевод (промпт):** лекарство
- **эталон-пример:** Did the doctor give you any medicine?
- **дистракторы:**
    - `Did doctor give you any medicine?` — word_order: **Did doctor** → `Did the doctor`
    - `Did the doctor gave you any medicine?` — tense: **gave** → `give`

### run a temperature

- **перевод (промпт):** температурить, иметь температуру
- **эталон-пример:** He's been running a temperature since last night.
- **принимаемые варианты:**
    - `running a fever` — _также используется для обозначения высокой температуры._
    - `have a temperature` — _синоним с тем же значением._
    - `to have a fever` — _Синонимы._
    - `to have a high temperature` — _Синонимы._
- **дистракторы:**
    - `He's been running temperature since last night.` — article: **running temperature** → `running a temperature`

### My throat hurts.

- **перевод (промпт):** У меня болит горло.
- **эталон-пример:** My throat hurts every time I swallow.
- **принимаемые варианты:**
    - `I have a sore throat.` — _тот же смысл_
- **дистракторы:**
    - `My throat hurt every time I swallow.` — tense: **hurt** → `hurts`
    - `My throat hurts every time I swallowing.` — modal_to: **I swallowing** → `I swallow`
    - `Throat hurts every time I swallow.` — article: **Throat** → `My throat`

### pharmacist

- **перевод (промпт):** фармацевт
- **эталон-пример:** The pharmacist can help you find the right medicine.
- **принимаемые варианты:**
    - `chemist` — _Используется в некоторых странах вместо фармацевт._
    - `druggist` — _Общее слово для фармацевта._
- **дистракторы:**
    - `The pharmacist can helps you find the right medicine.` — tense: **can helps** → `can help`
    - `The pharmacist can help you to find the right medicine.` — modal_to: **to find** → `find`

### Please take a seat.

- **перевод (промпт):** Пожалуйста, присаживайтесь.
- **эталон-пример:** Please take a seat, the doctor will see you shortly.
- **дистракторы:**
    - `Please take a seat, doctor will see you shortly.` — article: **doctor** → `the doctor`
    - `Please take a seat, the doctor will sees you shortly.` — tense: **sees** → `see`
    - `Please take a seat, the doctor will see on you shortly.` — preposition: **see on you** → `see you`
    - `Please take seat, the doctor will see you shortly.` — article: **seat** → `a seat`

### fill a prescription

- **перевод (промпт):** получить лекарство по рецепту (в аптеке)
- **эталон-пример:** I need to fill a prescription for my medication.
- **дистракторы:**
    - `I need fill a prescription for my medication.` — article: **fill** → `to fill`
    - `I need to fill prescription for my medication.` — article: **fill** → `fill a`

### take medicine

- **перевод (промпт):** принимать лекарства
- **эталон-пример:** Please remember to take your medicine twice a day.
- **принимаемые варианты:**
    - `take your medication` — _то же самое по смыслу_
    - `take your pills` — _Эквивалентное выражение для приема лекарств._
- **дистракторы:**
    - `Please remembered to take your medicine twice a day.` — tense: **remembered** → `remember`
    - `Please remember to take medicine your twice a day.` — word_order: **medicine your** → `your medicine`

### I have an appointment at 3 PM.

- **перевод (промпт):** У меня запись на 3 часа дня.
- **эталон-пример:** I have an appointment at 3 PM with the dentist.
- **дистракторы:**
    - `I have an appointment in 3 PM with the dentist.` — preposition: **in 3 PM** → `at 3 PM`
    - `I have appointment at 3 PM with the dentist.` — article: **appointment** → `an appointment`
    - `I have an appointment on 3 PM with the dentist.` — preposition: **on 3 PM** → `at 3 PM`

### allergic reaction

- **перевод (промпт):** аллергическая реакция
- **эталон-пример:** I had an allergic reaction to the medication.
- **дистракторы:**
    - `I had an allergic reaction of the medication.` — preposition: **of the medication** → `to the medication`
    - `I had allergic reaction to the medication.` — article: **allergic reaction** → `an allergic reaction`

## Собеседование в IT

### Could you tell me more about the team?

- **перевод (промпт):** Могли бы вы рассказать мне больше о команде?
- **эталон-пример:** Could you tell me more about the team I would be working with?
- **дистракторы:**
    - `Could you tell me more about team I would be working with?` — article: **team** → `the team`
    - `Could you tell me more about the team I would working with?` — tense: **would working** → `would be working`

### What programming languages are you proficient in?

- **перевод (промпт):** Какими языками программирования вы владеете?
- **эталон-пример:** What programming languages are you proficient in?
- **дистракторы:**
    - `What programming languages is you proficient in?` — tense: **is you** → `are you`
    - `What programming languages are you proficients in?` — tense: **proficients** → `proficient`

### work experience

- **перевод (промпт):** опыт работы
- **эталон-пример:** My work experience includes five years as a software developer.
- **дистракторы:**
    - `My work experience include five years as a software developer.` — tense: **include** → `includes`
    - `My work experience includes five years as software developer.` — article: **as software developer** → `as a software developer`

### What are the main responsibilities of this role?

- **перевод (промпт):** Каковы основные обязанности в этой должности?
- **эталон-пример:** What are the main responsibilities of this role?
- **дистракторы:**
    - `What are the responsibilities main of this role?` — word_order: **responsibilities main** → `main responsibilities`
    - `What is the main responsibilities of this role?` — article: **What is** → `What are`
    - `What are the main responsibility of this role?` — article: **main responsibility** → `main responsibilities`

### I'm confident in my problem-solving skills.

- **перевод (промпт):** Я уверен в своих навыках решения проблем.
- **эталон-пример:** I'm confident in my problem-solving skills and enjoy tackling complex issues.
- **принимаемые варианты:**
    - `I am confident in my problem-solving abilities.` — _Синонимы._
- **дистракторы:**
    - `I'm confident in my problem-solving skills and enjoy tackling complex issue.` — article: **complex issue** → `complex issues`
    - `I'm confident in my problem-solving skills and enjoy to tackle complex issues.` — modal_to: **to tackle** → `tackling`
    - `I'm confident in my problem-solving skills and enjoy tackle complex issues.` — tense: **tackle** → `tackling`

### offer letter

- **перевод (промпт):** письмо с предложением о работе
- **эталон-пример:** I was thrilled to receive the offer letter for the position.
- **принимаемые варианты:**
    - `job offer letter` — _то же значение_
    - `employment offer letter` — _Синоним "письмо с предложением о работе"._
- **дистракторы:**
    - `I was thrilled to receive offer letter for the position.` — article: **offer letter** → `the offer letter`
    - `I was thrilled to receive a offer letter for the position.` — article: **a offer letter** → `the offer letter`
    - `I was thrilled receiving the offer letter for the position.` — tense: **thrilled receiving** → `thrilled to receive`

### collaborative environment

- **перевод (промпт):** среда, способствующая сотрудничеству
- **эталон-пример:** I thrive in a collaborative environment where teamwork is valued.
- **дистракторы:**
    - `I thrive in a collaborative environments where teamwork is valued.` — article: **a collaborative environments** → `a collaborative environment`
    - `I thrive in a collaborative environment where is valued teamwork.` — word_order: **where is valued teamwork** → `where teamwork is valued`
    - `I thrive in collaborative environment where teamwork is valued.` — article: **collaborative environment** → `a collaborative environment`

### I have experience with agile methodologies.

- **перевод (промпт):** У меня есть опыт работы с гибкими методологиями.
- **эталон-пример:** I have experience with agile methodologies, which helps in adapting to changes quickly.
- **принимаемые варианты:**
    - `I have knowledge of agile methodologies.` — _Синонимы._
    - `I have expertise in agile methodologies.` — _Синонимы._
- **дистракторы:**
    - `I has experience with agile methodologies, which helps in adapting to changes quickly.` — tense: **I has** → `I have`
    - `I have experience with agile methodologies, what helps in adapting to changes quickly.` — word_order: **what** → `which`

### job offer

- **перевод (промпт):** предложение о работе
- **эталон-пример:** The company made me a job offer after the final interview.
- **дистракторы:**
    - `The company made me job offer after the final interview.` — article: **job offer** → `a job offer`
    - `The company made me a job offer to the final interview.` — preposition: **to the final interview** → `after the final interview`
    - `The company made me a job offer in the final interview.` — preposition: **in the final interview** → `after the final interview`

### Can you tell me about the career growth opportunities?

- **перевод (промпт):** Можете рассказать о возможностях карьерного роста?
- **эталон-пример:** Can you tell me about the career growth opportunities at your company?
- **принимаемые варианты:**
    - `Could you tell me about the career growth opportunities?` — _Синоним с другим модальным глаголом._
    - `Can you tell me about the job growth opportunities?` — _Синонимично данному термину._
    - `Could you tell me about the career advancement opportunities?` — _Синонимично данному термину._
- **дистракторы:**
    - `Can you tell about the career growth opportunities at your company?` — preposition: **tell about** → `tell me about`

### What are your strengths and weaknesses?

- **перевод (промпт):** Каковы ваши сильные и слабые стороны?
- **эталон-пример:** During the interview, they asked me about my strengths and weaknesses.
- **дистракторы:**
    - `During the interview, they asked about my strengths and weaknesses me.` — word_order: **asked about my strengths and weaknesses me** → `asked me about my strengths and weaknesses`
    - `During the interview, they ask me about my strengths and weaknesses.` — tense: **ask** → `asked`
    - `During the interview, they asked me about my strength and weaknesses.` — article: **my strength** → `my strengths`
    - `During the interview, they asking me about my strengths and weaknesses.` — tense: **asking** → `asked`

### long-term goals

- **перевод (промпт):** долгосрочные цели
- **эталон-пример:** In the interview, I discussed my long-term goals in the IT industry.
- **дистракторы:**
    - `In the interview, I discussed my the long-term goals in the IT industry.` — article: **my the** → `my`
    - `In the interview, I discussed my long-term goal in the IT industry.` — tense: **long-term goal** → `long-term goals`

### multitasking ability

- **перевод (промпт):** способность к многозадачности
- **эталон-пример:** I mentioned my multitasking ability as one of my strengths.
- **принимаемые варианты:**
    - `ability to multitask` — _та же мысль другой конструкцией_
- **дистракторы:**
    - `I mentioned my multitasking ability as one of strengths.` — article: **one of strengths** → `one of my strengths`
    - `I mentioned about my multitasking ability as one of my strengths.` — preposition: **mentioned about** → `mentioned`

### get along with

- **перевод (промпт):** ладить с
- **эталон-пример:** I get along with my team well, making collaboration effective.
- **дистракторы:**
    - `I get along my team well, making collaboration effective.` — preposition: **along my team** → `along with my team`
    - `I get along with my team good, making collaboration effective.` — false_friend: **good** → `well`
    - `I gets along with my team well, making collaboration effective.` — tense: **gets** → `get`
    - `I get along with my team well, make collaboration effective.` — tense: **make** → `making`

### focus on results

- **перевод (промпт):** фокусироваться на результатах
- **эталон-пример:** I focus on results, ensuring that projects meet their objectives.
- **принимаемые варианты:**
    - `concentrate on results` — _эквивалентное выражение_
    - `concentrate on outcomes` — _Вариант пересказа, эквивалентный основному термину._
- **дистракторы:**
    - `I focus in results, ensuring that projects meet their objectives.` — preposition: **in** → `on`
    - `I focuses on results, ensuring that projects meet their objectives.` — tense: **focuses** → `focus`
    - `I the focus on results, ensuring that projects meet their objectives.` — word_order: **the focus on results** → `focus on results`

### comfortable with

- **перевод (промпт):** комфортно работаю с
- **эталон-пример:** I'm comfortable with fast-paced environments and enjoy challenges.
- **дистракторы:**
    - `I'm comfortable with fast-paced environment and enjoy challenges.` — article: **fast-paced environment** → `fast-paced environments`

### notice period

- **перевод (промпт):** период уведомления
- **эталон-пример:** You have to give a one-month notice period before moving out.
- **дистракторы:**
    - `You have to give a one-month notices period before moving out.` — false_friend: **notices** → `notice`
    - `You have to give one-month notice period before moving out.` — article: **one-month** → `a one-month`
    - `You have to give a notice period one-month before moving out.` — word_order: **notice period one-month** → `one-month notice period`

### hands-on experience

- **перевод (промпт):** практический опыт
- **эталон-пример:** I have hands-on experience with JavaScript frameworks in previous projects.
- **принимаемые варианты:**
    - `practical experience` — _то же значение_
- **дистракторы:**
    - `I have a hands-on experience with JavaScript frameworks in previous projects.` — article: **a hands-on experience** → `hands-on experience`
    - `I have hands-on experience with JavaScript frameworks in previous project.` — article: **previous project** → `previous projects`
    - `I have hands-on experience with JavaScript frameworks at previous projects.` — preposition: **at previous projects** → `in previous projects`

### When can you start?

- **перевод (промпт):** Когда вы можете начать?
- **эталон-пример:** They asked me when I could start if offered the position.
- **дистракторы:**
    - `They asked me when I could start if to offered the position.` — modal_to: **if to offered** → `if offered`
    - `They asked me when I start if offered the position.` — tense: **when I start** → `when I could start`
    - `They asked me when I could start if offer the position.` — tense: **offer** → `offered`

### What is the expected salary range?

- **перевод (промпт):** Какой ожидаемый диапазон зарплаты?
- **эталон-пример:** What is the expected salary range for this position?
- **дистракторы:**
    - `What is expected salary range for this position?` — article: **expected salary range** → `the expected salary range`
    - `What is the expected salary range at this position?` — preposition: **at this position** → `for this position`

## Ресторан и доставка еды

### reserve a table

- **перевод (промпт):** забронировать столик
- **эталон-пример:** I would like to reserve a table for two at 7 PM.
- **принимаемые варианты:**
    - `book a table` — _британский стандарт_
- **дистракторы:**
    - `I would like to reserve table for two at 7 PM.` — article: **reserve table** → `reserve a table`
    - `I would like to reserve a table for two on 7 PM.` — preposition: **on 7 PM** → `at 7 PM`
    - `I would like to reserve a table on two at 7 PM.` — preposition: **on two** → `for two`

### What would you like to order?

- **перевод (промпт):** Что бы вы хотели заказать?
- **эталон-пример:** What would you like to order?
- **дистракторы:**
    - `What would you like to the order?` — preposition: **to the order** → `to order`
    - `What would you like order?` — modal_to: **like order** → `like to order`
    - `What would you like to ordering?` — modal_to: **to ordering** → `to order`

### I'll have the chicken salad.

- **перевод (промпт):** Я буду куриный салат.
- **эталон-пример:** I'll have the chicken salad, please.
- **принимаемые варианты:**
    - `I’ll take the chicken salad.` — _Также означает то же самое._
    - `I would like the chicken salad.` — _Эквивалентная формулировка с тем же значением._
- **дистракторы:**
    - `I'll have chicken salad, please.` — article: **chicken salad** → `the chicken salad`
    - `I'll have the chicken salads, please.` — word_order: **the chicken salads** → `the chicken salad`

### menu

- **перевод (промпт):** меню
- **эталон-пример:** Could you bring me the menu, please?
- **дистракторы:**
    - `Could you bring me menu, please?` — article: **menu** → `the menu`

### How would you like to pay?

- **перевод (промпт):** Как вы хотели бы оплатить?
- **эталон-пример:** How would you like to pay, by cash or card?
- **принимаемые варианты:**
    - `How do you want to pay?` — _То же самое значение._
    - `How would you prefer to pay?` — _Синонимичное выражение._
- **дистракторы:**
    - `How would you like to pays, by cash or card?` — tense: **pays** → `pay`
    - `How would you like to pay, by the cash or card?` — article: **the cash** → `cash`
    - `How would you like to pay, by cash or the card?` — article: **the card** → `card`

### Could I see the wine list?

- **перевод (промпт):** Могу я посмотреть винную карту?
- **эталон-пример:** Could I see the wine list, please?
- **принимаемые варианты:**
    - `Can I see the wine list?` — _Синонимичное выражение._
    - `May I see the wine list?` — _Синонимичное выражение._
    - `Can I see the wine menu?` — _Синоним._
- **дистракторы:**
    - `Could I see wine list, please?` — article: **wine list** → `the wine list`
    - `Could I see the list of wine, please?` — word_order: **the list of wine** → `the wine list`
    - `Could I saw the wine list, please?` — tense: **saw** → `see`
    - `Could I seen the wine list, please?` — tense: **seen** → `see`

### I'd like to make a reservation.

- **перевод (промпт):** Я бы хотел сделать бронь.
- **эталон-пример:** I'd like to make a reservation for Saturday night.
- **дистракторы:**
    - `I'd like to make a reservation at Saturday night.` — preposition: **at Saturday night** → `for Saturday night`
    - `I'd like to make reservation for Saturday night.` — article: **reservation** → `a reservation`
    - `I'd like to make a reservation on Saturday night.` — preposition: **on** → `for`

### tip

- **перевод (промпт):** чаевые
- **эталон-пример:** It's customary to leave a tip in restaurants here.
- **принимаемые варианты:**
    - `gratuity` — _то же значение (симметрично паре gratuity/tip)_
- **дистракторы:**
    - `It's customary to leave tip in restaurants here.` — article: **tip** → `a tip`
    - `It's customary to leave a tips in restaurants here.` — article: **tips** → `tip`

### Can I get this to go?

- **перевод (промпт):** Можно это с собой?
- **эталон-пример:** I'd like the pasta to go, please.
- **принимаемые варианты:**
    - `Can I have this to go?` — _Синоним, имеющий то же значение._
    - `Can I take this to go?` — _Синоним, имеющий то же значение._
    - `Can I get this to take away?` — _британский вариант_
- **дистракторы:**
    - `I'd like the pasta for go, please.` — preposition: **for go** → `to go`

### delivery

- **перевод (промпт):** доставка
- **эталон-пример:** The delivery should arrive in 30 minutes.
- **дистракторы:**
    - `The delivery should arrives in 30 minutes.` — tense: **arrives** → `arrive`
    - `The delivery should arrive in 30 minute.` — tense: **30 minute** → `30 minutes`

### starter

- **перевод (промпт):** закуска
- **эталон-пример:** For a starter, I would recommend the soup.
- **принимаемые варианты:**
    - `appetizer` — _американский вариант_
    - `first course` — _Также означает первое блюдо._
- **дистракторы:**
    - `For a starter, I would recommend soup.` — article: **soup** → `the soup`

### main course

- **перевод (промпт):** основное блюдо
- **эталон-пример:** I'll have the steak as my main course.
- **принимаемые варианты:**
    - `main dish` — _то же значение_
- **дистракторы:**
    - `I'll have steak as my main course.` — article: **steak** → `the steak`
    - `I'll have the steak as my main courses.` — tense: **courses** → `course`

### Could we have the bill, please?

- **перевод (промпт):** Можно нам счёт, пожалуйста?
- **эталон-пример:** Could we have the bill, please?
- **принимаемые варианты:**
    - `Could we have the check, please?` — _американский вариант_
- **дистракторы:**
    - `Could we have bill, please?` — article: **bill** → `the bill`
    - `Could we has the bill, please?` — tense: **has** → `have`

### order in

- **перевод (промпт):** заказать на дом
- **эталон-пример:** Let's order in tonight instead of cooking.
- **принимаемые варианты:**
    - `order delivery` — _синоним выражения «заказать на дом»._
    - `order takeaway` — _синоним выражения «заказать на дом»._
- **дистракторы:**
    - `Let's order in tonight instead of the cooking.` — article: **the cooking** → `cooking`
    - `Let's order in tonight instead of cook.` — tense: **cook** → `cooking`
    - `Let's order in tonight instead of to cook.` — modal_to: **to cook** → `cooking`

### Are you ready to order?

- **перевод (промпт):** Вы готовы заказать?
- **эталон-пример:** Are you ready to order, or do you need more time?
- **дистракторы:**
    - `Are you ready for order, or do you need more time?` — preposition: **for order** → `to order`
    - `Are you ready to ordering, or do you need more time?` — tense: **to ordering** → `to order`
    - `Are you ready ordering, or do you need more time?` — tense: **ready ordering** → `ready to order`

### Would you like anything to drink?

- **перевод (промпт):** Вы бы хотели что-нибудь выпить?
- **эталон-пример:** Would you like anything to drink with your meal?
- **принимаемые варианты:**
    - `Do you want something to drink?` — _Это синоним._
    - `Would you like something to drink?` — _Это синоним._
    - `Would you like a drink?` — _Эквивалентная фраза._
    - `Do you want a drink?` — _Эквивалентная фраза._
- **дистракторы:**
    - `Would you like anything to drink with meal?` — article: **with meal** → `with your meal`
    - `Would you like anything drink with your meal?` — modal_to: **anything drink** → `anything to drink`
    - `Would you liked anything to drink with your meal?` — tense: **liked** → `like`
    - `Would you like anything to drink for your meal?` — preposition: **for your meal** → `with your meal`

### cutlery

- **перевод (промпт):** столовые приборы
- **эталон-пример:** The cutlery is on the table.
- **принимаемые варианты:**
    - `eating utensils` — _синоним на английском._
    - `silverware` — _синоним на английском._
- **дистракторы:**
    - `The cutlery is at the table.` — preposition: **at** → `on`
    - `The cutlery are on the table.` — tense: **are** → `is`

### Can I have some water, please?

- **перевод (промпт):** Можно мне воды, пожалуйста?
- **эталон-пример:** Can I have some water, please?
- **дистракторы:**
    - `Can I had some water, please?` — tense: **had** → `have`
    - `Can I have some water for, please?` — preposition: **water for** → `water`
    - `Can I have for some water, please?` — preposition: **have for** → `have`
    - `Can I has some water, please?` — tense: **has** → `have`

### gratuity

- **перевод (промпт):** чаевые (формально)
- **эталон-пример:** Gratuity is not included in the bill.
- **принимаемые варианты:**
    - `tip` — _Синоним, используемый в разговорной речи._
- **дистракторы:**
    - `Gratuity is not include in the bill.` — tense: **include** → `included`
    - `Gratuity is not included in bill.` — article: **in bill** → `in the bill`

### serve

- **перевод (промпт):** обслуживать
- **эталон-пример:** The waiter will serve your meal shortly.
- **принимаемые варианты:**
    - `to serve` — _означает 'подавать' или 'обслуживать'._
- **дистракторы:**
    - `The waiter will serves your meal shortly.` — tense: **serves** → `serve`
    - `The waiter will serve meal shortly.` — article: **serve** → `serve your`

## Собеседование в IT: продвинутый уровень

### Tell me about a time you faced a challenge at work.

- **перевод (промпт):** Расскажите о случае, когда вы столкнулись с трудностью на работе.
- **эталон-пример:** Tell me about a time you faced a challenge at work and how you overcame it.
- **дистракторы:**
    - `Tell me about a time you face a challenge at work and how you overcame it.` — tense: **face** → `faced`

### scalability

- **перевод (промпт):** масштабируемость
- **эталон-пример:** Scalability is crucial for the system to handle a growing number of requests.
- **дистракторы:**
    - `Scalability are crucial for the system to handle a growing number of requests.` — tense: **are** → `is`

### Can you walk me through your design process?

- **перевод (промпт):** Можете рассказать о вашем процессе проектирования?
- **эталон-пример:** Can you walk me through your design process for this task?
- **дистракторы:**
    - `Can you walk me through your design processes for this task?` — tense: **processes** → `process`
    - `Can you walk me through the design process for this task?` — article: **the design process** → `your design process`

### bottleneck

- **перевод (промпт):** узкое место
- **эталон-пример:** Identifying bottlenecks is key to optimizing system performance.
- **дистракторы:**
    - `Identifying bottlenecks are key to optimizing system performance.` — tense: **are** → `is`
    - `Identifying bottlenecks is the key to optimizing system performance.` — article: **the key** → `key`

### What are your salary expectations?

- **перевод (промпт):** Каковы ваши ожидания по зарплате?
- **эталон-пример:** What are your salary expectations for this role?
- **дистракторы:**
    - `What are your salary expectations for the role?` — article: **the role** → `this role`

### trade-off

- **перевод (промпт):** компромисс
- **эталон-пример:** There is always a trade-off between performance and cost.
- **дистракторы:**
    - `There is always a trade-off between the performance and cost.` — article: **the performance** → `performance`

### How do you handle stress and pressure?

- **перевод (промпт):** Как вы справляетесь со стрессом и давлением?
- **эталон-пример:** How do you handle stress and pressure during tight deadlines?
- **дистракторы:**
    - `How do you handle stress and pressure at tight deadlines?` — preposition: **at tight deadlines** → `during tight deadlines`

### streamline

- **перевод (промпт):** упростить, оптимизировать
- **эталон-пример:** We need to streamline the process to improve efficiency.
- **дистракторы:**
    - `We need to streamline the process for improve efficiency.` — preposition: **for improve** → `to improve`
    - `We need to streamline process to improve efficiency.` — article: **streamline process** → `streamline the process`

### Could you elaborate on that?

- **перевод (промпт):** Можете уточнить этот момент?
- **эталон-пример:** Could you elaborate on how you handled that situation?
- **дистракторы:**
    - `Could you elaborate on how you handle that situation?` — tense: **handle** → `handled`

### How do you prioritize your tasks?

- **перевод (промпт):** Как вы расставляете приоритеты в задачах?
- **эталон-пример:** How do you prioritize your tasks in a fast-paced environment?
- **дистракторы:**
    - `How do you prioritize your tasks at a fast-paced environment?` — preposition: **at a fast-paced environment** → `in a fast-paced environment`

### cloud architecture

- **перевод (промпт):** облачная архитектура
- **эталон-пример:** Optimizing cloud architecture can reduce costs significantly.
- **дистракторы:**
    - `Optimizing cloud architectures can reduce costs significantly.` — tense: **architectures** → `architecture`
    - `Optimizing the cloud architecture can reduce costs significantly.` — article: **the cloud architecture** → `cloud architecture`

### Can you give an example of a successful team project?

- **перевод (промпт):** Можете привести пример успешного командного проекта?
- **эталон-пример:** Can you give an example of a successful team project you led?
- **принимаемые варианты:**
    - `Could you provide an example of a successful team project?` — _Это эквивалентно._
    - `Can you provide an example of a successful team project?` — _Это эквивалентно._
- **дистракторы:**
    - `Can you give example of a successful team project you led?` — article: **example** → `an example`
    - `Can you give an example of successful team project you led?` — article: **successful team project** → `a successful team project`

### negotiate a salary

- **перевод (промпт):** обсуждать зарплату
- **эталон-пример:** During the final round, she was ready to negotiate a salary that matched her experience.
- **дистракторы:**
    - `During the final round, she was ready to negotiate salary that matched her experience.` — article: **negotiate salary** → `negotiate a salary`

### resilient

- **перевод (промпт):** устойчивый
- **эталон-пример:** A resilient system can recover quickly from failures.
- **дистракторы:**
    - `A resilient system can recover quick from failures.` — tense: **quick** → `quickly`

### Could you describe your leadership style?

- **перевод (промпт):** Можете описать ваш стиль лидерства?
- **эталон-пример:** Could you describe your leadership style in managing diverse teams?
- **дистракторы:**
    - `Could you describe the leadership style in managing diverse teams?` — article: **the leadership style** → `your leadership style`

### bump up

- **перевод (промпт):** увеличить, повысить
- **эталон-пример:** They decided to bump up the offer after the negotiations.
- **дистракторы:**
    - `They decided to bump in the offer after the negotiations.` — preposition: **bump in** → `bump up`

### fault tolerance

- **перевод (промпт):** отказоустойчивость
- **эталон-пример:** Building fault tolerance into the system is a top priority.
- **дистракторы:**
    - `Building the fault tolerance into the system is a top priority.` — article: **the fault tolerance** → `fault tolerance`
    - `Building fault tolerance in the system is a top priority.` — preposition: **in** → `into`

### What do you consider your greatest strength?

- **перевод (промпт):** Что вы считаете своей сильнейшей стороной?
- **эталон-пример:** What do you consider your greatest strength in professional setting?
- **дистракторы:**
    - `What do you consider your greatest strength at professional setting?` — preposition: **at** → `in`
    - `What do you considers your greatest strength in professional setting?` — tense: **considers** → `consider`

### data redundancy

- **перевод (промпт):** избыточность данных
- **эталон-пример:** Ensuring data redundancy is important to prevent data loss.
- **дистракторы:**
    - `Ensuring data redundancy are important to prevent data loss.` — tense: **are** → `is`
    - `Ensuring a data redundancy is important to prevent data loss.` — article: **a data redundancy** → `data redundancy`

### burning the candle at both ends

- **перевод (промпт):** работать на износ
- **эталон-пример:** During the project, we were burning the candle at both ends.
- **дистракторы:**
    - `During the project, we were burn the candle at both ends.` — tense: **burn** → `burning`

### How do you define success?

- **перевод (промпт):** Как вы определяете успех?
- **эталон-пример:** How do you define success in a collaborative project?
- **дистракторы:**
    - `How does you define success in a collaborative project?` — tense: **does you** → `do you`
    - `How do you define success on a collaborative project?` — preposition: **on** → `in`
    - `How do you define the success in a collaborative project?` — article: **the success** → `success`

### iterate

- **перевод (промпт):** итерировать, дорабатывать итерациями
- **эталон-пример:** We must iterate several times to refine the design.
- **дистракторы:**
    - `We must iterate several time to refine the design.` — tense: **time** → `times`

### a good fit for

- **перевод (промпт):** хорошо подходит для
- **эталон-пример:** With your skills, you would be a good fit for our team.
- **принимаемые варианты:**
    - `a perfect fit for` — _синоним, ближе к контексту_
    - `suitable for` — _синонимично_
- **дистракторы:**
    - `With your skills, you would be a good fit in our team.` — preposition: **in our team** → `for our team`
    - `With your skills, you would be good fit for our team.` — article: **good fit** → `a good fit`

### What motivates you?

- **перевод (промпт):** Что вас мотивирует?
- **эталон-пример:** What motivates you to pursue a career in IT?
- **дистракторы:**
    - `What motivates you for pursue a career in IT?` — preposition: **for** → `to`
    - `What motivates you pursuing a career in IT?` — modal_to: **pursuing** → `to pursue`

### load balancing

- **перевод (промпт):** распределение нагрузки
- **эталон-пример:** Effective load balancing is crucial for system scalability.
- **дистракторы:**
    - `Effective load balancing is crucial for the system scalability.` — article: **the system** → `system`
    - `Effective load balancing are crucial for system scalability.` — tense: **are** → `is`

## Деловые созвоны и переписка

### schedule a meeting

- **перевод (промпт):** назначить встречу
- **эталон-пример:** Let's schedule a meeting for next week to discuss the project details.
- **дистракторы:**
    - `Let's schedule meeting for next week to discuss the project details.` — article: **schedule meeting** → `schedule a meeting`

### set up a conference call

- **перевод (промпт):** организовать конференц-звонок
- **эталон-пример:** I'll set up a conference call with the team for Friday afternoon.
- **дистракторы:**
    - `I'll set up a conference call at the team for Friday afternoon.` — preposition: **at the team** → `with the team`
    - `I'll set up conference call with the team for Friday afternoon.` — article: **conference call** → `a conference call`
- **флаги:**
    - ✍️ **не слово → правка** — конференц-звонок; конференц-звонок

### agenda

- **перевод (промпт):** повестка дня
- **эталон-пример:** Please send the meeting agenda by tomorrow morning.
- **дистракторы:**
    - `Please send the meeting agenda to tomorrow morning.` — preposition: **to tomorrow morning** → `by tomorrow morning`

### take minutes

- **перевод (промпт):** вести протокол встречи
- **эталон-пример:** Could you take minutes during the meeting?
- **дистракторы:**
    - `Could you take the minutes during the meeting?` — article: **the minutes** → `minutes`

### conference call

- **перевод (промпт):** конференц-звонок
- **эталон-пример:** We have a conference call scheduled for 3 PM.
- **дистракторы:**
    - `We have a conference call scheduled in 3 PM.` — preposition: **in 3 PM** → `for 3 PM`
    - `We have a conference call scheduled for the 3 PM.` — article: **the 3 PM** → `3 PM`

### follow up

- **перевод (промпт):** следить за выполнением
- **эталон-пример:** I'll follow up with you after the meeting to ensure everything is clear.
- **дистракторы:**
    - `I'll follow up with you after meeting to ensure everything is clear.` — article: **after meeting** → `after the meeting`

### make a call

- **перевод (промпт):** совершить звонок
- **эталон-пример:** I need to make a call to our supplier before the end of the day.
- **дистракторы:**
    - `I need to make call to our supplier before the end of the day.` — article: **make call** → `make a call`

### send an email

- **перевод (промпт):** отправить электронное письмо
- **эталон-пример:** Please send an email with the revised document attached.
- **дистракторы:**
    - `Please send an email with revised document attached.` — article: **revised document** → `the revised document`
    - `Please send email with the revised document attached.` — article: **email** → `an email`

### out of office

- **перевод (промпт):** вне офиса
- **эталон-пример:** I'll be out of office next week, but reachable by email.
- **дистракторы:**
    - `I'll be out of the office next week, but reachable by email.` — article: **out of the office** → `out of office`

### mark as urgent

- **перевод (промпт):** отметить как срочное
- **эталон-пример:** Please mark the email as urgent for immediate attention.
- **дистракторы:**
    - `Please mark email as urgent for immediate attention.` — article: **email** → `the email`

### get back to someone

- **перевод (промпт):** перезвонить кому-то
- **эталон-пример:** I'll get back to you with the answers to your questions by tomorrow.
- **дистракторы:**
    - `I'll get back to your with the answers to your questions by tomorrow.` — article: **your** → `you`
    - `I'll get back at you with the answers to your questions by tomorrow.` — preposition: **at** → `to`

### in the loop

- **перевод (промпт):** быть в курсе дел
- **эталон-пример:** Make sure to keep me in the loop with any updates on the project.
- **дистракторы:**
    - `Make sure to keep me in the loop with any updates for the project.` — preposition: **for** → `on`

### as per our conversation

- **перевод (промпт):** как обсуждали ранее
- **эталон-пример:** As per our conversation, I've attached the report for your review.
- **дистракторы:**
    - `As per our conversation, I've attach the report for your review.` — tense: **attach** → `attached`
    - `As per the conversation, I've attached the report for your review.` — article: **the conversation** → `our conversation`

### on the same page

- **перевод (промпт):** быть на одной волне
- **эталон-пример:** It's important for the team to be on the same page about the project goals.
- **принимаемые варианты:**
    - `on the same side` — _Еквівалентна фраза._
    - `in agreement` — _Синонім у цьому контексті._
- **дистракторы:**
    - `It's important for the team to be in the same page about the project goals.` — preposition: **in the same page** → `on the same page`
    - `It's important for the teams to be on the same page about the project goals.` — tense: **the teams** → `the team`
- **флаги:**
    - 🇺🇦 **украинизм → переген** — Украинские буквы в русском поле (і є): «Важливо, щоб команда розуміла одне одного щодо цілей проєкту.».
    - 🇺🇦 **украинизм → переген** — Украинские буквы в русском поле (і): «на одній хвилі, розуміти одне одного».

### attached

- **перевод (промпт):** приложенный (о файле)
- **эталон-пример:** Please see the attached document for further details.
- **дистракторы:**
    - `Please see the attached documents for further details.` — article: **the attached documents** → `the attached document`

### meeting request

- **перевод (промпт):** запрос на встречу
- **эталон-пример:** I have sent you a meeting request for next Tuesday.
- **дистракторы:**
    - `I have sent you the meeting request for next Tuesday.` — article: **the** → `a`

### follow-up email

- **перевод (промпт):** последующее письмо
- **эталон-пример:** I will send a follow-up email to confirm the details discussed.
- **дистракторы:**
    - `I will send follow-up email to confirm the details discussed.` — article: **follow-up email** → `a follow-up email`
    - `I will send a follow-up email confirm the details discussed.` — tense: **email confirm** → `email to confirm`

### in touch

- **перевод (промпт):** быть на связи
- **эталон-пример:** We'll be in touch with more information next week.
- **дистракторы:**
    - `We'll be in touched with more information next week.` — tense: **touched** → `touch`
    - `We'll be in touch with more information next weeks.` — tense: **weeks** → `week`
    - `We'll be in touch about more information next week.` — preposition: **about** → `with`

### clarify

- **перевод (промпт):** уточнить
- **эталон-пример:** Could you clarify what you mean by 'additional resources'?
- **дистракторы:**
    - `Could you clarify what mean by 'additional resources'?` — tense: **mean** → `you mean`
    - `Could you clarify what you mean for 'additional resources'?` — preposition: **for** → `by`

### to whom it may concern

- **перевод (промпт):** всем, кого это касается
- **эталон-пример:** The letter was addressed 'To whom it may concern' at the top.
- **дистракторы:**
    - `The letter was addressed 'To whom it may concern' in the top.` — preposition: **in the top** → `at the top`

### reach out

- **перевод (промпт):** связаться
- **эталон-пример:** Please reach out if you have any questions or concerns.
- **дистракторы:**
    - `Please reach out if you has any questions or concerns.` — tense: **has** → `have`
    - `Please reach out for you have any questions or concerns.` — preposition: **for** → `if`

## Знакомство и small talk

### Nice to meet you

- **перевод (промпт):** Приятно познакомиться
- **эталон-пример:** Nice to meet you, I'm Sarah.
- **принимаемые варианты:**
    - `Pleased to meet you` — _Синонимичное выражение._
    - `Good to meet you` — _Синонимичное выражение._
- **дистракторы:**
    - `Nice meet you, I'm Sarah.` — tense: **meet** → `to meet`

### How is the weather?

- **перевод (промпт):** Как погода?
- **эталон-пример:** How is the weather in your city right now?
- **дистракторы:**
    - `How the weather is in your city right now?` — word_order: **How the weather is** → `How is the weather`
    - `How is the weather at your city right now?` — preposition: **at your city** → `in your city`

### Do you have any hobbies?

- **перевод (промпт):** У вас есть какие-нибудь хобби?
- **эталон-пример:** Do you have any hobbies you enjoy?
- **дистракторы:**
    - `Do you has any hobbies you enjoy?` — tense: **has** → `have`
    - `Do you have the hobbies you enjoy?` — article: **the hobbies** → `any hobbies`

### Lovely weather today

- **перевод (промпт):** Прекрасная погода сегодня
- **эталон-пример:** It's lovely weather today, isn't it?
- **дистракторы:**
    - `It's lovely weather today, isn't he?` — tense: **isn't he?** → `isn't it?`

### What do you like to do in your free time?

- **перевод (промпт):** Что вы любите делать в свободное время?
- **эталон-пример:** What do you like to do in your free time?
- **дистракторы:**
    - `What do you likes to do in your free time?` — tense: **likes** → `like`
    - `What do you like doing in your free time?` — modal_to: **doing** → `to do`

### It's a bit chilly

- **перевод (промпт):** Немного прохладно
- **эталон-пример:** It's a bit chilly for a walk.
- **дистракторы:**
    - `It's a bit chilly for walk.` — article: **for walk** → `for a walk`

### Are you from around here?

- **перевод (промпт):** Вы местный?
- **эталон-пример:** Are you from around here, or are you visiting?
- **дистракторы:**
    - `Are you from the around here, or are you visiting?` — article: **the around here** → `around here`
    - `Are you around here from here, or are you visiting?` — word_order: **around here from here** → `from around here`

### How do you do?

- **перевод (промпт):** Как поживаете?
- **эталон-пример:** How do you do? Nice to meet you.
- **дистракторы:**
    - `How do you do? Nice to meet you all.` — word_order: **you all** → `you`

### catch up

- **перевод (промпт):** обсудить последние события
- **эталон-пример:** It's nice to catch up with old friends.
- **дистракторы:**
    - `It's nice to catch up with the old friends.` — article: **the old friends** → `old friends`
    - `It's nice to catch up for old friends.` — preposition: **for old friends** → `with old friends`
    - `It's nice to catches up with old friends.` — tense: **catches up** → `catch up`

### get to know

- **перевод (промпт):** узнать (кого-то/что-то)
- **эталон-пример:** I enjoy getting to know people's stories and backgrounds.
- **дистракторы:**
    - `I enjoy getting to know people stories and backgrounds.` — word_order: **people stories** → `people's stories`
    - `I enjoy getting to know people's stories and background.` — article: **background** → `backgrounds`

### start a conversation

- **перевод (промпт):** начать разговор
- **эталон-пример:** He always knows how to start a conversation.
- **дистракторы:**
    - `He always know how to start a conversation.` — tense: **know** → `knows`

### chat

- **перевод (промпт):** беседовать, болтать
- **эталон-пример:** We sat in the coffee shop and had a nice chat.
- **дистракторы:**
    - `We sat in the coffee shop and had nice chat.` — article: **nice chat** → `a nice chat`
    - `We sat in coffee shop and had a nice chat.` — article: **coffee shop** → `the coffee shop`

### How's it going?

- **перевод (промпт):** Как дела?
- **эталон-пример:** Hey, Tom! How's it going?
- **дистракторы:**
    - `Hey, Tom! How's going?` — modal_to: **How's going** → `How's it going?`
    - `Hey, Tom! How is it going?` — tense: **is** → `'s`

### Meet someone new

- **перевод (промпт):** познакомиться с кем-то новым
- **эталон-пример:** It's exciting to meet someone new at a party.
- **дистракторы:**
    - `It's exciting to meet someone new in a party.` — preposition: **in a party** → `at a party`

### Talk about myself

- **перевод (промпт):** рассказать о себе
- **эталон-пример:** In interviews, I often talk about myself and my experiences.
- **дистракторы:**
    - `In interviews, I often talks about myself and my experiences.` — tense: **talks** → `talk`

### Sorry, I didn't catch your name

- **перевод (промпт):** Извините, я не расслышал ваше имя
- **эталон-пример:** Sorry, I didn't catch your name the first time.
- **дистракторы:**
    - `Sorry, I didn't catch the name the first time.` — article: **the** → `your`

### break the ice

- **перевод (промпт):** растопить лёд
- **эталон-пример:** Telling a joke can help break the ice in a new environment.
- **дистракторы:**
    - `Telling a joke can help to break the ice in a new environment.` — modal_to: **to break** → `break`
    - `Telling a joke can help break ice in a new environment.` — article: **break ice** → `break the ice`

### lovely

- **перевод (промпт):** прекрасный
- **эталон-пример:** The weather has been lovely all week.
- **дистракторы:**
    - `The weather has been the lovely all week.` — article: **the lovely** → `lovely`

### Have you been here before?

- **перевод (промпт):** Вы были здесь раньше?
- **эталон-пример:** Have you been here before, or is this your first time?
- **дистракторы:**
    - `Have you been here before, or are this your first time?` — tense: **are this** → `is this`

### weather

- **перевод (промпт):** погода
- **эталон-пример:** The weather is nice today.
- **дистракторы:**
    - `The weather are nice today.` — tense: **are** → `is`
    - `The weather is the nice today.` — article: **the nice** → `nice`

## Телефон и мессенджеры

### make a call

- **перевод (промпт):** совершить звонок
- **эталон-пример:** I need to make a call to our supplier before the end of the day.
- **дистракторы:**
    - `I need to make call to our supplier before the end of the day.` — article: **make call** → `make a call`

### leave a message

- **перевод (промпт):** оставить сообщение
- **эталон-пример:** Can you leave a message after the beep?
- **дистракторы:**
    - `Can you leave a message at the beep?` — preposition: **at** → `after`
    - `Can you leaves a message after the beep?` — tense: **leaves** → `leave`
    - `Can you leave the message after the beep?` — article: **the message** → `a message`

### set up a meeting

- **перевод (промпт):** организовать встречу
- **эталон-пример:** Let's set up a meeting for next week.
- **дистракторы:**
    - `Let's set up meeting for next week.` — article: **set up meeting** → `set up a meeting`
    - `Let's set up a meeting at next week.` — preposition: **at next week** → `for next week`

### send a text

- **перевод (промпт):** отправить сообщение
- **эталон-пример:** I will send a text to let her know.
- **дистракторы:**
    - `I will send text to let her know.` — article: **text** → `a text`

### check your messages

- **перевод (промпт):** проверить свои сообщения
- **эталон-пример:** Don't forget to check your messages when you get home.
- **принимаемые варианты:**
    - `look at your messages` — _Эквивалентная фраза с тем же значением._
    - `review your messages` — _Альтернатива с тем же смыслом._
- **дистракторы:**
    - `Don't forget to check the messages when you get home.` — article: **the** → `your`

### give someone a call

- **перевод (промпт):** позвонить кому-либо
- **эталон-пример:** I'll give you a call tomorrow evening.
- **дистракторы:**
    - `I'll give you call tomorrow evening.` — article: **you call** → `you a call`
    - `I'll give you a call in tomorrow evening.` — preposition: **in tomorrow** → `tomorrow`

### answer the phone

- **перевод (промпт):** ответить на звонок
- **эталон-пример:** I answered the phone on the first ring.
- **дистракторы:**
    - `I answered the phone at the first ring.` — preposition: **at** → `on`
    - `I answered phone on the first ring.` — article: **phone** → `the phone`

### What's your number?

- **перевод (промпт):** Какой у тебя номер?
- **эталон-пример:** What's your number? I'll save it in my contacts.
- **дистракторы:**
    - `What's your number? I'll save it in the contacts.` — article: **the contacts** → `my contacts`
    - `What's your number? I save it in my contacts.` — tense: **I save** → `I'll save`

### call back

- **перевод (промпт):** перезвонить
- **эталон-пример:** Could you call me back later?
- **дистракторы:**
    - `Could you call back me later?` — word_order: **call back me** → `call me back`

### mute the phone

- **перевод (промпт):** отключить звук на телефоне
- **эталон-пример:** Please mute your phone during the meeting.
- **дистракторы:**
    - `Please mute the phone during the meeting.` — article: **the** → `your`

### message thread

- **перевод (промпт):** цепочка сообщений
- **эталон-пример:** I need to scroll through the message thread to find that information.
- **дистракторы:**
    - `I need to scroll through the message thread to finding that information.` — modal_to: **to finding** → `to find`
    - `I need to scroll through the message thread for find that information.` — preposition: **for** → `to`

### save a contact

- **перевод (промпт):** сохранить контакт
- **эталон-пример:** I'll save your contact for future reference.
- **дистракторы:**
    - `I'll save contact for future reference.` — article: **contact** → `your contact`

### send a voice message

- **перевод (промпт):** отправить голосовое сообщение
- **эталон-пример:** You can send a voice message if you can't type.
- **дистракторы:**
    - `You can send voice message if you can't type.` — article: **voice message** → `a voice message`

### on the line

- **перевод (промпт):** на линии
- **эталон-пример:** Please hold on, she's on the line with another call.
- **дистракторы:**
    - `Please hold on, she's on line with another call.` — article: **on line** → `on the line`

### get through to

- **перевод (промпт):** дозвониться до
- **эталон-пример:** I tried calling, but I couldn't get through to him.
- **принимаемые варианты:**
    - `reach him` — _синоним "дозвониться до"._
    - `contact him` — _синоним "дозвониться до"._
- **дистракторы:**
    - `I tried calling, but I couldn't get through him.` — preposition: **get through him** → `get through to him`

### video call

- **перевод (промпт):** видеозвонок
- **эталон-пример:** She prefers video calls to stay in touch with family.
- **дистракторы:**
    - `She prefers video call to stay in touch with family.` — article: **video call** → `video calls`

### voicemail

- **перевод (промпт):** голосовая почта
- **эталон-пример:** He left a voicemail about the meeting details.
- **дистракторы:**
    - `He left voicemail about the meeting details.` — article: **voicemail** → `a voicemail`

### drop a line

- **перевод (промпт):** черкнуть пару строк
- **эталон-пример:** Feel free to drop me a line if you have any questions.
- **дистракторы:**
    - `Feel free to drop me line if you have any questions.` — article: **me line** → `me a line`

### line is busy

- **перевод (промпт):** линия занята
- **эталон-пример:** I tried to call, but the line was busy.
- **дистракторы:**
    - `I tried to call, but line was busy.` — article: **line** → `the line`

### quick chat

- **перевод (промпт):** быстрый разговор
- **эталон-пример:** Let's have a quick chat about the project.
- **принимаемые варианты:**
    - `short conversation` — _Синоним по значению._
    - `brief discussion` — _Синоним по значению._
- **дистракторы:**
    - `Let's have quick chat about the project.` — article: **quick chat** → `a quick chat`
    - `Let's have a quick chat about project.` — article: **about project** → `about the project`

## Покупки в супермаркете

### Where can I find the dairy section?

- **перевод (промпт):** Где я могу найти отдел молочной продукции?
- **эталон-пример:** Where can I find the dairy section?
- **дистракторы:**
    - `Where can I find dairy section?` — article: **dairy section** → `the dairy section`

### shopping cart

- **перевод (промпт):** тележка для покупок
- **эталон-пример:** I grabbed a shopping cart and started my grocery shopping.
- **дистракторы:**
    - `I grabbed shopping cart and started my grocery shopping.` — article: **shopping cart** → `a shopping cart`

### checkout counter

- **перевод (промпт):** кассовая стойка
- **эталон-пример:** I paid for my groceries at the checkout counter.
- **дистракторы:**
    - `I paid for my groceries at the checkout counters.` — tense: **counters** → `counter`
    - `I paid for my groceries in the checkout counter.` — preposition: **in** → `at`

### Are there any discounts available?

- **перевод (промпт):** Есть ли какие-нибудь скидки?
- **эталон-пример:** I always ask, 'Are there any discounts available?' before paying.
- **дистракторы:**
    - `I always ask, 'Are there the discounts available?' before paying.` — article: **the discounts** → `any discounts`

### grocery list

- **перевод (промпт):** список продуктов
- **эталон-пример:** I made a grocery list before going to the store.
- **дистракторы:**
    - `I made a grocery lists before going to the store.` — tense: **grocery lists** → `grocery list`
    - `I made grocery list before going to the store.` — article: **grocery list** → `a grocery list`

### self-checkout

- **перевод (промпт):** самостоятельная касса
- **эталон-пример:** I prefer using the self-checkout for small purchases.
- **дистракторы:**
    - `I prefer to use the self-checkout for small purchases.` — modal_to: **prefer to use** → `prefer using`
    - `I prefer using self-checkout for small purchases.` — article: **self-checkout** → `the self-checkout`

### fresh produce

- **перевод (промпт):** свежие продукты
- **эталон-пример:** The fresh produce section is on the left.
- **дистракторы:**
    - `The fresh produce section is on left.` — article: **on left** → `on the left`
    - `The fresh produce section is in the left.` — preposition: **in the left** → `on the left`

### How much does this cost?

- **перевод (промпт):** Сколько это стоит?
- **эталон-пример:** I asked the clerk, 'How much does this cost?'
- **дистракторы:**
    - `I asked the clerk, 'How much do this cost?'` — tense: **do this** → `does this`

### Can I pay by card?

- **перевод (промпт):** Могу я оплатить картой?
- **эталон-пример:** Can I pay by card, or is it cash only?
- **дистракторы:**
    - `Can I pay by card, at is it cash only?` — preposition: **at** → `or`
    - `Can I pay by card, or are it cash only?` — tense: **are** → `is`

### on sale

- **перевод (промпт):** на распродаже
- **эталон-пример:** These apples are on sale today.
- **дистракторы:**
    - `These apples are in sale today.` — preposition: **in sale** → `on sale`

### cash register

- **перевод (промпт):** кассовый аппарат
- **эталон-пример:** The cash register was down, so we had to wait.
- **принимаемые варианты:**
    - `till` — _Кассовый аппарат может также называться "тиллом"._
- **дистракторы:**
    - `The cash register was down, so we had to wait for a while.` — word_order: **wait for a while** → `wait`

### carry out

- **перевод (промпт):** выполнить
- **эталон-пример:** Please carry out all items from your grocery list.
- **дистракторы:**
    - `Please carry out all items from your grocery lists.` — tense: **grocery lists** → `grocery list`
    - `Please carry out all item from your grocery list.` — article: **item** → `items`

### I need a bag, please.

- **перевод (промпт):** Мне нужен пакет, пожалуйста.
- **эталон-пример:** I told the cashier, 'I need a bag, please.'
- **дистракторы:**
    - `I told the cashier, 'I need a bag for please.'` — preposition: **for please** → `please`

### pay in cash

- **перевод (промпт):** оплатить наличными
- **эталон-пример:** I decided to pay in cash this time.
- **принимаемые варианты:**
    - `pay with cash` — _эквивалентное значение за счет использования наличных_
    - `make a cash payment` — _также обозначает оплату наличными_
- **дистракторы:**
    - `I decided to pay cash this time.` — article: **to pay cash** → `to pay in cash`

### running low

- **перевод (промпт):** заканчиваться
- **эталон-пример:** We are running low on milk, I must buy more.
- **дистракторы:**
    - `We are running lows on milk, I must buy more.` — tense: **running lows** → `running low`
    - `We are running low at milk, I must buy more.` — preposition: **at milk** → `on milk`

### express lane

- **перевод (промпт):** полоса экспресс-обслуживания
- **эталон-пример:** I used the express lane because I only had a few items.
- **дистракторы:**
    - `I used a express lane because I only had a few items.` — article: **a express lane** → `the express lane`
    - `I used the express lane for I only had a few items.` — preposition: **for I only had a few items** → `because I only had a few items`
    - `I use the express lane because I only had a few items.` — tense: **use the express lane** → `used the express lane`

### checkout assistant

- **перевод (промпт):** кассир
- **эталон-пример:** The checkout assistant helped me with my transaction.
- **дистракторы:**
    - `The checkout assistant help me with my transaction.` — tense: **help** → `helped`

### How would you like to pay?

- **перевод (промпт):** Как вы хотели бы оплатить?
- **эталон-пример:** How would you like to pay, by cash or card?
- **принимаемые варианты:**
    - `How do you want to pay?` — _То же самое значение._
    - `How would you prefer to pay?` — _Синонимичное выражение._
- **дистракторы:**
    - `How would you like to pays, by cash or card?` — tense: **pays** → `pay`
    - `How would you like to pay, by the cash or card?` — article: **the cash** → `cash`
    - `How would you like to pay, by cash or the card?` — article: **the card** → `card`

### aisle

- **перевод (промпт):** проход
- **эталон-пример:** The cereal is on aisle four.
- **дистракторы:**
    - `The cereal is on the aisle four.` — article: **the aisle** → `aisle`

### scan an item

- **перевод (промпт):** сканировать товар
- **эталон-пример:** I need to scan each item at the checkout.
- **дистракторы:**
    - `I needs to scan each item at the checkout.` — tense: **needs** → `need`
    - `I need to scan each item in the checkout.` — preposition: **in** → `at`

## Магазин одежды и размеры

### What size are you looking for?

- **перевод (промпт):** Какой размер вы ищете?
- **эталон-пример:** What size are you looking for?
- **дистракторы:**
    - `What size are looking for?` — tense: **are looking** → `are you looking`
    - `What size are you look for?` — modal_to: **look** → `looking`
    - `What size is you looking for?` — tense: **is you** → `are you`

### fitting room

- **перевод (промпт):** примерочная
- **эталон-пример:** The fitting room is at the back of the store.
- **принимаемые варианты:**
    - `changing room` — _Синоним._
    - `dressing room` — _Синоним._
- **дистракторы:**
    - `The fitting room are at the back of the store.` — tense: **are** → `is`
    - `The fitting room is in the back of the store.` — preposition: **in** → `at`

### Can I try this on?

- **перевод (промпт):** Могу я это примерить?
- **эталон-пример:** Can I try this on in the fitting room?
- **дистракторы:**
    - `Can I try this on in fitting room?` — preposition: **in** → `in the`

### larger size

- **перевод (промпт):** больший размер
- **эталон-пример:** Do you have this in a larger size?
- **дистракторы:**
    - `Do you have this at a larger size?` — preposition: **at a larger size** → `in a larger size`
    - `Do you have this in larger size?` — article: **larger size** → `a larger size`

### smaller size

- **перевод (промпт):** меньший размер
- **эталон-пример:** I need this in a smaller size.
- **дистракторы:**
    - `I need this at a smaller size.` — preposition: **at a smaller size** → `in a smaller size`
    - `I need this in smaller size.` — article: **in smaller size** → `in a smaller size`

### How much does it cost?

- **перевод (промпт):** Сколько это стоит?
- **эталон-пример:** How much does this dress cost?
- **дистракторы:**
    - `How much does dress this cost?` — word_order: **dress this** → `this dress`

### Do you accept credit cards?

- **перевод (промпт):** Вы принимаете кредитные карты?
- **эталон-пример:** Do you accept credit cards for payment?
- **дистракторы:**
    - `Do you accept credit card for payment?` — article: **credit card** → `credit cards`

### Can I get a refund?

- **перевод (промпт):** Могу я получить возврат?
- **эталон-пример:** Can I get a refund if it doesn't fit?
- **дистракторы:**
    - `Can I get refund if it doesn't fit?` — article: **refund** → `a refund`

### exchange policy

- **перевод (промпт):** политика обмена
- **эталон-пример:** The exchange policy allows you to return items within 30 days.
- **дистракторы:**
    - `The exchange policy allow you to return items within 30 days.` — tense: **allow** → `allows`
    - `The exchange policy allows you for return items within 30 days.` — preposition: **for return** → `to return`

### return receipt

- **перевод (промпт):** чек для возврата
- **эталон-пример:** Keep your return receipt in case you need to return the item.
- **дистракторы:**
    - `Keep your return receipt in case you need for return the item.` — preposition: **for return** → `to return`
    - `Keep the return receipt in case you need to return the item.` — article: **the return receipt** → `your return receipt`

### Does this come in different colors?

- **перевод (промпт):** Это бывает в разных цветах?
- **эталон-пример:** Does this shirt come in different colors?
- **дистракторы:**
    - `Does this shirt come in different color?` — article: **different color** → `different colors`
    - `Does this shirt come in a different colors?` — article: **a different colors** → `different colors`

### out of stock

- **перевод (промпт):** нет в наличии
- **эталон-пример:** These jeans are currently out of stock.
- **дистракторы:**
    - `These jeans is currently out of stock.` — tense: **is** → `are`
    - `These jeans are currently out in stock.` — preposition: **out in** → `out of`

### cash register

- **перевод (промпт):** кассовый аппарат
- **эталон-пример:** The cash register was down, so we had to wait.
- **принимаемые варианты:**
    - `till` — _Кассовый аппарат может также называться "тиллом"._
- **дистракторы:**
    - `The cash register was down, so we had to wait for a while.` — word_order: **wait for a while** → `wait`

### What's your return policy?

- **перевод (промпт):** Какие у вас правила возврата?
- **эталон-пример:** What's your return policy on sale items?
- **дистракторы:**
    - `What's your return policy in sale items?` — preposition: **in** → `on`

### Can I help you with something?

- **перевод (промпт):** Могу я вам чем-то помочь?
- **эталон-пример:** Can I help you with something today?
- **дистракторы:**
    - `Can I help you with the something today?` — article: **the something** → `something`
    - `Can I help you for something today?` — preposition: **for** → `with`
    - `Can I helped you with something today?` — tense: **helped** → `help`

### customer service desk

- **перевод (промпт):** стойка обслуживания клиентов
- **эталон-пример:** If you have any issues, please visit the customer service desk.
- **дистракторы:**
    - `If you have any issues, please visit the the customer service desk.` — article: **the the** → `the`

### on sale

- **перевод (промпт):** на распродаже
- **эталон-пример:** These apples are on sale today.
- **дистракторы:**
    - `These apples are in sale today.` — preposition: **in sale** → `on sale`

### fitting

- **перевод (промпт):** примерка
- **эталон-пример:** The fitting went well, and everything fits perfectly.
- **дистракторы:**
    - `The fitting went well, and everything fit perfectly.` — tense: **fit** → `fits`

### sale sign

- **перевод (промпт):** знак распродажи
- **эталон-пример:** Look for the sale sign to find great discounts.
- **дистракторы:**
    - `Look for the sale signs to find great discounts.` — tense: **signs** → `sign`

## Спортзал и здоровье

### join a gym

- **перевод (промпт):** записаться в спортзал
- **эталон-пример:** I want to join a gym to improve my fitness.
- **дистракторы:**
    - `I want to join the gym to improve my fitness.` — article: **the gym** → `a gym`
    - `I wants to join a gym to improve my fitness.` — tense: **wants** → `want`

### work out

- **перевод (промпт):** тренироваться
- **эталон-пример:** I work out three times a week.
- **дистракторы:**
    - `I work out three time a week.` — tense: **three time** → `three times`

### membership card

- **перевод (промпт):** членская карта
- **эталон-пример:** Don't forget to bring your membership card to the gym.
- **дистракторы:**
    - `Don't forget to bring your membership card in the gym.` — preposition: **in** → `to`
    - `Don't forget to bring the membership card to the gym.` — article: **the membership card** → `your membership card`

### exercise routine

- **перевод (промпт):** комплекс упражнений
- **эталон-пример:** My exercise routine includes running and weightlifting.
- **принимаемые варианты:**
    - `workout routine` — _Синонимично, значит, правильно._
    - `training routine` — _Синонимично, значит, правильно._
- **дистракторы:**
    - `My exercise routine includes running at weightlifting.` — preposition: **at weightlifting** → `and weightlifting`
    - `My exercise routine include running and weightlifting.` — tense: **include** → `includes`

### cardio workout

- **перевод (промпт):** кардиотренировка
- **эталон-пример:** Cardio workouts are great for heart health.
- **дистракторы:**
    - `Cardio workouts is great for heart health.` — tense: **is** → `are`
    - `Cardio workouts are great for the heart health.` — article: **the heart health** → `heart health`

### strength training

- **перевод (промпт):** силовая тренировка
- **эталон-пример:** Strength training can help build muscle mass.
- **дистракторы:**
    - `Strength training can help build the muscle mass.` — article: **the muscle mass** → `muscle mass`
    - `Strength training can helps build muscle mass.` — tense: **helps** → `help`

### keep fit

- **перевод (промпт):** поддерживать форму
- **эталон-пример:** I do yoga to keep fit.
- **дистракторы:**
    - `I do yoga for keep fit.` — modal_to: **for keep fit** → `to keep fit`

### healthy lifestyle

- **перевод (промпт):** здоровый образ жизни
- **эталон-пример:** A healthy lifestyle includes a balanced diet and regular exercise.
- **принимаемые варианты:**
    - `wholesome lifestyle` — _Синоним фразы._
    - `fit lifestyle` — _Синоним фразы._
- **дистракторы:**
    - `A healthy lifestyle include a balanced diet and regular exercise.` — tense: **include** → `includes`

### personal trainer

- **перевод (промпт):** персональный тренер
- **эталон-пример:** My personal trainer helped me develop a workout plan.
- **дистракторы:**
    - `My personal trainer help me develop a workout plan.` — tense: **help** → `helped`

### fitness level

- **перевод (промпт):** уровень физической подготовки
- **эталон-пример:** Your fitness level affects how you should train.
- **дистракторы:**
    - `Your fitness level affect how you should train.` — tense: **affect** → `affects`
    - `Your fitness level affects how you must train.` — modal_to: **must** → `should`

### cool down

- **перевод (промпт):** завершать тренировку
- **эталон-пример:** Always cool down after a workout to prevent injury.
- **дистракторы:**
    - `Always cools down after a workout to prevent injury.` — tense: **cools down** → `cool down`

### get in shape

- **перевод (промпт):** приходить в форму
- **эталон-пример:** I've started running to get in shape for the summer.
- **дистракторы:**
    - `I've start running to get in shape for the summer.` — tense: **start** → `started`
    - `I've started running for get in shape for the summer.` — modal_to: **for get** → `to get`

### diet plan

- **перевод (промпт):** план питания
- **эталон-пример:** She follows a strict diet plan to lose weight.
- **дистракторы:**
    - `She follows the strict diet plan to lose weight.` — article: **the strict** → `a strict`
    - `She follow a strict diet plan to lose weight.` — tense: **follow** → `follows`
    - `She follows a strict diet plan for lose weight.` — preposition: **for lose** → `to lose`

### feel energized

- **перевод (промпт):** чувствовать себя энергичным
- **эталон-пример:** After a workout, I always feel energized.
- **дистракторы:**
    - `After the workout, I always feel energized.` — article: **the workout** → `a workout`
    - `After a workout, I always feels energized.` — tense: **feels** → `feel`

### flexibility exercises

- **перевод (промпт):** упражнения на гибкость
- **эталон-пример:** Flexibility exercises help prevent injuries.
- **дистракторы:**
    - `Flexibility exercises help prevent injury.` — tense: **injury** → `injuries`

### lose weight

- **перевод (промпт):** худеть
- **эталон-пример:** I'm trying to lose weight by eating healthier.
- **дистракторы:**
    - `I'm trying to lose weight by eat healthier.` — tense: **eat** → `eating`

### gain muscle

- **перевод (промпт):** наращивать мышцы
- **эталон-пример:** He wants to gain muscle before the competition season.
- **дистракторы:**
    - `He wants to gain the muscle before the competition season.` — article: **the muscle** → `muscle`
    - `He want to gain muscle before the competition season.` — tense: **want** → `wants`

### feel sore

- **перевод (промпт):** чувствовать боль
- **эталон-пример:** I feel sore after yesterday's workout.
- **дистракторы:**
    - `I feel sorely after yesterday's workout.` — false_friend: **sorely** → `sore`

## Экстренные ситуации

### Call the police

- **перевод (промпт):** Вызвать полицию
- **эталон-пример:** If you see a break-in, call the police immediately.
- **дистракторы:**
    - `If you see break-in, call the police immediately.` — article: **break-in** → `a break-in`

### I need help

- **перевод (промпт):** Мне нужна помощь
- **эталон-пример:** I need help, please call an ambulance.
- **дистракторы:**
    - `I need help, please call the ambulance.` — article: **the** → `an`

### ambulance

- **перевод (промпт):** Скорая помощь
- **эталон-пример:** We called an ambulance after the accident.
- **дистракторы:**
    - `We called an ambulance to the accident.` — preposition: **to the accident** → `after the accident`
    - `We called the ambulance after the accident.` — article: **the ambulance** → `an ambulance`
    - `We calls an ambulance after the accident.` — tense: **calls** → `called`

### I lost my wallet

- **перевод (промпт):** Я потерял(а) кошелёк
- **эталон-пример:** I lost my wallet at the station.
- **дистракторы:**
    - `I lost my wallet at station.` — article: **at station** → `at the station`

### It's an emergency

- **перевод (промпт):** Это чрезвычайная ситуация
- **эталон-пример:** Please hurry, it's an emergency!
- **дистракторы:**
    - `Please hurry, it are an emergency!` — tense: **are** → `is`

### My passport was stolen

- **перевод (промпт):** Мой паспорт украли
- **эталон-пример:** My passport was stolen while I was at the cafe.
- **дистракторы:**
    - `My passport were stolen while I was at the cafe.` — tense: **were** → `was`

### contact the authorities

- **перевод (промпт):** Связаться с властями
- **эталон-пример:** You should contact the authorities about the missing person.
- **дистракторы:**
    - `You should contact the authorities about the missing persons.` — tense: **persons** → `person`
    - `You should contact the authorities in the missing person.` — preposition: **in** → `about`

### emergency services

- **перевод (промпт):** Аварийные службы
- **эталон-пример:** Dial 911 to reach emergency services.
- **дистракторы:**
    - `Dial 911 to reach emergency service.` — false_friend: **service** → `services`

### file a report

- **перевод (промпт):** Подать заявление
- **эталон-пример:** You need to file a report at the police station.
- **дистракторы:**
    - `You need to file report at the police station.` — article: **file report** → `file a report`

### break-in

- **перевод (промпт):** Взлом
- **эталон-пример:** There was a break-in at my neighbor's last night.
- **дистракторы:**
    - `There was break-in at my neighbor's last night.` — article: **break-in** → `a break-in`

### What happened?

- **перевод (промпт):** Что случилось?
- **эталон-пример:** Can you tell me what happened?
- **дистракторы:**
    - `Can you tell me what happens?` — tense: **happens** → `happened`

### stay calm

- **перевод (промпт):** оставаться спокойным
- **эталон-пример:** It's important to stay calm during training sessions.
- **дистракторы:**
    - `It's important to stay calm during training session.` — tense: **session** → `sessions`
    - `It's important to stay calm during the training sessions.` — article: **the training sessions** → `training sessions`

### urgent

- **перевод (промпт):** Срочный
- **эталон-пример:** This matter is urgent, please respond quickly.
- **дистракторы:**
    - `This matter with urgent, please respond quickly.` — preposition: **with urgent** → `is urgent`
    - `This matter is an urgent, please respond quickly.` — article: **an urgent** → `urgent`
    - `This matter are urgent, please respond quickly.` — tense: **are urgent** → `is urgent`

### locksmith

- **перевод (промпт):** Слесарь (по замкам)
- **эталон-пример:** I need a locksmith because I lost my keys.
- **принимаемые варианты:**
    - `locksmith for locks` — _Эквивалентное выражение._
- **дистракторы:**
    - `I need locksmith because I lost my keys.` — article: **locksmith** → `a locksmith`

### lost and found

- **перевод (промпт):** Бюро находок
- **эталон-пример:** Check the lost and found for your bag.
- **дистракторы:**
    - `Check lost and found for your bag.` — article: **Check lost and found** → `Check the lost and found`
    - `Check the lost and founds for your bag.` — article: **the lost and founds** → `the lost and found`

### crime

- **перевод (промпт):** Преступление
- **эталон-пример:** Reporting a crime can help others stay safe.
- **дистракторы:**
    - `Reporting a crime can helps others stay safe.` — tense: **helps** → `help`

### pickpocket

- **перевод (промпт):** Карманник
- **эталон-пример:** Beware of pickpockets in crowded places.
- **дистракторы:**
    - `Beware of the pickpockets in crowded places.` — article: **the pickpockets** → `pickpockets`

### Can you describe it?

- **перевод (промпт):** Можете это описать?
- **эталон-пример:** Can you describe it to the police officer?
- **дистракторы:**
    - `Can you describe it to police officer?` — article: **police officer** → `the police officer`
    - `Can you describes it to the police officer?` — tense: **describes** → `describe`

## В аэропорту и на рейсе

### check-in desk

- **перевод (промпт):** стойка регистрации
- **эталон-пример:** Please proceed to the check-in desk to collect your boarding pass.
- **дистракторы:**
    - `Please proceeds to the check-in desk to collect your boarding pass.` — tense: **proceeds** → `proceed`
    - `Please proceed to check-in desk to collect your boarding pass.` — article: **check-in desk** → `the check-in desk`
    - `Please proceed to the check-in desk for collect your boarding pass.` — preposition: **for collect** → `to collect`

### boarding pass

- **перевод (промпт):** посадочный талон
- **эталон-пример:** You need your boarding pass to enter the boarding area.
- **принимаемые варианты:**
    - `flight ticket` — _эквивалентное выражение для обозначения посадочного талона._
- **дистракторы:**
    - `You need your boarding pass to enter the boarding areas.` — tense: **boarding areas** → `boarding area.`

### security check

- **перевод (промпт):** досмотр безопасности
- **эталон-пример:** Please remove your shoes at the security check.
- **дистракторы:**
    - `Please remove your shoes is the security check.` — tense: **is** → `at`
    - `Please remove your shoes in the security check.` — preposition: **in** → `at`

### baggage claim

- **перевод (промпт):** выдача багажа
- **эталон-пример:** After landing, proceed to baggage claim to collect your luggage.
- **дистракторы:**
    - `After landing, proceed to the baggage claim to collect your luggage.` — article: **the baggage claim** → `baggage claim`

### customs

- **перевод (промпт):** таможня
- **эталон-пример:** You have to declare goods at customs if they exceed the limit.
- **дистракторы:**
    - `You have to declare goods in customs if they exceed the limit.` — preposition: **in customs** → `at customs`
    - `You have to declare goods at the customs if they exceed the limit.` — article: **the customs** → `customs`

### gate number

- **перевод (промпт):** номер выхода на посадку
- **эталон-пример:** Check your gate number on the display board.
- **дистракторы:**
    - `Check your gate number in the display board.` — preposition: **in the** → `on the`
    - `Check your gate numbers on the display board.` — article: **gate numbers** → `gate number`

### boarding

- **перевод (промпт):** посадка на борт
- **эталон-пример:** Boarding will begin 30 minutes before departure.
- **дистракторы:**
    - `Boarding will began 30 minutes before departure.` — tense: **began** → `begin`

### passport control

- **перевод (промпт):** паспортный контроль
- **эталон-пример:** There was a long queue at passport control.
- **дистракторы:**
    - `There was long queue at passport control.` — article: **long** → `a long`

### aisle seat

- **перевод (промпт):** место у прохода
- **эталон-пример:** I prefer an aisle seat for more legroom.
- **дистракторы:**
    - `I prefer the aisle seat for more legroom.` — article: **the aisle seat** → `an aisle seat`
    - `I prefer an aisle seat for more legrooms.` — tense: **legrooms** → `legroom`

### window seat

- **перевод (промпт):** место у окна
- **эталон-пример:** Would you like a window seat or an aisle seat?
- **дистракторы:**
    - `Would you like a window seat on an aisle seat?` — preposition: **on** → `or`
    - `Would you like the window seat or an aisle seat?` — article: **the window seat** → `a window seat`
    - `Would you like a window seat or aisle seat?` — article: **aisle seat** → `an aisle seat`

### carry-on bag

- **перевод (промпт):** ручная кладь
- **эталон-пример:** Your carry-on bag must fit in the overhead compartment.
- **дистракторы:**
    - `Your carry-on bag must fit in a overhead compartment.` — article: **a overhead** → `the overhead`

### fasten your seatbelt

- **перевод (промпт):** пристегните ремень безопасности
- **эталон-пример:** Please fasten your seatbelt as we prepare for takeoff.
- **дистракторы:**
    - `Please fastened your seatbelt as we prepare for takeoff.` — tense: **fastened** → `fasten`

### overhead compartment

- **перевод (промпт):** верхний багажный отсек
- **эталон-пример:** Please place your bags in the overhead compartment.
- **дистракторы:**
    - `Please place your bags in overhead compartment.` — article: **overhead** → `the overhead`

### tray table

- **перевод (промпт):** откидной столик
- **эталон-пример:** Please fold up your tray table before landing.
- **дистракторы:**
    - `Please fold up the tray table before landing.` — article: **the** → `your`

### flight attendant

- **перевод (промпт):** бортпроводник
- **эталон-пример:** If you need any assistance, ask a flight attendant.
- **дистракторы:**
    - `If you need any assistance, ask a flight attendants.` — tense: **attendants** → `attendant`
    - `If you need any assistance, ask the flight attendant.` — article: **the** → `a`

### takeoff

- **перевод (промпт):** взлет
- **эталон-пример:** The takeoff was smooth and on time.
- **дистракторы:**
    - `The takeoff was smooth and on times.` — tense: **on times** → `on time`

### landing

- **перевод (промпт):** посадка
- **эталон-пример:** We are now beginning our final approach for landing.
- **дистракторы:**
    - `We are now beginning our final approach for the landing.` — article: **the landing** → `landing`

### on time

- **перевод (промпт):** вовремя
- **эталон-пример:** The flight departed on time without any delays.
- **дистракторы:**
    - `The flight departed on time without any delay.` — tense: **delay** → `delays`

### luggage

- **перевод (промпт):** багаж
- **эталон-пример:** Make sure your luggage is tagged correctly.
- **дистракторы:**
    - `Make sure the luggage is tagged correctly.` — article: **the luggage** → `your luggage`

## Отель: бронь и заселение

### I'd like to book a room.

- **перевод (промпт):** Я бы хотел забронировать номер.
- **эталон-пример:** I'd like to book a room with a sea view.
- **дистракторы:**
    - `I'd like to book the room with a sea view.` — article: **the** → `a`

### check in

- **перевод (промпт):** заселяться
- **эталон-пример:** We can check in after 2 PM.
- **дистракторы:**
    - `We can the check in after 2 PM.` — article: **the check in** → `check in`
    - `We can check after 2 PM.` — modal_to: **check** → `check in`
- **флаги:**
    - ✍️ **не слово → правка** — заселяться — заселяться is not a valid English phrase.

### Could I have your ID, please?

- **перевод (промпт):** Пожалуйста, предъявите ваше удостоверение личности.
- **эталон-пример:** When you arrive, the receptionist will say, 'Could I have your ID, please?'
- **дистракторы:**
    - `When you arrive, the receptionist will say, 'Could I have a ID, please?'` — article: **a ID** → `your ID`
    - `When you arrives, the receptionist will say, 'Could I have your ID, please?'` — tense: **arrives** → `arrive`

### key card

- **перевод (промпт):** ключ-карта
- **эталон-пример:** Here's your key card; your room is on the third floor.
- **дистракторы:**
    - `Here's the key card; your room is on the third floor.` — article: **the key card** → `your key card`
    - `Here's your key card; your room are on the third floor.` — tense: **are** → `is`

### suite

- **перевод (промпт):** люкс
- **эталон-пример:** We decided to stay in a suite for our anniversary.
- **дистракторы:**
    - `We decided to stay in suite for our anniversary.` — article: **in suite** → `in a suite`

### Where is the elevator?

- **перевод (промпт):** Где находится лифт?
- **эталон-пример:** Can you tell me where the elevator is?
- **дистракторы:**
    - `Can you tell me where is the elevator?` — word_order: **where is the elevator** → `where the elevator is`

### complimentary breakfast

- **перевод (промпт):** бесплатный завтрак
- **эталон-пример:** The hotel offers a complimentary breakfast every morning.
- **принимаемые варианты:**
    - `free breakfast` — _Синоним для 'бесплатный завтрак'._
- **дистракторы:**
    - `The hotel offers complimentary breakfast every morning.` — article: **complimentary breakfast** → `a complimentary breakfast`

### What time is check-out?

- **перевод (промпт):** Во сколько выезд?
- **эталон-пример:** Excuse me, what time is check-out tomorrow?
- **дистракторы:**
    - `Excuse me, what time are check-out tomorrow?` — tense: **are** → `is`

### Do you offer room service?

- **перевод (промпт):** Есть ли у вас обслуживание номеров?
- **эталон-пример:** After a long day, I ordered dinner through room service.
- **дистракторы:**
    - `After a long day, I order dinner through room service.` — tense: **order** → `ordered`
    - `After a long day, I ordered dinner in room service.` — preposition: **in** → `through`

### Wi-Fi password

- **перевод (промпт):** пароль от Wi-Fi
- **эталон-пример:** Can you give me the Wi-Fi password, please?
- **дистракторы:**
    - `Can you give me a Wi-Fi password, please?` — article: **a Wi-Fi password** → `the Wi-Fi password`

### twin room

- **перевод (промпт):** номер с двумя раздельными кроватями
- **эталон-пример:** We booked a twin room for our stay.
- **дистракторы:**
    - `We booked twin room for our stay.` — article: **twin room** → `a twin room`

### concierge

- **перевод (промпт):** консьерж
- **эталон-пример:** You can ask the concierge for dinner recommendations.
- **дистракторы:**
    - `You can ask concierge for dinner recommendations.` — article: **concierge** → `the concierge`
    - `You can ask the concierge about dinner recommendations.` — preposition: **about** → `for`

### There's an issue with my room.

- **перевод (промпт):** Проблема с моим номером.
- **эталон-пример:** Excuse me, there's an issue with my room; the air conditioning isn't working.
- **дистракторы:**
    - `Excuse me, there's an issue at my room; the air conditioning isn't working.` — preposition: **at my room** → `with my room`
    - `Excuse me, there are an issue with my room; the air conditioning isn't working.` — tense: **are an** → `is an`
    - `Excuse me, there's issue with my room; the air conditioning isn't working.` — article: **issue** → `an issue`

### Can I have extra towels?

- **перевод (промпт):** Можно мне дополнительные полотенца?
- **эталон-пример:** After a swim, I asked the front desk, 'Can I have extra towels?'
- **дистракторы:**
    - `After a swim, I asked the front desk, 'Can I has extra towels?'` — tense: **has** → `have`

### late check-out

- **перевод (промпт):** поздний выезд
- **эталон-пример:** The hotel offers a late check-out option for an additional fee.
- **дистракторы:**
    - `The hotel offer a late check-out option for an additional fee.` — tense: **offer** → `offers`

### Could you change my reservation?

- **перевод (промпт):** Можете изменить моё бронирование?
- **эталон-пример:** Could you change my reservation to include an extra night?
- **дистракторы:**
    - `Could you change my reservation to includes an extra night?` — tense: **includes** → `include`
    - `Could you change the reservation to include an extra night?` — article: **the reservation** → `my reservation`

### front desk

- **перевод (промпт):** стойка регистрации
- **эталон-пример:** If you need any help, the front desk is available 24/7.
- **дистракторы:**
    - `If you need any help, the front desk is available in 24/7.` — preposition: **in 24/7** → `24/7.`
    - `If you need any help, a front desk is available 24/7.` — article: **a front desk** → `the front desk`

### housekeeping

- **перевод (промпт):** уборка
- **эталон-пример:** I asked housekeeping to clean my room while I was out.
- **дистракторы:**
    - `I asked housekeeping to clean the room while I was out.` — article: **the room** → `my room`

### luggage storage

- **перевод (промпт):** хранение багажа
- **эталон-пример:** The hotel offers luggage storage for early arrivals.
- **дистракторы:**
    - `The hotel offers luggage storage for early arrival.` — tense: **early arrival** → `early arrivals`
    - `The hotel offers the luggage storage for early arrivals.` — article: **the luggage storage** → `luggage storage`

### wake-up call

- **перевод (промпт):** звонок-будильник
- **эталон-пример:** I requested a wake-up call for 6 AM.
- **дистракторы:**
    - `I requested wake-up call for 6 AM.` — article: **wake-up call** → `a wake-up call`
    - `I requested a wake-up calls for 6 AM.` — article: **a wake-up calls** → `a wake-up call`

## Городской транспорт и такси

### Where can I buy a ticket?

- **перевод (промпт):** Где я могу купить билет?
- **эталон-пример:** Where can I buy a ticket for the subway?
- **дистракторы:**
    - `Where can I buy ticket for the subway?` — article: **ticket** → `a ticket`

### I'm looking for the bus stop.

- **перевод (промпт):** Я ищу автобусную остановку.
- **эталон-пример:** I'm looking for the bus stop on Main Street.
- **дистракторы:**
    - `I'm looking for bus stop on Main Street.` — article: **bus stop** → `the bus stop`

### How much is a ticket?

- **перевод (промпт):** Сколько стоит билет?
- **эталон-пример:** How much is a ticket to downtown?
- **принимаемые варианты:**
    - `How much does a ticket cost?` — _Это также выражает ту же мысль о цене билета._
    - `What is the price of a ticket?` — _Это эквивалентно вопросу о стоимости билета._
- **дистракторы:**
    - `How much is ticket to downtown?` — article: **ticket** → `a ticket`
    - `How much is a ticket in downtown?` — preposition: **in downtown** → `to downtown`

### get on

- **перевод (промпт):** сесть (в транспорт)
- **эталон-пример:** We need to get on the next bus to the city center.
- **дистракторы:**
    - `We need to get on next bus to the city center.` — article: **next** → `the next`

### get off

- **перевод (промпт):** выйти (из транспорта)
- **эталон-пример:** You'll get off at the next station.
- **дистракторы:**
    - `You'll get off in the next station.` — preposition: **in the next station** → `at the next station`

### How can I get to...?

- **перевод (промпт):** Как добраться до...?
- **эталон-пример:** How can I get to the museum from here?
- **дистракторы:**
    - `How can I get to the museum at here?` — preposition: **at here** → `from here`
    - `How can I get to museum from here?` — article: **museum** → `the museum`

### I'd like a ticket to...

- **перевод (промпт):** Я бы хотел(а) билет до...
- **эталон-пример:** I'd like a ticket to the airport, please.
- **дистракторы:**
    - `I'd like the ticket to the airport, please.` — article: **the** → `a`

### subway

- **перевод (промпт):** метро
- **эталон-пример:** The subway is faster than the bus during rush hour.
- **принимаемые варианты:**
    - `metro` — _Синоним — метро._
- **дистракторы:**
    - `The subway is faster than bus during rush hour.` — article: **than bus** → `than the bus`
    - `The subway is more faster than the bus during rush hour.` — tense: **more faster** → `faster`

### bus schedule

- **перевод (промпт):** расписание автобусов
- **эталон-пример:** Check the bus schedule to see the next departure.
- **дистракторы:**
    - `Check the bus schedule to see at the next departure.` — preposition: **at the next** → `the next`
    - `Check the bus schedule to sees the next departure.` — tense: **sees** → `see`
    - `Check the bus schedules to see the next departure.` — article: **schedules** → `schedule`

### I'd like to book a taxi.

- **перевод (промпт):** Я хотел(а) бы заказать такси.
- **эталон-пример:** I'd like to book a taxi for 7pm.
- **дистракторы:**
    - `I'd like to book a taxi at 7pm.` — preposition: **at 7pm** → `for 7pm`

### Could you tell me the way to...?

- **перевод (промпт):** Не могли бы вы подсказать путь до...?
- **эталон-пример:** Could you tell me the way to the nearest subway station?
- **дистракторы:**
    - `Could you tell me way to the nearest subway station?` — article: **way** → `the way`

### taxi stand

- **перевод (промпт):** стоянка такси
- **эталон-пример:** The hotel is next to a taxi stand.
- **дистракторы:**
    - `The hotel is next with a taxi stand.` — preposition: **next with** → `next to`
    - `The hotel are next to a taxi stand.` — tense: **are** → `is`

### change trains

- **перевод (промпт):** пересесть на другой поезд
- **эталон-пример:** We need to change trains at the next station.
- **дистракторы:**
    - `We need to change train at the next station.` — article: **change train** → `change trains`
    - `We need to change trains in the next station.` — preposition: **in the next station** → `at the next station`

### What's the fare?

- **перевод (промпт):** Сколько стоит проезд?
- **эталон-пример:** What's the fare to the airport in a taxi?
- **дистракторы:**
    - `What's fare to the airport in a taxi?` — article: **fare** → `the fare`
    - `What's the fare at the airport in a taxi?` — preposition: **at** → `to`

### mind the gap

- **перевод (промпт):** осторожно, промежуток (между вагоном и платформой)
- **эталон-пример:** Please mind the gap when exiting the train.
- **дистракторы:**
    - `Please mind gap when exiting the train.` — article: **mind gap** → `mind the gap`
    - `Please mind the gap when exit the train.` — tense: **when exit** → `when exiting`
    - `Please mind in the gap when exiting the train.` — preposition: **mind in the gap** → `mind the gap`

### pay by card

- **перевод (промпт):** оплатить картой
- **эталон-пример:** Can I pay by card for the taxi ride?
- **дистракторы:**
    - `Can I pay by the card for the taxi ride?` — article: **the card** → `card`

### Is this the line for the bus?

- **перевод (промпт):** Это очередь на автобус?
- **эталон-пример:** Is this the line for the bus to the city center?
- **дистракторы:**
    - `Is this the line on the bus to the city center?` — preposition: **on the bus** → `for the bus`
    - `Is this line for the bus to the city center?` — article: **this line** → `this the line`
    - `Is the line for the bus to the city center?` — word_order: **Is the line** → `Is this the line`

### public transport

- **перевод (промпт):** общественный транспорт
- **эталон-пример:** Public transport is very efficient in this city.
- **дистракторы:**
    - `Public transport are very efficient in this city.` — tense: **are** → `is`
    - `Public transport is very efficiently in this city.` — false_friend: **efficiently** → `efficient`

### Please take me to...

- **перевод (промпт):** Отвезите меня, пожалуйста, в...
- **эталон-пример:** Please take me to the hotel on Elm Street.
- **дистракторы:**
    - `Please take me to hotel on Elm Street.` — article: **hotel** → `the hotel`
    - `Please take me to the hotel in Elm Street.` — preposition: **in Elm Street** → `on Elm Street`

## Компьютер и интернет

### set up a new account

- **перевод (промпт):** создать новый аккаунт
- **эталон-пример:** I need to set up a new account for this service.
- **дистракторы:**
    - `I need to set up the new account for this service.` — article: **the** → `a`

### connect to Wi-Fi

- **перевод (промпт):** подключиться к Wi-Fi
- **эталон-пример:** Make sure you connect to Wi-Fi to save on data usage.
- **дистракторы:**
    - `Make sure you connecting to Wi-Fi to save on data usage.` — tense: **connecting** → `connect`

### download a file

- **перевод (промпт):** загрузить файл
- **эталон-пример:** Can you download the file from the website?
- **дистракторы:**
    - `Can you download file from the website?` — article: **file** → `the file`
    - `Can you download the file at the website?` — preposition: **at** → `from`

### troubleshoot a problem

- **перевод (промпт):** устранять неполадку
- **эталон-пример:** You might need to troubleshoot a problem with the printer.
- **дистракторы:**
    - `You might need to troubleshoot a problem about the printer.` — preposition: **about** → `with`
    - `You might need to troubleshoot problems with the printer.` — tense: **problems** → `a problem`

### log in

- **перевод (промпт):** войти (в систему)
- **эталон-пример:** Please log in to access your account.
- **дистракторы:**
    - `Please log on to access your account.` — preposition: **log on** → `log in`

### reset your password

- **перевод (промпт):** сбросить пароль
- **эталон-пример:** If you've forgotten your password, reset it via email.
- **дистракторы:**
    - `If you've forgotten your password, reset it by email.` — preposition: **by** → `via`

### device

- **перевод (промпт):** устройство
- **эталон-пример:** This is a new device that connects to the internet.
- **дистракторы:**
    - `This are a new device that connects to the internet.` — tense: **are** → `is`
    - `This is a new device with connects to the internet.` — preposition: **with** → `that`
    - `This is the new device that connects to the internet.` — article: **the new** → `a new`

### file

- **перевод (промпт):** файл
- **эталон-пример:** Save the document as a PDF file.
- **дистракторы:**
    - `Save the document as PDF file.` — article: **PDF file** → `a PDF file`
    - `Save the document as a file PDF.` — word_order: **file PDF** → `PDF file`

### Wi-Fi network

- **перевод (промпт):** сеть Wi-Fi
- **эталон-пример:** Choose the correct Wi-Fi network from the list.
- **дистракторы:**
    - `Choose correct Wi-Fi network from the list.` — article: **correct** → `the correct`

### upload a document

- **перевод (промпт):** загрузить документ
- **эталон-пример:** Please upload your document to the portal.
- **дистракторы:**
    - `Please upload your document in the portal.` — preposition: **in the portal** → `to the portal`

### connectivity issues

- **перевод (промпт):** проблемы с подключением
- **эталон-пример:** We are experiencing connectivity issues with the internet.
- **дистракторы:**
    - `We are experiencing connectivity issue with the internet.` — article: **connectivity issue** → `connectivity issues`

### user-friendly

- **перевод (промпт):** удобный для пользователя
- **эталон-пример:** This software is very user-friendly.
- **дистракторы:**
    - `This software are very user-friendly.` — tense: **are** → `is`
    - `This software is very the user-friendly.` — article: **the user-friendly** → `user-friendly`

### create a backup

- **перевод (промпт):** создать резервную копию
- **эталон-пример:** It's important to create a backup of your files regularly.
- **дистракторы:**
    - `It's important to creates a backup of your files regularly.` — tense: **creates** → `create`
    - `It's important for create a backup of your files regularly.` — modal_to: **for create** → `to create`
    - `It's important to create backup of your files regularly.` — article: **backup** → `a backup`

### desktop

- **перевод (промпт):** настольный компьютер
- **эталон-пример:** I prefer using a desktop for complex tasks.
- **дистракторы:**
    - `I prefer using a desktop in complex tasks.` — preposition: **in** → `for`
    - `I prefer using the desktop for complex tasks.` — article: **the desktop** → `a desktop`

### run a program

- **перевод (промпт):** запустить программу
- **эталон-пример:** Click this icon to run the program.
- **дистракторы:**
    - `Click this icon to runs the program.` — tense: **runs** → `run`
    - `Click this icon to run a program.` — article: **a program** → `the program`

### update software

- **перевод (промпт):** обновить программное обеспечение
- **эталон-пример:** You should update your software to the latest version.
- **дистракторы:**
    - `You should update your software to the latest versions.` — tense: **versions** → `version`
    - `You should update your software for the latest version.` — preposition: **for** → `to`
    - `You should update the software to the latest version.` — article: **the software** → `your software`

### secure your account

- **перевод (промпт):** обезопасить свой аккаунт
- **эталон-пример:** Enable two-factor authentication to secure your account.
- **дистракторы:**
    - `Enable two-factor authentication to secure your accounts.` — article: **your accounts** → `your account`
    - `Enable two-factor authentication for secure your account.` — preposition: **for secure** → `to secure`

### internet browser

- **перевод (промпт):** интернет-браузер
- **эталон-пример:** Which internet browser do you use the most?
- **дистракторы:**
    - `Which internet browser do you uses the most?` — tense: **do you uses** → `do you use`

### hardware

- **перевод (промпт):** аппаратное обеспечение
- **эталон-пример:** The hardware needs an upgrade to run the newest software.
- **дистракторы:**
    - `The hardware need an upgrade to run the newest software.` — tense: **need** → `needs`
    - `The hardware needs upgrade to run the newest software.` — article: **upgrade** → `an upgrade`

## Introducing Yourself

### Nice to meet you

- **перевод (промпт):** Приятно познакомиться
- **эталон-пример:** Nice to meet you, I'm Sarah.
- **принимаемые варианты:**
    - `Pleased to meet you` — _Синонимичное выражение._
    - `Good to meet you` — _Синонимичное выражение._
- **дистракторы:**
    - `Nice meet you, I'm Sarah.` — tense: **meet** → `to meet`

### Where are you from?

- **перевод (промпт):** Откуда вы?
- **эталон-пример:** Where are you from? I'm from Canada.
- **дистракторы:**
    - `Where are you from? I'm from the Canada.` — article: **the Canada** → `Canada`

### How old are you?

- **перевод (промпт):** Сколько вам лет?
- **эталон-пример:** How old are you? I'm 25.
- **дистракторы:**
    - `How old are you? I 25.` — tense: **I 25** → `I'm 25`

### I'm a teacher

- **перевод (промпт):** Я учитель
- **эталон-пример:** I'm a teacher and I love it.
- **дистракторы:**
    - `I'm a teacher and I loves it.` — tense: **loves** → `love`
    - `I'm a teacher and I love the it.` — article: **the it** → `it`

### I work as a

- **перевод (промпт):** Я работаю как
- **эталон-пример:** I work as a doctor.
- **дистракторы:**
    - `I work as doctor.` — article: **as doctor** → `as a doctor`

### In my free time, I like to

- **перевод (промпт):** В свободное время я люблю
- **эталон-пример:** In my free time, I like to read books.
- **дистракторы:**
    - `In my free time, I like read books.` — modal_to: **like read** → `like to read`

### I enjoy

- **перевод (промпт):** Я получаю удовольствие от
- **эталон-пример:** I enjoy hiking in the mountains.
- **дистракторы:**
    - `I enjoy hiking at the mountains.` — preposition: **at the** → `in the`

### Tell me about yourself

- **перевод (промпт):** Расскажите мне о себе
- **эталон-пример:** Can you tell me about yourself?
- **дистракторы:**
    - `Can you tell about yourself?` — preposition: **tell about** → `tell me about`

### a little bit about me

- **перевод (промпт):** немного обо мне
- **эталон-пример:** Here's a little bit about me.
- **дистракторы:**
    - `Here is a little bit about me.` — tense: **Here is** → `Here's`
    - `Here's little bit about me.` — article: **little bit** → `a little bit`

### get to know each other

- **перевод (промпт):** узнать друг друга
- **эталон-пример:** Let's get to know each other better.
- **дистракторы:**
    - `Let's get to know each other best.` — tense: **get to know each other best** → `get to know each other better`

### What brings you here?

- **перевод (промпт):** Что вас сюда привело?
- **эталон-пример:** What brings you here to this town?
- **принимаемые варианты:**
    - `What has brought you here?` — _Это синонимично по смыслу._
- **дистракторы:**
    - `What brings you here in this town?` — preposition: **in** → `to`

### have in common

- **перевод (промпт):** иметь общее
- **эталон-пример:** We have a lot in common.
- **дистракторы:**
    - `We has a lot in common.` — tense: **has** → `have`

### meet for the first time

- **перевод (промпт):** встретиться впервые
- **эталон-пример:** I was nervous to meet for the first time at the conference.
- **дистракторы:**
    - `I was nervous to meet at the first time the conference.` — word_order: **at the first time the conference** → `for the first time at the conference`
    - `I was nervous to meet for first time at the conference.` — article: **for first time** → `for the first time`

### interested in

- **перевод (промпт):** интересуюсь
- **эталон-пример:** I'm interested in learning new languages.
- **дистракторы:**
    - `I'm interested learning new languages.` — modal_to: **interested** → `interested in`

### first impression

- **перевод (промпт):** первое впечатление
- **эталон-пример:** First impressions are important.
- **дистракторы:**
    - `First impression are important.` — tense: **impression are** → `impressions are`

### hobby

- **перевод (промпт):** хобби
- **эталон-пример:** My hobby is painting.
- **принимаемые варианты:**
    - `interest` — _Синонимичное слово._
    - `pastime` — _Синоним._
- **дистракторы:**
    - `My hobby are painting.` — tense: **are** → `is`
    - `The hobby is painting.` — article: **The** → `My`

### native language

- **перевод (промпт):** родной язык
- **эталон-пример:** My native language is Spanish.
- **принимаемые варианты:**
    - `first language` — _это синоним для родного языка._
    - `mother tongue` — _это выражение аналогично родному языку._
- **дистракторы:**
    - `My native languages is Spanish.` — tense: **languages** → `language`
    - `My a native language is Spanish.` — article: **a native** → `native`

### greetings

- **перевод (промпт):** приветствия
- **эталон-пример:** We exchanged greetings at the party.
- **дистракторы:**
    - `We exchanged greetings on the party.` — preposition: **on the party** → `at the party`
    - `We exchanged a greetings at the party.` — article: **a greetings** → `greetings`

### background

- **перевод (промпт):** предыстория, происхождение
- **эталон-пример:** She has a background in engineering.
- **дистракторы:**
    - `She has a background on engineering.` — preposition: **on** → `in`
    - `She have a background in engineering.` — tense: **have** → `has`

### acquaintance

- **перевод (промпт):** знакомство
- **эталон-пример:** We made a new acquaintance at the seminar.
- **дистракторы:**
    - `We made the new acquaintance at the seminar.` — article: **the new acquaintance** → `a new acquaintance`
    - `We made a new acquaintance in the seminar.` — preposition: **in the seminar** → `at the seminar`

### I grew up in

- **перевод (промпт):** Я вырос в
- **эталон-пример:** I grew up in a small town near the sea.
- **дистракторы:**
    - `I grew up in small town near the sea.` — article: **small town** → `a small town`

## Discussing Long-term House Rental

> ⚠️ **Приоритетная вычитка.** Бэкфилл 2026-08-13 дал здесь 28.1% брака дистракторов (9 из 32
> предложено) — самый высокий по этому прогону, выше стоп-порога наряда ~25%. Записанные строки
> не тронуты. Разбивка отказов по типам проверок (equality/dedup+suppression/no-op/circular)
> валидатором не сохраняется по ни одному дистрактору — не восстановима постфактум; см. таксономию
> в `EnrichmentValidator::validDistractors()`.

### rent a house

- **перевод (промпт):** арендовать дом
- **эталон-пример:** We want to rent a house near the city center.
- **дистракторы:**
    - `We want to rent a house about the city center.` — preposition: **about the city center** → `near the city center`
    - `We wants to rent a house near the city center.` — tense: **We wants** → `We want`
    - `We want to rent house near the city center.` — article: **rent house** → `rent a house`

### monthly rent

- **перевод (промпт):** ежемесячная арендная плата
- **эталон-пример:** The monthly rent must be paid on the first of each month.
- **принимаемые варианты:**
    - `monthly rental fee` — _похожее значение, но с другим словом._
- **дистракторы:**
    - `The monthly rent must be paid at the first of each month.` — preposition: **at the first** → `on the first`
    - `The monthly rent must be paid on first of each month.` — article: **on first** → `on the first`
    - `The monthly rent must paid on the first of each month.` — tense: **must paid** → `must be paid`

### utilities included

- **перевод (промпт):** коммунальные платежи включены
- **эталон-пример:** Are utilities included in the rent?
- **принимаемые варианты:**
    - `utilities are included` — _взаимозаменяемый вариант_
- **дистракторы:**
    - `Are utilities include in the rent?` — tense: **include** → `included`
    - `Are utilities includes in the rent?` — tense: **includes** → `included`

### sign a lease

- **перевод (промпт):** подписать договор аренды
- **эталон-пример:** You need to sign a lease before moving in.
- **принимаемые варианты:**
    - `enter into a lease` — _Синонимы._
    - `sign a rental agreement` — _Синонимы._
- **дистракторы:**
    - `You need to sign the lease before moving in.` — article: **the lease** → `a lease`
    - `You need to sign a lease after moving in.` — tense: **after moving in** → `before moving in`

### security deposit

- **перевод (промпт):** залог
- **эталон-пример:** You'll need to pay a security deposit before moving in.
- **принимаемые варианты:**
    - `deposit` — _синоним к слову "залог"._
- **дистракторы:**
    - `You'll need to pay security deposit before moving in.` — article: **security deposit** → `a security deposit`
    - `You'll need to pay a security deposit for moving in.` — preposition: **for** → `before`

### pay on time

- **перевод (промпт):** платить вовремя
- **эталон-пример:** It's important to pay the rent on time every month.
- **флаги:**
    - ✍️ **не слово → правка** — Слово 'вовремя' может быть предпочтительнее 'своевременно' в данном контексте для более точного понимания.

### late fee

- **перевод (промпт):** пени за просрочку
- **эталон-пример:** There is a late fee for overdue rent payments.
- **дистракторы:**
    - `There is late fee for overdue rent payments.` — article: **late fee** → `a late fee`

### water bill

- **перевод (промпт):** счет за воду
- **эталон-пример:** The water bill comes every two months.
- **дистракторы:**
    - `The water bill come every two months.` — tense: **come** → `comes`

### electricity bill

- **перевод (промпт):** счет за электричество
- **эталон-пример:** The electricity bill is separate from the rent.
- **дистракторы:**
    - `The electricity bill is the separate from the rent.` — article: **the separate** → `separate`
    - `The electricity bill are separate from the rent.` — tense: **are** → `is`

### gas bill

- **перевод (промпт):** счет за газ
- **эталон-пример:** We need to budget for the gas bill this winter.
- **дистракторы:**
    - `We need to budget for gas bill this winter.` — article: **gas bill** → `the gas bill`
    - `We need to budget for the gas bills this winter.` — article: **the gas bills** → `the gas bill`

### negotiate rent

- **перевод (промпт):** договариваться о размере арендной платы
- **эталон-пример:** Be prepared to negotiate rent with the landlord.
- **дистракторы:**
    - `Be prepared to negotiate rent for the landlord.` — preposition: **for** → `with`
- **флаги:**
    - ✍️ **не слово → правка** — арендной платы; correct form is арендной плате.

### terms and conditions

- **перевод (промпт):** условия и положения
- **эталон-пример:** I need to read the terms and conditions before opening an account.
- **дистракторы:**
    - `I need to read the the terms and conditions before opening an account.` — article: **the terms** → `terms`
- **флаги:**
    - ✍️ **не слово → правка** — положения, положения

### maintenance

- **перевод (промпт):** обслуживание
- **эталон-пример:** Who is responsible for maintenance?
- **дистракторы:**
    - `Who is responsible for the maintenance?` — article: **the maintenance** → `maintenance`

### landlord

- **перевод (промпт):** арендодатель
- **эталон-пример:** The landlord will show you around the apartment.
- **дистракторы:**
    - `The landlord show you around the apartment.` — tense: **show** → `will show`
    - `The landlord will show around you the apartment.` — word_order: **around you** → `you around`

### tenant

- **перевод (промпт):** арендатор, жилец
- **эталон-пример:** The tenant is responsible for minor repairs.
- **принимаемые варианты:**
    - `lessee` — _синоним слова 'арендатор'._
    - `renter` — _то же значение_
- **дистракторы:**
    - `The tenant are responsible for minor repairs.` — tense: **are** → `is`
    - `The tenant is responsible on minor repairs.` — preposition: **on** → `for`

### fixed-term lease

- **перевод (промпт):** договор аренды на фиксированный срок
- **эталон-пример:** We signed a fixed-term lease for one year.
- **дистракторы:**
    - `We signed a fixed-term lease on one year.` — preposition: **on one year** → `for one year`
    - `We sign a fixed-term lease for one year.` — tense: **sign** → `signed`
    - `We signed fixed-term lease for one year.` — article: **fixed-term lease** → `a fixed-term lease`

### extension

- **перевод (промпт):** продление
- **эталон-пример:** We would like to discuss an extension of the lease.
- **дистракторы:**
    - `We would like to discuss the extension of the lease.` — article: **the** → `an`

### break the lease

- **перевод (промпт):** нарушить договор аренды
- **эталон-пример:** If you break the lease, you may lose your deposit.
- **дистракторы:**
    - `If you break lease, you may lose your deposit.` — article: **break lease** → `break the lease`

### pay a fee

- **перевод (промпт):** оплачивать сбор
- **эталон-пример:** You have to pay a fee for early termination of the lease.
- **дистракторы:**
    - `You have to pay the fee for early termination of the lease.` — article: **the fee** → `a fee`

### fixed utilities

- **перевод (промпт):** фиксированные коммунальные платежи
- **эталон-пример:** The apartment comes with fixed utilities per month.
- **дистракторы:**
    - `The apartment comes with fixed utilities in month.` — preposition: **in month** → `per month`
    - `The apartment come with fixed utilities per month.` — tense: **The apartment come** → `The apartment comes`
    - `The apartment comes with the fixed utilities per month.` — article: **the fixed utilities** → `fixed utilities`

### sublet

- **перевод (промпт):** сдавать в субаренду
- **эталон-пример:** Do you have permission to sublet the apartment?
- **принимаемые варианты:**
    - `sublease` — _синоним слова "субаренда"._
    - `lease out` — _Синонимично с терминами 'sublet' и 'sublease'._
- **дистракторы:**
    - `Do you have a permission to sublet the apartment?` — article: **a permission** → `permission`
    - `Do you have permission for sublet the apartment?` — preposition: **for sublet** → `to sublet`
    - `Do you has permission to sublet the apartment?` — tense: **has** → `have`

### check in

- **перевод (промпт):** заселяться
- **эталон-пример:** We can check in after 2 PM.
- **дистракторы:**
    - `We can the check in after 2 PM.` — article: **the check in** → `check in`
    - `We can check after 2 PM.` — modal_to: **check** → `check in`
- **флаги:**
    - ✍️ **не слово → правка** — заселяться — заселяться is not a valid English phrase.

## Clothing and Shoe Shopping

### Do you have this in my size?

- **перевод (промпт):** Есть ли это в моём размере?
- **эталон-пример:** Do you have this in my size?
- **принимаемые варианты:**
    - `Do you have this in the correct size?` — _Синоним выражения, выражающего тот же смысл._
    - `Is this available in my size?` — _Эквивалентное выражение._
- **дистракторы:**
    - `Do you have this in the size?` — article: **the size** → `my size`

### I'm looking for shoes.

- **перевод (промпт):** Я ищу обувь.
- **эталон-пример:** I'm looking for shoes that are comfortable.
- **дистракторы:**
    - `I'm looking for shoes that is comfortable.` — tense: **that is** → `that are`

### Can I try this on?

- **перевод (промпт):** Могу я это примерить?
- **эталон-пример:** Can I try this on in the fitting room?
- **дистракторы:**
    - `Can I try this on in fitting room?` — preposition: **in** → `in the`

### What size are you?

- **перевод (промпт):** Какой у вас размер?
- **эталон-пример:** What size are you looking for?
- **дистракторы:**
    - `What size you are looking for?` — tense: **you are** → `are you`
    - `What size are you looking to?` — preposition: **looking to** → `looking for`

### These are too tight.

- **перевод (промпт):** Это слишком тесно.
- **эталон-пример:** These shoes are too tight for me.
- **принимаемые варианты:**
    - `These are too small.` — _Вариант с другим значением размера._
    - `They are too tight.` — _Замена местоимения на их._
    - `These fit too tightly.` — _Другой способ выразить информацию о размере._
    - `Those are too tight.` — _Другой указательный местоимение._
- **дистракторы:**
    - `These shoes are too the tight for me.` — article: **the tight** → `tight`
    - `These shoes is too tight for me.` — tense: **is** → `are`

### in fashion

- **перевод (промпт):** в моде
- **эталон-пример:** These jackets are in fashion this season.
- **дистракторы:**
    - `These jackets are in the fashion this season.` — article: **the fashion** → `fashion`

### price tag

- **перевод (промпт):** ценник
- **эталон-пример:** I can't find the price tag on this shirt.
- **дистракторы:**
    - `I can't find price tag on this shirt.` — article: **price tag** → `the price tag`

### on sale

- **перевод (промпт):** на распродаже
- **эталон-пример:** These apples are on sale today.
- **дистракторы:**
    - `These apples are in sale today.` — preposition: **in sale** → `on sale`

### browse

- **перевод (промпт):** просматривать (товары)
- **эталон-пример:** I'm just browsing, thank you.
- **принимаемые варианты:**
    - `looking through` — _Синоним._
    - `perusing` — _Синоним._
    - `shopping around` — _Синоним._
- **дистракторы:**
    - `I'm just browse, thank you.` — tense: **browse** → `browsing`

### checkout

- **перевод (промпт):** касса
- **эталон-пример:** You can pay at the checkout.
- **дистракторы:**
    - `You can pay in the checkout.` — preposition: **in the checkout** → `at the checkout`

### return policy

- **перевод (промпт):** политика возврата
- **эталон-пример:** Please check the return policy before buying.
- **принимаемые варианты:**
    - `policy of returns` — _Эквивалентная формулировка._
    - `refund policy` — _Эквивалентная формулировка._
- **дистракторы:**
    - `Please check return policy before buying.` — article: **return policy** → `the return policy`
    - `Please check the return policies before buying.` — tense: **policies** → `policy`

### fitting room

- **перевод (промпт):** примерочная
- **эталон-пример:** The fitting room is at the back of the store.
- **принимаемые варианты:**
    - `changing room` — _Синоним._
    - `dressing room` — _Синоним._
- **дистракторы:**
    - `The fitting room are at the back of the store.` — tense: **are** → `is`
    - `The fitting room is in the back of the store.` — preposition: **in** → `at`

### too expensive

- **перевод (промпт):** слишком дорого
- **эталон-пример:** This jacket is too expensive for my budget.
- **дистракторы:**
    - `This jacket is too expensive in my budget.` — preposition: **in** → `for`
    - `This jacket are too expensive for my budget.` — tense: **are** → `is`
    - `This jacket is too expensive for budget.` — article: **for budget** → `for my budget`

### Can I get a refund?

- **перевод (промпт):** Могу я получить возврат?
- **эталон-пример:** Can I get a refund if it doesn't fit?
- **дистракторы:**
    - `Can I get refund if it doesn't fit?` — article: **refund** → `a refund`

### I'd like to buy this.

- **перевод (промпт):** Я хотел бы купить это.
- **эталон-пример:** I'd like to buy this dress, please.
- **дистракторы:**
    - `I'd like to buy the this dress, please.` — article: **the this** → `this`
    - `I like to buy this dress, please.` — tense: **I like** → `I'd like`
    - `I'd like to buy dress this, please.` — word_order: **dress this** → `this dress`

### May I have a discount?

- **перевод (промпт):** Могу я получить скидку?
- **эталон-пример:** May I have a discount if I buy two?
- **дистракторы:**
    - `May I have discount if I buy two?` — article: **discount** → `a discount`

### try on

- **перевод (промпт):** примерять
- **эталон-пример:** You should try on the jacket before buying it.
- **принимаемые варианты:**
    - `test out` — _Альтернативное выражение для 'примерять'._
    - `fit` — _Еще один способ сказать 'примерять'._
- **дистракторы:**
    - `You should try the jacket on before buying it.` — word_order: **the jacket on** → `on the jacket`

### customer service

- **перевод (промпт):** обслуживание клиентов
- **эталон-пример:** Their customer service is really friendly and attentive.
- **дистракторы:**
    - `Their customer service are really friendly and attentive.` — tense: **are** → `is`
    - `Their customer service is really the friendly and attentive.` — article: **the friendly** → `friendly`

### same-day delivery

- **перевод (промпт):** доставка в тот же день
- **эталон-пример:** We offer same-day delivery for your online orders.
- **дистракторы:**
    - `We offer same-day deliveries for your online orders.` — tense: **deliveries** → `delivery`
    - `We offer same-day delivery in your online orders.` — preposition: **in** → `for`

### How much does it cost?

- **перевод (промпт):** Сколько это стоит?
- **эталон-пример:** How much does this dress cost?
- **дистракторы:**
    - `How much does dress this cost?` — word_order: **dress this** → `this dress`

### shopping assistant

- **перевод (промпт):** продавец-консультант
- **эталон-пример:** The shopping assistant helped me find my size.
- **дистракторы:**
    - `The shopping assistant help me find my size.` — tense: **help** → `helped`

## Fictional Message Translation

### encrypted text

- **перевод (промпт):** зашифрованный текст
- **эталон-пример:** The document contains encrypted text that only he can read.
- **принимаемые варианты:**
    - `ciphered text` — _Синоним, также означающий зашифрованный текст._
- **дистракторы:**
    - `The document contains encrypted the text that only he can read.` — article: **encrypted the text** → `encrypted text`
    - `The document contains encrypted text that only he read.` — tense: **he read** → `he can read`

### cipher

- **перевод (промпт):** шифр
- **эталон-пример:** They used a simple cipher to conceal the true meaning.
- **дистракторы:**
    - `They used simple cipher to conceal the true meaning.` — article: **simple cipher** → `a simple cipher`
    - `They use a simple cipher to conceal the true meaning.` — tense: **use** → `used`
    - `They used a simple cipher for conceal the true meaning.` — preposition: **for conceal** → `to conceal`

### break the code

- **перевод (промпт):** разгадать шифр
- **эталон-пример:** The detective was able to break the code and solve the case.
- **дистракторы:**
    - `The detective was able to break code and solve the case.` — article: **break code** → `break the code`

### cryptic message

- **перевод (промпт):** загадочное сообщение
- **эталон-пример:** She found a cryptic message hidden in the book.
- **дистракторы:**
    - `She found a cryptic message hidden at the book.` — preposition: **hidden at the book** → `hidden in the book`
    - `She found cryptic message hidden in the book.` — article: **cryptic message** → `a cryptic message`

### hidden meaning

- **перевод (промпт):** скрытый смысл
- **эталон-пример:** There might be a hidden meaning behind his words.
- **дистракторы:**
    - `There might be the hidden meaning behind his words.` — article: **the hidden meaning** → `a hidden meaning`

### decrypt

- **перевод (промпт):** расшифровать
- **эталон-пример:** You need a special key to decrypt the data.
- **принимаемые варианты:**
    - `decode` — _Синоним._
    - `uncover` — _Синоним в контексте расшифровки._
- **дистракторы:**
    - `You need a special key for decrypt the data.` — preposition: **for decrypt** → `to decrypt`

### obliged

- **перевод (промпт):** обязанный
- **эталон-пример:** He felt obliged to help his friend with the problem.
- **дистракторы:**
    - `He felt obliged for help his friend with the problem.` — preposition: **for help** → `to help`
    - `He feel obliged to help his friend with the problem.` — tense: **feel** → `felt`

### must have been

- **перевод (промпт):** должно быть
- **эталон-пример:** It must have been a lot of work to create this encryption.
- **дистракторы:**
    - `It must have been the lot of work to create this encryption.` — article: **the lot** → `a lot`
    - `It must have be a lot of work to create this encryption.` — tense: **have be** → `have been`

### interpret

- **перевод (промпт):** интерпретировать
- **эталон-пример:** It's hard to interpret the true meaning of the note.
- **дистракторы:**
    - `It's hard to interpreting the true meaning of the note.` — tense: **interpreting** → `interpret`
    - `It's hard to interpret the meaning true of the note.` — word_order: **meaning true** → `true meaning`
    - `It's hard to interpret true the meaning of the note.` — word_order: **true the** → `the true`

### solve the riddle

- **перевод (промпт):** разгадать загадку
- **эталон-пример:** She tried to solve the riddle but found it too complex.
- **дистракторы:**
    - `She tried to solve riddle but found it too complex.` — article: **solve riddle** → `solve the riddle`
    - `She tried to solve the riddle but found it too complexities.` — tense: **too complexities** → `too complex`

### unscramble

- **перевод (промпт):** распутать
- **эталон-пример:** He tried to unscramble the mixed-up letters.
- **дистракторы:**
    - `He tried to unscramble mixed-up letters.` — word_order: **mixed-up** → `the mixed-up`

### crack the code

- **перевод (промпт):** взломать код
- **эталон-пример:** After hours of trying, they finally managed to crack the code.
- **дистракторы:**
    - `After hours of trying, they finally manages to crack the code.` — tense: **manages** → `managed`

### message in disguise

- **перевод (промпт):** сообщение под прикрытием
- **эталон-пример:** It took a keen eye to notice the message in disguise hidden in the text.
- **дистракторы:**
    - `It took keen eye to notice the message in disguise hidden in the text.` — article: **keen eye** → `a keen eye`

### conundrum

- **перевод (промпт):** головоломка
- **эталон-пример:** The conundrum was challenging, but eventually he found a solution.
- **дистракторы:**
    - `The conundrum was challenging, but eventually he found solution.` — article: **solution** → `a solution`
    - `The conundrum were challenging, but eventually he found a solution.` — tense: **were** → `was`

## Visiting the Vet with a Dog

### anal gland cleaning

- **перевод (промпт):** чистка параанальных желез
- **эталон-пример:** The vet recommended an anal gland cleaning for our dog.
- **дистракторы:**
    - `The vet recommended an anal gland cleanings for our dog.` — tense: **cleanings** → `cleaning`
    - `The vet recommended an the anal gland cleaning for our dog.` — article: **an the** → `an`
    - `The vet recommended an anal gland cleaning to our dog.` — preposition: **to** → `for`

### scooting

- **перевод (промпт):** трение о землю
- **эталон-пример:** The vet noticed the dog scooting and recommended a check-up.
- **дистракторы:**
    - `The vet noticed a dog scooting and recommended a check-up.` — article: **a dog** → `the dog`
    - `The vet noticed the dog scooting and recommend a check-up.` — tense: **recommend** → `recommended`

### gland expression

- **перевод (промпт):** выдавливание желез
- **эталон-пример:** The groomer can perform gland expression if needed.
- **дистракторы:**
    - `The groomer can perform the gland expression if needed.` — article: **the gland expression** → `gland expression`

### rectal examination

- **перевод (промпт):** ректальное обследование
- **эталон-пример:** A rectal examination may be necessary to diagnose the issue.
- **дистракторы:**
    - `A rectal examination may be necessaries to diagnose the issue.` — tense: **necessaries** → `necessary`
    - `A rectal examinations may be necessary to diagnose the issue.` — tense: **examinations** → `examination`

### vet technician

- **перевод (промпт):** ветеринарный техник
- **эталон-пример:** The vet technician will assist during the procedure.
- **принимаемые варианты:**
    - `veterinary technician` — _Синоним термина._
- **дистракторы:**
    - `The vet technician will assists during the procedure.` — tense: **assists** → `assist`

### discomfort

- **перевод (промпт):** дискомфорт
- **эталон-пример:** The dog seems to be in some discomfort.
- **дистракторы:**
    - `The dog seem to be in some discomfort.` — tense: **seem** → `seems`

### We have an appointment at two.

- **перевод (промпт):** У нас запись на два часа.
- **эталон-пример:** We have an appointment at two for the dog's consultation.
- **дистракторы:**
    - `We has an appointment at two for the dog's consultation.` — tense: **has** → `have`
    - `We have appointment at two for the dog's consultation.` — article: **appointment** → `an appointment`

### diagnose

- **перевод (промпт):** диагностировать
- **эталон-пример:** The vet will diagnose what's causing the discomfort.
- **дистракторы:**
    - `The vet will diagnose what causing the discomfort.` — word_order: **what** → `what's`
    - `The vet will diagnosed what's causing the discomfort.` — tense: **diagnosed** → `diagnose`

### anal glands

- **перевод (промпт):** анальные железы
- **эталон-пример:** The vet checked the dog's anal glands to see if they needed cleaning.
- **дистракторы:**
    - `The vet checked the dog's anal glands for see if they needed cleaning.` — preposition: **for see** → `to see`
    - `The vet checked the dog anal glands to see if they needed cleaning.` — article: **the dog anal glands** → `the dog's anal glands`

### my dog keeps licking the area

- **перевод (промпт):** моя собака постоянно лижет область
- **эталон-пример:** My dog keeps licking the area and seems uncomfortable.
- **принимаемые варианты:**
    - `my dog is always licking the area` — _это эквивалентно выражению._
    - `my dog constantly licks the area` — _это эквивалентно выражению._
- **дистракторы:**
    - `My dog keep licking the area and seems uncomfortable.` — tense: **keep** → `keeps`

### could you check his glands?

- **перевод (промпт):** не могли бы вы проверить его железы?
- **эталон-пример:** Could you check his glands to see if they need to be expressed?
- **дистракторы:**
    - `Could you check his glands to see if they needs to be expressed?` — tense: **needs** → `need`

### show signs of irritation

- **перевод (промпт):** показывать признаки раздражения
- **эталон-пример:** Her dog shows signs of irritation around the anal area.
- **дистракторы:**
    - `Her dog shows signs of irritation at the anal area.` — preposition: **at the anal area** → `around the anal area`
    - `Her dog show signs of irritation around the anal area.` — tense: **show** → `shows`

### anal area

- **перевод (промпт):** область ануса
- **эталон-пример:** The vet examined the anal area for any swelling.
- **дистракторы:**
    - `The vet examined the anal area for any swellings.` — tense: **swellings** → `swelling`

### frequent licking

- **перевод (промпт):** частое лизание
- **эталон-пример:** Frequent licking can be a sign that a dog's glands are blocked.
- **дистракторы:**
    - `Frequent licking can be signs that a dog's glands are blocked.` — tense: **signs** → `a sign`

## Basic Conversation with Strangers

> ⚠️ **Приоритетная вычитка.** Бэкфилл 2026-08-13 дал здесь 28.1% брака дистракторов (9 из 32
> предложено) — точное совпадение с «Discussing Long-term House Rental» по этому же прогону,
> проверено SQL и сырыми ответами OpenAI как честное совпадение, не баг счётчика. Выше стоп-порога
> наряда ~25%. Записанные строки не тронуты. Разбивка по типам проверок не восстановима постфактум
> по той же причине — см. «Discussing Long-term House Rental» выше.

### Could you repeat that?

- **перевод (промпт):** Повторите, пожалуйста?
- **эталон-пример:** Could you repeat that last part?
- **дистракторы:**
    - `Could you repeat that last parts?` — tense: **parts** → `part`

### Sure

- **перевод (промпт):** Конечно
- **эталон-пример:** Sure, I can help you with that.
- **дистракторы:**
    - `Sure, I can help to you with that.` — modal_to: **to you** → `you`
    - `Sure, I can helps you with that.` — tense: **helps** → `help`

### What a lovely day!

- **перевод (промпт):** Какой чудесный день!
- **эталон-пример:** What a lovely day! The sun is shining.
- **дистракторы:**
    - `What a lovely day! The sun are shining.` — tense: **are** → `is`

### Take care

- **перевод (промпт):** Берегите себя
- **эталон-пример:** Goodbye, take care!
- **дистракторы:**
    - `Goodbye, take cares!` — tense: **take cares** → `take care`
    - `Goodbye, take care of!` — preposition: **take care of** → `take care`

### Have a great day

- **перевод (промпт):** Хорошего дня
- **эталон-пример:** I hope you have a great day today!
- **дистракторы:**
    - `I hope you have great day today!` — article: **great day** → `a great day`

### This is my friend

- **перевод (промпт):** Это мой друг
- **эталон-пример:** This is my friend, Michael.
- **дистракторы:**
    - `This is friend, Michael.` — article: **friend** → `my friend`
    - `This is my friend, наichael.` — false_friend: **наichael** → `Michael.`

### May I join you?

- **перевод (промпт):** Можно присоединиться?
- **эталон-пример:** May I join you for lunch?
- **дистракторы:**
    - `May I join with you for lunch?` — preposition: **join with** → `join`
    - `May I joined you for lunch?` — tense: **joined** → `join`
    - `May I join you for the lunch?` — article: **the lunch** → `lunch`

### How do you spell that?

- **перевод (промпт):** Как это пишется?
- **эталон-пример:** How do you spell your name again?
- **принимаемые варианты:**
    - `How is that spelled?` — _Это синонимично._
    - `How do you write that?` — _Это тоже правильно._

### What are your hobbies?

- **перевод (промпт):** Какие у вас хобби?
- **эталон-пример:** What are your hobbies in your free time?
- **дистракторы:**
    - `What is your hobbies in your free time?` — tense: **is** → `are`
    - `What are your hobbies the in your free time?` — article: **the in** → `in`
    - `What are your hobbies at your free time?` — preposition: **at** → `in`

### Have we met before?

- **перевод (промпт):** Мы встречались раньше?
- **эталон-пример:** Have we met before? You look familiar.
- **дистракторы:**
    - `Have we meet before? You look familiar.` — tense: **meet** → `met`

### It's a pleasure to meet you

- **перевод (промпт):** Рад встрече с вами
- **эталон-пример:** It's a pleasure to meet you, Karen.
- **дистракторы:**
    - `It's a pleasure meeting you, Karen.` — tense: **meeting** → `to meet`
    - `It's pleasure to meet you, Karen.` — article: **pleasure** → `a pleasure`

### What's new with you?

- **перевод (промпт):** Что у вас нового?
- **эталон-пример:** Hey, what's new with you these days?
- **дистракторы:**
    - `Hey, what's new with you these day?` — tense: **these day** → `these days`

### I look forward to it

- **перевод (промпт):** Я с нетерпением жду этого
- **эталон-пример:** I look forward to our meeting next week.
- **дистракторы:**
    - `I look forward to the meeting next week.` — article: **the meeting** → `our meeting`

### How are you doing?

- **перевод (промпт):** Как у вас дела?
- **эталон-пример:** How are you doing today?
- **дистракторы:**
    - `How are you do today?` — tense: **do** → `doing`

### Nice to meet you

- **перевод (промпт):** Приятно познакомиться
- **эталон-пример:** Nice to meet you, I'm Sarah.
- **принимаемые варианты:**
    - `Pleased to meet you` — _Синонимичное выражение._
    - `Good to meet you` — _Синонимичное выражение._
- **дистракторы:**
    - `Nice meet you, I'm Sarah.` — tense: **meet** → `to meet`

### Where are you from?

- **перевод (промпт):** Откуда вы?
- **эталон-пример:** Where are you from? I'm from Canada.
- **дистракторы:**
    - `Where are you from? I'm from the Canada.` — article: **the Canada** → `Canada`

### Excuse me

- **перевод (промпт):** Извините
- **эталон-пример:** Excuse me, could you tell me the time?
- **дистракторы:**
    - `Excuse me, could you tells me the time?` — tense: **tells** → `tell`

### Can I help you with that?

- **перевод (промпт):** Могу я вам с этим помочь?
- **эталон-пример:** Can I help you with that heavy bag?
- **дистракторы:**
    - `Can I help you with the heavy bag?` — article: **the** → `that`

## First Day at a New Company

### introduce yourself

- **перевод (промпт):** представляться
- **эталон-пример:** On your first day, introduce yourself to your new coworkers.
- **дистракторы:**
    - `On your first day, you introduce yourself to your new coworkers.` — tense: **day, you** → `day,`

### orientation session

- **перевод (промпт):** вводный инструктаж
- **эталон-пример:** We have an orientation session at 10 a.m.
- **дистракторы:**
    - `We have orientation session at 10 a.m.` — article: **orientation session** → `an orientation session`
    - `We have an orientation session in 10 a.m.` — preposition: **in 10 a.m.** → `at 10 a.m.`

### meet the team

- **перевод (промпт):** встретиться с командой
- **эталон-пример:** You'll meet the team after lunch.
- **принимаемые варианты:**
    - `see the team` — _увидеть команду_
    - `greet the team` — _поздороваться с командой_

### get to know the office

- **перевод (промпт):** изучить офис
- **эталон-пример:** Take some time to get to know the office layout.
- **принимаемые варианты:**
    - `familiarize yourself with the office` — _Синонимичная фраза._
    - `get acquainted with the office` — _Синонимичная фраза._
- **дистракторы:**
    - `Take some time to get know the office layout.` — modal_to: **get know** → `get to know`

### workstation

- **перевод (промпт):** рабочее место
- **эталон-пример:** Your workstation is ready for you.
- **дистракторы:**
    - `Your workstation is ready for she.` — word_order: **she** → `you`
    - `Your workstation are ready for you.` — tense: **are** → `is`
    - `Your workstation is ready for the you.` — article: **the you** → `you`

### colleague

- **перевод (промпт):** колледка
- **эталон-пример:** You'll find your colleagues very helpful.
- **дистракторы:**
    - `You'll find a colleagues very helpful.` — article: **a** → `your`
    - `You'll find your colleagues to be very helpful.` — modal_to: **colleagues to be** → `colleagues`

### break room

- **перевод (промпт):** комната отдыха
- **эталон-пример:** You can relax in the break room during lunch.
- **дистракторы:**
    - `You can relax in the break room during lunches.` — tense: **during lunches** → `during lunch`
    - `You can relax in break room during lunch.` — article: **break room** → `the break room`

### office culture

- **перевод (промпт):** культура офиса
- **эталон-пример:** It's important to understand the office culture from day one.
- **дистракторы:**
    - `It's important to understand a office culture from day one.` — article: **a office culture** → `the office culture`
    - `It's important to understand the office culture since day one.` — preposition: **since day one** → `from day one`

### show the ropes

- **перевод (промпт):** ввести в курс дела
- **эталон-пример:** My manager will show me the ropes today.
- **принимаемые варианты:**
    - `introduce to the ropes` — _синонимный вариант_
    - `teach the ropes` — _синонимный вариант_

### lunch break

- **перевод (промпт):** обеденный перерыв
- **эталон-пример:** We have a one-hour lunch break at noon.
- **дистракторы:**
    - `We have a one-hour lunch break in noon.` — preposition: **in noon** → `at noon`
    - `We had a one-hour lunch break at noon.` — tense: **had** → `have`
    - `We have one-hour lunch break at noon.` — article: **one-hour** → `a one-hour`

### get settled in

- **перевод (промпт):** обустроиться
- **эталон-пример:** Take your time to get settled in your new workspace.
- **дистракторы:**
    - `Take your time to get settled at your new workspace.` — preposition: **settled at** → `settled in`
    - `Take your time to getting settled in your new workspace.` — modal_to: **to getting settled** → `to get settled`
    - `Take your time to get settled on your new workspace.` — preposition: **settled on** → `settled in`

### company policies

- **перевод (промпт):** политика компании
- **эталон-пример:** Please familiarize yourself with the company policies.
- **дистракторы:**
    - `Please familiarize yourself with the policies company.` — word_order: **the policies company** → `the company policies`
    - `Please familiarize yourself with company policies.` — article: **company policies** → `the company policies`

## Essential Travel Phrases in English

### Could you show me the way?

- **перевод (промпт):** Не могли бы вы показать мне дорогу?
- **эталон-пример:** Could you show me the way to the nearest bus stop?
- **дистракторы:**
    - `Could you show me the way at the nearest bus stop?` — preposition: **at the nearest bus stop** → `to the nearest bus stop`
- **флаги:**
    - 🌐 **не тот язык** — Не могли бы вы показать мне дорогу?

### How much does it cost?

- **перевод (промпт):** Сколько это стоит?
- **эталон-пример:** How much does this dress cost?
- **дистракторы:**
    - `How much does dress this cost?` — word_order: **dress this** → `this dress`

### Where is the nearest ATM?

- **перевод (промпт):** Где находится ближайший банкомат?
- **эталон-пример:** Can you tell me where the nearest ATM is?
- **дистракторы:**
    - `Can you tell me where the nearest ATM are?` — tense: **are** → `is`
    - `Can you tell me where at the nearest ATM is?` — preposition: **at the nearest ATM** → `the nearest ATM`

### I'd like to book a table for two.

- **перевод (промпт):** Я бы хотела забронировать столик на двоих.
- **эталон-пример:** I'd like to book a table for two at 7 p.m.
- **дистракторы:**
    - `I'd like to book a table for two in 7 p.m.` — preposition: **in 7 p.m.** → `at 7 p.m.`
    - `I'd like to book table for two at 7 p.m.` — article: **book table** → `book a table`

### Can I have the check, please?

- **перевод (промпт):** Можно счет, пожалуйста?
- **эталон-пример:** After finishing dinner, she asked, 'Can I have the check, please?'
- **дистракторы:**
    - `After finishing dinner, she asked, 'Can I have the check for, please?'` — preposition: **the check for, please?** → `the check, please?`
    - `After finishing dinner, she asked, 'Can I have check, please?'` — article: **'Can I have check, please?'** → `'Can I have the check, please?'`

### Do you have Wi-Fi here?

- **перевод (промпт):** У вас здесь есть Wi-Fi?
- **эталон-пример:** Excuse me, do you have Wi-Fi here?
- **дистракторы:**
    - `Excuse me, do you have the Wi-Fi here?` — article: **the Wi-Fi** → `Wi-Fi`
    - `Excuse me, do you has Wi-Fi here?` — tense: **do you has** → `do you have`
    - `Excuse me, do you have Wi-Fi in here?` — preposition: **in here** → `here`

### Is breakfast included?

- **перевод (промпт):** Завтрак включен?
- **эталон-пример:** Is breakfast included in the room price?
- **дистракторы:**
    - `Is breakfast included at the room price?` — preposition: **at the room price** → `in the room price`
    - `Is breakfast included in room price?` — article: **in room price** → `in the room price`

### Could you take a photo of us?

- **перевод (промпт):** Не могли бы вы нас сфотографировать?
- **эталон-пример:** Could you take a photo of us in front of the monument?
- **дистракторы:**
    - `Could you takes a photo of us in front of the monument?` — tense: **takes** → `take`
    - `Could you take photo of us in front of the monument?` — article: **photo** → `a photo`
    - `Could you take a photo of us in front the monument?` — preposition: **in front the monument** → `in front of the monument`

### I'd like to rent a car.

- **перевод (промпт):** Я бы хотел арендовать машину.
- **эталон-пример:** I'd like to rent a car for the weekend.
- **принимаемые варианты:**
    - `I want to rent a car.` — _Синоним с похожим значением._
- **дистракторы:**
    - `I'd like to rent car for the weekend.` — article: **rent car** → `rent a car`
    - `I'd like to rent a car in the weekend.` — preposition: **in the weekend** → `for the weekend`
- **флаги:**
    - ✍️ **не слово → правка** — Выражение "арендовать" корректно, но в некоторых контекстах используется "брать".

### Is this seat taken?

- **перевод (промпт):** Это место занято?
- **эталон-пример:** Excuse me, is this seat taken?
- **дистракторы:**
    - `Excuse me, is this seat take?` — tense: **take** → `taken`
    - `Excuse me, is this seats taken?` — tense: **seats** → `seat`

### Can you help me with my luggage?

- **перевод (промпт):** Можете помочь мне с багажом?
- **эталон-пример:** At the airport, I asked the porter, 'Can you help me with my luggage?'
- **дистракторы:**
    - `At the airport, I asked the porter, 'Can you help me with luggage?'` — article: **with luggage** → `with my luggage`
    - `At the airport, I asked the porter, 'Can you helped me with my luggage?'` — tense: **can you helped** → `can you help`
    - `At the airport, I asked the porter, 'Can you help me in my luggage?'` — preposition: **in my luggage** → `with my luggage`

### hotels

- **перевод (промпт):** гостиницы
- **эталон-пример:** There are many hotels in this area.
- **дистракторы:**
    - `There is many hotels in this area.` — tense: **is** → `are`
    - `There are much hotels in this area.` — word_order: **much hotels** → `many hotels`

### souvenirs

- **перевод (промпт):** сувениры
- **эталон-пример:** I bought some souvenirs from the local market.
- **дистракторы:**
    - `I bought some souvenirs in the local market.` — preposition: **in the local market** → `from the local market`
    - `I bought a souvenirs from the local market.` — article: **a souvenirs** → `some souvenirs`

### Could you speak a bit slower?

- **перевод (промпт):** Не могли бы вы говорить немного медленнее?
- **эталон-пример:** Sorry, could you speak a bit slower?
- **дистракторы:**
    - `Sorry, could you speak a bit slow?` — tense: **speak a bit slow** → `speak a bit slower`
    - `Sorry, could you speak bit slower?` — article: **speak bit slower** → `speak a bit slower`

### What's the exchange rate?

- **перевод (промпт):** Какой курс обмена?
- **эталон-пример:** What's the exchange rate today?
- **дистракторы:**
    - `What's the exchange rate at today?` — preposition: **at today** → `today`
    - `What's exchange rate today?` — article: **exchange rate** → `the exchange rate`

### Could you call a taxi for me?

- **перевод (промпт):** Не могли бы вы вызвать для меня такси?
- **эталон-пример:** Could you call a taxi for me to the airport?
- **принимаемые варианты:**
    - `Can you call a taxi for me?` — _Значит то же самое._
    - `Would you mind calling a taxi for me?` — _Значит то же самое._
- **дистракторы:**
    - `Could you call a taxi to me for the airport?` — preposition: **to me for the airport** → `for me to the airport`
    - `Could you call taxi for me to the airport?` — article: **call taxi** → `call a taxi`
- **флаги:**
    - 🌐 **не тот язык** — Поле на английском, должно быть на русском.

### I'll have the same, please.

- **перевод (промпт):** Мне то же самое, пожалуйста.
- **эталон-пример:** I'll have the same as her, please.
- **дистракторы:**
    - `I'll have same as her, please.` — article: **same** → `the same`
    - `I'll have the same to her, please.` — preposition: **to** → `as`

### Can I get a receipt, please?

- **перевод (промпт):** Можно получить чек, пожалуйста?
- **эталон-пример:** Can I get a receipt, please, for my records?
- **принимаемые варианты:**
    - `Could I get a receipt, please?` — _Это эквивалентная форма с другим модальным глаголом._
    - `May I get a receipt, please?` — _Это эквивалентная форма с другим модальным глаголом._
- **дистракторы:**
    - `Can I get receipt, please, for my records?` — article: **receipt** → `a receipt`

### go sightseeing

- **перевод (промпт):** осматривать достопримечательности
- **эталон-пример:** We plan to go sightseeing this afternoon.
- **дистракторы:**
    - `We plan to go sightseeing in this afternoon.` — preposition: **in this afternoon** → `this afternoon`
    - `We plans to go sightseeing this afternoon.` — tense: **plans** → `plan`

### travel insurance

- **перевод (промпт):** страховка для путешествий
- **эталон-пример:** Make sure your travel insurance covers unexpected cancellations.
- **дистракторы:**
    - `Make sure your travel insurance cover unexpected cancellations.` — tense: **cover** → `covers`

### I'd like to make a reservation.

- **перевод (промпт):** Я бы хотел сделать бронь.
- **эталон-пример:** I'd like to make a reservation for Saturday night.
- **дистракторы:**
    - `I'd like to make a reservation at Saturday night.` — preposition: **at Saturday night** → `for Saturday night`
    - `I'd like to make reservation for Saturday night.` — article: **reservation** → `a reservation`
    - `I'd like to make a reservation on Saturday night.` — preposition: **on** → `for`

### passport control

- **перевод (промпт):** паспортный контроль
- **эталон-пример:** There was a long queue at passport control.
- **дистракторы:**
    - `There was long queue at passport control.` — article: **long** → `a long`

## Essential IT Phrasal Verbs and Expressions for Programmers

### debug

- **перевод (промпт):** отлаживать (программу)
- **эталон-пример:** I need to debug the code to find the error.
- **принимаемые варианты:**
    - `fix` — _Синоним._
    - `troubleshoot` — _Синоним._
- **дистракторы:**
    - `I need to debug the code for find the error.` — preposition: **for** → `to`
    - `I need to debug a code to find the error.` — article: **a code** → `the code`
    - `I need to debugging the code to find the error.` — tense: **debugging** → `debug`

### run into an issue

- **перевод (промпт):** столкнуться с проблемой
- **эталон-пример:** We ran into an issue with the database connection.
- **дистракторы:**
    - `We ran into issue with the database connection.` — article: **into issue** → `into an issue`
    - `We run into an issue with the database connection.` — tense: **run** → `ran`

### set up a server

- **перевод (промпт):** настроить сервер
- **эталон-пример:** I'll set up a server for the new project.
- **дистракторы:**
    - `I setting up a server for the new project.` — tense: **setting** → `will set`

### pull request

- **перевод (промпт):** запрос на внесение изменений (в репозиторий)
- **эталон-пример:** Can you review my pull request?
- **дистракторы:**
    - `Can you reviewing my pull request?` — tense: **reviewing** → `review`
    - `Can you review a pull request?` — article: **a pull request** → `my pull request`

### commit changes

- **перевод (промпт):** зафиксировать изменения
- **эталон-пример:** Make sure to commit your changes regularly to avoid conflicts.
- **принимаемые варианты:**
    - `record changes` — _Синоним._
    - `save changes` — _Эквивалент._
- **дистракторы:**
    - `Make sure to commit changes your regularly to avoid conflicts.` — word_order: **commit changes your** → `commit your changes`
    - `Make sure to commit your change regularly to avoid conflicts.` — tense: **change** → `changes`

### branch out

- **перевод (промпт):** создать ветку (в системе контроля версий)
- **эталон-пример:** I decided to branch out to try a different solution.
- **принимаемые варианты:**
    - `diversify` — _Синоним выражения._
    - `expand` — _Синоним выражения._
- **дистракторы:**
    - `I decided to branch out to trying a different solution.` — modal_to: **to trying** → `to try`

### code review

- **перевод (промпт):** проверка кода
- **эталон-пример:** The team conducts a code review before merging.
- **дистракторы:**
    - `The team conduct a code review before merging.` — tense: **conduct** → `conducts`
    - `The team conducts code review before merging.` — article: **code review** → `a code review`
    - `The team conducts a code reviews before merging.` — tense: **code reviews** → `code review`

### deploy

- **перевод (промпт):** развернуть (программу)
- **эталон-пример:** We're planning to deploy the application next week.
- **принимаемые варианты:**
    - `to launch` — _Синоним развернуть в контексте программного обеспечения._
    - `to install` — _Синоним развернуть в контексте программного обеспечения._
- **дистракторы:**
    - `We're planning to deploy application next week.` — article: **deploy application** → `deploy the application`
    - `We're planning to deploy the application at next week.` — preposition: **at next week** → `next week`

### roll back

- **перевод (промпт):** откатить (изменения)
- **эталон-пример:** If the update fails, we'll roll back to the previous version.
- **дистракторы:**
    - `If the update fails, we will rolled back to the previous version.` — tense: **will rolled back** → `will roll back`
    - `If the update fails, we'll roll back the previous version.` — article: **the previous version** → `to the previous version`

### get up and running

- **перевод (промпт):** запускать (программу или систему)
- **эталон-пример:** Let's get the system up and running by tomorrow.
- **принимаемые варианты:**
    - `to get started` — _Эквивалентное выражение._
- **дистракторы:**
    - `Let's get the system up and run by tomorrow.` — tense: **up and run** → `up and running`

### patch

- **перевод (промпт):** исправление (патч)
- **эталон-пример:** We need to apply a patch to fix the security issue.
- **дистракторы:**
    - `We need to apply patch to fix the security issue.` — article: **patch** → `a patch`
    - `We need to apply a patch for fix the security issue.` — preposition: **for fix** → `to fix`
    - `We needs to apply a patch to fix the security issue.` — tense: **needs** → `need`

### run smoothly

- **перевод (промпт):** работать без сбоев
- **эталон-пример:** The new software runs smoothly on all devices.
- **принимаемые варианты:**
    - `function properly` — _Синоним._
    - `operate smoothly` — _Синоним._
- **дистракторы:**
    - `The new software runs smoothly in all devices.` — preposition: **in** → `on`
    - `The new software run smoothly on all devices.` — tense: **run** → `runs`

### integrate

- **перевод (промпт):** интегрировать
- **эталон-пример:** We need to integrate the new module with the existing system.
- **дистракторы:**
    - `We needs to integrate the new module with the existing system.` — tense: **needs** → `need`
    - `We need to integrate the new module in the existing system.` — preposition: **in** → `with`

### back up data

- **перевод (промпт):** создавать резервную копию данных
- **эталон-пример:** Don't forget to back up your data before the update.
- **дистракторы:**
    - `Don't forget to back up the data before the update.` — article: **the** → `your`

### go live

- **перевод (промпт):** запускать в эксплуатацию
- **эталон-пример:** The website is scheduled to go live next Monday.
- **принимаемые варианты:**
    - `launch` — _Синоним слова "go live"._
    - `commence` — _Синоним слова "go live"._
- **дистракторы:**
    - `The website is scheduled to goes live next Monday.` — tense: **goes** → `go`
    - `The website is scheduled to go live on next Monday.` — preposition: **on next** → `next`

### root cause

- **перевод (промпт):** коренная причина
- **эталон-пример:** We need to find the root cause of the performance issue.
- **принимаемые варианты:**
    - `main reason` — _основная причина_
    - `underlying cause` — _подлежащая причина_
- **дистракторы:**
    - `We need to find root cause of the performance issue.` — article: **root cause** → `the root cause`

### scalability

- **перевод (промпт):** масштабируемость
- **эталон-пример:** Scalability is crucial for the system to handle a growing number of requests.
- **дистракторы:**
    - `Scalability are crucial for the system to handle a growing number of requests.` — tense: **are** → `is`

### clean code

- **перевод (промпт):** чистый (хорошо структурированный) код
- **эталон-пример:** Writing clean code is essential for maintainability.
- **принимаемые варианты:**
    - `well-structured code` — _это синоним_
- **дистракторы:**
    - `Writing clean code are essential for maintainability.` — tense: **are** → `is`

### bottleneck

- **перевод (промпт):** узкое место
- **эталон-пример:** Identifying bottlenecks is key to optimizing system performance.
- **дистракторы:**
    - `Identifying bottlenecks are key to optimizing system performance.` — tense: **are** → `is`
    - `Identifying bottlenecks is the key to optimizing system performance.` — article: **the key** → `key`

### on the same page

- **перевод (промпт):** быть на одной волне
- **эталон-пример:** It's important for the team to be on the same page about the project goals.
- **принимаемые варианты:**
    - `on the same side` — _Еквівалентна фраза._
    - `in agreement` — _Синонім у цьому контексті._
- **дистракторы:**
    - `It's important for the team to be in the same page about the project goals.` — preposition: **in the same page** → `on the same page`
    - `It's important for the teams to be on the same page about the project goals.` — tense: **the teams** → `the team`
- **флаги:**
    - 🇺🇦 **украинизм → переген** — Украинские буквы в русском поле (і є): «Важливо, щоб команда розуміла одне одного щодо цілей проєкту.».
    - 🇺🇦 **украинизм → переген** — Украинские буквы в русском поле (і): «на одній хвилі, розуміти одне одного».

### rollback plan

- **перевод (промпт):** план по откату изменений
- **эталон-пример:** We need a rollback plan in case the deployment fails.
- **дистракторы:**
    - `We need rollback plan in case the deployment fails.` — article: **rollback plan** → `a rollback plan`
    - `We need a rollback plan for case the deployment fails.` — preposition: **for case** → `in case`
    - `We need a rollback plans in case the deployment fails.` — tense: **rollback plans** → `rollback plan`

### hotfix

- **перевод (промпт):** горячее исправление, хотфикс
- **эталон-пример:** They released a hotfix to solve the bug quickly.
- **дистракторы:**
    - `They released a hotfix to solved the bug quickly.` — tense: **solved** → `solve`
    - `They released a the hotfix to solve the bug quickly.` — article: **a the** → `a`

## Essential American Phrasal Verbs

### give up

- **перевод (промпт):** сдаваться, бросать
- **эталон-пример:** I won't give up until I've achieved my goals.
- **принимаемые варианты:**
    - `surrender` — _Синонім до 'здаватися'._
    - `quit` — _Синонім до 'здаватися'._
- **дистракторы:**
    - `I won't give up until I achieved my goals.` — tense: **achieved** → `have achieved`
    - `I won't give ups until I've achieved my goals.` — tense: **give ups** → `give up`
    - `I won't give up until I've achieve my goals.` — tense: **achieve** → `achieved`
- **флаги:**
    - 🇺🇦 **украинизм → переген** — Украинские буквы в русском поле (ї і): «Я не здамся, поки не досягну своїх цілей.».

### figure out

- **перевод (промпт):** разобраться, понять
- **эталон-пример:** I finally figured out how to solve the math problem.
- **дистракторы:**
    - `I finally figure out how to solve the math problem.` — tense: **figure** → `figured`

### look after

- **перевод (промпт):** ухаживать, присматривать
- **эталон-пример:** Can you look after my cat while I'm away?
- **принимаемые варианты:**
    - `take care of` — _синонімічний вираз_
- **дистракторы:**
    - `Can you look after the my cat while I'm away?` — article: **the my** → `my`
    - `Can you look after my cat while I was away?` — tense: **was** → `am`
- **флаги:**
    - 🇺🇦 **украинизм → переген** — Украинские буквы в русском поле (ї є): «Можеш доглянути за моїм котом, поки мене немає?».

### run into

- **перевод (промпт):** неожиданно встретить кого-либо, столкнуться
- **эталон-пример:** I ran into an old friend at the mall yesterday.
- **принимаемые варианты:**
    - `bump into` — _синоним для "run into"._
- **дистракторы:**
    - `I run into an old friend at the mall yesterday.` — tense: **run** → `ran`
    - `I ran into old friend at the mall yesterday.` — article: **old friend** → `an old friend`

### break down

- **перевод (промпт):** ломаться, выходить из строя
- **эталон-пример:** My car broke down on the way to work.
- **дистракторы:**
    - `My car break down on the way to work.` — tense: **break** → `broke`
    - `My car broke down in the way to work.` — preposition: **in the way** → `on the way`
- **флаги:**
    - 🇺🇦 **украинизм → переген** — Украинские буквы в русском поле (і): «Моя машина зламалася по дорозі на роботу.».

### make up

- **перевод (промпт):** создавать, придумывать
- **эталон-пример:** She made up a story about why she was late.
- **принимаемые варианты:**
    - `invent` — _Синоним слова "make up"._
    - `create` — _Синоним слова "make up"._
- **дистракторы:**
    - `She make up a story about why she was late.` — tense: **make** → `made`
    - `She made up story about why she was late.` — article: **story** → `a story`
    - `She made up in story about why she was late.` — preposition: **in story** → `a story`

### take off

- **перевод (промпт):** взлетать, снимать
- **эталон-пример:** The plane took off on time.
- **дистракторы:**
    - `The plane take off on time.` — tense: **take off** → `took off`
    - `The plane took off in time.` — preposition: **in time** → `on time`
- **флаги:**
    - 🇺🇦 **украинизм → переген** — Украинские буквы в русском поле (і): «Літак взлетів вчасно.».
    - 🇺🇦 **украинизм → переген** — Украинские буквы в русском поле (і): «взлітати, знімати».

### catch up

- **перевод (промпт):** обсудить последние события
- **эталон-пример:** It's nice to catch up with old friends.
- **дистракторы:**
    - `It's nice to catch up with the old friends.` — article: **the old friends** → `old friends`
    - `It's nice to catch up for old friends.` — preposition: **for old friends** → `with old friends`
    - `It's nice to catches up with old friends.` — tense: **catches up** → `catch up`

### turn down

- **перевод (промпт):** отказываться, убавлять
- **эталон-пример:** She turned down the job offer because the salary was too low.
- **принимаемые варианты:**
    - `reject` — _Синонім слова «відхилити»._
    - `decline` — _Синонім слова «відхилити»._
- **дистракторы:**
    - `She turn down the job offer because the salary was too low.` — tense: **turn** → `turned`
    - `She turned down for the job offer because the salary was too low.` — preposition: **down for** → `down`
- **флаги:**
    - ✍️ **не слово → правка** — Слово «відхилити» написано правильно.
    - 🇺🇦 **украинизм → переген** — Украинские буквы в русском поле (і): «відхилити».
    - 🇺🇦 **украинизм → переген** — Украинские буквы в русском поле (і): «Вона відхилила пропозицію роботи, тому що зарплата була занизькою.».

### get by

- **перевод (промпт):** справляться, обходиться
- **эталон-пример:** I can get by on my salary, but I don't save much.
- **дистракторы:**
    - `I can get by on salary, but I don't save much.` — article: **salary** → `my salary`
    - `I can get by in my salary, but I don't save much.` — preposition: **in my salary** → `on my salary`
    - `I can gets by on my salary, but I don't save much.` — tense: **gets** → `get`

### put off

- **перевод (промпт):** откладывать
- **эталон-пример:** He was tired, so he put off the meeting until tomorrow.
- **принимаемые варианты:**
    - `defer` — _синонім до 'put off'_
    - `postpone` — _синонім до 'put off'_
- **дистракторы:**
    - `He was tired, so he put off the meeting until tommorrow.` — false_friend: **tommorrow** → `tomorrow`
- **флаги:**
    - 🇺🇦 **украинизм → переген** — Украинские буквы в русском поле (і): «відкладати».
    - 🇺🇦 **украинизм → переген** — Украинские буквы в русском поле (і): «Він був втомлений, тож він відклав зустріч на завтра.».

### give in

- **перевод (промпт):** уступать, сдаваться
- **эталон-пример:** He finally gave in to their demands.
- **принимаемые варианты:**
    - `yield` — _синоним уступать_
    - `surrender` — _синоним сдаваться_
- **дистракторы:**
    - `He finally gave in at their demands.` — preposition: **at** → `to`
    - `He finally give in to their demands.` — tense: **give** → `gave`

### come across

- **перевод (промпт):** натолкнуться, случайно встретить
- **эталон-пример:** I came across an old photograph of us yesterday.
- **принимаемые варианты:**
    - `find by chance` — _Має схоже значення._
    - `stumble upon` — _Синонімічний вислів._
- **дистракторы:**
    - `I came across an old photograph of us at yesterday.` — preposition: **at yesterday** → `yesterday.`
    - `I come across an old photograph of us yesterday.` — tense: **come across** → `came across`
- **флаги:**
    - 🇺🇦 **украинизм → переген** — Украинские буквы в русском поле (і): «Я натрапив на стару нашу фотографію вчора.».

### turn up

- **перевод (промпт):** появляться, приходить
- **эталон-пример:** She turned up at the party unexpectedly.
- **дистракторы:**
    - `She turned up in the party unexpectedly.` — preposition: **in the party** → `at the party`
    - `She turn up at the party unexpectedly.` — tense: **turn up** → `turned up`
- **флаги:**
    - 🇺🇦 **украинизм → переген** — Украинские буквы в русском поле (і): «Вона несподівано з'явилася на вечірці.».

### hold on

- **перевод (промпт):** подождать, держаться
- **эталон-пример:** Please hold on a moment while I find your file.
- **дистракторы:**
    - `Please hold on moment while I find your file.` — article: **hold on moment** → `hold on a moment`
    - `Please hold on a moments while I find your file.` — tense: **a moments** → `a moment`

### get over

- **перевод (промпт):** пережить, справиться с
- **эталон-пример:** It took her a while to get over the flu.
- **дистракторы:**
    - `It took her a while for get over the flu.` — modal_to: **for get** → `to get`
    - `It took her a while to get over flu.` — article: **flu** → `the flu`

### fill out

- **перевод (промпт):** заполнить (форму)
- **эталон-пример:** Please fill out this application to proceed.
- **принимаемые варианты:**
    - `fill in` — _британский вариант_
    - `complete` — _Это синоним в данном контексте._
- **дистракторы:**
    - `Please fill out this application for proceed.` — preposition: **for proceed** → `to proceed`
    - `Please fill out a application to proceed.` — article: **a application** → `this application`

### back up

- **перевод (промпт):** поддерживать, делать резервную копию
- **эталон-пример:** You should back up your files regularly.
- **дистракторы:**
    - `You should back up the files regularly.` — article: **the files** → `your files`
    - `You should back up your files regular.` — tense: **regular** → `regularly`

### take care of

- **перевод (промпт):** заботиться о
- **эталон-пример:** She takes care of her younger brother after school.
- **дистракторы:**
    - `She takes care to her younger brother after school.` — preposition: **to** → `of`
    - `She take care of her younger brother after school.` — tense: **take** → `takes`

### try out

- **перевод (промпт):** тестировать, проверять
- **эталон-пример:** I'm going to try out the new software today.
- **дистракторы:**
    - `I'm going to try out the new software today and.` — tense: **today and.** → `today.`
    - `I'm going to try the new software out today.` — word_order: **try the new software out** → `try out the new software`

### sort out

- **перевод (промпт):** урегулировать, разобраться с
- **эталон-пример:** We need to sort out these issues before lunch.
- **принимаемые варианты:**
    - `resolve` — _Синоним для «урегулировать»._
    - `deal with` — _Альтернативное выражение для «разобраться с»._
- **дистракторы:**
    - `Sort out we need to these issues before lunch.` — word_order: **Sort out we need to** → `We need to sort out`
    - `We need to sort out with these issues before lunch.` — preposition: **sort out with** → `sort out`
    - `We needs to sort out these issues before lunch.` — tense: **needs** → `need`

### look forward to

- **перевод (промпт):** с нетерпением ждать
- **эталон-пример:** I'm looking forward to my vacation next month.
- **принимаемые варианты:**
    - `await` — _Синоним, обозначающий ожидание._
    - `anticipate` — _Синоним, обозначающий ожидание._
- **дистракторы:**
    - `I look forward to my vacation next month.` — tense: **look** → `am looking`
    - `I'm looking forward on my vacation next month.` — preposition: **forward on** → `forward to`
- **флаги:**
    - 🇺🇦 **украинизм → переген** — Украинские буквы в русском поле (і): «Я з нетерпінням чекаю на відпустку наступного місяця.».

## Describing a Person's Body

### head

- **перевод (промпт):** голова
- **эталон-пример:** She has a smart head on her shoulders.
- **дистракторы:**
    - `She has smart head on her shoulders.` — article: **smart head** → `a smart head`
    - `She has a smart head in her shoulders.` — preposition: **in her shoulders** → `on her shoulders`

### shoulders

- **перевод (промпт):** плечи
- **эталон-пример:** He shrugged his shoulders in confusion.
- **дистракторы:**
    - `He shrugged his shoulders in a confusion.` — article: **a confusion** → `confusion`

### arms

- **перевод (промпт):** руки (от плеч до кистей)
- **эталон-пример:** Her arms are strong from lifting weights.
- **дистракторы:**
    - `Her arms is strong from lifting weights.` — tense: **is** → `are`

### legs

- **перевод (промпт):** ноги
- **эталон-пример:** He stretched his legs after a long walk.
- **дистракторы:**
    - `He stretched his leg after a long walk.` — article: **his leg** → `his legs`

### back

- **перевод (промпт):** спина
- **эталон-пример:** His back was sore after moving the furniture.
- **дистракторы:**
    - `His back was sore after move the furniture.` — word_order: **move the furniture** → `moving the furniture`
    - `His back is sore after moving the furniture.` — tense: **is sore** → `was sore`

### face

- **перевод (промпт):** лицо
- **эталон-пример:** Her face lit up when she saw him.
- **дистракторы:**
    - `Her face lit up when she see him.` — tense: **see** → `saw`
    - `Her face lit up when she saw by him.` — preposition: **saw by** → `saw`

### skin tone

- **перевод (промпт):** цвет кожи
- **эталон-пример:** She has a fair skin tone that tans easily.
- **дистракторы:**
    - `She has a fair skin tone that tan easily.` — tense: **tan** → `tans`

### tall and slim

- **перевод (промпт):** высокий и стройный
- **эталон-пример:** He is tall and slim, making him ideal for basketball.
- **дистракторы:**
    - `He are tall and slim, making him ideal for basketball.` — tense: **are** → `is`
    - `He is tall and slim, that makes him ideal for basketball.` — preposition: **that makes** → `making`
    - `He is tall and slim, making him the ideal for basketball.` — article: **the ideal** → `ideal`

### round face

- **перевод (промпт):** круглое лицо
- **эталон-пример:** Her round face makes her look younger.
- **дистракторы:**
    - `Her round face make her look younger.` — tense: **make** → `makes`
    - `Round face makes her look younger.` — word_order: **Round face** → `Her round face`

### bald

- **перевод (промпт):** лысый
- **эталон-пример:** He started going bald in his thirties.
- **дистракторы:**
    - `He started going bald in his thirty years.` — tense: **thirty years.** → `thirties.`
    - `He started bald in his thirties.` — word_order: **started** → `started going`
    - `He started to going bald in his thirties.` — modal_to: **started to** → `started`

### freckles

- **перевод (промпт):** веснушки
- **эталон-пример:** Her face is covered in freckles from sun exposure.
- **дистракторы:**
    - `Her face is covered by freckles from sun exposure.` — preposition: **by** → `in`
    - `Her face are covered in freckles from sun exposure.` — tense: **are** → `is`

### curly hair

- **перевод (промпт):** кудрявые волосы
- **эталон-пример:** She has thick, curly hair like her grandmother.
- **дистракторы:**
    - `She has thick, the curly hair like her grandmother.` — article: **the curly hair** → `curly hair`
    - `She has thick, curly hair of her grandmother.` — preposition: **of her grandmother** → `like her grandmother`

### bushy eyebrows

- **перевод (промпт):** густые брови
- **эталон-пример:** His bushy eyebrows give him a serious look.
- **дистракторы:**
    - `His bushy eyebrows give him serious look.` — article: **serious look** → `a serious look`
    - `His bushy eyebrows gives him a serious look.` — tense: **gives** → `give`

### broad shoulders

- **перевод (промпт):** широкие плечи
- **эталон-пример:** His broad shoulders make him look very athletic.
- **дистракторы:**
    - `His broad shoulders makes him look very athletic.` — tense: **makes** → `make`
    - `His the broad shoulders make him look very athletic.` — article: **the broad** → `broad`

### facial features

- **перевод (промпт):** черты лица
- **эталон-пример:** Her facial features are very distinct.
- **принимаемые варианты:**
    - `facial characteristics` — _Синоним._
    - `features of the face` — _Синонимичное выражение._
- **дистракторы:**
    - `Her facial features is very distinct.` — tense: **is** → `are`
    - `Her facial features are very distincts.` — tense: **distincts** → `distinct`

### in good shape

- **перевод (промпт):** в хорошей форме (физическое состояние)
- **эталон-пример:** He is in good shape because he exercises regularly.
- **дистракторы:**
    - `He is in good shape because he is exercising regularly.` — tense: **is exercising** → `exercises`
    - `He is in good shape because he exercise regularly.` — tense: **exercise** → `exercises`

### blue-eyed

- **перевод (промпт):** голубоглазый
- **эталон-пример:** The blue-eyed child smiled warmly.
- **принимаемые варианты:**
    - `blue-eyed person` — _Термин может относиться не только к детям._
    - `azure-eyed` — _Синоним, который также обозначает голубые глаза._
- **дистракторы:**
    - `Blue-eyed the child smiled warmly.` — word_order: **Blue-eyed the** → `The blue-eyed`
    - `The blue-eyed child smile warmly.` — tense: **smile** → `smiled`

### well-built

- **перевод (промпт):** хорошо сложенный
- **эталон-пример:** He is well-built and looks intimidating.
- **дистракторы:**
    - `He is well-built at looks intimidating.` — preposition: **at looks** → `and looks`
    - `He is the well-built and looks intimidating.` — article: **the well-built** → `well-built`
    - `He are well-built and looks intimidating.` — tense: **are** → `is`

### sharp features

- **перевод (промпт):** резкие черты лица
- **эталон-пример:** His sharp features make him stand out in a crowd.
- **дистракторы:**
    - `His sharp features makes him stand out in a crowd.` — tense: **makes** → `make`
    - `His sharp features make him stand out at a crowd.` — preposition: **at a crowd** → `in a crowd`

### goatee

- **перевод (промпт):** бородка
- **эталон-пример:** He decided to grow a goatee to change his look.
- **дистракторы:**
    - `He decided to go to grow a goatee to change his look.` — preposition: **to go to** → `to`
    - `He decide to grow a goatee to change his look.` — tense: **decide** → `decided`
    - `He decided to grow goatee to change his look.` — article: **goatee** → `a goatee`

### soft skin

- **перевод (промпт):** мягкая кожа
- **эталон-пример:** She takes care of her soft skin with regular moisturizing.
- **дистракторы:**
    - `She takes care of her soft skin with a regular moisturizing.` — article: **a regular moisturizing** → `regular moisturizing`
    - `She takes care of her soft skin with regular moisturizing of.` — preposition: **with regular moisturizing of** → `with regular moisturizing`
    - `She take care of her soft skin with regular moisturizing.` — tense: **take** → `takes`

