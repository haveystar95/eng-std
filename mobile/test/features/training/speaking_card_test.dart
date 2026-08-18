import 'package:drift/native.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';

import 'package:eng_std/data/local/app_database.dart';
import 'package:eng_std/data/models.dart';
import 'package:eng_std/data/practice/learning_ladder.dart';
import 'package:eng_std/data/providers.dart';
import 'package:eng_std/data/speech/speech_recognizer.dart';
import 'package:eng_std/features/training/session/session_exercise.dart';
import 'package:eng_std/features/training/session/session_grading.dart';
import 'package:eng_std/l10n/app_localizations.dart';

/// A recogniser that returns whatever the test says, in order.
///
/// The seam exists for exactly this: a simulator has no microphone, so the only way to test what
/// the card does with silence, with a mis-hearing and with a clean utterance is to hand it those
/// three things directly. What CANNOT be tested here is whether a real SFSpeechRecognizer hears a
/// real person say «reservation» — that is the owner's device pass.
class _FakeRecognizer implements SpeechRecognizer {
  _FakeRecognizer(this._script, {this.isReady = true});

  final List<SpeechAttempt> _script;

  @override
  final bool isReady;

  int calls = 0;
  int cancels = 0;

  /// The words the card handed over as the accuracy hint, per call.
  final List<List<String>> expectedPerCall = [];
  final List<String> locales = [];

  @override
  Future<bool> prepare() async => isReady;

  @override
  Future<SpeechAttempt> listenOnce({
    required List<String> expected,
    required String localeId,
    Duration timeout = const Duration(seconds: 8),
    ValueChanged<String>? onPartial,
  }) async {
    expectedPerCall.add(expected);
    locales.add(localeId);
    final attempt = _script[calls.clamp(0, _script.length - 1)];
    calls++;
    if (attempt.isHeard) onPartial?.call(attempt.text);

    return attempt;
  }

  @override
  Future<void> stop() async {}

  @override
  Future<void> cancel() async => cancels++;
}

