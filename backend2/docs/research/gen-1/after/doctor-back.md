# Сходить к врачу из-за боли в спине: объяснить симптомы и понять назначения

- план `01M1W3GV9JGRF614751Y6QBFR4` · ru→en · basic · событие 2026-09-08 · статус active
- цель: «Иду к врачу из-за боли в спине: объяснить, что и как болит, и понять, что он назначил»
- сводка: Сходить к врачу из-за боли в спине: объяснить симптомы и понять назначения

## Сцены каркаса (P1)

### Сцена 1 — Запись и цель визита

Ты подходишь к стойке или говоришь с сотрудником по телефону, чтобы попасть на прием. Твоя задача — коротко назвать причину визита и понять, когда и к кому тебя направляют.

- умение `s1.1`: сказать короткую причину обращения — чек: называет, что пришел из-за боли в спине
- умение `s1.2`: понять простые вопросы о записи — чек: понимает, на когда назначен прием
- opening_lines: «What seems to be the problem?» · «Are you here for an appointment?» · «Do you have back pain?» · «When would you like to come in?» · «The doctor can see you at 3 p.m.» · «Please wait here.»
- entities: clinic, reception, appointment, doctor

### Сцена 2 — Разговор с врачом

Ты сидишь в кабинете врача и отвечаешь на простые вопросы о боли. Успех здесь — ясно сказать, где болит, какая это боль и когда она усиливается или началась.

- умение `s2.1`: показать место боли — чек: говорит, где именно болит спина
- умение `s2.2`: описать характер боли — чек: говорит, какая боль: острая, тупая, сильная
- умение `s2.3`: сказать, когда началась и когда усиливается боль — чек: говорит, когда боль началась или от чего хуже
- opening_lines: «Where does it hurt?» · «Is the pain in your lower back?» · «When did it start?» · «Is it sharp or dull?» · «Does it get worse when you move?» · «Does it hurt when you sit or walk?»
- entities: doctor, exam room, lower back, upper back

### Сцена 3 — Осмотр и вопросы

Во время осмотра врач дает простые указания и задает короткие вопросы о движении и ощущениях. Тебе нужно понять, что сделать, и сообщить, если движение вызывает боль.

- умение `s3.1`: понимать простые указания врача во время осмотра — чек: выполняет короткую команду врача
- умение `s3.2`: сообщить, что движение вызывает боль — чек: говорит, при каком движении больно
- opening_lines: «Please stand up.» · «Bend forward slowly.» · «Turn to the left.» · «Does this hurt?» · «Can you raise your leg?» · «Tell me if you feel pain.»
- entities: exam table, chair, left, right

### Сцена 4 — Назначения врача

В конце приема врач объясняет, что делать дальше: лекарства, отдых, упражнения или обследование. Твоя цель — понять основные указания и переспросить самое важное простыми словами.

- умение `s4.1`: понять основные назначения после приема — чек: может сказать, что назначил врач
- умение `s4.2`: уточнить, как принимать лекарство или что делать дальше — чек: задает простой уточняющий вопрос о назначении
- opening_lines: «I’m going to prescribe you some pain medicine.» · «Take this twice a day after meals.» · «Try to rest for a few days.» · «I want you to do these exercises.» · «You need an X-ray.» · «Come back if the pain gets worse.»
- entities: prescription, pharmacy, X-ray, pain medicine

## День 1 — Запись и цель визита (intro, ready, попыток 1, починок 1)

### Пары (по цепочке)

**1. [answer]**
- role: «What seems to be the problem?» — Что кажется проблемой?
- you: «My back hurts.» — У меня болит спина. · ключ: `back` · ещё: `Back pain` / `My back` · s1.1

**2. [answer]**
- role: «Are you here for an appointment?» — Вы пришли по записи?
- you: «No, I need an appointment.» — Нет, мне нужна запись. · ключ: `appointment` · ещё: `No appointment` / `I need to book` · s1.2

