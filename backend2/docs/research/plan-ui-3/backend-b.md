# PLAN-UI-3, часть B (бэкенд): журнал плана, устройства, письма, тик

Дата: 2026-09-12. Исполнитель: агент B. Не закоммичено (коммитит ведущий). `mobile/` не трогал.
Параллельно в том же дереве работал агент A (этапы маршрута, сводка, картинки сцен) — общие файлы
(`PlanServiceProvider`, `bootstrap/app.php`, `openapi.yaml`, `Plan/README.md`, `docs/plan-api.md`)
правились точечными вставками, его правки не перезаписаны.

## Что сделано

- **B1 — журнал `plan_events`** (модуль Plan): только дописывается. Пишут существующие обработчики в
  своих транзакциях: `BuildPlanHandler` (`plan_ready`), `BuildLessonHandler` (`day_ready` с номером
  дня, на котором сцена стоит в момент записи), `CloseDayHandler` (`day_passed`),
  `ReschedulePlanHandler` (`days_skipped_rebuilt {from, to}`, когда `days_total` уменьшился), тик
  (`event_today`, `event_passed`). Письмо ставится в очередь ПОСЛЕ коммита (`PlanNotifier`).
- **B2 — устройства** (модуль Identity): `device_push_tokens`, `user_visits`, три ручки, запросы
  `GetPushTokens` и `GetUsualVisitTime`; Plan читает их через свои порты `LearnerDevices` /
  `LearnerHabits` (адаптеры `IdentityLearnerDevices` / `IdentityLearnerHabits`, как
  `IdentityLearnerCalendar`). `deptrac.yaml` не менялся — нужные рёбра уже были.
- **B3 — отправка**: `PlanNotifier` → `SendPlanNotificationJob` (одна попытка) →
  `SendPlanNotificationHandler` (свежий план, правило «кому ещё письмо», проверка «раз в сутки»,
  тексты, адреса, `PushSender`, строка в `plan_notifications`). `PushSender`: `ApnsPushSender`
  (HTTP/2, JWT ES256 через `openssl_sign`, DER → R‖S, кеш 50 мин, 410/`BadDeviceToken` → адрес
  удаляется через Identity) при заданном `APNS_KEY_P8`, иначе `DryRunPushSender`.
- **B4 — тик** `plan:notify-tick` (Plan/Presentation/Console), `Schedule::command(...)
  ->everyFifteenMinutes()->withoutOverlapping()` в `routes/console.php` (расписаний раньше не было);
  сервис `scheduler` в `docker-compose.yml` (`php artisan schedule:work`) — **описан, не запущен**.
  Плюс `plan:notify-test {user} {kind} [--day=] [--from=7] [--to=5]` для живого прогона.

## Миграции (аддитивные, откат проверен на `wordtrainer_b_test`: `migrate:fresh` → `migrate:rollback --step=5` → `migrate`)

| миграция | модуль | что |
|---|---|---|
| `2026_09_12_100000_create_device_push_tokens_table` | Identity | `id`, `user_id` (FK users cascade), `platform` (CHECK `ios`), `token`, `locale`, `timezone`, `last_seen_at`, timestamps; UNIQUE (`platform`,`token`), INDEX (`user_id`) |
| `2026_09_12_100100_create_user_visits_table` | Identity | `id`, `user_id` (FK users cascade), `visited_at`; INDEX (`user_id`, `visited_at DESC`) |
| `2026_09_12_110000_create_plan_events_table` | Plan | `id`, `user_id`, `plan_id` (FK plans cascade), `day_id` (FK plan_days null on delete), `day_number`, `kind` (CHECK), `payload` jsonb, `occurred_at`, `created_at`; INDEX (`plan_id`,`kind`), (`plan_id`,`occurred_at`), (`user_id`); UNIQUE partial (`plan_id`,`kind`) WHERE kind IN (plan_ready, event_today, event_passed); UNIQUE partial (`plan_id`,`day_number`) WHERE kind = day_passed |
| `2026_09_12_110100_create_plan_notifications_table` | Plan | `id`, `user_id`, `plan_id` (FK plans cascade), `event_id` (FK plan_events null on delete), `kind` (CHECK), `day_number`, `local_date`, `status` (CHECK), `reason`, `created_at`; UNIQUE partial (`user_id`,`local_date`) WHERE kind = daily_reminder; INDEX (`plan_id`,`created_at`), (`user_id`,`created_at`) |

