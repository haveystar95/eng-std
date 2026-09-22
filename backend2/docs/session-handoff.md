# Session handoff — snapshot

> **Overwrite this file each session. Snapshot of current state, not a growing log.**
> Read with `CLAUDE.md`, `ARCHITECTURE.md`, `.claude/skills/`, `deptrac.yaml`, `docs/ROADMAP.md`.
> **`docs/DECISIONS.md` (в корне репозитория) читается ПЕРВЫМ шагом любого наряда** — это реестр
> действующих решений; если постановка ему противоречит, стоп и доклад архитектору.
> Канон плана: **`docs/plan-v2.md`**; контракт: **`docs/plan-api.md`** + `openapi/openapi.yaml`
> (теги `Plans`, `Devices`); модуль: `app/Modules/Plan/README.md`.

Branch: `back-tails-2` (worktree `../backend2-tails2`) — **в `main` НЕ влита** (решение Дена 22.09: бой исполняет рабочее
дерево `main`, влитие = выкат). Last updated: 2026-09-22. Наряд дня — **BACK-TAILS-2** (десять хвостов дня и разговора
после CONV-2 и CLIENT-CONV-1b; `backend2/` + `docs/DECISIONS.md`). Коммиты ветки: код `5987b16e`, документы `890eac9f`,
e2e на ветке `543cca72`, §11 в ROADMAP `55816897`, дополнение 1c (эхо старой формы): `5b357b61` + `adb04cdb`, затем эхо
к нынешней форме целиком — код `71b08b08` и документы следом. Отчёт — `docs/research/back-tails-2/README.md`; решения — DECISIONS пп.
**379–388**, шесть записей в «Отменено», «Спорное» п. 2 закрыто. **Бой и `main` не тронуты**; e2e — на ветке (§3).

## 1. Что сделано

- **Лестница «Фраз»** — пол «1 узнавание + 2 круга + своё»; ступени: третье узнавание → третий круг → второе узнавание →
  стоп-сигнал в журнал сборки (`plan.phrases_over_ceiling`). Живые дни («врач» e2e, зал Дена): 33 → 31 мин карточек.
- **`targets[].said` «как человек»** — `PhraseUse`: ключевые слова цели по основам, любой порядок, пропуск от четырёх,
  слово модели — вторая опора, сказанное не снимается. Зал Дена: 0 → 3 из 7.
- **Этап `repetition`** у дня повторения (миграция данных обратимая).
- **Окно**: `stages[].minutes` у всех рядов, `stages[].targets` у ряда разговора, `sources[]`, `talk_again`.
- **«Вспомнить»** — реплика в четыре поля; **имя сцены — одно, из плана**; `plan:reconcile-scenes`.
- **Повтор разговора** на пройденном дне; кап 3 в сутки ученика → 409 `plan_conversation_replay_limit`.
- **`minutes_spent`** = карточки + разговор, прошедший этап (с хода прохождения); повторы не входят.
- **Эхо роли** — вторая сверка против последнего хода (доля предложения, обмен лиц), перезапрос, вырез, нейтральный ход;
  `conversation_agent.v2.1`.
- **`partner_line` у эха снят.**

## 2. Ворота и деньги

Ворота на коде ветки (сайдкар `wt_tails2`): OpenAPI ok ×2, deptrac 0, PHPStan 0, **Pest 2416 passed** (`--parallel`),
invariant-reviewer — CLEAN, мутации 23 из 23. Живой прогон — копия e2e, **$0.040269** (36 вызовов
`gpt-5.4-mini`), голос 0, генераций 0.

## 3. Выкат

- **e2e — на ветке, без влития** (22.09, 02:07 UTC): бэкап `wordtrainer_e2e_test-20260922-050706.sql.gz` → `migrate`
  кодом ветки (657 карточек → `repetition`) → `plan:reconcile-scenes --apply` (1 лист, повтор — 0) → сайдкар `wt_app_e2e`
  (:8010) поднят на worktree (`/wt`, тот же образ и окружение, `storage/app/private` основного дерева). Новый контракт на
  стенде проверен (отчёт §1 «e2e на ветке», `live/e2e-verify.txt`). Боевой `wt_horizon` не трогался.
