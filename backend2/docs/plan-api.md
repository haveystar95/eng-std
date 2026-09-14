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
| языки плана | `GET /plans/languages` | `{targets: ["en","de"]}` — список сервера (`config/plan.php` → `languages`, env `PLAN_LANGUAGES`), не константа клиента |
| создать план | `POST /plans` `{goal_text, target_lang, level, days_total, event_date?}` | **202** `PlanBuild` (`status: building`); `target_lang` вне `GET /plans/languages` → 422 |
| опрос сборки | `GET /plans/{id}/build` | `PlanBuild`: `building` → `ready` / `unclear` (+`unclear_reason`) / `failed` (+`fail_reason`); `cost_usd`, `latency_ms`, `attempts`, `versions` |
| ещё раз | `POST /plans/{id}/build/retry` | 202 `PlanBuild`; 409 `plan_state`, если план не `unclear`/`failed`/не завис |

`PlanBuild.status = failed` показывается и тогда, когда сборка зависла дольше
`build_stale_seconds` — клиент предлагает повторить, а не крутит спиннер. Значений ровно четыре и
пятого нет: **собранный план читается как `ready`**, чем бы он ни стал потом. «Начать» разрешено,
пока пишется урок дня 1, и опрос, переживший нажатие, не должен отвечать словом, которого у этой
ручки нет.

**Сборка урока и картинки не трогают план.** Job урока пишет только свою сцену, обработчик картинок
— только колонки фотографий (и только пока их нет); статус плана, `started_at` и расписание дней
job не пишет никогда. Поэтому `POST …/start` во время идущей сборки разрешён и переживает её
завершение: урок и картинка доедут на уже запущенный план.

## Превью

`GET /plans/{id}` → `Plan`: `scenes[]` (с `image`, `lesson_status`, `day_number`), `days[]`
(маршрут с типами, слотами и этапами), `until_phrase`, `route_summary`, `summary`,
`days_shortened_from` (клиент показывает строку «до события N дней — план короче»), `rescue_kit`
(пять фраз).

**`summary`** (PLAN-UI-3) — план одной фразой, считается при чтении, не хранится: заголовки первых
трёх дней-сцен маршрута по порядку (повторения и репетиция не называются), в нижнем регистре через
«, », первая буква заглавная, затем обещание: с датой — «Регистрация на рейс, заселение в отель,
ресторан. К 17 сентября скажешь всё это сам», без даты — «… Скажешь всё это сам». Перед числом 2 —
«Ко» («Ко 2 сентября»), перед любым другим — «К» («К 12», «К 22»). Язык — родной
ученика (ru, uk, en; прочие — английский). `null`, если ни у одной сцены нет заголовка.

**Фото сцены** (PLAN-UI-3): `image = {url, author, author_url, tone, url_112, url_448}`. `tone` —
средний цвет фото (`#978E82`, из Pexels `avg_color`), им красится кружок до прихода байтов; у фото,
найденных до PLAN-UI-3, `null` до прогона `plan:images-backfill`. `url_112` / `url_448` — абсолютные
адреса квадратных копий на сервере с версией: `…/api/v1/plans/images/{sceneId}/112?v={12 hex
sha1(url)}`. Адрес фото сцены не меняется никогда, поэтому байты по такому адресу вечны: кэшировать
навсегда. У обложки (`cover_image`) есть `tone`, копий нет.

| действие | вызов | ответ |
|---|---|---|
| копия фото сцены | `GET /plans/images/{sceneId}/{size}` (`size` 112 или 448) | `image/jpeg`, `Cache-Control: public, max-age=31536000, immutable`, `ETag`; `If-None-Match` с этим тегом → 304. **Мимо лимита API** (доработка PLAN-UI-3): в стеке compose файл отдаёт nginx (`web`, `docker/nginx/default.conf`) как статику — запрос не доходит до Laravel и его `throttle:120,1`; только копию, которой нет на диске, отдаёт Laravel (маршрут вне лимитера, `auth:sanctum`): берёт её у CDN, сохраняет, отдаёт; дальше опять статика. CDN не отдал сейчас → 503 `plan_scene_image_unavailable`. Статика не проверяет владельца: адрес — ULID сцены, фото стоковое |

- убрать сцену: `DELETE /plans/{id}/scenes/{sceneId}` → `Plan` (день стал `review`); 409
  `plan_core_scene` на ядре; только на `ready`.
- «Начать»: `POST /plans/{id}/start` → `Plan` (`status: active`, день 1 `open`, слот `today`);
  409 `plan_already_active` (`meta.plan_id` — кто держит место), 409 `plan_state`.

