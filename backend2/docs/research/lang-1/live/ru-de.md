# LANG-1 · живой день · ru-de

Ученик `qa-lang1-live-ru-de-0926@wt.test` · план `01M3DGEQ5JN32N97SH0PQ66DEN` · база `wordtrainer_e2e_test` · выгружено 2026-09-26T00:35:02Z

Цель (на родном): «Записываюсь к врачу: третий день болит горло и температура. Нужно выбрать удобное время и объяснить, что меня беспокоит» · уровень beginner · дней 3

- сервер фазы create: `/wt/public` · база `wordtrainer_e2e_test` · очередь sync · модель (по умолчанию) · голос выкл · фото (по умолчанию)
- сервер фазы walk: `/wt/public` · база `wordtrainer_e2e_test` · очередь sync · модель (по умолчанию) · голос выкл · фото (по умолчанию)
- сервер фазы talk: `/wt/public` · база `wordtrainer_e2e_test` · очередь sync · модель (по умолчанию) · голос вкл · фото (по умолчанию)
- сервер фазы dump: `/wt/public` · база `wordtrainer_e2e_test` · очередь sync · модель (по умолчанию) · голос выкл · фото (по умолчанию)

### Сборка

План **ready** за 56.6 с (plan-builder-v2, gpt-5.4-2026-03-05, попыток 1) · день 1 «Запись к врачу» — **ready** за 56.6 с от POST (урок `lesson_day.v4.8`, модель gpt-5.4-2026-03-05, попыток 1) · `lang.pack_missing` +0 (счётчики не сдвинулись).

- `lang.pack_missing` · lesson_day.v4.5 · counted: 14 → 14 (0)
- `lang.pack_missing` · lesson_day.v4.7 · counted: 174 → 174 (0)
- находки урока (`checks_json`): `variant.longer` @B2, `variant.longer` @B4, `variant.longer` @B7, `vocab.used_in_wrong` @v6, `filler.native_seam` @p1.f1, `filler.native_seam` @p1.f2, `filler.native_seam` @p6.f2
- сцены: 1. «Запись к врачу» / «Arzttermin buchen» — ready, голос роли 4NejU5DwQjevnR6mh3mb (female) · 2. «Приём у врача» / «Arztbesuch» — pending

### Проход дня 1

| этап | вид | ответы |
|---|---|---|
| words | `word_intro` | passed ×8 |
| words | `word_repeat` | passed ×8 |
| words | `word_listen` | passed ×2 |
| words | `word_in_line` | passed ×2 |
| words | `word_choose` | passed ×2 |
| words | `word_assemble` | passed ×2 |
| phrases | `phrase_intro` | passed ×7 |
| phrases | `phrase_slot` | passed ×3 |
| phrases | `phrase_slot_listen` | passed ×6 |
| phrases | `phrase_other_slot` | passed ×7 |
| phrases | `phrase_choose_back` | passed ×2 |
| phrases | `phrase_assemble` | passed ×3 |
| phrases | `phrase_combine` | passed ×1 |
| dialogue | `dialogue_ask` | passed ×2 |
| dialogue | `dialogue_partner` | passed ×4 |
| dialogue | `dialogue_answer` | passed ×5 |
| dialogue | `dialogue_rescue` | passed ×1 |
| listen | `listen_dialogue` | passed ×1 |
| listen | `listen_question` | passed ×4 |
| listen | `listen_review` | passed ×1 |
| listen | `listen_predict` | passed ×2 |
| listen | `listen_pace` | passed ×1 |
| listen | `listen_number` | passed ×1 |
| speak | `speak_answer` | судья→passed ×1, судья→не зачтено ×2, skipped ×5 |
| speak | `speak_echo` | passed ×1 |
| speak | `speak_retell` | passed ×1 |

Закрытие этапов: words → 200 · phrases → 200 · dialogue → 200 · listen → 200 · speak → 200.
Окно после прохода: words done · phrases done · dialogue done · listen done · speak done · conversation current · неотвеченных карточек 0.

Судья окна (`speak_answer`; `literal` — своя строка обмена, её знакомое наполнение судит КОД; `forced` — та же строка с наполнением чужого каркаса, её окно судит МОДЕЛЬ):

- x1 [literal] «Ich brauche einen Termin.» → зачтено, окно «einen Termin» · решил код
- x2 [forced] «Ich habe einen Termin.» → не зачтено, окно «einen Termin» — «Ты сказал не симптом, а про запись на приём.» · решила модель gpt-5.4-mini-2026-03-17 · $0.0007
- x3 [forced] «Ich habe auch einen Termin.» → не зачтено, окно «einen Termin» — «Ты сказал не дополнительный симптом, а про встречу.» · решила модель gpt-5.4-mini-2026-03-17 · $0.0007

### Разговор

«Поговори с администратором» · ended · подсказки да · ходов 9 · конец **natural** · целей 7 из 7 · сказал сам 7 · понял все: да · минут 1 · ряд разговора в окне — done.

