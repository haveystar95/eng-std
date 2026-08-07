# Session handoff — snapshot

> **Overwrite this file each session. Snapshot of current state, not a growing log.**
> Read with `CLAUDE.md`, `ARCHITECTURE.md`, `.claude/skills/`, `deptrac.yaml`, `docs/ROADMAP.md`.
> `triage-contract-findings.md` is frozen.

Branch: `feat/mobile-backend2-cutover` — **not merged to `main`**. Last updated: 2026-08-07.

> **LATEST TRACK — Realtime practice: CLOSED & FROZEN (both sides).** Голосовой разговор с ИИ
> по коллекции работает end-to-end. **Backend** (module Generation, frozen): 4 эндпоинта
> (`POST /practice/dialogs`, `.../{id}/transcripts`, `.../{id}/finish`, `GET /practice/collections/
> {id}/last-dialog`); промпт **v3 с жёсткими CEFR-правилами** + react-then-ask; **два драйвера за
> `PRACTICE_DRIVER`** (RealtimeTokenPort): **openai — дефолт**, **gemini — выключенная альтернатива
> ~2× дешевле** (bare-токен + `session_setup`, WS `v1alpha …Constrained`); coverage (слово=user,
> фраза=любая роль), стоимость-оценка по докам с cap по span транскрипта; практика НЕ пишет
> reviews/progress. **Frontend**: WebRTC (OpenAI) + Gemini WS-транспорт, фабрика транспорта по
> `provider` из ответа старта. Гейты зелёные (arch 0 / stan L8 / pest), invariant-reviewer CLEAN,
> живой минт на обоих ключах. Идеи фазы 2 — в `ROADMAP.md` (не в работу).
>
> **AHEAD (next block):** (1) **device-batch** — интерактивный прогон (в т.ч. action-лог сессии,
> проверка **день-2 SRS** — что `due_at` долетает и карточки возвращаются) + чек-лист A3.1–A3.8
> ниже; (2) **A3.9 — стор + пейволл** (B5), (3) **стартовые коллекции** (first-run 3 набора),
> (4) **Apple Developer** (платный аккаунт → `/auth/apple`, Sign in with Apple), (5) **прод-инфра**.
>
> **Client redesign — Session A (paper/ink): A3.0–A3.8 + A3-close DONE.** A3.1–A3.2 device-verified;
> A3.3–A3.8 — device-прогон **батчем** (чек-лист ниже) — часть device-batch выше. **A3-close DONE**:
> `lib/core/` удалён (`buildAppTheme()`), оба guard-allowlist **пусты**, `google_fonts` убран,
> `app_en.arb` полон + `en` в `kSupportedLocales`. A3.7 Apple-вход ждёт `/auth/apple` + платный
> Apple-аккаунт. Разделы «Generation full feature» и «Client redesign — Session A» ниже — завершённые
> треки, оставлены ради device-сценариев и перенесённых findings.

---

## Latest track (complete + frozen): Realtime practice — backend

Голосовая практика по коллекции (premium). Аудио идёт мимо сервера (WebRTC/WS клиент↔провайдер);
сервер выдаёт урок + эфемерный токен, принимает транскрипты, считает coverage, оценивает стоимость.
Модуль Generation, обычные слои; **практика НЕ пишет reviews/`user_term_progress`** (invariant-reviewer
CLEAN на каждом раунде).

**Коммиты:** `745f648` база (3 эндпоинта, OpenAI) · `ff63048` input-транскрипция · `7c02933`
v2/coverage-сплит/VAD/last-dialog · `9424878` стоимость по речи агента · `107b46b` CEFR v3 + драйвер
Gemini + `provider`/`endpoint` · `2057c36` Gemini `session_setup` · `9f46b31` фикс WS-endpoint
(`v1alpha …Constrained`) · `91ec1da` cap аудио по span транскрипта + react-then-ask. Процессный
`962b836` (правило shell). Dev смигрирован (4 миграции practice).

**Не переигрывать молча (решения фичи):**
- Практика **никогда** не пишет reviews/progress — только читает due/started (ребро deptrac
  `GenerationApplication → LearningApplication`, цикла нет).
- Тела ответов practice — **без обёртки `data`** (дословно по контракту).
- Модель активного драйвера резолвится в провайдере (Application провайдер-агностик); суммаризатор
  итога — всегда OpenAI-текст, независимо от realtime-драйвера.
- Стоимость — **оценка, калибрована по токенной формуле из доков** (не по дельтам баланса: они
  запаздывают/агрегируются — сверка по суммарному за день). Аудио-секунды ограничены реальным span
  транскрипта (иначе expired/длинный TTL раздувает вход).
- Gemini `liveConnectConstraints` отвергается живым `auth_tokens` → bare-токен + `session_setup`;
  `PRACTICE_GEMINI_CONSTRAINED=true` — на когда Google выкатит поле.
- Дефолт `PRACTICE_DRIVER=openai`; gemini — рабочая, но выключенная альтернатива.

**Device-check практики:** не требуется на стороне бэка (проверено живым минтом обоих провайдеров +
тестами); реальный WS-коннект и аудио проверяются на клиенте (входят в device-batch выше).

---

## Earlier track (complete): Generation → full feature — **backend + client complete**

The generator is now a full feature end-to-end: backend (A1–A6 + A3 images), contract, and the
client UX (Part B). **A3 (Pexels images) is done and verified server-side on a real key. Part B (the
client half) is done and code-verified (`flutter analyze` + 23 tests), but NOT yet run on the
device.** The one remaining step for the whole feature is a **device end-to-end run** (scenarios
below). v4 is the live prompt (`PROMPT_VERSION='v4'`).

## What's done this session (with commit hashes)

**A3 — Pexels images (backend):**
- Schema (`196ce20`), `ImageSearchPort`+Pexels adapter+fake (`b1d5f9f`), prompt v4 (`fd47322`),
  flip to v4 (`cba9661`), `AttachImagesJob` (`cc86110`), `/sync`+drift image fields (`c3525ab`).