## Вкладка «План»

`GET /plans/current` → `Plan` или `data: null`. Отдаёт живой план (`active` / `overdue`), а если
живого нет — **самый свежий собранный и незапущенный** (`status: ready`, все дни `locked`,
`opens_on` и `started_at` — null). Неначатый план это СОСТОЯНИЕ вкладки, а не пустота: у клиента
под него есть кадр и фикстура `mobile/test/fixtures/plan/plan_ready_preview.json`, форма ответа —
обычный `Plan`. `data: null` — только когда плана нет вовсе.

`current_day` — первый незакрытый день; каждый день несёт **эффективный** `status`
(`locked` / `open` / `in_progress` / `closed`), `slot` и `stages`.

**`reminder_hour`** (доработка PLAN-UI-3) — локальный час ежедневного напоминания и «сегодня
разговор», **одно правило для сервера и телефона** (`Identity\Domain\Service\UsualVisitTime`): час
обычного захода — медиана последних 7 визитов в `profiles.timezone`, вниз до часа; без визитов — 19;
никогда не раньше 8. Телефон своих заходов не считает и ставит локальные напоминания в этот час.

**`stages`** (PLAN-UI-3) — `[{stage, state}]`, `stage` ∈ `words|phrases|dialogue|listen|speak` в
порядке прохождения, `state` ∈ `done|current|locked` — ровно три слова, любое другое клиент считает
ошибкой. В списке **только этапы, которые у дня есть**: этап без карточек пропускается (не `absent`
— это слово остаётся только у кабинета дня).

| день | откуда этапы | состояния |
|---|---|---|
| карточки розданы (`in_progress` / `closed`) | счёт карточек дня по этапам (один сгруппированный запрос на весь план) | все отвечены → `done`; первый незаконченный → `current`; дальше `locked`. Закрытый день — все `done` |
| не роздан, текущий день плана, урок написан | то, что раздаст `open` (тот же раскладчик, что у кабинета) | доступен сегодня (`open`) → первый `current`, остальные `locked`; иначе все `locked` |
| не роздан, любой другой | по типу дня: сцена — пять этапов; повторение — `words`, `speak`; репетиция — `speak` | все `locked` |

По типу — ориентир: у повторения этап слов живёт только возвратами (нет дважды проваленных слов —
не будет и этапа), а возвращённая фраза добавит `phrases`; у сцены, где ни в одном обмене нет двух
реплик, не будет `listen` и `speak`. Настоящий состав приходит, когда день становится текущим.

Слоты:

| `slot.code` | `date` | `label_native` | когда |
|---|---|---|---|
| `today` | сегодня | «сегодня» | текущий день, доступен |
| `tomorrow` | завтра | «завтра» | следующий день, или текущий, чей `opens_on` завтра |
| `date` | дата | null | дальше завтрашнего; клиент форматирует дату |
| `past` | дата закрытия | null | закрытый день |

Дни после текущего считаются по одному в календарный день **от даты самого текущего дня**, а не от
сегодня. Поэтому `today` и `tomorrow` носит не больше чем по одному дню маршрута: закрытие дня N
ставит день N+1 на завтра, а день N+2 — на послезавтра.

Меню: «перенести дату» / «изменить дни» — `PATCH /plans/{id}/schedule` `{event_date?, days_total?}`
(`event_date: null` снимает дату; 409 `plan_too_short` при попытке отрезать пройденные дни);
«завершить» — `POST /plans/{id}/finish`; «новый план» — `GET /plans` + `POST /plans`; «удалить» —
`DELETE /plans/{id}` (204). `status: overdue` приходит с `overdue_native` («Приём был вчера»).

## День

| действие | вызов | ответ |
|---|---|---|
| день | `GET /plans/{id}/days/{n}` | `PlanDayRoom`: `day`, `scene`, `stages[]` (`locked`/`current`/`done`/`absent`), `metrics` (`cards_total`, `minutes_spent`), `program[]` (`unit_kind`, `source`, `state` `pending`/`passed`/`failed`) — это читают плита таба и сессия; **`window`** — окно дня (ниже) |
| открыть / продолжить | `POST /plans/{id}/days/{n}/open` | `PlanDayCards` — **весь** список карточек с состоянием; 409 `plan_day_locked` (`meta.blocked_by_day` или `meta.opens_on`), 409 `plan_lesson_not_ready` (`meta.lesson_status`: `building` — подождать, `failed` — предложить `POST …/scenes/{sceneId}/lesson/retry`) |
| перечитать карточки | `GET /plans/{id}/days/{n}/cards` | `PlanDayCards` |
| ответить | `POST …/cards/{cardId}/answer` `{result, attempts}` | `{card, requeued}`; `requeued` — та же карточка в конце этапа после первого `failed`; 409 `plan_card_answered` |
| этап пройден | `POST …/stages/{stage}/close` | `PlanDayRoom`; 409 `plan_stage_incomplete` (`meta.remaining`) |
| день пройден | `POST …/close` | `PlanDayRoom` с `metrics`; 409 `plan_stage_incomplete`; после этого `Plan.collection_id` заполнен |
| озвучка реплики, фразы или слова | `GET /plans/audio/{audioId}` | файл голоса (`audio_url` окна, `audio_id` карточки) |

