# Să poți explica în engleză durerea de spate la medic și să înțelegi tratamentul prescris.

- план `01M1W3MA8NSTJXYQE7SFD1KDXE` · ro→en · basic · событие 2026-09-08 · статус active
- цель: «Merg la medic din cauza unei dureri de spate: să explic ce și cum mă doare și să înțeleg ce mi-a prescris»
- сводка: Să poți explica în engleză durerea de spate la medic și să înțelegi tratamentul prescris.

## Сцены каркаса (P1)

### Сцена 1 — La recepție

Ajungi la recepția cabinetului și spui de ce ai venit. Vei răspunde la întrebări simple despre programare și motivul vizitei, ca să fii trimis mai departe fără blocaje.

- умение `s1.1`: Să poți spune pe scurt de ce ai venit la medic. — чек: spune motivul vizitei în 1–2 propoziții
- умение `s1.2`: Să poți răspunde la întrebări simple despre problemă și durată. — чек: spune de când te doare și dacă problema este urgentă
- opening_lines: «How can I help you?» · «What seems to be the problem?» · «Do you have an appointment?» · «How long have you had this pain?» · «Is it getting worse?» · «Please take a seat and wait for the doctor.»
- entities: recepție, cabinet, programare

### Сцена 2 — Consultația

Intri la medic și descrii clar unde te doare și cum se simte durerea. Medicul îți va pune întrebări despre intensitate, mișcare și ce agravează sau calmează problema.

- умение `s2.1`: Să poți arăta unde este durerea și cum se simte. — чек: descrie locul durerii și tipul ei
- умение `s2.2`: Să poți spune când apare durerea și ce o agravează. — чек: spune ce mișcare sau poziție agravează durerea
- умение `s2.3`: Să poți răspunde dacă durerea se duce în altă zonă și cât de puternică este. — чек: spune intensitatea durerii și dacă coboară în picior
- opening_lines: «Where exactly does it hurt?» · «Can you describe the pain?» · «Is it sharp or dull?» · «Does it hurt when you bend or walk?» · «Does the pain go down your leg?» · «How bad is the pain right now?»
- entities: cabinet, medic, masă de consultație

### Сцена 3 — Recomandări și rețetă

La final, medicul îți spune ce tratament să urmezi și îți poate da o rețetă. Succesul aici înseamnă să înțelegi ce să iei, cât de des și ce să faci dacă durerea nu trece.

- умение `s3.1`: Să poți înțelege instrucțiuni simple despre medicamente. — чек: repetă cum iei medicamentul și cât de des
- умение `s3.2`: Să poți înțelege recomandări simple despre odihnă, mișcare și revenire la control. — чек: spune ce ai voie să faci și când să revii
- opening_lines: «I'm going to prescribe you a painkiller.» · «Take one tablet twice a day after food.» · «Use this cream on your lower back.» · «Try to rest for a few days.» · «Avoid heavy lifting for now.» · «Come back if the pain gets worse.»
- entities: rețetă, farmacie, pastile, cremă

## День 1 — La recepție (intro, ready, попыток 2, починок 2)

### Пары (по цепочке)

**1. [answer]**
- role: «How can I help you?» — Cu ce vă pot ajuta?
- you: «I have back pain.» — Am dureri de spate. · ключ: `back pain` · ещё: `My back hurts` / `Back pain` · s1.1

**2. [answer]**
- role: «Do you have an appointment?» — Aveți o programare?
- you: «Yes, I have an appointment.» — Da, am o programare. · ключ: `appointment` · ещё: `Yes, I do` / `Yes appointment` · s1.1

**3. [answer]**
- role: «How long have you had this pain?» — De cât timp aveți durerea aceasta?
- you: «For three days.» — De trei zile. · ключ: `three days` · ещё: `Three days` / `About three days` · s1.2

**4. [answer]**
- role: «Is it getting worse?» — Se agravează?
- you: «Yes, a little worse.» — Da, puțin mai rău. · ключ: `worse` · ещё: `A little worse` / `Yes worse` · s1.2

**5. [ask]**
- role: «Anything else you'd like to ask?» — Mai doriți să întrebați ceva?
- you: «Is it urgent?» — Este urgent? · ключ: `urgent` · ещё: `Urgent?` / `Is this urgent?` · s1.2

### Слова и связки

