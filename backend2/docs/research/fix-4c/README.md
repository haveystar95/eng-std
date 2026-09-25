# FIX-4c — хвосты сервера после CLIENT-FIX-4: второй голос собеседника, подсказка в обоих режимах, род роли, заголовок по ролям, страж перевода

Наряд FIX-4c (25.09.2026). Ветка `fix-4c`, worktree `../backend2-fix4c`, сайдкар `wt_fix4c` (база
`wordtrainer_fix4c_test`). **Сделан и ВЫКАЧЕН 25.09**: `main` — до `b7510b2e` (код и документы), отчёт — следующий
коммит. Миграция одна — `plan_scenes.partner_voice_id` с бэкфиллом. Покупки — образцы голосов **$0.024** и одна живая
репетиция на e2e **$0.033935**: всего **$0.057935 из $0.40**. Наряд назначен на Opus; исполнен сессией на Opus 5.5.

Решения — DECISIONS пп. **414–419** и четыре записи в «Отменено»; канон — `docs/plan-v2.md` §11 («Правило роли v3.3»,
«Голос роли — голос сцены», «Подсказка в обоих режимах»); контракт — `docs/plan-api.md` (раздел «С FIX-4c (25.09)») и
`openapi/openapi.yaml`.

## 1. §1 Второй голос собеседника на пол

**Что было.** Голос собеседника выбирался по полу роли, а голос на пол был один: регистратор и врач репетиции (обе
женщины) звучали одинаково, и смену сцены на слух было не отличить (отчёт CLIENT-FIX-4 §7).

**Кандидаты и выбор Дена.** Одна фраза на кандидата — «Good morning. What brings you in today? Take a seat, please.»
(60 символов), модель и настройка пакета (`eleven_v3_conversational`, stability 0.5), то есть так, как голос звучит в
приложении. Файлы — `voices/`, ответы вендора построчно — `voices/samples.json`, инструмент — `tools/voice-samples.php`.

| пол | файл | id | имя в библиотеке | откуда | выбор |
|---|---|---|---|---|---|
| Ж | `female-1-talia.mp3` | `OZ0L6eISlOejga3XjDFt` | Talia — Warm Soft Guide | замена Default «Sarah» | |
| Ж | `female-2-maisie.mp3` | `QtY3JBOUKEB5xzrRfOKc` | Maisie — Friendly Casual Neighbor | замена Default «Matilda» | ✅ F2 |
| Ж | `female-3-jade.mp3` | `g7LVvkPWALzPxOQbF6OE` | Jade — Upbeat and Natural | замена Default «Jessica» | |
| Ж | `female-4-juniper.mp3` | `aMSt68OGf4xUZAnLpTU8` | Juniper — Grounded and Professional | библиотека, ConvoAI | |
| М | `male-1-eddie.mp3` | `l7kNoIfnJKPg7779LI2t` | Eddie — Helpful and Comforting | замена Default «Eric» | |
| М | `male-2-kellan.mp3` | `cymHWdiF8WjUCg6vvFxx` | Kellan — Casual Friendly Speaker | замена Default «Callum» | |
| М | `male-3-caleb.mp3` | `AaOhDHYJ1XLZk74lXhdE` | Caleb — Trusted Guide | замена Default «Chris» | ✅ M2 |
| М | `male-4-wyatt.mp3` | `FrS6cKLB1wg4WYgPa9GW` | Wyatt — Seasoned Mentor | замена Default «Bill» | |

**Откуда эти голоса.** У ключа ElevenLabs нет права `voices_read`: общая библиотека (`/v1/shared-voices`) и карточка
голоса отвечают `401 missing_permissions`, фильтровать библиотеку «тёплые, разговорные, разного возраста» нечем. Семь
кандидатов — официальные голоса, которыми ElevenLabs заменяет свои Default-голоса (сами Default истекают **31.12.2026**,
закреплять за сценой навсегда их нельзя), восьмой — ConvoAI-голос публичной страницы библиотеки. Id прочитаны с собственных
ссылок вендора. Голоса пакета, которые уже заняты (`4NejU5DwQjevnR6mh3mb`, `EnjklPXGBMNldCJ7jqkE`,
`TWutjvRaJqAX89preB4e`, `Nhs7eitvQWFTQBsf0yiT`), исключены. Покупка: 8 строк · 480 символов · 120 кредитов · **$0.024**.

