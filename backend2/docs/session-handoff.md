# Session handoff — snapshot

> **Overwrite this file each session. Snapshot of current state, not a growing log.**
> Read with `CLAUDE.md`, `ARCHITECTURE.md`, `.claude/skills/`, `deptrac.yaml`, `docs/ROADMAP.md`.
> **`docs/DECISIONS.md` (в корне репозитория) читается ПЕРВЫМ шагом любого наряда** — это реестр
> действующих решений; если постановка ему противоречит, стоп и доклад архитектору.
> Канон плана: **`docs/plan-v2.md`**; контракт: **`docs/plan-api.md`** + `openapi/openapi.yaml`
> (теги `Plans`, `Devices`); модуль: `app/Modules/Plan/README.md`.

Branch: `main`. Last updated: 2026-09-12 (наряд PLAN-UI-3). Отчёт — `docs/research/plan-ui-3/README.md`.

---

## ПЕРВОЕ: PLAN-UI-3 — маршрут по канве PLAN-DES-3, картинки, события и уведомления

Коммиты: канва `63bc358f`, бэкенд `c265ebc1`, клиент `8acb7755`, отчёт — следующий.

**Решения владельца 12.09 (при постановке, в сессии) — не «чинить» обратно по канве:**
1. На линии маршрута **пять этапов, как на сервере** (`days[].stages`, `done/current/locked`), у
   повторения / репетиции — сколько отдал сервер. Канва с тремя узлами и «Повторить ошибки»
   расходится с продуктом, архитектор перерисует кадры; «Повторить ошибки» — функции нет.
2. **Push без entitlement** (бесплатная Personal Team): токен не выдаётся, регистрация пишет в лог;
   напоминание / «ждёт со вчера» / «сегодня событие» телефон ставит **локально** по датам плана;
   «план готов» / «день собран» — баннер в приложении при возврате. Сервер шлёт в сухом режиме.
3. **`plan.summary` — без модели**, считается в ресурсе: три сцены + «К <дате> скажешь всё это сам».
4. **Языки плана — `GET /plans/languages`** (`PLAN_LANGUAGES`, сегодня en, de); `POST /plans` сверяет.

**Что появилось на сервере** (подробно `docs/research/plan-ui-3/backend-a.md`, `backend-b.md`):
- `RouteStages` (Domain) — этапы дня маршрута одной сгруппированной выборкой;
- картинки сцен: `image.tone` (avg_color Pexels), `url_112` / `url_448` → `GET
  /plans/images/{scene}/{size}` (immutable + ETag, самолечение копии), `plan:images-backfill`;
- журнал `plan_events` (append-only), `plan_notifications` (≤ 1 напоминания в сутки), Identity:
  `device_push_tokens`, `user_visits`, `PUT/DELETE /devices/push-token`, `POST /devices/visit`;
- `PushSender`: `ApnsPushSender` при `APNS_KEY_P8` (+ `APNS_KEY_ID`, `APNS_TEAM_ID`, `APNS_TOPIC`,
  `APNS_ENV`), иначе `DryRunPushSender` (лог `plan push (dry run)`, `not_sent`);
- `plan:notify-tick` каждые 15 мин — **сервис `scheduler` в compose** (поднят); `plan:notify-test`.

**Боевая база `wordtrainer`:** 5 миграций `2026_09_12_*` применены после бэкапа
`storage/db-backups/wordtrainer-20260912-222456.sql.gz`; horizon перезапущен; backfill: 31 сцена.

**Клиент** (`mobile/lib/features/plan/route/`, `lib/ui/plan_preloader.dart`, `lib/ui/scene_circle.dart`,
`lib/data/image_loader.dart`, `lib/data/plan/plan_notifications.dart` + `plan_reminder_rules.dart`,
`features/plan/plan_notifications_host.dart`, `entry/goal_dictation.dart`):
- один виджет маршрута для таба, превью и витрины; запертый день не открывается ни с одной двери
  (`planExplainDayProvider`); тап по уведомлению — таб на нужном дне (`planFocusDayProvider`);
- `ImageLoader` — единственная дорога байтов картинок (6 параллельных, диск, повторы, bearer для API);
- канон — `test/features/plan/plan_canon_test.dart`, `plan_rules_canon_test.dart`; фикстуры сняты
  живым прогоном (`qa-planui3@wt.test`), новые: `current_started`, `current_day2`, `room_day2`.

Ворота: `composer check` — deptrac 0, PHPStan 0, Pest 1973; `flutter analyze` 0; `flutter test` 1371.
Телефон: release `8acb7755` установлен на iPhone (Denis) — установка снесла данные, нужен вход.

**Не проверено живьём:** APNs (нет ключа), голос (нет микрофона на симуляторе), баннер «день
собран» при возврате, срабатывание локальных уведомлений. Открытые вопросы — отчёт §11.

## Контекст, который остаётся верным (PLAN-GEN, DAY-UI)

- План — модуль `Plan`, два замороженных промпта (`plan-builder-v2`, `lesson-v3`), проверки в
  `observe`; дни открываются по одному в календарный день зоны; `plan:shift-day {plan} --days=N
  --force` вместо QA-часов. Урок дня N+1 пишется при открытии дня N.
- Клиент: одна дверь в кабинет — `openDayRoom`; кабинет и сессия дня — наряд DAY-UI (23-x по
  `plan.dc.html`); состояния — golden-тесты `mobile/test/goldens/plan/` из живых фикстур.
- Стенд: симулятор **PlanUI3 iPhone 17** (`1E947E83-6285-4739-9238-5A7B72F9E329`), `flutter run
  --debug --dart-define=API_BASE_URL=http://localhost:8001 --dart-define=DEV_LOGIN_EMAIL=qa-…@wt.test`;
  maestro — только целые проценты в `point`; `simctl launch` на свежем симуляторе виснет (индексация) —
  запускать через `flutter run`.
