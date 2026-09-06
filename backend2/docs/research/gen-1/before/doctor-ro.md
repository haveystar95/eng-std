# Să mergi la medic pentru durerea de spate, să explici simptomele clar și să înțelegi tratamentul prescris.

- план `01M1VTFZ518QG7Q9RDK425994G` · ro→en · basic · событие 2026-09-08 · статус active
- цель: «Merg la medic din cauza unei dureri de spate: să explic ce și cum mă doare și să înțeleg ce mi-a prescris»
- сводка: Să mergi la medic pentru durerea de spate, să explici simptomele clar și să înțelegi tratamentul prescris.

## Сцены каркаса (P1)

### Сцена 1 — La recepție

Ajungi la recepția clinicii și spui de ce ești acolo. Vei auzi întrebări scurte despre programare și motivul vizitei. Reușita înseamnă să spui simplu că ai dureri de spate și că ai nevoie de consultație.

- умение ``: poți spune pe scurt motivul vizitei — чек: poți spune că ai dureri de spate și că vrei să vezi un medic
- умение ``: poți răspunde când ești întrebat de când ai problema — чек: poți spune de când te doare spatele în cuvinte simple
- opening_lines: «Do you have an appointment?» · «What seems to be the problem?» · «How long have you had this pain?» · «Is it your lower back or upper back?» · «Please take a seat and wait for the doctor.»
- entities: clinic, reception, appointment

### Сцена 2 — În cabinet

Intri în cabinet și medicul te întreabă unde te doare și cum se simte durerea. Va trebui să descrii locul, intensitatea și ce mișcări o agravează. Reușita înseamnă să dai o imagine clară a durerii tale.

- умение ``: poți arăta unde te doare — чек: poți spune dacă te doare partea de jos sau partea de sus a spatelui
- умение ``: poți descrie cum se simte durerea — чек: poți spune dacă durerea este puternică, surdă, ascuțită sau constantă
- умение ``: poți spune ce mișcare sau situație agravează durerea — чек: poți spune că te doare mai mult când stai jos, te ridici sau te apleci
- opening_lines: «Where exactly does it hurt?» · «Is the pain sharp or dull?» · «Does it hurt all the time?» · «Does it get worse when you move?» · «Can you bend forward?» · «Did you injure your back?»
- entities: doctor, exam room

### Сцена 3 — Recomandări și rețetă

La final, medicul îți spune ce să faci mai departe și ce ți-a prescris. Tu trebuie să înțelegi instrucțiunile de bază și să ceri repetarea lor dacă nu sunt clare. Reușita înseamnă să pleci știind ce medicament iei și cum îl iei.

- умение ``: poți înțelege ideea principală a unei recomandări simple — чек: poți recunoaște dacă medicul îți recomandă odihnă, exerciții sau medicamente
- умение ``: poți verifica cum iei medicamentul prescris — чек: poți întreba cât de des și cât timp trebuie să iei medicamentul
- умение ``: poți cere repetarea sau explicarea instrucțiunilor — чек: poți cere politicos să ți se spună din nou mai rar sau mai simplu
- opening_lines: «I'm prescribing you a painkiller.» · «Take one tablet twice a day after food.» · «Use this for five days.» · «Try to rest your back for a few days.» · «If the pain gets worse, come back.» · «Do you have any questions about the prescription?»
- entities: prescription, painkiller, tablets

## День 1 — La recepție (intro, ready, попыток 1, починок 1)

### Пары (по цепочке)

**1. [answer]**
- role: «Do you have an appointment?» — Aveți o programare?
- you: «No, no appointment.» — Nu, nu am programare. · ключ: `appointment` · s1.1

**2. [answer]**
- role: «What seems to be the problem?» — Care pare să fie problema?
- you: «I have back pain.» — Am dureri de spate. · ключ: `back` · s1.1

**3. [answer]**
- role: «How long have you had this pain?» — De cât timp aveți durerea aceasta?
- you: «For two days.» — De două zile. · ключ: `days` · s1.2

**4. [answer]**
- role: «Is it your lower back or upper back?» — Este partea de jos a spatelui sau partea de sus?
- you: «Lower back, sorry.» — Partea de jos a spatelui, scuze. · ключ: `Lower back` · s1.2

**5. [ask]**
- role: «Please take a seat. Anything else?» — Vă rog să luați loc. Mai doriți să întrebați ceva?
- you: «Do I need a doctor today?» — Am nevoie de un doctor astăzi? · ключ: `doctor` · s1.1

### Слова и связки

- [words] **back** — spate · пример: «My back hurts when I sit.» — Mă doare spatele când stau jos.
- [words] **doctor** — medic · пример: «The doctor will see you soon.» — Medicul vă va consulta în curând.
- [words] **days** — zile · пример: «The pain started two days ago.» — Durerea a început acum două zile.
- [chunks] **lower back** — partea de jos a spatelui · пример: «The pain is in my lower back.» — Durerea este în partea de jos a spatelui.
- [chunks] **anything else** — altceva · пример: «Do you need anything else before I sit down?» — Aveți nevoie de altceva înainte să mă așez?
- [chunks] **take a seat** — a lua loc · пример: «Please take a seat near the window.» — Vă rog să luați loc lângă fereastră.