Снято нарядом DAY-UI-2 вместе со старым кабинетом дня: `GET …/sheet` («шит»), `goals_native` и
`sheet_available` дня, тексты и счётчики единиц программы (`unit_ref`, `scene_id`, `text_target`,
`text_native`, `cards_total`, `cards_done`), `metrics.cards_done` / `first_try_share` /
`hardest_unit_*` (и их колонки в `plan_days`) — их читал только старый кабинет.

**Окно дня — `window`** (DAY-UI-2, DAY-UI-3, кадры 23-0a…0e; схема `PlanDayWindow`). Всё, что окно пишет,
посчитано здесь; клиент складывает слова и не выводит ни одного числа. Нет поля — клиент говорит
«не загрузилось», а не угадывает.

| поле | что это |
|---|---|
| `day` | `index`, `type`, `title_native` / `title_target`, `image` (фото сцены с `tone`, `url_112`/`url_448`) и всегда `image_tone`; `status` — `not_started` / `in_progress` / `passed` (другой запертый день — `locked`: окно его не рисует); `minutes_estimate` — «≈ N минут» до конца дня (null у пройденного); `minutes_spent` — только у пройденного; `goals[{text, passed}]` — `passed` true у всех только у пройденного дня |
| `stages[5]` | words · phrases · dialogue · listen · speak: `state` `done` / `current` / `locked`; `done_count`, `total`, `minutes_left` — **только у `current`** (у остальных null: цифру клиент не рисует); `share` 0…1 — полоса ряда. У не начатого дня все `locked` |
| `day_progress` | 0…1 — доля пройденных этапов; полоса компактной шапки |
| `program.words` | `summary {total, done, returns}` + `items[{ref, term, translation, pronunciation, definition, image, image_tone, audio_url, usage, state, returns_day}]`; `state` — `pending` / `done` / `returns_tomorrow`; `usage {text, translation, offset, length, audio_url}` — реплика дня, где слово звучит, и место слова в ней в символах (подсветка шита 23-0e; слова нет в диалоге — `null`); `returns_day` — номер дня возврата у `returns_tomorrow` («вернётся в день 3») |
| `program.phrases` | `summary` + `items[{ref, text, translation, pronunciation, audio_url, state}]` — голос ученика сцены |
| `program.dialogue` | `summary` + `items[{step, partner {text, translation, audio_url}, learner {text, translation, audio_url, state}}]` — голос у обеих реплик (каждая голосом своего говорящего), состояние — у реплики ученика |
| `allowed_action` | `start` / `continue` / `again` / null — одна кнопка. `again` — «Говорю сам» ещё раз по `GET …/cards`, ответы не отправляются (не пересдача дня) |

Минуты — `DayPace` (секунд на карточку этапа: слова 8, фразы 29, диалог 34, слушание 13, речь 41;
вверх до минуты). Состояние единицы — по её карточкам (`UnitStates`): провал дважды →
`returns_tomorrow`, все отвечены → `done`. Счётчики брови — `ProgramSummary`.

**Картинки — при генерации дня, все сразу** (DAY-UI-2 → DAY-UI-3). Урок сцены написан — сцена
`illustrating` (на проводе `lesson_status: building`), и `IllustrateSceneJob` сразу ищет фото сцены и
всех её слов **параллельно** (пул 6 запросов, повторы на 429/5xx) — лестница запросов (`ImageQueries`):
`image_prompt` → «термин, тема сцены» («appointment, doctor's office»; тема — место из описания фото
сцены или её название) → тема сцены, своя страница ответов на слово. **Никогда не голое слово**
(«marketing» → супермаркет, телефон 14.09). Только после фото сцена `ready`: день приходит с
картинками, `day_ready` пишется тогда же. Не нашлось — `image = null`, `image_tone` из палитры,
счётчик `image_missing`; job сдался — день всё равно `ready` (`FinishIllustration`). Догрузка —
`php artisan plan:images-backfill` («было пусто / стало»; `--requery` — переспросить слова, чьё фото
нашло голое слово или повторяет картинку своего дня). Одна картинка в дне не показывается дважды: слово,
чьё фото повторяет плиту или слово раньше, получает следующую страницу ответов.