- [words] **urgent** — urgent · пример: «The receptionist asks if it is urgent.» — Recepționera întreabă dacă este urgent.
- [words] **back** — spate · пример: «My back hurts when I sit down.» — Mă doare spatele când mă așez.
- [words] **appointment** — programare · пример: «I have an appointment at the clinic.» — Am o programare la clinică.
- [words] **worse** — mai rău · пример: «The pain is worse this morning.» — Durerea este mai rea în această dimineață.
- [chunks] **getting worse** — se agravează · пример: «The pain is getting worse after work.» — Durerea se agravează după muncă.
- [chunks] **back pain** — durere de spate · пример: «I am here because of back pain.» — Sunt aici din cauza unei dureri de spate.
- [chunks] **how long** — de cât timp · пример: «She asks how long I have this pain.» — Ea întreabă de cât timp am această durere.
- [chunks] **help you** — cu ce vă pot ajuta · пример: «The woman at reception says she can help you.» — Femeia de la recepție spune că vă poate ajuta.

### Числа на слух

- «Did the pain start three days ago?» — Durerea a început acum trei zile? · value `3`
- «Your appointment is at ten thirty.» — Programarea dumneavoastră este la ora 10:30. · value `10:30`

## День 2 — Consultația (intro, failed, попыток 2, починок 2)

**Отбой `card.skill_ref_invalid`:** День не прошёл валидатор: card.skill_ref_invalid [Yes, it goes down my leg.]: умения «s1.4» у этой сцены нет; card.number_value_mismatch [Take one tablet twice a day.]: `value` «twice» — не цифры и не дата; ученик вводит число цифрами

_материала нет_

## День 3 — Rehearsal before the event (final, pending, попыток 0, починок 0)

_материала нет_

## Реестр трат

