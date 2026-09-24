# Session handoff — snapshot

> **Overwrite this file each session. Snapshot of current state, not a growing log.**
> Read with `CLAUDE.md`, `ARCHITECTURE.md`, `.claude/skills/`, `deptrac.yaml`, `docs/ROADMAP.md`.
> **`docs/DECISIONS.md` (в корне репозитория) читается ПЕРВЫМ шагом любого наряда** — это реестр
> действующих решений; если постановка ему противоречит, стоп и доклад архитектору.
> Канон плана: **`docs/plan-v2.md`**; контракт: **`docs/plan-api.md`** + `openapi/openapi.yaml`
> (теги `Plans`, `Devices`); модуль: `app/Modules/Plan/README.md`.

Branch: **`main`** (код и документы FIX-4 — до `b8497ca5`, отчёт и выкат — следующий коммит; точные хеши — `git log`).
Last updated: 2026-09-24. Наряд **FIX-4** (сервер разговора: судья каркасов, граница сцен, подсказки, «Вспомнить»)
**сделан и ВЫКАЧЕН 24.09**: бой мигрирован, `main` исполняет код наряда, Horizon перезапущен, админка пересобрана.
Сборка телефона — по-прежнему **1.0.0 (20)**: контракт FIX-4 только добавляет поля, (20) работает как работал.
Отчёт — `docs/research/fix-4/README.md`; решения — DECISIONS пп. **404–409**; клиенту — `docs/plan-api.md`,
раздел «Для CLIENT-FIX-4: что показывать». Worktree `../backend2-fix4`, ветка `fix-4`, сайдкар `wt_fix4` и его базы — снесены.

## 1. Что сделано

- **§1 «Вспомнить»** звучит файлами сцены своей страницы (`CardViews` — сцена узла); «звук ≠ текст» по всем планам боя — 0.
- **§2 судья каркасов** `FrameJudge` — связная фраза: сказано / почти / нет, только каркасы текущей сцены, «ещё
  вспомнил» (`extra_said`); отрицание — та же конструкция (решение владельца 24.09); `PhraseUse` удалён.
- **§3 цели роли — `T1…T7` своей сцены**; каждое открытие сверяет сервер, отброшенное — в журнал.
- **§4 граница сцен — сервер**: бюджет целей + 1, прощание (`scene_event: end`) и приветствие новой роли (`start`, свой
  голос), `ended_by_limit`; промпт `conversation_agent.v3.1`.
- **§5 подсказка — целая фраза урока**; после «почти» — точная строка (`hints.target`, `hints.ref`).
- **§6** «p.m..» (правило + `plan:rebuild-card-texts`; на бою исправлено 6 карточек / 7 строк), `plan_scenes.built_at`,
  журнал отбраковок `conversation_rejections` (id вызова `model_calls`), голос роли по полу сцены реплики.
- **Админка** «Разговоры»: почти, ещё вспомнил, начало/прощание сцены, отбраковки по ходам, метка «лимит».

## 2. Ворота и деньги

Один раз в конце наряда, сайдкар ветки: OpenAPI ok ×2, deptrac 0, PHPStan 0, **Pest 2 498 passed** (23 947 assertions,
`--parallel`), `flutter analyze` — no issues; wt_admin vue-tsc/eslint чисто, vitest 133; invariant-reviewer — **CLEAN**;
мутации **24 из 24**. Покупки — одна живая репетиция на e2e: **$0.038853** (модель $0.015253, голос $0.0236).

## 3. Выкат — ВЫПОЛНЕН 24.09

Бэкап `wordtrainer-20260924-105133.sql.gz` → `migrate` боя кодом ветки (`built_at`; `scene_id`, `scene_event`,
`phrases_almost`, `conversation_rejections`) → ff `main` → `restart horizon` → `plan:rebuild-card-texts --apply` (6 / 7,
повтор 0) → «звук ≠ текст» — 0 → `docker compose build admin && up -d admin` → `wordtrainer_test` догнан → стенд ветки
снесён → `stamp-build.sh`. e2e (`wordtrainer_e2e_test`) мигрирована до живого прогона (бэкап
`wordtrainer_e2e_test-20260924-103034.sql.gz`); `wt_app_e2e` (:8010) на `main`.

## 4. Проверено живьём / только кодом

