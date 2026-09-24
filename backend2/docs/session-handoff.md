# Session handoff — snapshot

> **Overwrite this file each session. Snapshot of current state, not a growing log.**
> Read with `CLAUDE.md`, `ARCHITECTURE.md`, `.claude/skills/`, `deptrac.yaml`, `docs/ROADMAP.md`.
> **`docs/DECISIONS.md` (в корне репозитория) читается ПЕРВЫМ шагом любого наряда** — это реестр
> действующих решений; если постановка ему противоречит, стоп и доклад архитектору.
> Канон плана: **`docs/plan-v2.md`**; контракт: **`docs/plan-api.md`** + `openapi/openapi.yaml`
> (теги `Plans`, `Devices`); модуль: `app/Modules/Plan/README.md`.

Branch: **`main`** (код и документы FIX-4b — до `76a202cd`, отчёт и выкат — следующий коммит; точные хеши — `git log`).
Last updated: 2026-09-24. Наряд **FIX-4b** (хвосты сервера разговора: союзы в судье, `hints.sentence`, промпт роли v3.2)
**сделан и ВЫКАЧЕН 24.09**: миграций нет, `main` исполняет код наряда, Horizon перезапущен. Сборка телефона — по-прежнему
**1.0.0 (20)**: контракт только добавляет `hints.sentence`, (20) работает как работал. Отчёт —
`docs/research/fix-4b/README.md`; решения — DECISIONS пп. **410–413**, «Спорное» пп. 3–5 закрыты. Worktree
`../backend2-fix4b`, ветка `fix-4b`, сайдкар `wt_fix4b` и его базы — снесены. Перед ним — FIX-4 (пп. 404–409, выкачен тем
же днём, отчёт `docs/research/fix-4/README.md`).

## 1. Что сделано (FIX-4b)

- **§1 союз начинает клаузу**: каркас засчитывается и сразу после and / but / so / then / or (ключ пакета
  `clause_starters`; ru/uk/ro — пусто); окно без части после него кончается перед союзом следующей конструкции хода
  (`FrameJudge::windowOf`), значение на проводе перечитывается со всеми конструкциями хода (`ConversationViews::valueIn`).
- **§2 `hints.sentence`** — фраза урока как есть, с заглавной и знаком конца; `hints.native` (придаточное) — до
  CLIENT-FIX-4, в OpenAPI `deprecated`.
- **§3 `conversation_agent.v3.2`** = v3.1 + THE LEARNER'S WORD, YOUR JOB, «новая роль не повторяет прежнюю»; из ответа
  сняты `phrases_used` и `checkpoint_done` (схема, фейк, тесты); v3.1 удалён; реестр промптов (`docs/prompts/REGISTRY.md`)
  догнан с v2 до v3.2.

## 2. Ворота и деньги

Один раз в конце наряда, сайдкар ветки: OpenAPI ok ×2, deptrac 0, PHPStan 0, **Pest 2 501 passed** (24 003 assertions,
`--parallel`), `flutter analyze` — no issues; invariant-reviewer — **CLEAN**. Покупки — одна живая репетиция на e2e:
**$0.037588** (модель 10 вызовов $0.013188; голос 486 символов · 122 кредита · $0.0244) из $0.20.

## 3. Выкат — ВЫПОЛНЕН 24.09

Бэкап `wordtrainer-20260924-130747.sql.gz` → ff `main` → `restart horizon` → бой (`wt_app`) читает
`conversation_agent.v3.2`, e2e (`wt_app_e2e`) отдаёт `hints.sentence` → отчёт → `stamp-build.sh`. Миграций нет;
`wordtrainer_test` догонять нечего; админка не пересобиралась (не менялась).

## 4. Проверено живьём / только кодом

