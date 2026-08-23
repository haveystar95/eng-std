import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';

import 'package:eng_std/data/feature_flags.dart';
import 'package:eng_std/data/models.dart';
import 'package:eng_std/data/providers.dart';
import 'package:eng_std/data/store_providers.dart';
import 'package:eng_std/features/collections/collections_screen.dart';
import 'package:eng_std/l10n/app_localizations.dart';

/// Acceptance guard: the «Мои»/«Готовые» segment (кадр 2.8) appears only when the store flag is on;
/// with it off the Collections tab is exactly as before A3.9 — no store surface.
void main() {
  const pair = (source: 'ru', target: 'en');

  AppUser user() => AppUser(
    id: 'u1',
    name: 'D',
    profile: Profile(nativeLanguage: 'ru', targetLanguage: 'en', cefrLevel: 'B1', dailyGoal: 20),
  );

  Future<void> pump(WidgetTester tester, {required bool store}) {
    return tester.pumpWidget(
      ProviderScope(
        overrides: [
          authControllerProvider.overrideWith(() => _FakeAuth(user())),
          featureFlagsProvider.overrideWith(
            () => _FakeFlags(
              FeatureFlags(storeEnabled: store, paywallEnabled: store, devPremium: false),
            ),
          ),
          collectionsProvider.overrideWith((ref) => Stream.value(const <WordCollection>[])),
          pendingGenerationsProvider.overrideWith((ref) => const Stream.empty()),
          storeCollectionsProvider(pair).overrideWith((ref) async => const <StoreSection>[]),
        ],
        child: const MaterialApp(
          locale: Locale('ru'),
          localizationsDelegates: AppLocalizations.localizationsDelegates,
          supportedLocales: [Locale('ru')],
          home: CollectionsScreen(),
        ),
      ),
    );
  }

  testWidgets('store off: no segment, no «Готовые»', (tester) async {
    await pump(tester, store: false);
    await tester.pumpAndSettle();

    expect(find.text('Готовые'), findsNothing);
    // The «Мои» segment label must also be absent (it only exists inside the segment).
    expect(find.text('Мои'), findsNothing);
  });

  testWidgets('store on: the «Мои»/«Готовые» segment is shown', (tester) async {
    await pump(tester, store: true);
    await tester.pumpAndSettle();

    expect(find.text('Мои'), findsOneWidget);
    expect(find.text('Готовые'), findsOneWidget);
  });

  testWidgets('store on: switching to «Готовые» shows the store (empty state here)', (
    tester,
  ) async {
    await pump(tester, store: true);
    await tester.pumpAndSettle();

    await tester.tap(find.text('Готовые'));
    await tester.pumpAndSettle();

    // Empty store copy (Session B hasn't published in this test).
    expect(find.text('Скоро здесь появятся наборы'), findsOneWidget);
  });
}

class _FakeAuth extends AuthController {
  _FakeAuth(this._user);
  final AppUser? _user;
  @override
  Future<AppUser?> build() async => _user;
}

class _FakeFlags extends FeatureFlagsController {
  _FakeFlags(this._flags);
  final FeatureFlags _flags;
  @override
  FeatureFlags build() => _flags;
}