**Как сделано.**
- Пакет: `generation.speech.voices.en.partner.female_2` / `male_2` — `SPEECH_VOICE_EN_PARTNER_FEMALE_2` /
  `SPEECH_VOICE_EN_PARTNER_MALE_2` (по умолчанию — выбор Дена; в `.env` боя тоже). `VoiceCatalog::partnerVoices()` —
  голоса 1 и 2 пола; `VoiceCatalog::forLanguage(…, $voice)` находит голос сцены по id (id, которого пакет больше не
  называет, — голос 1).
- Голос сцены — `plan_scenes.partner_voice_id` (varchar 64, nullable; `down` снимает колонку). Ставится **один раз, при
  приёме урока** (`BuildLessonHandler` → `PartnerVoices::cast`), вместе с полом роли: сцена берёт другой голос ближайшей
  более ранней сцены своего пола, нет такой — голос 1 (`PartnerVoiceRota`). Строки сцен плана запираются в порядке плана
  (`PlanRepository::sceneVoicesForUpdate`, `FOR UPDATE`) — два урока одного плана не возьмут голос по устаревшей картине.
  Урок, собранный заново (повтор сборки), голос сохраняет; урок, переписанный с другим полом роли, голос отпускает.
- **Отступление от буквы §1 (принято Деном 25.09).** Наряд — «при создании плана»; но пол роли в этот момент неизвестен,
  он приходит с уроком. Поэтому: голос — при приёме урока, чередование — по порядку сцен плана, строки — под блокировкой.
- Голос сцены держат все её реплики: урок (`LineToSay.voice` → ключ `LineSpeaker::voiceKeyFor(…, $voice)`), «Вспомнить» и
  повторение (индекс звука сцены — ключи по сцене и говорящему: `SceneVoices`, `SceneAudioIndex`), очередь озвучки
  (`SceneVoiceQueue`, `VoiceCast::voiceOf`), разговор (`ConversationCheckpoint::$partnerVoice` → `TurnSpeaker::say(…,
  $voice)`), инспекция (`TalkReport`, `VoiceTable` — строки `partner:<пол>:2`, `LessonReport` — `partner_voice_id`).
  Голос ученика — по полу профиля, как был.

**Бэкфилл** (в самой миграции, `PartnerVoiceBackfill`, идемпотентный). Буква наряда — «существующим сценам голос 1 своего
пола (то, чем они уже озвучены); переозвучки нет»; приёмка — «на e2e сцена 1 = F1, сцена 2 = F2», а сцены e2e-плана не
озвучены ни разу. Обе строки сходятся на правиле:
- сцена, у которой реплики собеседника уже озвучены, — **голос 1** её пола (тот, в котором лежат файлы);
- неозвученная сцена с уроком — **по правилу чередования**, как новая;
- сцена без урока — пусто: голос даст её урок.

Предел правила: озвученная сцена после неозвученной своего пола держит голос 1, даже если соседи от этого звучат одинаково,
— переозвучки нет (канон-тест держит и этот случай).

| база | озвучены → голос 1 | по правилу | без урока | всего |
|---|---|---|---|---|
| бой `wordtrainer` | **16** (14 Ж → F1 `4NejU5…`, 2 М → M1 `Enjkl…`) | 0 | 3 (`pending`) | 19 |
| e2e `wordtrainer_e2e_test` | 6 | 208 | 6 | 220 |

План репетиции `01M2QRH5MY…`: «Запись к врачу» (регистратор) → **F1** `4NejU5DwQjevnR6mh3mb`, «Приём у врача» (врач) →
**F2** `QtY3JBOUKEB5xzrRfOKc`. На бою старые сцены звучат, как звучали; три сцены без урока получат голос при сборке — и
женская после женской будет F2.

**Канон** (`tests/Unit/Plan/PartnerVoiceRotaTest.php`, `tests/Feature/Plan/PartnerVoiceTest.php`,
`ConversationApiTest`, `OneVoiceVendorTest`): роли Ж, Ж, М, Ж → F1, F2, M1, F1 — и правилом, и через сборку урока, как на
бою; повтор сборки голос не меняет; бэкфилл (озвученная — голос 1, неозвученная — по правилу, без урока — ничего, второй
прогон ничего не меняет); две женщины репетиции звучат двумя голосами по сценам (ключи голоса ходов); пакет — шесть разных
голосов.