| что | где |
|---|---|
| три разговора 2DX8QC новым кодом | бой, read-only: 37 строк из 37 как в FIX-4, JSON байт в байт (`research/fix-4b/live/replay-b.md`) |
| v3.2 на живой репетиции | e2e, разговор `01M39DK2G736PG45CTA2ZCHDQB` (план `01M2QRH5MY…`, день 3): спора со значением нет, лечения на ходе 7 нет, вопроса о лекарстве после «I gave him paracetamol» нет, отбраковок 0, `hints.sentence` 8 из 8 |
| **только кодом**: склейка двух конструкций союзом в живом разговоре (в сценарии прогона её нет — канон-тест и тест API), `hints.sentence` на телефоне | — |

## 5. Решения, которые не переделывать

- **Союз начинает клаузу** (п. 410): вводные слова — только в начале предложения, союз — где угодно.
- **Дверь к цели, не сказанной по судье, законна** (п. 411).
- **Подсказку клиент показывает как `hints.sentence` без рамки** (п. 412); `native` снять после CLIENT-FIX-4.
- **Роль v3.2**: сказанное учеником правда, своя компетенция, известное не переспрашивать; модель не судит ни фраз, ни
  сцен (п. 413).
- Прежние: судья разговора — код, связная фраза (пп. 404–405); сцены закрывает сервер (п. 406); журнал отбраковок — своя
  таблица (п. 409).

## 6. Известные хвосты (ROADMAP, разделы FIX-4b и FIX-4)

- регистратор e2e ставит диагноз («It sounds like a muscle strain», ход 9) — заготовка сцены «Booking» на e2e отдаёт ему
  визит врача; YOUR JOB снял лечение, не диагноз;
- цель-вопрос роль иногда задаёт сама (ход 16 репетиции) вместо повода ученику спросить;
- звук репетиции FIX-4 (`01M3958HM2…`) остался в снесённом worktree FIX-4 — на e2e у того разговора файлов нет (у FIX-4b
  перенесены);
- с прошлых нарядов: `speak_echo` 70 с по n = 7; изоляция девяти тестов озвучки и фото при серийном прогоне папки.

## 7. Стенд e2e

`wt_app_e2e` (:8010) — `main`, `wordtrainer_e2e_test`, `SPEECH_ENABLED=true`, `QUEUE_CONNECTION=sync`. У дня 3 плана
`01M2QRH5MY…` (репетиция, qa-gen3-doctor, пояс Europe/Bucharest) — живые разговоры FIX-4 `01M3958HM2…` и FIX-4b
`01M39DK2G7…` (оба — повторы). Повторов дня — 3 в сутки ученика: 24.09 истрачено 2.

## 8. Ждёт Дена / архитектора

- **CLIENT-FIX-4** — телефон: `hints.sentence` без рамки, `targets[].state`, «ещё вспомнил», граница сцен (раздел
  `docs/plan-api.md` «Для CLIENT-FIX-4: что показывать»); после него — снять `hints.native` на сервере;
- переозвучка плана Дена ($0.05, `plan:revoice-learner`) — только отдельной командой;
- с прошлых нарядов: сумма панели OpenAI за 17.09 UTC (GEN-3 §4a); пересборка дня 2 плана NKKGFF с голосом (GEN-3 §6).

## 9. FIX-4 + FIX-4b → клиент: карта новых полей

Все — **добавлены** (ни одно не снято): `targets[].state` (`none` · `almost` · `said`, последним ключом); `extra_said[]`;
`hints.sentence` (FIX-4b: фраза урока с заглавной и знаком — **её и показывать, без рамки**), `hints.target` (точная
строка после «почти»), `hints.scene_id` + `hints.ref`; `hints.native` — придаточное для (20), снять после CLIENT-FIX-4;
`turns[].scene_id`, `turns[].scene_event` (`start` · `end` · null), `turns[].extra_said`; `summary.ended_by_limit`,
`summary.extra_said`. Живой пример — `docs/research/fix-4b/live/live-rehearsal.json` (все ответы API репетиции).

```json
{"hints": {"enabled": true, "delay_ms": 5000, "native": "мне сказать вам его температуру?", "target": null,
           "scene_id": "01M2QRHF9M69AMBE2XGD18KQ8P", "ref": "p3", "sentence": "Мне сказать вам его температуру?"}}
```
