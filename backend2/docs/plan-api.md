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

`GET /plans/{id}` → `Plan`: `scenes[]` (с `image`, `lesson_status`, `lesson_fail_reason`, `day_number`), `days[]`
(маршрут с типами, слотами и этапами), `until_phrase`, `route_summary`, `summary`,
`days_shortened_from` (клиент показывает строку «до события N дней — план короче»), `rescue_kit`
(пять фраз).

`lesson_fail_reason` у `lesson_status = failed` — строка для лога и админки, клиент её не разбирает: ответ не по
схеме после ретрая — причина разбора; модель недоступна — её ошибка; **урок не прошёл порог** (фатальная находка
осталась после починки двух карточек или стоит не на карточке, канон §4) — `fatal: <коды через запятую>`, например
`fatal: line.ne_frame, check.shape`. Любую из них ученик повторяет тем же `POST …/scenes/{id}/lesson/retry`.

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
| день | `GET /plans/{id}/days/{n}` | `PlanDayRoom`: `day`, `scene`, `stages[]` (`locked`/`current`/`done`/`absent`, **и `cards[]`** — карточки этапа по `position`, в конверте разд. «Карточки сессии»; у нерозданного дня список пуст: у контура нет id, которым отвечают), `metrics` (`cards_total`, `minutes_spent`), `program[]` (`unit_kind`, `source`, `state` `pending`/`passed`/`failed`) — это читают плита таба и сессия; **`window`** — окно дня (ниже) |
| открыть / продолжить | `POST /plans/{id}/days/{n}/open` | `PlanDayCards` — **весь** плоский список карточек с состоянием; 409 `plan_day_locked` (`meta.blocked_by_day` или `meta.opens_on`), 409 `plan_lesson_not_ready` (`meta.lesson_status`: `building` — подождать, `failed` — предложить `POST …/scenes/{sceneId}/lesson/retry`) |
| перечитать карточки | `GET /plans/{id}/days/{n}/cards` | `PlanDayCards` (тот же плоский `cards[]`) |
| ответить | `POST …/cards/{cardId}/answer` `{result, attempts, response?}` | `{card, requeued, unit {kind, ref, returns_tomorrow, returns_day}, day {cards_total, cards_done, minutes_spent}, stage {stage, minutes_spent}}` — из этого клиент пишет итог этапа (30-6/33-8/34-8/35-6) и итог дня (30-7), не считая ничего сам. `requeued` — та же карточка в конце этапа после первого `failed` (новый `id`, `retry_of`), иначе `null`; 409 `plan_card_answered`, 422 `plan_card_result_not_allowed` (вид такого итога не принимает) |
| зачесть окно или пересказ | `POST …/cards/{cardId}/judge` `{heard, hinted}` | `{accepted, slot_value, reason_native, result, attempts, card}` — судья окна `slot_judge.v1`, только `phrase_own_slot` / `speak_answer` / `speak_retell`; 422 `plan_card_not_judged` у любого другого вида, 409 `plan_card_answered`, 409 `plan_day_not_open` |
| этап пройден | `POST …/stages/{stage}/close` | `PlanDayRoom`; 409 `plan_stage_incomplete` (`meta.remaining`) |
| день пройден | `POST …/close` | `PlanDayRoom` с `metrics`; 409 `plan_stage_incomplete`; после этого `Plan.collection_id` заполнен |
| озвучка реплики, фразы или слова | `GET /plans/audio/{audioId}` | файл голоса. Клиент этот адрес не собирает: в окне он приходит готовой строкой `audio_url`, в карточке — полем `url` объекта `audio` (ниже) |

