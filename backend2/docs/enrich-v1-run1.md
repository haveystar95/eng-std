# Выгрузка станка на вычитку

Версия генератора: `enrich-v1`.

Термины без вариантов, дистракторов и флагов в выгрузку не попадают — это рабочий список,
а не дамп базы. Колонка «флаги» — то, что требует решения человека.

## First Day at a New Company

### introduce yourself

- **перевод (промпт):** представляться
- **эталон-пример:** On your first day, introduce yourself to your new coworkers.
- **дистракторы:**
    - `On your first day, you introduce yourself to your new coworkers.` — tense: **you introduce** → `you will introduce`
- **флаги:**
    - ⚠️ **переформулировать** — Обратный перевод даёт «to present oneself» — ни эталон, ни варианты этого не покрывают; переформулировать.

### orientation session

- **перевод (промпт):** вводный инструктаж
- **эталон-пример:** We have an orientation session at 10 a.m.
- **дистракторы:**
    - `We have orientation session at 10 a.m.` — article: **orientation session** → `an orientation session`
    - `We have an orientation session in 10 a.m.` — preposition: **in 10 a.m.** → `at 10 a.m.`
- **флаги:**
    - ⚠️ **переформулировать** — Обратный перевод даёт «introductory briefing» — ни эталон, ни варианты этого не покрывают; переформулировать.

### meet the team

- **перевод (промпт):** встретиться с командой
- **эталон-пример:** You'll meet the team after lunch.
- **принимаемые варианты:**
    - `see the team` — _увидеть команду_
    - `greet the team` — _поздороваться с командой_
- **флаги:**
    - ⚠️ **переформулировать** — Обратный перевод даёт «meet with the team» — ни эталон, ни варианты этого не покрывают; переформулировать.

### get to know the office

- **перевод (промпт):** изучить офис
- **эталон-пример:** Take some time to get to know the office layout.
- **принимаемые варианты:**
    - `familiarize yourself with the office` — _Синонимичная фраза._
    - `get acquainted with the office` — _Синонимичная фраза._
- **дистракторы:**
    - `Take some time to get know the office layout.` — modal_to: **get know** → `get to know`
- **флаги:**
    - ⚠️ **переформулировать** — Обратный перевод даёт «learn the office» — ни эталон, ни варианты этого не покрывают; переформулировать.

### workstation

- **перевод (промпт):** рабочее место
- **эталон-пример:** Your workstation is ready for you.
- **дистракторы:**
    - `Your workstation is ready for she.` — word_order: **she** → `you`
    - `Your workstation are ready for you.` — tense: **are** → `is`
    - `Your workstation is ready for the you.` — article: **the you** → `you`
- **флаги:**
    - ⚠️ **переформулировать** — Обратный перевод даёт «workplace» — ни эталон, ни варианты этого не покрывают; переформулировать.

### colleague

- **перевод (промпт):** колледка
- **эталон-пример:** You'll find your colleagues very helpful.
- **дистракторы:**
    - `You'll find a colleagues very helpful.` — article: **a colleagues** → `colleagues`
    - `You'll find your colleagues to be very helpful.` — modal_to: **to be** → `very helpful.`
- **флаги:**
    - ✍️ **не слово → правка** — колледка, коллега

### break room

- **перевод (промпт):** комната отдыха
- **эталон-пример:** You can relax in the break room during lunch.
- **дистракторы:**
    - `You can relax in the break room during lunches.` — tense: **during lunches** → `during lunch`
    - `You can relax in break room during lunch.` — article: **break room** → `the break room`
- **флаги:**
    - ⚠️ **переформулировать** — Обратный перевод даёт «rest room» — ни эталон, ни варианты этого не покрывают; переформулировать.

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
- **флаги:**
    - ⚠️ **переформулировать** — Обратный перевод даёт «to introduce to the course of things» — ни эталон, ни варианты этого не покрывают; переформулировать.

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
- **флаги:**
    - ⚠️ **переформулировать** — Обратный перевод даёт «to settle in» — ни эталон, ни варианты этого не покрывают; переформулировать.

### company policies

- **перевод (промпт):** политика компании
- **эталон-пример:** Please familiarize yourself with the company policies.
- **дистракторы:**
    - `Please familiarize yourself with the policies company.` — word_order: **the policies company** → `the company policies`
    - `Please familiarize yourself with company policies.` — article: **company policies** → `the company policies`
- **флаги:**
    - ⚠️ **переформулировать** — Обратный перевод даёт «company policy» — ни эталон, ни варианты этого не покрывают; переформулировать.