void main() {
  late List<SessionAnswer> answers;
  late int skips;

  setUp(() {
    answers = [];
    skips = 0;
  });

  Widget host(SessionCard card, _FakeRecognizer recognizer) => ProviderScope(
        overrides: [
          appDatabaseProvider.overrideWith((ref) {
            final db = AppDatabase.forTesting(NativeDatabase.memory());
            ref.onDispose(db.close);
            return db;
          }),
          speechRecognizerProvider.overrideWithValue(recognizer),
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
                onAnswered: answers.add,
                onSkipped: () => skips++,
                onSpeak: (String text, {bool slow = false}) async {},
                speechLocaleId: 'en_US',
                showDue: false,
              ),
            ),
          ),
        ),
      );

  SessionCard wordCard() => SessionCard(
        termId: 'T1',
        mode: ExerciseMode.speaking,
        type: 'word',
        prompt: 'бронь',
        answer: 'reservation',
        example: 'I have a reservation for tonight.',
        exampleTranslation: 'У меня бронь на сегодня.',
        acceptedVariants: const ['booking'],
        ladderStep: LearningLadder.stepAssembly,
      );

  SessionCard exampleCard() => SessionCard(
        termId: 'T2',
        mode: ExerciseMode.speaking,
        type: 'word',
        prompt: 'фото',
        answer: 'Could you take a photo of us?',
        example: 'Could you take a photo of us?',
        exampleTranslation: 'Не могли бы вы нас сфотографировать?',
        ladderStep: LearningLadder.stepDictation,
      );

  // The record button is a Semantics-labelled circle; find it by its label rather than by icon so
  // the test does not depend on which icon pack is in use.
  Finder recordButton() => find.bySemanticsLabel(RegExp('Сказать|Готово'));

  // `_PromptPhoto` is private to session_exercise.dart, but runtimeType.toString() ignores library
  // privacy — the standard way to count instances of a private widget from an external test file.
  Finder promptPhotos() => find.byWidgetPredicate((w) => w.runtimeType.toString() == '_PromptPhoto');

  Future<void> record(WidgetTester tester) async {
    await tester.tap(recordButton().first);
    await tester.pumpAndSettle();
  }

  group('the word form', () {
    testWidgets('shows the translation and never the term — it is free recall', (tester) async {
      await tester.pumpWidget(host(wordCard(), _FakeRecognizer([const SpeechAttempt.silent()])));
      await tester.pumpAndSettle();

      expect(find.text('бронь'), findsOneWidget);
      // Printing the word being recalled would make this «read it aloud», which is the OTHER form.
      expect(find.text('reservation'), findsNothing);
      expect(find.textContaining('скажи слово вслух'), findsOneWidget);
    });

    testWidgets('commits a recognised utterance as the answer, graded like any other', (tester) async {
      final recognizer = _FakeRecognizer([const SpeechAttempt.heard('Reservation')]);
      await tester.pumpWidget(host(wordCard(), recognizer));
      await tester.pumpAndSettle();

      await record(tester);

      expect(answers, hasLength(1));
      // The RAW transcript goes up — the server grades it, exactly as it grades typed text.
      expect(answers.single.response, 'Reservation');
      expect(answers.single.verdict, LocalCheck.correct);
      expect(skips, 0);
    });

    testWidgets('hands the recogniser the words it is hoping for', (tester) async {
      final recognizer = _FakeRecognizer([const SpeechAttempt.heard('reservation')]);
      await tester.pumpWidget(host(wordCard(), recognizer));
      await tester.pumpAndSettle();

      await record(tester);

      // The answer AND its accepted variants: a learner who says «booking» said a correct answer,
      // so the engine should have been told it might hear that too.
      expect(recognizer.expectedPerCall.single, containsAll(['reservation', 'booking']));
      expect(recognizer.locales.single, 'en_US');
    });

    testWidgets('a recognised WRONG word is a verdict on the first try, not a retry', (tester) async {
      final recognizer = _FakeRecognizer([const SpeechAttempt.heard('registration')]);
      await tester.pumpWidget(host(wordCard(), recognizer));
      await tester.pumpAndSettle();

      await record(tester);

      // The channel worked. Retrying here would let someone fish for the right word, and the
      // attempts budget exists for the microphone, not for the memory.
      expect(answers.single.verdict, LocalCheck.wrong);
      expect(answers.single.response, 'registration');
    });
  });

  group('the example form', () {
    testWidgets('shows the sentence, because reading it IS the task', (tester) async {
      await tester.pumpWidget(host(exampleCard(), _FakeRecognizer([const SpeechAttempt.silent()])));
      await tester.pumpAndSettle();

      expect(find.text('Could you take a photo of us?'), findsOneWidget);
      expect(find.textContaining('прочитай предложение вслух'), findsOneWidget);
    });

    testWidgets('accepts a reading the recogniser mangled', (tester) async {
      // The article eaten, no question mark: the reading was fine, the transcript is not exact.
      final recognizer = _FakeRecognizer([const SpeechAttempt.heard('could you take photo of us')]);
      await tester.pumpWidget(host(exampleCard(), recognizer));
      await tester.pumpAndSettle();

      await record(tester);

      // Equality would show «Не то» over an answer the server counts as correct — the one direction
      // the client's instant read is forbidden to take.
      expect(answers.single.verdict, LocalCheck.correct);
    });
  });

  group('the channel', () {
    testWidgets('silence spends an attempt and writes NOTHING', (tester) async {
      final recognizer = _FakeRecognizer([const SpeechAttempt.silent()]);
      await tester.pumpWidget(host(wordCard(), recognizer));
      await tester.pumpAndSettle();

      await record(tester);

      expect(answers, isEmpty, reason: 'a microphone that heard nothing is not an answer');
      expect(skips, 0);
      expect(find.textContaining('Не расслышал'), findsOneWidget);
      // Still answerable: the card did not lock itself after one failure.
      expect(recordButton(), findsWidgets);
    });

    testWidgets('offers «Пропустить» only after the microphone has really failed', (tester) async {
      final recognizer = _FakeRecognizer([const SpeechAttempt.silent()]);
      await tester.pumpWidget(host(wordCard(), recognizer));
      await tester.pumpAndSettle();

      // An escape hatch offered before it is needed reads as «this probably won't work».
      expect(find.text('Пропустить'), findsNothing);
      for (var i = 0; i < SpokenAnswer.maxChannelAttempts; i++) {
        expect(find.text('Пропустить'), findsNothing, reason: 'attempt ${i + 1} of the budget');
        await record(tester);
      }
      expect(find.text('Пропустить'), findsOneWidget);
    });

    testWidgets('skipping records no answer at all — only the shell is told', (tester) async {
      final recognizer = _FakeRecognizer([const SpeechAttempt.silent()]);
      await tester.pumpWidget(host(wordCard(), recognizer));
      await tester.pumpAndSettle();

      for (var i = 0; i < SpokenAnswer.maxChannelAttempts; i++) {
        await record(tester);
      }
      await tester.tap(find.text('Пропустить'));
      await tester.pumpAndSettle();

      // The whole trainer in one assertion: the room was noisy, so nothing about this word's
      // memory was recorded anywhere.
      expect(skips, 1);
      expect(answers, isEmpty);
    });

    testWidgets('an unavailable microphone says so, and is still not a lapse', (tester) async {
      final recognizer = _FakeRecognizer([const SpeechAttempt.unavailable()], isReady: false);
      await tester.pumpWidget(host(wordCard(), recognizer));
      await tester.pumpAndSettle();

      await record(tester);

      expect(find.textContaining('Микрофон недоступен'), findsOneWidget);
      expect(answers, isEmpty);
    });
  });

  group('the verdict (QA-20)', () {
    testWidgets('shows what the recogniser heard on a WRONG word-form verdict', (tester) async {
      final recognizer = _FakeRecognizer([const SpeechAttempt.heard('registration')]);
      await tester.pumpWidget(host(wordCard(), recognizer));
      await tester.pumpAndSettle();

      await record(tester);

      expect(find.textContaining('registration'), findsWidgets);
    });

    testWidgets('shows what the recogniser heard on a CORRECT word-form verdict too', (tester) async {
      final recognizer = _FakeRecognizer([const SpeechAttempt.heard('Reservation')]);
      await tester.pumpWidget(host(wordCard(), recognizer));
      await tester.pumpAndSettle();

      await record(tester);

      // Both outcomes: a correct verdict must not go silent about what was actually heard either.
      expect(find.textContaining('Reservation'), findsWidgets);
    });

    testWidgets('does not repeat the prompt photo in a wrong word-form verdict', (tester) async {
      final recognizer = _FakeRecognizer([const SpeechAttempt.heard('registration')]);
      await tester.pumpWidget(host(wordCard(), recognizer));
      await tester.pumpAndSettle();
      // The prompt's own photo is already on screen before any answer.
      expect(promptPhotos(), findsOneWidget);

      await record(tester);

      // Still exactly one — the feedback block must not add a second copy.
      expect(promptPhotos(), findsOneWidget);
    });

    testWidgets('shows no photo at all in a wrong example-form verdict', (tester) async {
      // The example form's prompt never shows a photo (reading the sentence is the task) — the
      // feedback must not introduce one either.
      final recognizer = _FakeRecognizer([const SpeechAttempt.heard('completely wrong reading')]);
      await tester.pumpWidget(host(exampleCard(), recognizer));
      await tester.pumpAndSettle();
      expect(promptPhotos(), findsNothing);

      await record(tester);

      expect(promptPhotos(), findsNothing);
    });
  });

  group('«не помню»', () {
    testWidgets('is the one exit that writes — an honest lapse, available immediately', (tester) async {
      final recognizer = _FakeRecognizer([const SpeechAttempt.silent()]);
      await tester.pumpWidget(host(wordCard(), recognizer));
      await tester.pumpAndSettle();

      // No microphone attempt needed first: someone who knows they have forgotten should not have
      // to fail three recordings to say so.
      await tester.tap(find.text('Не помню'));
      await tester.pumpAndSettle();

      expect(answers, hasLength(1));
      expect(answers.single.response, '', reason: 'the empty answer the server grades as `again`');
      expect(answers.single.verdict, LocalCheck.wrong);
      expect(skips, 0);
      expect(recognizer.calls, 0);
    });
  });
}
