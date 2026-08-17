import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';

import 'package:eng_std/data/models.dart';
import 'package:eng_std/data/practice/learning_ladder.dart';
import 'package:eng_std/features/collections/word_ladder_sheet.dart';
import 'package:eng_std/l10n/app_localizations.dart';
import 'package:eng_std/ui/ui.dart';

/// Widget test: «Тренировать слово» in the expanded word card (кадр 16e) is INERT for a word that
/// has never been introduced.
///
/// Free practice introduces nothing, so a word at rung 0 has nothing for it to drill and the
/// session builder deals it no card at all. An action that opens an empty session is worse than one
/// that is greyed out with a reason — this pins the greyed-out state, and that tapping it does
/// nothing.
void main() {
  Word word({required int? ladderStep, bool isKnown = false}) => Word(
        termId: '01KZETAAA50EMHCN6SP80T8DHC',
        term: 'reservation',
        translation: 'бронь',
        type: 'word',
        example: 'I have a reservation for tonight.',
        ladderStep: ladderStep,
        isKnown: isKnown,
      );

  Future<int> pumpSheet(WidgetTester tester, Word w) async {
    var trained = 0;
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
              ),
              child: const Text('open'),
            ),
          ),
        ),
      ),
    ));

    await tester.tap(find.text('open'));
    await tester.pumpAndSettle();
    return trained;
  }

  testWidgets('a never-introduced word: the action is disabled and explains itself', (tester) async {
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
    final trained = await pumpSheet(tester, word(ladderStep: LearningLadder.stepIntro));
    await tester.tap(find.text('Тренировать слово'));
    await tester.pumpAndSettle();

    expect(trained, 0, reason: 'a session here would come back with no cards');
  });

  testWidgets('an introduced word trains, with no explanation in the way', (tester) async {
    var trained = 0;
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
                word: word(ladderStep: LearningLadder.stepRecognitionForward),
                onSpeak: () {},
                onTrain: () => trained++,
              ),
              child: const Text('open'),
            ),
          ),
        ),
      ),
    ));
    await tester.tap(find.text('open'));
    await tester.pumpAndSettle();

    expect(find.textContaining('после знакомства'), findsNothing);

    await tester.tap(find.text('Тренировать слово'));
    await tester.pumpAndSettle();
    expect(trained, 1);
  });

  testWidgets('a «знаю» word is outside the ladder, not at the bottom of it — it trains', (tester) async {
    var trained = 0;
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
                // `known` has no rung at all — reading that null as rung 0 would lock a word the
                // learner claims to know out of the very practice that would check the claim.
                word: word(ladderStep: null, isKnown: true),
                onSpeak: () {},
                onTrain: () => trained++,
              ),
              child: const Text('open'),
            ),
          ),
        ),
      ),
    ));
    await tester.tap(find.text('open'));
    await tester.pumpAndSettle();

    expect(find.textContaining('после знакомства'), findsNothing);

    await tester.tap(find.text('Тренировать слово'));
    await tester.pumpAndSettle();
    expect(trained, 1);
  });
}
