# Session handoff — snapshot

> **Overwrite this file each session. Snapshot of current state, not a growing log.**
> Read with `CLAUDE.md`, `ARCHITECTURE.md`, `.claude/skills/`, `deptrac.yaml`, `docs/ROADMAP.md`.
> **`docs/DECISIONS.md` (в корне репозитория) читается ПЕРВЫМ шагом любого наряда** — это реестр
> действующих решений; если постановка ему противоречит, стоп и доклад архитектору.
> Канон плана: **`docs/plan-v2.md`**; контракт: **`docs/plan-api.md`** + `openapi/openapi.yaml`
> (тег `Plans`); модуль: `app/Modules/Plan/README.md`. Отчёт наряда — `docs/research/plan-gen/README.md`.

Branch: `main`, коммит `5b2809c1`. Last updated: 2026-09-10 (наряд PLAN-GEN).

---

## ПЕРВОЕ: PLAN-GEN — новый бэкенд плана, старая цепочка снесена

Наряд одним махом: (1) удалена вся старая цепочка плана (P1 → P2 → судья → починка → нарезка,
лестница A/B/C, умения, чек-пойнты, готовность, спасатели-сущность, QA-часы, озвучка `term_audios`,
плановые колонки `terms`, второй scope `learning_mode_settings`, ситуативные режимы) — 403 файла;
(2) написан модуль `app/Modules/Plan` (Domain/Application/Infrastructure/Presentation) под два
замороженных промта `plan-builder-v2` и `lesson-v3`, проверки в коде с режимами
`observe`/`drop`/`gate`, детерминированную сборку дня из пяти этапов, возвраты, метрики; (3) API
`/plans*` переписан (см. `docs/plan-api.md`); (4) живой прогон четырёх целей через сервер и
симулятор дня.

**Что надо знать, прежде чем трогать план:**

1. **Две модели-вызова, оба через `Generation`'s `ContentModelCatalog`** (purpose `plan`, свой
   таймаут 90 с). Цена/задержка/попытки/версия промпта/версия сборки лежат на `plans` и
   `plan_scenes`, не в `generation_requests`.
2. **Проверки все в `observe`** (`config/plan.php`); режим переключается там, счётчики —
   `GET /admin/api/plans/checks`. Живой прогон дал по 2–6 наблюдений на урок (`vocabulary_id_absent`,
   `vocabulary_contained`, `second_message_question`, `variant_length`, `phrase_unused`) — все
   честные; урок принимается как есть, сборка терпит.
3. **Дни открываются по одному в календарный день** зоны пользователя; даты сравниваются как
   даты, не моменты. QA-часов нет — `php artisan plan:shift-day {plan} --days=1` сдвигает даты
   плана в прошлое (на `wordtrainer` только с `--force`).
4. **Миграции-сносы применены к `wordtrainer` 10.09** (доработка; владелец подтвердил, что все
   аккаунты со старыми планами тестовые). Бэкап `storage/db-backups/wordtrainer-20260910-233221.sql.gz`;
   сверка до/после — отчёт §5а; Horizon перезапущен на новом коде.
5. **Мобильный клиент не тронут** и работает против УДАЛЁННЫХ маршрутов (`/plans/{id}/outline`,
   `…/session`, `/qa/plan-clock`, `/audio/lines/*`, `/plans/listen-warmup`). Экраны плана на
   телефоне — отдельный наряд под `docs/plan-api.md`. Обычные сессии (`/study/sessions`)
   потеряли три поля карточки — `speaking_key`, `speaking_keys`, `say_as`; клиент читает их
   null-safe (`models.dart:653`), ничего не ломается.
6. **Версия сборки** для `versions.build` — `APP_COMMIT` или `storage/app/commit`; штамп пишет
   `scripts/stamp-build.sh` (запускать после каждого коммита, который едет на телефон).
7. **Решения наряда — DECISIONS пп. 304–308**; старая цепочка плана целиком в «Отменено».

Ворота один раз в конце: OpenAPI ok, deptrac **0 нарушений**, PHPStan **0 ошибок**, Pest
**1864 passed** (10 процессов, ~32 с). `flutter analyze` — см. отчёт наряда.

## Что изменилось в контракте — клиент ОБЯЗАН это прочитать

Весь контракт плана новый: `docs/plan-api.md`. Кратко: `POST /plans` → 202 и опрос
`GET /plans/{id}/build`; `GET /plans/current`; `POST …/start`; день — `GET …/days/{n}` (кабинет),
`POST …/days/{n}/open` (весь список карточек с пейлоадами), `POST …/cards/{id}/answer`
(`{result, attempts}` → `{card, requeued}`), `POST …/stages/{stage}/close`, `POST …/close`
(метрики), `GET …/sheet`; `PATCH …/schedule`, `POST …/finish`, `DELETE`. Все склоняемые строки
готовые. Аудио реплик собеседника — `GET /plans/audio/{id}`.

## Что осталось / вопросы владельцу

См. отчёт `docs/research/plan-gen/README.md` §6–7: модуль `Plan` заведён без предварительного
вопроса (внесён в DECISIONS п. 304); клиент под новый контракт; prune старых версий промптов
генерации (`generate_collection.v1…v8` и т. п.) — вне наряда, не трогались.
