# LANG-1 · Українська→English (uk→en, начальный)

Цель плана (слова ученика): «Записуюся до лікаря: третій день болить горло і температура. Треба вибрати зручний час і пояснити, що мене турбує»

Роль ученика в плане: Patient / Пацієнт. Сцена 1: «Запис до лікаря» (Receptionist / Адміністратор); сцена 2: «Прийом у лікаря» (Doctor / Лікар).

> День 1 — урок, каким его получил бы ученик (прошедший порог, как записан в сцену; при `failed` — ответ модели как написан), прочитанный сервером: наполнение реплики найдено по её тексту. «Чтение» — как модель записала звучание целевого текста буквами родного. Находки — по ответу модели ДО починок, валидатором кода прогона в контексте боя этой пары. «Не проверено» — проверки, для которых у пакета языка нет ключей (`lang.pack_missing`, не находка). Цена — вызовы плана и дня по одному разу. Факты сюжета и роли код не проверяет.

Итог: **failed** (fatal: options.form_mismatch) · починок P2R: 2 (x1: exchange.second_question; x4.check: options.form_mismatch) · вызовов урока: 1 · план $0.0122 · 10.4 с · день $0.0977 (урок $0.0663 · починки $0.0314 · судья $0.0000) · из кэша 44 % входа · 49.4 с · всего $0.1099 · ученик: Пацієнт · собеседник: Адміністратор (женщина)

Промты: план `plan-builder-v2` · урок `lesson_day.v4.7` · починка `lesson_card_repair.v1.3` · судья `lesson_seam_judge.v1.1` · модель урока `gpt-5.4-2026-03-05` · попыток урока: 1 · в сцене записано $0.097695 (урок, починки и судья — сверка, не слагаемое)

### Сценарий диалога

| # | вид обмена | кто | реплика | перевод | чтение | каркас · наполнение |
|---|---|---|---|---|---|---|
| 1 | вопрос ученика | Пацієнт (ученик) | I’d like to book a doctor’s appointment. | Я б хотів записатися на прийом до лікаря. | Айд лайк ту бук е докторз епойнтмент. | p1 · a doctor’s appointment |
| 1 | вопрос ученика | Адміністратор (собеседник) | Of course. What seems to be the problem? | Звісно. Що вас турбує? |  |  |
| 2 | ответ | Адміністратор (собеседник) | What seems to be the problem? | Що вас турбує? |  |  |
| 2 | ответ | Пацієнт (ученик) | I have a sore throat and a fever. | У мене болить горло і є температура. | Ай хев э сор сроут энд э фівер. | p2 · a sore throat and a fever |
| 3 | ответ | Адміністратор (собеседник) | We have appointments at ten or three today. | Сьогодні є записи на десяту або на третю. |  |  |
| 3 | ответ | Пацієнт (ученик) | Three works better for me. | Третя мені підходить краще. | Срі воркс бетер фор мі. | p3 · three |
| 4 | вопрос ученика | Пацієнт (ученик) | Could you give me the clinic address? | Не могли б ви дати мені адресу клініки? | Куд ю гів мі зе клінік едрес? | p4 · the clinic address |
| 4 | вопрос ученика | Адміністратор (собеседник) | Yes. It’s 18 Green Street. | Так. Це Грін-стріт, 18. |  |  |
| 5 | ответ | Адміністратор (собеседник) | Please arrive ten minutes early for check-in. | Будь ласка, прийдіть на десять хвилин раніше для реєстрації. |  |  |
| 5 | ответ | Пацієнт (ученик) | I’ll arrive ten minutes early. | Я прийду на десять хвилин раніше. | Айл ерайв тен мінітс ерлі. | p5 · ten minutes early |
| 6 | вопрос ученика | Пацієнт (ученик) | Are you open tomorrow morning? | Ви працюєте завтра вранці? | Ар ю оупен туморо морнінг? | p6 · tomorrow morning |
| 6 | вопрос ученика | Адміністратор (собеседник) | Yes, we open at eight tomorrow. | Так, завтра ми відчиняємося о восьмій. |  |  |
| 7 | ответ | Адміністратор (собеседник) | Your appointment is at three with Dr. Brown. | Ваш прийом о третій у лікаря Браун. |  |  |
| 7 | ответ | Пацієнт (ученик) | That’s fine at three. | Добре, о третій мені підходить. | Зетс файн эт срі. | p7 · at three |
| 8 | ответ | Адміністратор (собеседник) | Please bring an ID card to the front desk. | Будь ласка, принесіть посвідчення особи на стійку реєстрації. |  |  |
| 8 | ответ | Пацієнт (ученик) | I’ll bring my ID card. | Я принесу своє посвідчення особи. | Айл брінг май ай ді кард. | p8 · my ID card |

