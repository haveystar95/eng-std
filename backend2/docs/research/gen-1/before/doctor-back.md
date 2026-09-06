# Сходить к врачу из-за боли в спине: объяснить симптомы и понять назначения

- план `01M1VTKC54VWVFCVTA5QG66PCC` · ru→en · basic · событие 2026-09-08 · статус active
- цель: «Иду к врачу из-за боли в спине: объяснить, что и как болит, и понять, что он назначил»
- сводка: Сходить к врачу из-за боли в спине: объяснить симптомы и понять назначения

## Сцены каркаса (P1)

### Сцена 1 — На приеме

Вы заходите в кабинет врача и коротко объясняете, что вас беспокоит. Важно спокойно описать, где болит, как именно болит и когда стало хуже.

- умение `s1.1`: сможет назвать главную жалобу на боль в спине — чек: может прямо сказать, что у него болит спина и зачем он пришел
- умение `s1.2`: сможет описать место и характер боли — чек: может сказать, где именно болит и какая это боль
- умение `s1.3`: сможет сказать, когда началась боль и что ее усиливает — чек: может описать, когда началась боль и при каком движении становится хуже
- opening_lines: «What brings you in today?» · «Where exactly does it hurt?» · «How would you describe the pain?» · «When did the pain start?» · «Does anything make it worse?» · «Does the pain go down your leg?»
- entities: 

### Сцена 2 — Вопросы врача

Врач будет задавать короткие вопросы, чтобы понять, насколько это серьезно. Вам нужно узнавать знакомые вопросы и давать простые точные ответы.

- умение `s2.1`: сможет ответить на вопросы о силе боли и постоянная ли она — чек: может сказать, сильная боль или нет и болит постоянно или временами
- умение `s2.2`: сможет ответить на вопросы о сопутствующих симптомах — чек: может сказать, есть ли онемение, слабость или температура
- умение `s2.3`: сможет сказать, что уже пробовал для облегчения боли — чек: может сообщить, принимал ли лекарства или отдыхал
- opening_lines: «On a scale of one to ten, how bad is the pain?» · «Is the pain constant or does it come and go?» · «Do you have any numbness or weakness?» · «Do you have a fever?» · «Have you taken anything for the pain?» · «Did anything help?»
- entities: 

### Сцена 3 — Осмотр и советы

После вопросов врач может попросить вас подвигаться или показать, какое движение вызывает боль. Потом вы услышите простые советы о том, что делать дальше.

- умение `s3.1`: сможет понять простые просьбы врача во время осмотра — чек: может правильно реагировать на просьбы сесть, встать, наклониться или повернуться
- умение `s3.2`: сможет сообщить, какое движение вызывает боль — чек: может сказать, при каком движении боль появляется или усиливается
- opening_lines: «Can you stand up, please?» · «Please sit down here.» · «Bend forward slowly.» · «Turn to the left.» · «Does this movement hurt?» · «Tell me when you feel pain.»
- entities: 

### Сцена 4 — Назначения врача

В конце приема врач скажет, что он рекомендует: лекарства, отдых, упражнения или обследование. Ваша задача — понять основное назначение и уточнить, как это делать.

- умение `s4.1`: сможет понять основное назначение врача — чек: может повторить, что ему назначили: лекарство, покой, упражнения или обследование
- умение `s4.2`: сможет уточнить, как принимать лекарство или что делать дома — чек: может спросить, как часто это делать или принимать
- умение `s4.3`: сможет понять, когда нужно обратиться снова — чек: может сказать, в каком случае нужно прийти еще раз или срочно обратиться
- opening_lines: «I’m going to prescribe you a painkiller.» · «Take this medicine twice a day after food.» · «Try to rest your back for a few days.» · «Use heat on the area.» · «I’d like you to do some gentle exercises.» · «Come back if it gets worse.»
- entities: 

## День 1 — На приеме (intro, ready, попыток 1, починок 1)

### Пары (по цепочке)

**1. [answer]**
- role: «What brings you in today?» — Что вас сегодня беспокоит?
- you: «I came in for lower back pain .» — Я пришел из-за боли в пояснице. · ключ: `lower back pain` · s1.1

