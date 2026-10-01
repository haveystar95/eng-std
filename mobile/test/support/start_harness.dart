/// THE START AND THE ACCOUNT UNDER TEST (work order CLIENT-START) — fakes for what the start screens and the profile
/// reach outside the widget tree: the account the sign-in yields, the keychain store, the notification probe, the links
/// that leave the app, the HTTP of `/auth/*`.
library;

import 'dart:async';
import 'dart:convert';
import 'dart:typed_data';

import 'package:dio/dio.dart';
import 'package:drift/native.dart';
import 'package:flutter/widgets.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_riverpod/misc.dart' show Override;
import 'package:flutter_test/flutter_test.dart';

import 'package:eng_std/data/api_client.dart';
import 'package:eng_std/data/app_version.dart';
import 'package:eng_std/data/auth_repository.dart';
import 'package:eng_std/data/local/app_database.dart';
import 'package:eng_std/data/models.dart';
import 'package:eng_std/data/plan/notify_permission.dart';
import 'package:eng_std/data/providers.dart';
import 'package:eng_std/data/seq_counter.dart';
import 'package:eng_std/data/start/account_device_store.dart';
import 'package:eng_std/data/token_store.dart';
import 'package:eng_std/features/profile/account_providers.dart';
import 'package:eng_std/features/profile/profile_screen.dart' show devMenuProvider;

/// The owner of the canvas's frames: «Ден», Apple, free.
AppUser denUser({bool premium = false, DateTime? until, String? gender = 'male', String native = 'ru'}) => AppUser(
  id: '01TESTUSER000000000000000A',
  name: 'Ден',
  profile: Profile(nativeLanguage: native, targetLanguage: 'en', cefrLevel: 'B1', dailyGoal: 20, gender: gender),
  access: AccountAccess(premium: premium, expiresAt: until, source: premium ? 'apple' : null),
);

/// AN ACCOUNT BY SCRIPT: [restored] is what the keychain gives at launch (null — a first launch); a door yields
/// [signInUser], or throws [signInError]; the calls are counted.
class ScriptedAuth extends AuthController {
  ScriptedAuth({this.restored, this.signInUser, this.signInError, this.hold, this.deleteHold});

  final AppUser? restored;
  final AppUser? signInUser;
  final Object? signInError;

  /// A sign-in that does not answer until this completes (41-4c «ожидание»).
  final Completer<void>? hold;

  /// A deletion that does not answer until this completes (42-3b «Удаляем…»).
  final Completer<void>? deleteHold;

  int googleCalls = 0;
  int appleCalls = 0;
  int signOutCalls = 0;
  int deleteCalls = 0;
  int refreshCalls = 0;

  @override
  Future<AppUser?> build() async => restored;

  Future<void> _door() async {
    state = const AsyncLoading();
    await hold?.future;
    state = signInError != null ? AsyncError(signInError!, StackTrace.empty) : AsyncData(signInUser);
  }

  @override
  Future<void> signIn() async {
    googleCalls++;
    await _door();
  }

  @override
  Future<void> signInWithApple() async {
    appleCalls++;
    await _door();
  }

  @override
  Future<void> signOut() async {
    signOutCalls++;
    state = const AsyncData(null);
  }

  @override
  Future<void> deleteAccount() async {
    deleteCalls++;
    await deleteHold?.future;
    state = const AsyncData(null);
  }

  @override
  Future<void> updateProfile(Map<String, dynamic> changes) async {}

  @override
  Future<void> refreshAccount() async => refreshCalls++;
}

/// What iOS says about notifications — settable, and it counts the asks.
class FakeNotifyProbe implements NotifyPermissionProbe {
  FakeNotifyProbe([this.value = NotifyPermission.notDetermined]);

  NotifyPermission value;

  @override
  Future<NotifyPermission> status() async => value;

  @override
  dynamic noSuchMethod(Invocation invocation) => super.noSuchMethod(invocation);
}

