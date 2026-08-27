# mobile — Flutter/iOS app

The product: a personal English vocabulary trainer. **Cut over to `../backend2`** (modular
API, `/api/v1`, ULID ids, RFC 7807 errors) — see `../backend2/openapi/openapi.yaml` for the
contract. Auto-loads for sessions in `mobile/`. See root `../CLAUDE.md` for the whole project.

## Stack

Flutter 3.44 / Dart 3.12. Packages: `flutter_riverpod` (state), `dio` (HTTP),
`flutter_tts` (pronunciation), `google_sign_in` **v7** (new API: `GoogleSignIn.instance`,
`initialize()`, `authenticate()`), `sign_in_with_apple`, `flutter_secure_storage` (token in
Keychain), `flutter_animate`. Fonts (Literata + Inter) are **bundled** in `assets/fonts/`
(offline-first) — `google_fonts` was removed at the A3 close. `lib/core/` is **gone** (the old dark
theme); the «Слова» paper/ink design lives in `lib/theme/` (tokens) + `lib/ui/` (components).

## Structure (`lib/`)

- `theme/` — paper/ink design tokens (colors, typography, geometry, motion, haptics, shadows) + `buildAppTheme()`. `ui/` — base components (PaperCard, buttons, chips, InkSegments, FloatingTabBar, CenterAlert, …).
- `data/` — `models.dart`, `api_client.dart` (Dio + bearer token), `auth_repository.dart` (Google/Apple → backend token exchange, throws an `AuthError` code the login screen localizes), `config.dart` (API_BASE_URL + GOOGLE_IOS_CLIENT_ID via `--dart-define`), `languages.dart` (CEFR + TTS locale + endonym re-export), `pronouncer.dart` (system TTS), `token_store.dart`, `providers.dart`, the offline pipelines (`review_sync`, `triage_sync`, `pool_sync`, `seq_counter`, `local/app_database.dart` drift mirror).
- `features/` — `auth/`, `home/` (the tab shell + the daily-goal counter the session summary reads), `training/` (`training_home_screen.dart` = «Главная», кадры 17a–17d, `triage_screen.dart`, `session_screen.dart` + `session/` = the exercise session, A3.8), `collections/` (incl. `my_words_screen.dart` = «Мои слова», the pool), `progress/`, `onboarding/`, `profile/`.
- `l10n/` — `app_ru.arb` (source of truth) + `app_en.arb` (complete); both `ru` and `en` are in `kSupportedLocales`. All UI copy routes through `AppLocalizations` (guarded by `test/l10n/no_cyrillic_outside_l10n_test.dart`, allowlist now **empty**).
- `tool/preview.dart` — design preview harness with mock data: `flutter run -d chrome --target tool/preview.dart` (no backend/login needed). Lives outside `lib/` so its sample Russian data is exempt from the cyrillic guard.

## The pool («Мои слова») — the library is not the queue

A collection is a CATALOGUE of a topic; what the trainer works through is the learner's **pool**.
Membership is one nullable column on the mirrored progress row (`enrolled_at`), never a collection:

- a word joins the pool only by a deliberate act — a `не знаю` / `не уверен` triage swipe, «Учить
  это слово» on the word card (кадр 16e), or «Учить сразу» in the translator. Adding or generating a
  collection enrols nothing, and neither does plain **«Сохранить»**: saving from search files the
  word on a shelf and it then waits in the swipe pass (`POST /search/add` carries `enroll`; the
  two buttons are two acts, and the toast names which one happened).
- every **study** session is built from the pool — «Учить N», due repeats, the main-screen session.
- **free practice is open to EVERY word, always** — «Тренировать это слово» is never a grey button,
  and «Тренировка по теме» drills the whole collection, untriaged words included, so a topic is
  usable the moment it exists. It is allowed to be open because it moves nothing: no enrolment, no
  exposure, no quota, no rung, no schedule. **Only the planned session moves the ladder.** What the
  rung still decides is the CARD: a pair with no rung of its own — outside the pool, or in it and
  still at rung 0 — is dealt only what the matrix opens at `LearningLadder.stepUnenrolledPractice`
  (choice and assembly), never typing, listening or dictation, and never an intro. A pair on a rung
  of its own fans across every switched-on trainer. Pool words come first in the session.
  `LadderPosition.drillsAtOwnRung` asks that one question; the server mirrors it as
  `StudyCardAssembler::drillsAtOwnRung()`.
