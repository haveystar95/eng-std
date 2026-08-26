import 'package:flutter/foundation.dart' show kDebugMode;

/// Whether the QA dev sign-in exists in THIS build.
///
/// `kDebugMode` and nothing else — deliberately not a `bool.fromEnvironment`, because every other
/// flag in this file is one, and a `--dart-define` is exactly how a dev door reaches a shipped
/// build. The canonical device build is `--release` (see mobile/CLAUDE.md), so the door is absent
/// from the build that goes on the owner's phone and present on the simulator, which is the only
/// place it is needed: Google and Apple sign-in cannot be completed there at all.
///
/// The constant is `const`, so `if (kDevLoginEnabled)` is folded away at compile time in release
/// and the guarded widgets and calls are tree-shaken out with it. Pinned by
/// `test/data/dev_login_release_guard_test.dart`, which fails if this definition ever grows a
/// second term or if a dev-login reference appears outside a guard.
///
/// The server has its own, independent locks (`DevLoginGate`: not production AND
/// `DEV_LOGIN_ENABLED`), so a leaked client would still find no door.
const bool kDevLoginEnabled = kDebugMode;

/// The QA account the dev door signs into.
///
/// `qa@wt.test` unless the BUILD says otherwise, and what it may say is narrow: an address of the
/// form `qa-<slug>@wt.test`, and nothing else ([isDevLoginEmail]). Anything that does not match is
/// ignored and the default stands — a malformed define must not become a run under an address
/// nobody meant.
///
/// The address moved because the ACCOUNT is a run's most basic fixture and a clean one was reachable
/// only by editing this constant: a pass that needs an empty shelf (first-day states, a fresh swipe
/// pass, an untouched pool) had to be committed for, run, and reverted. It is now
/// `--dart-define=DEV_LOGIN_EMAIL=qa-input1@wt.test` and the source is untouched.
///
/// What has NOT moved is the point of the bench: every run still happens under an address the QA
/// commands are allowed to touch, and the login screen still has no text field — a free-text entry
/// is how a run quietly happens against the owner's real account. And the door itself is still
/// [kDevLoginEnabled], which is `kDebugMode` and nothing else: a `--dart-define` cannot open it, it
/// can only say which QA account it opens onto.
/// `final` rather than `const`, and only because Dart has no const functions: the value is a
/// validated string and the validator below is a function. That is a safe difference — release
/// safety rests on [kDevLoginEnabled], which stays `const kDebugMode`, so the branches that read
/// this address are folded away whatever kind of binding it has.
final String kDevLoginEmail = isDevLoginEmail(_devLoginEmailOverride)
    ? _devLoginEmailOverride
    : kCanonicalDevLoginEmail;

/// Where a run happens unless the build says otherwise.
const String kCanonicalDevLoginEmail = 'qa@wt.test';

const String _devLoginEmailOverride = String.fromEnvironment('DEV_LOGIN_EMAIL');

/// The QA bench's own address shape: `qa@wt.test`, or `qa-<slug>@wt.test` for a run that needs an
/// account of its own. Deterministic and narrow on purpose — this is the rule that keeps a
/// mistyped define from becoming a sign-in somewhere real.
bool isDevLoginEmail(String email) =>
    email == kCanonicalDevLoginEmail ||
    (email.startsWith('qa-') && email.endsWith('@wt.test') && email.length > 'qa-@wt.test'.length);

/// Feature flags.
///
/// Photo attribution ("Фото: Author · Pexels") is hidden for now — the data (author + author_url)
/// still syncs and is stored, only the UI is gated. **Flip to true before any public release:
/// crediting the photographer with a link is a Pexels API requirement.**
const bool kShowPhotoAttribution = false;

/// App-wide configuration.
///
/// Values default to the current dev setup but can be overridden at run time:
///   flutter run --dart-define=API_BASE_URL=https://xxxx.ngrok-free.dev
class AppConfig {
  // ── Feature flags (A3.9 — «Стор коллекций» + пейволл) ──────────────────────
  //
  // The store + paywall block sits behind these flags. Each is the compile-time
  // DEFAULT for the runtime [FeatureFlags]; a stored dev-menu override (SyncMeta)
  // wins over it.
  //
  // **DEV DEFAULT: ON.** The store is in scope and only we install builds, so a
  // plain `flutter run` (no --dart-define) must not silently drop these surfaces —
  // that's exactly how the store "disappeared" from a rebuild. They default true
  // so every dev build is identical without extra flags.
  //
  // TODO(release): decide the release defaults for STORE_ENABLED / PAYWALL_ENABLED /
  // DEV_MENU (DEV_MENU almost certainly false; store/paywall per launch scope). Owned
  // by the release block — see ROADMAP «релиз: решить дефолты флагов». Override at
  // launch when needed, e.g. --dart-define=STORE_ENABLED=false.

  /// The store surface (the «Готовые» segment in the Collections tab, кадр 2.8).
  static const bool storeEnabled = bool.fromEnvironment(
    'STORE_ENABLED',
    defaultValue: true,
  ); // TODO(release): default

  /// The paywall (кадры 2.13–2.14) and its entry points (store premium lock,
  /// exhausted-quota upsell, the profile «Попробовать Premium» row).
  static const bool paywallEnabled = bool.fromEnvironment(
    'PAYWALL_ENABLED',
    defaultValue: true,
  ); // TODO(release): default

  /// Reveals the Profile → «Разработка» dev section (feature-flag + fake-premium
  /// toggles). On by default in dev builds so the flags are reachable on device
  /// without a rebuild; a release build should pin it off.
  static const bool devMenuEnabled = bool.fromEnvironment(
    'DEV_MENU',
    defaultValue: true,
  ); // TODO(release): default false

  /// Serve a small built-in mock store catalogue when `GET /store/collections`
  /// returns nothing (Session B hasn't published content yet), so the store UI is
  /// exercisable on device before the live feed exists. Dev-only; never in release.
  static const bool storeMockFallback = bool.fromEnvironment('STORE_MOCK', defaultValue: false);

  /// Base host of the backend (without a path). The client appends `/api/v1`.
  /// The app now targets **backend2** — point this at an exposed backend2
  /// (ngrok tunnel to host :8001, or a deployed URL). Override at run time:
  ///   flutter run --dart-define=API_BASE_URL=https://xxxx.ngrok-free.dev
  static const String apiBaseUrl = String.fromEnvironment(
    'API_BASE_URL',
    defaultValue: 'https://greedily-thermos-finer.ngrok-free.dev',
  );

  /// Google iOS OAuth client id (from Google Cloud Console / credentials.plist).
  static const String googleIosClientId = String.fromEnvironment(
    'GOOGLE_IOS_CLIENT_ID',
    defaultValue: '1003468760314-lvn5ckoc6p8s6v5g44i396ma5j4797j0.apps.googleusercontent.com',
  );

  /// Optional web/server client id (used as serverClientId if set).
  static const String googleServerClientId = String.fromEnvironment(
    'GOOGLE_SERVER_CLIENT_ID',
    defaultValue: '',
  );
}
