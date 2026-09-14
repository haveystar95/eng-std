# Session handoff — snapshot

> **Overwrite this file each session. Snapshot of current state, not a growing log.**
> Read with `CLAUDE.md`, `ARCHITECTURE.md`, `.claude/skills/`, `deptrac.yaml`, `docs/ROADMAP.md`.
> **`docs/DECISIONS.md` (в корне репозитория) читается ПЕРВЫМ шагом любого наряда** — это реестр
> действующих решений; если постановка ему противоречит, стоп и доклад архитектору.
> Канон плана: **`docs/plan-v2.md`**; контракт: **`docs/plan-api.md`** + `openapi/openapi.yaml`
> (теги `Plans`, `Devices`); модуль: `app/Modules/Plan/README.md`.

Branch: `main`. Last updated: 2026-09-14 ~23:00 Киев (наряд DAY-UI-3, код закрыт; голос ждёт квоты).
Отчёт — `docs/research/day-ui-3/README.md` (Ч.0–Ч.13), инструменты — там же `tools/`.

---

## ПЕРВОЕ: утро 15.09 — проверить голос (после 07:00 UTC / 10:00 Киев)

Суточная квота Gemini TTS (100 запросов на модель, проект **`899059304000`**) исчерпана 14.09; по документации
сбрасывается в полночь Pacific — **07:00 UTC**. Код закрыт, ворота зелёные; ниже — всё, что осталось проверить
живьём, по порядку. Результаты — в отчёт (Ч.4б, «Стоимость дня», Ч.8 строка 10, Ч.13) и коммит.

0. **Ключ сменился** (Ден проверяет биллинг проекта) → не ждать 07:00: `docker compose restart horizon`, отпустить
   отложенные jobs (`queues:default:delayed`: score → текущее время, tinker + `Redis::connection()->zadd`), дальше
   по списку.
1. **Отложенные `VoiceSceneJob`** — четыре, на 07:00:30–07:00:45 UTC: сцены `01M2GJXC21MVGRTYJ9G529D3BT` (день 1
   живого плана `01M2GJX2WD0VS4TC2XZRFJ436V`, QA `qa-dayui3@wt.test`), `01M2GJXC24BSZHKGACF9ZK0XWV` (его день 2),
   `01M2GF2Y18JT5JKPMK286EQ6JS` и `01M2GF2Y19ZTNF4PYK8PPWDK46` (план фикстур). Проверить: очередь пуста,
   `failed_jobs` 0, в `storage/logs/laravel.log` строки `gemini speech cut` (форма `turns`, `spans_ms`) или
   `gemini speech not cut` (причина).
2. **Пачка фраз**: у сцены `01M2GJXC21MV…` шесть строк `p1…p6` в `plan_line_audios` — **шесть файлов разной
   длины** (`duration_ms`), слова `v1…v8`, реплики `x1…x8` / `x1b…x8b`; `docker compose exec -T app php
   docs/research/day-ui-3/tools/verify_cut.php 01M2GJXC21MVGRTYJ9G529D3BT` — каждый кусок слышит свою строку
   (транскрипция OpenAI ≈ $0.003/мин). Не разрезалось обеими формами — разбор по логу, не повторять вслепую.
3. **Симулятор** «DayUI iPhone 17» (`F97AD291-D6D8-467C-AF64-293863160DCD`), приложение уже вошло как
   `qa-dayui3@wt.test`: `cd mobile && DEVELOPER_DIR=/Users/yalantisdenys/Documents/privar_sert/Xcode-beta.app/Contents/Developer
   flutter run --debug -d F97AD291-… --dart-define=API_BASE_URL=http://localhost:8001 --dart-define=DEV_LOGIN_EMAIL=qa-dayui3@wt.test`
   (лог в файл, фоном). Таб «План» → плита дня → окно: `[day-media]` — голос N (сеть N); вкладка «Слова» → карточка →
   шит: «прослушать» 44 и «В разговоре» → в логе `[line-audio] play «…»`; вкладка «Диалог» — «прослушать» у реплики
   собеседника и у реплики ученика → два разных голоса (`voice_key` в базе). Снимки — `xcrun simctl io <udid>
   screenshot` (MCP-скриншот симулятора падает; tap/swipe через MCP работают), рядом с кадрами 23-0e / 23-0d
   (`docs/research/day-ui-2/tools/render_frames.py`, `compose.swift`). Второй вход в окно — тёплый замер голоса.
4. **Бэкфилл** (сначала `scripts/db-backup.sh`): `docker compose exec -T app php artisan plan:speak-backfill
   --plan=01M2BQGKM4MA2RKFCDMPBFN75N` (план Дена, 5 пакетов) → `plan:speak-backfill` (все). До: реплики
   собеседника 135, ученика 232, фразы 129, слова 240 (30 сцен), план — 61 пакет. Команда печатает пакеты и
   «до / после»; на суточном лимите останавливается — остаток в следующие сутки.
5. **Телефон Дена**: после бэкфилла его плана `GET` дня отдаёт `audio_url` у реплик ученика, фраз и слов;
   Дену — открыть окно дня (оно докачивает голос дня) и послушать реплику ученика и фразу.
6. **Стоимость голоса дня**: `SUM(cost_usd)` в `plan_line_audios` сцены `01M2GJXC21MV…` (оценка ≈ $0,017).

## DAY-UI-3 — что сделано (коммиты — отчёт Ч.11)

