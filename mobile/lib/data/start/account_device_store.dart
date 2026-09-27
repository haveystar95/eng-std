import 'dart:io';

import 'package:flutter/foundation.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_secure_storage/flutter_secure_storage.dart';
import 'package:path/path.dart' as p;
import 'package:path_provider/path_provider.dart';

/// WHAT THIS PHONE REMEMBERS ABOUT AN ACCOUNT — beyond the session (work order CLIENT-START §§2, 3, 5).
///
/// Three facts, each keyed by the user's id and none of them the server's:
/// - the five «why» sheets have been shown to this account on this phone (41-2 — «один раз на аккаунт на устройстве»);
/// - which door the account came in through, Apple or Google (42-1 «Вход через …»): `/auth/me` does not say, so the
///   phone keeps what it saw at sign-in;
/// - the name the learner typed in 42-2: `PATCH /profile` takes no name, so it lives here until the server does.
///
/// In the keychain, not in the local database: sign-out wipes the database (a next account must not start from
/// someone else's rows), and «once per account on this phone» has to outlive a sign-out.
class AccountDeviceStore {
  AccountDeviceStore(this._kv);

  final DeviceKeyValue _kv;

  Future<bool> introSeen(String userId) async => await _kv.read('intro_seen:$userId') == '1';

  Future<void> markIntroSeen(String userId) => _kv.write('intro_seen:$userId', '1');

  /// `apple` | `google`; null — not known (an account restored from a build that did not keep it, the QA door).
  Future<SignInDoor?> door(String userId) async => SignInDoor.fromKey(await _kv.read('door:$userId'));

  Future<void> setDoor(String userId, SignInDoor door) => _kv.write('door:$userId', door.name);

  Future<String?> displayName(String userId) async {
    final name = (await _kv.read('display_name:$userId'))?.trim();
    return name == null || name.isEmpty ? null : name;
  }

  Future<void> setDisplayName(String userId, String name) => _kv.write('display_name:$userId', name.trim());
}

/// The door of 41-4 the account came in through.
enum SignInDoor {
  apple,
  google;

  static SignInDoor? fromKey(String? key) => switch (key) {
    'apple' => apple,
    'google' => google,
    _ => null,
  };
}

/// The smallest key-value surface [AccountDeviceStore] needs — the keychain in the app, a map in tests (the plugin
/// has no implementation under `flutter test`).
abstract interface class DeviceKeyValue {
  Future<String?> read(String key);
  Future<void> write(String key, String value);
}

class KeychainKeyValue implements DeviceKeyValue {
  const KeychainKeyValue();

  static const _storage = FlutterSecureStorage();

  @override
  Future<String?> read(String key) async {
    try {
      return await _storage.read(key: key);
    } catch (e) {
      debugPrint('[device-store] read $key failed: $e');
      return null;
    }
  }

  @override
  Future<void> write(String key, String value) async {
    try {
      await _storage.write(key: key, value: value);
    } catch (e) {
      debugPrint('[device-store] write $key failed: $e');
    }
  }
}

@visibleForTesting
class MemoryKeyValue implements DeviceKeyValue {
  MemoryKeyValue([Map<String, String>? values]) : values = values ?? {};

  final Map<String, String> values;

  @override
  Future<String?> read(String key) async => values[key];

  @override
  Future<void> write(String key, String value) async => values[key] = value;
}

final accountDeviceStoreProvider = Provider<AccountDeviceStore>(
  (ref) => AccountDeviceStore(const KeychainKeyValue()),
);

/// A FRESH INSTALL STARTS SIGNED OUT (work order CLIENT-START: «удали приложение и поставь заново — первый запуск с
/// нуля»).
///
/// The token lives in the keychain, and iOS keeps keychain items when the app is deleted — so a reinstall used to come
/// back signed in and skip the splash, the sign-in and the sheets. The app's own container IS deleted with it: a marker
/// file there says «this install has run before». No marker and no local database either — a new install: the stored
/// session is dropped before anything reads it. No marker but a database — the first run of a build that did not write
/// the marker yet (an update in place): the session stays, the marker is written.
///
/// The «sheets seen», the door and the name stay: they are about the account on this phone, not about the install.
class InstallMarker {
  InstallMarker({Future<Directory> Function()? directory}) : _directory = directory ?? getApplicationDocumentsDirectory;

  final Future<Directory> Function() _directory;

  static const _marker = 'install.marker';

  /// The local mirror's file (`AppDatabase` opens it lazily in the same directory).
  static const _database = 'wordtrainer.sqlite';

  /// True — this is the first run of a new install, and [dropSession] was called.
  Future<bool> check({required Future<void> Function() dropSession}) async {
    try {
      final dir = await _directory();
      final marker = File(p.join(dir.path, _marker));
      if (marker.existsSync()) return false;
      final fresh = !File(p.join(dir.path, _database)).existsSync();
      if (fresh) await dropSession();
      marker.writeAsStringSync(DateTime.now().toUtc().toIso8601String());
      return fresh;
    } catch (e) {
      // No container to look at (tests, a platform without path_provider) — nothing to decide; never block a start.
      debugPrint('[install] marker check skipped: $e');
      return false;
    }
  }
}
