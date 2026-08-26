import 'dart:async';

import 'package:drift/drift.dart' show Value;
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
  _FakeRecognizer(this._script, {this.isReady = true, this.completeOnStop = false});

  final List<SpeechAttempt> _script;

  @override
  final bool isReady;

  /// When true, `listenOnce` does not settle on its own — only [stop] resolves it, mirroring the
  /// real plugin (a stop() call is what delivers the final transcript). Needed to test the manual
  /// «Готово» path, where the test must be able to tap it WHILE the card still thinks it is
  /// listening; the default (false) matches every other test's "the mic just heard it" shape.
  final bool completeOnStop;
  Completer<SpeechAttempt>? _pending;

  int calls = 0;
  int cancels = 0;
  int stops = 0;

  /// The words the card handed over as the accuracy hint, per call.
  final List<List<String>> expectedPerCall = [];
  final List<String> locales = [];
  final List<Duration> timeoutsPerCall = [];
  final List<Duration> pauseForsPerCall = [];

  /// The `contextualStrings` vocabulary hint handed over per call — see this class's own doc:
  /// this is what QA-20's fix actually sends the recogniser, [expectedPerCall] only picks the
  /// taskHint.
  final List<List<String>> contextualStringsPerCall = [];

  @override
  Future<bool> prepare() async => isReady;

  @override
  Future<bool> get hasPermission async => isReady;

  @override
  Future<SpeechAttempt> listenOnce({
    required List<String> expected,
    required String localeId,
    Duration timeout = const Duration(seconds: 8),
    Duration pauseFor = const Duration(seconds: 2),
    List<String> contextualStrings = const [],
    ValueChanged<String>? onPartial,
  }) async {
    expectedPerCall.add(expected);
    locales.add(localeId);
    timeoutsPerCall.add(timeout);
    pauseForsPerCall.add(pauseFor);
    contextualStringsPerCall.add(contextualStrings);
    final attempt = _script[calls.clamp(0, _script.length - 1)];
    calls++;
    if (attempt.isHeard) onPartial?.call(attempt.text);

    if (completeOnStop) {
      final completer = Completer<SpeechAttempt>();
      _pending = completer;

      return completer.future;
    }

    return attempt;
  }

  @override
  Future<void> stop() async {
    stops++;
    final pending = _pending;
    _pending = null;
    if (pending != null && !pending.isCompleted) {
      pending.complete(_script[(calls - 1).clamp(0, _script.length - 1)]);
    }
  }

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

  // [db] lets a test seed the local mirror BEFORE the card ever reads it — needed to give the
  // example-form's contextualStrings lookup (`_term` in session_exercise.dart, mirroring
  // `_PromptPhotoState._term`) a `termText` to find. Every other test doesn't care and gets a
  // fresh empty one, same as before.
  Widget host(SessionCard card, _FakeRecognizer recognizer, {AppDatabase? db}) => ProviderScope(
    overrides: [
      appDatabaseProvider.overrideWith((ref) {
        final database = db ?? AppDatabase.forTesting(NativeDatabase.memory());
        ref.onDispose(database.close);
        return database;
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
            answerLang: 'en',
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

  /// A term that is itself a whole phrase, still asked on the WORD form (rung: assembly).
  SessionCard longTermWordCard() => SessionCard(
    termId: 'T3',
    mode: ExerciseMode.speaking,
    type: 'phrase',
    prompt: 'кем вы видите себя через пять лет?',
    answer: 'Where do you see yourself in five years?',
    ladderStep: LearningLadder.stepAssembly,
  );

  /// A word-form phrase whose only fragile part is the article.
  SessionCard articleCard() => SessionCard(
    termId: 'T4',
    mode: ExerciseMode.speaking,
    type: 'phrase',
    prompt: 'вы командный игрок?',
    answer: 'Are you a team player?',
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
  Finder promptPhotos() =>
      find.byWidgetPredicate((w) => w.runtimeType.toString() == '_PromptPhoto');

  /// The style of the span carrying [word], wherever in the tree it is drawn — same pattern as
  /// own_answer_verdict_test.dart's helper of the same name.
  TextStyle? styleOf(WidgetTester tester, String word) {
    for (final text in tester.widgetList<Text>(find.byType(Text))) {
      final span = text.textSpan;
      if (span == null) continue;
      TextStyle? found;
      span.visitChildren((child) {
        if (child is TextSpan && (child.text ?? '') == word) {
          found = child.style;
          return false;
        }
        return true;
      });
      if (found != null) return found;
    }
    return null;
  }

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

    testWidgets('commits a recognised utterance as the answer, graded like any other', (
      tester,
    ) async {
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

    testWidgets('a recognised WRONG word is a verdict on the first try, not a retry', (
      tester,
    ) async {
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

    testWidgets('offers «Пропустить» from the FIRST channel failure, not before (QA-OBS-7)', (
      tester,
    ) async {
      final recognizer = _FakeRecognizer([const SpeechAttempt.silent()]);
      await tester.pumpWidget(host(wordCard(), recognizer));
      await tester.pumpAndSettle();

      // An escape hatch offered before it is needed reads as «this probably won't work».
      expect(find.text('Пропустить'), findsNothing);

      await record(tester);

      // …but the moment the card says the channel let them down, the way out is on screen with it:
      // the message and the button are the same fact. Two more taps to reach it left «Не помню» —
      // a lapse — as the only action, which is the scheduler punishing a hardware refusal.
      expect(find.textContaining('Не расслышал'), findsOneWidget);
      expect(find.text('Пропустить'), findsOneWidget);
    });

    testWidgets('a denied microphone offers the way out with its first message', (tester) async {
      final recognizer = _FakeRecognizer([const SpeechAttempt.unavailable()], isReady: false);
      await tester.pumpWidget(host(wordCard(), recognizer));
      await tester.pumpAndSettle();

      await record(tester);

      // The copy promises «Можно пропустить эту карточку» — the button has to be there to keep it.
      expect(find.textContaining('Микрофон недоступен'), findsOneWidget);
      expect(find.text('Пропустить'), findsOneWidget);
      // The other exit is still «Не помню», untouched: a learner who HAS forgotten may still say so.
      expect(find.text('Не помню'), findsOneWidget);
      expect(answers, isEmpty);
    });

    testWidgets('the escape hatch stays put once the attempt budget is spent', (tester) async {
      final recognizer = _FakeRecognizer([const SpeechAttempt.silent()]);
      await tester.pumpWidget(host(wordCard(), recognizer));
      await tester.pumpAndSettle();

      for (var i = 0; i < SpokenAnswer.maxChannelAttempts; i++) {
        await record(tester);
      }
      expect(find.text('Пропустить'), findsOneWidget);
    });

    testWidgets('skipping records no answer at all — only the shell is told', (tester) async {
      final recognizer = _FakeRecognizer([const SpeechAttempt.silent()]);
      await tester.pumpWidget(host(wordCard(), recognizer));
      await tester.pumpAndSettle();

      await record(tester);
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

    testWidgets('shows what the recogniser heard on a CORRECT word-form verdict too', (
      tester,
    ) async {
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

  group('recording window (QA-20)', () {
    testWidgets('the word form uses the shorter window', (tester) async {
      final recognizer = _FakeRecognizer([const SpeechAttempt.heard('reservation')]);
      await tester.pumpWidget(host(wordCard(), recognizer));
      await tester.pumpAndSettle();

      await record(tester);

      expect(recognizer.timeoutsPerCall.single, SpokenAnswer.wordFormListenFor);
      expect(recognizer.pauseForsPerCall.single, SpokenAnswer.wordFormPauseFor);
    });

    testWidgets('the example form uses the longer window', (tester) async {
      final recognizer = _FakeRecognizer([
        const SpeechAttempt.heard('Could you take a photo of us?'),
      ]);
      await tester.pumpWidget(host(exampleCard(), recognizer));
      await tester.pumpAndSettle();

      await record(tester);

      expect(recognizer.timeoutsPerCall.single, SpokenAnswer.exampleFormListenFor);
      expect(recognizer.pauseForsPerCall.single, SpokenAnswer.exampleFormPauseFor);
    });

    testWidgets('a PHRASE-shaped term on the word form also gets the longer window (QA-21)', (
      tester,
    ) async {
      // The live case: an 8s/2s window cut this reading off after the first word («Heard: When»).
      // Still the word form — the card asks for the term, not the example — but what is being said
      // is sentence-length, and the window has to fit that.
      final recognizer = _FakeRecognizer([
        const SpeechAttempt.heard('Where do you see yourself in five years'),
      ]);
      await tester.pumpWidget(host(longTermWordCard(), recognizer));
      await tester.pumpAndSettle();

      await record(tester);

      expect(recognizer.timeoutsPerCall.single, SpokenAnswer.exampleFormListenFor);
      expect(recognizer.pauseForsPerCall.single, SpokenAnswer.exampleFormPauseFor);
    });
  });

  group('an article the microphone ate (QA-21)', () {
    testWidgets('a word-form phrase missing only «a» is accepted, not failed', (tester) async {
      // The live case: «Are you a team player?» read correctly, transcribed without the article.
      final recognizer = _FakeRecognizer([const SpeechAttempt.heard('Are you team player')]);
      await tester.pumpWidget(host(articleCard(), recognizer));
      await tester.pumpAndSettle();

      await record(tester);

      expect(answers.single.verdict, LocalCheck.correct);
      // The RAW transcript still goes up untouched — the server grades it, this only fixes what the
      // phone SHOWS in the meantime.
      expect(answers.single.response, 'Are you team player');
    });

    testWidgets('a genuinely different answer is still wrong', (tester) async {
      final recognizer = _FakeRecognizer([const SpeechAttempt.heard('Do you like coffee')]);
      await tester.pumpWidget(host(articleCard(), recognizer));
      await tester.pumpAndSettle();

      await record(tester);

      expect(answers.single.verdict, LocalCheck.wrong);
    });

    testWidgets('ONE wrong content word in a short phrase now passes — the 70% trade-off', (
      tester,
    ) async {
      // «Are you a team player?» → «are you team leader»: with the article dropped the target is
      // four tokens, so a single wrong one is 3/4 = 75%, over the threshold. This is the specified
      // consequence of coverage-grading a phrase at 70% (QA-22) and is pinned, not accidental —
      // before QA-22 this card was binary and this reading failed. The shorter the phrase, the
      // coarser the threshold's grain; the server is the grader that decides what it counts.
      final recognizer = _FakeRecognizer([const SpeechAttempt.heard('Are you a team leader')]);
      await tester.pumpWidget(host(articleCard(), recognizer));
      await tester.pumpAndSettle();

      await record(tester);

      expect(answers.single.verdict, LocalCheck.correct);
    });
  });

  group('a phrase-shaped term on the word form is graded by coverage (QA-22)', () {
    /// The live case, verbatim: the reading was correct, the recogniser mangled the middle.
    SessionCard conflictCard() => SessionCard(
      termId: 'T5',
      mode: ExerciseMode.speaking,
      type: 'phrase',
      prompt: 'как вы справляетесь с конфликтами?',
      answer: 'How do you deal with conflict?',
      ladderStep: LearningLadder.stepAssembly,
    );

    testWidgets('«How do you deal this a conflict?» counts — 5 of 6, article forgiven', (
      tester,
    ) async {
      final recognizer = _FakeRecognizer([
        const SpeechAttempt.heard('How do you deal this a conflict?'),
      ]);
      await tester.pumpWidget(host(conflictCard(), recognizer));
      await tester.pumpAndSettle();

      await record(tester);

      // Binary equality marked this wrong, which is the bug: the learner read it correctly.
      expect(answers.single.verdict, LocalCheck.correct);
      // The RAW transcript still goes up untouched — the server is the grader.
      expect(answers.single.response, 'How do you deal this a conflict?');
    });

    testWidgets('a reading that really is short still fails', (tester) async {
      // Two of six words: nowhere near the 70% the threshold asks for.
      final recognizer = _FakeRecognizer([const SpeechAttempt.heard('How do')]);
      await tester.pumpWidget(host(conflictCard(), recognizer));
      await tester.pumpAndSettle();

      await record(tester);

      expect(answers.single.verdict, LocalCheck.wrong);
    });

    testWidgets('a SHORT term keeps binary grading — one wrong word is still wrong', (
      tester,
    ) async {
      // 'reservation' vs 'registration' shares no coverage concept at all: the word form of a
      // one-word term is exactly where equality is the fair question.
      final recognizer = _FakeRecognizer([const SpeechAttempt.heard('registration')]);
      await tester.pumpWidget(host(wordCard(), recognizer));
      await tester.pumpAndSettle();

      await record(tester);

      expect(answers.single.verdict, LocalCheck.wrong);
    });

    testWidgets('a WRONG reading marks the target words that never registered', (tester) async {
      // A deliberate «Готово» on a short reading, so the verdict is wrong and the reveal (which is
      // where the highlight lives) is shown. Before QA-22 the word form had no highlight at all —
      // it typed the term out whole, because it was never compared word by word.
      final recognizer = _FakeRecognizer([
        const SpeechAttempt.heard('How do you'),
      ], completeOnStop: true);
      await tester.pumpWidget(host(conflictCard(), recognizer));
      await tester.pumpAndSettle();

      await tester.tap(recordButton().first); // start
      await tester.pump();
      await tester.tap(recordButton().first); // «Готово»
      await tester.pumpAndSettle();

      expect(answers.single.verdict, LocalCheck.wrong);
      // «deal with conflict?» never registered; «How do you» did.
      expect(styleOf(tester, 'conflict?')?.decorationStyle, TextDecorationStyle.wavy);
      expect(styleOf(tester, 'deal')?.decorationStyle, TextDecorationStyle.wavy);
      expect(styleOf(tester, 'How')?.decorationStyle, isNot(TextDecorationStyle.wavy));
    });
  });

  group('a pause cutoff on the example form (QA-20 finding iii)', () {
    testWidgets('a low-coverage reading is retried, not finalized as wrong', (tester) async {
      // The recogniser's own window closed on it (no manual «Готово»), and it only caught half the
      // sentence — a stumble or a channel cutoff, not a wrong answer.
      final recognizer = _FakeRecognizer([
        const SpeechAttempt.heard('could you take'), // 3 of 7 words: well under 70%
        const SpeechAttempt.heard('Could you take a photo of us?'),
      ]);
      await tester.pumpWidget(host(exampleCard(), recognizer));
      await tester.pumpAndSettle();

      await record(tester);

      // No verdict spent — the card is still open, offering another try, exactly like a channel
      // failure (the same budget, the same "Не расслышал" line).
      expect(answers, isEmpty);
      expect(find.textContaining('Не расслышал'), findsOneWidget);
      expect(recordButton(), findsWidgets);

      // The retry succeeds normally and commits like any other attempt.
      await record(tester);
      expect(answers, hasLength(1));
      expect(answers.single.verdict, LocalCheck.correct);
    });

    testWidgets('a deliberate «Готово» on a short reading is graded as the final answer', (
      tester,
    ) async {
      // Same low-coverage transcript as above, but this time the learner tapped «Готово»
      // themselves — their own choice to stop, not the recogniser cutting them off, so it is
      // graded as-is like every other trainer's honest wrong answer. `completeOnStop` holds the
      // attempt open until stop() fires, exactly like the real plugin, so the tap lands while the
      // card still thinks it is listening.
      final recognizer = _FakeRecognizer([
        const SpeechAttempt.heard('could you take'),
      ], completeOnStop: true);
      await tester.pumpWidget(host(exampleCard(), recognizer));
      await tester.pumpAndSettle();

      await tester.tap(recordButton().first); // start
      await tester.pump();
      expect(recognizer.calls, 1, reason: 'listening has begun, but the attempt has not settled');

      await tester.tap(recordButton().first); // «Готово» — manual stop
      await tester.pumpAndSettle();

      expect(recognizer.stops, 1);
      expect(answers, hasLength(1));
      expect(answers.single.verdict, LocalCheck.wrong);
    });

    testWidgets('does not apply to the word form — no coverage concept there', (tester) async {
      // A one-character-off word is graded (wrong) on the first try, exactly as before — the
      // cutoff guard is scoped to the sentence form, which is the only one with a coverage bar.
      final recognizer = _FakeRecognizer([const SpeechAttempt.heard('registration')]);
      await tester.pumpWidget(host(wordCard(), recognizer));
      await tester.pumpAndSettle();

      await record(tester);

      expect(answers, hasLength(1));
      expect(answers.single.verdict, LocalCheck.wrong);
    });
  });

  group('the uncovered-word highlight (QA-20)', () {
    testWidgets('marks the tail of the target sentence a short reading never reached', (
      tester,
    ) async {
      // A deliberate «Готово» on a short reading — graded wrong (Part 4.4's cutoff guard does not
      // apply, since this is not an automatic timeout) and short by its own tail.
      final recognizer = _FakeRecognizer([
        const SpeechAttempt.heard('could you take'),
      ], completeOnStop: true);
      await tester.pumpWidget(host(exampleCard(), recognizer));
      await tester.pumpAndSettle();

      await tester.tap(recordButton().first); // start
      await tester.pump();
      await tester.tap(recordButton().first); // «Готово»
      await tester.pumpAndSettle();

      expect(answers.single.verdict, LocalCheck.wrong);
      // "Could you take a photo of us?" — "could you take" heard, "a photo of us?" is the tail.
      expect(styleOf(tester, 'us?')?.decorationStyle, TextDecorationStyle.wavy);
      expect(styleOf(tester, 'photo')?.decorationStyle, TextDecorationStyle.wavy);
      // The part that WAS heard stays unmarked.
      expect(styleOf(tester, 'Could')?.decorationStyle, isNot(TextDecorationStyle.wavy));
      expect(styleOf(tester, 'take')?.decorationStyle, isNot(TextDecorationStyle.wavy));
    });
  });

  group('«не помню»', () {
    testWidgets('is the one exit that writes — an honest lapse, available immediately', (
      tester,
    ) async {
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

  group('contextualStrings (QA-20 — the recogniser vocabulary hint)', () {
    testWidgets('word form: the term whole, plus its individual words', (tester) async {
      final recognizer = _FakeRecognizer([const SpeechAttempt.heard('reservation')]);
      await tester.pumpWidget(host(wordCard(), recognizer));
      await tester.pumpAndSettle();

      await record(tester);

      final sent = recognizer.contextualStringsPerCall.single;
      expect(sent, containsAll(['reservation']));
      // acceptedVariants ('booking') is the taskHint's job (expectedPerCall), not this list's — the
      // work order asks only for the term itself and its words here.
      expect(sent.toSet().length, sent.length, reason: 'deduplicated');
    });

    testWidgets('example form: the unique example words, plus the term looked up locally', (
      tester,
    ) async {
      final db = AppDatabase.forTesting(NativeDatabase.memory());
      await db
          .into(db.terms)
          .insert(
            TermsCompanion.insert(
              id: 'T2',
              updatedAt: DateTime.utc(2026, 8, 19),
              termText: const Value('take a photo'),
            ),
          );
      final recognizer = _FakeRecognizer([
        const SpeechAttempt.heard('could you take a photo of us'),
      ]);
      await tester.pumpWidget(host(exampleCard(), recognizer, db: db));
      await tester.pumpAndSettle();

      await record(tester);

      final sent = recognizer.contextualStringsPerCall.single;
      expect(
        sent,
        containsAll(['Could', 'you', 'take', 'a', 'photo', 'of', 'us?', 'take a photo']),
      );
      expect(sent.toSet().length, sent.length, reason: 'deduplicated (e.g. "a" appears once)');
    });

    testWidgets('example form with no local term row: falls back to just the example words', (
      tester,
    ) async {
      // No termById row for T2 — the DB is fresh/unsynced. The lookup returns null, and the card
      // must not crash or hang on it (this is the exact «без параметра» backward-compat shape one
      // layer up, at the app's own call site rather than the plugin's).
      final recognizer = _FakeRecognizer([
        const SpeechAttempt.heard('could you take a photo of us'),
      ]);
      await tester.pumpWidget(host(exampleCard(), recognizer));
      await tester.pumpAndSettle();

      await record(tester);

      final sent = recognizer.contextualStringsPerCall.single;
      expect(sent, containsAll(['Could', 'you', 'take', 'a', 'photo', 'of', 'us?']));
      expect(sent, isNotEmpty);
    });
  });
}
