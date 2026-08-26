import 'package:drift/drift.dart' show Value;
import 'package:drift/native.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';

import 'package:eng_std/data/local/app_database.dart';
import 'package:eng_std/data/providers.dart';
import 'package:eng_std/features/collections/collection_detail_screen.dart';
import 'package:eng_std/l10n/app_localizations.dart';
import 'package:eng_std/ui/mini_flag.dart';
import 'package:eng_std/ui/pair_badge.dart';

/// A PHRASEBOOK on screen (DECISIONS пп. 84, 136).
///
/// zh and ja carry no trainers at all, so their collections are read and heard, never studied. The
/// server enforces it at both doors — `PUT /pool/terms/{id}` answers 422 `reference_language_term`
/// and `/triage/batch` refuses the swipe — which is exactly why the screen must not offer either:
/// a button whose only outcome is an error is worse than no button.
///
/// THERE IS NO SUCH COLLECTION IN THE DEV DATABASE YET, so these are the states' only cover. That
/// is the whole reason the file exists: the flag is read from `/sync`, and until somebody makes a
/// Chinese folder the live run cannot show any of this.
void main() {
  const collectionId = '01M0T1SVG4MVEMN4J2MBD9F4HZ';
  const termId = '01M0T1SVPK3VQWR25ADACRZ6HY';

  Future<void> seed(AppDatabase db, {required bool reference, required String learned}) async {
    await db
        .into(db.collections)
        .insert(
          CollectionsCompanion.insert(
            id: collectionId,
            updatedAt: DateTime.utc(2026, 8, 24),
            title: const Value('中文'),
            sourceLang: const Value('ru'),
            targetLang: Value(learned),
            itemsCount: const Value(1),
            type: const Value('custom'),
            source: const Value('user'),
            isReference: Value(reference),
          ),
        );
    await db
        .into(db.terms)
        .insert(
          TermsCompanion.insert(
            id: termId,
            updatedAt: DateTime.utc(2026, 8, 24),
            termText: const Value('苹果'),
            translation: const Value('яблоко'),
          ),
        );
    await db
        .into(db.collectionItems)
        .insert(
          CollectionItemsCompanion.insert(
            collectionId: collectionId,
            termId: termId,
            updatedAt: DateTime.utc(2026, 8, 24),
          ),
        );
  }

  Future<void> pump(
    WidgetTester tester, {
    required bool reference,
    String learned = 'zh',
  }) async {
    late AppDatabase db;
    await tester.pumpWidget(
      ProviderScope(
        overrides: [
          appDatabaseProvider.overrideWith((ref) {
            db = AppDatabase.forTesting(NativeDatabase.memory());
            ref.onDispose(db.close);
            return db;
          }),
        ],
        child: const MaterialApp(
          locale: Locale('ru'),
          localizationsDelegates: AppLocalizations.localizationsDelegates,
          supportedLocales: [Locale('ru'), Locale('en')],
          home: CollectionDetailScreen(collectionId: collectionId, title: '中文'),
        ),
      ),
    );
    await seed(db, reference: reference, learned: learned);
    await tester.pump();
    await tester.pump();
  }

  /// Tear the tree down INSIDE the test, then give drift's stream store its cleanup frame.
  ///
  /// The screen holds live drift streams; cancelling one schedules a zero-delay timer
  /// (`StreamQueryStore.markAsClosed`), and a test that simply ends leaves that timer armed — which
  /// the binding reports as a leak and which has nothing to do with the code under test.
  Future<void> close(WidgetTester tester) async {
    await tester.pumpWidget(const SizedBox.shrink());
    await tester.pump(const Duration(milliseconds: 50));
  }

  testWidgets('says it is a phrasebook, and says why there is nothing to press', (tester) async {
    await pump(tester, reference: true);

    expect(tester.takeException(), isNull);
    expect(find.text('СПРАВОЧНИК'), findsOneWidget);
    expect(
      find.textContaining('Тренажёров для этого языка пока нет'),
      findsOneWidget,
      reason: 'the absence of buttons has to be explained, or the screen reads as broken',
    );
    await close(tester);
  });

  testWidgets('offers no training, no triage and no free practice', (tester) async {
    await pump(tester, reference: true);

    expect(find.text('Свободная тренировка'), findsNothing);
    expect(find.textContaining('Разобрать'), findsNothing);
    expect(find.textContaining('Учить'), findsNothing);
    expect(find.textContaining('Повторить'), findsNothing);
    await close(tester);
  });

  testWidgets('still shows the word and its translation — reading is the whole product here', (
    tester,
  ) async {
    await pump(tester, reference: true);

    expect(find.text('苹果'), findsOneWidget);
    expect(find.text('яблоко'), findsOneWidget);
    await close(tester);
  });

  testWidgets('an ordinary collection is untouched — it names its pair and keeps its buttons', (
    tester,
  ) async {
    await pump(tester, reference: false, learned: 'en');

    expect(tester.takeException(), isNull);
    expect(find.text('СПРАВОЧНИК'), findsNothing);
    expect(find.byType(PairBadge), findsOneWidget);
    // One flag — the language being LEARNED — and the two codes beside it (Ч.5а).
    expect(find.byType(MiniFlag), findsOneWidget);
    expect(find.text('EN'), findsOneWidget);
    expect(find.text('RU'), findsOneWidget);
    expect(find.text('Свободная тренировка'), findsOneWidget);
    await close(tester);
  });
}
