import 'package:dio/dio.dart';
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
import 'package:eng_std/ui/mini_flag.dart';
import 'package:eng_std/ui/pair_badge.dart';

/// A3.9 store surface (кадр 2.8): list renders by topic sections, premium sets carry the lock badge,
/// and tapping a premium set routes through the preview to the paywall.
/// The flags INSIDE the card's pair badge. The filter row above the grid draws two of its own, and
/// a bare `byType(MiniFlag)` counts those too — which is how this file's first cut managed to fail
/// on a screen that was rendering correctly.
final _badgeFlags = find.descendant(
  of: find.byType(PairBadge),
  matching: find.byType(MiniFlag),
);

void main() {
  const pair = (source: 'ru', target: 'en');

  AppUser user() => AppUser(
    id: 'u1',
    name: 'D',
    profile: Profile(nativeLanguage: 'ru', targetLanguage: 'en', cefrLevel: 'B1', dailyGoal: 20),
  );

  StoreCollection col(
    String id,
    String title,
    String topic,
    int n,
    String cefr,
    bool premium, {
    String learned = 'en',
    bool? reference,
  }) => StoreCollection(
    id: id,
    title: title,
    topic: topic,
    sourceLang: 'ru',
    targetLang: learned,
    isPremium: premium,
    isSubscribed: false,
    itemsCount: n,
    cefr: cefr,
    isReference: reference,
  );

  final sections = [
    StoreSection(topic: 'Everyday', items: [col('cafe', 'Cafe', 'Everyday', 16, 'A2', false)]),
    StoreSection(
      topic: 'Work',
      items: [col('interview', 'Job interview', 'Work', 22, 'B1–B2', true)],
    ),
  ];

  StorePreview preview(int total) => StorePreview(
    items: const [
      StorePreviewItem(term: 'appointment', translation: 'приём у врача'),
      StorePreviewItem(term: 'symptom', translation: 'симптом'),
      StorePreviewItem(term: 'prescription', translation: 'рецепт'),
    ],
    total: total,
  );

  Future<void> pump(WidgetTester tester, {bool paywall = true, Object? previewError}) {
    return tester.pumpWidget(
      ProviderScope(
        overrides: [
          authControllerProvider.overrideWith(() => _FakeAuth(user())),
          featureFlagsProvider.overrideWith(
            () => _FakeFlags(
              FeatureFlags(storeEnabled: true, paywallEnabled: paywall, devPremium: false),
            ),
          ),
          storeCollectionsProvider(pair).overrideWith((ref) async => sections),
          storePreviewProvider('cafe').overrideWith((ref) async {
            if (previewError != null) throw previewError;
            return preview(16);
          }),
          storePreviewProvider('interview').overrideWith((ref) async => preview(22)),
        ],
        child: const MaterialApp(
          locale: Locale('ru'),
          localizationsDelegates: AppLocalizations.localizationsDelegates,
          supportedLocales: [Locale('ru')],
          home: Scaffold(body: StoreView(bottomInset: 0)),
        ),
      ),
    );
  }

  /// The same host as [pump], on a caller-supplied catalogue — the pair badge's answer depends on
  /// which decks are on the shelf, not on the fixed two the other tests share.
  Future<void> pumpWith(WidgetTester tester, List<StoreSection> catalogue) => tester.pumpWidget(
    ProviderScope(
      overrides: [
        authControllerProvider.overrideWith(() => _FakeAuth(user())),
        featureFlagsProvider.overrideWith(
          () => _FakeFlags(
            FeatureFlags(storeEnabled: true, paywallEnabled: true, devPremium: false),
          ),
        ),
        storeCollectionsProvider(pair).overrideWith((ref) async => catalogue),
      ],
      child: const MaterialApp(
        locale: Locale('ru'),
        localizationsDelegates: AppLocalizations.localizationsDelegates,
        supportedLocales: [Locale('ru')],
        home: Scaffold(body: StoreView(bottomInset: 0)),
      ),
    ),
  );

  testWidgets('renders topic sections and cards with word counts', (tester) async {
    await pump(tester);
    await tester.pumpAndSettle();

    expect(find.text('EVERYDAY'), findsOneWidget);
    expect(find.text('WORK'), findsOneWidget);
    expect(find.text('Cafe'), findsOneWidget);
    expect(find.text('Job interview'), findsOneWidget);
    // «N слов · CEFR» line (кадр 2.8).
    expect(find.text('16 слов · A2'), findsOneWidget);
    expect(find.text('22 слова · B1–B2'), findsOneWidget);
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

    await tester.ensureVisible(find.text('Job interview'));
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

    await tester.ensureVisible(find.text('Cafe'));
    await tester.pumpAndSettle();
    await tester.tap(find.text('Cafe'));
    await tester.pumpAndSettle();

    expect(find.text('Добавить в мои'), findsOneWidget);
    expect(find.text('Доступно с Premium'), findsNothing);
    // «Что внутри» teaser: term — translation rows + «и ещё N слов» (16 total − 3 shown = 13).
    expect(find.text('appointment'), findsOneWidget);
    expect(find.text('приём у врача'), findsOneWidget);
    expect(find.textContaining('ещё 13'), findsOneWidget);
  });

  testWidgets(
    'premium set preview shows the term list too (value showcase); gate only on the CTA',
    (tester) async {
      await pump(tester);
      await tester.pumpAndSettle();

      await tester.ensureVisible(find.text('Job interview'));
      await tester.pumpAndSettle();
      await tester.tap(find.text('Job interview'));
      await tester.pumpAndSettle();

      // Terms are visible even for the locked premium set…
      expect(find.text('appointment'), findsOneWidget);
      expect(find.textContaining('ещё 19'), findsOneWidget); // 22 − 3
      // …and the lock is only on adding.
      expect(find.text('Доступно с Premium'), findsOneWidget);
    },
  );

  testWidgets('offline: preview sheet shows no list, CTA still there', (tester) async {
    await pump(tester, previewError: Exception('offline'));
    await tester.pumpAndSettle();

    await tester.ensureVisible(find.text('Cafe'));
    await tester.pumpAndSettle();
    await tester.tap(find.text('Cafe'));
    await tester.pumpAndSettle();

    expect(find.text('appointment'), findsNothing);
    expect(find.text('Добавить в мои'), findsOneWidget);
  });

  testWidgets('preview 404 (endpoint missing): skeleton collapses to no list', (tester) async {
    final notFound = DioException(
      requestOptions: RequestOptions(path: '/store/collections/cafe/preview'),
      response: Response(
        requestOptions: RequestOptions(path: '/store/collections/cafe/preview'),
        statusCode: 404,
      ),
      type: DioExceptionType.badResponse,
    );
    await pump(tester, previewError: notFound);
    await tester.pumpAndSettle();

    await tester.ensureVisible(find.text('Cafe'));
    await tester.pumpAndSettle();
    await tester.tap(find.text('Cafe'));
    await tester.pumpAndSettle();

    // No skeleton bars left hanging, no list — just the sheet + its CTA.
    expect(find.text('appointment'), findsNothing);
    expect(find.text('Что внутри'.toUpperCase()), findsNothing);
    expect(find.text('Добавить в мои'), findsOneWidget);
  });

  group('справочник на карточке стора (наряд A-4.1 Ч.2)', () {
    // The feed carries `is_reference` since Ч.2. Until then it did not, and a Chinese deck would
    // have shown a pair of flags — training promised where none exists (DECISIONS пп. 84, 136).

    testWidgets('a phrasebook deck says so instead of naming a pair', (tester) async {
      await pumpWith(tester, [
        StoreSection(
          topic: 'Everyday',
          items: [col('zh', '中文', 'Everyday', 12, 'A1', false, learned: 'zh', reference: true)],
        ),
      ]);
      await tester.pumpAndSettle();

      expect(find.text('СПРАВОЧНИК'), findsOneWidget);
      expect(_badgeFlags, findsNothing);
    });

    testWidgets('an ordinary deck still names its pair', (tester) async {
      await pumpWith(tester, [
        StoreSection(
          topic: 'Everyday',
          items: [col('cafe', 'Cafe', 'Everyday', 16, 'A2', false, reference: false)],
        ),
      ]);
      await tester.pumpAndSettle();

      expect(find.text('СПРАВОЧНИК'), findsNothing);
      expect(_badgeFlags, findsNWidgets(2));
    });

    testWidgets('an older server that says nothing is read as «not a phrasebook»', (tester) async {
      // The only safe default: a null means a server from before the flag existed, and such a
      // server has no zh/ja deck to be wrong about. The other way round would stamp «справочник»
      // across the whole catalogue.
      await pumpWith(tester, [
        StoreSection(
          topic: 'Everyday',
          items: [col('cafe', 'Cafe', 'Everyday', 16, 'A2', false)],
        ),
      ]);
      await tester.pumpAndSettle();

      expect(find.text('СПРАВОЧНИК'), findsNothing);
      expect(_badgeFlags, findsNWidgets(2));
    });
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