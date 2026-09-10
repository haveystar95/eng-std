# План — контракт API (наряд PLAN-GEN, 2026-09-10)

Машинно — `openapi/openapi.yaml`, тег `Plans` (схемы `Plan*`). Здесь — маршрут клиента по
экранам и то, что в YAML не читается: какой ответ на какое действие, какие 409 ждать, какие строки
приходят готовыми. Все пути под `/api/v1`, `auth:sanctum`. Ошибки — RFC 7807 с `code`.

## Версии

`GET /plans/versions` → `{build, prompt_plan, prompt_lesson}`. Те же три строки лежат в каждом
плане (`Plan.versions`) и в статусе сборки; `build` — `APP_COMMIT` / `storage/app/commit`.

## Вход и сборка

| действие | вызов | ответ |
|---|---|---|
| создать план | `POST /plans` `{goal_text, target_lang, level, days_total, event_date?}` | **202** `PlanBuild` (`status: building`) |
| опрос сборки | `GET /plans/{id}/build` | `PlanBuild`: `building` → `ready` / `unclear` (+`unclear_reason`) / `failed` (+`fail_reason`); `cost_usd`, `latency_ms`, `attempts`, `versions` |
| ещё раз | `POST /plans/{id}/build/retry` | 202 `PlanBuild`; 409 `plan_state`, если план не `unclear`/`failed`/не завис |

`PlanBuild.status = failed` показывается и тогда, когда сборка зависла дольше
`build_stale_seconds` — клиент предлагает повторить, а не крутит спиннер.

## Превью

`GET /plans/{id}` → `Plan`: `scenes[]` (с `image`, `lesson_status`, `day_number`), `days[]`
(маршрут с типами и слотами), `until_phrase`, `route_summary`, `days_shortened_from` (клиент
показывает строку «до события N дней — план короче»), `rescue_kit` (пять фраз).

- убрать сцену: `DELETE /plans/{id}/scenes/{sceneId}` → `Plan` (день стал `review`); 409
  `plan_core_scene` на ядре; только на `ready`.
- «Начать»: `POST /plans/{id}/start` → `Plan` (`status: active`, день 1 `open`, слот `today`);
  409 `plan_already_active` (`meta.plan_id` — кто держит место), 409 `plan_state`.

## Вкладка «План»

`GET /plans/current` → `Plan` или `data: null`. `current_day` — первый незакрытый день; каждый
день несёт **эффективный** `status` (`locked` / `open` / `in_progress` / `closed`) и `slot`:

| `slot.code` | `date` | `label_native` | когда |
|---|---|---|---|
| `today` | сегодня | «сегодня» | текущий день, доступен |
| `tomorrow` | завтра | «завтра» | следующий день, или текущий, чей `opens_on` завтра |
| `date` | дата | null | дальше завтрашнего; клиент форматирует дату |
| `past` | дата закрытия | null | закрытый день |

Меню: «перенести дату» / «изменить дни» — `PATCH /plans/{id}/schedule` `{event_date?, days_total?}`
(`event_date: null` снимает дату; 409 `plan_too_short` при попытке отрезать пройденные дни);
«завершить» — `POST /plans/{id}/finish`; «новый план» — `GET /plans` + `POST /plans`; «удалить» —
`DELETE /plans/{id}` (204). `status: overdue` приходит с `overdue_native` («Приём был вчера»).

## День

| действие | вызов | ответ |
|---|---|---|
| кабинет дня | `GET /plans/{id}/days/{n}` | `PlanDayRoom`: `day`, `scene`, `goals_native`, `stages[]` (`locked`/`current`/`done`/`absent`), `metrics` (после закрытия), `program[]` (единицы с состоянием `pending`/`passed`/`failed`), `sheet_available` |
| открыть / продолжить | `POST /plans/{id}/days/{n}/open` | `PlanDayCards` — **весь** список карточек с состоянием; 409 `plan_day_locked` (`meta.blocked_by_day` или `meta.opens_on`), 409 `plan_lesson_not_ready` (`meta.lesson_status`: `building` — подождать, `failed` — предложить `POST …/scenes/{sceneId}/lesson/retry`) |
| перечитать карточки | `GET /plans/{id}/days/{n}/cards` | `PlanDayCards` |
| ответить | `POST …/cards/{cardId}/answer` `{result, attempts}` | `{card, requeued}`; `requeued` — та же карточка в конце этапа после первого `failed`; 409 `plan_card_answered` |
| этап пройден | `POST …/stages/{stage}/close` | `PlanDayRoom`; 409 `plan_stage_incomplete` (`meta.remaining`) |
| день пройден | `POST …/close` | `PlanDayRoom` с `metrics`; 409 `plan_stage_incomplete`; после этого `Plan.collection_id` заполнен |
| шит | `GET …/sheet` | `{words[], phrases[]}` (`PlanTerm`) |

Карточка (`PlanCard`): `stage`, `position`, `kind`, `source` (`today`/`returned`), `unit_kind` /
`unit_ref`, `payload`, `retry_of`, `result`, `attempts`, `returns`. Состав `payload` по видам
описан в схеме `PlanCard`; общее: `scene_id` у всех; у карточек слов/фраз `plan_term_id`, `image`;
у карточек обмена `exchange_step`, `partner` (реплика A), `audio_id`
(→ `GET /plans/audio/{audioId}`, `audio/mpeg`).

Клиент оценивает сам по данным пейлоада (варианты с `correct`, `answer`, `expected` + `coverage`,
`speaking_key` + `variants`) и присылает вердикт: `passed` / `hinted` (зачёт с подсказкой) /
`failed` / `skipped` (произносимая карточка после двух попыток). Сервер ведёт последствия.

## Коды 409

`plan_state`, `plan_already_active`, `plan_core_scene`, `plan_day_locked`, `plan_day_not_open`,
`plan_lesson_not_ready`, `plan_stage_incomplete`, `plan_card_answered`, `plan_too_short`. 404:
`plan_not_found`, `plan_scene_not_found`, `plan_day_not_found`, `plan_card_not_found`.

## Что клиенту НЕ надо считать

Дни, слоты, фразу обратного отсчёта, строку маршрута, состав дня, возвраты, метрики, «самое
трудное». Что клиент считает сам: формат дат и чисел, обрезку длинных заголовков (лимиты промпта —
ориентир, `char_limits` только считается).

## Админка

`GET /admin/api/plans/checks` → `[{prompt_version, check, action, hits}]` — счётчики проверок по
версии промпта (`counted` / `dropped` / `gated`). Стоимость плана — в `GET /admin/api/costs`
строкой `plan` (сумма `plans.cost_usd_plan` + `plan_scenes.cost_usd_lesson`).