| что | где |
|---|---|
| новый судья на сохранённых ходах | 3 разговора зала 2DX8QC (бой, read-only): все ходы ученика — как в каноне наряда; расхождение одно — открытие хода 7 (отчёт §3) |
| граница сцен, прощание/приветствие, смена голоса на `start`, подсказки, «почти» → точная строка, открытия своей сцены, журнал | живая репетиция e2e `01M3958HM2…` (план `01M2QRH5MY…`, врач — male на 71 с) |
| «звук ≠ текст» = 0 | все 9 планов боя (активные — по ответу клиенту) |
| «p.m..» | бой: `WSW1H6` день 1 |
| **только кодом**: `ended_by_limit` на живом разговоре (в прогоне не сработал), отброшенное открытие вживую (в прогоне их не было — только в приёмке А и тестах), разговор по трём сценам, телефон на новых полях | — |

## 5. Решения, которые не переделывать

- **Судья разговора — код, связная фраза; модель о фразах не спрашивается** (пп. 404–405).
- **Сцены закрывает сервер; роль знает только свою сцену** (п. 406); **открытия — только своей сцены, по `T…`** (п. 407).
- **Подсказка — целая фраза урока, `hints.native` — придаточным для (20)** (п. 408).
- **Журнал отбраковок — своя таблица; `model_calls` не трогаем; цена хода — сумма попыток** (п. 409).
- Прежние: снимок прейскуранта у плана (п. 390), голос ученика — пол профиля (п. 389), «цели покрыты» — не конец дня (п. 396).

## 6. Известные хвосты (ROADMAP, раздел FIX-4)

- каркас в середине предложения («…, and it hurts in his neck») не засчитывается — «Спорное» п. 3, ждёт владельца;
- роль на `mini`: спорит со значением ученика, даёт совет не своей роли, переспрашивает сказанное — наряд промпта;
- `phrases_used` и `checkpoint_done` в ответе роли не читаются — убрать в v3.2;
- подсказка к цели-вопросу в рамке «Скажи, что …» — решение клиента;
- с прошлых нарядов: `speak_echo` 70 с по n = 7; изоляция девяти тестов озвучки и фото при серийном прогоне папки.

## 7. Стенд e2e

`wt_app_e2e` (:8010) — `main`, `wordtrainer_e2e_test`, `SPEECH_ENABLED=true`, `QUEUE_CONNECTION=sync`. У дня 3 плана
`01M2QRH5MY…` (репетиция, qa-gen3-doctor) — живой разговор FIX-4 `01M3958HM2X2V4XP0CCQXWTHXR` (повтор, 18 ходов); голос
врача возвращён female.

## 8. Ждёт Дена / архитектора

- **CLIENT-FIX-4** — телефон на новых полях (раздел «Для CLIENT-FIX-4: что показывать»);
- «Спорное» пп. 3–5 (каркас в середине предложения, ожидание хода 7, качество роли);
- переозвучка плана Дена ($0.05, `plan:revoice-learner`) — только отдельной командой;
- с прошлых нарядов: сумма панели OpenAI за 17.09 UTC (GEN-3 §4a); пересборка дня 2 плана NKKGFF с голосом (GEN-3 §6).

## 9. FIX-4 → клиент: карта новых полей

Все — **добавлены** (ни одно не снято): `targets[].state` (`none` · `almost` · `said`, последним ключом; `said` = `state ==
said`); `extra_said[]` (форма `targets[]`); `hints.target` (точная строка после «почти», иначе null), `hints.scene_id` +
`hints.ref`; `hints.native` — целая фраза урока придаточным («у него болит поясница»); `turns[].scene_id`,
`turns[].scene_event` (`start` · `end` · null), `turns[].extra_said` (`{scene_id, ref}`); `summary.ended_by_limit`,
`summary.extra_said`. Живой пример — `docs/research/fix-4/live/live-rehearsal.json` (все ответы API репетиции),
фикстуры `docs/fixtures/day-doctor*.json` (ряд разговора окна — `targets[].state`).

```json
{"turns": [{"index": 11, "speaker": "partner", "kind": "agent", "scene_id": "01M2QRHF9M86KAM8JW8XYRAWDR", "scene_event": "end",
            "text_target": "Good. Then it sounds like a muscle strain. Goodbye.", "phrases_used": [], "extra_said": []},
           {"index": 12, "speaker": "partner", "kind": "agent", "scene_id": "01M2QRHF9M69AMBE2XGD18KQ8P", "scene_event": "start",
            "text_target": "Hello. What symptoms does your son have today?", "phrases_used": [], "extra_said": []}],
 "hints": {"enabled": true, "delay_ms": 5000, "native": "боль острая, когда он наклоняется",
           "target": "The pain is sharp when he bends.", "scene_id": "01M2QRHF9M86KAM8JW8XYRAWDR", "ref": "p3"}}
```
