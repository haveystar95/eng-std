import 'package:flutter_secure_storage/flutter_secure_storage.dart';

/// Persists the Sanctum bearer token in the iOS keychain.
class TokenStore {
  static const _key = 'api_token';
  static const _userKey = 'api_user';
  static const _onboardedKey = 'onboarded';
  final _storage = const FlutterSecureStorage();

  String? _cached;

  /// Cheap synchronous accessor for interceptors (populated by [load]).
  String? get current => _cached;

  Future<String?> load() async {
    _cached = await _storage.read(key: _key);
    return _cached;
  }

  Future<void> save(String token) async {
    _cached = token;
    await _storage.write(key: _key, value: token);
  }

  Future<void> clear() async {
    _cached = null;
    await _storage.delete(key: _key);
    await _storage.delete(key: _userKey);
    // Onboarding is per-account: forget it on sign-out so a different account
    // (or a re-login) runs first-run onboarding again instead of inheriting the
    // previous account's keychain flag. (device-batch F1)
    await _storage.delete(key: _onboardedKey);
  }

  /// The last-known signed-in user, as a JSON string, cached in the keychain alongside the token
  /// so the session restores offline (no `/auth/me` round-trip on cold start). Survives reinstall,
  /// same as the token.
  Future<String?> loadUser() async => _storage.read(key: _userKey);

  Future<void> saveUser(String json) async => _storage.write(key: _userKey, value: json);

  /// Whether the user has finished the first-run onboarding on this device.
  Future<bool> isOnboarded() async => (await _storage.read(key: _onboardedKey)) == '1';

  Future<void> setOnboarded() async => _storage.write(key: _onboardedKey, value: '1');
}