## 2. §2 `hints` в обоих режимах

`ConversationViews::hint()` больше не смотрит на режим: `hints.sentence` / `target` / `scene_id` / `ref` приходят и в «Без
подсказок» — по тем же правилам (ход ученика, в сцене есть несказанное, разговор не кончен). `hints.enabled` остаётся
флагом режима; плашку до «Подсказать» прячет телефон. Запись хода роли не меняется: её `hint_native` пишется только в режиме
с подсказками.

Сборка (21) в «Без подсказок» подсказку сервера не читает (`ConversationController.hint`: при `enabled = false` — своя цель
`_blindTarget` после «Подсказать») — на телефоне ничего не сломалось. Следующему клиенту — брать подсказку сервера: она знает
цель, к которой вела реплика роли (`opens`).

**Канон** (`ConversationApiTest`): в «Без подсказок» подсказка та же, что другой режим получает на том же ходе; после
«почти» — с точной строкой `target`.

## 3. §3 `window.sources[].partner_gender`

`window.sources[]` несёт `partner_gender` (`male` | `female`) — пол, который урок сцены дал роли; сцена, урок которой
писался до полов голосов, — `female` (пол голоса по умолчанию, тот, которым она звучит). Для «Регистратор начнёт первым» /
«Медсестра начнёт первой». OpenAPI — поле обязательное, enum. Фикстуры `docs/fixtures/day-doctor*.json` перегенерированы
(`UPDATE_SESSION_FIXTURES=1`) — разница ровно в новом поле; тесты клиента, читающие фикстуру (`test/data/plan`,
`day_window_system_test`, `talk_entry_test`), — 106 passed.

**Канон** (`WindowSourcesTest`): у двух сцен окна — свой род каждой, по уроку; без пола урока — `female`.

## 4. §4 `talk_title_native` по всем ролям

`NativeStrings::talkTitle()` принимает роли разговора в порядке сцен: одна — как было («Поговори с врачом»), две —
«Поговори с регистратором и врачом», три и больше — «Поговори с регистратором, врачом и медсестрой». Формы — из того же
правила, что дало «с врачом» (`InstrumentalRole`); предлог — по первой роли и один раз («со стоматологом и врачом»); роль,
повторённая сценами, — один раз; роль, которую правило не склоняет уверенно, делает весь заголовок нейтральным («Поговори с
собеседниками»). uk — «Поговори з лікарем і секретарем», en — «Talk to the receptionist, the doctor and the nurse». Ряд окна
и документ разговора получают одну и ту же строку. Склонение события («к приёму у врача») не делалось — оно придёт с
промптом плана v2.1.

**Канон** (`ConversationTargetsTest`, `ConversationApiTest`): одна, две, три, четыре роли; «со»; повтор роли; несклоняемая
роль; uk/en; ряд окна и документ репетиции — «Поговори с регистратором и врачом».

## 5. §5 «Сказал сам 1 реплику из 9» — находка стенда, правило верно

`DayHighlights` в первой строке «Что было хорошо» считает **свои карточки речи дня**: у репетиции это «Вспомнить» (девять
пересказов реплик ученика, `speak_retell`; обзорная карточка `recall_scenes` — не реплика), у повторения — «Повторение»
(`speak_answer`); подсказка засчитывается как «сказал». У разговора — свои две строки (конструкции, понятые вопросы).
«Дня без своих карточек речи» среди нынешних маршрутов нет, а день, где их не сдавали, о репликах молчит (никакого «0 из N»).

На кадре `live/13` отчёта CLIENT-FIX-4 девять карточек «Вспомнить» дня 3 были отвечены **21.09** (1 зачтена, 8 пропущены),
а 25.09 заново пройден только разговор — отсюда «1 из 9». Код не менялся; правило закреплено тестом
`tests/Unit/Plan/DayHighlightsTest.php` (репетиция считает свои «Вспомнить», разговор — свои строки; повторение — своё
«Повторение»; без карточек речи — ни строки о репликах).