**Голос сервера — всё, двумя голосами** (канон владельца, DAY-UI-3; отменяет «только реплики роли»
DAY-UI-2): озвучены реплики собеседника (`x3`) и ученика (`x3b`), фразы (`p2`) и слова (`v5`). У сцены
два голоса разного пола: пол собеседника — `role_gender` урока (`lesson-v4`), по умолчанию собеседник
женский, ученик мужской; голос ученика читает и его реплики, и фразы, и слова
(`plan_scenes.partner_voice_gender`). `VoiceSceneJob` ставится вместе с фото и **день не ждёт**: диалог —
**один** вызов Gemini TTS с двумя говорящими, разрезанный по паузам (`PcmTurnCutter`), фразы — один
вызов, слова — один (≤ 4 вызовов на день). 429 поминутный — job ждёт минуту, суточный — до окна
вендора (`RetryInfo.retryDelay`), не падает; телефон тем временем читает своим голосом. Недостающее
существующих сцен — `php artisan plan:speak-backfill` (печатает «не озвучено» по видам до и после;
`--count` — только посчитать; на суточном лимите останавливается и говорит, когда окно).

Карточка (`PlanCard`): `stage`, `position`, `kind`, `source` (`today`/`returned`), `unit_kind` /
`unit_ref`, `payload`, `retry_of`, `result`, `attempts`, `returns`. Состав `payload` по видам
описан в схеме `PlanCard`; общее: `scene_id` у всех; у карточек слов/фраз `plan_term_id`, `image`;
у карточек обмена `exchange_step`, `partner` (реплика A), `audio_id`
(→ `GET /plans/audio/{audioId}`, `audio/mpeg`).

**День известен ДО открытия.** Как только урок сцены написан, кабинет отдаёт настоящие `stages[]`
(те же счётчики, что раздаст `open`: первый этап `current`, остальные `locked`, `done` = 0) и
заполненную `program[]` — `absent` и пустая программа означают «урока ещё нет», а не «день ещё не
открыт». `open` меняет статус дня, а не появление структуры.

**Счёт дня живой.** `day.cards_done` / `minutes_spent` и `metrics` пересчитываются из карточек дня
на каждый ответ — тем же калькулятором, которым закрывается день, по тем же строкам. `metrics`
приходит у открытого дня, не только у закрытого; у неоткрытого — `null`. `cards_total` растёт,
когда проваленная карточка раздаётся заново. Окно дня (`window`) читает те же карточки — дня,
который ещё не открыт, по контуру раздачи.

Клиент оценивает сам по данным пейлоада (варианты с `correct`, `answer`, `expected` + `coverage`,
`speaking_key` + `variants`) и присылает вердикт: `passed` / `hinted` (зачёт с подсказкой) /
`failed` / `skipped` (произносимая карточка после двух попыток). Сервер ведёт последствия.

## События и уведомления

(наряд PLAN-UI-3.) Сервер ведёт **журнал плана** (`plan_events`, только дописывается) и **журнал
доставки** (`plan_notifications`, только дописывается). Отдельных ручек чтения у клиента нет:
клиент узнаёт новое обычными `GET /plans/current` / `GET /plans/{id}`, а пуш несёт только
`{kind, plan_id, day_number}` и текст — содержимого плана в нём нет.

**Устройство** (тег `Devices`):

| действие | вызов | ответ |
|---|---|---|
| адрес пуша | `PUT /devices/push-token` `{platform: "ios", token, locale?, timezone?}` | 200 `{push_enabled}`; upsert по (platform, token), токен чужого аккаунта переезжает к вызывающему. `push_enabled: true` — токен сохранён и у сервера есть ключ APNs: письма шлёт сервер, клиент **снимает все локальные напоминания**; `false` — клиент ставит их сам |
| забыть адрес | `DELETE /devices/push-token` `{platform, token}` | 204; только своя строка |
| «я здесь» | `POST /devices/visit` `{timezone?}` | 204; визит ближе 30 минут к прошлому записанному строки не пишет |

**Факты** (кто пишет → письмо):

