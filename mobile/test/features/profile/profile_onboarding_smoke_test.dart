import 'package:drift/native.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';

import 'package:eng_std/data/local/app_database.dart';
import 'package:eng_std/data/models.dart';
import 'package:eng_std/data/providers.dart';
import 'package:eng_std/features/onboarding/onboarding_screen.dart';
import 'package:eng_std/features/profile/profile_screen.dart';
import 'package:eng_std/l10n/app_localizations.dart';

/// Render smoke tests for the A3.7 screens (device-batched) — catch layout throws and confirm the
/// copy routes through AppLocalizations. Behaviour (edit sheets, delete) is exercised on device.
class _FakeAuth extends AuthController {
  _FakeAuth(this._user);
  final AppUser? _user;
  @override
  Future<AppUser?> build() async => _user;
}

AppUser _user() => AppUser(
      id: 'u1',
      name: 'Марина Ковалёва',
      email: 'marina.k@icloud.com',
      profile: Profile(nativeLanguage: 'ru', targetLanguage: 'en', cefrLevel: 'B1', dailyGoal: 20),
    );

MaterialApp _app(Widget home) => MaterialApp(
      locale: const Locale('ru'),
      localizationsDelegates: AppLocalizations.localizationsDelegates,
      supportedLocales: const [Locale('ru')],
      home: Scaffold(body: home),
    );

void main() {
  testWidgets('Onboarding renders step 1 (language) with a default', (tester) async {
    await tester.pumpWidget(ProviderScope(
      overrides: [
        appDatabaseProvider.overrideWith((ref) {
          final db = AppDatabase.forTesting(NativeDatabase.memory());
          ref.onDispose(db.close);
          return db;
        }),
        authControllerProvider.overrideWith(() => _FakeAuth(_user())),
      ],
      child: _app(const OnboardingScreen()),
    ));
    await tester.pumpAndSettle();
    expect(tester.takeException(), isNull);
    expect(find.text('Какой язык учим?'), findsOneWidget);
    expect(find.text('Далее'), findsOneWidget);
  });

  testWidgets('Profile renders sections and account actions', (tester) async {
    await tester.pumpWidget(ProviderScope(
      overrides: [
        appDatabaseProvider.overrideWith((ref) {
          final db = AppDatabase.forTesting(NativeDatabase.memory());
          ref.onDispose(db.close);
          return db;
        }),
        authControllerProvider.overrideWith(() => _FakeAuth(_user())),
        statsProvider.overrideWith((ref) => Stream.value(Stats(
              totalWords: 146,
              learned: 60,
              mastered: 82,
              dueToday: 0,
              reviewsTotal: 12,
              streakDays: 12,
            ))),
      ],
      child: _app(const ProfileScreen()),
    ));
    await tester.pumpAndSettle();
    expect(tester.takeException(), isNull);
    expect(find.text('Профиль'), findsOneWidget);
    expect(find.text('ОБУЧЕНИЕ'), findsOneWidget); // section labels are uppercased
    expect(find.text('Язык интерфейса'), findsOneWidget);
    // «Удалить аккаунт» lives below the test viewport fold — scroll it into view to confirm it renders.
    await tester.scrollUntilVisible(find.text('Удалить аккаунт'), 300);
    expect(find.text('Удалить аккаунт'), findsOneWidget);
  });
}
