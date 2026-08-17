import 'package:eng_std/data/feature_flags.dart';
import 'package:eng_std/data/models.dart';
import 'package:eng_std/data/providers.dart';
import 'package:eng_std/data/store_providers.dart';
import 'package:eng_std/features/collections/collections_screen.dart';
import 'package:eng_std/l10n/app_localizations.dart';
import 'package:eng_std/theme/theme.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

/// Store showcase preview (кадр 2.8) — renders the real Collections tab with the store flag on and
/// realistic Russian store data (topic sections «Повседневная жизнь» / «Работа и карьера», CEFR
/// levels, a mix of free/premium). Not shipped; lives in `tool/` so its Russian sample data is
/// exempt from the cyrillic-guard. Run: `flutter run --target tool/store_preview.dart -d <device>`.
void main() {
  const pair = (source: 'ru', target: 'en');

  StoreCollection mk(String id, String title, String desc, String topic, int n, String cefr, bool premium,
          {bool subscribed = false}) =>
      StoreCollection(
        id: id,
        title: title,
        description: desc,
        topic: topic,
        sourceLang: 'ru',
        targetLang: 'en',
        isPremium: premium,
        isSubscribed: subscribed,
        itemsCount: n,
        cefr: cefr,
      );

  final sections = [
    StoreSection(topic: 'Повседневная жизнь', items: [
      mk('cafe', 'Кофейня', 'Заказ, оплата и мелочи у стойки', 'Повседневная жизнь', 16, 'A2', false),
      mk('market', 'Продукты и рынок', 'Названия, вес, торг и касса', 'Повседневная жизнь', 20, 'A2–B1', false),
      mk('rent', 'Аренда жилья', 'Осмотр, договор и залог', 'Повседневная жизнь', 18, 'B1', false, subscribed: true),
      mk('doctor', 'У врача', 'Жалобы, симптомы и запись', 'Повседневная жизнь', 16, 'A2–B1', false),
    ]),
    StoreSection(topic: 'Работа и карьера', items: [
      mk('interview', 'Собеседование', 'Рассказ о себе и переговоры об условиях', 'Работа и карьера', 22, 'B1–B2', true),
      mk('office', 'Офис и созвоны', 'Митинги, статусы и договорённости', 'Работа и карьера', 19, 'B1', true),
      mk('firstday', 'Первый день', 'Знакомство с командой и процессами', 'Работа и карьера', 14, 'A2', false),
    ]),
  ];

  runApp(ProviderScope(
    overrides: [
      authControllerProvider.overrideWith(_PreviewAuth.new),
      featureFlagsProvider.overrideWith(_PreviewFlags.new),
      collectionsProvider.overrideWith((ref) => Stream.value(const <WordCollection>[])),
      pendingGenerationsProvider.overrideWith((ref) => const Stream.empty()),
      storeCollectionsProvider(pair).overrideWith((ref) async => sections),
    ],
    child: const _PreviewApp(),
  ));
}

class _PreviewAuth extends AuthController {
  @override
  Future<AppUser?> build() async => AppUser(
        id: '01CCCCCCCCCCCCCCCCCCCCCCCC1',
        name: 'Denis',
        email: 'you@example.com',
        profile: Profile(nativeLanguage: 'ru', targetLanguage: 'en', cefrLevel: 'B1', dailyGoal: 20),
      );
}

class _PreviewFlags extends FeatureFlagsController {
  @override
  FeatureFlags build() => const FeatureFlags(storeEnabled: true, paywallEnabled: true, devPremium: false);
}

class _PreviewApp extends StatelessWidget {
  const _PreviewApp();

  @override
  Widget build(BuildContext context) {
    return MaterialApp(
      debugShowCheckedModeBanner: false,
      theme: buildAppTheme(),
      locale: const Locale('ru'),
      localizationsDelegates: AppLocalizations.localizationsDelegates,
      supportedLocales: AppLocalizations.supportedLocales,
      home: const Scaffold(body: CollectionsScreen(initialSegment: 1)),
    );
  }
}