| `kind` события | кто пишет | письмо |
|---|---|---|
| `plan_ready` | сборка плана закончилась `ready` (в той же транзакции) | `plan_ready` |
| `day_ready` (`day_number`, `payload.scene_id`) | урок сцены написан; номер дня — тот, на котором сцена стоит сейчас | `day_ready`, только для дня ≥ 2 (готовность дня 1 — часть готовности плана) |
| `day_passed` (`day_number`) | `POST …/days/{n}/close` | нет |
| `days_skipped_rebuilt` (`payload {from, to}`) | `PATCH …/schedule`, если `days_total` уменьшился | `days_skipped_rebuilt`, только живому плану (`active`/`overdue`) |
| `event_today` | тик, в день события по зоне ученика, с часа напоминаний (`reminder_hour`) | `event_today` |
| `event_passed` | тик, на следующий день после события и позже | нет |

`plan_ready`, `event_today`, `event_passed` — по разу на план, `day_passed` — по разу на день:
держит частичный уникальный индекс, повторный тик ничего не удваивает. **Сервер сам не замечает
пропущенных дней** — `days_skipped_rebuilt` сегодня рождается только из укорачивания маршрута
через `PATCH …/schedule`.

**Тик** `plan:notify-tick` — каждые 15 минут (сервис `scheduler`). Для каждого `active` плана в
зоне ученика: факты про дату события и **напоминание дня** — когда сейчас в окне
[обычное время визита; +15 мин), текущий день доступен и не закрыт и сегодня (локальная дата)
напоминания ещё не было, и сегодня не день события (в этот день одно письмо — «Сегодня …»). Время —
`reminder_hour` (правило выше: час обычного захода, 19:00 без визитов, не раньше 08:00). **Не чаще
раза в сутки**: проверка перед отправкой +
частичный уникальный индекс `(user_id, local_date) WHERE kind = 'daily_reminder'`.

**Письмо** пишется в момент отправки по свежему плану (удалённому, завершённому плану письма нет)
и уходит через `PushSender`. Нет `APNS_KEY_P8` — **сухой режим**: письмо целиком в лог приложения,
строка доставки `not_sent`. С ключом: нет адресов — `no_token`; APNs принял хоть для одного — `sent`;
отказал всем — `failed`; адрес с 410 / `BadDeviceToken` удаляется.

Тексты (ru; en — в `NotificationTexts`):

| `kind` письма | заголовок | текст |
|---|---|---|
| `plan_ready` | План готов | «До приёма · 7 дней. День 1 — «Регистратура»» (с датой события); без даты — «5 дней. День 1 — «…»» |
| `day_ready` | День {n} собран | «{название дня}» — можно начинать |
| `daily_reminder` | День {n} ждёт | «{название дня}» — начни с того места, где остановился |
| `event_today` | Сегодня {event_native с маленькой буквы} (без него — «Сегодня разговор») | Скажи сам перед разговором — прогони его вслух |
| `days_skipped_rebuilt` | Маршрут пересобран | Было 7 дней, стало 5 |

Название дня — `title_native` сцены; у дня повторения — «Повторение», у репетиции — «Репетиция».
Пока `push_enabled: false` (сегодня: бесплатная Personal Team без Push capability, ключа нет) напоминание,
«событие сегодня» и пропуски клиент ставит **локально** в `reminder_hour`, «план готов» / «день
собран» показывает в приложении при возвращении; сервер всё равно пишет факты и пытается доставить.
Как только регистрация токена ответит `push_enabled: true`, клиент снимает локальные — дублей нет.

## Коды 409

`plan_state`, `plan_already_active`, `plan_core_scene`, `plan_day_locked`, `plan_day_not_open`,
`plan_lesson_not_ready`, `plan_stage_incomplete`, `plan_card_answered`, `plan_too_short`. 404:
`plan_not_found`, `plan_scene_not_found`, `plan_day_not_found`, `plan_card_not_found`,
`plan_scene_image_not_found`. 503: `plan_scene_image_unavailable`.

## Что клиенту НЕ надо считать

Дни, слоты, фразу обратного отсчёта, строку маршрута, состав дня, возвраты, метрики, минуты дня и
этапа, состояния единиц, счётчики бровей окна, действие дня. Что клиент считает сам: формат дат и
чисел, обрезку длинных заголовков (лимиты промпта — ориентир, `char_limits` только считается).

## Админка

`GET /admin/api/plans/checks` → `[{prompt_version, check, action, hits}]` — счётчики проверок по
версии промпта (`counted` / `dropped` / `gated`). Стоимость плана — в `GET /admin/api/costs`
строкой `plan` (сумма `plans.cost_usd_plan` + `plan_scenes.cost_usd_lesson`).