**Имя маршрута ответа.** Наряд SESSION-1a зовёт ручку итога «`…/result`» — это она же, `POST
…/cards/{cardId}/answer`; адрес не менялся, изменилось только тело (`response`) и ответ. Сказано
здесь один раз, чтобы не искать несуществующий маршрут.

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
| `program.words` | `summary {total, done, returns}` + `items[{ref, term, translation, pronunciation, definition, image, image_tone, audio_url, usage, state, returns_day, used_in}]`; `state` — `pending` / `done` / `returns_tomorrow`; `usage {text, translation, offset, length, audio_url}` — реплика дня, где слово звучит (по `used_in` урока, иначе первая реплика визита со словом), и место слова в ней в символах (подсветка шита 23-0e; слова нет в диалоге — `null`); `returns_day` — номер дня возврата у `returns_tomorrow` («вернётся в день 3»); `used_in` — где урок говорит слово (`p3` каркас или его наполнение, `A3` реплика собеседника), аддитивно GEN-2a |
| `program.phrases` | `summary` + `items[{ref, text, translation, pronunciation, audio_url, state, frame}]` — голос ученика сцены. Фраза — каркас (`lesson_day.v4.5`): `text`/`translation`/`pronunciation` — каркас с наполнением его первой реплики диалога; `frame {target, native, pronunciation, kind, slot {hint, fillers[{target, native, pronunciation, in_dialogue, audio_url}]} \| null}` — сам каркас, аддитивно GEN-2a; `audio_url` наполнения — каркас, сказанный с этим наполнением, голосом ученика (TTS-2; у наполнения, которым сказана сама фраза, — файл фразы) |
| `program.dialogue` | `summary` + `items[{step, kind, partner {text, translation, audio_url}, learner {text, translation, audio_url, state, phrase_ref, filler}}]` — голос у обеих реплик (каждая голосом своего говорящего), состояние — у реплики ученика; реплика ученика — собранная сервером из каркаса и наполнения; `kind` (answer / ask / rescue), `phrase_ref`, `filler` — аддитивно GEN-2a |
| `allowed_action` | `start` / `continue` / `again` / null — одна кнопка. `again` — «Говорю сам» ещё раз по `GET …/cards`, ответы не отправляются (не пересдача дня) |
| `listening` | вопросы обо всём визите дня (`lesson_day.v4.5`): `[{question, options[{text, correct}], explanation_native}]` на родном языке, верный — на перемешанном сервером месте; у повторения и репетиции — `[]`; аддитивно GEN-2a. Это **не** источник сессии: те же вопросы этап «Слушаю и отвечаю» раздаёт карточками `listen_question` со своими вариантами и `correct` по id — окно держит их для разбора |

Минуты — `DayPace`: **секунды на карточку по ВИДУ**, не по этапу (наряд SESSION-1a), таблица в
`config/plan.php` → `plan.pace`, начальные значения наряда — подкручиваются там, не в коде. Этап
реестра мешает десятисекундный тап с тридцатисекундной репликой вслух, поэтому ставка на этап
обещала бы одинаковые минуты этапу тапов и этапу речи. Самые дорогие: `listen_dialogue` 110 (весь
визит проигрывается целиком), `speak_answer` 35, `listen_review` / `speak_retell` 30,
`dialogue_answer` / `dialogue_ask` 30, `phrase_assemble` / `phrase_repeat` / `phrase_other_slot` /
`phrase_own_slot` 25, `listen_pace` / `speak_echo` 25; самая дешёвая — `word_intro` 8. Вид, которого
в таблице нет, стоит 0, а не догадку. `minutes_left` этапа — по его **неотвеченным** карточкам,
`minutes_estimate` дня — по всем до старта и по неотвеченным, пока день идёт; вверх до минуты
(этап с оставшейся карточкой никогда не говорит «0»). Состояние единицы — по её карточкам
(`UnitStates`; карточки с `unit.kind = day` пропускаются): провал дважды → `returns_tomorrow`, все
отвечены → `done`. Счётчики брови — `ProgramSummary`.

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

