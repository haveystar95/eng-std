# mobile — Flutter/iOS app

The product: a personal English vocabulary trainer. Talks to the **old `../backend`** API
(via ngrok) today. Auto-loads for sessions in `mobile/`. See root `../CLAUDE.md` for the
whole project.

## Stack

Flutter 3.44 / Dart 3.12. Packages: `flutter_riverpod` (state), `dio` (HTTP),
`flutter_tts` (pronunciation), `google_sign_in` **v7** (new API: `GoogleSignIn.instance`,
`initialize()`, `authenticate()`), `flutter_secure_storage` (token in Keychain),
`google_fonts` (Inter), `flutter_animate`.

## Structure (`lib/`)

- `core/` — `config.dart` (API_BASE_URL + GOOGLE_IOS_CLIENT_ID via `--dart-define`, with dev defaults), `design.dart` (dark tokens + gradients), `theme.dart`.
- `data/` — `models.dart`, `api_client.dart` (Dio + bearer token + `ngrok-skip-browser-warning`), `auth_repository.dart` (Google → backend token exchange), `token_store.dart`, `providers.dart` (Riverpod: auth, stats, collections, sessionCards).
- `features/` — `auth/` (Google login), `home/` (bottom nav: Тренировка/Коллекции/Профиль), `training/` (`training_home_screen.dart` dashboard + progress monitor + "Перемешать всё"; `session_screen.dart` = swipe deck), `collections/` (emoji tiles, CRUD, `generate_dialog.dart`, `collection_edit_dialog.dart`, `word_edit_dialog.dart`), `profile/`.
- `preview.dart` — design preview harness with mock data: `flutter run -d chrome --target lib/preview.dart` (no backend/login needed).

## Design

Dark, vibrant (reference-driven): deep navy bg, gradient accents, emoji on collections.
Training session: tap card to reveal; **swipe right = Знаю, left = Не знаю, up = Повторить**
(also 3 buttons). Live progress monitor (know/review/don't-know tallies + bar) + session summary.

## Running on the iPhone — the exact recipe (and the gotchas)

Device: **iPhone (Denis)**, id `00008110-000A7CCC3492801E`, iOS 27 beta.

```bash
cd mobile && flutter run --release -d 00008110-000A7CCC3492801E
```

**Use `--release`.** In debug the app shows a white screen / "Dart VM Service not discovered"
because the debug VM service needs the phone's Local Network permission; release sidesteps it.

Hard-won gotchas (all already resolved once — needed again on a fresh machine/checkout):
- **Xcode 27 beta required** — host is macOS 27 beta; App Store Xcode 26.x won't launch (error -10664). Xcode 27 beta 3 is installed.
- **Signing** (one-time in Xcode → Runner → Signing & Capabilities): personal team "Solonina Denis" (Apple ID `haveystar95@gmail.com`), team id `7A5U4R66CB`, **bundle id `com.denis.engstd`**. Free personal team ⇒ app expires ~7 days; re-run to reinstall.
- **Developer Mode ON** on the iPhone (Settings → Privacy & Security → Developer Mode).
- **iOS deployment target ≥ 15.0** — set in `ios/Runner.xcodeproj/project.pbxproj` (3×) and `ios/Podfile` (`platform :ios, '15.0'`). Xcode 27 rejects 13.0.
- **CocoaPods**: use Homebrew's (`/opt/homebrew/bin/pod`, 1.17+). The old gem pod `/usr/local/bin/pod` (1.11.3) has a broken `ffi` and fails `pod install`; it was removed. Run flutter with `/opt/homebrew/bin` first on PATH and `LANG=en_US.UTF-8`.
- Google Sign-In needs the **REVERSED_CLIENT_ID URL scheme** in `ios/Runner/Info.plist` (already added, from `../credentials.plist`).
- `flutter_tts` warns it doesn't support Swift Package Manager — harmless.

## API config

`lib/core/config.dart` defaults `API_BASE_URL` to the ngrok URL of the **old backend**
(`https://greedily-thermos-finer.ngrok-free.dev`) and `GOOGLE_IOS_CLIENT_ID` to the value in
`../credentials.plist`. Override at run time: `flutter run --dart-define=API_BASE_URL=…`.
When backend2 is ready (ROADMAP Phase 4), repoint this / regenerate the client from OpenAPI.

## Verify without the phone

`flutter analyze` must be clean. `flutter test` runs the widget test. For visual checks use
the web preview harness (`lib/preview.dart`) instead of a simulator (no iOS simulator runtime
is installed; device is the target).
