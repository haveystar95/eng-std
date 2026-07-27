/// App-wide configuration.
///
/// Values default to the current dev setup but can be overridden at run time:
///   flutter run --dart-define=API_BASE_URL=https://xxxx.ngrok-free.dev
class AppConfig {
  /// Base URL of the Laravel backend (without trailing `/api`).
  /// The ngrok free URL changes when the tunnel restarts — override it then.
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