## 6. §6 Страж перевода и промпт v3.3

**Правило** (`ReplyNative::missing`): `reply_native` — не перевод, если он пуст; совпадает с `reply_target` без регистра,
знаков и пробелов; или букв письменности родного языка пары (`script_letters` пакета) в нём меньше половины (латиница в
русской строке — «Ibuprofen», «MRI» — его не валит). У языка без записанных букв — только первые две проверки.

**Ход.** Проверка — последней в `ConversationMoves::fault()`: отказ `native_missing`, как остальные, — один перезапрос с
причиной («REDO: native_missing — your reply_native was no translation of your reply: it was empty, in TARGET_LANGUAGE, or
the same words. Answer this move again as YOUR_ROLE, and write reply_native as reply_target translated into NATIVE_LANGUAGE,
faithful to its meaning»), отбраковка в `conversation_rejections`, счётчик `conversation.native_missing`. Второй провал —
реплика остаётся, перевод пустой (`text_native` = `""`, в журнале `outcome: blanked`, счётчик
`conversation.native_missing_blanked`). Если второй ответ отбракован по другой причине, а перевода в нём тоже нет, — реплика
чинится своим путём, а перевод снимается тем же правилом (`translated()`). Сборка (21) печатает `""` как «перевода нет»
(`_text` → null).

**Промпт** `conversation_agent.v3.3` = v3.2 + одна фраза в OUTPUT; sha256
`1a39118ed95763d8dc94247d1b9caa3c02ea100283ca068b28a9464347f5377b`:

```diff
-CONVERSATION AGENT — v3.2
+CONVERSATION AGENT — v3.3
 …
-OUTPUT: only {…} — reply_native is your reply translated into NATIVE_LANGUAGE, end is one of "no", "natural", "declined"; the first character { and the last }.
+OUTPUT: only {…} — reply_native is your reply translated into NATIVE_LANGUAGE, end is one of "no", "natural", "declined"; the first character { and the last }. reply_native is reply_target translated into the learner's NATIVE_LANGUAGE — faithful to its meaning, never in TARGET_LANGUAGE and never a retelling.
```

Буква наряда — «never in English». Записано «never in TARGET_LANGUAGE»: у нынешних планов язык цели и есть английский, а у
плана с родным английским «never in English» противоречило бы NATIVE_LANGUAGE. v3.2 удалён; `PlanPromptFiles`, фейк,
реестр (`docs/prompts/REGISTRY.md`) и счётчики — под v3.3. Пересказ вместо перевода кодом не ловится — это правило промпта.

**Канон** (`ReplyNativeTest`, `ConversationPromptTest`, `ConversationApiTest`): совпадение, другая письменность, пусто,
латиница в родной строке, язык без букв; промпт = v3.2 + одна фраза; через API — перезапрос приветствия с причиной и
отбраковкой в журнал, второй провал хода — перевод пустой, три строки журнала, оба счётчика.

## 7. Живая репетиция на e2e

После выката, кодом `main`: `php -S 127.0.0.1:8012` в `wt_app_e2e` (`DB_DATABASE=wordtrainer_e2e_test`,
`QUEUE_CONNECTION=sync` — ни одна задача не уходит в Horizon боя, `SPEECH_ENABLED=true`). План `01M2QRH5MY…`, день 3
(qa-gen3-doctor), «Ещё раз» в режиме **«Без подсказок»** — чтобы §2 проверился на каждом документе; ходы ученика — те же,
что у FIX-4 и FIX-4b, слово в слово. Инструмент — `tools/live-rehearsal.php`; все ответы API — `live/live-rehearsal.json`;
вывод — `live/transcript.txt`. Разговор `01M3CF7Z9J75T0EK4V1648XVSF`; звук (10 файлов) — в `storage` `main`.