**Голос сервера — ElevenLabs, всё, что звучит в дне** (DAY-UI-3, TTS-2): реплики собеседника (`x3`) и ученика
(`x3b`), фразы (`p2`), фразы с наполнениями (`p2.f3` — каркас фразы 2 с третьим наполнением) и слова (`v5`). У сцены
два человека разного пола: пол собеседника — `role_gender` урока, по умолчанию собеседник женский, ученик мужской;
голос ученика читает его реплики, фразы, наполнения и слова (`plan_scenes.partner_voice_gender`). Голос выбирается по
роли и полу, у каждой роли свой женский и свой мужской голос — голоса ролей в сцене всегда разные. Модель — `eleven_v3_conversational`
(v3 Conversational), stability 0.5 (Natural), mp3 44,1 кГц 128 кбит/с, темп всегда обычный: замедление «Повтори вслух» —
забота клиента (воспроизведение 0.85×). `VoiceSceneJob` ставится вместе с фото, и
**день не ждёт**: каждая строка — отдельный вызов своим голосом (связная интонация диалога не требуется), по три
одновременно (тариф Starter). Лимит
одновременности (429) — короткие повторы, потом job ждёт и не падает; отказ аккаунта (401/402, голос не по тарифу) —
job `failed` с кодом вендора, письмо в лог. Остаток кредитов аккаунта меньше 10 % — очередь голоса не покупает
(предохранитель). Телефон тем временем читает своим голосом. Недостающее существующих сцен —
`php artisan plan:speak-backfill {--plan=*} {--count} {--drop-unread} {--drop-only}`: сцена за сценой, планы учеников раньше QA-аккаунтов,
«не озвучено» по пяти видам до и после, `--count` — цена недостающего; `--drop-unread` переозвучивает строки голоса,
сменённого в пакете, `--drop-only` только удаляет их, ничего не покупая. Один запуск покупок не тратит больше `SPEECH_JOB_CREDITS_CAP` (3 000 кредитов). Во что обошлось —
`php artisan plan:speak-report {--plan=} {--day=}`: символы, кредиты, $, вызовы по плану, дню, виду строк.

Карточка (`PlanCard`) и её `payload` по видам — раздел «Карточки сессии» ниже; машинно — схема
`PlanCard` в `openapi/openapi.yaml`.

**День известен ДО открытия.** Как только урок сцены написан, кабинет отдаёт настоящие `stages[]`
(те же счётчики, что раздаст `open`: первый этап `current`, остальные `locked`, `done` = 0) и
заполненную `program[]` — `absent` и пустая программа означают «урока ещё нет», а не «день ещё не
открыт». `open` меняет статус дня, а не появление структуры.

**Счёт дня живой.** `day.cards_done` / `minutes_spent` и `metrics` пересчитываются из карточек дня
на каждый ответ — тем же калькулятором, которым закрывается день, по тем же строкам. `metrics`
приходит у открытого дня, не только у закрытого; у неоткрытого — `null`. `cards_total` растёт,
когда проваленная карточка раздаётся заново. Окно дня (`window`) читает те же карточки — дня,
который ещё не открыт, по контуру раздачи.

## Карточки сессии

(Наряд SESSION-1a. Кадры — `docs/session-map.md`, правда по кадрам — канва
`docs/design/session-canvas.dc.html`.)

Реестр тренажёров: в enum `kind` **29 значений**, раздаются **28**. `listen_pairs` (34-4) в enum
есть и не раздаётся никогда — в уроке нет двух похожих реплик, источник появится в `lesson_day.v4.6`;
клиент держит ветку для неизвестного `kind` (пропустить карточку), а не падает.

**Конверт карточки** — один на все виды:

```
{id, stage, position, kind, unit {kind: word|phrase|exchange|day, ref},
 source: today|returned, source_day, retry_of, payload, result, attempts, response}
```

Плюс старые ключи тем же значением, аддитивно: `unit_kind`, `unit_ref`, `source_day_id`,
`answered_at`, `returns`. Порядок прохождения внутри этапа — `position`. `source_day` — **номер**
дня, на котором возвращённая единица провалилась (у `source: today` — `null`). `response` — то, что
клиент прислал с ответом, или то, что записал судья; до ответа `null`.

