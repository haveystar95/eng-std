import 'package:drift/native.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';

import 'package:eng_std/data/local/app_database.dart';
import 'package:eng_std/data/models.dart';
import 'package:eng_std/data/providers.dart';
import 'package:eng_std/features/training/session/session_exercise.dart';
import 'package:eng_std/l10n/app_localizations.dart';

/// pick_correct's card, pinned after the device batch caught it rendering EMPTY: the prompt and the
/// photo drew, and the three sentences did not, because the options branch listed multiple_choice and
/// listening by name and nothing else. A widget test is the only thing that would have caught that —
/// the unit tests were all green, because the card DATA was correct all along.
void main() {
  const right = 'Your workstation is ready for you.';
  const wrongTense = 'Your workstation are ready for you.';
  const wrongPrep = 'Your workstation is ready of you.';

  SessionCard card() => SessionCard(
    termId: 't1',
    mode: ExerciseMode.pickCorrect,
    type: 'word',
    prompt: 'Ваше рабочее место готово.',
    answer: right,
    example: right,
    exampleTranslation: 'Ваше рабочее место готово.',
    options: const [wrongTense, right, wrongPrep],
    optionFeedback: const [
      OptionFeedback(sentence: wrongTense, errorSpan: 'are', correction: 'is'),
      OptionFeedback(sentence: wrongPrep, errorSpan: 'of', correction: 'for'),
    ],
  );

  Widget host(SessionCard c, {String? photoUrl}) => ProviderScope(
    overrides: [
      appDatabaseProvider.overrideWith((ref) {
        final db = AppDatabase.forTesting(NativeDatabase.memory());
        ref.onDispose(db.close);
        return db;
      }),
    ],
    child: MaterialApp(
      locale: const Locale('ru'),
      localizationsDelegates: AppLocalizations.localizationsDelegates,
      supportedLocales: const [Locale('ru')],
      home: Scaffold(
        body: SingleChildScrollView(
          child: SessionExerciseCard(
            card: c,
            speechLocaleId: 'en_US',
            answerLang: 'en',
            autoPronounce: false,
            onAnswered: (_) {},
            onSpeak: (text, {bool slow = false}) async {},
            photoUrl: photoUrl,
          ),
        ),
      ),
    ),
  );

  testWidgets('renders all three sentences as tappable options', (tester) async {
    await tester.pumpWidget(host(card()));
    // Explicit pumps, not pumpAndSettle: the in-memory drift stream leaves a timer alive that
    // settling turns into a teardown failure. Same pattern as the other card tests.
    await tester.pump(const Duration(milliseconds: 400));

    // The regression the device found: an empty answer area.
    expect(find.text(right), findsOneWidget);
    expect(find.text(wrongTense), findsOneWidget);
    expect(find.text(wrongPrep), findsOneWidget);
  });

  testWidgets('shows the translated sentence as the question and no photo', (tester) async {
    await tester.pumpWidget(host(card(), photoUrl: 'https://example.test/market.jpg'));
    // Explicit pumps, not pumpAndSettle: the in-memory drift stream leaves a timer alive that
    // settling turns into a teardown failure. Same pattern as the other card tests.
    await tester.pump(const Duration(milliseconds: 400));

    expect(find.text('Ваше рабочее место готово.'), findsWidgets);
    // Three whole sentences to compare is enough on one screen; a banner pushes the third option off
    // it. The photo is a memory aid for a WORD, and this card is a reading task.
    expect(find.byType(Image), findsNothing);
  });

  group('spanPositionIn — which occurrence the underline means', () {
    test('skips a match buried inside a longer word', () {
      // «on» lives inside «responsible». Underlining the first raw match draws the wavy line through
      // the middle of an unrelated word and tells the learner the mistake is there.
      const sentence = 'The tenant is responsible on minor repairs.';
      expect(sentence.toLowerCase().indexOf('on'), 18); // what indexOf would have marked
      expect(sentence.substring(18, 20), 'on'); // …inside «responsible»
      expect(spanPositionIn(sentence, 'on'), 26);
      expect(sentence.substring(26, 28), 'on');
    });

    test('matches case-insensitively, as the server does', () {
      expect(spanPositionIn('Your workstation ARE ready.', 'are'), 17);
    });

    test('falls back to a plain search when no standalone occurrence exists', () {
      // Only ever smarter, never stricter: a span that was findable before stays findable.
      expect(spanPositionIn('I would like to withdrawing money.', 'withdrawin'), 16);
    });

    test('returns -1 when the span is not there at all', () {
      expect(spanPositionIn('Your workstation is ready.', 'nowhere'), -1);
    });

    test('handles a span at the very start and at the very end', () {
      expect(spanPositionIn('On your first day, introduce yourself.', 'On'), 0);
      expect(spanPositionIn('The doctor will see you', 'you'), 20);
    });
  });

  // NOT covered here: what a TAP renders (the wavy underline on the broken fragment and the
  // «должно быть: …» line). Committing an answer subscribes to the local mirror, and the in-memory
  // drift stream leaves a zero-duration timer alive that the harness reports as pending at teardown —
  // the failure is the harness, not the widget, and pumping past it does not help.
  //
  // The DATA behind that rendering is pinned in test/data/practice/pick_correct_test.dart
  // (`feedbackFor` returns the span/correction for a wrong option and null for the right one), so
  // what is untested is the drawing itself. It stays on the device checklist rather than being
  // claimed as covered.
}