```
окно дня 3 · sources:
   01M2QRHF9M86KAM8JW8XYRAWDR «Запись к врачу» · partner_gender female
   01M2QRHF9M69AMBE2XGD18KQ8P «Приём у врача» · partner_gender female
   ряд разговора: «Поговори с регистратором и врачом»

разговор 01M3CF7Z9J75T0EK4V1648XVSF · «Поговори с регистратором и врачом» · ходов 9
 1 РОЛЬ  [начало «Запись к врачу»] Hello. What seems to be the problem with his back?
         Здравствуйте. Что у него с спиной?
   hints: enabled false · «У него болит поясница.» · Запись к врачу/p1
 2 УЧЕНИК It hurts in his lower back. · сказано: p1
 3 РОЛЬ  When did it start?
         Когда это началось?
   hints: enabled false · «Началось три дня назад.» · Запись к врачу/p2
 4 УЧЕНИК It started two days ago. · сказано: p2
 5 РОЛЬ  Is the pain sharp, or more of a dull ache when he bends?
         Боль острая или больше тупая, когда он наклоняется?
   hints: enabled false · «Боль острая, когда он наклоняется.» · Запись к врачу/p3
   GET документа: enabled false · «Боль острая, когда он наклоняется.» · p3
 6 УЧЕНИК The pain is sharp when she bends.
 7 РОЛЬ  Does he have a fever?
         У него температура?
   hints: enabled false · «Боль острая, когда он наклоняется.» · точная строка «The pain is sharp when he bends.» · Запись к врачу/p3
 8 УЧЕНИК The pain is sharp when he bends. · сказано: p3
 9 РОЛЬ  Thank you. He doesn't have a fever, right?
         Спасибо. У него нет температуры, верно?
   hints: enabled false · «Температуры у него нет.» · Запись к врачу/p4
10 УЧЕНИК He doesn't have a fever. · сказано: p4
11 РОЛЬ  [прощание «Запись к врачу»] Thank you. Please see a doctor if it gets worse.
         Спасибо. Пожалуйста, обратитесь к врачу, если станет хуже.
12 РОЛЬ  [начало «Приём у врача»] Good morning. What seems to be the problem with your son today?
         Доброе утро. Что случилось с вашим сыном сегодня?
   hints: enabled false · «У него температура и болит горло.» · Приём у врача/p1
13 УЧЕНИК He has a fever and a sore throat. · сказано: p1
14 РОЛЬ  How long has he had these symptoms?
         Как долго у него эти симптомы?
   hints: enabled false · «Он болеет уже три дня.» · Приём у врача/p2
15 УЧЕНИК He's been sick for three days. I gave him paracetamol. · сказано: p2 · ещё вспомнил: p4
16 РОЛЬ  Yes, thank you. The paracetamol is fine.
         Да, спасибо. Парацетамол подходит.
   hints: enabled false · «Мне сказать вам его температуру?» · Приём у врача/p3
17 УЧЕНИК Should I tell you his temperature? · сказано: p3
18 РОЛЬ  [прощание «Приём у врача»] No, that's okay. We can finish here. Goodbye.
         Нет, это не нужно. Мы можем закончить здесь. До свидания.

итог: ended · natural · ended_by_limit false · 7 из 7 · ещё вспомнил p4
```

**Голос каждого хода роли** (`conversation_turns.audio_voice_key`; на проводе — только `voice: partner`):

| ход | сцена | голос |
|---|---|---|
| 1 (начало), 3, 5, 7, 9, 11 (прощание) | «Запись к врачу» — регистратор | **F1** `elevenlabs:eleven_v3_conversational:4NejU5DwQjevnR6mh3mb:s50` |
| 12 (начало), 14, 16, 18 (прощание) | «Приём у врача» — врач | **F2** `elevenlabs:eleven_v3_conversational:QtY3JBOUKEB5xzrRfOKc:s50` |

| ожидание приёмки | итог |
|---|---|
| §1 голос ходов: сцена 1 = F1, сцена 2 = F2 | ✅ 6 из 6 ходов регистратора — F1, 4 из 4 ходов врача — F2; смена — ровно на границе сцен (ход 12) |
| §2 `hints` при `hints_enabled = false`, проверка GET | ✅ во всех 8 документах, где ход за учеником: `enabled: false`, `sentence` + `scene_id` + `ref` (после «почти» — и `target`); `GET /plans/{id}/conversation/{id}` после хода 4 — то же |
| §3 `sources[].partner_gender` у обеих сцен | ✅ `female` · `female` |
| §4 `talk_title_native` | ✅ «Поговори с регистратором и врачом» — и в ряду окна, и в документе разговора |
| §6 перевод у каждой реплики роли | ✅ 10 из 10 по-русски; отказов `native_missing` — 0 (на коротких «Когда это началось?», «У него температура?» страж не сработал ложно) |