**Звук.** У каждого звучащего элемента — объект `audio {ref, url, duration_ms, voice}`, где `voice`
∈ `partner` / `learner`, `url` — готовый абсолютный адрес `GET /plans/audio/{id}` (или `null`, пока
файла нет), `duration_ms` — длительность (`null`, если неизвестна). `ref` — как в
`plan_line_audios`: реплика собеседника `x3`, реплика ученика `x3b`, фраза `p2` (каркас с
наполнением первой реплики), каркас с наполнением 3 — `p2.f3`, слово — `v5`. Плеер, «по частям» и
темпы 0.75× / 0.85× — забота клиента. **Картинка** слова — `image {url, tone}` внутри `term` или
`prompt` (`url` `null`, если фото не нашлось; `tone` рисует кружок).

**`scene_id` есть в каждом payload.**

### Реестр видов

`payload` — ключи верхнего уровня; всё, что не отмечено «может быть `null`», приходит всегда.

| `kind` | кадр | `payload` | режим клиента | кто и как зачитывает |
|---|---|---|---|---|
| **Слова** — `unit.kind = word`, ref `v3` | | | | |
| `word_intro` | 31-1 | `term`, `used_in` (реплика дня со словом и место слова в ней, или `null`), `audio {term, line}` (`line` — `null`, если слова нет в репликах) | чтение + звук | тап «Дальше» → `passed` |
| `word_repeat` | 31-2 | `term`, `expected_text`, `coverage_min`, `audio {term}` | голос | клиент: покрытие речи → `passed`; две попытки без зачёта → `skipped` |
| `word_choose` | 31-3 (`term_to_native`) / 31-4 (`native_to_term`) | `direction`, `prompt`, `options[4]`, `correct`. beginner — `term_to_native`: `prompt {text_target, image, audio}`, варианты на родном; intermediate — `native_to_term`: `prompt {text_native, image}` без звука, варианты — слова цели, у каждого свой `audio` | тап по варианту (30-9) | клиент: id варианта = `correct` |
| `word_listen` | 31-5 | `audio` (только звук: ни текста, ни перевода, ни фото), `options[4]` — **слова цели** (звук → написание, кадр 31-5), `correct` | тап | клиент: сверка с `correct` |
| `word_assemble` | 31-6 | `term`, `tiles[]`, `expected[]` | плитки (30-5) | клиент: собранное = `expected` по порядку |
| `word_in_line` | 31-7 | `line` (текст цели с `___`, `text_native`, `text_native_gapped` — может быть `null`, `audio`), `options[4]` (у каждого свой `audio`), `correct` | тап | клиент: сверка с `correct` |
| **Фразы** — `unit.kind = phrase`, ref `p3` | | | | |
| `phrase_intro` | 32-1 | `frame`, `said` | чтение + звук + чипы | тап «Дальше» → `passed` |
| `phrase_assemble` | 32-2 | `frame`, `target_native`, `tiles[]`, `chips[]`, `expected {words, slot_at, filler_index}` | плитки + чип в окно | клиент: слова = `expected.words` по порядку, окно на месте `slot_at`, наполнение — `expected.filler_index` |
| `phrase_choose_back` | 32-3 | `prompt` (цель: `text_target`, `pronunciation_native`, `filler_index` — может быть `null`, `audio`), `options[4]` (на родном), `correct` | тап | клиент: сверка с `correct` |
| `phrase_slot` | 32-4 | `frame`, `prompt_native`, `options[4]` (наполнения, у каждого `audio`), `correct` | тап | клиент: сверка с `correct` |
| `phrase_slot_listen` | 32-5 | `frame`, `filler_index`, `audio` (звучит одно наполнение), `options[4]` (без звука), `correct` | тап | клиент: сверка с `correct` |
| `phrase_repeat` | 32-6 | `frame`, `filler_index` (может быть `null` у каркаса без окна), `expected_text`, `key`, `coverage_min`, `audio` | голос | клиент: покрытие `expected_text`; две попытки → `skipped` |
| `phrase_other_slot` | 32-7 | `frame`, `filler_index` (НЕ сказанное), `task_native`, `expected_text`, `slot_expected`, `key`, `coverage_min` | голос, звука у листа нет | клиент: покрытие каркаса вне окна **И** все слова `slot_expected` услышаны (каркас и окно считаются отдельно) |
| `phrase_combine` | 32-8 | `exchange`, `partner_line`, `frames[3]`, `correct_frame`, `chips[]`, `correct_filler` | тап: каркас → чип | клиент: каркас = `correct_frame`, наполнение — **любое** из `chips` |
| `phrase_own_slot` | 32-9 | `frame`, `partner_line` (может быть `null`), `task_native`, `examples[]`, `chips[]`, `key`, `coverage_min`, `judge: true` | чипы или «сказать своё» голосом | **сервер**, `POST …/judge` |
| **Диалог** — `unit.kind = exchange`, ref `x3` | | | | |
| `dialogue_partner` | 33-1 | `exchange`, `partner_line`, `question_native`, `options[4]` (на родном), `correct` | тап | клиент: сверка с `correct`; текст реплики открывается после верного |
| `dialogue_answer` | 33-2 / 33-3 / 33-4 | `exchange`, `partner_line`, `own_line`, `frame`, `modes {chips, voice_hint, voice_blind}`, `coverage_min` | чипы, голос с подсказкой или голос вслепую — выбирает клиент | клиент: чипами — верно любое наполнение; голосом — покрытие каркаса вне окна, окно любое; две попытки → `skipped` |
| `dialogue_ask` | 33-5 | те же ключи (ученик говорит первым, ответ собеседника звучит после) | то же | то же |
| `dialogue_rescue` | 33-6 | `exchange`, `asked_line` (может быть `null`), `rescue_line`, `partner_repeat`, `slow_rate`, `expected_text`, `coverage_min` | прослушивание и переспрос | тап «Дальше» → `passed`: микрофона на кадре нет, `expected_text` и `coverage_min` лежат в payload на будущее и сейчас не зачитываются |
| **Слушаю и отвечаю** — `unit.kind = day`, ref `day`; у `listen_question` ref `L2` | | | | |
| `listen_dialogue` | 34-1 | `lines[]` (весь визит по порядку), `total_ms` (может быть `null`) | плеер, текста клиент не показывает | тап «Дальше» → `passed` |
| `listen_question` | 34-2 | `question {ref, text_native}`, `options[4]`, `correct`, `exchange_step` (может быть `null`) | тап | клиент: сверка с `correct` |
| `listen_review` | 34-3 | `lines[]`, `total_ms`, `answers[{question_ref, exchange_step, line_ref, span}]` (`line_ref` и `span` — где подсветить ответ; могут быть `null`) | разбор с подсветкой | тап «Дальше» → `passed` |
| `listen_pairs` | 34-4 | — | — | **не раздаётся**, зарезервирован в enum |
| `listen_predict` | 34-5 | `exchange`, `own_line`, `options[3]` (на родном), `correct`, `partner_line` | тап | клиент: сверка с `correct`; реплика собеседника открывается после |
| `listen_pace` | 34-6 | `exchange`, `partner_line`, `rates [0.75, 1.0]` | плеер на двух темпах | тап «Понял» → `passed` |
| `listen_number` | 34-7 | `line`, `span` (число внутри `line.text_target`), `options[3]` (на родном), `correct` | тап | клиент: сверка с `correct` |
| **Говорю сам** — `unit.kind = exchange` | | | | |
| `speak_answer` | 35-2 | `exchange`, `partner_line` (у `answer`-обмена — его собственная реплика A, у `ask`-обмена — реплика A **предыдущего** обмена как контекст; у первого обмена `null`), `own_line`, `task_native`, `frame`, `hint`, `key`, `coverage_min`, `judge: true` | голос; каркас-подсказка после 5 с молчания или по кнопке (35-5) | **сервер**, `POST …/judge` |
| `speak_echo` | 35-3 | `exchange`, `partner_line`, `expected_text`, `coverage_min` (0.7), `pause_ms` (3000) | голос, текст скрыт до ответа | клиент: покрытие; две попытки → `skipped` |
| `speak_retell` | 35-4 | `exchange`, `partner_line`, `reveal {text_target, text_native}`, `judge: true` | голос на **родном** | **сервер**, `POST …/judge` |

