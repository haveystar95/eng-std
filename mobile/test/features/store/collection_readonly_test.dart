import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';

import 'package:eng_std/data/models.dart';
import 'package:eng_std/data/providers.dart';
import 'package:eng_std/features/collections/collection_detail_screen.dart';
import 'package:eng_std/l10n/app_localizations.dart';

/// Read-only store sets in «Мои»: parsing the `is_subscribed` flag, the type-derived read-only
/// state, and the collection screen suppressing «Добавить слово» for a subscribed set.
void main() {
  group('WordCollection read-only derivation', () {
    WordCollection parse(Map<String, dynamic> extra) => WordCollection.fromJson({
          'id': 'c1',
          'title': 'Set',
          'source': 'curated',
          'type': 'custom',
          'items_count': 10,
          'source_lang': 'ru',
          'target_lang': 'en',
          ...extra,
        });

    test('own custom collection is editable', () {
      final c = parse({'type': 'custom'});
      expect(c.isOwned, isTrue);
      expect(c.readOnly, isFalse);
      expect(c.isSubscribed, isFalse);
    });

    test('system/shared sets are read-only (no flag needed)', () {
      expect(parse({'type': 'system'}).readOnly, isTrue);
      expect(parse({'type': 'shared'}).readOnly, isTrue);
    });

    test('is_subscribed flag is parsed additively and forces read-only', () {
      final c = parse({'type': 'custom', 'is_subscribed': true});
      expect(c.isSubscribed, isTrue);
      expect(c.readOnly, isTrue);
    });
  });

  group('CollectionDetailScreen read-only state', () {
    WordCollection collection(String type) => WordCollection(
          id: 'c1',
          title: 'Собеседование',
          source: 'curated',
          type: type,
          wordsCount: 1,
          sourceLang: 'ru',
          targetLang: 'en',
        );

    final word = Word(termId: 't1', term: 'cover letter', translation: 'сопроводительное', type: 'phrase');

    Future<void> pump(WidgetTester tester, {required String type}) {
      return tester.pumpWidget(ProviderScope(
        overrides: [
          authControllerProvider.overrideWith(() => _FakeAuth()),
          connectivityProvider.overrideWith((ref) => Stream.value(true)),
          collectionsProvider.overrideWith((ref) => Stream.value([collection(type)])),
          collectionWordsProvider('c1').overrideWith((ref) => Stream.value([word])),
          collectionDensityProvider('c1')
              .overrideWith((ref) => Stream.value(const CollectionDensity(confirmed: 0, familiar: 0, inProgress: 1))),
          collectionsProgressProvider.overrideWith((ref) => Stream.value(const <String, CollectionProgress>{})),
          untriagedByCollectionProvider.overrideWith((ref) => Stream.value(const <String, int>{})),
          learnableByCollectionProvider.overrideWith((ref) => Stream.value(const <String, int>{})),
        ],
        child: const MaterialApp(
          locale: Locale('ru'),
          localizationsDelegates: AppLocalizations.localizationsDelegates,
          supportedLocales: [Locale('ru')],
          home: CollectionDetailScreen(collectionId: 'c1', title: 'Собеседование'),
        ),
      ));
    }

    testWidgets('own collection shows «Добавить слово»', (tester) async {
      await pump(tester, type: 'custom');
      await tester.pumpAndSettle();
      expect(find.text('Добавить слово'), findsOneWidget);
    });

    testWidgets('subscribed store set hides «Добавить слово» (read-only)', (tester) async {
      await pump(tester, type: 'system');
      await tester.pumpAndSettle();
      expect(find.text('Добавить слово'), findsNothing);
      // The term still renders (full learning cycle is unaffected).
      expect(find.text('cover letter'), findsOneWidget);
    });
  });
}

class _FakeAuth extends AuthController {
  @override
  Future<AppUser?> build() async => AppUser(
        id: 'u1',
        name: 'D',
        profile: Profile(nativeLanguage: 'ru', targetLanguage: 'en', cefrLevel: 'B1', dailyGoal: 20),
      );
}