**Отбраковки** (`conversation_rejections`): ход 9 — `own_line` дважды (роль повторила свой вопрос хода 7 «Does he have a
fever?»; второй ответ оставлен, `outcome: kept`) — страж FIX-4b, в ожидания FIX-4c не входит.

**Цена.** Модель: 11 вызовов (1 перезапрос — ход 9) — **37 443 → 595 токенов** (из них 26 112 из кэша), **$0.013135**
(`model_calls` сходится с `conversation_turns` до токена). Голос: **418 символов · 104 кредита · $0.0208**. Репетиция —
**$0.033935**; наряд целиком — $0.024 + $0.033935 = **$0.057935 из $0.40**.

**Замечено, в ожидания наряда не входит** (в ROADMAP «Хвосты FIX-4c»):
- ход 1: перевод «Что у него с спиной?» — грамматика перевода («со спиной») на модели; страж проверяет язык, не грамматику;
- ход 9: роль повторила свой вопрос (`own_line` ×2) — перезапрос страж FIX-4b отработал;
- ходы 16–18: на «I gave him paracetamol» врач отвечает «The paracetamol is fine», а на «Should I tell you his temperature?»
  — «No, that's okay. We can finish here. Goodbye.»: сцену закрыл сервер (все цели сказаны), и прощание отклонило
  предложение ученика вместо того, чтобы его принять.

## 8. Что удалено

- `app/Modules/Plan/Infrastructure/Prompt/conversation_agent.v3.2.md` (git видит его переименованным в v3.3 и изменённым;
  v3.2 — в истории).
- Проверка режима в `ConversationViews::hint()` — «в „Без подсказок" подсказки нет».
- `NativeStrings::talkTitle(string)` по одной роли — теперь `talkTitle(list)` по всем ролям разговора.
- Ключи голоса звука дня по одному говорящему на весь день — теперь по сцене и говорящему (`SceneAudioIndex`, `SceneVoices`).
- Ожидание «в пакете четыре голоса» (`OneVoiceVendorTest`) — шесть.
- В DECISIONS «Отменено»: `conversation_agent.v3.2`; «один голос собеседника на пол» (п. 318 в части собеседника); «`hints`
  пусты в „Без подсказок"»; «заголовок разговора по роли первой сцены».

## 9. Ворота и выкат

Ворота — **один раз, в конце**, на коде, ставшем `b7510b2e`, в сайдкаре ветки (`composer check`, база
`wordtrainer_fix4c_test`). Первый прогон дал 3 падения — оба ожидаемые от контракта наряда, починены до коммитов:
фикстура дня (новое поле `partner_gender` — перегенерирована, разница только в нём) и `OneVoiceVendorTest` (пакет 4 → 6
голосов). Второй прогон — зелёный:

| что | итог |
|---|---|
| `composer check` | lint:openapi — оба файла ok; deptrac — **0 нарушений** (7 936 allowed, 3 uncovered); PHPStan — **No errors** (1 721 файл); Pest — **2 515 passed, 24 317 assertions**, 119,4 с (параллельно, 10 процессов) |
| `flutter analyze` | **No issues found** (клиент наряд не трогал) |
| тесты клиента на новой фикстуре | `test/data/plan`, `day_window_system_test`, `talk_entry_test` — **106 passed** |
| invariant-reviewer | **CLEAN** (новые Domain-сервисы `PartnerVoiceRota`, `ReplyNative` — чистый PHP; бэкфилл пишет только `plan_scenes`, не журнал; подсказка в «Без подсказок» не участвует в судействе) |

Коммиты — `git -C` в worktree (хук ворот worktree не видит); общие файлы разложены по параграфам ханками (`hash-object` +
`update-index` по явным путям), единственная правка после ворот — одна фраза приёмки CLIENT-FIX-4 в `design-map.md`.

**Выкат 25.09:**