- **Бой — ОДНИМ заходом по команде Дена после сборки (19):** `scripts/db-backup.sh --safety` → `migrate` боя (кодом ветки)
  → ff-влитие в `main` → `docker compose restart horizon` → `php artisan plan:reconcile-scenes --apply` (ждать 1 лист —
  план Дена `01M2TSRM…`, 2 сцены) → сайдкар e2e обратно на `main` → worktree снести → `wordtrainer_test` догнать. Миграции
  на бою: этап `repetition` — 0 карточек (`speak` на днях повторения нет); эхо старой формы — ожидаемо 10 строк, три
  ключа (`own_line`, `expected_text`, `speech_mode` нынешней формы; `coverage_min` снят у 9; `partner_line` на месте). Перед ff — rebase на `main` (он ушёл на три коммита клиента 1c, пересечений нет);
  к шагу с сайдкаром — `migrate` e2e (эхо старой формы там не прогнано, 1 карточка). Команды и вывод — в §9 отчёта.

## 4. Проверено живьём / только кодом

| что | где |
|---|---|
| лестница на живых днях | «врач» e2e и зал Дена (бой) — раздача в памяти, только чтение (отчёт §3) |
| зачёт фраз «как человек» | стенограммы Дена (зал, «Просмотр жилья») — отчёт §2; живой прогон |
| эхо роли, v2.1, роли, рост `said`, минуты дня, p50/p95 | копия e2e: повторение 4 хода, репетиция 2 сцены × 10 ходов, день-сцена (отчёт §4) |
| `repetition`, `sources`, `minutes`, `targets`, одно имя, `reconcile-scenes` | копия e2e (фикстуры `day-review.json`, `day-rehearsal.json`) |
| **только кодом**: озвучка хода (голос выключен на стенде), повтор на пройденном дне и кап 409 (тесты), клиент на новых полях | — |

## 5. Решения, которые не переделывать

- **Лестница: отдаётся второе узнавание, не второй круг** (п. 379) — выбор архитектора; стоп-сигнал — предупреждение в
  журнале, не откат.
- **Цели разговора судит `PhraseUse`, не `SpeechMatch`** (п. 380); карточки — своим правилом, это другой канал.
- **Эхо меряется долей ПРЕДЛОЖЕНИЯ роли, а не перекрытием строк** (п. 387) — перекрытие пропускало живое эхо.
- **Один селектор целей дня** — окно и `POST …/conversation` зовут один `ConversationMaterial` (п. 382).
- **Минуты дня — один разговор, прошедший этап** (п. 386).
- Прежние: роль говорит только свою сторону (п. 366), этап проходит первый собственный конец (п. 367), ручной UPDATE
  журнала запрещён — только `plan:reconcile-talks`.

## 6. Известные хвосты (ROADMAP, разделы BACK-TAILS-2 и CONV-2)

- §11 наряда: колонка `has_conversation` и рубильник стоят — на бою 4 дня `in_progress` без разговора на удалённых
  планах; решение архитектора;
- роль переспрашивает уже сказанное (не эхо — невнимание); кандидат в следующую версию роли;
- смена лица I → we в цели из трёх ключевых слов не засчитывается;
- `replayAccepted` клиента строже сервера (CONV-2 §8 п. 1) — клиентский наряд;
- стенд e2e: сцена «Запись к врачу» плана 1b без своего дня (`sources[].day_number = null`).

## 7. Стенд e2e после наряда

