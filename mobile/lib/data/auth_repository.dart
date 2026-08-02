import 'package:google_sign_in/google_sign_in.dart';

import '../core/config.dart';
import 'api_client.dart';
import 'models.dart';
import 'seq_counter.dart';
import 'token_store.dart';

class AuthException implements Exception {
  final String message;
  AuthException(this.message);
  @override
  String toString() => message;
}

/// Owns the sign-in flow: native Google Sign-In → backend token exchange →
/// persisted Sanctum token.
class AuthRepository {
  AuthRepository(this._api, this._tokens, this._seq);

  final ApiClient _api;
  final TokenStore _tokens;
  final SeqCounter _seq;
  bool _googleReady = false;

  Future<void> _ensureGoogle() async {
    if (_googleReady) return;
    await GoogleSignIn.instance.initialize(
      clientId: AppConfig.googleIosClientId,
      serverClientId:
          AppConfig.googleServerClientId.isEmpty ? null : AppConfig.googleServerClientId,
    );
    _googleReady = true;
  }

  /// Try to restore a session from a stored token. Returns the user or null.
  Future<AppUser?> restore() async {
    final token = await _tokens.load();
    if (token == null || token.isEmpty) return null;
    try {
      final user = await _api.me();
      await _seedSeqCounters();
      return user;
    } catch (_) {
      await _tokens.clear();
      return null;
    }
  }

  /// Lift the local monotonic counters to the server's high-water mark, so a reinstall
  /// (keychain wiped → counters at 0) or a fresh device can't emit client_seq values
  /// that would lose to rows the server already holds. Best-effort: offline just keeps
  /// the local counters, which are still monotonic for this device.
  Future<void> _seedSeqCounters() async {
    try {
      final cursor = await _api.syncCursor();
      await _seq.seedAtLeast(SeqCounter.triage, cursor.triage);
      await _seq.seedAtLeast(SeqCounter.review, cursor.review);
    } catch (_) {/* offline or endpoint unavailable — keep local counters */}
  }

  Future<AppUser> signInWithGoogle() async {
    await _ensureGoogle();

    if (!GoogleSignIn.instance.supportsAuthenticate()) {
      throw AuthException('Вход через Google не поддерживается на этой платформе.');
    }

    final GoogleSignInAccount account;
    try {
      account = await GoogleSignIn.instance.authenticate();
    } on GoogleSignInException catch (e) {
      if (e.code == GoogleSignInExceptionCode.canceled) {
        throw AuthException('Вход отменён.');
      }
      throw AuthException('Ошибка Google: ${e.description ?? e.code.name}');
    }

    final idToken = account.authentication.idToken;
    if (idToken == null) {
      throw AuthException('Не удалось получить ID-токен Google.');
    }

    final result = await _api.googleLogin(idToken);
    await _tokens.save(result.token);
    await _seedSeqCounters();
    return result.user;
  }

  Future<void> signOut() async {
    try {
      await _api.logout();
    } catch (_) {/* best effort */}
    try {
      await GoogleSignIn.instance.signOut();
    } catch (_) {/* best effort */}
    await _tokens.clear();
  }
}