### Числа на слух

- «The doctor can see you in fifteen minutes.» — Doctorul vă poate vedea în 15 minute. · value `15`
- «Your appointment is in room two.» — Programarea dumneavoastră este la camera 2. · value `2`

## День 2 — În cabinet (intro, ready, попыток 1, починок 1)

### Пары (по цепочке)

**1. [answer]**
- role: «Where exactly does it hurt?» — Unde anume vă doare?
- you: «My upper back hurts.» — Mă doare partea de sus. · ключ: `upper back` · s2.1

**2. [answer]**
- role: «Is the pain sharp or dull?» — Durerea este ascuțită sau surdă?
- you: «It's mostly dull.» — Este mai degrabă surdă. · ключ: `dull` · s2.2

**3. [answer]**
- role: «Does it get worse when you move?» — Se agravează când vă mișcați?
- you: «Yes, when I bend forward.» — Da, când mă aplec. · ключ: `bend forward` · s2.3

**4. [answer]**
- role: «Did you injure your back?» — V-ați rănit spatele?
- you: «No, just some dull pain.» — Nu, doar o durere surdă. · ключ: `dull` · s2.2

**5. [ask]**
- role: «Okay. Any questions?» — Bine. Aveți vreo întrebare?
- you: «Sorry, what does constant mean?» — Scuze, ce înseamnă „constant”? · ключ: `constant` · s2.2

### Слова и связки

- [words] **dull** — surd(ă) · пример: «The pain is dull in the morning.» — Durerea este surdă dimineața.
- [words] **constant** — continuu · пример: «The pain is constant when I sit down.» — Durerea este continuă când mă așez.
- [words] **lifting** — ridicare · пример: «It started after lifting at work.» — A început după ridicare la muncă.
- [chunks] **upper back** — partea de sus a spatelui · пример: «The pain is in my upper back.» — Durerea este în partea de sus a spatelui meu.
- [chunks] **sharp pain** — durere ascuțită · пример: «I get a sharp pain when I stand up.» — Am o durere ascuțită când mă ridic în picioare.
- [chunks] **bend forward** — a te apleca în față · пример: «I can bend forward, but it hurts.» — Pot să mă aplec în față, dar mă doare.

### Числа на слух

- «Take this medicine twice a day.» — Luați acest medicament de două ori pe zi. · value `twice`
- «Take one after breakfast and one in the evening.» — Luați una după micul dejun și una seara. · value `evening`

## День 3 — Rehearsal before the event (final, pending, попыток 0, починок 0)

_материала нет_

## Реестр трат

| вызов | статус | версия | $ |
|---|---|---|---|
| outline: Merg la medic din cauza unei dureri de spate: să explic ce și cum mă doare și să înțeleg ce mi-a prescris | succeeded | plan_outline.v0.4.1 | 0.016568 |
| pair_judge: пара 0 — Do you have an appointment? | succeeded | plan_pair_judge.v0.1 | 0.001133 |
| pair_judge: пара 1 — What seems to be the problem? | succeeded | plan_pair_judge.v0.1 | 0.001120 |
| pair_judge: пара 2 — How long have you had this pain? | succeeded | plan_pair_judge.v0.1 | 0.001180 |
| pair_judge: пара 3 — Is it your lower back or upper back? | succeeded | plan_pair_judge.v0.1 | 0.001155 |
| pair_judge: пара 4 — Please take a seat. Anything else? | succeeded | plan_pair_judge.v0.1 | 0.001188 |
| day: день 1 — La recepție | failed | plan_day.v0.6 | 0.023890 |
| day_repair: починка дня 1 — карточек 2 | succeeded | plan_day_repair.v0.2 | 0.010548 |
| pair_judge: пара 0 — Where exactly does it hurt? | succeeded | plan_pair_judge.v0.1 | 0.001148 |
| pair_judge: пара 1 — Is the pain sharp or dull? | succeeded | plan_pair_judge.v0.1 | 0.001163 |
| pair_judge: пара 2 — Does it get worse when you move? | succeeded | plan_pair_judge.v0.1 | 0.001173 |
| pair_judge: пара 3 — Did you injure your back? | succeeded | plan_pair_judge.v0.1 | 0.001248 |
| pair_rewrite: пара 3 — Did you injure your back? | succeeded | plan_pair_rewrite.v0.1 | 0.001903 |
| pair_judge: пара 3 — Did you injure your back? | succeeded | plan_pair_judge.v0.1 | 0.001185 |
| pair_judge: пара 4 — Okay. Any questions? | succeeded | plan_pair_judge.v0.1 | 0.001180 |
| day: день 2 — În cabinet | failed | plan_day.v0.6 | 0.024833 |
| day_repair: починка дня 2 — карточек 4 | succeeded | plan_day_repair.v0.2 | 0.014145 |
| **итого** | | | **0.104760** |

