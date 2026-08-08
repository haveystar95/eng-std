# Session handoff — snapshot

> **Overwrite this file each session. Snapshot of current state, not a growing log.**
> Read with `CLAUDE.md`, `ARCHITECTURE.md`, `.claude/skills/`, `deptrac.yaml`, `docs/ROADMAP.md`.
> `triage-contract-findings.md` is frozen. Full device-run detail: `docs/device-batch-run1.md`.

Branch: `feat/mobile-backend2-cutover` — **not merged to `main`**. Last updated: 2026-08-08.

> **LATEST TRACK — Device-batch run1: CLOSED (2026-08-08).** Полный приёмочный прогон на iPhone с
> чистого аккаунта (`denys.solonyna@yalantis.net`, стёрт в конце): **блоки 0–9 + день-2 SRS-петля +
> удаление аккаунта**. Прошли: онбординг+профиль, генерация (недобор/бейджи/картинки Pexels), триаж,
> тренажёры (multiple_choice/word_bank+частицы-дистракторы/typing+«Не помню»), итог сессии, SRS-петля
> (Sm2-переходы learning→review, интервалы, полный цикл), ещё коллекции (идиомы/фраз.глаголы +
> немецкая ru→de + TTS de), офлайн /sync (дек офлайн, durable-очередь, реконнект-флаш, проекция,
> курсор/тонстоуны, триаж не воскресает), realtime-диалог (premium, транскрипт по-предложениям,
> coverage, ModelCost), профиль (язык en↔ru), негативные (офлайн-старт, лимиты), удаление аккаунта
> (контрольный SELECT — 0 по всем таблицам).
>
> **13 багов пофикшено ВЖИВУЮ (все зелёные гейты, закоммичено):** F1 онбординг per-account → серверный
> `onboarded_at`; F8 CTA-ветка «Учить N»; F9 закреплённая «Дальше»; F10 TTS в mute; F11 итог сессии
> (IntrinsicHeight); F12 «Проверить»→«Дальше»; F14 CEFR-полоса = предпочтение; F16 TTS язык коллекции;
> F21 null-ответ «Не помню» не терялся; F23 **удаление чистит practice-диалоги/транскрипты (PII)**.
> Dev-инструмент `batch:age-progress` (local-only машина времени для day-2 SRS). Заведено **8 чипов**
> (Training Loop v2 и пр.) — см. ROADMAP «Device-batch run1».
>
> **AHEAD (следующие треки):** (1) **Training Loop v2** — F19 (SRS due по календарному дню — ПЕРВЫМ
> фиксом), F17 (free-practice дрилит выученные-не-due), лестница режимов/квоты; (2) **хвосты стора** —
> preview-эндпоинт закоммичен (`49d51d7`), но `openapi.yaml` в дереве смешан → стор-сессии закоммитить
> свой openapi-хунк, дерево контракта должно стать чистым; (3) **пачка чипов** (флаги, Слово дня,
> параллельные картинки, языковые значки, офлайн-кеш картинок, F18 бэкфилл активности, F21 robustness);
> (4) **Apple Developer** (платный → `/auth/apple`, Sign in with Apple); (5) **аудит** + **прод-инфра**.

---

## Что готово этим треком (с хешами, ветка `feat/mobile-backend2-cutover`)
- `81a7239` fix(identity): серверный `profiles.onboarded_at` — поле + PUT/profile (`onboarded:true` штампит один раз) + `/me`-маппинг + миграция с бэкфиллом (`updated_at<>created_at`). 3 теста (F1).
- `b5a6398` fix(mobile): онбординг-гейт из `user.profile.onboardedAt` (кэш переживает офлайн), keychain-флаг удалён (F1).
- `d096cf3` chore(dev): `batch:age-progress {email} {--hours} {--reviews} {--force}` — APP_ENV=local, forced-time (в `app/Console/Commands/`, вне модулей → deptrac uncovered).
- `49d51d7` feat(store): `GET /store/collections/{id}/preview` (первые термины + total) + клиент (⚠️ этот коммит утащил `openapi.yaml` целиком, включая мой `onboarded_at`; хвост — на стор-сессию).
- `630a43f` fix(learning): `reviews.*.response` → `nullable` (пустой «Не помню» приходил `null` → 422 → клиент дропал → потеря). Контроллер уже коалесит `null→''`. Тест (F21).
- `dc21d5d` fix(generation): `EloquentGenerationAccountEraser` теперь чистит `practice_dialog_messages`→`practice_dialogs`→`example_regenerations`→`generation_requests`. `DeleteAccountTest` расширен (F23, PII).
- День-1 (те же ветка/прогон): `e95809d` mobile-фиксы F1/F8–F12/F16; `d2f5539` F14 DraftValidator; `739ca5d`/`9ffff96`/`9d541a8` docs батча.
- **Смежно (стор-сессии, уже в ветке):** `8ba83e0` /sync несёт триаж-вердикты (проверено офлайн: триаж не воскресает); `877a23f` пустая learn-сессия при исчерпанной квоте → honest empty state (адресует F13); `796a551`+ стор-каталог/подписки.