Общие объекты, одинаковые везде: `term {ref, text_target, text_native, pronunciation_native,
definition_target, image}`; `frame {ref, kind, frame_target, frame_native,
frame_pronunciation_native, slot}`, где `slot` — `null` или `{hint_native, fillers[]}`, а наполнение
— `{index, target, native, pronunciation_native, in_dialogue, native_line, audio}`; `exchange {ref,
step, kind}`; реплика — `{ref, text_target, text_native, audio}`; `own_line` дополнительно несёт
`frame_ref`, `filler_index` и `key` (серверный ключ произнесения).

### Режим ввода выбирает клиент

Карточка режим не выбирает: в payload лежат данные для **всех** режимов сразу (`modes` у
`dialogue_answer` / `dialogue_ask`, `chips` рядом с голосом у `phrase_own_slot`), режим выбирает
клиент — по уровню плана и по переключателю «Без подсказок» (30-1). Ориентир наряда: beginner —
чипы, intermediate — голос с подсказкой, «Без подсказок» — голос вслепую. **Состав дня от
переключателя не зависит**: сервер раздал одинаковый день, переключатель меняет только то, как
карточка спрашивает.

### Покрытие речи

`coverage_min` приходит в payload **числом**: `1` — сказать всё, `0.7` — сказать 70 % слов. Единица
уезжает на провод **целым числом** (`"coverage_min": 1`), семь десятых — дробным
(`"coverage_min": 0.7`): клиент читает поле как `num`, а не как `double`, иначе разбор падает на
первой же голосовой карточке.

