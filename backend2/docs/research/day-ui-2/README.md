# DAY-UI-2 — «Окно дня» по кадрам 23-0a…0d + бэкенд под него

Наряд DAY-UI-2 (14.09.2026). Канва — `backend2/docs/design/plan-canvas.dc.html`, серия 23 (кадры
23-0a «не начат», 23-0b «идёт», 23-0c «пройден», 23-0d «три вкладки в трёх состояниях»), таблицы
«Изменения · серия 23» и «Тайминг · серия 23», `@keyframes om-cab-in` / `om-check-pop`.

## Ч.0 — что прочитано и где документы расходятся

Прочитаны: `docs/DECISIONS.md` (реестр), `backend2/docs/session-handoff.md`, `docs/plan-ui-glossary.md`,
`backend2/docs/design/design-map.md`, код кадров 23-0a…0d (скриптом, дерево элементов со стилями),
`docs/plan-v2.md`, `docs/plan-api.md`, код окна DAY-UI (`day_room_screen.dart`, `DayRoomPlate` в
`ui/day_plate.dart`) и GET дня (`GetDayRoomHandler`, `PlanJson::room`).

Реестру постановка не противоречит: пп. 271–272 («цифра одна — минуты», «Пройти ещё раз») — решения
первой цепочки плана, отменённой целиком 10.09 (раздел «Отменено», первая строка: «действующие решения
о плане — пп. 304–308»), и кабинет DAY-UI уже печатает «Слова · 12 из 32».

Расхождения внутри постановки (выбрано и названо, не угадано молча):

| место | наряд | код кадра | взято |
|---|---|---|---|
| название дня на плите | «Literata 30» | `font-size:44px; line-height:48px` во всех трёх кадрах и в описании серии | **44 / 48** — наряд велит читать код кадров, а не описание |
| «прослушать» у фраз | 28 | 30 (значок 15) у фраз, 28 у собеседника; «Вычтено» 23-0d: «прослушать 28, как у фраз» | **28** везде — подпись и наряд старше картинки |
| бровь диалога | «бровь словами из summary» | «ДИАЛОГ» без числа, «ДИАЛОГ · 2 ПРОЙДЕНО» | как в кадре: у диалога общего числа нет, у слов и фраз есть |

## Ч.1 — чего не хватает контракту `GET /plans/{id}/days/{n}` (список ДО правок)

Сегодня ответ — `PlanDayRoom`: `day` (строка маршрута), `scene`, `goals_native`, `stages[]`, `metrics`,
`program[]`, `sheet_available`. Его же читают плита таба (21-2…21-4) и каркас сессии, поэтому новое
кладётся **рядом**, отдельным блоком `window`, а не переименованием старых ключей (у `day.status`,
`stages[].total` и `program` в окне другой смысл).

| # | окну нужно | GET дня сегодня | решение |
|---|---|---|---|
| 1 | статус словами: не начат / идёт / пройден | `day.status` = locked/open/in_progress/closed; день 1 неначатого плана — `locked` | `window.day.status` ∈ `not_started` / `in_progress` / `passed`; другой запертый день — `locked`, окно его не рисует (честная ошибка) |
| 2 | «≈ 20 минут» у не начатого и идущего | оценки нет нигде (клиент считал 13 с на карточку) | `window.day.minutes_estimate` — сервер, темп по этапам |
| 3 | «пройден · 19 минут» | `day.minutes_spent` у любого дня | `window.day.minutes_spent` только у пройденного, иначе null |
| 4 | фото дня под скримом, кружок 32 | `scene.image` (url, tone, url_112/448) | `window.day.image` + `image_tone` всегда |
| 5 | «научишься» с галками | `goals_native` — строки без состояния | `window.day.goals[{text, passed}]`; `passed` — только у пройденного дня |
| 6 | пять рядов: слово состояния, цифра только у текущего, «≈ N мин» текущего, полоса | `stages[{stage,total,done,state}]`: числа у ВСЕХ, `absent`, у не начатого первый `current`; минут и доли нет | `window.stages[{stage, state, done_count, total, minutes_left, share}]`; `done_count`/`total`/`minutes_left` не null ТОЛЬКО у `current`; у не начатого все `locked` |
| 7 | полоса компактной шапки — прогресс по этапам | нет | `window.day_progress` 0…1 — доля пройденных этапов |
| 8 | слова: термин, перевод, фото + тон, состояние | `program[]`: text/state `pending/passed/failed`; фото только в шите и карточках; тона у терминов в базе нет | `window.program.words.items[{ref, term, translation, image, image_tone, state}]`, state ∈ `pending/done/returns_tomorrow`; колонка `plan_terms.image_tone` |
| 9 | фразы: текст, перевод, «прослушать», состояние | текст и перевод есть; озвучки фраз на сервере нет — только реплики собеседника | серверная озвучка фраз в том же хранилище (`plan_line_audios.line_ref`), `audio_url` |
| 10 | диалог парами: собеседник (текст, перевод, audio_url), ученик (текст, перевод, состояние) | единица «обмен»: `text_target` = своя реплика, `text_native` = задание; реплика собеседника и `audio_id` только в карточках | `window.program.dialogue.items[{step, partner{text, translation, audio_url}, learner{text, translation, state}}]` |
| 11 | бровь вкладки словами | счётчиков нет, клиент считал сам | `summary {total, done, returns}` у слов, фраз и диалога |
| 12 | одна кнопка: Начать / Продолжить / Ещё раз | нет, клиент выбирал по статусу | `window.allowed_action` ∈ `start/continue/again`; «Ещё раз» = «Говорю сам» заново по существующему `GET …/cards`, без записи ответов |
| 13 | картинка у каждой карточки слова | 69 из 232 слов/связок без фото: у них нет `image_prompt`, поиск не зовётся вовсе (дев-база 14.09) | лестница запросов, тон из палитры, догрузка командой, счётчик `image_missing` |

Что станет мёртвым после перехода окна на `window`: `goals_native`, `program[].text_target`,
`program[].text_native`, `program[].cards_total`, `program[].cards_done`, `metrics.first_try_share`,
`metrics.hardest_unit_*` — их читал только старый кабинет. Плита таба читает `day`, `stages[]`,
`metrics.cards_total/minutes_spent`, `program[].unit_kind/source/state`; сессия — `day`, `stages[]`,
`scene` — остаются.
