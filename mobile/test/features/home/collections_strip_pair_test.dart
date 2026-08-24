import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';

import 'package:eng_std/data/models.dart';
import 'package:eng_std/features/training/collections_strip.dart';
import 'package:eng_std/l10n/app_localizations.dart';
import 'package:eng_std/ui/mini_flag.dart';
import 'package:eng_std/ui/pair_badge.dart';

/// The pair badge on the FOURTH surface a collection is seen on (DECISIONS п. 148).
///
/// The shelf, the store card and the collection header all name the pair; the home carousel did not,
/// and a label that is present on three surfaces out of four reads as a property of the shelf rather
/// than of the collection. The source is the same one the shelf uses — the collection's own
/// `target_lang → source_lang` off the local mirror, and its derived reference flag — so the two
/// cannot say different things about one collection.
void main() {
  WordCollection collection({
    required String id,
    required String title,
    String learned = 'en',
    String support = 'ru',
    bool reference = false,
  }) => WordCollection(
    id: id,
    title: title,
    wordsCount: 4,
    type: 'custom',
    source: 'user',
    sourceLang: support,
    targetLang: learned,
    isReference: reference,
  );

  Future<void> pump(WidgetTester tester, List<WordCollection> collections) async {
    await tester.pumpWidget(
      MaterialApp(
        locale: const Locale('ru'),
        localizationsDelegates: AppLocalizations.localizationsDelegates,
        supportedLocales: const [Locale('ru'), Locale('en')],
        home: Scaffold(
          body: CollectionsStrip(collections: collections, progress: const {}),
        ),
      ),
    );
    await tester.pump();
  }

  testWidgets('every card in the carousel names its pair', (tester) async {
    await pump(tester, [
      collection(id: 'c1', title: 'Аптека'),
      collection(id: 'c2', title: 'Praca', learned: 'pl'),
    ]);

    expect(tester.takeException(), isNull);
    expect(find.byType(PairBadge), findsNWidgets(2));
    // Flags, not codes — the accepted edition (п. 148): two per pair.
    expect(find.byType(MiniFlag), findsNWidgets(4));
  });

  testWidgets('a phrasebook says so instead of showing a pair it cannot train', (tester) async {
    await pump(tester, [collection(id: 'c1', title: '中文', learned: 'zh', reference: true)]);

    expect(find.text('СПРАВОЧНИК'), findsOneWidget);
    expect(find.byType(MiniFlag), findsNothing);
  });

  testWidgets('the card is exactly as tall with a badge as the strip reserves', (tester) async {
    // The carousel is a fixed-height row: a badge that grew the card would clip the progress bar
    // off the bottom, which is the shape of QA-OBS-11 all over again.
    await pump(tester, [collection(id: 'c1', title: 'Ordering Takeaway Coffee')]);

    expect(tester.takeException(), isNull);
  });
}