Правило (`SpeechCoverage`, то же на сервере перед судьёй и на клиенте):

- ожидаемый текст ≤ 2 значащих слов → нужны **все**; 3 и больше → **70 %**;
- сверка **мультимножеством** и без порядка: строка, где слово сказано дважды, требует его дважды,
  а распознаватель слова роняет и меняет местами, но не переставляет;
- **артикли языка цели прощаются** (список — в пакете языка, не константа `a/an/the`; у языка без
  такого правила не прощается ничего).

У `phrase_other_slot` каркас и окно считаются **раздельно**: покрытие `expected_text` по правилу
выше плюс все слова `slot_expected` подряд.

### Плитки и `expected` — одно написание

У `phrase_assemble` плитки каркаса приходят **в нижнем регистре** (заглавная первого слова выдала бы
его место), кроме «I», которое остаётся собой; `expected.words` написаны **точно так же**. Клиент
сравнивает собранное с `expected` слово в слово и **сам возвращает заглавную**, когда показывает
предложение целиком. У `word_assemble` плитки и `expected` тоже одного написания — там это
собственное написание термина (`X-ray` остаётся `X-ray`).

### Что клиент шлёт в `POST …/answer`

`result` ∈ `passed` / `hinted` / `failed` / `skipped`, `attempts` ≥ 1, `response` — необязателен.
**Что позволено — решает вид** (`CardKind::allows`), чужой итог отбивается 422
`plan_card_result_not_allowed` и на карточке не остаётся ничего:

