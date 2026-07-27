import 'package:flutter_secure_storage/flutter_secure_storage.dart';

/// Persists the Sanctum bearer token in the iOS keychain.
class TokenStore {
  static const _key = 'api_token';
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
  }
}