Живой прогон шёл на копии `wordtrainer_bt2_e2e_test` (дни 2–3 плана `01M2QRH5…` там пересданы и пройдены, разговоры дня 1
плана `01M2H13E…` пересняты). Сам `wordtrainer_e2e_test` — на ветке: мигрирован, лист репетиции плана `01M2QRH5…`
переименован; его данные в остальном как оставил 1c (все дни обоих «врачей» закрыты). Миграция эха старой формы
(`2026_09_22_110000_bring_old_echo_cards_to_todays_form`) на e2e НЕ прогнана — 1 карточка ждёт. Сайдкар `wt_app_e2e` исполняет worktree — **снос worktree
сломает e2e**, пока сайдкар не возвращён на `main` (шаг 6 выката на бой).

## 8. Ждёт Дена / архитектора

- команда на бой после сборки (19);
- §11 — дни удалённых планов (выше);
- с прошлых нарядов: сумма панели OpenAI за 17.09 UTC (GEN-3 §4a); пересборка дня 2 плана NKKGFF с голосом (GEN-3 §6).

## 9. BACK-TAILS-2 → клиент

Все поля — аддитивные, кроме снятого `partner_line` у эха (читать `own_line`) и нового этапа `repetition`. Схемы —
`PlanWindowStage`, `PlanConversationTarget`, `PlanDayWindow.sources`, `PlanCard`; живые примеры — `docs/fixtures/`
(`day-review.json`, `day-rehearsal.json`, `day-doctor*.json`, `conversation-*.json`).

**1. Этап `repetition`.** Полный перечень `stage` — `words`, `phrases`, `dialogue`, `listen`, `speak`, `recall`,
`repetition`, `conversation` (в `stages[]` дня, в `window.stages[]`, в карточках, в `POST …/stages/{stage}/close`). День
повторения раздаёт свои `speak_answer` / `speak_echo` / `speak_retell` в `repetition` («Повторение», кадр 37-2), не в
`speak`. Клиенту больше не угадывать этап по типу дня. Пример (`day-review.json`):

```json
{"stages": [
  {"stage": "repetition", "state": "current", "done_count": 0, "total": 7, "minutes_left": 5, "minutes": 5, "share": 0.0,
   "talk_title_native": null, "scenes_count": null, "targets": null},
  {"stage": "conversation", "state": "locked", "minutes": 6, "talk_title_native": "Поговори с врачом", "scenes_count": 1,
   "targets": ["…"]}
]}
```

**2. `window.stages[].minutes`** — у КАЖДОГО ряда, в любом состоянии, у всех типов дней: «около N минут» у запертых
рядов 37-1/37-2. `minutes_left` — как было, только у `current`.

**3. `window.stages[].targets`** — у ряда `conversation` (у рядов карточек — `null`): «Скажи в разговоре» ДО разговора.
Та же форма, что `targets[]` документа разговора, и ровно тот список, с которым `POST …/conversation` начнёт разговор.
`said` — где эту фразу оставил ПОСЛЕДНИЙ разговор дня (повтор тоже); `false` везде, пока разговора не было. Репетиция —
цели по всем сценам, повторение — по своим. Пример (`day-rehearsal.json`, две цели из семи):

```json
{"stage": "conversation", "state": "locked", "done_count": null, "total": null, "minutes_left": null, "minutes": 6,
 "share": 0.0, "talk_title_native": "Поговори с регистратором", "scenes_count": 2,
 "targets": [
   {"scene_id": "01M2QRHF9M86KAM8JW8XYRAWDR", "ref": "p1", "text_target": "It hurts in his lower back.",
    "text_native": "У него болит поясница.", "said": false},
   {"scene_id": "01M2QRHF9M86KAM8JW8XYRAWDR", "ref": "p2", "text_target": "It started three days ago.",
    "text_native": "Началось три дня назад.", "said": false}
 ]}
```

**4. `window.sources[]`** — «Из каких сцен» / «Из каких дней»: `{scene_id, title_native, day_number}`. День-сцена — своя
сцена; повторение — сцены двух повторяемых дней; репетиция — все сцены плана. Порядок — порядок сцен плана (= порядок,
в котором их проходят «Вспомнить» и разговор). Фото — `Plan.scenes[].image` по `scene_id`. **`day_number` может быть
`null`** — у сцены без своего дня (в плане генератора так не бывает; на стенде e2e 1b — «Запись к врачу»): печатать сцену
без «день N». Пример (`day-rehearsal.json`):

