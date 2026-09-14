# Session handoff — snapshot

> **Overwrite this file each session. Snapshot of current state, not a growing log.**
> Read with `CLAUDE.md`, `ARCHITECTURE.md`, `.claude/skills/`, `deptrac.yaml`, `docs/ROADMAP.md`.
> **`docs/DECISIONS.md` (в корне репозитория) читается ПЕРВЫМ шагом любого наряда** — это реестр
> действующих решений; если постановка ему противоречит, стоп и доклад архитектору.
> Канон плана: **`docs/plan-v2.md`**; контракт: **`docs/plan-api.md`** + `openapi/openapi.yaml`
> (теги `Plans`, `Devices`); модуль: `app/Modules/Plan/README.md`.

Branch: `main`. Last updated: 2026-09-14 (наряд DAY-UI-2). Отчёт — `docs/research/day-ui-2/README.md`.

---

## ПЕРВОЕ: DAY-UI-2 — окно дня по кадрам 23-0a…0d + бэкенд под него

Коммиты — отчёт Ч.11. Канва — `docs/design/plan-canvas.dc.html`, серия 23 (архитектор, коммит `d9547324`).

**Сервер** (подробно — отчёт Ч.2–Ч.4, `docs/plan-api.md` «Окно дня»):
- `GET /plans/{id}/days/{n}` отдаёт блок **`window`** (`DayWindowViews` + доменные `DayWindowStages`,
  `DayPace`, `UnitStates`, `ImageTones`, `WindowStatus`, `ProgramSummary`): статус словами, цифра
  только у текущего этапа, минуты, состояния единиц и счётчики бровей, `allowed_action`;
- сняты мёртвые поля старого кабинета и **`GET …/sheet`**, колонки `plan_days.first_try_share` /
  `hardest_unit_*` удалены;
- фото у каждого слова — лестница запросов + тон слота + `image_missing`; `plan:images-backfill`;
- голос фраз — `plan_line_audios.line_ref` (`x3`, `p2`); `SpeakSceneLinesJob` повторяется до получаса;
  `plan:speak-backfill`.

**Боевая база `wordtrainer`:** три миграции `2026_09_14_*` применены (бэкапы
`wordtrainer-20260914-110554.sql.gz` — перед `image_tone` и `line_ref`, `wordtrainer-20260914-114653.sql.gz` —
перед сносом колонок метрик, `wordtrainer-20260914-115714.sql.gz` — перед догрузкой голоса); horizon
перезапущен; фото: было пусто 45 → 0; голос: 184 → 228 строк, **суточный лимит Gemini TTS (100) исчерпан** —
`plan:speak-backfill` догонять в следующие дни.

**Клиент** (`mobile/lib/features/plan/day/day_window_screen.dart`, `window/`, `data/plan/day_window.dart`):
- одна лента: плита на фото → строка 56 (240 мс), вкладки Слова · Фразы · Диалог (тап и свайп),
  одна кнопка по `allowed_action`; «Ещё раз» — «Говорю сам» без записи ответов (`DaySession.rehearsal`);
- старый кабинет (`day_room_screen.dart`, `DayRoomPlate`, шит `term_sheet.dart`) снесён с тестами,
  снимками, фикстурами и 33 строками ARB;
- снимки — `test/features/plan/day_window_golden_test.dart` (12 PNG `test/goldens/plan/23-0*`), канон —
  `day_window_canon_test.dart` (15), «Ещё раз» — `day_session_test.dart`, `day_ui_golden_test.dart`;
  харнесс — `test/support/day_window_harness.dart`;
- **живой прогон нашёл и починил**: доводку ленты без кадра (тест с выключенной семантикой), кэш дня
  между входами в окно (`autoDispose`), «Прогресс сохранится» в «Ещё раз».

Ворота — отчёт Ч.11: Pest 2002, deptrac 0, PHPStan 0, `flutter analyze` 0, `flutter test` 1389.
Телефон: release `dc819ef3` собран (Runner.app 13:12 14.09), **не установлен** — «iPhone (Denis)»
не подключён (`unavailable`); установить `flutter install --release -d 00008110-000A7CCC3492801E` по кабелю.

**Не проверено живьём:** «прослушать» серверным голосом (фразы свежих планов не озвучены — суточный
лимит); `om-check-pop` галки после возврата из сессии; растворение фото 200 мс на устройстве;
«прокрутка вкладки помнится» отдельно не проверялась. Открытые вопросы — отчёт Ч.13.

## Контекст, который остаётся верным (PLAN-GEN, PLAN-UI-3, DAY-UI)

- План — модуль `Plan`, два замороженных промпта (`plan-builder-v2`, `lesson-v3`), проверки в
  `observe`; дни открываются по одному в календарный день зоны; `plan:shift-day {plan} --days=N
  --force` вместо QA-часов. Урок дня N+1 пишется при открытии дня N.
- Решения владельца 12.09 (PLAN-UI-3): пять этапов на линии маршрута; push без entitlement —
  напоминания локально, пока `push_enabled: false`; `plan.summary` без модели; языки — `GET /plans/languages`.
- Клиент: одна дверь в окно дня — `openDayRoom`; сессия дня — наряд DAY-UI (23-1…23-13); состояния —
  golden-тесты из живых фикстур, канон — утверждениями, проверенными мутацией.
- Стенд: симулятор **PlanUI3 iPhone 17** (`1E947E83-6285-4739-9238-5A7B72F9E329`), `flutter run
  --debug --dart-define=API_BASE_URL=http://localhost:8001 --dart-define=DEV_LOGIN_EMAIL=qa-…@wt.test`;
  перед сменой аккаунта — `xcrun simctl keychain <udid> reset`; maestro — только целые проценты в
  `point`; горячий перезапуск запущенного `flutter run` — `kill -USR2 <pid flutter_tools>`, файлы,
  правленные до конца первой сборки, нужно `touch`, иначе перезапуск их не видит.
- Канву читать скриптом **со всеми свойствами** (белый список свойств терял `padding-top`); кадр для
  сверки рисуется headless Chrome (`--allow-file-access-from-files`, iframe канвы, сдвиг к
  `[data-screen-label]`).