### Каркасы

| id | вид | каркас | на родном | чтение | наполнения (в диалоге — жирным) |
|---|---|---|---|---|---|
| p1 | вопрос ученика | I’d like to book ___. | Я б хотів записатися на ___. | Айд лайк ту бук ___. | **a doctor’s appointment** / прийом до лікаря · a check-up / огляд |
| p2 | ответ | I have ___. | У мене ___. | Ай хев ___. | **a sore throat and a fever** / болить горло і є температура · a bad cough / сильний кашель · ear pain / біль у вусі |
| p3 | ответ | ___ works better for me. | ___ мені підходить краще. | ___ воркс бетер фор мі. | **three** / третя · ten / десята |
| p4 | вопрос ученика | Could you give me ___? | Не могли б ви дати мені ___? | Куд ю гів мі ___? | **the clinic address** / адресу клініки · the phone number / номер телефону |
| p5 | ответ | I’ll arrive ___. | Я прийду ___. | Айл ерайв ___. | **ten minutes early** / на десять хвилин раніше · right on time / точно вчасно |
| p6 | вопрос ученика | Are you open ___? | Ви працюєте ___? | Ар ю оупен ___? | **tomorrow morning** / завтра вранці · on Saturday / у суботу |
| p7 | ответ | That’s fine ___. | Добре, ___ мені підходить. | Зетс файн ___. | **at three** / о третій · for Friday / на п’ятницю |
| p8 | ответ | I’ll bring ___. | Я принесу ___. | Айл брінг ___. | **my ID card** / своє посвідчення особи · my insurance card / свою страхову картку |

### Наполнения

| каркас | наполнение | перевод | чтение | в диалоге | собранная фраза (родной) | судья швов |
|---|---|---|---|---|---|---|
| p1 | a doctor’s appointment | прийом до лікаря | э докторз епойнтмент | да | Я б хотів записатися на прийом до лікаря. | — |
| p1 | a check-up | огляд | э чек-ап | — | Я б хотів записатися на огляд. | — |
| p2 | a sore throat and a fever | болить горло і є температура | э сор сроут энд э фівер | да | У мене болить горло і є температура. | — |
| p2 | a bad cough | сильний кашель | э бед коф | — | У мене сильний кашель. | — |
| p2 | ear pain | біль у вусі | ір пейн | — | У мене біль у вусі. | — |
| p3 | three | третя | срі | да | третя мені підходить краще. | — |
| p3 | ten | десята | тен | — | десята мені підходить краще. | — |
| p4 | the clinic address | адресу клініки | зе клінік едрес | да | Не могли б ви дати мені адресу клініки? | — |
| p4 | the phone number | номер телефону | зе фоун намбер | — | Не могли б ви дати мені номер телефону? | — |
| p5 | ten minutes early | на десять хвилин раніше | тен мінітс ерлі | да | Я прийду на десять хвилин раніше. | — |
| p5 | right on time | точно вчасно | райт он тайм | — | Я прийду точно вчасно. | — |
| p6 | tomorrow morning | завтра вранці | туморо морнінг | да | Ви працюєте завтра вранці? | — |
| p6 | on Saturday | у суботу | он сетердей | — | Ви працюєте у суботу? | — |
| p7 | at three | о третій | эт срі | да | Добре, о третій мені підходить. | — |
| p7 | for Friday | на п’ятницю | фор фрайдей | — | Добре, на п’ятницю мені підходить. | — |
| p8 | my ID card | своє посвідчення особи | май ай ді кард | да | Я принесу своє посвідчення особи. | — |
| p8 | my insurance card | свою страхову картку | май іншуренс кард | — | Я принесу свою страхову картку. | — |

### Проверки обменов