**Сервер:**
- `window` дня: у слова `pronunciation`, `definition`, `audio_url`, `usage {text, translation, offset, length,
  audio_url}`, `returns_day`; у фразы `pronunciation`, `audio_url`; `audio_url` у обеих реплик; `lesson-v4`
  (`role_gender`); внутренний `illustrating` (на проводе `building`) — **`ready` ждёт фото, не голос**.
- Фото при генерации дня: `IllustrateSceneJob` сразу после урока, пул 6, повторы; лестница `image_prompt` →
  «слово, тема сцены» → тема своей страницей, **голого слова нет**; день не показывает одну картинку дважды.
- Голос всего (DECISIONS п. 309): два голоса сцены (`partner_voice_gender`, по `role_gender`), диалог — один
  вызов с двумя говорящими и нарезкой по паузам (`PcmTurnCutter`), фразы и слова — пачкой в форме реплик двух
  чтецов одним голосом, вторая попытка — списком; ≤ 4 вызовов на день; суточный отказ ждёт позднее из `retryDelay`
  и полуночи Pacific; `plan:speak-backfill` — **пакетами по видам** (диалоги без реплик собеседника → без реплик
  ученика → фразы → слова, до 12 в вызове через сцены).
- `plan:images-backfill --requery` — фото голого слова (61, заменено 54) и повторы картинки дня (7 → 0).
- Гонка «урок затирает фото сцены» закрыта: `saveScene` не пишет колонки фото.

**Клиент** (`mobile/lib/features/plan/day/day_window_screen.dart`, `window/`, `data/audio_loader.dart`): плита во
всю ширину, цели одним предложением, пилюля на шве и прилипает, шапка 56 — функция прокрутки, доводка один
`animateTo` 260 мс, шит слова 23-0e, «прослушать» у каждой строки, весь день (фото и голос) в загрузку при открытии.
Снимки `test/goldens/plan/23-0*` (16), канон `day_window_canon_test.dart` (28), мутации — отчёт Ч.6.

**База `wordtrainer`:** миграции `2026_09_14_200000/200100/200200` применены (бэкап `wordtrainer-20260914-200819`),
откат проверен на `wordtrainer_test`; бэкфилл фото — бэкапы `wordtrainer-20260914-211616`, `…-212002`. Голос не
тронут бэкфиллом (ждёт квоты).

**Ворота** (один раз, финальный коммит): deptrac 0, PHPStan 0, Pest 2027, `flutter analyze` 0; `flutter test` 1410
(клиент `bca543ce`). **Телефон**: «iPhone (Denis)» — `scripts/build_ios.sh` (`BUILD_SHA=bca543ce`), Runner.app
2026-09-14 21:48:51, установлено поверх (`devicectl install`, данные целы). Клиент после этого не менялся.

## Решения, которые не переделывать

- Сервер озвучивает всё: реплики собеседника и ученика, фразы, слова; голос ученика — один на его реплики, фразы и
  слова; «премиум только собеседнику» ОТМЕНЕНО (DECISIONS п. 309).
- Голос и фото — два job'а из `BuildLessonHandler`, выполняются параллельно; день не ждёт голос.
- «Плита ≤ 60 %» — без статус-бара; «0…160 px» — длина перехода плиты в шапку на её пути (плита едет 1:1).
- Время сброса суточной квоты — из ответа 429, но не раньше полуночи Pacific (правка владельца 14.09).

## Известные хвосты (ROADMAP, «Окно дня — DAY-UI-3»)

- Таб «План» не перечитывает сервер при возврате на таб (`refresh()` только в `initState`).
- Сессия дня: у реплики ученика в этапе «Диалог» нет «прослушать»; `DayVoice.prepare` докачивает только реплики
  собеседника (голос ученика, фраз, слов в сессии — только если его уже скачало окно).
- Фикстуры окна сняты при исчерпанной квоте — у слов и фраз `audio_url` пустые.

## Контекст, который остаётся верным (PLAN-GEN, PLAN-UI-3, DAY-UI)

- План — модуль `Plan`, два замороженных промпта (`plan-builder-v2`, `lesson-v4`), проверки в `observe`; дни
  открываются по одному в календарный день зоны; `plan:shift-day {plan} --days=N --force` вместо QA-часов. Урок
  дня N+1 пишется при открытии дня N.
- Решения владельца 12.09 (PLAN-UI-3): пять этапов на линии маршрута; push без entitlement — напоминания
  локально, пока `push_enabled: false`; `plan.summary` без модели; языки — `GET /plans/languages`.
- Клиент: одна дверь в окно дня — `openDayRoom`; состояния — golden-тесты из живых фикстур, канон —
  утверждениями, проверенными мутацией; жесты в тестах — `semanticsEnabled: false`.
- Стенд: `:8001` + horizon (перезапуск после правки job'ов и того, что они зовут); `/auth/dev` — только `qa-*`;
  горячий перезапуск фонового `flutter run` — `kill -USR2 <pid flutter_tools>`; смена QA-аккаунта —
  `xcrun simctl keychain <udid> reset`; свежий аккаунт проходит онбординг (4 экрана).
- Канву читать скриптом со всеми свойствами (`docs/research/day-ui-3/tools/dc.py`), геометрию кадра —
  `tools/measure.html` (headless Chrome `--dump-dom`), кадр в PNG — `docs/research/day-ui-2/tools/render_frames.py`.