/// Where the profile's rows lead out of the app — recorded, not opened.
class RecordingLinks implements AccountLinks {
  final opened = <String>[];
  int letters = 0;
  int ratings = 0;

  @override
  Future<void> open(String url) async => opened.add(url);

  @override
  Future<void> rate() async => ratings++;

  @override
  Future<void> writeSupport({required String version}) async => letters++;
}

AppDatabase memoryDatabase(Ref ref) {
  final db = AppDatabase.forTesting(NativeDatabase.memory());
  ref.onDispose(db.close);
  return db;
}

/// The overrides every start / account screen needs off the device: the local base in memory, the keychain as a map,
/// the notification probe, the links, the profile without its dev door (the canvas has none).
///
/// [auth] replaces the account controller; without it the real controller runs over [repository] (the wire tests).
List<Override> accountOverrides({
  AuthController Function()? auth,
  AuthRepository? repository,
  MemoryKeyValue? keychain,
  FakeNotifyProbe? probe,
  RecordingLinks? links,
}) => [
  appDatabaseProvider.overrideWith(memoryDatabase),
  imageDiskCacheProvider.overrideWith((ref) async => null),
  if (auth != null) authControllerProvider.overrideWith(auth),
  if (repository != null) authRepositoryProvider.overrideWithValue(repository),
  accountDeviceStoreProvider.overrideWithValue(AccountDeviceStore(keychain ?? MemoryKeyValue())),
  notifyPermissionProbeProvider.overrideWithValue(probe ?? FakeNotifyProbe()),
  accountLinksProvider.overrideWithValue(links ?? RecordingLinks()),
  devMenuProvider.overrideWithValue(false),
  appVersionProvider.overrideWith((ref) async => kFakeBuildVersion),
];

/// THE VERSION A TEST'S DEVICE REPORTS — obviously fake (наряд CLIENT-22-1 §3): a golden that showed «1.0.0 (22)» was
/// taken for the build on the phone. Only the device's answer is faked; the profile reads it the way the app does.
const kFakeBuildVersion = '0.0.0 (0)';

/// `/auth/*` over a recording adapter — the real repository and the real controller, the server faked at the wire.
class AuthWire implements HttpClientAdapter {
  AuthWire(this.user);

  final Map<String, dynamic> user;
  final calls = <String>[];

  @override
  Future<ResponseBody> fetch(RequestOptions options, Stream<Uint8List>? requestStream, Future<void>? cancelFuture) async {
    calls.add('${options.method} ${options.uri.path}');
    final path = options.uri.path;
    if (options.method == 'DELETE' && path.endsWith('/auth/me')) return ResponseBody.fromString('', 204);
    if (options.method == 'POST' && path.endsWith('/auth/logout')) return ResponseBody.fromString('', 204);
    if (path.endsWith('/auth/me')) return _json({'data': user});
    if (path.endsWith('/sync/cursor')) return _json({'data': {'review': 0, 'triage': 0}});
    return _json({'data': null});
  }

  static ResponseBody _json(Object body) => ResponseBody.fromString(
    jsonEncode(body),
    200,
    headers: {
      Headers.contentTypeHeader: [Headers.jsonContentType],
    },
  );

  @override
  void close({bool force = false}) {}
}

/// The real [AuthRepository] over [wire] (the keychain mocked by `FlutterSecureStorage.setMockInitialValues`).
AuthRepository wiredRepository(AuthWire wire) {
  final tokens = TokenStore();
  return AuthRepository(ApiClient(tokens, adapter: wire), tokens, SeqCounter());
}

/// Pictures decode on the real clock: every [Image] in the tree is loaded (and the SVG marks read), then a frame.
Future<void> loadImages(WidgetTester tester) async {
  await tester.runAsync(() async {
    for (final element in find.byType(Image).evaluate()) {
      await precacheImage((element.widget as Image).image, element);
    }
    await Future<void>.delayed(const Duration(milliseconds: 50));
  });
  await tester.pump();
}