**3. [answer]**
- role: «When would you like to come in?» — Когда вы хотели бы прийти?
- you: «As soon as possible, please.» — Как можно скорее, пожалуйста. · ключ: `as soon as possible` · ещё: `Soon, please` / `Earliest time` · s1.1

**4. [answer]**
- role: «The doctor can see you at 3 p.m..» — Врач может принять вас в 3 часа дня.
- you: «Yes, that works.» — Да, это подходит. · ключ: `that works` · ещё: `Yes, okay` / `That is fine` · s1.2

**5. [ask]**
- role: «Any other questions?» — Есть еще вопросы?
- you: «Which doctor should I see?» — К какому врачу мне идти? · ключ: `doctor` · ещё: `Which doctor?` / `Who is the doctor?` · s1.2

**6. [answer]**
- role: «Please wait here.» — Пожалуйста, подождите здесь.
- you: «Okay, I'll wait here.» — Хорошо, я подожду здесь. · ключ: `here` · ещё: `I'll wait here` / `Okay, sure` · s1.2

### Слова и связки

- [words] **back** — спина · пример: «My back feels stiff.» — Моя спина кажется скованной.
- [words] **doctor** — врач · пример: «The doctor is with another patient.» — Врач сейчас с другим пациентом.
- [words] **hurts** — болит · пример: «It hurts when I sit.» — Болит, когда я сижу.
- [words] **appointment** — запись на прием · пример: «I have an appointment this afternoon.» — У меня запись на прием сегодня днем.
- [words] **problem** — проблема · пример: «The problem started last week.» — Проблема началась на прошлой неделе.
- [chunks] **as soon as possible** — как можно скорее · пример: «I would like to come in as soon as possible.» — Я хотел бы прийти как можно скорее.
- [chunks] **wait here** — подождите здесь · пример: «Please wait here for the doctor.» — Пожалуйста, подождите здесь врача.
- [chunks] **that works** — это подходит · пример: «Three o'clock? Yes, that works.» — Три часа? Да, это подходит.

### Числа на слух

- «We can book you for 3 p.m..» — Мы можем записать вас на 3 часа дня. · value `3:00`
- «We can book you for 10 a.m..» — Мы можем записать вас на 10 часов утра. · value `10:00`

### Спасатели (сервер)

- «Could you speak more slowly, please?» — Помедленнее, пожалуйста.
- «Could you write it down, please?» — Напишите, пожалуйста.
- «Could you repeat that, please?» — Повторите ещё раз, пожалуйста.
- «How much is it?» — Сколько это стоит?
- «One moment, let me check.» — Секунду, я проверю.

## День 2 — Разговор с врачом (intro, ready, попыток 1, починок 1)

### Пары (по цепочке)

**1. [answer]**
- role: «Is the pain sharp or dull?» — Боль острая или тупая?
- you: «It is sharp and strong.» — Она острая и сильная. · ключ: `sharp` · ещё: `sharp pain` / `very sharp` · s2.2

**2. [answer]**
- role: «When did it start?» — Когда это началось?
- you: «It started two days ago.» — Она началась два дня назад. · ключ: `two days` · ещё: `two days ago` / `started yesterday` · s2.3

**3. [answer]**
- role: «Does it get worse when you move?» — Становится хуже, когда вы двигаетесь?
- you: «Yes, it gets worse when I move.» — Да, становится хуже, когда я двигаюсь. · ключ: `move` · ещё: `Yes, when I move` / `Yes, it does` · s2.3

**4. [ask]**
- role: «Any questions?» — Есть вопросы?
- you: «Can I walk to work?» — Можно ли мне ходить на работу? · ключ: `walk` · ещё: `can I walk` / `is walking okay` · s2.3

### Слова и связки

- [words] **sharp** — острая · пример: «The pain feels sharp when I stand up.» — Боль кажется острой, когда я встаю.
- [words] **strong** — сильный · пример: «The pain is strong this morning.» — Боль сильная этим утром.
- [words] **move** — двигаться · пример: «It hurts more when I move.» — Болит сильнее, когда я двигаюсь.
- [chunks] **get worse** — становиться хуже · пример: «Does it get worse at night?» — Становится хуже ночью?
- [chunks] **out of 10** — по шкале от 1 до 10 · пример: «My pain is 7 out of 10 now.» — Моя боль сейчас на уровне 7 из 10.
- [chunks] **walk to work** — ходить на работу · пример: «Can I walk to work tomorrow?» — Можно мне завтра ходить на работу пешком?

### Числа на слух

- «Take this 2 times a day.» — Принимайте это 2 раза в день. · value `2`
- «Come back in 3 days.» — Приходите снова через 3 дня. · value `3`
- «Is your pain 7 out of 10 now?» — Ваша боль сейчас на уровне 7 из 10? · value `7`

## День 3 — Прогон перед событием (final, pending, попыток 0, починок 0)

_материала нет_

## Реестр трат

| вызов | статус | версия | $ |
|---|---|---|---|
| outline: Иду к врачу из-за боли в спине: объяснить, что и как болит, и понять, что он назначил | succeeded | plan_outline.v0.4.2 | 0.018065 |
| pair_judge: пара 0 — What seems to be the problem? | succeeded | plan_pair_judge.v0.2 | 0.002878 |
| pair_judge: пара 1 — Are you here for an appointment? | succeeded | plan_pair_judge.v0.2 | 0.002883 |
| pair_judge: пара 2 — When would you like to come in? | succeeded | plan_pair_judge.v0.2 | 0.002890 |
| pair_judge: пара 3 — The doctor can see you at 3 p.m.. | succeeded | plan_pair_judge.v0.2 | 0.002900 |
| pair_judge: пара 4 — Any other questions? | succeeded | plan_pair_judge.v0.2 | 0.002870 |
| pair_judge: пара 5 — Please wait here. | succeeded | plan_pair_judge.v0.2 | 0.002875 |
| day: день 1 — Запись и цель визита | failed | plan_day.v0.7 | 0.031815 |
| day_repair: починка дня 1 — карточек 2 | succeeded | plan_day_repair.v0.3 | 0.011678 |
| pair_judge: пара 5 — Please wait here. | succeeded | plan_pair_judge.v0.2 | 0.002890 |
| pair_judge: пара 0 — Is it in your lower back? | succeeded | plan_pair_judge.v0.2 | 0.003130 |
| pair_rewrite: пара 0 — Is it in your lower back? | succeeded | plan_pair_rewrite.v0.2 | 0.003028 |
| pair_judge: пара 0 — Is it in your lower back? | succeeded | plan_pair_judge.v0.2 | 0.003175 |
| pair_rewrite: пара 0 — Is it in your lower back? | succeeded | plan_pair_rewrite.v0.2 | 0.003063 |
| pair_judge: пара 0 — Is it in your lower back? | succeeded | plan_pair_judge.v0.2 | 0.003133 |
| pair_judge: пара 1 — Is the pain sharp or dull? | succeeded | plan_pair_judge.v0.2 | 0.002893 |
| pair_judge: пара 2 — When did it start? | succeeded | plan_pair_judge.v0.2 | 0.002875 |
| pair_judge: пара 3 — Does it get worse when you move? | succeeded | plan_pair_judge.v0.2 | 0.003185 |
| pair_rewrite: пара 3 — Does it get worse when you move? | succeeded | plan_pair_rewrite.v0.2 | 0.003128 |
| pair_judge: пара 3 — Does it get worse when you move? | succeeded | plan_pair_judge.v0.2 | 0.002918 |
| pair_judge: пара 4 — Any questions? | succeeded | plan_pair_judge.v0.2 | 0.002868 |
| day: день 2 — Разговор с врачом | failed | plan_day.v0.7 | 0.032315 |
| day_repair: починка дня 2 — карточек 3 | succeeded | plan_day_repair.v0.3 | 0.013725 |
| **итого** | | | **0.161180** |