| # | кто | реплика (цель) | перевод (родной) | голос | кредиты |
|---|---|---|---|---|---|
| 1 | роль | Gern. Sagen Sie bitte den Grund. | Хорошо. Скажите, пожалуйста, причину. | elevenlabs:eleven_v3_conversational:4NejU5DwQjevnR6mh3mb:s50 | 8 |
| 2 | ученик · said | Ich brauche einen Termin. ✓p1 | — | — | — |
| 3 | роль | Was für Beschwerden haben Sie? | Какие у вас жалобы? | elevenlabs:eleven_v3_conversational:4NejU5DwQjevnR6mh3mb:s50 | 8 |
| 4 | ученик · said | Ich habe Halsschmerzen. ✓p2 | — | — | — |
| 5 | роль | Haben Sie auch Fieber? | У вас ещё и температура? | elevenlabs:eleven_v3_conversational:4NejU5DwQjevnR6mh3mb:s50 | 5 |
| 6 | ученик · said | Ich habe auch Fieber. ✓p3 | — | — | — |
| 7 | роль | Seit wann haben Sie das? | С каких пор это у вас? | elevenlabs:eleven_v3_conversational:4NejU5DwQjevnR6mh3mb:s50 | 6 |
| 8 | ученик · said | Seit drei Tagen. ✓p4 | — | — | — |
| 9 | роль | Wir haben morgen um zehn oder um vierzehn Uhr frei. | У нас есть свободное время завтра в десять или в четырнадцать часов. | elevenlabs:eleven_v3_conversational:4NejU5DwQjevnR6mh3mb:s50 | 13 |
| 10 | ученик · said | zehn Uhr passt mir. ✓p5 | — | — | — |
| 11 | роль | Ja, das notiere ich. Brauchen Sie noch etwas? | Да, я это запишу. Вам ещё что-нибудь нужно? | elevenlabs:eleven_v3_conversational:4NejU5DwQjevnR6mh3mb:s50 | 11 |
| 12 | ученик · said | Brauchen Sie meinen Namen? ✓p6 | — | — | — |
| 13 | роль | Ja, ich brauche Ihren Namen für die Buchung. Dann ist Ihr Termin morgen um zehn Uhr. | Да, мне нужно ваше имя для записи. Тогда ваш приём завтра в десять часов. | elevenlabs:eleven_v3_conversational:4NejU5DwQjevnR6mh3mb:s50 | 21 |
| 14 | ученик · said | Das ist gut, morgen um zehn. ✓p7 | — | — | — |
| 15 | роль | Gut, ich notiere es so. | Хорошо, я так и запишу. | elevenlabs:eleven_v3_conversational:4NejU5DwQjevnR6mh3mb:s50 | 6 |
| 16 | ученик · skip | — | — | — | — |
| 17 | роль | Gut, ich notiere es so. Wir sehen uns morgen. | Хорошо, я это записываю. До завтра. | elevenlabs:eleven_v3_conversational:4NejU5DwQjevnR6mh3mb:s50 | 11 |
| 18 | ученик · skip | — | — | — | — |
| 19 | роль | Auf Wiedersehen. | До свидания. | elevenlabs:eleven_v3_conversational:4NejU5DwQjevnR6mh3mb:s50 | 4 |

Откуда реплики ученика: фраза урока p1 (hints.ref) ×1; фраза урока p2 (hints.ref) ×1; фраза урока p3 (hints.ref) ×1; фраза урока p4 (hints.ref) ×1; фраза урока p5 (hints.ref) ×1; фраза урока p6 (hints.ref) ×1; фраза урока p7 (hints.ref) ×1; skip ×2.

### Отказы сторожей роли

Всего 2: `rejected_answer/early_end` ×1, `rejected_answer/own_line` ×1. **Страж перевода (`native_missing`): 0** — ниже у каждого отказа ответ, который он не пустил: перевод настоящий — отказ ложный.

- ход 17 · попытка 1 · страж реплики · `rejected_answer` / `early_end`
  - отказанный ответ: «Gut, ich notiere es so.» / reply_native «Хорошо, я так и запишу.» (журнал: duration_ms ≈ latency_ms, расхождение 12 мс, кандидатов 3)
- ход 17 · попытка 2 · страж реплики · `rejected_answer` / `own_line` · {"outcome":"kept"}
  - отказанный ответ: «Gut, ich notiere es so. Wir sehen uns morgen.» / reply_native «Хорошо, я это записываю. До завтра.» (журнал: duration_ms ≈ latency_ms, расхождение 15 мс, кандидатов 3)

### Деньги

| что | кредиты · символы · $ |
|---|---|
| голос дня 1 (40 строк) | 224 · 897 · $0.0448 |
| — реплика ученика (8) | 47 · 191 · $0.0094 |
| — реплика собеседника (8) | 70 · 278 · $0.0140 |
| — фраза (7) | 39 · 158 · $0.0078 |
| — фраза с наполнением (9) | 51 · 204 · $0.0102 |
| — слово (8) | 17 · 66 · $0.0034 |
| — голос `elevenlabs:eleven_v3_conversational:TWutjvRaJqAX89preB4e:s50` (32) | 154 · 619 · $0.0308 |
| — голос `elevenlabs:eleven_v3_conversational:4NejU5DwQjevnR6mh3mb:s50` (8) | 70 · 278 · $0.0140 |
| голос разговора (10 реплик) | 93 · 372 · $0.0186 |
| **голос всего** | **317 · 1269 · $0.0634** |

| модели | $ |
|---|---|
| план | $0.0183 |
| урок дня 1 (с починками и судьёй швов) | $0.0960 |
| судья окна (2 решений модели из 3, цена из `response.judge` карточек) | $0.0014 |
| разговор (ходы роли, `model_cost_usd` — с отказанными попытками) | $0.0120 |
| **модели всего** | **$0.1277** |

Сверка: `conversations.cost_usd` $0.0306 = модель $0.0120 + голос $0.0186 по журналу ходов — сходится (в счёт не прибавляется: голос в нём уже есть).
Сверка судьи: вызовов `judge` в журнале model_calls за окно прохода — 2 на $0.0014 (сюда попал бы и судья швов чужой сборки в той же базе).
