# QA-плейбук — eng-std

Сценарии еженедельного прогона: что нажать на симуляторе, что должно появиться на экране, что
должно оказаться в базе и в логах. Написан по фактическим флоу кода, а не по замыслу; каждое
утверждение о БД — готовый SELECT, который можно скопировать.

Прогон идёт **под QA-аккаунтом и только под ним**. Всё разрушительное (машина времени, сброс)
отказывается работать на любом аккаунте без `users.is_qa` — это не соглашение, это код.

> Правило прогона: **баги фиксируются, а не чинятся**. Задача QA-сессии — эталонный отчёт с репро,
> а не патч. Даже однострочный.

---

## Оглавление и бюджет

| # | Сценарий | Длительность | Стоимость | Когда гонять |
|---|---|---|---|---|
| [1](#1-смоук) | **СМОУК** | ~15 мин | $0 | **каждый прогон, обязательно** |
| [2](#2-поиск-и-сохранение) | Поиск и сохранение | ~25 мин | ~$0.005 | каждый прогон |
| [3](#3-жизненный-цикл-слова) | **Жизненный цикл слова** (главный) | ~40 мин | $0 | каждый прогон |
| [4](#4-свободная-тренировка) | Свободная тренировка | ~15 мин | $0 | через прогон |
| [5](#5-генерация-и-обогащение) | Генерация и обогащение | ~25 мин | ~$0.035 | через прогон, или после правок генерации |
| [6](#6-офлайн-и-синк) | Офлайн и синк | ~20 мин | $0 | каждый прогон |
| [7](#7-бд-инварианты) | БД-инварианты (SELECT-чек-лист) | ~10 мин | $0 | **каждый прогон, в конце** |
| [8](#8-логи-и-расходы) | Логи и расходы | ~10 мин | $0 | **каждый прогон, в конце** |
| [9](#9-uiux-осмотр) | UI/UX-осмотр | ~30 мин | $0 | раз в месяц или после редизайна |

Полный прогон ≈ **3 часа, ≈$0.04**. Минимальный (1 + 3 + 7 + 8) ≈ **75 минут, $0**.

Реальные цены по журналам на 2026-08-22 (`avg(cost_usd)` по каждой таблице-реестру):

| Что | Реестр | Средняя цена |
|---|---|---|
| «Собрать карточку» (lookup) | `search_lookups` | **$0.00023** за слово |
| Генерация коллекции | `generation_requests` | **$0.030** за коллекцию (gpt-4o) |
| Обогащение термина (станок) | `term_enrichments` | **$0.00053** за термин |
| «Новый пример» | `example_regenerations` | **$0.00008** |
| Разговор с ИИ (premium) | `practice_dialogs` | **$0.035** за диалог |
| Мгновенный перевод (DeepL) | `instant_translations` | по символам, отдельный счёт |
| Поиск по базе `GET /search`, синк, любая тренировка | — | **бесплатно** |

---

## Подготовка стенда (делается один раз в начале прогона, ~5 мин)

### 1. Бэкенд

```bash
cd /Users/yalantisdenys/eng-std/backend2 && docker compose up -d
```

Должны подняться `wt_app` (:8001), `wt_db` (:5433), `wt_horizon`, `wt_redis`, `wt_ngrok`,
`wt_admin` (:5175). Проверка живости — `curl -s localhost:8001/up`.

В `backend2/.env` должно стоять `DEV_LOGIN_ENABLED=true` и `APP_ENV=local`. После правки `.env` —
`docker compose exec app php artisan config:clear` (иначе конфиг останется закэшированным и dev-вход
будет отвечать 404 при включённом флаге; это не баг, это кэш).

Проверка, что дверь открыта:

```bash
curl -s -o /dev/null -w '%{http_code}\n' -X POST localhost:8001/api/v1/auth/dev \
  -H 'Content-Type: application/json' -d '{"email":"qa@wt.test"}'
```

`200` — дверь открыта. `404` — либо флаг выключен, либо `APP_ENV=production`, либо конфиг закэширован.

### 2. Приложение на симуляторе

Симулятор — **только debug-сборка** (`flutter run --release` на симуляторе отказывается работать;
кнопка «Dev login» есть только в debug — в release её нет по построению).

```bash
xcrun simctl boot 6633B08F-35EA-47D8-99AE-B96791B84058
```

(`iPhone 17 Pro`, iOS 26.5. Полный список — `xcrun simctl list devices available`.)

```bash
cd /Users/yalantisdenys/eng-std/mobile && PATH="/opt/homebrew/bin:$PATH" LANG=en_US.UTF-8 flutter run --debug -d 6633B08F-35EA-47D8-99AE-B96791B84058
```

`--dart-define` не нужен: `AppConfig.apiBaseUrl` по умолчанию указывает на ngrok-домен backend2
(`https://greedily-thermos-finer.ngrok-free.dev`), а ngrok-сервис живёт в том же compose. Если
ngrok отдаёт `ERR_NGROK_334` — его домен захватил чужой процесс: `pkill -f "ngrok http"`, потом
`docker compose up -d ngrok`.

**Язык интерфейса.** Симулятор берёт локаль хоста, и на чистом симуляторе она английская — приложение
откроется на английском («Words for real situations…»), а вся копия в этом плейбуке процитирована по
русской колоде (`app_ru.arb`). Либо переключите Профиль → «Язык интерфейса» → Русский в начале
прогона, либо читайте ожидания как английские эквиваленты. Прогон UI/UX (сценарий 9) требует обе
локали в любом случае.

**Готовая сборка без `flutter run`.** Если нужно только установить и запустить (без attached-процесса):

```bash
cd /Users/yalantisdenys/eng-std/mobile && PATH="/opt/homebrew/bin:$PATH" LANG=en_US.UTF-8 flutter build ios --debug --simulator
```

даёт `build/ios/iphonesimulator/Runner.app`, который ставится на booted-симулятор через
`xcrun simctl install booted <путь>` и запускается `xcrun simctl launch booted com.denis.engstd`.

### 3. QA-аккаунт

На экране входа (кадр 10a) под кнопками Apple и Google есть третья, пунктирная: **`Dev login ·
qa@wt.test`**. Она видна только в debug. Аккаунт создаётся при первом нажатии — сразу с
`is_qa = true` и без `google_id`.

Адрес зашит в код (`kDevLoginEmail`) намеренно: у прогона должен быть один и тот же аккаунт — тот
самый, который машине времени и сбросу разрешено трогать.

### 4. Чистый лист перед прогоном

```bash
docker compose exec app php artisan qa:reset qa@wt.test --force
```

Стирает обучение QA-аккаунта (прогресс, `reviews`, триажи, показы, сессии, дневную статистику,
персональные оверрайды режимов). **Оставляет** аккаунт, профиль, коллекции и термины.

После сброса на устройстве надо **выйти и войти заново** (Профиль → «Выйти» → «Dev login») — иначе
локальное зеркало устройства всё ещё помнит стёртый прогресс, и первые же экраны соврут.

### 5. Куда смотреть, кроме экрана

| Инструмент | Адрес | Зачем |
|---|---|---|
| Админка | http://localhost:5175 | `/ladder` — живая лестница слова; `/users/:id/plan` — план дня; `/logs` — журнал запросов; `/dashboard` — расходы |

Для админки нужен пароль администратора (`admins`: `haveystar95@gmail.com`). Если пароля под рукой
нет, новый администратор заводится командой `docker compose exec app php artisan admin:create`.
**Без входа в панель шаг 3.8 (сверка экрана `/ladder` с SQL) выполнить нельзя** — это единственная
часть плейбука, которая требует учётной записи, отличной от QA-аккаунта.

| БД | TablePlus → `localhost:5433`, `wordtrainer`/`wordtrainer`/`secret` | все SELECT ниже |
| psql | `docker compose exec db psql -U wordtrainer -d wordtrainer` | то же из терминала |
| Логи приложения | `docker compose logs -f app` | 500-ки, стектрейсы |
| Логи очереди | `docker compose logs -f horizon` | обогащение, генерация, картинки |

Во всех SELECT ниже `:u` — id QA-юзера. Взять один раз и подставлять:

```sql
SELECT id FROM users WHERE email = 'qa@wt.test';
```

Дальше в примерах он подставлен как `(SELECT id FROM users WHERE email='qa@wt.test')` — так запросы
копируются без правки.

### 6. Отсечка времени

Перед первым шагом прогона запомните момент старта — по нему потом фильтруются логи и расходы:

```sql
SELECT now() AS run_started_at;
```

---

## 1. СМОУК

**Обязателен каждый прогон. ~15 минут, $0.** Если смоук красный — остальные сценарии не гоняются,
пока он не позеленеет: они все стоят на нём.

### Предусловия

Стенд поднят, `qa:reset` сделан, устройство перезалогинено, отсечка времени взята.

### 1.1 Вход

**Шаги.** Запустить приложение → экран «Слова» с двумя кнопками входа → нажать
`Dev login · qa@wt.test`.

**На экране.** Кратковременный спиннер вместо кнопок, затем — либо онбординг (если аккаунт свежий:
`profiles.onboarded_at IS NULL`), либо сразу главная. Онбординг пройти до конца.

**В БД.**

```sql
-- ровно один QA-аккаунт, помечен, без google_id
SELECT id, name, email, google_id, is_qa FROM users WHERE email = 'qa@wt.test';
-- ожидание: одна строка, is_qa = t, google_id IS NULL

-- ровно один профиль, с часовым поясом устройства
SELECT user_id, native_language, target_language, cefr_level, daily_goal, tier, timezone, onboarded_at
FROM profiles WHERE user_id = (SELECT id FROM users WHERE email='qa@wt.test');
-- ожидание: одна строка; timezone НЕ NULL (симулятор отдаёт зону хоста)

-- на каждый вход выдаётся свой токен; старые не отзываются
SELECT count(*) FROM personal_access_tokens
WHERE tokenable_id = (SELECT id FROM users WHERE email='qa@wt.test');
-- ожидание: ≥ 1 и растёт на 1 за вход
```

**В логах.** `POST /api/v1/auth/dev` → 200 в `api_request_logs`:

```sql
SELECT occurred_at, method, path, status, duration_ms FROM api_request_logs
WHERE direction = 'inbound' AND path LIKE '%/auth/dev%' ORDER BY occurred_at DESC LIMIT 3;
```

### 1.2 Главный экран

**Шаги.** Дождаться загрузки главной (таб «Главная»).

**На экране.** Одно из двух — и оба нормальны:
- если у аккаунта пусто: карточка «Опиши тему — соберём коллекцию» и пустой список «Мои коллекции»;
- если слова уже есть: «Дневная цель» с прогрессом `N / M слов`, кнопка **«Учить N слов»** и/или
  **«Повторить N слов»** и/или **«Разобрать N слов»**, «Слово дня», «Мои коллекции», стрик.

Чего быть не должно: красного экрана Flutter, бесконечного спиннера, полоски синка, которая не
исчезает (полоска под статус-баром живёт только пока синк в полёте).

**В БД.** Главная читает **из локальной базы устройства**, а не из сети, — но её цифры должны
сходиться с сервером:

```sql
-- сколько слов в пуле (enrolled_at IS NOT NULL) и сколько из них к повторению сейчас
SELECT
  count(*) FILTER (WHERE enrolled_at IS NOT NULL)                                   AS in_pool,
  count(*) FILTER (WHERE enrolled_at IS NOT NULL AND acquisition <> 'graduated')     AS on_ladder,
  count(*) FILTER (WHERE enrolled_at IS NOT NULL AND due_at IS NOT NULL AND due_at <= now()) AS due_now
FROM user_term_progress WHERE user_id = (SELECT id FROM users WHERE email='qa@wt.test');
```

Цифра на кнопке «Повторить» должна совпасть с `due_now` (± слова, дошедшие в этот момент).

**В логах.** `GET /api/v1/sync`, `GET /api/v1/stats`, `GET /api/v1/study/progress` — все 200.

> **`mastered` может быть БОЛЬШЕ `learned` — это не сбой счётчика.** `learned` — это ровно
> `state = 'review'`; `mastered` (`Mastery::isMastered`) — это `state='known'` **или**
> (`review` и `interval_days ≥ 21`). Слово, отмеченное «знаю» на триаже, попадает во второе и не
> попадает в первое, поэтому «Выучено всего: 3 · освоено 4» — арифметически корректное состояние.
> Наблюдалось в самопрогоне 2026-08-22. Стоит держать в голове, читая экран «Прогресс»: две цифры
> считают разное, и на UI это не подписано.

### 1.3 Одна сессия тренировки

**Шаги.** Нажать «Учить N слов» (или «Повторить N слов») → пройти сессию до экрана «Сессия
закончена» → «Готово».

**На экране.** Карточки по одной; шапка называет фазу («Знакомство» / «Узнавание» / «Сборка» /
«Повторение»); после ответа — «Верно» / «Почти:» / «Не то — правильная форма ниже» и строка
«Увидишь снова завтра / через N дней». В конце — итог: «Повторено», «Новых», «Ошибки», дневная цель,
стрик.

**В БД.**

```sql
-- сессия закрыта: ended_at проставлен, состав записан
SELECT id, started_at, ended_at, is_practice, jsonb_array_length(composition) AS cards, stats
FROM study_sessions WHERE user_id = (SELECT id FROM users WHERE email='qa@wt.test')
ORDER BY started_at DESC LIMIT 1;
-- ожидание: is_practice = f, ended_at НЕ NULL

-- ответы легли в журнал, каждый со своей ступенью и режимом
SELECT answered_at, exercise_mode, grade, is_correct, is_practice, ladder_step, client_seq
FROM reviews WHERE user_id = (SELECT id FROM users WHERE email='qa@wt.test')
ORDER BY client_seq DESC LIMIT 20;
-- ожидание: is_practice = f; client_seq строго растёт; ladder_step в 1..5 или NULL

-- дневная статистика обновилась
SELECT * FROM daily_user_stats
WHERE user_id = (SELECT id FROM users WHERE email='qa@wt.test') ORDER BY date DESC LIMIT 3;
-- ожидание: reviews_count = числу неучебно-практических ответов за день
```

**В логах.** `POST /api/v1/study/sessions` → 200, `POST /api/v1/reviews/batch` → 200 с
`accepted = <число ответов>`, `duplicates = 0`, `unknown = 0`,
`POST /api/v1/study/sessions/{id}/complete` → 200.

`unknown > 0` — **всегда находка**: сервер отбросил ответ (неизвестный термин, чужая сессия,
ответ не из состава сессии, устаревший ответ по лестнице). Ищите причину, не списывайте.

### 1.4 Поиск слова из базы

**Шаги.** Таб «Поиск» → ввести слово, которое точно есть в базе → дождаться списка.

Слово надо выбрать заранее и проверить, а не угадать: `apple`, например, в базе **нет**, и поиск по
нему честно возвращает `{"data":[]}` — это пустой экран, а не дефект. Годное слово:

```sql
SELECT text FROM terms WHERE deleted_at IS NULL AND lang='en'
  AND id IN (SELECT term_id FROM collection_items WHERE deleted_at IS NULL) LIMIT 10;
```

**На экране.** Список результатов с транскрипцией и переводом, под полем — серая строка мгновенного
перевода. Кнопка «Собрать карточку» **не нажимается** (это платно — отдельный сценарий).

**В БД.** Ничего не пишется: `GET /search` — чистое чтение.

```sql
-- контрольная проверка: за смоук ни одного платного lookup
SELECT count(*) FROM search_lookups
WHERE user_id = (SELECT id FROM users WHERE email='qa@wt.test') AND created_at >= :run_started_at;
-- ожидание: 0
```

**В логах.** `GET /api/v1/search?q=…` → 200 и `GET /api/v1/search/instant?q=…` → 200.
`instant` **всегда** 200, у него нет пути ошибки; исходящего вызова к DeepL может и не быть (кэш
или каталог), это норма.

### 1.5 Синхронизация

**Шаги.** На главной потянуть экран вниз (pull-to-refresh) — это `resync()`: сбрасывает курсор и
тянет полный снимок.

**На экране.** Полоска синка под статус-баром появляется и исчезает. Ничего не пропадает и не
дублируется.

**В логах.** Серия `GET /api/v1/sync` (первый — без `since`, значит полный снимок; дальше по
`cursor`, пока `has_more = false`).

### 1.6 Ошибок за прогон нет

```sql
-- любые 5xx и 4xx за прогон
SELECT occurred_at, method, path, status, error FROM api_request_logs
WHERE direction = 'inbound' AND status >= 400 AND occurred_at >= :run_started_at
ORDER BY occurred_at;
-- ожидание: пусто. 401 до входа — норма; 422 — всегда находка.

-- сбойные задания очереди
SELECT count(*) FROM failed_jobs WHERE failed_at >= :run_started_at;
-- ожидание: 0
```

```bash
docker compose logs --since 30m app | grep -iE "ERROR|Exception|stack trace" | head -30
docker compose logs --since 30m horizon | grep -iE "ERROR|failed" | head -30
```

**Вердикт смоука:** зелёный только если все шесть пунктов зелёные.

---

## 2. Поиск и сохранение

**~25 минут, ≈$0.005** (до ~20 сборок карточки по $0.00023). Дневной потолок сборок —
`SEARCH_LOOKUP_DAILY_CAP`, по умолчанию **30 на аккаунт**.

### Предусловия

Смоук зелёный. Знать заранее: одно слово, которого в базе точно нет (проверить SELECT ниже), и одно,
которое точно есть.

```sql
SELECT id, text, lang, type FROM terms WHERE normalized_text = lower('<слово>') AND deleted_at IS NULL;
```

### 2.1 Прямой поиск (en → родной)

**Шаги.** Таб «Поиск» → ввести `apple`.

**На экране.** Список карточек: слово, транскрипция, перевод, пример. Под полем — серая строка
мгновенного перевода. У слов, уже лежащих в папках, видна пометка папки.

**В БД.** Ноль записей. `GET /search` читает `terms` + `term_translations` + `term_examples`.

**В логах.** `GET /api/v1/search` 200 (throttle 240/мин — поле дебаунсится, вызовов будет много,
это норма).

### 2.2 Обратный поиск (родной → en)

**Шаги.** Ввести `яблоко`.

**На экране.** Тот же список; заголовком карточки должно быть **английское** слово, а не русское.
Мгновенная строка тоже должна отдать английский эквивалент.

**Это место, где ловятся дефекты направления** — сервер сам решает, какая из двух строк «изучаемая»
(`reversed` в ответе `/search/instant`), и решает не всегда.

**В логах.**

```sql
SELECT occurred_at, path, status,
       response_body->'data'->>'translation' AS translation,
       response_body->'data'->>'source'      AS src,
       response_body->'data'->>'reversed'    AS reversed
FROM api_request_logs
WHERE direction='inbound' AND path LIKE '%/search/instant%' AND occurred_at >= :run_started_at
ORDER BY occurred_at DESC LIMIT 10;
```

`source` говорит, какая ступень ответила (каталог / кэш / вендор) — по нему видно, платил ли запрос.

### 2.3 Фразы

**Шаги.** Ввести короткую фразу (`take a look`), затем заведомо длинную (предложение на 15+ слов).

**На экране.** Короткая — обычный результат. Длинная — строка **«Поиск — для слов и коротких
фраз»** (`query_too_long`), а не ошибка и не пустой экран.

### 2.4 «Собрать карточку» (платно)

**Шаги.** Найти слово, которого в базе нет → нажать **«Собрать карточку»** («Значение, пример и фото.
Повторно — бесплатно»).

**На экране.** Прогресс по этапам: «перевод» → «значение» → «пример» → «фото», с примечанием «Пара
секунд. Можно закрыть — карточка появится в поиске». Затем — готовая карточка. Счётчик
«N из 30 на сегодня».

**В БД.**

```sql
-- запись в реестре расходов: ровно одна на слово
SELECT id, normalized_query, lang, native_lang, model, prompt_version,
       tokens_in, tokens_out, cost_usd, created_at
FROM search_lookups WHERE user_id = (SELECT id FROM users WHERE email='qa@wt.test')
ORDER BY created_at DESC LIMIT 3;
-- ожидание: cost_usd ≈ 0.0002…0.0004, model = gpt-4o-mini
```

**В логах.** Исходящий вызов с `purpose = 'search_lookup'`:

```sql
SELECT occurred_at, host, path, status, duration_ms FROM api_request_logs
WHERE direction='outbound' AND purpose='search_lookup' AND occurred_at >= :run_started_at
ORDER BY occurred_at DESC;
```

### 2.5 Кэш: повтор бесплатен

**Шаги.** Тот же запрос ещё раз (можно другим написанием того же слова — ключ нормализованный).

**На экране.** Карточка появляется мгновенно, без этапов сборки.

**В БД.** Строк в `search_lookups` **не прибавилось** (уникальный индекс
`(normalized_query, lang, native_lang)`), исходящего вызова с `purpose='search_lookup'` не
появилось. `fresh` в ответе — `false`.

```sql
SELECT count(*) FROM search_lookups WHERE normalized_query = lower('<слово>');
-- ожидание: 1, сколько бы раз ни искали
```

### 2.6 Сохранение: папки и переносы

**Шаги.**
1. На карточке нажать **«+ Сохранённые»** — слово уходит в дефолтную папку.
2. На другой карточке — **«в коллекцию…»** → выбрать свою коллекцию (или «Новая коллекция»).
3. Открыть коллекцию → на слове меню → **«Перенести в…»** → другая коллекция.

**На экране.** «Сохранено в «X» — слово учится» / «В «X»». После переноса — «Перенесено в «X»».

**В БД.**

```sql
-- слово попало в папку И в пул (сохранение из поиска = решение учить)
SELECT c.title, c.is_default, ci.deleted_at, p.enrolled_at, p.acquisition, p.learning_step
FROM collection_items ci
JOIN collections c ON c.id = ci.collection_id
LEFT JOIN user_term_progress p
       ON p.term_id = ci.term_id AND p.user_id = (SELECT id FROM users WHERE email='qa@wt.test')
WHERE ci.term_id = '<term_id>';
-- ожидание: enrolled_at НЕ NULL (сохранение из поиска зачисляет в пул);
--           после переноса — ровно одна живая строка (deleted_at IS NULL)
```

**Ключевой инвариант переноса:** прогресс ключуется парой `(user_id, term_id)` и **не знает о
коллекции**. Перенос слова между папками не должен менять ни `acquisition`, ни `learning_step`, ни
`due_at`, ни `successful_reviews`. Снять значения до и после — они обязаны совпасть.

### 2.7 Лимит дня

**Шаги.** Довести число сборок до потолка (30) — либо гонять, либо временно опустить
`SEARCH_LOOKUP_DAILY_CAP` в `.env` до 2 и сделать `config:clear` (быстрее и дешевле; вернуть
обратно после сценария).

**На экране.** Карточка **«Сборки с моделью вернутся в полночь»** и рядом — бесплатные результаты
из базы. Экрана ошибки быть не должно: потолок отдаётся как `200 + limit_reached`, а не как 429.

**В БД.** Новых строк в `search_lookups` нет, исходящего вызова нет.

---

## 3. Жизненный цикл слова

**Главный сценарий. ~40 минут, $0.** Ведёт одно слово от «никогда не видел» до диктанта, двигая
время между повторениями.

### Модель, которую этот сценарий проверяет

У пары `(юзер, слово)` **две независимые оси**, и путать их — самая частая ошибка чтения:

| Ось | Колонки | Что говорит |
|---|---|---|
| **Лестница освоения** | `acquisition`, `learning_step`, `successful_reviews` | ЧЕМ слово приходит: каким тренажёром |
| **Расписание (SM-2)** | `state`, `due_at`, `interval_days`, `ease_factor`, `reps`, `lapses` | КОГДА слово придёт снова |

Ступени лестницы (`LearningLadder`):

| Ступень | Что это | Как определяется |
|---|---|---|
| 0 · знакомство | показали, не спросили | `acquisition = 'new'` |
| 1 · узнавание вперёд | слово → выбрать перевод | `acquisition='learning'`, `learning_step = 1` |
| 2 · узнавание назад | перевод → выбрать слово | `acquisition='learning'`, `learning_step = 2` |
| 3 · сборка | word_bank, cloze, scramble, pick_correct, description_match, speaking | `acquisition='graduated'`, `successful_reviews < 4` |
| 4 · написание | + typing, listening | `graduated`, `successful_reviews >= 4` |
| 5 · диктант | + dictation | `graduated`, `successful_reviews >= 6` |

Три правила, которые надо держать в голове при сверке:

1. **`learning_step` двигается только на успехе.** Провал ступени узнавания оставляет пару на месте
   (`repeatLadderStep` не меняет ничего) — но ответ всё равно ложится в `reviews`.
2. **Ступени узнавания не планируют ничего.** Пока `acquisition <> 'graduated'`, планировщик пару не
   видел: `due_at` NULL, `reps = 0`, `interval_days = 0`. Выпуск с лестницы интервала не выдумывает.
3. **`successful_reviews` растёт только на верном непрактическом ответе УЖЕ ВЫПУЩЕННОЙ пары.**
   `hard` считается, `again` — нет и **не обнуляет**. Ступень, однажды заработанная, не отбирается.

> **Про «демоцию» — важная поправка.** Ошибка на выпущенном слове **не понижает ступень лестницы**:
> `successful_reviews` не уменьшается, и `pick_correct`/`typing`/`dictation` остаются допущенными.
> Понижается **расписание**: `state` `review → relearning`, `lapses + 1`, `ease_factor − 0.20`,
> `interval_days → 0` (слово вернётся в этой же сессии). Проверять надо именно это; если увидите
> падение `successful_reviews` — это баг, а не ожидаемое поведение.

### Предусловия

- `qa:reset qa@wt.test --force`, перезаход на устройстве.
- Все тренажёры включены глобально. Проверить:
  ```sql
  SELECT mode, enabled, min_acquisition, min_learning_step, min_successful_reviews
  FROM learning_mode_settings WHERE user_id IS NULL ORDER BY position;
  ```
  Ожидание: 11 строк, все `enabled = t`; `intro` — `min_acquisition = 'new'`;
  `pick_correct` — `graduated` без порогов; `typing`/`listening` — `min_successful_reviews = 4`;
  `dictation` — `6`.
- Персональных оверрайдов у QA-юзера нет (`qa:reset` их снимает):
  ```sql
  SELECT count(*) FROM learning_mode_settings
  WHERE user_id = (SELECT id FROM users WHERE email='qa@wt.test');
  -- ожидание: 0
  ```
- Выбрать **одно** подопытное слово и записать его `term_id`. Дальше он в запросах как `'<T>'`.

### Сквозной запрос сверки

Копируется на каждом шаге — им и ведётся сценарий:

```sql
SELECT
  t.text,
  p.acquisition, p.learning_step, p.successful_reviews,
  p.state, p.due_at, p.interval_days, p.ease_factor, p.reps, p.lapses,
  p.enrolled_at, p.last_reviewed_at,
  CASE
    WHEN p.state = 'known'              THEN NULL
    WHEN p.acquisition = 'new'          THEN 0
    WHEN p.acquisition = 'learning'     THEN greatest(1, least(2, p.learning_step))
    WHEN p.successful_reviews >= 6      THEN 5
    WHEN p.successful_reviews >= 4      THEN 4
    ELSE 3
  END AS ladder_step
FROM user_term_progress p JOIN terms t ON t.id = p.term_id
WHERE p.user_id = (SELECT id FROM users WHERE email='qa@wt.test') AND p.term_id = '<T>';
```

Колонка `ladder_step` — дословный перенос `LearningLadder::stepFor()` в SQL. Она обязана совпадать
с тем, что показывает `/ladder` в админке, и с тем, каким тренажёром слово реально пришло.

### Шаг 3.1 — новое слово

**Шаги.** Добавить слово в свою коллекцию (Коллекции → коллекция → «Добавить слово»), **не** нажимая
«Учить это слово».

**На экране.** Слово в списке коллекции. На главной число «Учить N» **не выросло**: каталог — не
очередь.

**В БД.** Строки прогресса может не быть вовсе, а если есть — `enrolled_at IS NULL`.

```sql
SELECT count(*) FROM user_term_progress
WHERE user_id=(SELECT id FROM users WHERE email='qa@wt.test') AND term_id='<T>' AND enrolled_at IS NOT NULL;
-- ожидание: 0
```

**Находка, если** число на «Учить» выросло — значит добавление слова зачисляет в пул, а не должно.

**Не находка: слово, добавленное существующим термином, не стоит денег.** Дедупликация ловит его по
`(lang, normalized_text, pos)`, станок обогащения не запускается, `term_enrichments` не растёт.
Проверено самопрогоном 2026-08-22: пять слов добавлены, расход $0.

**Не находка: часть слов не приходит в ПЕРВОЙ сессии.** `SessionLayout` откладывает термин ЦЕЛИКОМ,
если его цепочка «знакомство → узнавание вперёд → узнавание назад» не помещается до конца сессии:
слово, с которым «познакомились» и не спросили в тот же заход, хуже, чем слово, не показанное вовсе.
В самопрогоне из пяти слов первая сессия взяла три, `prescription` пришёл во вторую. Это замысел, а
не потеря.

### Шаг 3.2 — триаж (разбор свайпами)

**Шаги.** На коллекции — баннер «Разбери коллекцию» → «Начать». Свайпнуть подопытное слово
**«Не знаю»** (влево).

**На экране.** Карточка со словом, счётчик «N из M», подсказка «Свайпай или жми кнопки · тап —
перевернуть». В конце — итог «Пачка разобрана» с тремя счётчиками.

**В БД.**

```sql
-- вердикт лёг в append-only журнал
SELECT id, verdict, decided_at, client_seq, collection_id, revealed
FROM term_triages WHERE user_id=(SELECT id FROM users WHERE email='qa@wt.test') AND term_id='<T>'
ORDER BY client_seq DESC;
-- ожидание: одна строка, verdict='unknown'
```

Сквозной запрос после «Не знаю»: `acquisition='new'`, `learning_step=0`, `enrolled_at` **НЕ NULL**,
`state='new'`, `due_at` NULL, `ladder_step = 0`.

Для сравнения — что делают два других свайпа:
- **«Не уверен»** → `acquisition='learning'`, `learning_step=1`, `enrolled_at` НЕ NULL
  (знакомство пропускается: слово уже видели во время свайпа);
- **«Знаю»** → `state='known'`, `acquisition='graduated'`, `enrolled_at` **NULL**, `due_at` = дата
  проверки заявления (≈ +90 дней). `ladder_step` = NULL: у `known` лестницы нет, его проверка —
  всегда typing.

**Находка, если** «Знаю» зачислило слово в пул: это единственный вердикт, который говорит обратное.

> **Что удивляет при первой сверке (и не является багом).** Слово со `state='known'` **приходит в
> учебную сессию**, хотя оно НЕ в пуле: проверка заявления едет на `due_at`, а не на членстве в
> пуле, и когда дата подходит, карточка (typing, `ladder_step: null`) попадает в сессию наравне с
> повторениями. Верный ответ на неё увеличивает `successful_reviews` — это осознанно: непрактический
> верный ретривал выпущенной пары и есть то, что этот счётчик считает. Подтверждено самопрогоном:
> после машины времени `chat` пришёл дважды карточкой `typing@None` и набрал `successful_reviews = 2`,
> оставшись `known`.

### Шаг 3.3 — знакомство (ступень 0 → 1)

**Шаги.** Главная → «Учить N слов» → дойти до карточки подопытного слова.

**На экране.** Шапка **«Знакомство»**, бейдж «новое слово», слово, перевод, «также:» со вторыми
значениями, кнопка **«Понятно»**. Ответа не спрашивают.

**В БД.**

```sql
-- показ записан ровно один раз на пару
SELECT user_id, term_id, session_id, shown_at FROM term_exposures
WHERE user_id=(SELECT id FROM users WHERE email='qa@wt.test') AND term_id='<T>';
-- ожидание: одна строка

-- в reviews по знакомству НИЧЕГО не появилось
SELECT count(*) FROM reviews
WHERE user_id=(SELECT id FROM users WHERE email='qa@wt.test') AND term_id='<T>';
-- ожидание: 0
```

Сквозной запрос: `acquisition='learning'`, `learning_step=1`, `ladder_step = 1`, `due_at` всё ещё
NULL, `reps=0`.

**Находка, если** знакомство создало строку в `reviews`: карточка без ответа не ретривал, и сервер
такой ответ обязан отбросить (`unknown++` в ответе `/reviews/batch`).

### Шаг 3.4 — узнавание вперёд (ступень 1 → 2)

**Шаги.** В той же сессии слово приходит снова. Шапка **«Узнавание»**, инструкция «выбери перевод»,
подсказка «вы только что познакомились с этим словом». Ответить **верно**.

**На экране.** «Верно». Строки «Увидишь снова…» на ступенях узнавания быть **не должно** — интервала
ещё нет.

**В БД.**

```sql
SELECT answered_at, exercise_mode, grade, is_correct, ladder_step, is_practice, client_seq, response
FROM reviews WHERE user_id=(SELECT id FROM users WHERE email='qa@wt.test') AND term_id='<T>'
ORDER BY client_seq;
-- ожидание: exercise_mode='multiple_choice', ladder_step=1, grade IN ('good','hard'),
--           response = ULID выбранного термина (не текст перевода!)
```

Это важная деталь: карточка узнавания-вперёд оценивается **по идентичности**, а не по тексту —
клиент шлёт id нажатого варианта. В ключе ответа перевод не участвует никогда.

Сквозной запрос: `learning_step=2`, `ladder_step = 2`, `due_at` NULL, `reps=0`,
`successful_reviews=0` (на лестнице счётчик не растёт — пара ещё не выпущена).

**Проверка провала (можно на втором слове).** Ответить **неверно** на ступени узнавания:
`learning_step` **не меняется**, строка в `reviews` появляется с `grade='again'`, карточка
возвращается в хвост той же сессии.

### Шаг 3.5 — узнавание назад → выпуск (ступень 2 → 3)

**Шаги.** Ответить верно на карточке «перевод → слово».

**В БД.** Сквозной запрос: `acquisition='graduated'`, `learning_step` сброшен в 0,
`successful_reviews=0`, `ladder_step = 3`. Расписание **всё ещё пустое**: `state='new'`,
`due_at` NULL, `reps=0`.

Выпуск намеренно не выдумывает интервал: пара просто становится планируемой, и первый настоящий
ответ входит в SM-2 из `new`.

### Шаг 3.6 — первое настоящее повторение (SM-2 стартует)

**Шаги.** Слово приходит тренажёром **сборки** — `word_bank` / `cloze` / `scramble` /
`pick_correct` / `description_match`. Ответить верно.

**На экране.** Появляется строка **«Увидишь снова завтра»** — первая, потому что интервал появился
только сейчас.

**В БД.** Сквозной запрос: `state='learning'`, `interval_days=1`, `due_at` = **00:00 следующего дня
в часовом поясе профиля** (не «через 24 часа»: дневные интервалы прижимаются к началу календарного
дня), `reps=1`, `successful_reviews=1`, `ladder_step` всё ещё 3.

```sql
-- убедиться, что due_at действительно в полночь пользовательской зоны
SELECT due_at,
       due_at AT TIME ZONE (SELECT timezone FROM profiles
                            WHERE user_id=(SELECT id FROM users WHERE email='qa@wt.test')) AS local_due
FROM user_term_progress
WHERE user_id=(SELECT id FROM users WHERE email='qa@wt.test') AND term_id='<T>';
-- ожидание: local_due = 00:00:00
```

> ### ⚠️ ЭТА ПРОВЕРКА СЕЙЧАС КРАСНАЯ — QA-BUG-1, открыт 2026-08-22, НЕ ЧИНИЛСЯ
>
> Фактически `local_due` = **03:00:00** для `Europe/Kyiv` (+3), то есть `due_at` сохраняется как
> `00:00` **UTC**, а не как полночь пользователя. Домен считает правильно
> (`Sm2Scheduler` возвращает `2026-08-23T00:00:00+03:00` = `21:00Z`), теряется значение на ЗАПИСИ:
> `TermProgressMapper` отдаёт `DateTimeImmutable` прямо в query builder, грамматика Postgres
> форматирует его как `Y-m-d H:i:s` — смещение отбрасывается, — и Postgres читает наивный литерал в
> зоне сессии (`Etc/UTC`). Проверено напрямую: вставка `2026-08-23T00:00:00+03:00` во временную
> `timestamptz` возвращает `2026-08-23 00:00:00+00`.
>
> **Не пере-открывайте как новую находку.** Пока баг открыт, ожидание этой проверки — `local_due`
> равен UTC-смещению пользователя, а не `00:00`. Когда починят, ожидание вернётся к `00:00:00`, и
> эту врезку надо убрать.
>
> Почему 1387 зелёных тестов этого не видят: F19 покрыт **юнит**-тестами
> (`tests/Unit/Learning/Sm2SchedulerTest.php`), которые проверяют сущность в памяти и никогда не
> ходят через БД. Это ровно тот класс дефектов, ради которого живой прогон и существует.

### Шаг 3.7 — машина времени, и так до ступени 4

**Шаги.** Каждый раз, когда слово «уехало» в будущее:

```bash
docker compose exec app php artisan qa:time-travel qa@wt.test --days=+1 --force
```

Команда печатает, сколько строк в каждой таблице сдвигает, и требует подтверждения (или `--force`).
Сдвигаются **все** временные поля обучения: прогресс, `reviews.answered_at`, триажи, показы,
сессии и `daily_user_stats.date`. Так «прошедший день» согласован везде — и в расписании, и в
дневном лимите новых слов, и в стрике.

**После каждого сдвига на устройстве обязателен pull-to-refresh** (полный ресинк): сдвинутые строки
**старше** курсора, который держит устройство, и дельта-синк их не увидит. Это не баг машины
времени — это цена сдвига «в прошлое».

Повторить **4 раза** (после каждого — ответить верно):

| После | `successful_reviews` | `ladder_step` | Что открылось |
|---|---|---|---|
| 1-го верного (шаг 3.6) | 1 | 3 | сборка |
| 2-го | 2 | 3 | сборка |
| 3-го | 3 | 3 | сборка |
| **4-го** | **4** | **4** | **+ `typing`, `listening`** |
| 5-го | 5 | 4 | — |
| **6-го** | **6** | **5** | **+ `dictation`** |

Между шагами интервал растёт: `learning + good → review, interval 4` → далее
`interval × ease_factor` (старт 2.50), с фаззингом ±. Значит после выхода в `review` сдвигать надо
не по одному дню, а на `interval_days`:

```sql
SELECT interval_days FROM user_term_progress
WHERE user_id=(SELECT id FROM users WHERE email='qa@wt.test') AND term_id='<T>';
```

и `--days=+<это число>`.

**На экране** после 4-го успеха слово должно хотя бы раз прийти карточкой **«напиши по-английски»**
(typing) или «прослушай и напиши» (listening), после 6-го — **«прослушай и запиши предложение»**
(dictation). Тренажёр выбирается ротацией, поэтому «хотя бы раз за две-три сессии», а не «строго
следующей карточкой».

Наблюдённая последовательность самопрогона 2026-08-22 (режим@ступень, как их выдал сервер) —
годится как эталон того, что «нормально»:

```
ступень 3: word_bank@3, cloze@3, pick_correct@3, speaking@3
ступень 4: pick_correct@4, typing@4, speaking@4
ступень 5: speaking@5   (+ dictation допущен)
вне лестницы: typing@None — это проверка «знаю» у chat, см. врезку в 3.2
```

Обратите внимание: `pick_correct` и `speaking` приходят **уже на ступени 3**, вместе с остальной
сборкой. Это и есть их правило (`min_acquisition = graduated`, порогов нет), а не признак того, что
лестница поехала.

**Что появление `pick_correct` значит на самом деле.** `pick_correct` («выбери верное предложение»)
допущен **с момента выпуска**, вместе с остальной сборкой, а не на 4-й ступени. Его реальное
условие — не ступень, а **контент**: у термина должны быть примеры-дистракторы.

```sql
-- есть ли у слова из чего построить pick_correct
SELECT count(*) FROM example_distractors ed
JOIN term_examples te ON te.id = ed.example_id WHERE te.term_id = '<T>';
-- 0 → карточка не построится, и это не дефект тренажёра, а пробел контента
```

### Шаг 3.8 — сверка с админкой на каждом шаге

Открыть http://localhost:5175 → **`/ladder`** → выбрать QA-юзера.

Экран показывает распределение пар по ступеням и ленту событий. На каждом шаге сценария:
- ступень подопытного слова на экране = `ladder_step` из сквозного запроса;
- в ленте событий — то же событие, что только что легло в `reviews`;
- `/users/:id/plan` показывает план дня: какие слова придут и каким тренажёром. После
  `qa:time-travel` план обязан перестроиться.

Расхождение экрана и SQL — **всегда находка**: это значит, что панель и домен считают ступень
по-разному.

### Шаг 3.9 — ошибка на выпущенном слове

**Шаги.** Дойти до слова на ступени 4–5 и ответить **неверно** (в typing — набрать заведомо не то).

**На экране.** «Не то — правильная форма ниже», и слово возвращается **в этой же сессии** (интервал
обнулён).

**В БД.** Сквозной запрос до и после. Ожидание:

| Поле | До | После |
|---|---|---|
| `state` | `review` | **`relearning`** |
| `lapses` | N | **N + 1** |
| `ease_factor` | E | **E − 0.20** (не ниже 1.30) |
| `interval_days` | > 0 | **0** |
| `due_at` | завтра+ | **≈ сейчас** |
| `successful_reviews` | S | **S, без изменений** |
| `acquisition` | `graduated` | **`graduated`** |
| `ladder_step` | 4 или 5 | **тот же** |

```sql
SELECT grade, is_correct, exercise_mode, ladder_step, response, answered_at
FROM reviews WHERE user_id=(SELECT id FROM users WHERE email='qa@wt.test') AND term_id='<T>'
ORDER BY client_seq DESC LIMIT 1;
-- ожидание: grade='again', is_correct=false
```

Потом ответить верно: `relearning + good → review, interval_days = 1` (сокращённый выпуск),
`successful_reviews + 1`.

**Подтверждено самопрогоном 2026-08-22** на слове `clarify` (typing@5, ответ «zzzz nonsense zzzz»):
`review → relearning`, `lapses 0 → 1`, `ease 2.50 → 2.30`, `interval 350 → 0`,
`successful_reviews 7 → 7`, `acquisition` остался `graduated`. Ступень лестницы не изменилась —
именно так, как написано выше, и НЕ так, как подсказывает интуиция «ошибся — откатился».

---

## 4. Свободная тренировка

**~15 минут, $0.** Проверяет главное свойство свободной тренировки: она **считается активностью**,
но **ничего не двигает** — ни расписания, ни пула, ни лестницы.

### Предусловия

Есть коллекция с несколькими словами, из которых **часть не в пуле** (не разобрана свайпами и без
«Учить это слово»).

### 4.1 По системной коллекции

**Шаги.** Коллекции → «Готовые» → добавить набор в «Мои» → открыть → **«Свободная тренировка»**
(«Ничего не горит — можно просто позаниматься»).

**На экране.** Баннер **«Свободная тренировка — прогресс не меняется»**, шапка фазы «Свободная
тренировка». В итоге — «Пройдено» вместо «Повторено/Новых/Ошибки» и кнопка «Ещё раз».

### 4.2 По своей папке

То же самое из своей коллекции.

### 4.3 Незачёт прогресса — главная проверка

Снять снимок ДО и ПОСЛЕ по всем словам коллекции:

```sql
SELECT term_id, acquisition, learning_step, successful_reviews, state, due_at, interval_days,
       reps, lapses, enrolled_at
FROM user_term_progress
WHERE user_id=(SELECT id FROM users WHERE email='qa@wt.test')
  AND term_id IN (SELECT term_id FROM collection_items WHERE collection_id='<C>' AND deleted_at IS NULL)
ORDER BY term_id;
```

**Ожидание: обе выборки идентичны до последнего поля.** Свободная тренировка не пишет в прогресс
вообще.

Что при этом всё-таки появляется:

```sql
-- ответы в журнале — с флагом практики
SELECT count(*) FILTER (WHERE is_practice) AS practice,
       count(*) FILTER (WHERE NOT is_practice) AS study
FROM reviews WHERE user_id=(SELECT id FROM users WHERE email='qa@wt.test') AND answered_at >= :run_started_at;
-- ожидание: practice > 0, study = 0 (если в этом окне была только свободная тренировка)

-- сессия помечена практикой
SELECT id, is_practice, ended_at FROM study_sessions
WHERE user_id=(SELECT id FROM users WHERE email='qa@wt.test') ORDER BY started_at DESC LIMIT 1;
-- ожидание: is_practice = t

-- активность засчитана (стрик живёт), новых слов не прибавилось
SELECT date, reviews_count, new_terms_count FROM daily_user_stats
WHERE user_id=(SELECT id FROM users WHERE email='qa@wt.test') ORDER BY date DESC LIMIT 1;
```

### 4.4 Слово вне пула получает только лёгкие режимы

**Шаги.** В свободной тренировке дождаться слова, которое **не** в пуле.

**На экране.** Такое слово приходит только карточками выбора и сборки — **никогда** typing,
listening или dictation.

```sql
-- проверка по журналу: у слов вне пула нет тяжёлых режимов
SELECT r.exercise_mode, count(*)
FROM reviews r
LEFT JOIN user_term_progress p ON p.user_id=r.user_id AND p.term_id=r.term_id
WHERE r.user_id=(SELECT id FROM users WHERE email='qa@wt.test')
  AND r.is_practice AND r.answered_at >= :run_started_at
  AND (p.enrolled_at IS NULL)
GROUP BY 1;
-- ожидание: НЕТ строк с typing / listening / dictation
```

**Находка, если** слово вне пула получило typing или dictation: это просьба воспроизвести слово,
которое человек может видеть впервые.

### 4.5 Практика не закрывает проверку «знаю»

Если в коллекции есть слово со `state='known'`: ответ на него в свободной тренировке **не должен**
разрешать проверку.

```sql
SELECT state, due_at FROM user_term_progress
WHERE user_id=(SELECT id FROM users WHERE email='qa@wt.test') AND term_id='<known-term>';
-- ожидание: state='known' и due_at не сдвинулся
SELECT is_verification FROM reviews
WHERE user_id=(SELECT id FROM users WHERE email='qa@wt.test') AND term_id='<known-term>'
ORDER BY client_seq DESC LIMIT 1;
-- ожидание: false
```

---

## 5. Генерация и обогащение

**~25 минут, ≈$0.035.** Одна маленькая коллекция на 3–5 слов.

> **Витрину не трогать.** Ни `store:publish`, ни `generation:regenerate-showcase`, ни правки
> системных коллекций. Проверяется своя, одноразовая коллекция QA-юзера.

### Предусловия

У QA-юзера остались генерации на сегодня (free-тариф — 3 в день):

```sql
SELECT count(*) FROM generation_requests
WHERE user_id=(SELECT id FROM users WHERE email='qa@wt.test') AND created_at >= date_trunc('day', now());
```

или на устройстве: Профиль → «Осталось N генераций сегодня».

### 5.1 Своя маленькая коллекция

**Шаги.** Главная → «Опиши тему — соберём коллекцию» → тема (например «Заказываю еду в кафе») →
Размер **«Маленькая»** → уровень → **«Сгенерировать»**.

**На экране.** Карточка «Собираем коллекцию…» с метаданными `тема · уровни · размер` и примечанием
«Подбираем слова и фотографии · обычно 20–30 секунд». Потом — «Готово» и коллекция в списке. Если
собрано меньше запрошенного — бейдж «N из M» и «Готова · собрано меньше».

**В БД.**

```sql
SELECT id, status, model, prompt_version, size, delivered_count,
       tokens_in, tokens_out, cost_usd, created_at, finished_at, error
FROM generation_requests WHERE user_id=(SELECT id FROM users WHERE email='qa@wt.test')
ORDER BY created_at DESC LIMIT 1;
-- ожидание: status='succeeded', collection_id НЕ NULL, cost_usd ≈ 0.02…0.05,
--           delivered_count = числу слов в коллекции
```

```sql
-- отбраковка: что модель предложила, а фильтры выкинули
SELECT reason, count(*) FROM generation_rejections
WHERE request_id = '<request_id>' GROUP BY reason;
-- не ошибка сама по себе, но объясняет «собрано меньше»
```

### 5.2 Автообогащение

**Шаги.** Дождаться, пока Horizon доработает (`docker compose logs -f horizon`).

**В БД.**

```sql
-- у каждого слова коллекции есть основной перевод, пример и описание
SELECT t.id, t.text, t.type, t.cefr, t.ipa IS NOT NULL AS has_ipa,
       (SELECT count(*) FROM term_translations tr WHERE tr.term_id=t.id AND tr.is_primary) AS primary_tr,
       (SELECT count(*) FROM term_translations tr WHERE tr.term_id=t.id)                    AS all_tr,
       (SELECT count(*) FROM term_examples te WHERE te.term_id=t.id)                        AS examples,
       (SELECT count(*) FROM term_descriptions td WHERE td.term_id=t.id)                    AS descriptions,
       (SELECT count(*) FROM term_accepted_variants av WHERE av.term_id=t.id)               AS variants,
       t.image_url IS NOT NULL AS has_image
FROM terms t
JOIN collection_items ci ON ci.term_id=t.id AND ci.deleted_at IS NULL
WHERE ci.collection_id = '<C>' ORDER BY t.text;
-- ожидание: primary_tr = 1 у каждого (НЕ 0 и НЕ 2), examples ≥ 1, variants ≥ 1
```

**`primary_tr = 2` — известная находка на витрине** (9 терминов с двумя `is_primary`); на свежей
коллекции это дефект обогащения.

### 5.3 Покрытие режимов

Каждый тренажёр требует своих данных. Слово без них молча не приходит в этом режиме — и это
выглядит как «тренажёр сломан», хотя сломан контент:

```sql
SELECT t.text,
       (SELECT count(*) FROM term_examples te WHERE te.term_id=t.id) > 0                   AS cloze_scramble_ok,
       (SELECT count(*) FROM term_accepted_variants av WHERE av.term_id=t.id) > 0          AS typing_ok,
       (SELECT count(*) FROM term_descriptions td WHERE td.term_id=t.id) > 0               AS description_match_ok,
       (SELECT count(*) FROM example_distractors ed
          JOIN term_examples te2 ON te2.id=ed.example_id WHERE te2.term_id=t.id) > 0       AS pick_correct_ok
FROM terms t JOIN collection_items ci ON ci.term_id=t.id AND ci.deleted_at IS NULL
WHERE ci.collection_id='<C>' ORDER BY t.text;
```

Сверить с админкой: **«Здоровье контента»** (`/content`) и паспорт термина (`/terms/:id`).

### 5.4 Версии обогащения

```sql
SELECT tev.generator_version, count(*) FROM term_enrichment_versions tev
JOIN collection_items ci ON ci.term_id=tev.term_id AND ci.deleted_at IS NULL
WHERE ci.collection_id='<C>' GROUP BY 1;
-- ожидание: одна версия на всю свежую коллекцию

-- паспорт контента: ни одной строки без штампа
SELECT count(*) FILTER (WHERE prompt_version IS NULL) AS unstamped_terms
FROM terms t JOIN collection_items ci ON ci.term_id=t.id AND ci.deleted_at IS NULL
WHERE ci.collection_id='<C>';
-- ожидание: 0. NULL = писатель создал контент и не проштамповал его (сентинел — 'legacy', не NULL)
```

### 5.5 Стоимость в учёте

```bash
docker compose exec app php artisan qa:cost qa@wt.test --period=day
```

Строка `generation` должна совпасть с `cost_usd` из `generation_requests`. Обогащение в
пер-юзерной разбивке **не появится** — реестр станка не хранит `user_id`, обогащение считается
только по флоту. Это не потеря, это устройство учёта; фактическая сумма — здесь:

```sql
SELECT count(*), round(sum(cost_usd)::numeric, 5) FROM term_enrichments
WHERE term_id IN (SELECT term_id FROM collection_items WHERE collection_id='<C>' AND deleted_at IS NULL);
```

### 5.6 Отказ генерации

**Шаги.** Исчерпать дневной лимит (сгенерировать 3 раза на free-тарифе) и попробовать четвёртый.

**На экране.** Карточка **«Генерации на сегодня закончились»** с временем сброса и «Открыть
Premium» — очередь **останавливается и говорит об этом**, а не обещает «отправим, когда появится
сеть» бесконечно.

**В логах.** `POST /api/v1/generations` → `429` с `code = generation_quota_exceeded`. Это
единственный 429, который клиент трактует как окончательный.

---

## 6. Офлайн и синк

**~20 минут, $0.** Проверяет, что офлайн ничего не теряет и ничего не дублирует.

### 6.1 Режим полёта

**Шаги.** Симулятор: Settings → Airplane Mode ON (или на хосте выключить сеть контейнерам). Вернуться
в приложение.

**На экране.** Баннер **«Нет сети. Повторения идут как обычно — синхронизируем, когда связь
вернётся»**. Экраны продолжают работать — они читают из локальной БД устройства, а не из сети.

### 6.2 Действия офлайн

**Шаги (все офлайн).**
1. Пройти сессию тренировки целиком.
2. Разобрать несколько слов свайпами.
3. Нажать «Учить это слово» на карточке и «Убрать из изучения» на другой.
4. Поставить генерацию в очередь.

**На экране.** Всё проходит. Генерация показывает «Отправим, как только появится сеть».

**В БД.** Пока офлайн — **ничего нового** на сервере:

```sql
SELECT max(client_seq) FROM reviews WHERE user_id=(SELECT id FROM users WHERE email='qa@wt.test');
SELECT max(client_seq) FROM term_triages WHERE user_id=(SELECT id FROM users WHERE email='qa@wt.test');
-- ожидание: те же значения, что до офлайна
```

### 6.3 Восстановление

**Шаги.** Airplane Mode OFF. Приложение ловит смену связности и само сливает все очереди (ответы,
триажи, решения о пуле, завершения сессий, генерации).

**На экране.** Полоска синка, потом обычная работа. Экраны показывают то же, что показывали офлайн.

**В БД — потерь нет.**

```sql
-- число ответов на сервере = числу карточек, отвеченных офлайн
SELECT count(*) FROM reviews
WHERE user_id=(SELECT id FROM users WHERE email='qa@wt.test') AND answered_at >= :offline_started_at;
```

**В БД — дублей нет.** `reviews.id` генерируется устройством (ULID), вставка идемпотентна:

```sql
-- один и тот же ответ не может лечь дважды
SELECT id, count(*) FROM reviews
WHERE user_id=(SELECT id FROM users WHERE email='qa@wt.test') GROUP BY id HAVING count(*) > 1;
-- ожидание: пусто

-- и одна и та же карточка не отвечена дважды за одну сессию одним и тем же seq
SELECT session_id, client_seq, count(*) FROM reviews
WHERE user_id=(SELECT id FROM users WHERE email='qa@wt.test') AND session_id IS NOT NULL
GROUP BY 1,2 HAVING count(*) > 1;
-- ожидание: пусто
```

**В логах.** `POST /api/v1/reviews/batch` → 200. В теле ответа:
- `accepted` = число новых ответов,
- `duplicates` > 0 — **норма** при повторной отправке пачки (очередь не получила ack),
- `unknown` > 0 — **находка**.

```sql
SELECT occurred_at, path, status,
       response_body->'data'->>'accepted'   AS accepted,
       response_body->'data'->>'duplicates' AS duplicates,
       response_body->'data'->>'unknown'    AS unknown
FROM api_request_logs
WHERE direction='inbound' AND path LIKE '%/reviews/batch%' AND occurred_at >= :run_started_at
ORDER BY occurred_at DESC LIMIT 10;
```

### 6.4 Очередь ответов переполнена

**Шаги.** Долго отвечать офлайн, пока очередь не упрётся в потолок.

**На экране.** Баннер о застрявшей очереди. Ответы, несущие прогресс, **никогда не выбрасываются**;
выбрасываются только практические. Молча потерянного прогресса быть не должно.

### 6.5 Порядок восстановления

Прогресс сворачивается по `client_seq` — монотонному счётчику устройства, а не по часам устройства.
Значит переигранная офлайн-пачка даёт тот же прогресс, что дала бы онлайн.

```sql
-- client_seq строго растёт и не имеет дублей у одного юзера
SELECT client_seq, count(*) FROM reviews
WHERE user_id=(SELECT id FROM users WHERE email='qa@wt.test') AND client_seq > 0
GROUP BY 1 HAVING count(*) > 1;
-- ожидание: пусто
```

---

## 7. БД-инварианты

**~10 минут, $0. Обязательно в конце каждого прогона.** Девять запросов; каждый обязан вернуть
**пусто** или ноль.

### 7.1 `reviews` — только append

Снять контрольную сумму префикса журнала в начале прогона и в конце. Изменение = кто-то обновил или
удалил уже записанный ответ.

```sql
-- ВНАЧАЛЕ прогона (запомнить обе цифры)
SELECT count(*) AS n,
       md5(string_agg(id || '|' || grade || '|' || answered_at::text, ',' ORDER BY id)) AS fingerprint
FROM reviews WHERE user_id = (SELECT id FROM users WHERE email='qa@wt.test');

-- В КОНЦЕ: тот же запрос, но с отсечкой по времени старта
SELECT count(*) AS n,
       md5(string_agg(id || '|' || grade || '|' || answered_at::text, ',' ORDER BY id)) AS fingerprint
FROM reviews
WHERE user_id = (SELECT id FROM users WHERE email='qa@wt.test') AND created_at < :run_started_at;
-- ожидание: n и fingerprint совпали с начальными
```

> **Единственное легальное исключение — `qa:time-travel`.** Она сдвигает `answered_at` и `created_at`
> всех ответов юзера, поэтому после машины времени отпечаток меняется по построению. Если сценарий
> 3 гонялся, снимайте отпечаток **после последнего сдвига**, а не до.

То же для `term_triages`:

```sql
SELECT count(*), md5(string_agg(id || '|' || verdict, ',' ORDER BY id)) FROM term_triages
WHERE user_id=(SELECT id FROM users WHERE email='qa@wt.test') AND created_at < :run_started_at;
```

### 7.2 Нет пар без юзера

`user_term_progress` **не имеет** внешнего ключа на `users` — осиротеть может:

```sql
SELECT count(*) FROM user_term_progress p
LEFT JOIN users u ON u.id = p.user_id WHERE u.id IS NULL;
-- ожидание: 0
```

То же для остальных таблиц обучения:

```sql
SELECT 'reviews' AS t, count(*) FROM reviews r LEFT JOIN users u ON u.id=r.user_id WHERE u.id IS NULL
UNION ALL SELECT 'term_triages', count(*) FROM term_triages x LEFT JOIN users u ON u.id=x.user_id WHERE u.id IS NULL
UNION ALL SELECT 'term_exposures', count(*) FROM term_exposures x LEFT JOIN users u ON u.id=x.user_id WHERE u.id IS NULL
UNION ALL SELECT 'study_sessions', count(*) FROM study_sessions x LEFT JOIN users u ON u.id=x.user_id WHERE u.id IS NULL
UNION ALL SELECT 'daily_user_stats', count(*) FROM daily_user_stats x LEFT JOIN users u ON u.id=x.user_id WHERE u.id IS NULL;
-- ожидание: везде 0
```

### 7.3 Нет пар без термина

Внешние ключи стоят (`ON DELETE RESTRICT`), так что это скорее проверка мягкого удаления:

```sql
-- прогресс на удалённом (soft-deleted) термине
SELECT count(*) FROM user_term_progress p JOIN terms t ON t.id=p.term_id
WHERE t.deleted_at IS NOT NULL AND p.enrolled_at IS NOT NULL;
-- ожидание: 0 — учить удалённое слово нельзя
```

### 7.4 Нет терминов-сирот без коллекции

```sql
SELECT t.id, t.text, t.source, t.created_at FROM terms t
LEFT JOIN collection_items ci ON ci.term_id=t.id AND ci.deleted_at IS NULL
WHERE t.deleted_at IS NULL AND ci.term_id IS NULL
ORDER BY t.created_at DESC LIMIT 20;
-- ожидание: пусто
```

Термин, ни в одной живой коллекции, недостижим из приложения, но продолжает попадать в станок
обогащения — то есть стоит денег и никому не показывается.

### 7.5 Нет дублей нормализованных терминов

Уникальный индекс `terms_dedup_uidx` — `(lang, normalized_text, coalesce(pos,''))` среди живых.
Проверка на случай, если он когда-нибудь разъедется:

```sql
SELECT lang, normalized_text, coalesce(pos,'') AS pos, count(*), array_agg(id) FROM terms
WHERE deleted_at IS NULL GROUP BY 1,2,3 HAVING count(*) > 1;
-- ожидание: пусто
```

И более мягкая, но полезная проверка — дубли по видимому тексту, различающиеся регистром/пробелами:

```sql
SELECT lang, lower(btrim(text)) AS t, count(*), array_agg(id) FROM terms
WHERE deleted_at IS NULL GROUP BY 1,2 HAVING count(*) > 1 LIMIT 20;
```

### 7.6 Ровно один основной перевод

```sql
SELECT term_id, lang, count(*) FROM term_translations WHERE is_primary
GROUP BY 1,2 HAVING count(*) <> 1 ORDER BY 3 DESC LIMIT 20;
-- ожидание: пусто.
```

> **Про эту проверку.** В `session-handoff.md` от 2026-08-20 записано, что у 9 терминов витрины по
> ДВА `is_primary` перевода. На 2026-08-22 запрос возвращает **0** — значит либо это починили, либо
> запись устарела. Базовая линия сейчас чистая, поэтому **любая непустая выдача — находка**.

### 7.7 Версии обогащения консистентны

```sql
-- термины, обогащённые больше чем одной версией станка
SELECT count(*) FROM (SELECT term_id FROM term_enrichment_versions
                      GROUP BY term_id HAVING count(*) > 1) x;
-- БАЗОВАЯ ЛИНИЯ на 2026-08-22: 38. Это следы бэкфилла v1→v2, а не дефект.
-- Находка — РОСТ этого числа за прогон, а не само число.

-- контент без паспорта: NULL значит «писатель создал строку и не проштамповал»
SELECT 'terms' AS tbl, count(*) FROM terms WHERE deleted_at IS NULL AND prompt_version IS NULL
UNION ALL SELECT 'term_translations', count(*) FROM term_translations WHERE prompt_version IS NULL
UNION ALL SELECT 'term_examples', count(*) FROM term_examples WHERE prompt_version IS NULL;
-- ожидание: 0 везде (сентинел старых строк — 'legacy', а не NULL)
```

### 7.8 `client_seq` без дыр в рамках сессии

Внутри одной сессии счётчик устройства обязан быть непрерывным: дыра значит, что ответ до сервера
не доехал.

```sql
WITH s AS (
  SELECT session_id,
         min(client_seq) AS lo, max(client_seq) AS hi, count(*) AS n
  FROM reviews
  WHERE user_id=(SELECT id FROM users WHERE email='qa@wt.test') AND session_id IS NOT NULL
  GROUP BY session_id
)
SELECT session_id, lo, hi, n, (hi - lo + 1) AS expected, (hi - lo + 1) - n AS missing
FROM s WHERE (hi - lo + 1) <> n ORDER BY missing DESC;
-- ожидание: пусто
```

> Дыра **не всегда** дефект. Номер легально пропускается, если ответ был отброшен как устаревший по
> лестнице, или если клиент тратит номер на карточку знакомства (она не создаёт строку в `reviews`).
> Тратит он его или нет — свойство клиента, а не сервера, и его надо один раз проверить на реальной
> сборке, а не предполагать: в самопрогоне 2026-08-22 (сессии игрались через API, знакомства номеров
> не тратили) дыр не было вовсе. Сверяйте дыру с числом показов той же сессии:
> ```sql
> SELECT session_id, count(*) FROM term_exposures
> WHERE user_id=(SELECT id FROM users WHERE email='qa@wt.test') AND session_id IS NOT NULL GROUP BY 1;
> ```
> Дыра размером не больше числа знакомств — объяснимая. Дыра больше — находка.

### 7.9 Согласованность лестницы и расписания

```sql
-- пара на ступенях узнавания не может иметь расписания
SELECT term_id, acquisition, learning_step, state, due_at, reps FROM user_term_progress
WHERE user_id=(SELECT id FROM users WHERE email='qa@wt.test')
  AND acquisition <> 'graduated' AND state = 'known';
-- ожидание: пусто (known всегда graduated по построению)

-- successful_reviews не может быть > 0 у невыпущенной пары
SELECT term_id, acquisition, successful_reviews FROM user_term_progress
WHERE acquisition <> 'graduated' AND successful_reviews > 0;
-- ожидание: пусто — счётчик растёт только на выпущенных

-- learning_step вне 0..2 (защищён CHECK, но проверим и логику)
SELECT term_id, acquisition, learning_step FROM user_term_progress
WHERE acquisition = 'learning' AND learning_step NOT IN (1,2);
-- ожидание: пусто
```

---

## 8. Логи и расходы

**~10 минут, $0. Обязательно в конце каждого прогона.**

### 8.1 Ошибки за период

```sql
SELECT status, method, path, count(*), max(occurred_at) AS last_seen FROM api_request_logs
WHERE direction='inbound' AND status >= 400 AND occurred_at >= :run_started_at
GROUP BY 1,2,3 ORDER BY 4 DESC;
```

Что норма и что находка:

| Статус | Норма, если | Находка, если |
|---|---|---|
| 401 | запрос до входа | после входа |
| 403 `not_a_qa_account` | сознательная проверка замка dev-входа | иначе |
| 404 | путь, которого нет по замыслу (dev-вход при закрытом флаге) | реальный ресурс |
| 422 | — | **всегда**: клиент отправил то, что сервер не принимает |
| 429 `generation_quota_exceeded` | сценарий 5.6 | иначе |
| 429 без кода | троттл — потолок в минуту, чистится сам | если сыпется постоянно |
| **5xx** | никогда | **всегда** |

```sql
-- исходящие вызовы, которые не удались
SELECT occurred_at, host, path, purpose, status, error FROM api_request_logs
WHERE direction='outbound' AND (status IS NULL OR status >= 400) AND occurred_at >= :run_started_at
ORDER BY occurred_at DESC;
```

```sql
SELECT id, queue, exception, failed_at FROM failed_jobs
WHERE failed_at >= :run_started_at ORDER BY failed_at DESC;
-- ожидание: пусто
```

### 8.2 Расходы за прогон

```bash
docker compose exec app php artisan qa:cost qa@wt.test --period=day
```

Печатает таблицу: `generation` / `practice` / `example_regen` / `search`, каждая строка со своим
реестром, плюс `TOTAL` и число символов мгновенного перевода за окно.

Флот целиком (то, что показывает `/dashboard` админки):

```sql
SELECT 'generation' AS purpose, count(*), round(sum(cost_usd)::numeric,4) FROM generation_requests WHERE created_at >= :run_started_at
UNION ALL SELECT 'enrichment', count(*), round(sum(cost_usd)::numeric,4) FROM term_enrichments      WHERE created_at >= :run_started_at
UNION ALL SELECT 'search',     count(*), round(sum(cost_usd)::numeric,4) FROM search_lookups        WHERE created_at >= :run_started_at
UNION ALL SELECT 'practice',   count(*), round(sum(cost_usd)::numeric,4) FROM practice_dialogs      WHERE created_at >= :run_started_at
UNION ALL SELECT 'ex_regen',   count(*), round(sum(cost_usd)::numeric,4) FROM example_regenerations WHERE created_at >= :run_started_at;
```

### 8.3 Аномалии стоимостей

```sql
-- вызов, стоивший заметно дороже своего класса
SELECT id, model, tokens_in, tokens_out, cost_usd, created_at FROM generation_requests
WHERE cost_usd > 0.10 AND created_at >= :run_started_at ORDER BY cost_usd DESC;
-- порог: обычная генерация ≈ $0.03

SELECT id, normalized_query, model, tokens_in, tokens_out, cost_usd FROM search_lookups
WHERE cost_usd > 0.002 AND created_at >= :run_started_at ORDER BY cost_usd DESC;
-- порог: обычный lookup ≈ $0.00023
```

```sql
-- одно и то же слово, оплаченное дважды: значит кэш поиска не сработал
SELECT normalized_query, lang, native_lang, count(*) FROM search_lookups
GROUP BY 1,2,3 HAVING count(*) > 1;
-- ожидание: пусто (защищено уникальным индексом; строка здесь = индекс потерян)
```

### 8.4 Отказы генерации

```sql
SELECT id, status, error, model, created_at, finished_at FROM generation_requests
WHERE status = 'failed' AND created_at >= :run_started_at ORDER BY created_at DESC;

-- зависшие: взяты в работу и не закончились
SELECT id, status, created_at, now() - created_at AS age FROM generation_requests
WHERE status IN ('pending','running') AND created_at < now() - interval '10 minutes';
-- ожидание: пусто
```

### 8.5 Известный пробел учёта

`qa:cost` печатает строку `search` отдельно и подписывает её `NOT in the panel breakdown` не для
красоты: пер-юзерная разбивка админки (`AdminCostReader::userBreakdownSince`) считает только
`generation_requests`, `practice_dialogs` и `example_regenerations`. **`search_lookups` в неё не
входит**, хотя у неё есть и `user_id`, и `cost_usd`. Разница между `TOTAL` и «panel would say» в
выводе команды — ровно этот пробел. Зафиксировано как находка; не чинилось.

---

## 9. UI/UX-осмотр

**~30 минут, $0.** Не про баги логики — про то, как приложение выглядит. **Рекомендации пишутся
отдельным разделом отчёта, не вперемешку с багами.**

Скриншот симулятора:

```bash
xcrun simctl io booted screenshot ~/Desktop/qa-<экран>.png
```

### Поверхности к осмотру

| # | Экран | Как попасть |
|---|---|---|
| 1 | Вход (кадр 10a) | выйти из аккаунта |
| 2 | Онбординг, все шаги | свежий аккаунт после `qa:reset` + новый юзер |
| 3 | Главная — пустая | новый аккаунт без слов |
| 4 | Главная — с очередью | после разбора коллекции |
| 5 | Главная — «На сегодня всё» | закрыть дневную цель |
| 6 | Коллекции — «Мои» | таб 2 |
| 7 | Коллекции — «Готовые» (стор) | таб 2 → сегмент |
| 8 | Карточка коллекции | открыть любую |
| 9 | «Мои слова» (пул) | главная → «Мои слова» |
| 10 | Триаж — карточка, оборот, итог | «Разобрать N» |
| 11 | Сессия — знакомство | новое слово |
| 12 | Сессия — узнавание | ступень 1–2 |
| 13 | Сессия — сборка (word_bank / cloze / scramble) | ступень 3 |
| 14 | Сессия — написание (typing / listening) | ступень 4 |
| 15 | Сессия — диктант | ступень 5 |
| 16 | Сессия — итог | конец сессии |
| 17 | Карточка слова + «Лестница слова» | тап по слову |
| 18 | Поиск — пусто / история / результаты / лимит | таб 3 |
| 19 | Экран генерации | главная → «Опиши тему» |
| 20 | Прогресс + активность за месяц | таб 4 |
| 21 | Профиль (+ секция «Разработка») | таб 5 |
| 22 | Пейволл | Профиль → «Попробовать Premium» |

### На что смотреть

- **Обрезки текста.** Длинное слово, длинный перевод, длинный пример, длинное название коллекции.
  Отдельно — цифры в кнопках («Повторить 128 слов»).
- **Переполнения.** Русский текст в среднем на 20–30% длиннее английского: проверить обе локали
  (Профиль → «Язык интерфейса»).
- **Тап-зоны.** Меньше 44×44 pt — находка. Особенно: иконка «ещё» на карточке слова, кнопки
  озвучки, чипсы уровней на экране генерации.
- **Тёмные углы.** Пустые состояния каждого списка; состояние загрузки; состояние ошибки;
  офлайн-версия каждого экрана.
- **Плавающий таб-бар** не должен перекрывать последний элемент списка ни на одном экране.
- **Безопасные зоны** на устройстве с «островом» — шапка и нижняя панель.
- **Клавиатура** не должна закрывать поле ввода и кнопку подтверждения (поиск, генерация, typing).
- **Скелетоны и спиннеры** не должны «моргать» на быстрых ответах.

---

## Приложение А — команды QA-стенда

```bash
# сброс обучения QA-юзера в ноль (аккаунт, профиль, коллекции, слова остаются)
docker compose exec app php artisan qa:reset qa@wt.test --force

# «прошло N дней» — сдвигает ВСЕ временные поля обучения назад
docker compose exec app php artisan qa:time-travel qa@wt.test --days=+3 --force

# расход по аккаунту за окно: day | week | month | all
docker compose exec app php artisan qa:cost qa@wt.test --period=day

# бэкап дев-базы перед чем угодно рискованным
./scripts/db-backup.sh
```

Оба пишущих командных инструмента **отказываются** работать:
- в `APP_ENV=production`;
- на аккаунте без `users.is_qa` (сообщение «is not a QA account. Refusing.»);
- без `--force` — переспрашивают, показав, сколько строк тронут.

Без `--force` команда печатает построчный отчёт по таблицам до подтверждения — это самая дешёвая
проверка, что выбран тот аккаунт.

## Приложение Б — что чем открывается

| Тренажёр | Ступень | Что должно быть у термина |
|---|---|---|
| `intro` | 0 (ровно) | перевод |
| `multiple_choice` | 1+ | перевод + чужие переводы на варианты |
| `word_bank`, `cloze`, `scramble` | 3+ | закреплённый пример |
| `pick_correct` | 3+ | пример **+ дистракторы примера** |
| `description_match` | 3+ | описание (`term_descriptions`) |
| `speaking` | 3+ | слово (рано) / пример (поздно) |
| `typing`, `listening` | 4+ (`successful_reviews ≥ 4`) | принятые формы (`term_accepted_variants`) |
| `dictation` | 5+ (`successful_reviews ≥ 6`) | пример |

Тренажёр не приходит, если выключен глобально ИЛИ не допущен ступенью ИЛИ у термина нет данных.
Три разные причины — три разных места смотреть, и путать их дорого.

## Приложение Б-2 — открытые находки, которые НЕ надо переоткрывать

Список ведётся прогонами. Пока строка здесь, красная проверка — ожидаемая; убирать строку, когда
починят.

| # | Открыт | Где | Что | Статус |
|---|---|---|---|---|
| QA-BUG-1 | 2026-08-22 | сценарий 3.6 | `due_at` пишется как полночь **UTC**, а не полночь зоны пользователя: смещение теряется на записи (query builder форматирует `Y-m-d H:i:s`, Postgres читает наивный литерал в `Etc/UTC`). Домен считает верно; F19 покрыт только юнит-тестами, которые не ходят в БД. | открыт, не чинился |
| QA-OBS-1 | 2026-08-22 | смоук 1.2, сценарий 9 | На «Прогрессе» `mastered` может быть больше `learned`: определения разные (`known` считается освоенным, но не выученным), на UI это не подписано. | наблюдение, не баг |
| QA-OBS-2 | 2026-08-22 | стенд | Кросс-сверка `/ladder` (шаг 3.8) требует пароля администратора — единственное место плейбука, где QA-аккаунта не хватает. | ограничение стенда |

## Приложение В — известные ограничения самого стенда

- **Машина времени необратима.** Отката нет по замыслу; обратная дорога — `qa:reset`.
- **После сдвига времени нужен полный ресинк на устройстве.** Сдвинутые строки старше курсора
  устройства, дельта-синк их не увидит. Pull-to-refresh на главной или в «Коллекциях».
- **`qa:time-travel` меняет `answered_at` у `reviews`.** Это единственный код в проекте, который
  трогает append-only журнал, и он живёт вне домена намеренно. Отпечаток из 7.1 снимайте после
  последнего сдвига.
- **Dev-вход умеет ровно две вещи:** войти в `is_qa`-аккаунт и создать новый `is_qa`-аккаунт. В
  чужой аккаунт им попасть нельзя — 403 `not_a_qa_account`.
- **В release-сборке dev-входа нет.** Флаг `kDevLoginEnabled` — это `kDebugMode` и ничего больше;
  на каждом коммите это держит `mobile/test/data/dev_login_release_guard_test.dart` (определение
  флага, единственный файл с `/auth/dev`, каждое упоминание за guard'ом).

  Раз в релиз это стоит подтвердить **на артефакте**, а не на исходниках:

  ```bash
  cd mobile && PATH="/opt/homebrew/bin:$PATH" LANG=en_US.UTF-8 flutter build ios --release --no-codesign
  ```

  ```bash
  grep -rac 'qa@wt.test' mobile/build/ios/iphoneos/Runner.app
  ```

  Должно быть **0** — и то же для `auth/dev`, `Dev login`, `debug-only door`. Контроль обязателен:
  тот же grep по debug-сборке (`build/ios/iphonesimulator/Runner.app`) должен дать НЕ ноль, иначе
  проверка ничего не доказывает — она просто не умеет читать этот артефакт. Строки Dart в debug
  лежат в `flutter_assets/kernel_blob.bin`, в release их выкидывает tree-shaker.

  Прогон 2026-08-22: debug — `auth/dev` ×2, `Dev login` ×3, `qa@wt.test` ×2; release — 0/0/0/0;
  контрольная строка `greedily-thermos-finer` (это `apiBaseUrl`) нашлась в обеих сборках.
- **Прогон можно вести и без экрана.** Всё, что делает приложение, — это те же вызовы `/api/v1`;
  первый прогон этого плейбука (2026-08-22) прошёл сценарий 3 целиком через `curl` под токеном
  dev-входа, потому что панель симулятора в тот момент не отвечала. Сверки по БД от этого не
  меняются — но экранная половина ожиданий («что видно») тогда остаётся непроверенной, и это надо
  писать в отчёт прямо, а не молчать.
