# LANG-1 · живой день · be-en

Ученик `qa-lang1-live-be-en-0926@wt.test` · план `01M3DGRD9FJXR2REFTR0VQ6RPR` · база `wordtrainer_e2e_test` · выгружено 2026-09-26T00:18:38Z

Цель (на родном): «Запісваюся да лекара: трэці дзень баліць горла і тэмпература. Трэба выбраць зручны час і растлумачыць, што мяне турбуе» · уровень beginner · дней 3

- сервер фазы create: `/wt/public` · база `wordtrainer_e2e_test` · очередь sync · модель (по умолчанию) · голос выкл · фото (по умолчанию)
- сервер фазы walk: `/wt/public` · база `wordtrainer_e2e_test` · очередь sync · модель (по умолчанию) · голос выкл · фото (по умолчанию)
- сервер фазы talk: `/wt/public` · база `wordtrainer_e2e_test` · очередь sync · модель (по умолчанию) · голос вкл · фото (по умолчанию)
- сервер фазы dump: `/wt/public` · база `wordtrainer_e2e_test` · очередь sync · модель (по умолчанию) · голос вкл · фото (по умолчанию)

### Сборка

План **ready** за 58.8 с (plan-builder-v2, gpt-5.4-2026-03-05, попыток 1) · день 1 «Запіс да лекара» — **ready** за 58.9 с от POST (урок `lesson_day.v4.8`, модель gpt-5.4-2026-03-05, попыток 1) · `lang.pack_missing` +0 (счётчики не сдвинулись).

- `lang.pack_missing` · lesson_day.v4.5 · counted: 14 → 14 (0)
- `lang.pack_missing` · lesson_day.v4.7 · counted: 174 → 174 (0)
- находки урока (`checks_json`): `pronunciation.script` @p4.f3, `variant.longer` @B3, `check.verbatim` @x3.check, `listening.distractor_not_filler` @L4, `vocab.used_in_wrong` @v6, `vocab.used_in_wrong` @v8, `vocab.used_in_wrong` @v8, `native.gendered_past` @B1, `native.gendered_past` @p1, `filler.native_seam` @p7.f2, `filler.native_seam` @p7.f3
- сцены: 1. «Запіс да лекара» / «Doctor booking» — ready, голос роли 4NejU5DwQjevnR6mh3mb (female) · 2. «Прыём у лекара» / «Doctor consultation» — pending

### Проход дня 1

| этап | вид | ответы |
|---|---|---|
| words | `word_intro` | passed ×8 |
| words | `word_repeat` | passed ×8 |
| words | `word_listen` | passed ×2 |
| words | `word_assemble` | passed ×2 |
| words | `word_in_line` | passed ×2 |
| words | `word_choose` | passed ×2 |
| phrases | `phrase_intro` | passed ×8 |
| phrases | `phrase_choose_back` | passed ×6 |
| phrases | `phrase_slot_listen` | passed ×6 |
| phrases | `phrase_assemble` | passed ×2 |
| phrases | `phrase_other_slot` | passed ×8 |
| phrases | `phrase_slot` | passed ×2 |
| phrases | `phrase_combine` | passed ×1 |
| dialogue | `dialogue_ask` | passed ×3 |
| dialogue | `dialogue_partner` | passed ×5 |
| dialogue | `dialogue_answer` | passed ×5 |
| listen | `listen_dialogue` | passed ×1 |
| listen | `listen_question` | passed ×4 |
| listen | `listen_review` | passed ×1 |
| listen | `listen_predict` | passed ×3 |
| listen | `listen_pace` | passed ×1 |
| listen | `listen_number` | passed ×1 |
| speak | `speak_answer` | судья→passed ×3, skipped ×3 |
| speak | `speak_echo` | passed ×1 |
| speak | `speak_retell` | passed ×1 |

Закрытие этапов: words → 200 · phrases → 200 · dialogue → 200 · listen → 200 · speak → 200.
Окно после прохода: words done · phrases done · dialogue done · listen done · speak done · conversation current · неотвеченных карточек 0.

Судья окна (`speak_answer`; `literal` — своя строка обмена, её знакомое наполнение судит КОД; `forced` — та же строка с наполнением чужого каркаса, её окно судит МОДЕЛЬ):

- x1 [literal] «I'd like an appointment.» → зачтено, окно «an appointment» · решил код
- x2 [forced] «I have an appointment.» → зачтено, окно «an appointment» · решила модель gpt-5.4-mini-2026-03-17 · $0.0007
- x3 [forced] «For an appointment.» → зачтено, окно «an appointment» · решила модель gpt-5.4-mini-2026-03-17 · $0.0007

### Разговор

«Размова: рэгістратар» · ended · подсказки да · ходов 9 · конец **natural** · целей 7 из 7 · сказал сам 7 · понял все: да · минут 1 · ряд разговора в окне — done.

