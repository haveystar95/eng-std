import 'dart:math';

import 'package:drift/native.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';

import 'package:eng_std/data/local/app_database.dart';
import 'package:eng_std/data/models.dart';
import 'package:eng_std/data/practice/learning_ladder.dart';
import 'package:eng_std/data/practice/local_session_builder.dart';
import 'package:eng_std/data/practice/practice_mode_selector.dart';
import 'package:eng_std/data/providers.dart';
import 'package:eng_std/features/training/session/session_exercise.dart';
import 'package:eng_std/features/training/session/session_grading.dart';
import 'package:eng_std/l10n/app_localizations.dart';

/// `dictation`: the example sentence is spoken, the learner writes it down.
///
/// It reuses listening's card and typing's input on purpose — these tests pin the parts where
/// reuse could quietly become a copy, and the one thing that is genuinely its own: no written cue.
void main() {
  late List<({String text, bool slow})> spoken;

  setUp(() => spoken = []);

  Future<void> onSpeak(String text, {bool slow = false}) async => spoken.add((text: text, slow: slow));

  const sentence = 'I have a reservation for tonight.';

  SessionCard dictationCard() => SessionCard(
        termId: 'T1',
        mode: ExerciseMode.dictation,
        type: 'word',
        prompt: null, // the audio IS the task
        answer: sentence,
        example: sentence,
        exampleTranslation: 'У меня бронь на сегодня.',
      );

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
                autoPronounce: false,
                onAnswered: (_) {},
                onSpeak: onSpeak,
                showDue: false,
              ),
            ),
          ),
        ),
      );

  group('the card', () {
    testWidgets('never shows the sentence — it is the answer', (tester) async {
      await tester.pumpWidget(host(dictationCard()));
      await tester.pump();

      expect(find.text(sentence), findsNothing);
      // …and no written cue either: a translation on screen would make it a translation exercise.
      expect(find.text('У меня бронь на сегодня.'), findsNothing);
      expect(find.text('прослушай и запиши предложение'), findsOneWidget);
    });

    testWidgets('speaks the sentence on appearance, after the slide (F20)', (tester) async {
      await tester.pumpWidget(host(dictationCard()));

      // Nothing during the transition: the audio is scheduled, not fired inline.
      expect(spoken, isEmpty);

      await tester.pump(const Duration(milliseconds: 300));
      expect(spoken, [(text: sentence, slow: false)]);
    });

    testWidgets('offers replay and a slowed replay, exactly as listening does', (tester) async {
      await tester.pumpWidget(host(dictationCard()));
      await tester.pump(const Duration(milliseconds: 300));
      spoken.clear();

      await tester.tap(find.text('Замедленно'));
      await tester.pump();

      expect(spoken, [(text: sentence, slow: true)]);
    });

    testWidgets('keeps typing\'s «Не помню» — one code path, not a copy', (tester) async {
      // It is `isTyped` that puts the hint row on the card; dictation opts in there rather than
      // growing its own button.
      expect(ExerciseMode.dictation.isTyped, isTrue);

      await tester.pumpWidget(host(dictationCard()));
      await tester.pump(const Duration(milliseconds: 300));

      expect(find.text('Не помню'), findsOneWidget);

      await tester.tap(find.text('Не помню'));
      await tester.pump();
      // The correct form «writes itself» character by character (§4е), so give it its 350 ms.
      await tester.pump(const Duration(milliseconds: 500));

      // An empty answer is a miss, and the correct sentence is revealed.
      expect(find.textContaining('Не то'), findsOneWidget);
      expect(find.text(sentence), findsWidgets);
    });

    testWidgets('does not autofocus — let the sentence be heard first', (tester) async {
      await tester.pumpWidget(host(dictationCard()));
      await tester.pump(const Duration(milliseconds: 400));

      expect(tester.testTextInput.isVisible, isFalse);
    });

    test('reads as a review-phase card in the header', () {
      expect(phaseFor(ExerciseMode.dictation), SessionPhase.review);
    });
  });

  group('grading is typing\'s, unchanged', () {
    test('accepts the sentence without the punctuation nobody types by ear', () {
      expect(SessionGrader.check('i have a reservation for tonight', sentence).isAccepted, isTrue);
      expect(SessionGrader.check('I have a reservation for tonight.', sentence).isAccepted, isTrue);
    });

    test('rejects a mis-heard word', () {
      expect(SessionGrader.check('I have a registration for tonight', sentence).isAccepted, isFalse);
    });

    test('an empty answer («Не помню») is a miss', () {
      expect(SessionGrader.check('', sentence), LocalCheck.wrong);
    });
  });

  group('the gate', () {
    bool dictates(String answer, String? example, [String? translation]) =>
        TermPlayability.of(answer: answer, example: example, exampleTranslation: translation)
            .supports(ExerciseMode.dictation);

    test('honours the 4…10 window at both edges', () {
      String of(int words) => '${List.filled(words, 'word').join(' ')}.';

      expect(dictates('word', of(3)), isFalse);
      expect(dictates('word', of(4)), isTrue);
      expect(dictates('word', of(10)), isTrue);
      expect(dictates('word', of(11)), isFalse);
    });

    test('stops lower than scramble — the two ceilings are different numbers', () {
      final eleven = '${List.filled(11, 'word').join(' ')}.';
      final p = TermPlayability.of(answer: 'word', example: eleven, exampleTranslation: 'Перевод.');

      expect(p.supports(ExerciseMode.scramble), isTrue);
      expect(p.supports(ExerciseMode.dictation), isFalse);
    });

    test('needs no translation, unlike scramble', () {
      expect(dictates('reservation', sentence), isTrue); // no translation passed
      expect(
        TermPlayability.of(answer: 'reservation', example: sentence).supports(ExerciseMode.scramble),
        isFalse,
      );
    });

    test('refuses an example that is merely the term — that is listening', () {
      expect(dictates('Nice to meet you', 'Nice to meet you.'), isFalse);
    });

    test('refuses a term with no example', () {
      expect(dictates('towel', null), isFalse);
    });
  });

  group('offline practice', () {
    Term term(String id, String text, String example) => Term(
          id: id,
          termText: text,
          type: 'word',
          transcription: null,
          translation: 'перевод',
          example: example,
          exampleTranslation: 'перевод примера',
          imageUrl: null,
          imageAuthor: null,
          imageAuthorUrl: null,
          updatedAt: DateTime.utc(2026, 8, 12),
        );

    test('deals dictation once it is switched on, and never before', () {
      final terms = [
        term('01KZETAAA50EMHCN6SP80T8DHC', 'reservation', 'I have a reservation for tonight.'),
        term('01KZETAAB4AW6M9ZFRB3X02CVW', 'towel', 'I need a clean towel, please.'),
        term('01KZETAAC103WZ24WQ7H087ZJ3', 'sheets', 'Could I have extra sheets, please?'),
      ];

      Set<ExerciseMode> dealt(PracticeModes enabled) => LocalPracticeSessionBuilder.build(
            terms: terms,
            limit: 20,
            random: Random(5),
            sessionId: 'S',
            enabled: enabled,
            // Dictation is a rung-5 trainer, so the pairs have to BE at rung 5 — this test is about
            // the TOGGLE, and the ladder is a separate filter with its own tests.
            ladder: {
              for (final t in terms)
                t.id: const LadderPosition(acquisition: Acquisition.graduated, successfulReviews: 12, enrolled: true),
            },
          ).cards.map((c) => c.mode).toSet();

      // The shipped default has no dictation in it — the release rule, seen from the device.
      expect(PracticeModes.serverDefault.modes, isNot(contains(ExerciseMode.dictation)));
      expect(dealt(PracticeModes.serverDefault), isNot(contains(ExerciseMode.dictation)));

      // Turned on for this user (the set arrives via /sync), it is dealt offline like any other.
      expect(dealt(const PracticeModes([ExerciseMode.dictation])), {ExerciseMode.dictation});
    });

    test('the offline card matches the server\'s: no cue, answer is the sentence', () {
      final session = LocalPracticeSessionBuilder.build(
        terms: [term('01KZETAAA50EMHCN6SP80T8DHC', 'reservation', sentence)],
        limit: 5,
        random: Random(1),
        sessionId: 'S',
        enabled: const PracticeModes([ExerciseMode.dictation]),
        ladder: const {
          '01KZETAAA50EMHCN6SP80T8DHC':
              LadderPosition(acquisition: Acquisition.graduated, successfulReviews: 12, enrolled: true),
        },
      );

      final card = session.cards.single;
      expect(card.mode, ExerciseMode.dictation);
      expect(card.prompt, isNull);
      expect(card.answer, sentence);
      expect(card.chips, isNull);
      expect(card.options, isNull);
    });
  });
}
