import 'dart:async';
import 'dart:convert';

import 'package:connectivity_plus/connectivity_plus.dart';
import 'package:dio/dio.dart';
import 'package:google_sign_in/google_sign_in.dart';
import 'package:sign_in_with_apple/sign_in_with_apple.dart';

import 'config.dart';
import 'api_client.dart';
import 'device_timezone.dart';
import 'models.dart';
import 'seq_counter.dart';
import 'token_store.dart';

/// Why a sign-in failed — a code, not a message, so the copy lives in `AppLocalizations` (the
/// data layer has no `BuildContext`). The login screen maps each to a localized string.
enum AuthError {
  offline,
  googleUnsupported,
  cancelled,
  googleFailed,
  googleNoToken,
  loginFailed,
  appleUnavailable,
  appleNoToken,
}

class AuthException implements Exception {
  final AuthError code;
  AuthException(this.code);
  @override
  String toString() => 'AuthException(${code.name})';
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
      // Offline-first: return the cached user now. The auth controller refreshes from /me in the
      // background and pushes the fresh user (with tier + quota) into state — so a stale/tier-less
      // cache heals within the session, no restart or re-login needed.
      return AppUser.fromJson(jsonDecode(cached) as Map<String, dynamic>);
    }
    // No cache (e.g. an upgrade from before caching existed): fetch once, but still only drop the
    // token on a real 401/403.
    return refresh();
  }

  /// Re-fetch the user from the server and update the cache + seq counters. Returns null (keeping
  /// the token) on any network trouble; only a 401/403 clears the token. Public so the auth
  /// controller can refresh into state after an offline-first restore.
  Future<AppUser?> refresh() async {
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
      throw AuthException(AuthError.offline);
    }

    await _ensureGoogle();

    if (!GoogleSignIn.instance.supportsAuthenticate()) {
      throw AuthException(AuthError.googleUnsupported);
    }

    final GoogleSignInAccount account;
    try {
      account = await GoogleSignIn.instance.authenticate();
    } on GoogleSignInException catch (e) {
      if (e.code == GoogleSignInExceptionCode.canceled) {
        throw AuthException(AuthError.cancelled);
      }
      throw AuthException(AuthError.googleFailed);
    }

    final idToken = account.authentication.idToken;
    if (idToken == null) {
      throw AuthException(AuthError.googleNoToken);
    }

    final ({String token, AppUser user}) result;
    try {
      // Seed the profile's timezone from the device at login so calendar-day due rounding (F19)
      // works from the first review, before any profile edit.
      result = await _api.googleLogin(idToken, timezone: await deviceTimezone());
    } on DioException catch (e) {
      // No response = the request never reached the backend (offline / DNS / timeout).
      if (e.response == null) {
        throw AuthException(AuthError.offline);
      }
      throw AuthException(AuthError.loginFailed);
    }
    await _tokens.save(result.token);
    await _tokens.saveUser(jsonEncode(result.user.toJson())); // enable offline restore next launch
    await _seedSeqCounters();
    return result.user;
  }

  /// Sign in with Apple (кадр 10a). Obtains an Apple ID credential natively, then exchanges its
  /// identity token at `/auth/apple`. Two external prerequisites that are NOT in place yet: the
  /// backend `/auth/apple` endpoint, and the "Sign in with Apple" capability (a paid Apple team —
  /// the current free personal team can't enable it). Until both land this surfaces a clear error
  /// rather than crashing.
  Future<AppUser> signInWithApple() async {
    final conn = await Connectivity().checkConnectivity();
    if (conn.every((r) => r == ConnectivityResult.none)) {
      throw AuthException(AuthError.offline);
    }

    final AuthorizationCredentialAppleID cred;
    try {
      cred = await SignInWithApple.getAppleIDCredential(
        scopes: [AppleIDAuthorizationScopes.email, AppleIDAuthorizationScopes.fullName],
      );
    } on SignInWithAppleAuthorizationException catch (e) {
      if (e.code == AuthorizationErrorCode.canceled) throw AuthException(AuthError.cancelled);
      throw AuthException(AuthError.appleUnavailable);
    } catch (_) {
      throw AuthException(AuthError.appleUnavailable);
    }

    final idToken = cred.identityToken;
    if (idToken == null) throw AuthException(AuthError.appleNoToken);

    final name = [cred.givenName, cred.familyName].whereType<String>().join(' ').trim();
    final ({String token, AppUser user}) result;
    try {
      result = await _api.appleLogin(idToken, name: name.isEmpty ? null : name);
    } on DioException catch (e) {
      if (e.response == null) throw AuthException(AuthError.offline);
      throw AuthException(AuthError.appleUnavailable);
    }
    await _tokens.save(result.token);
    await _tokens.saveUser(jsonEncode(result.user.toJson()));
    await _seedSeqCounters();
    return result.user;
  }

  /// Permanently delete the account (B3) and clear the local session. The server cascade (204)
  /// removes all remote data; the caller wipes the local mirror.
  Future<void> deleteAccount() async {
    await _api.deleteAccount();
    try {
      await GoogleSignIn.instance.signOut();
    } catch (_) {/* best effort */}
    await _tokens.clear();
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
