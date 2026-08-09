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
  static const bool storeEnabled =
      bool.fromEnvironment('STORE_ENABLED', defaultValue: true); // TODO(release): default

  /// The paywall (кадры 2.13–2.14) and its entry points (store premium lock,
  /// exhausted-quota upsell, the profile «Попробовать Premium» row).
  static const bool paywallEnabled =
      bool.fromEnvironment('PAYWALL_ENABLED', defaultValue: true); // TODO(release): default

  /// Reveals the Profile → «Разработка» dev section (feature-flag + fake-premium
  /// toggles). On by default in dev builds so the flags are reachable on device
  /// without a rebuild; a release build should pin it off.
  static const bool devMenuEnabled =
      bool.fromEnvironment('DEV_MENU', defaultValue: true); // TODO(release): default false

  /// Serve a small built-in mock store catalogue when `GET /store/collections`
  /// returns nothing (Session B hasn't published content yet), so the store UI is
  /// exercisable on device before the live feed exists. Dev-only; never in release.
  static const bool storeMockFallback =
      bool.fromEnvironment('STORE_MOCK', defaultValue: false);

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