- Verified end-to-end server-side (`27551f9`): a real `generation:make` attached a Pexels cover +
  8/8 term photos with attribution. invariant-reviewer CLEAN.

**Part B — client generation UX (`42fd584`):**
- **B1 create screen** (`generate_screen.dart`): situation field + rotating placeholder; size
  маленькая/средняя/большая → 10/15/22 (no number); levels default from profile; target-language
  dropdown (source = UI language) — first language choice in the UI, lives on the collection; button
  greys out on exhausted quota with remaining + resets_at (device-local) from `/me`.
- **B2 pending card + reconciliation**: client-only `PendingGenerations` drift table (schema v5),
  survives an app kill; `GenerationController` polls + reconciles on launch/resume (succeeded→sync+drop,
  failed→error card+retry, pending/running→poll, >24h/404→drop+log). Card faces: generating / failed
  («Повторить») / ready (cover, title, count, "получилось N из M"), tap → collection with a
  first-contact «Разобрать» banner.
- **B3 images + type badges**: collection cover on the tile + ready card; term photo on the word card
  with a typed placeholder; badges слово/фраза/идиома/фраз. глагол (unknown→phrase); "Фото: Author ·
  Pexels" credit with a clickable author link (**new dep: `url_launcher`**). Images dock in via the
  drift stream (no reload).
- Data: image fields on `WordCollection`/`Word`; `GenerationQuota`/`GenerationStatusView`; `/me`
  quota parsed but never cached; `jobStatus` carries requested/delivered; fixed the old
  `status=='done'` bug (backend says `succeeded`).

**Also:** removed the synthetic-owner test data from the dev DB (1 collection, 8 terms, 1 request).

## Verified vs. code-only

| Item | How verified | Status |
|---|---|---|
| A3 backend (schema, port, job, /sync) | 241 tests; arch 0, stan clean; invariant-reviewer CLEAN | ✅ (backend) |
| Real Pexels attach on a real generation | live `generation:make` → cover + 8/8 terms imaged w/ attribution | ✅ (real Pexels + gpt-4o) |
| v4 no A1 regression + img% 100% | real eval, 25 prompts (`docs/generation-eval-v4.json`) | ✅ (real-LLM, single run) |
| Part B client (create/pending/images/badges) | `flutter analyze` clean; 23 widget/unit tests green | ⚠️ **code-only — NOT on device** |
| Anything on the **device** | nothing this session ran on a client | ⚠️ **device run pending (the one open step)** |

## Decisions that must not be silently revised (this feature)

- **All client reads come from the local DB** (drift v5; image fields present). The network is used
  ONLY for `POST /generations` and status polling — nothing else in Part B hits the API on a read.
- **The pending-generation card lives in a drift table** (`PendingGenerations`), survives an app
  kill, and is reconciled on launch/resume — never held only in memory.
- **`image_api_prompt` is server-internal** — never shipped in `/sync`, never on the client.
- **Images cached per term globally, never overwritten**; empty result = null (no retry); transient
  = job retry+backoff; image schema gated to v4+.
- **Language lives on the collection** — the create screen's target-language dropdown is the first
  UI language choice; no workspaces.
- **Size is a feel, not a number** — маленькая/средняя/большая → 10/15/22, decided server-side.
- **`/me` generation quota is fetched fresh, never cached** in the persisted user (staleness); the
  server is the real gate — an offline/unknown quota still lets the user try.
- **`resets_at` is an absolute UTC instant**, rendered in device-local time.
- (carried) A2 cache stores the final accepted set; spend summed; prompt vN = versioned file +
  eval-compare before flip; client tolerates unknown term types (phrase-like); sync cursor in
  `sync_meta` not keychain; `since` inclusive; process rules change in `.claude/`.

## What's next — device end-to-end run (the finish line)

Run `flutter run --release -d 00008110-000A7CCC3492801E` and walk these scenarios (each maps to code
that is currently only test-verified):

1. **Generation end-to-end with images** — create a collection ("иду в банк"); the pending card
   shows generating → ready with a real cover; open it; term photos + attribution appear as `/sync`
   lands them (screen updates from the drift stream, no reload); tap an author link → opens Pexels.
2. **Under-delivery** — a prompt the model under-fills; ready card shows "получилось N из M".
3. **Kill during generation** — start a generation, kill the app before it finishes; relaunch → the
   pending card is still there and reconciliation resumes polling → ready (or error).
4. **Quota exhausted** — after the daily limit, the create button is grey with remaining +
   resets_at in local time; submit is blocked client-side.
5. **Offline view after sync** — with the collection synced, go offline; the collection, its words
   and images (cached) still open and render from the local DB.
6. **TTS on a non-standard target language** — generate with a non-en target; the speaker button
   pronounces in that language (`ttsLocaleFor`).

## Known limitations / deferred

- **Whole Part B is unverified on device** — the above run is the gate.
- Study/session cards still come from the network (online-only, pre-existing) and don't show term
  photos — images are on the drift-backed screens (collection tile + word card) by design.
- New client dep **`url_launcher`** (image attribution links). iOS opens https externally without
  Info.plist changes.
- A3 server findings (carried): cache-path collection covers are re-searched (one extra Pexels call
  per cache hit) rather than copying the source URL — accepted.
- (carried) A2 top-up unobserved on the real model; two-device `client_seq` collision; stale reviews
  upload pipeline; triage-after-reinstall resurrection; stale offline streak/reviews-today; orphan
  local terms/progress not GC'd; `/study/progress` field-name mismatch (endpoint unused by the app).

## Running / verifying
- Backend2 in Docker (`wt_app` :8001, `wt_db` :5433). Gates: commit hook runs `composer check`
  (arch+stan+test) for backend, `flutter analyze` for mobile. **`composer stan` analyzes `app/` only.**
- `generation:eval [--fake] [--prompt=vN] [--out=path]` — manual quality gauge (real driver costs
  money). Baselines: `docs/generation-eval-v3.json`, `docs/generation-eval-v4.json`.