- **a word is a SCOPE of its own**: «Тренировать это слово» needs no collection («Мои слова» has none
  to give — the pool outlives folders), and the term list the session is built from stays wide, so a
  choice card still has neighbours to draw its wrong options from.
- «Убрать из изучения» is a PAUSE: `enrolled_at → null` and nothing else, so the word resumes at the
  rung and due date it left with. The wording says so, or the button reads as a delete.
- both taps ride a durable queue (`data/pool_sync.dart`) keyed by term and holding the DESIRED
  membership rather than a log — the verbs are idempotent and there is no order to protect.

Pinned by `test/data/pool_lifecycle_test.dart` (the device's half of the story) and the pool section
of `test/data/practice/ladder_parity_test.dart`; the server's half is
`backend2/tests/Feature/Learning/PoolApiTest.php`.

## «Главная» — one question, and the server answers it (кадры 17a–17d)

The main screen asks «что мне делать прямо сейчас и сколько это займёт», and everything on it is a
part of that answer. It reads ONE payload — `GET /home-plan`, cached into `sync_meta` by
`SyncService` and watched out of the local DB by `homePlanProvider`, like every other screen here.

- The server names the STATE (`plan` / `done` / `idle` / `empty` = кадры 17a / 17b / 17d / 17c). The
  client does not re-derive it: the composition on the card is what the session builder would deal,
  and two places deciding that separately is how a screen promises a session that comes back empty.
- **A block with no data is not drawn.** Not as a zero, not greyed out — absent. «0 слов» is not a
  sentence this screen says. Guarded by `test/features/home/home_plan_blocks_test.dart` against the
  keys in `HomeBlockKeys`.
- «Дневная цель» in the old sense is gone from here: the day's progress is ANSWERED CARDS
  («32 из 32»). The counter itself lives on — the session summary still shows it (`dailyGoalProvider`).
- Gone with the rewrite: «Слово дня» (`word_of_day.dart`), the collections carousel
  (`collections_strip.dart`) which duplicated the Collections tab, and `computeHomeCta` — the home
  no longer picks one verb for the day, it states the day's composition.

## Design

**«Слова» — paper/ink.** A light, typographic look: paper ground, ink type, hairline rules; no
gradients, no emoji decoration, **no dark theme** (the old dark UI died with `lib/core/`). Tokens
live in `lib/theme/`, components in `lib/ui/`. The source of truth for the theme is
`../backend2/docs/design/tokens.html`; where the token list and a screen frame disagree, the token
list wins.

The palette is deliberately close-valued, which has a QA consequence worth knowing: a fill can be
present in the code and invisible in a screenshot. «Выглядит пустым» is not a finding until it has
been measured with an eyedropper (`../docs/qa/PLAYBOOK.md`).

Training: triage is a swipe pass (**right = Знаю, left = Не знаю, up = Не уверен**, buttons too);
the session itself is server-assembled and server-graded, with a live progress monitor and a
summary at the end.

## Running on the iPhone — the exact recipe (and the gotchas)

Device: **iPhone (Denis)**, id `00008110-000A7CCC3492801E`, iOS 27 beta.

**Canonical build command — use this verbatim, every session.** No `--dart-define` needed: every
compile-time default in `lib/data/config.dart` is already correct for a dev build — `API_BASE_URL`
→ the stable backend2 ngrok, the Google client id is set, and the store/paywall/dev-menu flags
default **true**. Do NOT drop the flags with a bare rebuild — that is exactly how the store once
"disappeared" from a build.

```bash
cd mobile && PATH="/opt/homebrew/bin:$PATH" LANG=en_US.UTF-8 flutter run --release -d 00008110-000A7CCC3492801E
```

Override a single setting only when you must, e.g. `--dart-define=STORE_ENABLED=false` or
`--dart-define=API_BASE_URL=https://…`. (Release defaults for the flags are a `TODO(release)` — see
ROADMAP «релиз: решить дефолты флагов».)

**Use `--release`.** In debug the app shows a white screen / "Dart VM Service not discovered"
because the debug VM service needs the phone's Local Network permission; release sidesteps it.
The `PATH`/`LANG` prefix is required (Homebrew pod/toolchain first, UTF-8 locale) — see the pod gotcha below.

Hard-won gotchas (all already resolved once — needed again on a fresh machine/checkout):
- **Xcode 27 beta required** — host is macOS 27 beta; App Store Xcode 26.x won't launch (error -10664). Xcode 27 beta 3 is installed.
- **Signing** (one-time in Xcode → Runner → Signing & Capabilities): personal team "Solonina Denis" (Apple ID `haveystar95@gmail.com`), team id `7A5U4R66CB`, **bundle id `com.denis.engstd`**. Free personal team ⇒ app expires ~7 days; re-run to reinstall.
- **Developer Mode ON** on the iPhone (Settings → Privacy & Security → Developer Mode).
- **iOS deployment target ≥ 15.0** — set in `ios/Runner.xcodeproj/project.pbxproj` (3×) and `ios/Podfile` (`platform :ios, '15.0'`). Xcode 27 rejects 13.0.
- **CocoaPods**: use Homebrew's (`/opt/homebrew/bin/pod`, 1.17+). The old gem pod `/usr/local/bin/pod` (1.11.3) has a broken `ffi` and fails `pod install`; it was removed. Run flutter with `/opt/homebrew/bin` first on PATH and `LANG=en_US.UTF-8`.
- Google Sign-In needs the **REVERSED_CLIENT_ID URL scheme** in `ios/Runner/Info.plist` (already added, from `../credentials.plist`).
- `flutter_tts` warns it doesn't support Swift Package Manager — harmless.

## API config

The data layer targets **backend2** (`{API_BASE_URL}/api/v1`, bearer token, responses wrapped
in `data`, ULID string ids). The study surface is `POST /study/sessions` (self-contained package)
+ `POST /reviews/batch`; `GET /study/due` was removed on 2026-07-30 and is not a thing. Alongside
them: `GET /study/progress`, `GET /stats`, `GET /home-plan` (the main screen's whole day — see
«Главная» above), `/triage/*`, `/pool/terms/{id}`, `GET /sync?since=` +
`GET /sync/cursor`, `/collections/*`, `/generations`, `/search` + `/search/instant`. `lib/data/`
(`models.dart`, `api_client.dart`, `providers.dart`) was hand-adapted to the OpenAPI contract
(not codegen).

**The default in `config.dart` is correct — do not override it.** backend2 owns the ngrok tunnel:
its compose has a `ngrok` service (`wt_ngrok`) serving the static domain
`https://greedily-thermos-finer.ngrok-free.dev` straight at the backend2 `app` container, and that
same URL is `AppConfig.apiBaseUrl`'s compile-time default. So `docker compose up -d` in `backend2/`
is the whole "expose it" step, and the canonical build command above needs no `--dart-define`.
(backend2 also publishes `:8001` on the host — that is for a desktop browser/curl, not for the
phone.) Free ngrok plan = **one tunnel**: the old `backend/` claims the same domain, so don't run
both stacks' ngrok at once; on `ERR_NGROK_334`, `pkill -f "ngrok http"` and bring
`backend2`'s back up. `GOOGLE_IOS_CLIENT_ID` unchanged (`../credentials.plist`).

Gaps vs the old app (backend2 doesn't provide): per-item CEFR badge, collection emoji
(chosen locally, not persisted), word editing (done as remove+add), per-collection progress
on the training home (now shows word counts), and AI open-answer check.

## Verify without the phone

`flutter analyze` must be clean. `flutter test` runs the widget + unit tests.

For visual checks there are two harnesses under `tool/` (both outside `lib/`, so their Russian
sample copy is exempt from the cyrillic guard):

- `tool/preview.dart` — the app's own screens with mock providers.
- `tool/ladder_preview.dart` — the acquisition-ladder surfaces (кадры 16b/16d/16e): the intro card,
  the word row's five dots, the expanded word card.

Both run on the **iOS simulator** (runtimes 26.5 and 27.0 ARE installed — the old note claiming
otherwise was stale), or in Chrome:

```bash
PATH="/opt/homebrew/bin:$PATH" LANG=en_US.UTF-8 flutter run --debug -d <simulator-udid> --target tool/ladder_preview.dart
```

**`--debug`, not `--release`, on a simulator** — `flutter run --release` refuses with «Release mode
is not supported by <device>» (the release build is device-only). The `--release` rule in the
recipe above is for the PHONE and still stands there.
