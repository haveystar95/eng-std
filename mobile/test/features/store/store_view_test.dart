import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:lucide_icons_flutter/lucide_icons.dart';

import 'package:eng_std/data/feature_flags.dart';
import 'package:eng_std/data/models.dart';
import 'package:eng_std/data/providers.dart';
import 'package:eng_std/data/store_providers.dart';
import 'package:eng_std/features/collections/store_view.dart';
import 'package:eng_std/l10n/app_localizations.dart';

/// A3.9 store surface (кадр 2.8): list renders by topic sections, premium sets carry the lock badge,
/// and tapping a premium set routes through the preview to the paywall.
void main() {
  const pair = (source: 'ru', target: 'en');

  AppUser user() => AppUser(
        id: 'u1',
        name: 'D',
        profile: Profile(nativeLanguage: 'ru', targetLanguage: 'en', cefrLevel: 'B1', dailyGoal: 20),
      );

  StoreCollection col(String id, String title, String topic, int n, bool premium) => StoreCollection(
        id: id,
        title: title,
        topic: topic,
        sourceLang: 'ru',
        targetLang: 'en',
        isPremium: premium,
        isSubscribed: false,
        itemsCount: n,
      );

  final sections = [
    StoreSection(topic: 'Everyday', items: [col('cafe', 'Cafe', 'Everyday', 16, false)]),
    StoreSection(topic: 'Work', items: [col('interview', 'Job interview', 'Work', 22, true)]),
  ];

  Future<void> pump(WidgetTester tester, {bool paywall = true}) {
    return tester.pumpWidget(ProviderScope(
      overrides: [
        authControllerProvider.overrideWith(() => _FakeAuth(user())),
        featureFlagsProvider.overrideWith(
            () => _FakeFlags(FeatureFlags(storeEnabled: true, paywallEnabled: paywall, devPremium: false))),
        storeCollectionsProvider(pair).overrideWith((ref) async => sections),
      ],
      child: const MaterialApp(
        locale: Locale('ru'),
        localizationsDelegates: AppLocalizations.localizationsDelegates,
        supportedLocales: [Locale('ru')],
        home: Scaffold(body: StoreView(bottomInset: 0)),
      ),
    ));
  }

  testWidgets('renders topic sections and cards with word counts', (tester) async {
    await pump(tester);
    await tester.pumpAndSettle();

    expect(find.text('EVERYDAY'), findsOneWidget);
    expect(find.text('WORK'), findsOneWidget);
    expect(find.text('Cafe'), findsOneWidget);
    expect(find.text('Job interview'), findsOneWidget);
    expect(find.text('16 слов'), findsOneWidget);
    expect(find.text('22 слова'), findsOneWidget);
  });

  testWidgets('premium set shows the lock badge; free set does not', (tester) async {
    await pump(tester);
    await tester.pumpAndSettle();

    // Exactly one lock badge — on the premium card.
    expect(find.byIcon(LucideIcons.lock), findsOneWidget);
  });

  testWidgets('tapping a premium set opens the preview, then the paywall', (tester) async {
    await pump(tester);
    await tester.pumpAndSettle();

    await tester.tap(find.text('Job interview'));
    await tester.pumpAndSettle();

    // Preview sheet: the premium CTA.
    expect(find.text('Доступно с Premium'), findsOneWidget);

    await tester.tap(find.text('Доступно с Premium'));
    await tester.pumpAndSettle();

    // Paywall is up — title names the set, «Продолжить» present.
    expect(find.text('Продолжить'), findsOneWidget);
    expect(find.textContaining('Job interview'), findsOneWidget);
  });

  testWidgets('free set preview offers «Добавить в мои», not the paywall', (tester) async {
    await pump(tester);
    await tester.pumpAndSettle();

    await tester.tap(find.text('Cafe'));
    await tester.pumpAndSettle();

    expect(find.text('Добавить в мои'), findsOneWidget);
    expect(find.text('Доступно с Premium'), findsNothing);
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