**2. [answer]**
- role: «Where exactly does it hurt?» — Где именно болит?
- you: «It hurts most right here .» — Больше всего болит здесь. · ключ: `right here` · s1.2

**3. [answer]**
- role: «How would you describe the pain?» — Как бы вы описали боль?
- you: «It is a sharp pain.» — Это острая боль. · ключ: `sharp` · s1.2

**4. [answer]**
- role: «When did the pain start?» — Когда началась боль?
- you: «It started after lifting a box .» — Она началась после поднятия коробки. · ключ: `lifting a box` · s1.3

**5. [answer]**
- role: «Does anything make it worse?» — Что-нибудь делает ее хуже?
- you: «Bending forward makes it worse.» — Наклоняться хуже. · ключ: `Bending forward` · s1.3

**6. [ask]**
- role: «Anything else you want to ask?» — Есть что-нибудь еще, что вы хотите спросить?
- you: «What does rest mean?» — Что значит отдых? · ключ: `rest` · s1.3

### Слова и связки

- [words] **sharp** — острая · пример: «The pain feels sharp when I move.» — Боль ощущается как острая, когда я двигаюсь.
- [words] **dull** — тупой · пример: «Sometimes it is a dull pain.» — Иногда это тупая боль.
- [words] **hurt** — болеть · пример: «Does it hurt when you bend forward?» — Болит, когда вы наклоняетесь вперед?
- [words] **rest** — отдых · пример: «The doctor says rest may help.» — Врач говорит, что отдых может помочь.
- [chunks] **lower back pain** — боль в пояснице · пример: «I am here because of lower back pain.» — Я здесь из-за боли в пояснице.
- [chunks] **right here** — прямо здесь · пример: «It hurts right here on the left side.» — Болит прямо здесь с левой стороны.
- [chunks] **lifting a box** — поднимать коробку · пример: «The problem started after lifting a box at home.» — Проблема началась после поднятия коробки дома.
- [chunks] **bending forward** — наклоняться вперед · пример: «Bending forward is hard today.» — Наклоняться вперед сегодня трудно.

### Числа на слух

- «The pain started two days ago.» — Боль началась два дня назад. · value `2`
- «Please take this twice a day.» — Пожалуйста, принимайте это два раза в день. · value `twice`
- «Come back in seven days if it does not improve.» — Вернитесь через семь дней, если не станет лучше. · value `7`

### Спасатели (сервер)

- «Could you speak more slowly, please?» — Помедленнее, пожалуйста.
- «Could you write it down, please?» — Напишите, пожалуйста.
- «Could you repeat that, please?» — Повторите ещё раз, пожалуйста.
- «How much is it?» — Сколько это стоит?
- «One moment, let me check.» — Секунду, я проверю.

## День 2 — Вопросы врача (intro, ready, попыток 1, починок 1)

### Пары (по цепочке)

**1. [answer]**
- role: «On a scale of one to ten, how bad is the pain?» — По шкале от одного до десяти, насколько сильная боль?
- you: «It is quite bad.» — Она довольно сильная. · ключ: `bad` · s2.1

**2. [answer]**
- role: «Is the pain constant or does it come and go?» — Боль постоянная или приходит и уходит?
- you: «It comes and goes» — Она приходит и уходит. · ключ: `comes and goes` · s2.1

**3. [answer]**
- role: «Do you have any numbness or weakness?» — У вас есть онемение или слабость?
- you: «No numbness but some weakness.» — Онемения нет, но есть слабость. · ключ: `weakness` · s2.2

**4. [answer]**
- role: «Have you taken anything for the pain?» — Вы что нибудь принимали от боли?
- you: «I tried rest and pills.» — Я пробовал отдых и таблетки. · ключ: `rest` · s2.3

**5. [ask]**
- role: «Is there anything you want to ask?» — Есть что-нибудь, что вы хотите спросить?
- you: «What does take it easy mean?» — Что значит полегче себя вести? · ключ: `take it easy` · s2.3

**6. [answer]**
- role: «Did anything help?» — Помогло хоть что нибудь?
- you: «Yes, I took pills.» — Да, я принял таблетки. · ключ: `took pills` · s2.3

