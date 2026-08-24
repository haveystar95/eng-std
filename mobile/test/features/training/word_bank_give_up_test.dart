import 'package:drift/native.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';

import 'package:eng_std/data/local/app_database.dart';
import 'package:eng_std/data/models.dart';
import 'package:eng_std/data/providers.dart';
import 'package:eng_std/features/training/session/session_exercise.dart';
import 'package:eng_std/features/training/session/session_grading.dart';
import 'package:eng_std/l10n/app_localizations.dart';

/// «Не помню» belongs to BOTH assembly modes, through the one lapse channel.
///
/// `scramble` had the button and `word_bank` did not, which made «я не помню это слово» sayable
/// about a sentence and unsayable about a word. The only exit from a word_bank card was to assemble
/// something wrong on purpose — and a wrong answer and a blank one are not the same statement:
/// one says «I thought it was this», the other says «I have nothing». The server grades the empty
/// response as the lapse it is, which is why this is one code path and not a second verdict.
void main() {
  const termId = '01M00WHZFYJSYW76Z4B4BBASXC';

  SessionCard wordBank() => SessionCard(
    termId: termId,
    mode: ExerciseMode.wordBank,
    type: 'word',
    prompt: 'счёт',
    answer: 'invoice',
    chips: const ['i', 'n', 'v', 'o', 'i', 'c', 'e'],
    ladderStep: 3,
  );

  SessionCard scramble() => SessionCard(
    termId: termId,
    mode: ExerciseMode.scramble,
    type: 'phrase',
    prompt: 'Они прислали счёт по почте.',
    answer: 'They sent the invoice by email',
    chips: const ['They', 'sent', 'the', 'invoice', 'by', 'email'],
    ladderStep: 3,
  );

  late List<SessionAnswer> answers;

  setUp(() => answers = []);

  Future<void> onSpeak(String text, {bool slow = false}) async {}

  Widget host(SessionCard c) => ProviderScope(
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
      home: MediaQuery(
        data: const MediaQueryData(disableAnimations: true),
        child: Scaffold(
          body: SingleChildScrollView(
            child: SessionExerciseCard(
              card: c,
              autoPronounce: false,
              onAnswered: answers.add,
              onSpeak: onSpeak,
              showDue: false,
            ),
          ),
        ),
      ),
    ),
  );

  testWidgets('word_bank offers «Не помню» before anything is assembled', (tester) async {
    await tester.pumpWidget(host(wordBank()));
    await tester.pumpAndSettle();

    expect(find.text('Не помню'), findsOneWidget);
  });

  testWidgets('scramble still offers it — this is one channel, not two', (tester) async {
    await tester.pumpWidget(host(scramble()));
    await tester.pumpAndSettle();

    expect(find.text('Не помню'), findsOneWidget);
  });

  testWidgets('the tap commits an EMPTY answer, not a wrong guess', (tester) async {
    await tester.pumpWidget(host(wordBank()));
    await tester.pumpAndSettle();

    await tester.tap(find.text('Не помню'));
    await tester.pumpAndSettle();

    expect(answers, hasLength(1));
    // Empty is the whole point: the server reads «nothing offered» as a lapse, and an assembled
    // wrong guess would have been a different (and untrue) statement about what the learner knows.
    expect(answers.single.response, isEmpty);
    expect(answers.single.verdict, LocalCheck.wrong);
  });

  testWidgets('it goes once the card is answered — there is no second exit', (tester) async {
    await tester.pumpWidget(host(wordBank()));
    await tester.pumpAndSettle();

    await tester.tap(find.text('Не помню'));
    await tester.pumpAndSettle();

    expect(find.text('Не помню'), findsNothing);
    expect(answers, hasLength(1));
  });
}