| # | вопрос | варианты (✓ — верный) |
|---|---|---|
| 1 | What does the receptionist ask about? / Про що запитує адміністраторка? | ✓ Why the patient needs the visit / Чому пацієнту потрібен візит · Which doctor the patient saw before / До якого лікаря пацієнт ходив раніше · How the patient will pay / Як пацієнт буде платити |
| 2 | What does the receptionist want to know? / Що хоче дізнатися адміністраторка? | The patient’s address / Адресу пацієнта · ✓ The reason for the visit / Причину візиту · The patient’s insurance number / Номер страхування пацієнта |
| 3 | Which times does the receptionist offer? / Який час пропонує адміністраторка? | Nine or two / Дев’ята або друга · ✓ Ten or three / Десята або третя · Eleven or four / Одинадцята або четверта |
| 4 | What address does the receptionist give? / Яку адресу називає адміністраторка? | ✓ 18 Green Street / Грін-стріт, 18 · 80 Green Street / Грін-стріт, 80 · 18 Queen Street / Квін-стріт, 18 |
| 5 | When should the patient come? / Коли пацієнту треба прийти? | A quarter of an hour before / За п’ятнадцять хвилин до · Exactly on time / Точно вчасно · ✓ Ten minutes before the visit / За десять хвилин до візиту |
| 6 | What opening time does the receptionist give? / Який час відкриття називає адміністраторка? | At seven in the morning / О сьомій ранку · ✓ At eight in the morning / О восьмій ранку · At nine in the morning / О дев’ятій ранку |
| 7 | Who is the appointment with? / До кого запис? | Dr. Green / До лікаря Грін · ✓ Dr. Brown / До лікаря Браун · Dr. White / До лікаря Вайт |
| 8 | What should the patient bring? / Що пацієнту треба принести? | A payment receipt / Квитанцію про оплату · ✓ An identity document / Документ, що посвідчує особу · A bottle of water / Пляшку води |

### Слушаю весь визит

- L1. На який час пацієнт обрав запис? — На десяту · ✓ На третю · На четверту
- L2. Яку адресу назвала адміністраторка? — Квін-стріт, 18 · ✓ Грін-стріт, 18 · Грін-стріт, 80
- L3. Що сказав пацієнт про свою проблему? — Болить спина · ✓ Болить горло і є температура · Болить живіт
- L4. На скільки раніше треба прийти? — ✓ На десять хвилин · На двадцять хвилин · Точно вчасно

### Словарь

| id | слово | вид | перевод | чтение | где звучит |
|---|---|---|---|---|---|
| v1 | appointment | слово | запис на прийом | епойнтмент | p1, A3, A7 |
| v2 | sore throat | связка | біль у горлі | сор сроут | p2 |
| v3 | fever | слово | температура | фівер | p2 |
| v4 | clinic address | связка | адреса клініки | клінік едрес | p4 |
| v5 | check-in | связка | реєстрація | чек-ін | A5 |
| v6 | arrive early | связка | прийти раніше | ерайв ерлі | p5, A5 |
| v7 | open | слово | працювати / бути відчиненим | оупен | p6, A6 |
| v8 | ID card | связка | посвідчення особи | ай ді кард | p8, A8 |

### Находки в ответе модели (до починок)

| код | порог | адрес | что |
|---|---|---|---|
| `exchange.second_question` | **фатальная** | x1 | the closing message of A «Of course. What seems to be the problem?» ends with a question mark |
| `variant.longer` | предупреждение | B6 | the variant «Is the clinic open tomorrow morning?» has 6 words, the line 5 |
| `options.form_mismatch` | **фатальная** | x4.check | the option «Грін-стріт, 18» is a piece of the partner's line «Так. Це Грін-стріт, 18.» |
| `options.form_mismatch` | **фатальная** | x8.check | the option «Пляшку води» is 10 letters against 24 of the right «Документ, що посвідчує особу» |
| `vocab.used_in_wrong` | предупреждение | v6 | «arrive early» is not in frame p5 or its fillers |
| `vocab.used_in_wrong` | предупреждение | v6 | «arrive early» is not in the partner's line of exchange 5 |
| `vocab.abbreviation` | предупреждение | v8 | «ID card» is an abbreviation or an acronym — a word of the day only when the learner's language has an everyday word for it |

### Не проверено — нет ключей пакета (lang.pack_missing, не находка)

Кодов не проверено: родной 9, целевой 0. Контекст проверки: родной `uk`, целевой `en`.

| код | сторона | язык | недостающие ключи |
|---|---|---|---|
| `pronunciation.script` | родной | uk | script |
| `pronunciation.foreign_script` | родной | uk | script_letters |
| `frame.no_end_punct` | родной | uk | sentence_ends |
| `frame.native_punct` | родной | uk | sentence_ends |
| `frame.native_agreement` | родной | uk | agreement |
| `listening.same_exchange` | родной | uk | function_words, word_forms |
| `listening.no_learner_value` | родной | uk | function_words, word_forms |
| `listening.distractor_not_filler` | родной | uk | function_words, word_forms, number_pattern, time_pattern |
| `native.gendered_past` | родной | uk | gendered_past_pattern |

### Судья швов

Судья не звался: урок не прошёл порог.