### Слова и связки

- [words] **weakness** — слабость · пример: «I have some weakness in my leg.» — У меня есть некоторая слабость в ноге.
- [words] **pain** — боль · пример: «The pain is worse in the morning.» — Боль сильнее утром.
- [words] **fever** — температура жар · пример: «I do not have a fever.» — У меня нет температуры.
- [chunks] **comes and goes** — приходит и уходит · пример: «The pain comes and goes during the day.» — Боль приходит и уходит в течение дня.
- [chunks] **constant pain** — постоянная боль · пример: «It is more like constant pain now.» — Сейчас это скорее постоянная боль.
- [chunks] **numbness or weakness** — онемение или слабость · пример: «I do not have numbness or weakness in my foot.» — У меня нет онемения или слабости в ступне.
- [chunks] **take it easy** — полегче себя вести · пример: «The doctor says to take it easy for a few days.» — Врач говорит, что нужно поберечь себя несколько дней.
- [chunks] **took pills** — принял таблетки · пример: «I took pills this morning, but the pain is still there.» — Я принял таблетки сегодня утром, но боль все еще есть.

### Числа на слух

- «That sounds like about seven out of ten.» — Это примерно семь из десяти. · value `7`
- «Take one pill twice a day.» — Принимайте по одной таблетке два раза в день. · value `twice`
- «Try this for three days, then come back.» — Попробуйте это три дня, потом вернитесь. · value `3`

## День 3 — Прогон перед событием (final, pending, попыток 0, починок 0)

_материала нет_

## Реестр трат

| вызов | статус | версия | $ |
|---|---|---|---|
| outline: Иду к врачу из-за боли в спине: объяснить, что и как болит, и понять, что он назначил | succeeded | plan_outline.v0.4.1 | 0.019222 |
| pair_judge: пара 0 — What brings you in today? | succeeded | plan_pair_judge.v0.1 | 0.001188 |
| pair_judge: пара 1 — Where exactly does it hurt? | succeeded | plan_pair_judge.v0.1 | 0.001168 |
| pair_judge: пара 2 — How would you describe the pain? | succeeded | plan_pair_judge.v0.1 | 0.001123 |
| pair_judge: пара 3 — When did the pain start? | succeeded | plan_pair_judge.v0.1 | 0.001155 |
| pair_judge: пара 4 — Does anything make it worse? | succeeded | plan_pair_judge.v0.1 | 0.001152 |
| pair_judge: пара 5 — Anything else you want to ask? | succeeded | plan_pair_judge.v0.1 | 0.001180 |
| day: день 1 — На приеме | failed | plan_day.v0.6 | 0.029067 |
| day_repair: починка дня 1 — карточек 3 | succeeded | plan_day_repair.v0.2 | 0.012580 |
| pair_judge: пара 0 — On a scale of one to ten, how bad is the pain? | succeeded | plan_pair_judge.v0.1 | 0.001213 |
| pair_judge: пара 1 — Is the pain constant or does it come and go? | succeeded | plan_pair_judge.v0.1 | 0.001130 |
| pair_judge: пара 2 — Do you have any numbness or weakness? | succeeded | plan_pair_judge.v0.1 | 0.001160 |
| pair_judge: пара 3 — Have you taken anything for the pain? | succeeded | plan_pair_judge.v0.1 | 0.001185 |
| pair_judge: пара 4 — Is there anything you want to ask? | succeeded | plan_pair_judge.v0.1 | 0.001188 |
| pair_judge: пара 5 — Did anything help? | succeeded | plan_pair_judge.v0.1 | 0.001188 |
| pair_rewrite: пара 5 — Did anything help? | succeeded | plan_pair_rewrite.v0.1 | 0.001897 |
| pair_judge: пара 5 — Did anything help? | succeeded | plan_pair_judge.v0.1 | 0.001115 |
| day: день 2 — Вопросы врача | failed | plan_day.v0.6 | 0.030162 |
| day_repair: починка дня 2 — карточек 4 | succeeded | plan_day_repair.v0.2 | 0.014590 |
| **итого** | | | **0.122663** |

