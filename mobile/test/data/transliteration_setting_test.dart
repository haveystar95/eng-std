import 'dart:io';

import 'package:drift/native.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';

import 'package:eng_std/data/app_settings.dart';
import 'package:eng_std/data/local/app_database.dart';
import 'package:eng_std/data/models.dart';
import 'package:eng_std/data/providers.dart';

/// «Подсказка произношения» (Ч.4) — one switch, one default, one provider.
///
/// The default is not stored anywhere: it is READ OFF the learner's own language, so somebody who
/// has never opened the setting gets the answer their own alphabet implies. Touching the switch is
/// what turns that into a stored decision, and a stored decision outlives any later change to the
/// default.
void main() {
  group('the default follows the learner\'s own alphabet', () {
    test('a Cyrillic reader gets the hint', () {
      expect(transliterationDefaultFor('ru'), isTrue);
      expect(transliterationDefaultFor('uk'), isTrue);
      expect(transliterationDefaultFor('RU'), isTrue, reason: 'case is not a language difference');
    });

    test('a Latin reader does not', () {
      expect(transliterationDefaultFor('en'), isFalse);
      expect(transliterationDefaultFor('es'), isFalse);
    });

    test('an unknown language is not known to read Cyrillic — so, off', () {
      // Signed out, or the profile has not been restored yet. Guessing «on» would put a Russian
      // reading under an English word for somebody who never asked for one.
      expect(transliterationDefaultFor(null), isFalse);
    });
  });

  group('the provider: a stored decision beats the default', () {
    late AppDatabase db;
    late ProviderContainer container;

    AppUser userWith(String native) => AppUser(
      id: 'U',
      name: 'Denis',
      profile: Profile(
        nativeLanguage: native,
        targetLanguage: 'en',
        cefrLevel: 'B1',
        dailyGoal: 20,
      ),
    );

    Future<bool> resolve({required String native, bool? stored}) async {
      if (stored != null) await db.setMeta('transliteration', stored ? '1' : '0');
      container = ProviderContainer(
        overrides: [
          appDatabaseProvider.overrideWithValue(db),
          authControllerProvider.overrideWith(() => _FixedAuth(userWith(native))),
        ],
      );
      addTearDown(container.dispose);
      // Both inputs are asynchronous — the KV read and the restored user. Wait for each, or the
      // provider is only being asked what it says while it still knows nothing.
      await container.read(appSettingsProvider.future);
      await container.read(authControllerProvider.future);

      return container.read(transliterationEnabledProvider);
    }

    setUp(() => db = AppDatabase.forTesting(NativeDatabase.memory()));
    tearDown(() => db.close());

    test('nothing stored, Cyrillic native → on', () async {
      expect(await resolve(native: 'ru'), isTrue);
    });

    test('nothing stored, Latin native → off', () async {
      expect(await resolve(native: 'en'), isFalse);
    });

    test('switched off by hand → off, even for a Cyrillic reader', () async {
      expect(await resolve(native: 'ru', stored: false), isFalse);
    });

    test('switched on by hand → on, even for a Latin reader', () async {
      expect(await resolve(native: 'en', stored: true), isTrue);
    });

    test('the switch stores a decision, and the decision survives a reread', () async {
      container = ProviderContainer(
        overrides: [
          appDatabaseProvider.overrideWithValue(db),
          authControllerProvider.overrideWith(() => _FixedAuth(userWith('ru'))),
        ],
      );
      addTearDown(container.dispose);
      await container.read(appSettingsProvider.future);

      await container.read(appSettingsProvider.notifier).setTransliteration(false);
      expect(container.read(transliterationEnabledProvider), isFalse);
      expect(await db.getMeta('transliteration'), '0');
    });
  });

  _trainersNeverShowTheReading();
}

/// The hint is for READING a word, never for producing one. A trainer that showed it would be
/// handing the learner the answer to the card it is asking — so the exercise surfaces are held to
/// «not one mention», the same way the theme guard holds hex codes out of `lib/features/`.
void _trainersNeverShowTheReading() {
  test('no exercise surface so much as mentions the reading hint', () {
    final offenders = <String>[];
    for (final dir in [
      'lib/features/training',
      'lib/data/practice',
      'lib/features/practice_dialog',
    ]) {
      for (final entity in Directory(dir).listSync(recursive: true)) {
        if (entity is! File || !entity.path.endsWith('.dart')) continue;
        final src = entity.readAsStringSync().toLowerCase();
        if (src.contains('transliteration')) offenders.add(entity.path);
      }
    }

    expect(
      offenders,
      isEmpty,
      reason:
          'The pronunciation hint belongs to the word card and the translator, and to nothing '
          'that asks the learner to type, assemble or say the word:\n${offenders.join('\n')}',
    );
  });
}

/// A signed-in user, without the keychain or the network the real controller restores from.
class _FixedAuth extends AuthController {
  _FixedAuth(this._user);

  final AppUser _user;

  @override
  Future<AppUser?> build() async => _user;
}