`day_number` в `plan_notifications` — сверх списка наряда: письмо «День 3» в логе должно говорить,
какой день, а дни плана могут переложиться после отправки. Миграция 120000 — агента A (оказалась в
моём шаге отката, на одноразовой базе это безвредно).

**На основную базу `wordtrainer` ничего не накатывалось** — это делает ведущий после бэкапа.

## Эндпоинты

- `PUT /api/v1/devices/push-token` `{"platform":"ios","token":"<hex>","locale":"ru-UA","timezone":"Europe/Kyiv"}` → 204 (`locale`/`timezone` необязательны); 422, 401.
- `DELETE /api/v1/devices/push-token` `{"platform":"ios","token":"<hex>"}` → 204 (удаляет только свою строку).
- `POST /api/v1/devices/visit` `{"timezone":"Europe/Kyiv"}` (тело необязательно) → 204.

## Тексты писем (для глоссария клиента — дословно)

| `kind` | заголовок (ru) | текст (ru) | en |
|---|---|---|---|
| `plan_ready` | План готов | `{until_phrase}. День 1 — «{название дня 1}»` → «До приёма · 7 дней. День 1 — «Регистратура»»; без даты события: «5 дней. День 1 — «Запись к врачу»» | Your plan is ready / `{lead}. Day 1 — “{title}”` |
| `day_ready` | День {n} собран | «{название}» — можно начинать | Day {n} is ready / “{title}” — you can start |
| `daily_reminder` | День {n} ждёт | «{название}» — начни с того места, где остановился | Day {n} is waiting / “{title}” — pick up where you left off |
| `event_today` | Сегодня {event_native, первая буква строчная} → «Сегодня приём у врача»; без `event_native` — «Сегодня разговор» | Скажи сам перед разговором — прогони его вслух | Today: {event} / Today is the conversation; Say it yourself before the conversation — run it out loud |
| `days_skipped_rebuilt` | Маршрут пересобран | Было {from дней}, стало {to} → «Было 7 дней, стало 5», «Было 3 дня, стало 2» | Route rebuilt / It was 7 days, now 5 |

`{название}` — `title_native` сцены дня; у дня повторения «Повторение», у репетиции «Репетиция»
(en: Review / Rehearsal). `until_phrase` — та же строка, что `Plan.until_phrase`. Плюрали — через
`NativeStrings::count`. Другие языки → английский. Код: `Plan/Domain/Service/NotificationTexts.php`.

## Уведомление → как доставляется сейчас → как после ключа

| письмо | сейчас (нет `APNS_KEY_P8`, на телефоне нет токена) | после ключа и Push capability |
|---|---|---|
| `plan_ready` | факт в журнале; письмо целиком в лог (`plan push (dry run) — letter not sent`, `tokens: 0`), строка `not_sent`; клиент показывает «план готов» в приложении при возвращении | APNs на все адреса ученика → `sent` / `failed`; нет адресов → `no_token` |
| `day_ready` (день ≥ 2) | то же; клиент показывает в приложении | то же |
| `daily_reminder` | тик раз в сутки в окне обычного времени → лог + `not_sent`; клиент ставит напоминание **локально** | APNs; клиенту локальное напоминание стоит снять, иначе будет два |
| `event_today` | тик в день события с 08:00 → лог + `not_sent`; клиент ставит локально | APNs |
| `days_skipped_rebuilt` | при укорачивании живого плана → лог + `not_sent`; клиент показывает сам (пропуски — локально) | APNs |
| `day_passed`, `event_passed` | только журнал | только журнал |

Переключение: задать `APNS_KEY_P8` (содержимое .p8 или путь в контейнере), `APNS_KEY_ID`,
`APNS_TEAM_ID`, `APNS_TOPIC=com.denis.engstd`, `APNS_ENV=sandbox|production` → `docker compose
restart horizon` (привязка `PushSender` читается при сборке обработчика в воркере).