| вызов | статус | версия | $ |
|---|---|---|---|
| outline: Merg la medic din cauza unei dureri de spate: să explic ce și cum mă doare și să înțeleg ce mi-a prescris | succeeded | plan_outline.v0.4.2 | 0.016640 |
| pair_judge: пара 0 — How can I help you? | succeeded | plan_pair_judge.v0.2 | 0.002893 |
| pair_judge: пара 1 — Do you have an appointment? | succeeded | plan_pair_judge.v0.2 | 0.002883 |
| pair_judge: пара 2 — How long have you had this pain? | succeeded | plan_pair_judge.v0.2 | 0.002880 |
| pair_judge: пара 3 — Is it getting worse? | succeeded | plan_pair_judge.v0.2 | 0.002878 |
| pair_judge: пара 4 — Anything else you'd like to ask? | succeeded | plan_pair_judge.v0.2 | 0.002878 |
| day: день 1 — La recepție | failed | plan_day.v0.7 | 0.027878 |
| day_repair: починка дня 1 — карточек 1 | succeeded | plan_day_repair.v0.3 | 0.009603 |
| pair_judge: пара 0 — How can I help you? | succeeded | plan_pair_judge.v0.2 | 0.002883 |
| pair_judge: пара 1 — Do you have an appointment? | succeeded | plan_pair_judge.v0.2 | 0.002883 |
| pair_judge: пара 2 — How long have you had this pain? | succeeded | plan_pair_judge.v0.2 | 0.002888 |
| pair_judge: пара 3 — Is it getting worse? | succeeded | plan_pair_judge.v0.2 | 0.002878 |
| pair_judge: пара 4 — Anything else you'd like to ask? | succeeded | plan_pair_judge.v0.2 | 0.002878 |
| day: день 1 — La recepție | failed | plan_day.v0.7 | 0.030635 |
| day_repair: починка дня 1 — карточек 2 | succeeded | plan_day_repair.v0.3 | 0.012120 |
| pair_judge: пара 0 — How can I help you? | succeeded | plan_pair_judge.v0.2 | 0.002880 |
| pair_judge: пара 1 — Do you have an appointment? | succeeded | plan_pair_judge.v0.2 | 0.002883 |
| pair_judge: пара 2 — How long have you had this pain? | succeeded | plan_pair_judge.v0.2 | 0.002880 |
| pair_judge: пара 3 — Is it getting worse? | succeeded | plan_pair_judge.v0.2 | 0.002878 |
| pair_judge: пара 4 — Anything else you'd like to ask? | succeeded | plan_pair_judge.v0.2 | 0.002878 |
| day: день 1 — La recepție | failed | plan_day.v0.7 | 0.028430 |
| day_repair: починка дня 1 — карточек 3 | succeeded | plan_day_repair.v0.3 | 0.013683 |
| pair_judge: пара 0 — How can I help you? | succeeded | plan_pair_judge.v0.2 | 0.002883 |
| pair_judge: пара 1 — Do you have an appointment? | succeeded | plan_pair_judge.v0.2 | 0.002883 |
| pair_judge: пара 2 — How long have you had this pain? | succeeded | plan_pair_judge.v0.2 | 0.002880 |
| pair_judge: пара 3 — Is it getting worse? | succeeded | plan_pair_judge.v0.2 | 0.002878 |
| pair_judge: пара 4 — Anything else you'd like to ask? | succeeded | plan_pair_judge.v0.2 | 0.002878 |
| day: день 1 — La recepție | succeeded | plan_day.v0.7 | 0.028670 |
| pair_judge: пара 0 — Where exactly does it hurt? | succeeded | plan_pair_judge.v0.2 | 0.002888 |
| pair_judge: пара 1 — Is it sharp or dull? | succeeded | plan_pair_judge.v0.2 | 0.002893 |
| pair_judge: пара 2 — Does it hurt when you bend or walk? | succeeded | plan_pair_judge.v0.2 | 0.002910 |
| pair_judge: пара 3 — Does the pain go down your leg? | succeeded | plan_pair_judge.v0.2 | 0.003165 |
| pair_rewrite: пара 3 — Does the pain go down your leg? | succeeded | plan_pair_rewrite.v0.2 | 0.003085 |
| pair_judge: пара 3 — Does the pain go down your leg? | succeeded | plan_pair_judge.v0.2 | 0.002905 |
| pair_judge: пара 4 — How bad is the pain right now? | succeeded | plan_pair_judge.v0.2 | 0.002890 |
| pair_judge: пара 5 — Any questions about the medicine? | succeeded | plan_pair_judge.v0.2 | 0.003163 |
| pair_rewrite: пара 5 — Any questions about the medicine? | succeeded | plan_pair_rewrite.v0.2 | 0.003088 |
| pair_judge: пара 5 — Any questions about the medicine? | succeeded | plan_pair_judge.v0.2 | 0.002902 |
| day: день 2 — Consultația | failed | plan_day.v0.7 | 0.033062 |
| day_repair: починка дня 2 — карточек 2 | succeeded | plan_day_repair.v0.3 | 0.011900 |
| pair_judge: пара 3 — Does the pain go down your leg? | succeeded | plan_pair_judge.v0.2 | 0.002912 |
| pair_judge: пара 0 — Where exactly does it hurt? | succeeded | plan_pair_judge.v0.2 | 0.002895 |
| pair_judge: пара 1 — Can you describe the pain? | succeeded | plan_pair_judge.v0.2 | 0.002885 |
| pair_judge: пара 2 — Does it hurt when you bend or walk? | succeeded | plan_pair_judge.v0.2 | 0.002910 |
| pair_judge: пара 3 — Does the pain go down your leg? | succeeded | plan_pair_judge.v0.2 | 0.003108 |
| pair_rewrite: пара 3 — Does the pain go down your leg? | succeeded | plan_pair_rewrite.v0.2 | 0.003058 |
| pair_judge: пара 3 — Does the pain go down your leg? | succeeded | plan_pair_judge.v0.2 | 0.003195 |
| pair_rewrite: пара 3 — Does the pain go down your leg? | succeeded | plan_pair_rewrite.v0.2 | 0.003040 |
| pair_judge: пара 3 — Does the pain go down your leg? | succeeded | plan_pair_judge.v0.2 | 0.002908 |
| pair_judge: пара 4 — How bad is the pain right now? | succeeded | plan_pair_judge.v0.2 | 0.002893 |
| pair_judge: пара 5 — Any questions about the medicine? | succeeded | plan_pair_judge.v0.2 | 0.003073 |
| pair_rewrite: пара 5 — Any questions about the medicine? | succeeded | plan_pair_rewrite.v0.2 | 0.003095 |
| pair_judge: пара 5 — Any questions about the medicine? | succeeded | plan_pair_judge.v0.2 | 0.003138 |
| pair_rewrite: пара 5 — Any questions about the medicine? | succeeded | plan_pair_rewrite.v0.2 | 0.003155 |
| pair_judge: пара 5 — Any questions about the medicine? | succeeded | plan_pair_judge.v0.2 | 0.003245 |
| day: день 2 — Consultația | failed | plan_day.v0.7 | 0.035108 |
| day_repair: починка дня 2 — карточек 2 | succeeded | plan_day_repair.v0.3 | 0.011477 |
| **итого** | | | **0.392228** |

