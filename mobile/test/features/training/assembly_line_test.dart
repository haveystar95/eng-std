import 'package:drift/native.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';

import 'package:eng_std/data/local/app_database.dart';
import 'package:eng_std/data/models.dart';
import 'package:eng_std/data/providers.dart';
import 'package:eng_std/features/training/session/session_exercise.dart';
import 'package:eng_std/l10n/app_localizations.dart';
import 'package:eng_std/ui/ui.dart';

/// The assembly line's EMPTY state — the one both assembling modes open on.
///
/// It regressed silently on device: the line is the only thing telling you where the words go, and
/// it was drawing at zero width (a bare SizedBox in a start-aligned Column), so a scramble card
/// opened as a blank box. Nothing failed — it just looked unfinished. Pinned here for both modes.
void main() {
  Widget host(SessionCard card) => ProviderScope(
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
      supportedLocales: const [Locale('ru'), Locale('en')],
      home: Scaffold(
        body: SingleChildScrollView(
          child: SessionExerciseCard(
            card: card,
            speechLocaleId: 'en_US',
            answerLang: 'en',
            autoPronounce: false,
            onAnswered: (_) {},
            onSpeak: (String text, {bool slow = false}) async {},
            // Nothing here is about the next-due line, and it subscribes to a drift stream
            // whose teardown leaves a pending timer — free practice hides it anyway.
            showDue: false,
          ),
        ),
      ),
    ),
  );

  SessionCard scrambleCard() => SessionCard(
    termId: 'T1',
    mode: ExerciseMode.scramble,
    type: 'word',
    prompt: 'Привет, Том! Как дела?',
    answer: "Hey, Tom! How's it going?",
    example: "Hey, Tom! How's it going?",
    exampleTranslation: 'Привет, Том! Как дела?',
    chips: const ['How\'s', 'Tom!', 'Hey,', 'going', 'it'],
  );

  SessionCard wordBankCard() => SessionCard(
    termId: 'T2',
    mode: ExerciseMode.wordBank,
    type: 'phrase',
    prompt: 'рассказать о себе',
    answer: 'talk about myself',
    chips: const ['myself', 'talk', 'about'],
  );

  /// The 1.5-px rule the line draws itself with, and how wide it actually rendered.
  Finder assemblyLine() => find.byWidgetPredicate((w) {
    if (w is! Container) return false;
    final d = w.decoration;
    return d is BoxDecoration && d.border?.bottom.width == 1.5;
  });

  testWidgets('scramble opens with a full-width line and says where the words go', (tester) async {
    await tester.pumpWidget(host(scrambleCard()));
    await tester.pump();

    expect(find.text('Собери из слов ниже'), findsOneWidget);

    final line = tester.getSize(assemblyLine().first);
    final card = tester.getSize(find.byType(PaperCard).first);
    // "Full width" means the card's width minus its padding — not a collapsed zero-width box.
    expect(
      line.width,
      greaterThan(card.width * 0.8),
      reason: 'the empty line must span the card, or there is no visible drop zone',
    );
    expect(line.height, greaterThanOrEqualTo(30));
  });

  testWidgets('word_bank gets the same empty line — one widget, both modes', (tester) async {
    await tester.pumpWidget(host(wordBankCard()));
    await tester.pump();

    expect(find.text('Собери из слов ниже'), findsOneWidget);
    expect(tester.getSize(assemblyLine().first).width, greaterThan(200));
  });

  testWidgets('the hint disappears as soon as the first word is placed', (tester) async {
    await tester.pumpWidget(host(scrambleCard()));
    await tester.pump();

    await tester.tap(find.text('Hey,').last);
    await tester.pump();

    expect(find.text('Собери из слов ниже'), findsNothing);
    expect(find.text('Hey,'), findsNWidgets(2)); // the chip's faded copy + the placed word
  });

  testWidgets('«Не помню» leaves no hint under the verdict line', (tester) async {
    await tester.pumpWidget(host(scrambleCard()));
    await tester.pump();

    await tester.tap(find.text('Не помню'));
    await tester.pump();

    // The line is still empty, but the card is answered — telling the user to tap words now
    // would be instructions after the fact.
    expect(find.text('Собери из слов ниже'), findsNothing);
  });
}