1. Бой, только чтение: 19 сцен — 14 готовых женских (все озвучены F1), 2 мужские (озвучены M1), 3 без урока.
2. `DB=wordtrainer scripts/db-backup.sh --safety` → `wordtrainer-20260925-172040.sql.gz` (21 МБ).
3. `migrate --pretend`, затем `migrate --force` боя **кодом ветки** в её сайдкаре (`-w /wt -e DB_DATABASE=wordtrainer`) —
   43 мс; бэкфилл: 14 → F1, 2 → M1, 3 → пусто (озвучены 16 / по правилу 0 / без урока 3).
4. `git merge --ff-only fix-4c` в `main` (`main` не уходил от `c11f651a`, rebase не нужен) → `b7510b2e`. `wt_app`,
   `wt_horizon`, `wt_scheduler`, `wt_app_e2e` исполняют рабочее дерево `main` — влитие и есть выкат.
5. `.env` боя: `SPEECH_VOICE_EN_PARTNER_FEMALE_2`, `SPEECH_VOICE_EN_PARTNER_MALE_2` (те же id, что по умолчанию в
   конфиге); `docker compose restart horizon` → `Horizon is running`; `wt_app` читает оба вторых голоса.
6. `scripts/stamp-build.sh` → `/health` отдаёт `b7510b2e`; туннель — 200.
7. e2e: бэкап `wordtrainer_e2e_test-20260925-172152.sql.gz` (2,5 МБ) → `migrate` (155 мс; озвучены 6, по правилу 208,
   без урока 6). `wordtrainer_test` → `migrate` (31 мс).
8. Живая репетиция (§7).
9. Отчёт, handoff — коммитом на `main`; `stamp-build.sh` — `/health` отдаёт хеш этого коммита.
10. Стенд ветки снесён: worktree `../backend2-fix4c`, ветка `fix-4c`, сайдкар `wt_fix4c`, база `wordtrainer_fix4c_test`
    и её параллельные копии.

Админка не пересобиралась: её код наряд не трогал.

## 10. Коммиты (`main`)

| хеш | что |
|---|---|
| `6ac4b086` | §1 второй голос собеседника на пол: голос сцены `partner_voice_id`, чередование, бэкфилл |
| `217f0134` | §2 `hints` в документе разговора в обоих режимах |
| `12720b96` | §3 `window.sources[].partner_gender` |
| `8a20dfe3` | §4 `talk_title_native` по всем ролям разговора |
| `eec3230f` | §5 канон `DayHighlights`: «Сказал сам» репетиции — правило верно |
| `53a85f3b` | §6 страж перевода `native_missing`, промпт `conversation_agent.v3.3` |
| `b7510b2e` | документы: контракт, OpenAPI, канон §11, реестр промптов, DECISIONS пп. 414–419, ROADMAP, design-map (строки отчёта CLIENT-FIX-4 §7 и одна фраза о правках его приёмки §9) |
| (этот) | отчёт, образцы голосов, живая репетиция, handoff |

## 11. Телефону и хвосты

**Телефону** (следующий клиентский наряд; раздел `docs/plan-api.md` «С FIX-4c (25.09)»):
- «{Роль} начнёт первым / первой» — по `window.sources[].partner_gender`;
- «Подсказать» в «Без подсказок» — подсказка сервера (`hints.sentence` / `target` / `ref`) вместо своего подбора цели;
- пустой `text_native` у реплики роли — «перевода нет» (сборка (21) так и делает);
- `talk_title_native` репетиции теперь длиннее («Поговори с регистратором и врачом») — (21) печатает как пришло.

**Хвосты** (найдено, в работу не бралось; ROADMAP):
- статические фикстуры `docs/fixtures/day-rehearsal.json`, `day-review.json`, `conversation-*.json` — снимки прошлых
  нарядов, серверные тесты их не держат: в них нет `partner_gender`, и заголовок репетиции — «Поговори с регистратором».
  Освежить вместе с клиентским нарядом, который начнёт читать новые поля;
- `hints.native` всё ещё на сервере — снять, когда (21) и новее стоят везде;
- грамматика перевода роли («с спиной») и прощание, отклоняющее предложение ученика (§7);
- тесты голоса разговора (`ConversationApiTest`, новый тест §1 тоже) пишут 40-байтные mp3 фейкового синтезатора в
  настоящий `storage` дерева, где идут (нет `Storage::fake`) — мусор, не данные; родня хвоста «изоляция тестов озвучки».