- Image search: `IMAGE_DRIVER=fake` + `PEXELS_FAKE_MODE=found|not_found|rate_limited|transient_error`
  exercises the attach job with no network. `PEXELS_API_KEY` is set for real runs.
- Mobile: `flutter analyze` clean; drift codegen `dart run build_runner build`; `flutter test`
  (23 tests). Device: `PATH="/opt/homebrew/bin:$PATH" LANG=en_US.UTF-8 flutter run --release -d 00008110-000A7CCC3492801E`.

---

## Client redesign — Session A (paper/ink design system)

Parallel track to the generation work above. Rebuilds the Flutter client to the
«Слова» design (source of truth: `backend2/docs/design/tokens.html`; frames:
`phase2-screens.html`). **Functionality is unchanged** — triage, sync, offline,
voice, contracts all stay. Sessions A and B don't touch each other's files; B's
contract changes (B2/B4/B5) are additive and A wires them in when ready.

**A1 — design-system foundation: DONE (code-verified).** `mobile/lib/theme/`
(colors, geometry, shadows, ink_density, motion, typography, haptics, theme
barrel + `buildAppTheme()`). Fonts **bundled** offline-first in
`mobile/assets/fonts/` — Literata 400/400i/500, Inter 400/600/700/800,
instanced from the OFL variable fonts to exactly the token weights (opsz axis
kept), + `OFL-*.txt`. Icons: `lucide_icons_flutter` (the older `lucide_icons`
caps at Dart <3.0 and can't resolve). Hex-guard test
(`test/theme/no_hex_outside_theme_test.dart`) fails on any colour hex outside
`lib/theme/`; a 3-file legacy allowlist (`lib/core/design|glass|theme.dart`)
must shrink to empty. `flutter analyze` clean; `flutter test` green.
Note: Literata has **no IPA** — transcriptions render in Inter (per token-list
§2/§4г), which covers full IPA + Cyrillic. Device font-render check
(`/wɪðˈdrɔː/`, «Банк и платежи») is deferred to the A3.1/A3.2 device runs.

**A2 — base components (`lib/ui/`): DONE (code-verified).** `mobile/lib/ui/`
+ barrel `ui.dart`: PaperCard, PrimaryButton/QuietButton, AppChip +
ChipWrap/ChipScrollRow (rule 16 — never clipped), AppBottomSheet
(+`showAppBottomSheet`), CenterAlert (+`showCenterAlert`, 274/destructive text),
FloatingContextMenu (**custom** overlay, not Cupertino — anchor/scrim/destructive-
last/tap-outside per §4в), FloatingTabBar (blur+saturate glass pill),
ProgressLine (min-width 8), InkSegments (3 densities), MiniFlag (rule 14; painted
en/de/es/fr/pt + neutral fallback; flag palette in `lib/theme/flag_palette.dart`),
VerdictButton (rule 20 — fill only «Не знаю»). Widget tests for the logic-bearing
ones (chip wrap/no-clip, verdict fill-only-unknown, ink-segments proportions,
context-menu open/close/destructive-last, progress-line clamp). `flutter analyze`
clean; `flutter test` **45 green**. Not yet wired into any screen or `main` — that
begins in A3. New theme tokens added for components: `AppColors.menuScrim/.faintInk/
.dividerFaint`, `flag_palette.dart`.

**A3.1 — Триаж reskin: DONE (DEVICE-VERIFIED 2026-08-06).**
`features/training/triage_screen.dart` rebuilt on the paper/ink system; behaviour
layer untouched (`revealed`, first-event latency, durable queue, `client_seq`,
deck-invalidate-on-entry, connectivity flush — all as before). Flip card on
`PaperCard` + tokens (front = term Literata 46; back = photo + перевод + пример +
транскрипция + type badge; rotateY 280 мс ease-out with midpoint darkening; reduced-
motion → instant swap). Swipe visuals in a new pure, unit-tested helper
`features/training/triage_swipe.dart` (`TriageSwipe`): tilt ±6°, verdict sign +
≤16 % tint on the staying side with opacity ∝ displacement, threshold 32 % width /
fling 600 px·s, spring-back (easeOutBack ≈ spring). `VerdictButton`s (fill only «Не
знаю»); «Отменить последний» tertiary; teaching hint on the first 3 cards **of this
session** (not persisted «first session ever» — noted). Header = back chip +
collection name + «N из M» + `SessionSegments` (§2б, new widget). Summary/empty
states reskinned (monochrome §2б). All copy via `AppLocalizations`. `flutter analyze`
clean; `flutter test` **65 green**; hex-guard + cyrillic-guard green (triage no longer
on either allowlist). **Device-verified** on the iPhone: front (term wraps, hint,
buttons — fill only «Не знаю»), back (full-width photo, term, **IPA transcription
renders** — this also closes the deferred A1 font-render check: IPA-in-Inter +
Literata + Cyrillic all render on device — badge, translation, example), summary
(monochrome tallies), header segment bar. **One device-only bug found & fixed:**
`SessionSegments` collapsed to height 0 (Row cross-axis defaulted to center, so the
zero-child `ColoredBox`es had no height) → `CrossAxisAlignment.stretch`; the widget
test now asserts segment height, not just colour. Re-run confirmed the bar renders.

**A3.2 — Главная reskin: DONE (DEVICE-VERIFIED 2026-08-06).** `features/
training/training_home_screen.dart` + `features/home/home_screen.dart` rebuilt on
the paper/ink system (кадр 2.1, both states). Everything reads the **local DB** —
renders in airplane mode. Blocks (rule 10 order — «Повторить» first): daily goal +
streak dots · state-dependent primary action · generation card · «Слово дня» ·
collections strip.
- **CTA** (`features/home/home_cta.dart`, pure + tested): due → «Повторить N»
  (session), else untriaged → «Разобрать N» (triage the most-eligible collection),
  else words exist → practice, else none (new user). All counts local
  (`statsProvider` + `watchUntriagedByCollection`).
- **«Слово дня»** (`features/home/word_of_day.dart`, pure + tested): deterministic
  by UTC day-number from local terms, hidden when none. No endpoint. **Level-
  proximity deferred** — local terms carry no CEFR to bias on.
- **Generation card**: antiqua title + faux field (mic + submit) + example chips →
  open `GenerateScreen` (chips prefill via new optional `initialTopic`). **Voice-fill
  deferred** (opens the create screen; STT lands with it).
- **Collections strip**: horizontal, bleeds to edges, photo covers (monochrome
  placeholder when no cover); «N из M слов» uses `mastered`/total; «Все» → Collections tab.
- **`FloatingTabBar`** wired in the shell; content scrolls under the pill with a
  bottom inset. **3 tabs** (Главная/Коллекции/Профиль) — the кадр's 4th «Прогресс»
  tab arrives with its screen (later A3.x). Collections/Profile tabs are still the
  old dark theme until their own A3.x reskin — expect visual mixing under the pill.
- New DB reads: `watchAllTerms`, `watchUntriagedByCollection` (both local/reactive).
  ARB ru+en extended (home keys, incl. ICU plurals). `flutter analyze` clean;
  `flutter test` **77 green**; hex-guard + cyrillic-guard green (both home files off
  the cyrillic allowlist → 15 legacy files left). **New-user «Готовые наборы» (store)
  strip omitted** — it needs the network store listing; out of the offline-first home
  scope, noted. **Device-verified** on the iPhone: goal + streak dots, review CTA
  («Повторить 4 слова» with due-collection names), generation card (Literata title,
  field, chips), «Слово дня» (`stretching`, IPA renders), collections strip (photo +
  monochrome placeholders, bleed), floating tab pill. No bugs found on the run.

**A3.3 — Экран коллекции reskin: DONE (code-verified; DEVICE RUN BATCHED to after A3.8).**
`features/collections/collection_detail_screen.dart` rebuilt on the paper/ink system
(кадр 2.3). All reads local (offline). Cover photo header (monochrome placeholder when
none) + back chip · title (Literata 30) + «N слов · M к повторению сегодня» · three
**ink-density segments** (`InkSegments.fromCounts`) + legend · state-dependent primary
action · word list · inline «Добавить слово».
- **Density** (`collectionDensityProvider` + pure `classifyDensity`, tested): confirmed
  (mastered) · familiar (in SRS, not mastered) · in-progress (new) — partitions total.
- **CTA** (`features/collections/collection_cta.dart`, pure + tested): **triage first**
  («приоритет №1»), then review, then quiet **outline** practice, else none. Counts
  local (`untriagedByCollectionProvider` + `collectionsProgressProvider`).
- **Word row**: thumb + Literata term + type badge (incl. идиома/фраз. глагол) +
  secondary translation + slashed transcription + right-axis speaker (existing
  `Pronouncer`, kept). **Swipe-left** → inline Изменить/Удалить (custom slidable, snaps
  open/closed); **long-press** → `FloatingContextMenu` (Изменить/Удалить, **no
  «Озвучить»** — rule 18). **Delete** → `CenterAlert` confirm (rule 15). Edit/add →
  existing `word_edit_dialog` (old theme until A3.4).
- `watchUntriagedByCollection` promoted to `untriagedByCollectionProvider` (home uses it
  too). ARB ru+en extended. `flutter analyze` clean; `flutter test` **84 green**;
  hex/cyrillic guards green (collection_detail off the cyrillic allowlist → 14 legacy
  left). **«Сортировка»** omitted (a feature, not reskin) — noted.

**Device-batch checklist (accumulating; run after A3.8):**
- **A3.8 выбор из четырёх (12a):** открой сессию повторения → карточка multiple_choice: фото (если у
  термина есть), промпт по-русски, 4 варианта Literata. Верный → шалфейное подчёркивание рисуется
  слева-направо + галка, хаптика success. Неверный → терракотовое подчёркивание + × на выбранном,
  галка на верном, хаптика warning. «Дальше» ведёт к следующей карточке (слайд справа).
- **A3.8 сборка фразы (12b):** карточка word_bank (многословный ответ) → чипы внизу, среди них
  **дистракторы-частицы** (up/in) с бэкенда. Тап по чипу — летит в строку пружинкой + тик-хаптика,
  на месте остаётся выцветшая копия; тап по слову в строке возвращает его вниз; «Дальше» собирает.
- **A3.8 ввод (12c):** typing-карточка → поле ввода **антиквой**, клавиатура поднимается сразу;
  «Подсказка: первая буква» подставляет первую букву (used_hint); опечатка в одну букву («withdrow»)
  принимается → «Почти: withdraw»; «Не помню» → сразу показывает верный ответ (честный провал).
- **A3.8 фидбек (12d):** после ошибки — правильная форма «пишется сама» (буквы по очереди),
  автопроизношение звучит (если «Автопроизношение» вкл. в профиле), фото + транскрипция + пример;
  строка «Увидишь снова через N дней» появляется, когда серверный `due_at` долетит через sync
  (онлайн; офлайн строки нет — сервер единственный планировщик, клиент интервал не считает).
- **A3.8 итог (12e):** после последней карточки — «Сессия закончена», счётчики Повторено/Новых/Ошибки;
  блок дневной цели (сегодня/цель + стрик, «закрыта» при достижении — **число обязано совпадать с
  Прогрессом**, оба из `daily_activity`); список слов с иконками результата + относительным сроком;
  блок «Проседает: {term}» → **«Новый пример»** → пример перегенерится (B6) либо «лимит исчерпан» при
  429; «Готово» выходит.
- **A3.8 тренировка (12f):** зайди в «Свободную тренировку» (practice) → тихая плашка «Тренировка —
  прогресс не меняется» сверху, закрывается крестиком и не возвращается; ответы НЕ двигают интервалы
  (`is_practice`), в итоге **нет** блока дневной цели.
- **A3.8 выход (12k):** крестик в шапке / системный «назад» → алерт «Прервать сессию?» (Продолжить —
  дефолт, Выйти — терракотовый текст); Выйти закрывает сессию, отвеченные слова сохранены.
- **A3.8 аудирование/пропуск (12g–12j):** собраны, но включаются **конфигом режимов на бэкенде**
  (сейчас сервер шлёт только phase-1: multiple_choice/word_bank/typing). Если listening/cloze не
  приходят — отметить «сервер не включил», проверить нельзя. Если включат: listening — слово звучит
  при появлении (без текста), круг-кнопка пульсирует и повторяет; cloze — пример курсивной антиквой с
  пропуском по ширине слова, перевод серым.
- **A3.8 проводка /reviews/batch (сырой ответ + client_seq):** ответь на несколько карточек онлайн →
  прогресс/стрик/Прогресс-экран обновляются после sync; **убей приложение офлайн посреди сессии** →
  ответы не потеряны, досылаются при возврате сети; порядок фолда — по `client_seq` из `seq_review`.
- **A3-close (тема/локализация):** приложение целиком на **бумажной** теме (нет тёмных экранов —
  `lib/core/` удалён, `main` на `buildAppTheme()`); в Профиле **нет** debug-карточки диагностики;
  Профиль → Язык интерфейса → **English** → UI становится английским (en теперь в `kSupportedLocales`);
  шрифты Literata/Inter + IPA рендерятся из bundled `assets/fonts/` (`google_fonts` удалён); экран
  входа: ошибки входа (офлайн/отмена/Apple недоступен) показываются локализованным текстом.
- **A3.6 Progress screen:** do a review/practice session, then open Прогресс — «Повторений сегодня»
  and «За неделю» tick up and the month chart shows today's bar, all **agreeing with the streak dots**
  beside them (triage must NOT move them). Streak reads big in Literata oldstyle; density bar + legend
  sum to «Все N слов». Empty on a brand-new install (accrues from now), then fills.
- **A3.6 empty states:** finish the daily goal with nothing due/untriaged → the home shows «На сегодня
  всё» (Свободная тренировка runs a practice session; «Собрать новую коллекцию» opens create). Toggle
  airplane mode → the quiet offline banner appears and the generation card goes dashed «Генерация
  недоступна без сети»; tapping it still queues the prompt (A3.5), which sends on reconnect.
- **0b home entrance:** on the home generation card, tapping the field / the arrow / a chip opens the
  create screen with the chip text carried and the field focused (cursor at end); the home never
  generates. The mic opens create already recording.
- **A3.7 вход (10a):** paper login renders «Слова» + tagline; **Google** sign-in works end-to-end;
  **Apple** button shows and, on tap, fails gracefully with a clear message (no crash) — it's blocked
  on `/auth/apple` + a paid Apple team (check the message copy reads sensibly). Offline → the hint row.
- **A3.7 онбординг (10b–10d):** first launch after sign-in → 3 steps, «Далее» active on every step
  (defaults preselected: en / B1 / 20); «Начать» persists the profile and lands on the home.
- **A3.7 профиль (11a/13a):** all rows render; edit Уровень / Дневная цель / Язык изучения via their
  sheets and confirm the value updates (needs network — `PUT /profile`); **Язык интерфейса** sheet
  (Системный/Русский/English) — RU vs Системный switch the UI; English still resolves to Russian
  (kSupportedLocales gate — expected). **Напоминания** toggle reveals/hides the **Время** row; the
  wheel sheet saves 24h/15-min time; **Автопроизношение** toggle persists. Both survive an app kill.
- **A3.7 удаление аккаунта (11b):** «Удалить аккаунт» → the alert shows the real local numbers
  («146 слов, 12 дней стрика»); confirm → `DELETE /auth/me`, local wipe, back to the login screen.
- Collection screen — **scroll the word list down: is the status bar readable once
  the cover scrolls off the top?** If the cover doesn't scroll past the top, close by
  observation. If it does and the glyphs are light-on-paper, add a scroll-driven
  overlay-style switch (dark once the cover clears the status bar) then.
- **A3.5 offline prompt-queue (⚠️ the critical scenario):** with the app offline, create a
  generation ("иду в банк") → a «Собираем…» card appears on the Collections tab with the
  «Отправим, как только появится сеть» note; **kill the app and relaunch still offline** →
  the card is STILL there (not dropped as a ghost — it was never sent, so it must not 404-drop);
  turn the network on → it sends, polls, and lands as a ready collection. Then, while a just-
  generated collection is on screen, **pull-to-refresh** (full-snapshot resync) → the collection
  is NOT reaped (pending-referenced exclusion). Unit-covered in `test/data/generation_queue_test.dart`,
  but confirm on device.
- **A3.5 voice fill (кадр 6c):** the field mic and the home-card mic both grant the two iOS
  permissions (microphone + speech recognition — check the Info.plist prompt copy reads sensibly),
  transcription streams INTO the field in the UI language, «Стоп» returns the keyboard for manual
  edit, and voice never fires «Сгенерировать».
- **A3.5 create screen:** quota line (remaining / exhausted with a device-LOCAL reset time), greyed
  button + Premium row (dead-ends for now, paywall behind a flag) + «Собрать вручную» at zero, size
  chips, level multi-select, language dropdown with MiniFlag defaulting to the profile («по
  умолчанию» until an explicit pick; B4 omits `target_lang` while it's the default).

**Home fixes folded in after the A3.2 live screenshot (verify in the device batch):**
(1) streak dots locked to **exactly 7** (a week) via a pure, tested `streakDots`
(`features/home/streak.dart`) — the widget loop was already bounded to 7, now
regression-tested; (2) **status-bar glyphs set dark on the paper screens.** The one-shot
`SystemChrome` call in `main()` was invisible on device — the app's global theme is
still the old **dark** `buildTheme()`, whose per-frame default forces light glyphs
and overrides it. Fixed with an authoritative **`AnnotatedRegion<SystemUiOverlayStyle>`**
(`.dark`) wrapping the reskinned paper screens (home, triage); the collection cover
wraps `.light` for white glyphs over the photo. `main()` keeps the call as an Android
baseline. (Old dark tabs keep their own light glyphs until their A3.x reskin.) (3) the **«Прогресс» tab** is deliberately
absent until A3.6 (recorded in A3.6 acceptance above — returns between Коллекции и
Профиль).

**Session-A cadence (per the user):** reskin the ordered items **without stopping for
device runs**; `/report` per closed item; the device run is a **single batch after
A3.8**. Session boundaries by context (~85 %): stop on a whole item, `/handoff`, resume.

**A3.4 — Editing surfaces reskin: DONE (code-verified; DEVICE RUN BATCHED).** Кадры
2.3b. `word_edit_dialog.dart` rebuilt as a paper bottom sheet (`showAppBottomSheet` /
`AppBottomSheet`, rule 08): title (Добавить/Изменить слово) · **Термин** (Literata
input) + **Перевод** (optional, placeholder «необязательно — подберём сами») · auto-
derive helper · `PrimaryButton` (Добавить в коллекцию / Сохранить) · edit-only
**«Удалить из коллекции»** terracotta link → caller's confirm alert. Behaviour kept:
add = `addWord`; edit = remove-old + add-new (backend2 has no word-update); `sync()` after.
- **Delete-word alert** copy matched to кадр 5d («Удалить «{term}»?» / «Слово останется
  в других коллекциях, прогресс сохранится.»).
- **Collection ⋯ menu** on the cover (кадр 5e), `FloatingContextMenu`: **own** variant
  — Переименовать (→ existing `collection_edit_dialog`, old theme until A3.5) + **Удалить
  коллекцию** (`CenterAlert` confirm → `deleteCollection` + `sync` + pop). Speaker in the
  edit-sheet header omitted (TTS-defer, same line as elsewhere).
- **Deferred (noted, into A3.5):** the **shared-collection** menu variant («Создать свою
  копию» / «Убрать из моих») + **«Сменить обложку»** — no fork/unsubscribe API and no
  store→detail entry yet (this screen is own-collections only); they land with the store
  flow. `word_edit_dialog` off the cyrillic allowlist → **13 legacy left**. `flutter
  analyze` clean; `flutter test` **88 green**; both guards green. **Not on device** (batched).

**Bug fixed (device): ghost collections — «backend deleted it, front didn't».** Two
layers:
1. **New deletes** drop the row **optimistically** the moment the server delete succeeds
   — `AppDatabase.deleteCollectionLocal(id)` (collection + its `collection_items`, txn),
   called from the collection screen (`_confirmDeleteCollection`, try/catch so a network
   failure keeps the row) and the old collections tab. `sync()` still runs after.
2. **Existing ghosts** (collections removed server-side with **no tombstone** — a dev-DB
   reset / hard delete; the backend's incremental `since`-feed can't report them, and the
   sync reader is otherwise correct — it *does* emit soft-delete tombstones). Fix:
   **full-snapshot reconcile** — `SyncService.resync()` clears the cursor → next `sync()`
   pulls a full snapshot (authoritative live set) → `AppDatabase.reconcileCollections(keep)`
   reaps any local collection not in it. **Pull-to-refresh on the home now calls
   `resync()`**, so the gesture clears ghosts. ⚠️ When offline collection creation lands
   (A3.5, client ULID pre-upload), `reconcileCollections` MUST exclude not-yet-synced rows
   or it will reap them — noted in the method doc. (Word removal uses the tombstone path;
   no ghost reported there — item tombstones flow.)

**A3.5 — collections tab + create + B-tails: DONE (code-verified; DEVICE RUN BATCHED).** Кадры
2.4/2.5/6a–6c. Collections tab ([collections_screen.dart](../../mobile/lib/features/collections/collections_screen.dart))
rebuilt on paper: title + чернильный «+» (→ create), flat rows with 96px cover, Literata title,
«N слов · освоено M», three ink-density segments, review/triage hint; long-press → own-collection
menu. Generation states inline ([pending_generation_card.dart](../../mobile/lib/features/collections/pending_generation_card.dart)):
Ticker shimmer «Собираем… обычно 20–30 секунд», error «Генерация не потрачена» + Повторить/Скрыть,
ready with a контурный недобор-бейдж «13 из 15». Create screen ([generate_screen.dart](../../mobile/lib/features/collections/generate_screen.dart))
rebuilt: situation field + rotating placeholder + **voice fill** (`speech_to_text`, recognition
locale = UI language, transcription into the field, «Стоп» → manual edit, voice never generates;
home mic opens create already recording; two Info.plist perms added), size feel, level multi-select,
language dropdown with `MiniFlag` (default from profile, «по умолчанию» until an explicit pick),
docked footer with quota (device-local reset) + greyed button + Premium row (paywall behind a flag,
dead-ends) + «Собрать вручную».
- **B2** — client ULID on `POST /generations` + **durable offline prompt queue** (PendingGenerations
  gained `sent` + `targetLangExplicit`, schema **v6**): [generation_controller.dart](../../mobile/lib/data/generation_controller.dart)
  enqueues, re-sends on network return / resume / launch, retry = 200 existing.
- **B4** — omit `target_lang` while it's the profile default (server fallback).
- **B1** — «Добавить слово» sends WITHOUT translation when empty ([word_edit_dialog.dart](../../mobile/lib/features/collections/word_edit_dialog.dart)
  + `addWord` drops the key).
- **⚠️ Critical (offline ghosts), two guards + `test/data/generation_queue_test.dart`:** an un-sent
  (offline) generation is **never polled** → never 404-dropped; `reconcileCollections` **excludes**
  collections a pending generation still references → a full-snapshot resync can't reap a just-
  generated collection. `flutter analyze` clean; `flutter test` **96 green**; hex + cyrillic guards
  green (3 screens off the cyrillic allowlist).

**A3.6 — progress + empties, 2.6–2.7: DONE (code-verified; DEVICE RUN BATCHED).** Earlier this
track: **`collection_edit_dialog`** reskinned to a paper `AppBottomSheet` (name-only; legacy emoji
picker dropped). This session closed the rest:

1. **Progress screen** ([progress_screen.dart](../../mobile/lib/features/progress/progress_screen.dart),
   кадр 2.6) — all reads local (renders offline): streak in Literata 40 **oldstyle** «N дней подряд»
   (`statsProvider.streakDays`) + «Лучший результат — N дней»; week calendar of 7 dots Пн–Вс
   (past-with-activity filled · today outline · future/empty track); three **tabular** counters
   «Выучено всего»/«За неделю»/«Повторений сегодня»; month activity chart of ink bars (filled/
   halftone/outline by share of the busiest day); global density bar + legend «Все N слов»
   (`globalDensityProvider` folds `classifyDensity` over **all terms**).
   - **Data:** new client-only drift table **`DailyActivity(day PK, reviews)` — schema v7**, `day` =
     local `YYYY-MM-DD`. Bumped **only** in `ReviewSync.record` (session + free practice; **triage
     excluded** — правило 21, so the chart converges with the streak dots). Accumulates from now, no
     backfill (ROADMAP note added under the offline-mode deferred list). Pure derivations in
     [activity.dart](../../mobile/lib/features/progress/activity.dart) (weekDots / monthBars /
     weekReviewCount / todayReviewCount) — unit-tested.
   - **DECISIONS:** «Выучено всего» = **mastered** (per the prior handoff note; it's the headline
     achievement, the density legend below breaks it down — flag if you'd rather it be `learned` or
     total). «Лучший результат» = a **local running max** of the observed streak (`SyncKeys.bestStreak`,
     updated in `_refreshStatsCache`) — there is no server field; hidden until >0. «Повторений сегодня»
     reads `DailyActivity`, **not** the server `reviews_today` cache, so it agrees with the chart.
2. **«Прогресс» tab back** in [home_screen.dart](../../mobile/lib/features/home/home_screen.dart) —
   order Главная / Коллекции / **Прогресс** / Профиль (`LucideIcons.barChart3`). Acceptance met.
3. **Empty states (кадр 2.7):** **9b «На сегодня всё»** — home card (Свободная тренировка +
   «Собрать новую коллекцию») shown when the goal is met and the CTA has fallen to practice
   (`allDone` in [training_home_screen.dart](../../mobile/lib/features/training/training_home_screen.dart)).
   **9c offline** — a quiet outline banner + the generation card's **dashed** variant (new
   `DottedBorderBox` in `lib/ui/`, `AppColors.dashed` token); the prompt still queues (A3.5).
   **9a first-run «3 стартовые коллекции» → deferred to A3.9** (needs the store/starter listing, B5).
   - `flutter analyze` clean; `flutter test` **106 green** (new: 9 pure activity tests + a Progress
     render smoke test); hex + cyrillic guards green (all new files route copy through
     `AppLocalizations`; only comments carry Cyrillic).

**A3.7 — auth / onboarding / profile, 2.9–2.10 + 13a-b: DONE (code-verified; DEVICE RUN BATCHED).**
All paper/ink, copy via `AppLocalizations`, 108 tests green.
- **Вход** ([login_screen.dart](../../mobile/lib/features/auth/login_screen.dart), кадр 10a):
  «Слова» Literata wordmark + tagline; **Sign in with Apple** via the package widget
  (`sign_in_with_apple`, new dep) + a **guideline Google** button (four-colour G painted from an
  isolated `lib/theme/brand_palette.dart`); Условия · Конфиденциальность; offline hint.
- **Онбординг** ([onboarding_screen.dart](../../mobile/lib/features/onboarding/onboarding_screen.dart),
  10b–10d): 3 steps, each pre-defaulted so «Далее» is always active — target language (en default,
  native ru not asked), level grid (B1 default), daily goal 10/20/30 (20 default). Finish →
  `updateProfile` + `setOnboarded`.
- **Профиль** ([profile_screen.dart](../../mobile/lib/features/profile/profile_screen.dart), 11a/13a):
  header (initials avatar) · Обучение (Уровень/Дневная цель/Язык изучения → bottom-sheet editors →
  `PUT /profile`) · Приложение (**Язык интерфейса** → `LocaleController` sheet Системный/Русский/
  English; **Напоминания** toggle; **Время** row slides in when on → wheel sheet; **Автопроизношение**
  toggle) · Подписка (Бесплатный тариф · Скоро) · Аккаунт (Выйти; **Удалить аккаунт**).
- **Удаление аккаунта** (11b): `CenterAlert` with **personalised local numbers** (`{words}`,
  `{streak}` from `statsProvider`) → `AuthController.deleteAccount()` → `DELETE /auth/me` (B3) +
  `clearAll` + drop to signed-out.
- **Local settings** ([app_settings.dart](../../mobile/lib/data/app_settings.dart)): reminders
  enabled/time + auto-pronounce in drift `sync_meta` (never synced). The old dark
  `settings_screen.dart` was **deleted** (folded into profile rows); the sync-diagnostics debug card
  moved to its own file (`sync_diagnostics_card.dart`, still on the cyrillic allowlist — debug-only,
  dies with `lib/core/`). Cyrillic allowlist now: `word_media`, `session_screen`, `preview`,
  `sync_diagnostics_card` (4 left).
- **Решения без согласования (A3.7):** (1) **Apple-вход** wired client-side (`signInWithApple` →
  `appleLogin` → `POST /auth/apple`) but **blocked** on the backend endpoint + a paid Apple team
  capability — Google works, Apple surfaces a clear message (ROADMAP). (2) **Reminders = stored
  preference only**; OS scheduling (2.12 pre-permission 13c/13d + notifications plugin) deferred
  (ROADMAP). (3) **English still not in `kSupportedLocales`** (A3-close gate) — picking English
  persists the override but resolves to ru for now. (4) Google «G» is a painted approximation, not the
  pixel-exact brand asset. (5) `settings_screen.dart` deleted.

**A3.8 (start here)** — exercise-session screens (кадры 12a–12i, Фаза 2.11 + 2.11-доп at
`phase2-screens.html` lines 886–1176 and 640–836; итог 8b–8d at 1254–1534): the biggest item.
Session shell + 3 phase-1 modes; in-card feedback («пишущиеся» буквы, автопроизношение via the new
`AppSettings.autoPronounce`, «увидишь через N дней»); session summary with **«Новый пример»**
(wire B6 `POST /terms/{id}/regenerate-example`, swap in place, handle 429); practice banner (кадр
12f — «прогресс не меняется»); exit-with-confirm; listening/cloze behind a mode config. Wire
`/reviews/batch`: raw answer + `client_seq` (`SeqCounter.review` ready) — **the client check is
never stricter than the server** (invariant). Motion §4е, haptics. NB `ReviewSync.record` already
bumps `daily_activity` + the streak, so a real session now feeds the Progress screen. Then the
A3.1–A3.8 device batch + the A3-close checklist below (`app_en.arb` complete + **English into
`kSupportedLocales`**, delete `lib/core/`, empty both guard allowlists, remove `google_fonts`).

### A3 acceptance (definition of done for the whole A-block)

By the end of A3, all of these must hold (checked at A3 close):
1. `mobile/lib/core/` (the old dark theme: `design.dart`, `glass.dart`,
   `theme.dart`) is **deleted**.
2. The hex-guard allowlist in `test/theme/no_hex_outside_theme_test.dart` is
   **empty** (no legacy files remain).
3. The **cyrillic-guard** allowlist in
   `test/l10n/no_cyrillic_outside_l10n_test.dart` is **empty** — every reskinned
   screen routes its copy through `AppLocalizations` (no Cyrillic string literals
   outside `lib/l10n/`). No permanent guard holes: endonyms moved to
   `lib/l10n/language_endonyms.dart` (language data, not copy — the guard skips
   `lib/l10n/`; `lib/core/languages.dart` is now a thin re-export + CEFR/TTS that
   dies with `lib/core/`), and the base components `lib/ui/center_alert.dart` /
   `floating_context_menu.dart` take **caller-supplied** labels (they know no
   languages). All three are already off the allowlist.
4. `app_en.arb` is complete and **English is added to `kSupportedLocales`** in
   `lib/data/locale_controller.dart` (today it ships `[ru]` only; choosing English
   resolves back to Russian until then).
5. `google_fonts` is **removed** from `mobile/pubspec.yaml` (it survives only as
   long as the old `lib/core/theme.dart` imports it).

**A3.0 — localisation scaffold: DONE (code-verified).** `flutter_localizations` +
gen-l10n (`l10n.yaml`, non-synthetic → generated into `lib/l10n/`). Source of truth
`lib/l10n/app_ru.arb` (triage copy verbatim from the frames, incl. an ICU plural
`triageRemainingAfterSync`); `app_en.arb` seeded with the triage keys, not yet in
`supportedLocales`. Locale resolution **override → device → ru** in
`lib/data/locale_controller.dart` (`LocaleController` AsyncNotifier; override stored
in drift `SyncMeta['ui_locale']`, never synced; `UiLanguageOption` = Системный/
Русский/English for the A3.7 profile row — mechanism only, the row lands then). Wired
in `main.dart`. Cyrillic-guard added (see #3). Backend push-locale note recorded in
ROADMAP (B7).

---

## Contract wiring map B→A (backend tails → client blocks)

Session B closed all backend tails (B1–B8, this branch; commits + full detail in
`docs/ROADMAP.md` → "Backend tails (B-series)"). Every B contract change is **additive** —
A wires each in when its block is ready. The `/api/v1` shapes are in `openapi/openapi.yaml`.

| B change | What A consumes | A block |
|---|---|---|
| **B2** — client-generated ULID `id` on `POST /generations`; re-send = **200** existing (no dup/quota), cross-user id = 409 | send a client ULID with each create so the durable offline prompt-queue can retry safely (backend is now idempotent) | **A3.5** (создание коллекции) — offline-очередь промптов |
| **B4** — `POST /generations` without `target_lang` falls back to `profiles.target_language` (then `en`) | the language dropdown defaults from the profile; omit `target_lang` to accept that default | **A3.5** (дропдаун языка: дефолт из профиля) |
| **B1** — `translation` is **optional** on `POST /collections/{id}/items`; when omitted the term is enriched async (перевод/транскрипция/пример/фото), arriving via a later `/sync` | show the «подберём сами» hint; allow submit without a translation; render the fields when sync lands them | **A3.4** (sheet «Добавить слово») |
| **B5** — `GET /store/collections` (public+system by lang pair, topic sections, `is_premium`/`is_subscribed`), `POST/DELETE /store/collections/{id}/subscribe` (idempotent; premium+free → 403 `subscription_required`), `profile.tier` in `/me` | store screen behind a feature flag; subscribe/unsubscribe from the library; **hide «Создать свою копию»** (fork is deferred — ROADMAP) | **A3.9** (стор за фичефлагом) |
| **B6** — `POST /terms/{id}/regenerate-example` → new example (+translation), replaces the stored one, returns it; counts against the daily quota (429 when exhausted) | the «Новый пример» button on the session summary; swap the example in place from the 200 body; handle 429 | **A3.8** (итог сессии, кнопка «Новый пример») |

Notes for A: `/me` now also carries `profile.tier` (free|premium) and the generation quota
block already used for the create button. Fork («Создать свою копию») is intentionally not
built — its decided semantics (copy `collection_items` refs to the same global `term_id`s,
never duplicate terms) are recorded in ROADMAP for whoever builds it.
