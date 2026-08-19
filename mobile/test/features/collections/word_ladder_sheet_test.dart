import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';

import 'package:eng_std/data/models.dart';
import 'package:eng_std/data/practice/learning_ladder.dart';
import 'package:eng_std/features/collections/word_ladder_sheet.dart';
import 'package:eng_std/l10n/app_localizations.dart';
import 'package:eng_std/ui/ui.dart';

/// Widget test: the expanded word card (кадр 16e) offers ONE action, and which one depends on where
/// the word stands.
///
/// Out of the POOL — «Учить это слово», and nothing else: every session is assembled from the pool,
/// so offering a training run for a word outside it would be offering an empty session.
///
/// In the pool — «Тренировать слово», INERT for a word that has never been introduced (free practice
/// introduces nothing, so a word at rung 0 has nothing for it to drill), plus the quiet way back
/// out. An action that opens an empty session is worse than one greyed out with a reason.
void main() {
  Word word({required int? ladderStep, bool isKnown = false, bool enrolled = true}) => Word(
        termId: '01KZETAAA50EMHCN6SP80T8DHC',
        term: 'reservation',
        translation: 'бронь',
        type: 'word',
        example: 'I have a reservation for tonight.',
        ladderStep: ladderStep,
        isKnown: isKnown,
        enrolled: enrolled,
      );

  /// Opens the sheet and returns the three counters the card can move.
  Future<({int Function() trained, int Function() enrolled, int Function() unenrolled})> pumpSheet(
    WidgetTester tester,
    Word w,
  ) async {
    var trained = 0, enrolled = 0, unenrolled = 0;
    await tester.pumpWidget(MaterialApp(
      locale: const Locale('ru'),
      localizationsDelegates: AppLocalizations.localizationsDelegates,
      supportedLocales: const [Locale('ru')],
      home: Builder(
        builder: (context) => Scaffold(
          body: Center(
            child: ElevatedButton(
              onPressed: () => showWordLadderSheet(
                context: context,
                word: w,
                onSpeak: () {},
                onTrain: () => trained++,
                onEnroll: () => enrolled++,
                onUnenroll: () => unenrolled++,
              ),
              child: const Text('open'),
            ),
          ),
        ),
      ),
    ));

    await tester.tap(find.text('open'));
    await tester.pumpAndSettle();
    return (trained: () => trained, enrolled: () => enrolled, unenrolled: () => unenrolled);
  }

  testWidgets('a never-introduced word in the pool: the action is disabled and explains itself', (tester) async {
    await pumpSheet(tester, word(ladderStep: LearningLadder.stepIntro));

    expect(find.text('Тренировать слово'), findsOneWidget);
    expect(
      find.textContaining('после знакомства'),
      findsOneWidget,
      reason: 'a disabled action with no reason reads as a bug',
    );

    // Inert in the tree, not merely painted grey — and inert where it counts, so the disabled state
    // survives someone later re-styling the button.
    final ink = tester.widget<InkWell>(find.descendant(
      of: find.byType(PrimaryButton),
      matching: find.byType(InkWell),
    ));
    expect(ink.onTap, isNull);
  });

  testWidgets('tapping the disabled action starts nothing', (tester) async {
    final counters = await pumpSheet(tester, word(ladderStep: LearningLadder.stepIntro));
    await tester.tap(find.text('Тренировать слово'));
    await tester.pumpAndSettle();

    expect(counters.trained(), 0, reason: 'a session here would come back with no cards');
  });

  testWidgets('an introduced word trains, with no explanation in the way', (tester) async {
    final counters = await pumpSheet(tester, word(ladderStep: LearningLadder.stepRecognitionForward));

    expect(find.textContaining('после знакомства'), findsNothing);

    await tester.tap(find.text('Тренировать слово'));
    await tester.pumpAndSettle();
    expect(counters.trained(), 1);
  });

  testWidgets('a «знаю» word IN the pool is outside the ladder, not at the bottom of it — it trains',
      (tester) async {
    // `known` has no rung at all — reading that null as rung 0 would lock a word the learner claims
    // to know out of the very practice that would check the claim.
    final counters = await pumpSheet(tester, word(ladderStep: null, isKnown: true));

    expect(find.textContaining('после знакомства'), findsNothing);

    await tester.tap(find.text('Тренировать слово'));
    await tester.pumpAndSettle();
    expect(counters.trained(), 1);
  });

  testWidgets('a word in the catalogue offers only «Учить это слово»', (tester) async {
    final counters = await pumpSheet(tester, word(ladderStep: null, enrolled: false));

    expect(find.text('Учить это слово'), findsOneWidget);
    // No training run and no ladder: both would be claims about a word nobody has started.
    expect(find.text('Тренировать слово'), findsNothing);
    expect(find.text('ЛЕСТНИЦА СЛОВА'), findsNothing);
    expect(find.textContaining('в каталоге'), findsOneWidget);

    await tester.tap(find.text('Учить это слово'));
    await tester.pumpAndSettle();
    expect(counters.enrolled(), 1);
    expect(counters.trained(), 0);
  });

  testWidgets('a «знаю» word that never entered the pool is offered study, not practice', (tester) async {
    // The verdict said «не учи это», so the pair is out of the pool and no session will deal it.
    // Checking the claim now means taking the word into study — which is what the card offers.
    final counters = await pumpSheet(tester, word(ladderStep: null, isKnown: true, enrolled: false));

    expect(find.text('Учить это слово'), findsOneWidget);
    expect(find.text('Тренировать слово'), findsNothing);

    await tester.tap(find.text('Учить это слово'));
    await tester.pumpAndSettle();
    expect(counters.enrolled(), 1);
  });

  testWidgets('«Убрать из изучения» is confirmed, and the confirmation says it is a pause',
      (tester) async {
    final counters = await pumpSheet(tester, word(ladderStep: LearningLadder.stepAssembly));

    await tester.tap(find.text('Убрать из изучения'));
    await tester.pumpAndSettle();

    // The wording carries the whole promise: a button read as a delete is a button nobody presses.
    expect(find.textContaining('можно вернуть в любой момент'), findsOneWidget);
    expect(counters.unenrolled(), 0, reason: 'nothing happens until the learner confirms');

    await tester.tap(find.text('Убрать'));
    await tester.pumpAndSettle();
    expect(counters.unenrolled(), 1);
  });

  testWidgets('cancelling the confirmation leaves the word in the pool', (tester) async {
    final counters = await pumpSheet(tester, word(ladderStep: LearningLadder.stepAssembly));

    await tester.tap(find.text('Убрать из изучения'));
    await tester.pumpAndSettle();
    await tester.tap(find.text('Отмена'));
    await tester.pumpAndSettle();

    expect(counters.unenrolled(), 0);
  });
}
