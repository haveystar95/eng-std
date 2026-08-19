# Device acceptance — `pick_correct` (выбери верное предложение)

Режим приёмки: **наблюдатель**. Claude не правит код/данные/миграции. Ден идёт по пунктам
руками на устройстве; на каждый шаг — вердикт `[OK/FAIL]` + улика той стороны, которой пункт
доказуем (SQL-строка / лог / счётчик). Дефекты фиксируются, не чинятся.

Аккаунт/устройство приёмки: **`haveystar95@gmail.com` / `01KZCV97X3WKTR0B8VPVPXXZA3`**
(подтверждено Деном — тут лежат все три коллекции приёмки). Все SQL-улики ниже фильтруются
по этому `user_id`.

---

## ЭТАП 0 — снимок ДО (снят Claude, до сигналов Дена)

Время снимка: 2026-08-14 ~10:22 UTC (13:22 Kyiv). БД `wordtrainer` (backend2, Postgres :5433).

### 0.1 Кто Ден и какое устройство

Два аккаунта принадлежат Дену — развилка, которую надо снять до ЭТАПА B/C:

| account | user_id | последний `api/v1/sync` | reviews (practice/study/total) |
|---|---|---|---|
| `haveystar95@gmail.com` (git/userEmail, «Денис Солонина») | `01KZCV97X3WKTR0B8VPVPXXZA3` | 2026-08-12 14:45:26 | 1194 / 181 / **1375** |
| `denys.solonyna@yalantis.net` («Denys Solonyna») | `01KZKBAPPPTER74P9VJTE5MG9P` | **2026-08-14 10:14:34** (сегодня) | 90 / 30 / **120** |

Развилка: сегодня синкалось **yalantis**-устройство, но три «новые» коллекции приёмки
(бокс/кофейня/аэропорт как custom) принадлежат **gmail**-аккаунту. Нужно подтверждение Дена,
под каким аккаунтом он идёт по чек-листу — от этого зависят все SQL-улики ниже.

Sync определяется курсором `since` в теле запроса; заголовка device-id нет (`x-device-id`/`x-device`
пусты). Client — `Dart/3.12 (dart:io)` через ngrok `greedily-thermos-finer.ngrok-free.dev`.

### 0.2 Снимок счётчиков ДО

- `learning_mode_settings`: **1 строка**, `user_id = NULL` (дефолтный набор). Персонального
  оверрайда нет ни у кого.
  `modes = [multiple_choice, word_bank, typing, listening, cloze, scramble, dictation, pick_correct]`
  — **pick_correct присутствует, последним**, `updated_at = 2026-08-12 15:19:35+00`.
  ⚠️ То есть по состоянию БД режим уже включён глобально (см. ЭТАП A).
- `reviews` всего по всем: **1996**. По аккаунтам Дена — таблица выше.
- `api_request_logs`: всего **14807** = inbound **12872** + outbound **1935**.
  Outbound по purpose: `generation 6`, `images 101`, `enrichment 623`, `null 1205`.
- `laravel.log`: базовый размер **30204485 байт**; последние записи — тестовый прогон
  2026-08-13 22:20 (Pest), боевого inbound в файле нет.
- Horizon: **running**.

### 0.3 Что оставляет в БД free practice vs учебная сессия (разбор по коду)

Источники: `SubmitReviewsHandler`, `Review`, `AnswerGrader`, `ExerciseMode`, `StudyCardAssembler`,
`ExerciseSelector`, `TermPlayability`.

- **Обе** тренировки пишут строки в `reviews` (append-only) с серверным грейдом и `is_practice`
  флагом. Разница: у free practice `is_practice=true`, и такие строки **не сворачиваются** в
  `user_term_progress` (`foldIntoProgress` их пропускает) — не двигают расписание, не тратят квоту
  новых слов, не резолвят verification `known`-термина. Учебная (`is_practice=false`) — сворачивается.
- Значит **грейд-улики доказуемы базой в обоих режимах** (строка `reviews` пишется всегда).
  Что доказуемо только глазами Дена: визуал карточки (галочка/подчёркивание/волна) — помечено в чек-листе.
- **Офлайн-батч** (`POST` submit): массив `reviews[]`, у каждой client-ULID `id`, `term_id`,
  `exercise_mode`, `response`, `answered_at`, `client_seq`, `is_practice`, `session_id`, `latency_ms`.
  Порядок свёртки — по `client_seq` (не по времени устройства). Офлайн-практика-сессия, которой сервер
  не видел, **усыновляется**: создаётся строка `study_sessions` (`is_practice=true`) через insertOrIgnore
  (повторная заливка того же батча идемпотентна — `insertIgnore` по client-ULID).

### 0.4 Улики, специфичные для pick_correct (из кода — инвариант, не поведение прогона)

- `ExerciseMode::PickCorrect->forgivesTypos() = false` → **ключевая улика пункта 3**: пик на один
  символ мимо → `response ≠ example` → грейд `again` (провал), не `hard`/«Почти». is_correct=false.
- `->maxGrade() = good` (распознавание, никогда `easy`); `->gradesAgainstExample() = true`
  (ответ = закреплённый пример дословно). Верный пик → `good` (или `hard`, если медленно/подсказка),
  is_correct=true — **улика пункта 2**.
- Гейт карточки (`TermPlayability::supports(PickCorrect)` + `StudyCardAssembler::spanDistinct`):
  пример не равен ответу, есть перевод примера, и **≥2 span-различных дистрактора** (уникальный
  `lower(trim(error_span))`). Иначе режим не собирается. Три предложения (ответ + 2 неверных), фото нет.
- В ротации PickCorrect стоит **последним** в обеих лестницах (review и learning) — совпадает с
  «в конце ротации».
- Глобально pick_correct-годных примеров: **331** (≥2 span-различных + перевод).

Годность трёх коллекций приёмки (owner = gmail-аккаунт):

| коллекция | terms | pick_correct-годных |
|---|---|---|
| Ordering Drinks in a Coffee Shop | 12 | 3 |
| В аэропорту и на рейсе | 20 | 7 |
| Boxing Practice Essentials | 15 | 9 |

---

## ЭТАП A — включение
_ждёт сигнала «включай»/«включил сам»_

## ЭТАП B — пересборка и ресинк
_ждёт сигнала «клиент собран, синк прошёл»_

## ЭТАП C — чек-лист на устройстве
_Ден идёт по пунктам_

## ЭТАП D — итог
_вердикты, дефекты списком, дифф счётчиков ДО/ПОСЛЕ_