| группа видов | что принимает `…/answer` |
|---|---|
| выбор и сборка (`word_choose`, `word_listen`, `word_assemble`, `word_in_line`, `phrase_assemble`, `phrase_choose_back`, `phrase_slot`, `phrase_slot_listen`, `phrase_combine`, `dialogue_partner`, `listen_question`, `listen_predict`, `listen_number`) | все четыре |
| голос (`word_repeat`, `phrase_repeat`, `phrase_other_slot`, `dialogue_answer`, `dialogue_ask`, `speak_echo`) | `passed`, `hinted`, `skipped`. **`failed` — 422**: две попытки без зачёта это `skipped`, молчание распознавателя не доказывает провала, и возврата у него нет |
| прохождение (`word_intro`, `phrase_intro`, `dialogue_rescue`, `listen_dialogue`, `listen_review`, `listen_pace`) | `passed` или `skipped` — «Дальше» и «Понял» это `passed` |
| судья (`phrase_own_slot`, `speak_answer`, `speak_retell`) | **только `skipped`** («Пропустить»). Зачёт этих видов пишет сервер в `…/judge`; `passed` от клиента — 422 |

«Пропустить» на любой карточке — это `skipped`.

Последствия ведёт сервер: **первый** `failed` выбора возвращает ту же карточку копией в конец
своего этапа (`requeued` в ответе, у копии свой `id` и `retry_of`; `cards_total` дня растёт).
**Копия перемешана заново** — её `options` и `tiles` в другом порядке (сид — id исходной карточки),
id вариантов и `correct` остаются верными, так что запомнить «правильный был второй» нельзя.
**Второй** `failed` возвращает единицу: `unit.returns_tomorrow: true` и `unit.returns_day` — номер
дня, на котором она придёт одной карточкой (слово → `word_choose`, каркас → `phrase_slot`, обмен →
`speak_answer`, `source: returned`). Единица `day` не возвращается никогда.

Ключи `response` (хранятся только они, лишнее отбрасывается): `heard` (≤ 1000), `hinted_at` (≤ 40),
`slot_value` (≤ 200), `filler_index` (0…9), `mode` (`chips` / `tiles` / `voice_hint` /
`voice_blind`), `no_mic` (bool).

### Судья окна — `POST …/cards/{cardId}/judge`

Тело `{heard: string, hinted: bool}` (`heard` обязателен и может быть пустым — молчание тоже
попытка). Только `phrase_own_slot`, `speak_answer`, `speak_retell`; у любого другого вида — 422
`plan_card_not_judged`, у отвеченной карточки — 409 `plan_card_answered`, у закрытого дня — 409
`plan_day_not_open`.

Ответ `{accepted, slot_value, reason_native, result, attempts, card}`:

- `accepted: true` → `result` = `passed`, а при `hinted: true` — `hinted` (каркас был на экране до
  попытки). У `phrase_own_slot` `hinted` не значит ничего и не пишется: его каркас на экране всегда;
- `accepted: false` → `result` остаётся **`null`**, `attempts` на единицу больше; карточка ждёт
  следующей попытки («Ещё раз») или «Пропустить» обычным `POST …/answer` со `skipped` (35-5);
- `reason_native` — одна короткая фраза на родном, когда не зачтено; при зачёте `null`;
- `slot_value` — что сервер услышал в окне (у `speak_retell` всегда `null`).

Порядок зачёта у `phrase_own_slot` и `speak_answer`: сначала код — не покрыты слова каркаса вне окна
→ отказ с «Каркас не прозвучал — скажи его целиком», и модель не зовётся; каркас без окна и знакомое
наполнение, услышанное подряд, зачитываются кодом же. Дальше — модель, она судит **только окно**:
один вызов, таймаут 8 с, кап на ученика в сутки (по умолчанию 60). У `speak_retell` каркаса нет —
судит всегда модель, на вопрос «пересказ на родном сохранил смысл реплики» — но пустое `heard` она не
видит: молчание отклоняет код («Я ничего не услышал — перескажи своими словами»), иначе деградация
ниже засчитала бы пересказ, которого не было. **Модель промолчала,
ответила не по форме или кап исчерпан — попытка зачтена по коду** (`slot_value` = слова сверх
каркаса), ученик за молчание вендора не платит. Клиенту различать это не нужно: он читает
`accepted` и `result`.