```json
{"sources": [
  {"scene_id": "01M2QRHF9M86KAM8JW8XYRAWDR", "title_native": "Запись к врачу", "day_number": null},
  {"scene_id": "01M2QRHF9M69AMBE2XGD18KQ8P", "title_native": "Приём у врача", "day_number": 1}
], "talk_again": false}
```

**5. `window.talk_again`** — «Повторить разговор»: `true`, когда этап разговора пройден и день показывает окно (идёт или
пройден). Нажатие — `POST …/days/{n}/conversation`: на ЗАКРЫТОМ дне это разрешено и приходит `replay: true`; день, его
итог и минуты не меняются. Не больше 3 повторов на день плана в сутки ученика — сверх:

```json
{"status": 409, "code": "plan_conversation_replay_limit",
 "meta": {"day": 1, "replays_per_day": 3, "retry_after_utc": "2026-09-22T21:00:00Z"}}
```

`allowed_action` окна — как было (`again` — «Говорю сам» / «Повторение» ещё раз по карточкам).

**6. `targets[].said` в документе разговора** — правило сервера «как человек» (ключевые слова фразы в любом порядке и
форме, пропуск от четырёх, слово роли — вторая опора). Клиенту менять нечего: `said` и `turns[].phrases_used` приходят
готовыми; `phrases_used` стоит на ходе УЧЕНИКА.

**7. `recall_scenes`** — реплика листа ровно `{ref, text_target, text_native, audio}` (служебных `step`, `frame_ref`,
`filler_index`, `key` нет); сцена называется, как в плане. Пример (`day-rehearsal.json`, сцена, одна реплика):

```json
{"scene_id": "01M2QRHF9M86KAM8JW8XYRAWDR", "title_native": "Запись к врачу", "title_target": "Booking",
 "lines": [{"ref": "x1b", "text_target": "It hurts in his lower back.", "text_native": "У него болит поясница.",
            "audio": {"ref": "x1b", "url": null, "duration_ms": null, "voice": "learner"}}]}
```

**8. `speak_echo`** — `partner_line` СНЯТ; звук и текст эха — `own_line` (`exchange`, `own_line`, `expected_text`,
`speech_mode`, `pause_ms`, `scene_id`). Сборка 1.0.0 (18) эхо читает из `partner_line` — поэтому на бой только после (19).
Эхо, сданное ДО CONV-2 (10 карточек на бою, закрытые дни и удалённые планы), миграция данных приводит к нынешней форме:
`own_line` — реплика ученика своего обмена, `expected_text` — её текст, `speech_mode` — `repeat`, `coverage_min` снят;
такая карточка равна свежей раздаче своего обмена, кроме оставленного `partner_line` (его клиент не читает).

**9. `minutes_spent`** дня — карточки плюс разговор, прошедший этап, с того хода, которым он этап прошёл: 30-7 и закрытый
день говорят одно число; повторы не входят.

**10. Эхо роли** — сервер сам не даёт роли повторить сказанное учеником (перезапрос, вырез, в крайнем случае «I see.
Please go on.»); клиенту ничего не делать.

Что клиенту ещё подхватить из CONV-2 §8 (не закрыто): `SessionRules.replayAccepted` строже сервера у `speak_answer`;
новые поля CONV-2 (`targets[]`, `talk_title_native`, `scenes_count`, `replay`, `rescue.text_target`, `hints.native`
придаточным, `phrases_used` с текстом, `highlights` до «Закрыть день», `heard` у судьи, `phrase_intro.usage`) — если 1c их
ещё не взял. Фикстуры `conversation-*.json` пересняты этим нарядом (golden-снимки клиента, читающие их, сдвинутся).