## Решения по именам и по правилам

- `learning_plan_events` → **`plan_events`**; `PlanDayPassing` → запись `day_passed` в
  существующем **`CloseDayHandler`**. Названия наряда — из удалённой первой цепочки плана.
- Консольные команды — в `Plan/Presentation/Console` (как просит наряд); старые `plan:shift-day` /
  `plan:seed-load` лежат в `Infrastructure/Console` — не переносил.
- **`event_today` не раньше 08:00** по зоне ученика: тик идёт от полуночи, и «Сегодня приём» в 00:15
  будит человека. Это моё решение, не из наряда (см. вопросы).
- **Кому ещё письмо** (`NotificationRules::allows`): `plan_ready` — плану `ready`/`active`/`overdue`;
  остальные — только живому (`active`/`overdue`). Удалённому/завершённому — ни письма, ни строки.
  Укорачивание в превью (`ready`) пишет факт, но не письмо — это тап самого ученика.
- **Вечерние визиты через полночь**: для медианы день ученика начинается в 04:00 — визит 00:20
  считается как 24:20; результат сворачивается обратно в 00:00–23:59 после округления вниз.
  23:50 / 00:10 / 00:20 → 00:00; 01:30 каждый день → 01:30.
- Чётное число визитов: медиана = целая половина суммы двух средних (вниз), потом вниз до четверти.
- `timezone` в `POST /devices/visit` принимается и валидируется, **не хранится**: время визита
  читается в `profiles.timezone` (как требует наряд), зона держится свежей через `PUT /profile`.
- Дробление: один запрос APNs на адрес; письмо `sent`, если принято хоть одним адресом.
  `DeviceTokenNotForTopic` адрес не удаляет (это ошибка `APNS_TOPIC`, а не мёртвый токен).
- «Раз в сутки»: тик проверяет журнал доставки, задача перепроверяет перед отправкой, частичный
  уникальный индекс — последнее слово. Две одновременные задачи теоретически могут обе отправить
  (проверка → отправка → вставка не атомарны); вторая строка не запишется. На одном тике раз в 15
  минут с `withoutOverlapping` это не встречается.
- `SendPlanNotificationJob` сбрасывает мемо `IdentityLearnerCalendar` перед работой: воркер живёт
  днями, зона могла смениться.

## Чего сервер НЕ делает

- **Не замечает пропущенных дней.** Нет ни детектора «вчера не открыл», ни автоматической
  пересборки маршрута; `days_skipped_rebuilt` рождается только из `PATCH …/schedule`, который
  укоротил маршрут. Не придумывал.
- Не шлёт ничего без ключа (сухой режим) и не имеет токена от телефона (Personal Team).
- Не учитывает «ученик уже заходил сегодня» для напоминания — напоминает, если текущий день
  доступен и не закрыт, даже если сегодня уже был визит.
- Не отдаёт клиенту журнал/лог доставки отдельной ручкой.
- `plan:shift-day` не сдвигает `plan_events` / `plan_notifications` (журнал — история, не календарь).

## Файлы

Identity:
- `app/Modules/Identity/Domain/Service/UsualVisitTime.php`, `VisitThrottle.php`
- `app/Modules/Identity/Application/Dto/PushTokenView.php`, `UsualVisitTimeView.php`
- `app/Modules/Identity/Application/Port/PushTokenStore.php`, `VisitLog.php`
- `app/Modules/Identity/Application/Command/RegisterPushToken(.php|Handler.php)`, `RemovePushToken(…)`, `RecordVisit(…)`
- `app/Modules/Identity/Application/Query/GetPushTokens(…)`, `GetUsualVisitTime(…)`
- `app/Modules/Identity/Infrastructure/Eloquent/EloquentPushTokenStore.php`, `EloquentVisitLog.php`
- `app/Modules/Identity/Infrastructure/Migration/2026_09_12_100000_…`, `2026_09_12_100100_…`
- `app/Modules/Identity/Presentation/Http/Controller/DeviceController.php`
- `app/Modules/Identity/Presentation/Http/Request/PushTokenRequest.php`, `RemovePushTokenRequest.php`, `VisitRequest.php`
- изменены: `Infrastructure/Provider/IdentityServiceProvider.php`, `Presentation/Http/routes.php`, `README.md`

