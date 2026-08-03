import 'dart:async';
import 'dart:convert';

import 'package:connectivity_plus/connectivity_plus.dart';
import 'package:dio/dio.dart';
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

  /// Restore a session from the stored token. Offline-first: if a cached user is present, return
  /// it immediately (the app must open on a plane) and refresh in the background. The token is
  /// cleared ONLY on a genuine auth rejection (401/403) — never on a network failure, which would
  /// otherwise log the user out the first time they cold-start offline and lose their token.
  Future<AppUser?> restore() async {
    final token = await _tokens.load();
    if (token == null || token.isEmpty) return null;

    final cached = await _tokens.loadUser();
    if (cached != null) {
      unawaited(_refresh()); // best-effort; updates the cache + seq counters when online
      return AppUser.fromJson(jsonDecode(cached) as Map<String, dynamic>);
    }
    // No cache (e.g. an upgrade from before caching existed): fetch once, but still only drop the
    // token on a real 401/403.
    return _refresh();
  }

  /// Re-fetch the user from the server and update the cache. Returns null (keeping the token) on
  /// any network trouble; only a 401/403 clears the token.
  Future<AppUser?> _refresh() async {
    try {
      final user = await _api.me();
      await _tokens.saveUser(jsonEncode(user.toJson()));
      await _seedSeqCounters();
      return user;
    } on DioException catch (e) {
      final status = e.response?.statusCode;
      if (status == 401 || status == 403) {
        await _tokens.clear(); // genuinely unauthorized → sign out
      }
      return null; // offline / timeout / 5xx → keep the token and the cached user
    } catch (_) {
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
    // Sign-in is the one flow that genuinely needs the network (Google auth + backend token
    // exchange). Fail fast with a clear message instead of a cryptic Google/Dio error deep in
    // the flow — this is the honest "offline doesn't work here" case for a brand-new user.
    final conn = await Connectivity().checkConnectivity();
    if (conn.every((r) => r == ConnectivityResult.none)) {
      throw AuthException('Нет подключения к интернету. Для входа нужна сеть.');
    }

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

    final ({String token, AppUser user}) result;
    try {
      result = await _api.googleLogin(idToken);
    } on DioException catch (e) {
      // No response = the request never reached the backend (offline / DNS / timeout).
      if (e.response == null) {
        throw AuthException('Нет подключения к интернету. Для входа нужна сеть.');
      }
      throw AuthException('Не удалось войти (${e.response?.statusCode}). Попробуй ещё раз.');
    }
    await _tokens.save(result.token);
    await _tokens.saveUser(jsonEncode(result.user.toJson())); // enable offline restore next launch
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