## Device-verified vs code-only
| Пункт | Как | Статус |
|---|---|---|
| Все 13 fixed-in-run багов | реальный прогон на iPhone + зелёные гейты (arch/stan/test, flutter analyze/test) | ✅ device-verified |
| SRS-петля (Sm2 learning→review, интервалы, reps, lapses) | снапшоты БД до/после (forced-time age-progress) | ✅ (данные) |
| Удаление аккаунта (стирание всех таблиц) | контрольный SELECT → 0; `DeleteAccountTest` 27 ассертов | ✅ |
| Realtime-диалог + ModelCost + транскрипт | живой диалог, `practice_dialogs`/`_messages` в БД | ✅ |
| Офлайн /sync (дек, durable-очередь, проекция, тонстоуны) | авиарежим → реконнект → БД | ✅ |
| typing-«Почти» (1-символьный тайпо), лимит диалогов 5/день, лимит генераций 3/день | не догоняли (стоимость/1-символьный ввод); UI лимитов видели | ⚠️ code-only |

## Не переигрывать молча (решения)
- **F1 = серверный `onboarded_at`** (не клиентский keychain-флаг): привязан к аккаунту, переживает wipe/reinstall/устройства. Штампится один раз, не перезаписывается.
- **F14: CEFR-полоса — предпочтение, не жёсткий гейт.** Если in-level < MIN_ITEMS (8) → берём все валидные items, не отклоняем драфт. Жёсткий отказ — только для реально короткого/битого ответа.
- **F21: `response` nullable**; пустой ответ = промах (грейд again). Клиент/сервер терпят null.
- **F23: eraser Generation покрывает practice-диалоги/транскрипты/регенерации** — PII уходит с аккаунтом.
- **Realtime заморожен**, дефолт `PRACTICE_DRIVER=openai` (gemini — выключенная альтернатива). Практика НЕ пишет reviews/progress.
- **F19 НЕ трогали в прогоне** (планировщик чинить нельзя посреди замера) — первым фиксом после батча.

## Известные ограничения / отложенное
- **F19** — SRS due по точному таймстампу, а не календарному дню (вечерний ответ → due вечером). ROADMAP → Training Loop v2, первым.
- **F17** — free-practice отдаёт только due-термины → пусто, когда ничего не due (нельзя дрилить выученное по требованию).
- **F18** — активность (Reviews-today/неделя/график) из локального `daily_activity`, стирается при релогине; Home-goal и streak (сервер) ок. Нужен серверный per-day activity + backfill.
- **F21 residual** — клиент дропает весь чанк на 422 (batch отдаёт только агрегат) + flush не дренирует под `_flushing` + `resync` не пушит up-очередь. Чип.
- **F22** — картинки без офлайн-кеша (`Image.network`) → в авиарежиме пусто. Чип.
- **F13/F15/F20** — «Учить N» пустая при исчерпанной квоте (адресовано `877a23f`); залипшая клиентская квота генераций после фейла (рефетч `/me`); микро-подвисания анимаций сессии (нужен профайл).
- **openapi.yaml дерево смешано** — стор-preview + мой `onboarded_at` в одном diff; стор-сессии закоммитить свой хунк.
- (carried) delta-sync `GET /sync?since=` полный, лог-ретеншн, two-device `client_seq` collision, retire `../backend`, merge в `main` (только по явной просьбе).

## Running / verifying
- Backend2 в Docker (`wt_app` :8001, `wt_db` :5433, `wt_horizon`, `wt_redis`, `wt_ngrok` → static `greedily-thermos-finer.ngrok-free.dev`). Гейты: commit-hook `composer check` (arch+stan+test) для backend2, `flutter analyze` для mobile. `composer stan` анализирует `app/`.
- Mobile: `PATH="/opt/homebrew/bin:$PATH" LANG=en_US.UTF-8 flutter run --release -d 00008110-000A7CCC3492801E`.
- Dev-время: `docker compose exec app php artisan batch:age-progress <email> --hours=N --force` (local-only).
- После правок кода джоб/провайдеров: `docker compose restart horizon` (кэш кода в памяти).
