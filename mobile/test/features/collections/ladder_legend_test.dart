import 'package:drift/native.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';

import 'package:eng_std/data/local/app_database.dart';
import 'package:eng_std/data/models.dart';
import 'package:eng_std/data/practice/learning_ladder.dart';
import 'package:eng_std/data/providers.dart';
import 'package:eng_std/features/collections/ladder_legend.dart';
import 'package:eng_std/features/collections/my_words_screen.dart';
import 'package:eng_std/l10n/app_localizations.dart';

/// Ч.4 — «пять безымянных точек» get a legend.
///
/// The marks are the only place in the pool list that says how far a word has come, and they said
/// it in a language nobody had been taught. The words already existed on the expanded word card, so
/// this is a legend and not a new vocabulary: the same five strings, one tap from the marks.
void main() {
  Future<void> pump(WidgetTester tester, {int step = LearningLadder.stepAssembly}) async {
    final db = AppDatabase.forTesting(NativeDatabase.memory());
    addTearDown(db.close);
    await tester.pumpWidget(
      ProviderScope(
        overrides: [
          appDatabaseProvider.overrideWithValue(db),
          collectionsProvider.overrideWith((ref) => Stream.value(const <WordCollection>[])),
          poolProvider.overrideWith(
            (ref) => Stream.value([
              PoolWordRow(
                term: Term(
                  id: 't1',
                  termText: 'invoice',
                  translation: 'счёт',
                  type: 'word',
                  updatedAt: DateTime(2026),
                ),
                position: LadderPosition(
                  acquisition: Acquisition.graduated,
                  successfulReviews: 0,
                  enrolled: true,
                ),
                collectionIds: const [],
                enrolledAt: DateTime(2026),
              ),
            ]),
          ),
        ],
        child: MaterialApp(
          locale: const Locale('ru'),
          localizationsDelegates: AppLocalizations.localizationsDelegates,
          supportedLocales: const [Locale('ru')],
          home: const MyWordsScreen(),
        ),
      ),
    );
    await tester.pumpAndSettle();
  }

  testWidgets('tapping the dots names all five rungs, in order', (tester) async {
    await pump(tester);

    expect(find.byKey(ladderDotsLegendKey), findsOneWidget);
    await tester.tap(find.byKey(ladderDotsLegendKey));
    await tester.pumpAndSettle();

    expect(find.text('Что значат точки'), findsOneWidget);
    expect(find.text('Ступень 1 из 5: знакомство'), findsOneWidget);
    expect(find.text('Ступень 2 из 5: узнавание'), findsOneWidget);
    expect(find.text('Ступень 3 из 5: сборка'), findsOneWidget);
    expect(find.text('Ступень 4 из 5: написание'), findsOneWidget);
    expect(find.text('Ступень 5 из 5: диктант'), findsOneWidget);
  });

  testWidgets('the legend also explains the ONE mark that is not a rung', (tester) async {
    // A «знаю» word never walked the ladder, and the list draws it a dash for exactly that reason.
    await pump(tester);

    await tester.tap(find.byKey(ladderDotsLegendKey));
    await tester.pumpAndSettle();

    expect(find.textContaining('помечено «знаю»'), findsOneWidget);
  });

  testWidgets('the dots are their own tap target — the row still opens the word', (tester) async {
    await pump(tester);

    await tester.tap(find.text('invoice'));
    await tester.pumpAndSettle();

    // The word card, not the legend.
    expect(find.text('Что значат точки'), findsNothing);
    expect(find.text('счёт'), findsWidgets);
  });
}
