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
/// The chips in the TRAY, in the order the card dealt them.
///
/// `_WordChip` is private to the screen's own library, so it cannot be named by type here. Found by
/// its runtime type instead of by its text on purpose: a placed chip's letter also appears in the
/// assembly line, and `invoice` has two `i`s — finding by text would be ambiguous twice over.
final _trayChip = find.byWidgetPredicate((w) => w.runtimeType.toString() == '_WordChip');

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
              speechLocaleId: 'en_US',
              answerLang: 'en',
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

  // ── the trainer's two shapes name themselves (BUGFIX-2 Ч.2б D2) ──────────────────────────────
  //
  // A single word is assembled from its LETTERS and a phrase from its WORDS — the branch that was
  // unreachable until the gate stopped asking for two words. The instruction has to say which, or
  // «собери из слов» stands over a row of single letters and describes a card the learner is not
  // looking at (seen on the owner's run, `docs/shots/pract1/04`).

  testWidgets('a single word says «собери из букв», and its empty line too', (tester) async {
    await tester.pumpWidget(host(wordBank()));
    await tester.pumpAndSettle();

    expect(find.textContaining('собери из букв'), findsOneWidget);
    expect(find.text('Собери из букв ниже'), findsOneWidget);
    expect(find.textContaining('собери из слов'), findsNothing);
  });

  testWidgets('the assembled letters commit as ONE WORD, not as spaced tokens', (tester) async {
    // The bug the owner met on the very first phone run: the assembly line joined its chips with a
    // space — right for word chips, and for letter chips it uploaded «d o l p h i n» for a correctly
    // spelled `dolphin`, which the grader then marked wrong. The key is the TERM, and the term has
    // no spaces in it.
    await tester.pumpWidget(host(wordBank()));
    await tester.pumpAndSettle();

    // Tapped by POSITION in the tray, not by letter: `invoice` has two `i`s, and once a chip is
    // placed its letter also stands in the assembly line, so finding by text would be ambiguous
    // twice over. The fixture's chips are already in the answer's order.
    for (var i = 0; i < 'invoice'.length; i++) {
      await tester.tap(_trayChip.at(i));
      await tester.pumpAndSettle();
    }

    await tester.tap(find.text('Проверить'));
    await tester.pumpAndSettle();

    expect(answers, hasLength(1));
    expect(answers.single.response, 'invoice');
    expect(answers.single.verdict, LocalCheck.correct);
  });

  testWidgets('a PHRASE still commits with the spaces that separate its words', (tester) async {
    final phrase = SessionCard(
      termId: termId,
      mode: ExerciseMode.wordBank,
      type: 'phrase',
      prompt: 'стойка регистрации',
      answer: 'front desk',
      chips: const ['desk', 'front'],
      ladderStep: 3,
    );

    await tester.pumpWidget(host(phrase));
    await tester.pumpAndSettle();

    // The tray is shuffled ('desk' first here), so the words are tapped in the order the ANSWER
    // wants them, found by their position in the tray.
    await tester.tap(_trayChip.at(1)); // front
    await tester.pumpAndSettle();
    await tester.tap(_trayChip.at(0)); // desk
    await tester.pumpAndSettle();
    await tester.tap(find.text('Проверить'));
    await tester.pumpAndSettle();

    expect(answers.single.response, 'front desk');
    expect(answers.single.verdict, LocalCheck.correct);
  });

  testWidgets('a PHRASE still says «собери из слов» — nothing moved for it', (tester) async {
    final phrase = SessionCard(
      termId: termId,
      mode: ExerciseMode.wordBank,
      type: 'phrase',
      prompt: 'стойка регистрации',
      answer: 'front desk',
      chips: const ['desk', 'front'],
      ladderStep: 3,
    );

    await tester.pumpWidget(host(phrase));
    await tester.pumpAndSettle();

    expect(find.textContaining('собери из слов'), findsOneWidget);
    expect(find.text('Собери из слов ниже'), findsOneWidget);
  });
}
