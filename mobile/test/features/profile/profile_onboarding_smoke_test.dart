import 'package:drift/native.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';

import 'package:eng_std/data/api_client.dart';
import 'package:eng_std/data/local/app_database.dart';
import 'package:eng_std/data/models.dart';
import 'package:eng_std/data/plan/plan_languages.dart';
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

/// `GET /languages` as backend2 answers it since LANG-1 — or a network that does not answer.
class _LanguagesApi implements ApiClient {
  _LanguagesApi({this.offline = false, this.natives = const ['ru', 'uk', 'be', 'pl', 'ro', 'es', 'it', 'de', 'fr']});

  final bool offline;
  final List<String> natives;

  @override
  Future<PlanLanguages> pairLanguages() async {
    if (offline) throw StateError('offline');

    return PlanLanguages.fromJson({
      'targets': ['en', 'pl', 'ro', 'es', 'it', 'de', 'fr'],
      'natives': [
        for (final code in natives) {'code': code},
      ],
    });
  }

  @override
  dynamic noSuchMethod(Invocation invocation) => super.noSuchMethod(invocation);
}

MaterialApp _app(Widget home) => MaterialApp(
  locale: const Locale('ru'),
  localizationsDelegates: AppLocalizations.localizationsDelegates,
  supportedLocales: const [Locale('ru')],
  home: Scaffold(body: home),
);

void main() {
  testWidgets('Onboarding asks the NATIVE language first (ONB-1)', (tester) async {
    await tester.pumpWidget(
      ProviderScope(
        overrides: [
          appDatabaseProvider.overrideWith((ref) {
            final db = AppDatabase.forTesting(NativeDatabase.memory());
            ref.onDispose(db.close);
            return db;
          }),
          authControllerProvider.overrideWith(() => _FakeAuth(_user())),
          apiClientProvider.overrideWithValue(_LanguagesApi()),
        ],
        child: _app(const OnboardingScreen()),
      ),
    );
    await tester.pumpAndSettle();
    expect(tester.takeException(), isNull);
    expect(find.text('На каком языке показывать переводы?'), findsOneWidget);
    expect(find.text('Далее'), findsOneWidget);
    // The server's natives (LANG-1: nine, Belarusian among them) — and English is deliberately not
    // one of them.
    expect(find.text('Українська'), findsOneWidget);
    expect(find.text('Беларуская'), findsOneWidget);
    expect(find.text('Polski'), findsOneWidget);
    expect(find.text('English'), findsNothing);
  });

  testWidgets('the native step falls back to the bundled nine when the server does not answer', (tester) async {
    await tester.pumpWidget(
      ProviderScope(
        overrides: [
          appDatabaseProvider.overrideWith((ref) {
            final db = AppDatabase.forTesting(NativeDatabase.memory());
            ref.onDispose(db.close);
            return db;
          }),
          authControllerProvider.overrideWith(() => _FakeAuth(_user())),
          apiClientProvider.overrideWithValue(_LanguagesApi(offline: true)),
        ],
        child: _app(const OnboardingScreen()),
      ),
    );
    await tester.pumpAndSettle();

    expect(tester.takeException(), isNull);
    expect(find.text('Русский'), findsOneWidget);
    expect(find.text('Беларуская'), findsOneWidget);
  });

  // The bundle and the server hold the same nine today, so only a DIFFERENT answer shows which one the
  // step draws: a step on the client constant would still list Русский and Polski here.
  testWidgets('the native step draws the server\'s natives, not the bundled copy', (tester) async {
    await tester.pumpWidget(
      ProviderScope(
        overrides: [
          appDatabaseProvider.overrideWith((ref) {
            final db = AppDatabase.forTesting(NativeDatabase.memory());
            ref.onDispose(db.close);
            return db;
          }),
          authControllerProvider.overrideWith(() => _FakeAuth(_user())),
          apiClientProvider.overrideWithValue(_LanguagesApi(natives: const ['uk', 'be'])),
        ],
        child: _app(const OnboardingScreen()),
      ),
    );
    await tester.pumpAndSettle();

    expect(find.text('Українська'), findsOneWidget);
    expect(find.text('Беларуская'), findsOneWidget);
    expect(find.text('Русский'), findsNothing);
    expect(find.text('Polski'), findsNothing);
  });

  testWidgets('the studied language offers English and German, and nothing else', (tester) async {
    await tester.pumpWidget(
      ProviderScope(
        overrides: [
          appDatabaseProvider.overrideWith((ref) {
            final db = AppDatabase.forTesting(NativeDatabase.memory());
            ref.onDispose(db.close);
            return db;
          }),
          authControllerProvider.overrideWith(() => _FakeAuth(_user())),
          apiClientProvider.overrideWithValue(_LanguagesApi()),
        ],
        child: _app(const OnboardingScreen()),
      ),
    );
    await tester.pumpAndSettle();
    await tester.tap(find.text('Далее'));
    await tester.pumpAndSettle();

    expect(find.text('Какой язык учим?'), findsOneWidget);
    expect(find.text('English'), findsOneWidget);
    expect(find.text('Deutsch'), findsOneWidget);
    // The catalogue still knows thirteen languages; the product offers two.
    expect(find.text('Español'), findsNothing);
    expect(find.text('日本語'), findsNothing);
  });

  testWidgets('Profile renders sections and account actions', (tester) async {
    await tester.pumpWidget(
      ProviderScope(
        overrides: [
          appDatabaseProvider.overrideWith((ref) {
            final db = AppDatabase.forTesting(NativeDatabase.memory());
            ref.onDispose(db.close);
            return db;
          }),
          authControllerProvider.overrideWith(() => _FakeAuth(_user())),
          statsProvider.overrideWith(
            (ref) => Stream.value(
              Stats(
                totalWords: 146,
                learned: 60,
                mastered: 82,
                dueToday: 0,
                reviewsTotal: 12,
                streakDays: 12,
              ),
            ),
          ),
        ],
        child: _app(const ProfileScreen()),
      ),
    );
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