Plan:
- Domain: `Entity/PlanEvent.php`, `Repository/PlanEventRepository.php`, `Service/PlanEventRules.php`,
  `Service/NotificationRules.php`, `Service/NotificationTexts.php`, `ValueObject/PlanEventId.php`,
  `PlanEventKind.php`, `NotificationKind.php`, `DeliveryStatus.php`, `NotificationText.php`
- Application: `Command/SendPlanNotification(…)`, `Command/RunNotificationTick(…)`,
  `Dto/PushTarget.php`, `PushMessage.php`, `DeliveryResult.php`, `NotificationRecord.php`,
  `Port/PushSender.php`, `LearnerDevices.php`, `LearnerHabits.php`, `NotificationLog.php`,
  `NotificationDispatcher.php`, `NotifiablePlans.php`, `Service/PlanEventJournal.php`, `Service/PlanNotifier.php`
- Infrastructure: `Adapter/IdentityLearnerDevices.php`, `IdentityLearnerHabits.php`,
  `QueuedNotificationDispatcher.php`, `Eloquent/EloquentPlanEventRepository.php`,
  `EloquentNotificationLog.php`, `EloquentNotifiablePlans.php`, `Job/SendPlanNotificationJob.php`,
  `Push/ApnsPushSender.php`, `Push/ApnsProviderToken.php`, `Push/DryRunPushSender.php`,
  `Migration/2026_09_12_110000_…`, `2026_09_12_110100_…`
- Presentation: `Console/PlanNotifyTickCommand.php`, `Console/PlanNotifyTestCommand.php`
- изменены: `Application/Command/BuildPlanHandler.php`, `BuildLessonHandler.php`,
  `CloseDayHandler.php`, `ReschedulePlanHandler.php`, `Infrastructure/Eloquent/EloquentPlanAccountEraser.php`,
  `Infrastructure/Provider/PlanServiceProvider.php`, `README.md`

Общие: `bootstrap/app.php` (две команды), `routes/console.php` (расписание), `config/services.php`
(`apns`), `docker-compose.yml` (`scheduler`), `.env.example` (APNS_*), `openapi/openapi.yaml`
(тег `Devices`, три операции), `docs/plan-api.md` («События и уведомления»).

Тесты: `tests/Unit/Identity/UsualVisitTimeTest.php`, `tests/Unit/Plan/PlanNotificationRulesTest.php`,
`tests/Feature/Identity/DeviceEndpointsTest.php`, `tests/Feature/Plan/PlanEventsTest.php`,
`tests/Feature/Plan/ApnsPushSenderTest.php`.

## Живой прогон (для ведущего, после бэкапа и `migrate`)

1. `docker compose restart horizon` (новые задачи и привязки).
2. `docker compose exec -T app php artisan plan:notify-test <user_id> plan_ready` → «plan_ready:
   not_sent — dry_run…», в `storage/logs/laravel.log` строка `plan push (dry run)` с текстом.
3. `docker compose up -d scheduler` → через ≤ 15 минут `plan:notify-tick` в логе планировщика.

## Открытые вопросы

1. `event_today` с 08:00 — устраивает ли порог, или брать обычное время визита (как у напоминания)?
2. Напоминание, если ученик сегодня уже заходил (визит до окна) — слать или молчать?
3. После ключа: снимать ли клиенту локальные напоминания (иначе дубль) — и как клиент узнает, что
   сервер начал слать (флаг в `/auth/me`?).
4. `days_skipped_rebuilt` для укорачивания в превью (`ready`) сейчас без письма — так и оставить?
5. Нужен ли детектор пропущенных дней на сервере (сейчас его нет) — это отдельный наряд.
6. `timezone` из `POST /devices/visit` — хранить на визите или обновлять им `profiles.timezone`?
7. Тестовая база наряда называлась `wordtrainer_test_b`, но охранник `AppServiceProvider` считает
   одноразовыми только имена на `_test` — прогоны шли на **`wordtrainer_b_test`** (пустая
   `wordtrainer_test_b` тоже создана, её можно удалить).
