import 'package:drift/native.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:lucide_icons_flutter/lucide_icons.dart';

import 'package:eng_std/data/local/app_database.dart';
import 'package:eng_std/data/models.dart';
import 'package:eng_std/data/providers.dart';
import 'package:eng_std/data/practice/practice_mode_selector.dart';
import 'package:eng_std/data/review_queue.dart';
import 'package:eng_std/features/training/session/session_exercise.dart';
import 'package:eng_std/features/training/session/session_grading.dart';
import 'package:eng_std/l10n/app_localizations.dart';

/// `description_match`: read what a word MEANS, in the language being learned, and tap the word.
///
/// The lesson this file exists for is the one rung-1 taught: parity is needed on the ANSWER PATH,
/// not only on the layout. A card can render perfectly and still upload the wrong thing — rung 1
/// looked right and uploaded a translation against a term id, folding every answer as `again`.
///
/// So the claim pinned here is: this card grades and uploads BY TEXT, exactly like an ordinary
/// multiple_choice, and carries no option ids at all. Identity grading exists because rung 1's
/// correct option is a TRANSLATION; here it is the WORD, so the text path is both available and
/// correct — and it is what keeps `acceptedVariants` meaningful and the device no stricter than the
/// server.
void main() {
  const termId = '01M00WHZFYJSYW76Z4B4BBASXC';
  const term = 'invoice';
  const description = 'A paper that says how much money you must pay for something.';

  SessionCard card() => SessionCard(
    termId: termId,
    mode: ExerciseMode.descriptionMatch,
    type: 'word',
    // The description IS the prompt. Not the translation, not the example.
    prompt: description,
    answer: term,
    example: 'They sent the invoice by email.',
    exampleTranslation: 'Они прислали счёт по почте.',
    options: const ['ledger', term, 'receipt', 'deposit'],
    ladderStep: 3,
  );

  group('the answer key', () {
    test('carries no option ids — nothing here is graded by identity', () {
      expect(card().isIdentityGraded, isFalse);
      expect(card().optionIdAt(1), isNull);
      expect(card().answer, term, reason: 'the key is the WORD, never an id');
    });

    test('does not ask for the example — the question is the description', () {
      expect(ExerciseMode.descriptionMatch.asksForExample(3), isFalse);
      expect(ExerciseMode.descriptionMatch.asksForExample(null), isFalse);
      expect(card().asksForExample, isFalse);
    });

    test('forgives no typos: a tap has no slipped key to forgive', () {
      expect(ExerciseMode.descriptionMatch.forgivesTypos, isFalse);
    });

    test('is a tapped card, not a typed or assembled one', () {
      expect(card().answeredByTapping, isTrue);
      expect(ExerciseMode.descriptionMatch.isTyped, isFalse);
      expect(ExerciseMode.descriptionMatch.isAssembled, isFalse);
      expect(ExerciseMode.descriptionMatch.isSentenceChoice, isFalse);
    });
  });

  group('the content gate', () {
    test('a term with no description cannot be dealt this card', () {
      final playable = TermPlayability.of(answer: term, example: 'They sent the invoice by email.');

      expect(playable.supports(ExerciseMode.descriptionMatch), isFalse);
    });

    test('a term with one can, with or without an example', () {
      // The description hangs off the WORD, not off the example — unlike the distractors.
      expect(
        TermPlayability.of(
          answer: term,
          description: description,
        ).supports(ExerciseMode.descriptionMatch),
        isTrue,
      );
      expect(
        TermPlayability.of(
          answer: term,
          example: 'They sent the invoice.',
          description: description,
        ).supports(ExerciseMode.descriptionMatch),
        isTrue,
      );
    });

    test('whitespace is not a description', () {
      expect(
        TermPlayability.of(
          answer: term,
          description: '   ',
        ).supports(ExerciseMode.descriptionMatch),
        isFalse,
      );
    });
  });

  group('the uploaded payload', () {
    test('carries the tapped WORD and the rung, never an id', () {
      final json = PendingReview(
        id: '01M00WHZFYJSYW76Z4B4BBAS11',
        termId: termId,
        exerciseMode: 'description_match',
        response: term,
        clientSeq: 7,
        answeredAt: '2026-08-21T13:00:00.000Z',
        ladderStep: 3,
      ).toBatchJson();

      expect(json['exercise_mode'], 'description_match');
      expect(json['response'], term);
      expect(json['ladder_step'], 3);
    });
  });

  group('the card on screen', () {
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

    testWidgets('shows the description and four words, and no Russian cue', (tester) async {
      await tester.pumpWidget(host(card()));
      await tester.pumpAndSettle();

      expect(find.text(description), findsOneWidget);
      for (final option in ['ledger', term, 'receipt', 'deposit']) {
        expect(find.text(option), findsOneWidget);
      }
      // The example's translation is on the card object but must not be printed as the question:
      // this is the one card in the session that shows no Russian at all.
      expect(find.text('Они прислали счёт по почте.'), findsNothing);
    });

    testWidgets('names its own task — not «выбери английский эквивалент»', (tester) async {
      await tester.pumpWidget(host(card()));
      await tester.pumpAndSettle();

      expect(find.textContaining('выбери слово по описанию'), findsOneWidget);
    });

    testWidgets('a correct tap is CORRECT and uploads the WORD', (tester) async {
      await tester.pumpWidget(host(card()));
      await tester.pumpAndSettle();

      await tester.tap(find.text(term));
      await tester.pumpAndSettle();

      expect(answers, hasLength(1));
      expect(answers.single.verdict, LocalCheck.correct);
      expect(
        answers.single.response,
        term,
        reason: 'text, not an id — this card is not identity-graded',
      );
    });

    testWidgets('a wrong tap is WRONG and uploads that option own text', (tester) async {
      await tester.pumpWidget(host(card()));
      await tester.pumpAndSettle();

      await tester.tap(find.text('receipt'));
      await tester.pumpAndSettle();

      expect(answers.single.verdict, LocalCheck.wrong);
      expect(answers.single.response, 'receipt');
    });

    testWidgets('the correct option gets its check mark', (tester) async {
      await tester.pumpWidget(host(card()));
      await tester.pumpAndSettle();

      expect(find.byIcon(LucideIcons.check), findsNothing);

      await tester.tap(find.text(term));
      await tester.pumpAndSettle();

      expect(find.byIcon(LucideIcons.check), findsNWidgets(2));
      expect(find.byIcon(LucideIcons.x), findsNothing);
    });
  });
}