| # | кто | реплика (цель) | перевод (родной) | голос | кредиты |
|---|---|---|---|---|---|
| 1 | роль | Good morning. How can I help you today? | Добрай раніцы. Чым я магу вам дапамагчы сёння? | elevenlabs:eleven_v3_conversational:4NejU5DwQjevnR6mh3mb:s50 | 10 |
| 2 | ученик · said | I'd like an appointment. ✓p1 | — | — | — |
| 3 | роль | Of course. What seems to be the problem? | Вядома. Што вас турбуе? | elevenlabs:eleven_v3_conversational:4NejU5DwQjevnR6mh3mb:s50 | 10 |
| 4 | ученик · said | I have a sore throat. ✓p2 | — | — | — |
| 5 | роль | How long have you had it? | Колькі часу ў вас гэта ўжо? | elevenlabs:eleven_v3_conversational:4NejU5DwQjevnR6mh3mb:s50 | 6 |
| 6 | ученик · said | For three days. ✓p3 | — | — | — |
| 7 | роль | Okay, Wednesday at two works. | Добра, серада а другой падыходзіць. | elevenlabs:eleven_v3_conversational:4NejU5DwQjevnR6mh3mb:s50 | 7 |
| 8 | ученик · said | Wednesday at two is good. ✓p4 | — | — | — |
| 9 | роль | May I have your full name, please? | Ці магу я, калі ласка, даведацца ваша поўнае імя? | elevenlabs:eleven_v3_conversational:4NejU5DwQjevnR6mh3mb:s50 | 8 |
| 10 | ученик · said | My name is Ali Hasan. ✓p5 | — | — | — |
| 11 | роль | Thank you. What phone number can we use? | Дзякуй. Які нумар тэлефона мы можам выкарыстаць? | elevenlabs:eleven_v3_conversational:4NejU5DwQjevnR6mh3mb:s50 | 10 |
| 12 | ученик · said | My number is 555-0182. ✓p6 | — | — | — |
| 13 | роль | Thank you. I'll add that to your notes. | Дзякуй. Я дадам гэта ў вашы запісы. | elevenlabs:eleven_v3_conversational:4NejU5DwQjevnR6mh3mb:s50 | 10 |
| 14 | ученик · said | I also have a fever too. ✓p7 | — | — | — |
| 15 | роль | Thank you. I’ll note that as well. | Дзякуй. Я таксама гэта адзначу. | elevenlabs:eleven_v3_conversational:4NejU5DwQjevnR6mh3mb:s50 | 8 |
| 16 | ученик · skip | — | — | — | — |
| 17 | роль | You're booked. Please arrive ten minutes early. | Вы запісаны. Калі ласка, прыйдзіце на дзесяць хвілін раней. | elevenlabs:eleven_v3_conversational:4NejU5DwQjevnR6mh3mb:s50 | 12 |
| 18 | ученик · skip | — | — | — | — |
| 19 | роль | Goodbye. | Да пабачэння. | elevenlabs:eleven_v3_conversational:4NejU5DwQjevnR6mh3mb:s50 | 2 |

Откуда реплики ученика: фраза урока p1 (hints.ref) ×1; фраза урока p2 (hints.ref) ×1; фраза урока p3 (hints.ref) ×1; фраза урока p4 (hints.ref) ×1; фраза урока p5 (hints.ref) ×1; фраза урока p6 (hints.ref) ×1; фраза урока p7 (hints.ref) ×1; skip ×2.

### Отказы сторожей роли

Всего 2: `rejected_answer/own_line` ×1, `rejected_answer/early_end` ×1. **Страж перевода (`native_missing`): 0** — ниже у каждого отказа ответ, который он не пустил: перевод настоящий — отказ ложный.

- ход 15 · попытка 1 · страж реплики · `rejected_answer` / `own_line` · {"line":"I'll add that to your notes."}
  - отказанный ответ: «Thank you. I'll add that to your notes.» / reply_native «Дзякуй. Я дадам гэта ў вашы запісы.» (журнал: duration_ms ≈ latency_ms, расхождение 16 мс, кандидатов 3)
- ход 17 · попытка 1 · страж реплики · `rejected_answer` / `early_end`
  - отказанный ответ: «You're booked. Please arrive ten minutes early. Goodbye.» / reply_native «Вы запісаны. Калі ласка, прыйдзіце на дзесяць хвілін раней. Да пабачэння.» (журнал: duration_ms ≈ latency_ms, расхождение 14 мс, кандидатов 3)

### Деньги

| что | кредиты · символы · $ |
|---|---|
| голос дня 1 (48 строк) | 271 · 1096 · $0.0542 |
| — реплика ученика (8) | 44 · 180 · $0.0088 |
| — реплика собеседника (8) | 75 · 299 · $0.0150 |
| — фраза (8) | 44 · 180 · $0.0088 |
| — фраза с наполнением (16) | 90 · 363 · $0.0180 |
| — слово (8) | 18 · 74 · $0.0036 |
| — голос `elevenlabs:eleven_v3_conversational:TWutjvRaJqAX89preB4e:s50` (40) | 196 · 797 · $0.0392 |
| — голос `elevenlabs:eleven_v3_conversational:4NejU5DwQjevnR6mh3mb:s50` (8) | 75 · 299 · $0.0150 |
| голос разговора (10 реплик) | 83 · 335 · $0.0166 |
| **голос всего** | **354 · 1431 · $0.0708** |

| модели | $ |
|---|---|
| план | $0.0188 |
| урок дня 1 (с починками и судьёй швов) | $0.0807 |
| судья окна (2 решений модели из 3, цена из `response.judge` карточек) | $0.0013 |
| разговор (ходы роли, `model_cost_usd` — с отказанными попытками) | $0.0121 |
| **модели всего** | **$0.1130** |

Сверка: `conversations.cost_usd` $0.0287 = модель $0.0121 + голос $0.0166 по журналу ходов — сходится (в счёт не прибавляется: голос в нём уже есть).
Сверка судьи: вызовов `judge` в журнале model_calls за окно прохода — 2 на $0.0013 (сюда попал бы и судья швов чужой сборки в той же базе).