### Чего сервер не даёт

Короткий список, чтобы клиент не искал несуществующие поля (данных нет в уроке, не в контракте):

| кадр | чего нет | что есть вместо |
|---|---|---|
| 31-1 | определения слова на **родном** | `term.definition_target` — определение на языке цели (и `text_native` — перевод) |
| 33-5 | текста задания «Спроси про работу» | `own_line.text_native` — перевод собственной реплики; задание клиент формулирует сам |
| 34-7 | текста вопроса («что за число?») | только `line`, `span` и варианты: вопрос рисует клиент |

Расхождения с канвой, которые клиент увидит на проводе:

- `dialogue_partner` (33-1) и `listen_question` (34-2) приходят с **четырьмя** вариантами, хотя
  проверка урока даёт три: четвёртый строит сервер — у `dialogue_partner` это верный вариант
  проверки самого дальнего по шагу обмена, у `listen_question` — неверный вариант другого вопроса.
  Варианты никогда не совпадают ни с верным, ни друг с другом (без учёта регистра);
- копия карточки после первого `failed` встаёт в конец этапа, поэтому в этапе «Слушаю и отвечаю»
  она может оказаться **после** `listen_review`, который уже показал разбор.

### Входные фикстуры клиента

`docs/fixtures/day-doctor.json` (intermediate) и `docs/fixtures/day-doctor-beginner.json`
(beginner) — полный раздатый день «Приём у врача»: тело `data` ответа
`GET /api/v1/plans/{id}/days/1` как есть (`plan_id`, `day`, `scene`, `stages[]` с карточками,
`metrics`, `program`, `window`). Оба дня — 75 карточек: слова 24, фразы 19, диалог 15, слушание 9,
речь 8. Id и адреса в фикстурах подставные и детерминированные (`ulid-0001…`,
`http://localhost/api/v1/plans/audio/…`), тест держит файлы байт-в-байт — на них и пишется разбор
на клиенте.

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

422 (наряд SESSION-1a): `plan_card_result_not_allowed` — вид карточки такого итога не принимает
(`meta {kind, result}`: `passed` на судейской карточке, `failed` на голосовой, `hinted` на
прохождении); `plan_card_not_judged` — `POST …/judge` на виде, который судья не судит
(`meta {kind}`). Оба — отказ до записи: на карточке не меняется ничего.

## Что клиенту НЕ надо считать

Дни, слоты, фразу обратного отсчёта, строку маршрута, состав дня, возвраты, метрики, минуты дня и
этапа, состояния единиц, счётчики бровей окна, действие дня. Наряд SESSION-1a добавил сюда: порядок
карточек (`position`), какой вид на какую единицу пришёл, перемешивание вариантов и плиток (включая
перемешивание копии после провала), четвёртый вариант выбора, номер дня возврата (`returns_day`),
порог покрытия (`coverage_min`), ключ произнесения (`key`), адрес и длительность звука
(`audio.url`, `duration_ms`), зачёт «по смыслу» (судья).

Что клиент считает сам: формат дат и чисел, обрезку длинных заголовков (лимиты промпта — ориентир,
`char_limits` только считается), **режим ввода** (уровень плана + «Без подсказок»), покрытие речи по
`coverage_min` на голосовых видах, сверку выбора с `correct` и сборки с `expected`, заглавную букву
первого слова при показе собранного предложения, и таймер подсказки (5 с молчания — константа
клиента, сервер её не шлёт).

## Админка

`GET /admin/api/plans/checks` → `[{prompt_version, check, action, hits}]` — счётчики проверок по
версии промпта (`counted` / `dropped` / `gated`). Стоимость плана — в `GET /admin/api/costs`
строкой `plan` (сумма `plans.cost_usd_plan` + `plan_scenes.cost_usd_lesson`).
