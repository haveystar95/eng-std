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
- `data/` — `models.dart`, `api_client.dart` (Dio + bearer token), `auth_repository.dart` (Google/Apple → backend token exchange, throws an `AuthError` code the login screen localizes), `config.dart` (API_BASE_URL + GOOGLE_IOS_CLIENT_ID via `--dart-define`), `languages.dart` (CEFR + TTS locale + endonym re-export), `pronouncer.dart` (system TTS), `token_store.dart`, `providers.dart`, the offline pipelines (`review_sync`, `triage_sync`, `seq_counter`, `local/app_database.dart` drift mirror).
- `features/` — `auth/`, `home/`, `training/` (`training_home_screen.dart` dashboard, `triage_screen.dart`, `session_screen.dart` + `session/` = the exercise session, A3.8), `collections/`, `progress/`, `onboarding/`, `profile/`.
- `l10n/` — `app_ru.arb` (source of truth) + `app_en.arb` (complete); both `ru` and `en` are in `kSupportedLocales`. All UI copy routes through `AppLocalizations` (guarded by `test/l10n/no_cyrillic_outside_l10n_test.dart`, allowlist now **empty**).
- `tool/preview.dart` — design preview harness with mock data: `flutter run -d chrome --target tool/preview.dart` (no backend/login needed). Lives outside `lib/` so its sample Russian data is exempt from the cyrillic guard.

## Design

Dark, vibrant (reference-driven): deep navy bg, gradient accents, emoji on collections.
Training session: tap card to reveal; **swipe right = Знаю, left = Не знаю, up = Повторить**
(also 3 buttons). Live progress monitor (know/review/don't-know tallies + bar) + session summary.

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
in `data`, ULID string ids, `/reviews/batch`, `/study/due`, `/generations`). `lib/data/`
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
